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
			'aieh_test_imap'  => 'test_imap',
			'aieh_fetch'      => 'fetch',
			'aieh_summarize'  => 'summarize',
			'aieh_draft'      => 'draft',
			'aieh_send'       => 'send',
			'aieh_scan_faq'   => 'scan_faq',
			'aieh_delete_faq' => 'delete_faq',
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

		global $wpdb;
		$table = AIEH_Activator::messages_table();
		$wpdb->update( $table, array( 'status' => 'replied' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB

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
		global $wpdb;
		$table = AIEH_Activator::faqs_table();
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		wp_send_json_success( array( 'message' => __( 'FAQ source removed.', 'ai-email-helper' ) ) );
	}
}
