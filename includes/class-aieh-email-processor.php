<?php
/**
 * Orchestrates the AI features: summarizing incoming email and drafting replies
 * using FAQ knowledge and the user's learned tone.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Email_Processor {

	/**
	 * Load a cached message row by id.
	 *
	 * @param int $message_id Local id.
	 * @return object|null
	 */
	public static function get_message( $message_id ) {
		global $wpdb;
		$table = AIEH_Activator::messages_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $message_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Best-effort first name of the person we are replying to.
	 *
	 * @param object $msg Message row.
	 * @return string
	 */
	public static function recipient_first_name( $msg ) {
		$name = isset( $msg->from_name ) ? trim( (string) $msg->from_name ) : '';
		if ( '' !== $name ) {
			$parts = preg_split( '/\s+/', $name );
			if ( ! empty( $parts[0] ) ) {
				return $parts[0];
			}
		}
		$email = isset( $msg->from_email ) ? (string) $msg->from_email : '';
		$at    = strpos( $email, '@' );
		if ( false !== $at ) {
			$local = preg_replace( '/[._\-].*$/', '', substr( $email, 0, $at ) );
			if ( '' !== $local ) {
				return ucfirst( $local );
			}
		}
		return 'there';
	}

	/**
	 * Build the greeting + sign-off instruction for reply prompts.
	 *
	 * @param object $msg Message row being replied to.
	 * @return string
	 */
	public static function signature_instruction( $msg ) {
		$name    = self::recipient_first_name( $msg );
		$signoff = AIEH_Settings::get( 'signoff', 'Kind regards' );
		$sig     = AIEH_Settings::get( 'signature_name' );

		$out = " Open the reply with the greeting line \"Hello {$name},\" on its own line (use the real name, never a placeholder).";
		if ( '' !== $signoff || '' !== $sig ) {
			$out .= ' Close the reply with';
			if ( '' !== $signoff ) {
				$out .= " \"{$signoff},\"";
			}
			if ( '' !== $sig ) {
				$out .= ( '' !== $signoff ? ' followed by' : '' ) . " \"{$sig}\"";
			}
			$out .= ' on separate lines at the end. Do not duplicate an existing greeting or sign-off.';
		}
		return $out;
	}

	/**
	 * Summarize a message and persist the summary.
	 *
	 * @param int $message_id Local id.
	 * @return string|WP_Error
	 */
	public static function summarize( $message_id ) {
		$msg = self::get_message( $message_id );
		if ( ! $msg ) {
			return new WP_Error( 'aieh_no_message', __( 'Message not found.', 'ai-email-helper' ) );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an assistant that writes concise 2-3 sentence summaries of emails, highlighting who it is from, what they want, and any action required. Be direct.',
			),
			array(
				'role'    => 'user',
				'content' => "From: {$msg->from_name} <{$msg->from_email}>\nSubject: {$msg->subject}\n\n" . self::trim_body( $msg->body_text ),
			),
		);

		$summary = AIEH_OpenAI_Client::chat( $messages, array( 'max_tokens' => 200, 'temperature' => 0.3 ) );
		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		global $wpdb;
		$table = AIEH_Activator::messages_table();
		$wpdb->update( // phpcs:ignore WordPress.DB
			$table,
			array( 'summary' => $summary ),
			array( 'id' => (int) $message_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $summary;
	}

	/**
	 * Generate a suggested reply draft using FAQ knowledge + learned tone.
	 *
	 * @param int $message_id Local id.
	 * @return string|WP_Error
	 */
	public static function draft_reply( $message_id ) {
		$msg = self::get_message( $message_id );
		if ( ! $msg ) {
			return new WP_Error( 'aieh_no_message', __( 'Message not found.', 'ai-email-helper' ) );
		}

		$knowledge = AIEH_Faq_Scanner::knowledge_context();
		$examples  = AIEH_Learning_Store::examples_context();

		$system  = "You are the email assistant for the user. Write a helpful, professional reply to the email below. ";
		$system .= "Only answer using facts you are confident about. If FAQ knowledge is provided, prefer it. ";
		$system .= "Do not invent policies, prices, or commitments. Keep it concise. Output only the reply body (no subject line, no placeholders like [Name] unless necessary).";
		$system .= self::signature_instruction( $msg );

		if ( '' !== $examples ) {
			$system .= "\n\n" . $examples;
		}

		$user = '';
		if ( '' !== $knowledge ) {
			$user .= "FAQ / website knowledge you may use:\n{$knowledge}\n\n----\n\n";
		}
		$user .= "Email to reply to:\nFrom: {$msg->from_name} <{$msg->from_email}>\nSubject: {$msg->subject}\n\n" . self::trim_body( $msg->body_text );

		$messages = array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $user ),
		);

		$draft = AIEH_OpenAI_Client::chat( $messages, array( 'max_tokens' => 600, 'temperature' => 0.5 ) );
		if ( is_wp_error( $draft ) ) {
			return $draft;
		}

		global $wpdb;
		$table = AIEH_Activator::drafts_table();
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'message_id' => (int) $message_id,
				'draft_text' => $draft,
				'model'      => AIEH_Settings::get( 'openai_model', 'gpt-4o-mini' ),
				'status'     => 'suggested',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return $draft;
	}

	/**
	 * Trim an email body to a safe size for prompting.
	 */
	private static function trim_body( $body ) {
		$body = (string) $body;
		if ( function_exists( 'mb_substr' ) && mb_strlen( $body ) > 8000 ) {
			$body = mb_substr( $body, 0, 8000 );
		}
		return $body;
	}
}
