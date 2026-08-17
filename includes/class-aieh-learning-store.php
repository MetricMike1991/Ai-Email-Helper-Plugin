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
	 * Fetch one learning entry by id.
	 *
	 * @param int $id Entry id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = AIEH_Activator::learning_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Search/list learning entries, newest first.
	 *
	 * @param string $search Optional search term (matches summary/reply/notes).
	 * @param int    $limit  Max rows.
	 * @param int    $offset Row offset.
	 * @return array
	 */
	public static function search( $search = '', $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = AIEH_Activator::learning_table();
		$search = trim( (string) $search );

		if ( '' === $search ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", (int) $limit, (int) $offset ) ); // phpcs:ignore WordPress.DB
		}

		$like = '%' . $wpdb->esc_like( $search ) . '%';
		return $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE prompt_summary LIKE %s OR user_reply LIKE %s OR tone_notes LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$like,
				$like,
				$like,
				(int) $limit,
				(int) $offset
			)
		);
	}

	/**
	 * Count entries matching an optional search term.
	 *
	 * @param string $search Optional search term.
	 * @return int
	 */
	public static function count( $search = '' ) {
		global $wpdb;
		$table  = AIEH_Activator::learning_table();
		$search = trim( (string) $search );

		if ( '' === $search ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
		}

		$like = '%' . $wpdb->esc_like( $search ) . '%';
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE prompt_summary LIKE %s OR user_reply LIKE %s OR tone_notes LIKE %s",
				$like,
				$like,
				$like
			)
		);
	}

	/**
	 * Update an existing learning entry (e.g. when underlying facts changed).
	 *
	 * @param int    $id             Entry id.
	 * @param string $prompt_summary Updated context summary.
	 * @param string $user_reply     Updated reply text.
	 * @param string $tone_notes     Updated tone notes.
	 * @return bool
	 */
	public static function update( $id, $prompt_summary, $user_reply, $tone_notes ) {
		global $wpdb;
		$table = AIEH_Activator::learning_table();
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB
			$table,
			array(
				'prompt_summary' => wp_kses_post( $prompt_summary ),
				'user_reply'     => wp_kses_post( $user_reply ),
				'tone_notes'     => sanitize_textarea_field( $tone_notes ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a learning entry.
	 *
	 * @param int $id Entry id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = AIEH_Activator::learning_table();
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
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
