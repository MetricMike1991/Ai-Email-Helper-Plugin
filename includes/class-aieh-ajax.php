<?php
/**
 * AJAX handlers for the admin UI. Every handler checks capability + nonce.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Ajax {

	const NONCE = 'aieh_ajax';

	/**
	 * Register all AJAX actions.
	 */
	public function register() {
		$actions = array(
			'aieh_test_imap'     => 'test_imap',
			'aieh_fetch'         => 'fetch',
			'aieh_summarize'     => 'summarize',
			'aieh_draft'         => 'draft',
			'aieh_send'          => 'send',
			'aieh_scan_faq'      => 'scan_faq',
			'aieh_delete_faq'    => 'delete_faq',
			'aieh_update_faq'    => 'update_faq',
			'aieh_update_learning' => 'update_learning',
			'aieh_delete_learning' => 'delete_learning',
			'aieh_ai_revise'     => 'ai_revise',
			'aieh_mark_read'     => 'mark_read',
			'aieh_mark_unread'   => 'mark_unread',
			'aieh_move'          => 'move',
			'aieh_folders'       => 'folders',
			'aieh_sync_unread'   => 'sync_unread',
			'aieh_set_category'  => 'set_category',
		);
		foreach ( $actions as $action => $method ) {
			add_action( "wp_ajax_{$action}", array( $this, $method ) );
		}
	}

	/**
	 * Shared guard: capability + nonce.
	 */
	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-email-helper' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
	}

	/**
	 * Test IMAP connection.
	 */
	public function test_imap() {
		$this->guard();
		$result = AIEH_Imap_Client::test_connection();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => sprintf( /* translators: %d: message count */ __( 'Connected. Mailbox has %d messages.', 'ai-email-helper' ), $result['messages'] ) ) );
	}

	/**
	 * Fetch recent emails now.
	 */
	public function fetch() {
		$this->guard();
		$new = AIEH_Imap_Client::fetch_recent();
		if ( is_wp_error( $new ) ) {
			wp_send_json_error( array( 'message' => $new->get_error_message() ) );
		}
		wp_send_json_success( array( 'new' => (int) $new, 'message' => sprintf( /* translators: %d: count */ _n( '%d new message.', '%d new messages.', $new, 'ai-email-helper' ), $new ) ) );
	}

	/**
	 * Summarize one message.
	 */
	public function summarize() {
		$this->guard();
		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$summary = AIEH_Email_Processor::summarize( $id );
		if ( is_wp_error( $summary ) ) {
			wp_send_json_error( array( 'message' => $summary->get_error_message() ) );
		}
		wp_send_json_success( array( 'summary' => wp_kses_post( $summary ) ) );
	}

	/**
	 * Draft a reply for one message.
	 */
	public function draft() {
		$this->guard();
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$draft = AIEH_Email_Processor::draft_reply( $id );
		if ( is_wp_error( $draft ) ) {
			wp_send_json_error( array( 'message' => $draft->get_error_message() ) );
		}
		wp_send_json_success( array( 'draft' => wp_kses_post( $draft ) ) );
	}

	/**
	 * Send an approved reply and record it as a learning example.
	 */
	public function send() {
		$this->guard();
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

		$msg = AIEH_Email_Processor::get_message( $id );
		if ( ! $msg ) {
			wp_send_json_error( array( 'message' => __( 'Message not found.', 'ai-email-helper' ) ) );
		}
		if ( '' === trim( $body ) ) {
			wp_send_json_error( array( 'message' => __( 'Reply body is empty.', 'ai-email-helper' ) ) );
		}

		$subject = 0 === stripos( $msg->subject, 'Re:' ) ? $msg->subject : 'Re: ' . $msg->subject;
		$sent    = AIEH_Smtp_Mailer::send( $msg->from_email, $subject, $body );
		if ( is_wp_error( $sent ) ) {
			wp_send_json_error( array( 'message' => $sent->get_error_message() ) );
		}

		AIEH_Learning_Store::record( $id, $msg->subject, $body );

		// Mark answered + seen on the server and locally.
		AIEH_Imap_Client::mark_replied( $id );

		wp_send_json_success( array( 'message' => __( 'Reply sent and saved for learning.', 'ai-email-helper' ) ) );
	}

	/**
	 * Scan a FAQ URL.
	 */
	public function scan_faq() {
		$this->guard();
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$row = AIEH_Faq_Scanner::scan_url( $url );
		if ( is_wp_error( $row ) ) {
			wp_send_json_error( array( 'message' => $row->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'message' => sprintf( /* translators: %d: word count */ __( 'Scanned %d words.', 'ai-email-helper' ), (int) $row['word_count'] ),
				'row'     => array(
					'id'         => (int) $row['id'],
					'title'      => esc_html( $row['title'] ),
					'url'        => esc_url( $row['source_url'] ),
					'word_count' => (int) $row['word_count'],
				),
			)
		);
	}

	/**
	 * Delete a FAQ source.
	 */
	public function delete_faq() {
		$this->guard();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		AIEH_Faq_Scanner::delete( $id );
		wp_send_json_success( array( 'message' => __( 'FAQ source removed.', 'ai-email-helper' ) ) );
	}

	/**
	 * Update a FAQ entry's title/content directly.
	 */
	public function update_faq() {
		$this->guard();
		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';

		if ( ! $id || '' === trim( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Content cannot be empty.', 'ai-email-helper' ) ) );
		}

		AIEH_Faq_Scanner::update( $id, $title, $content );
		wp_send_json_success( array( 'message' => __( 'FAQ entry saved.', 'ai-email-helper' ) ) );
	}

	/**
	 * Update a learning entry (e.g. when the underlying facts changed).
	 */
	public function update_learning() {
		$this->guard();
		$id             = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$prompt_summary = isset( $_POST['prompt_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt_summary'] ) ) : '';
		$user_reply     = isset( $_POST['user_reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['user_reply'] ) ) : '';
		$tone_notes     = isset( $_POST['tone_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tone_notes'] ) ) : '';

		if ( ! $id || '' === trim( $user_reply ) ) {
			wp_send_json_error( array( 'message' => __( 'Reply text cannot be empty.', 'ai-email-helper' ) ) );
		}

		AIEH_Learning_Store::update( $id, $prompt_summary, $user_reply, $tone_notes );
		wp_send_json_success( array( 'message' => __( 'Learning entry saved.', 'ai-email-helper' ) ) );
	}

	/**
	 * Delete a learning entry.
	 */
	public function delete_learning() {
		$this->guard();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		AIEH_Learning_Store::delete( $id );
		wp_send_json_success( array( 'message' => __( 'Learning entry removed.', 'ai-email-helper' ) ) );
	}

	/**
	 * Generic AI-assisted revision: rewrite a piece of text per a free-form instruction.
	 * Used to update learning replies / FAQ content when underlying facts change.
	 * Returns the suggestion only — caller must still Save to persist it.
	 */
	public function ai_revise() {
		$this->guard();
		$text        = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		$instruction = isset( $_POST['instruction'] ) ? sanitize_textarea_field( wp_unslash( $_POST['instruction'] ) ) : '';

		if ( '' === trim( $text ) || '' === trim( $instruction ) ) {
			wp_send_json_error( array( 'message' => __( 'Provide both the text and an instruction.', 'ai-email-helper' ) ) );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You revise text according to the instruction given. Keep the overall tone and style unless told otherwise. Output only the revised text, with no preamble, no quotes, and no explanation.',
			),
			array(
				'role'    => 'user',
				'content' => "Original text:\n{$text}\n\nInstruction: {$instruction}",
			),
		);

		$revised = AIEH_OpenAI_Client::chat( $messages, array( 'max_tokens' => 700, 'temperature' => 0.3 ) );
		if ( is_wp_error( $revised ) ) {
			wp_send_json_error( array( 'message' => $revised->get_error_message() ) );
		}

		wp_send_json_success( array( 'revised' => $revised ) );
	}

	/**
	 * Mark a message read (\Seen) on the server + locally.
	 */
	public function mark_read() {
		$this->guard();
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$result = AIEH_Imap_Client::set_seen( $id, true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'status' => 'read', 'message' => __( 'Marked as read.', 'ai-email-helper' ) ) );
	}

	/**
	 * Mark a message unread (clear \Seen) on the server + locally.
	 */
	public function mark_unread() {
		$this->guard();
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$result = AIEH_Imap_Client::set_seen( $id, false );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'status' => 'unread', 'message' => __( 'Marked as unread.', 'ai-email-helper' ) ) );
	}

	/**
	 * Move a message to another server folder.
	 */
	public function move() {
		$this->guard();
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$target = isset( $_POST['folder'] ) ? sanitize_text_field( wp_unslash( $_POST['folder'] ) ) : '';
		$result = AIEH_Imap_Client::move_message( $id, $target );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => sprintf( /* translators: %s: folder */ __( 'Moved to %s.', 'ai-email-helper' ), $target ) ) );
	}

	/**
	 * Return the list of server folders (for the Move-to dropdown).
	 */
	public function folders() {
		$this->guard();
		$force   = ! empty( $_POST['force'] );
		$folders = AIEH_Imap_Client::list_folders( $force );
		if ( is_wp_error( $folders ) ) {
			wp_send_json_error( array( 'message' => $folders->get_error_message() ) );
		}
		wp_send_json_success( array( 'folders' => array_map( 'strval', $folders ) ) );
	}

	/**
	 * Live hybrid sync of unread state from the server.
	 */
	public function sync_unread() {
		$this->guard();
		$count = AIEH_Imap_Client::sync_unread();
		if ( is_wp_error( $count ) ) {
			wp_send_json_error( array( 'message' => $count->get_error_message() ) );
		}
		wp_send_json_success( array( 'unread' => (int) $count, 'message' => sprintf( /* translators: %d: count */ _n( '%d unread message synced.', '%d unread messages synced.', $count, 'ai-email-helper' ), $count ) ) );
	}

	/**
	 * Set an internal category tag on a message (local only).
	 */
	public function set_category() {
		$this->guard();
		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Message not found.', 'ai-email-helper' ) ) );
		}
		global $wpdb;
		$table = AIEH_Activator::messages_table();
		$wpdb->update( $table, array( 'category' => substr( $category, 0, 60 ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		wp_send_json_success( array( 'category' => $category, 'message' => __( 'Category updated.', 'ai-email-helper' ) ) );
	}
}
