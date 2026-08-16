<?php
/**
 * Stores the user's approved / edited replies as learning examples so the
 * assistant can mirror their tone and reuse prior answers.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Learning_Store {

	/**
	 * Record an approved reply as a learning example.
	 *
	 * @param int    $message_id     Local message id.
	 * @param string $prompt_summary Short context of what was being replied to.
	 * @param string $user_reply     The reply the user actually approved/sent.
	 */
	public static function record( $message_id, $prompt_summary, $user_reply ) {
		global $wpdb;
		$table = AIEH_Activator::learning_table();
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'message_id'     => (int) $message_id,
				'prompt_summary' => wp_kses_post( $prompt_summary ),
				'user_reply'     => wp_kses_post( $user_reply ),
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Build a few-shot style context from recent learned replies.
	 *
	 * @param int $limit Number of examples.
	 * @return string
	 */
	public static function examples_context( $limit = 5 ) {
		global $wpdb;
		$table = AIEH_Activator::learning_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT prompt_summary, user_reply FROM {$table} ORDER BY created_at DESC LIMIT %d", (int) $limit ) ); // phpcs:ignore WordPress.DB

		if ( empty( $rows ) ) {
			return '';
		}

		$out = "Here are examples of how the user has replied before. Match this tone and style:\n\n";
		foreach ( $rows as $i => $row ) {
			$n    = $i + 1;
			$out .= "Example {$n}:\nContext: {$row->prompt_summary}\nUser's reply: {$row->user_reply}\n\n";
		}
		return trim( $out );
	}
}
