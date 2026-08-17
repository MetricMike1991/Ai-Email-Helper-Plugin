<?php
/**
 * Kanban to-do store: customizable columns, task CRUD, and AI prioritisation.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Tasks {

	const COLUMNS_OPTION = 'aieh_kanban_columns';

	/* ---------------------------------------------------------------------
	 * Columns
	 * ------------------------------------------------------------------- */

	/**
	 * Default columns.
	 */
	public static function default_columns() {
		return array(
			array( 'id' => 'todo', 'label' => 'To Do', 'description' => 'Tasks not started yet that need action.' ),
			array( 'id' => 'doing', 'label' => 'In Progress', 'description' => 'Tasks currently being worked on.' ),
			array( 'id' => 'done', 'label' => 'Done', 'description' => 'Completed tasks.' ),
		);
	}

	/**
	 * Get the configured columns (falls back to defaults). Each column is
	 * normalised to always include a 'description' key.
	 *
	 * @return array
	 */
	public static function columns() {
		$cols = get_option( self::COLUMNS_OPTION );
		if ( ! is_array( $cols ) || empty( $cols ) ) {
			$cols = self::default_columns();
			update_option( self::COLUMNS_OPTION, $cols );
		}
		foreach ( $cols as &$col ) {
			if ( ! isset( $col['description'] ) ) {
				$col['description'] = '';
			}
		}
		unset( $col );
		return $cols;
	}

	/**
	 * Whether a column id exists.
	 */
	public static function column_exists( $id ) {
		foreach ( self::columns() as $col ) {
			if ( $col['id'] === $id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Id of the first column.
	 */
	public static function first_column_id() {
		$cols = self::columns();
		return isset( $cols[0]['id'] ) ? $cols[0]['id'] : 'todo';
	}

	/**
	 * Id of the last column (treated as "done").
	 */
	public static function done_column_id() {
		$cols = self::columns();
		$last = end( $cols );
		return ( is_array( $last ) && isset( $last['id'] ) ) ? $last['id'] : 'done';
	}

	/**
	 * Add a column. Returns the new column array.
	 *
	 * @param string $label Column label.
	 * @return array
	 */
	public static function add_column( $label, $description = '' ) {
		$label = sanitize_text_field( $label );
		$cols  = self::columns();
		$id    = 'col_' . substr( md5( $label . microtime() ), 0, 8 );
		$col   = array(
			'id'          => $id,
			'label'       => '' !== $label ? $label : __( 'New column', 'ai-email-helper' ),
			'description' => sanitize_textarea_field( $description ),
		);
		$cols[] = $col;
		update_option( self::COLUMNS_OPTION, $cols );
		return $col;
	}

	/**
	 * Update a column's AI description.
	 */
	public static function describe_column( $id, $description ) {
		$description = sanitize_textarea_field( $description );
		$cols        = self::columns();
		foreach ( $cols as &$col ) {
			if ( $col['id'] === $id ) {
				$col['description'] = $description;
			}
		}
		unset( $col );
		update_option( self::COLUMNS_OPTION, $cols );
	}

	/**
	 * Rename a column.
	 */
	public static function rename_column( $id, $label ) {
		$label = sanitize_text_field( $label );
		$cols  = self::columns();
		foreach ( $cols as &$col ) {
			if ( $col['id'] === $id ) {
				$col['label'] = $label;
			}
		}
		unset( $col );
		update_option( self::COLUMNS_OPTION, $cols );
	}

	/**
	 * Delete a column. Its cards move to the first remaining column.
	 */
	public static function delete_column( $id ) {
		$cols      = self::columns();
		$remaining = array();
		foreach ( $cols as $col ) {
			if ( $col['id'] !== $id ) {
				$remaining[] = $col;
			}
		}
		if ( empty( $remaining ) ) {
			return; // Never delete the last column.
		}
		update_option( self::COLUMNS_OPTION, $remaining );

		global $wpdb;
		$table = AIEH_Activator::tasks_table();
		$wpdb->update( $table, array( 'column_id' => $remaining[0]['id'] ), array( 'column_id' => $id ), array( '%s' ), array( '%s' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Reorder columns to match the given ordered list of ids.
	 *
	 * @param array $ids Ordered column ids.
	 */
	public static function reorder_columns( array $ids ) {
		$current = self::columns();
		$byId    = array();
		foreach ( $current as $col ) {
			$byId[ $col['id'] ] = $col;
		}
		$ordered = array();
		foreach ( $ids as $id ) {
			if ( isset( $byId[ $id ] ) ) {
				$ordered[] = $byId[ $id ];
			}
		}
		if ( count( $ordered ) === count( $current ) ) {
			update_option( self::COLUMNS_OPTION, $ordered );
		}
	}

	/* ---------------------------------------------------------------------
	 * Tasks
	 * ------------------------------------------------------------------- */

	/**
	 * Get all tasks ordered by column then position.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		$table = AIEH_Activator::tasks_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY column_id ASC, position ASC, id ASC" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Get one task.
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = AIEH_Activator::tasks_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Create a task. Returns the new id.
	 *
	 * @param array $data Task fields.
	 * @return int
	 */
	public static function create( array $data ) {
		global $wpdb;
		$table = AIEH_Activator::tasks_table();

		$column_id = isset( $data['column_id'] ) && self::column_exists( $data['column_id'] ) ? $data['column_id'] : self::first_column_id();
		$position  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(position),-1)+1 FROM {$table} WHERE column_id = %s", $column_id ) ); // phpcs:ignore WordPress.DB

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'title'         => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
				'notes'         => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
				'column_id'     => $column_id,
				'position'      => $position,
				'priority'      => isset( $data['priority'] ) ? max( 0, min( 3, (int) $data['priority'] ) ) : 0,
				'category'      => isset( $data['category'] ) ? substr( sanitize_text_field( $data['category'] ), 0, 60 ) : '',
				'source'        => isset( $data['source'] ) ? sanitize_key( $data['source'] ) : 'manual',
				'message_id'    => isset( $data['message_id'] ) ? (int) $data['message_id'] : 0,
				'email_from'    => isset( $data['email_from'] ) ? substr( sanitize_text_field( $data['email_from'] ), 0, 191 ) : '',
				'email_subject' => isset( $data['email_subject'] ) ? sanitize_text_field( $data['email_subject'] ) : '',
				'due_date'      => ! empty( $data['due_date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['due_date'] ) ) : null,
				'created_at'    => current_time( 'mysql', true ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update editable fields of a task.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$table = AIEH_Activator::tasks_table();

		$fields  = array( 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s' );

		if ( isset( $data['title'] ) ) {
			$fields['title'] = sanitize_text_field( $data['title'] );
			$formats[]       = '%s';
		}
		if ( isset( $data['notes'] ) ) {
			$fields['notes'] = sanitize_textarea_field( $data['notes'] );
			$formats[]       = '%s';
		}
		if ( isset( $data['priority'] ) ) {
			$fields['priority'] = max( 0, min( 3, (int) $data['priority'] ) );
			$formats[]          = '%d';
		}
		if ( isset( $data['category'] ) ) {
			$fields['category'] = substr( sanitize_text_field( $data['category'] ), 0, 60 );
			$formats[]          = '%s';
		}
		if ( array_key_exists( 'due_date', $data ) ) {
			$fields['due_date'] = ! empty( $data['due_date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['due_date'] ) ) : null;
			$formats[]          = '%s';
		}

		$wpdb->update( $table, $fields, array( 'id' => (int) $id ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Delete a task.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = AIEH_Activator::tasks_table();
		$wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Persist a full ordering for a column: $ordered_ids in their new order.
	 *
	 * @param string $column_id   Destination column.
	 * @param array  $ordered_ids Task ids in order.
	 */
	public static function set_column_order( $column_id, array $ordered_ids ) {
		global $wpdb;
		$table = AIEH_Activator::tasks_table();
		if ( ! self::column_exists( $column_id ) ) {
			return;
		}
		$pos = 0;
		foreach ( $ordered_ids as $id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				array( 'column_id' => $column_id, 'position' => $pos, 'updated_at' => current_time( 'mysql', true ) ),
				array( 'id' => (int) $id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			$pos++;
		}
	}

	/**
	 * Create a task linked to a cached email.
	 *
	 * @param int $message_id Local message id.
	 * @return int|WP_Error
	 */
	public static function from_email( $message_id ) {
		$msg = AIEH_Email_Processor::get_message( $message_id );
		if ( ! $msg ) {
			return new WP_Error( 'aieh_no_message', __( 'Message not found.', 'ai-email-helper' ) );
		}
		return self::create(
			array(
				'title'         => $msg->subject ? $msg->subject : __( '(no subject)', 'ai-email-helper' ),
				'notes'         => $msg->summary ? $msg->summary : '',
				'source'        => 'email',
				'message_id'    => (int) $msg->id,
				'email_from'    => $msg->from_name ? $msg->from_name . ' <' . $msg->from_email . '>' : $msg->from_email,
				'email_subject' => $msg->subject,
				'category'      => $msg->category,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * AI
	 * ------------------------------------------------------------------- */

	/**
	 * Ask the AI to assign a priority (1-3) to every open (non-done) task and
	 * route it into the best-matching column (by the columns' AI descriptions),
	 * then sort each column so the most urgent are at the top.
	 *
	 * @return true|WP_Error
	 */
	public static function ai_prioritise() {
		$tasks = self::all();
		if ( empty( $tasks ) ) {
			return true;
		}

		$done_id = self::done_column_id(); // Never auto-move completed cards.
		$cols    = self::columns();
		$col_ctx = array();
		$valid   = array();
		foreach ( $cols as $c ) {
			if ( $c['id'] === $done_id ) {
				continue; // Not an auto-routing target.
			}
			$col_ctx[] = array(
				'column_id'  => $c['id'],
				'label'      => $c['label'],
				'about'      => $c['description'],
			);
			$valid[ $c['id'] ] = true;
		}

		$list = array();
		foreach ( $tasks as $t ) {
			if ( $t->column_id === $done_id ) {
				continue;
			}
			$list[] = array(
				'id'         => (int) $t->id,
				'title'      => $t->title,
				'notes'      => mb_substr( (string) $t->notes, 0, 500 ),
				'category'   => $t->category,
				'due_date'   => $t->due_date,
				'created'    => $t->created_at,
				'column_now' => $t->column_id,
			);
		}
		if ( empty( $list ) ) {
			return true;
		}

		$now      = current_time( 'mysql' );
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You organise a Kanban board. You are given the available columns (each with an "about" description of what belongs there), the current date/time, and a list of tasks. For EACH task: (1) choose the best column_id from the provided columns by matching the task to the column descriptions; (2) assign a priority 3=high/urgent, 2=medium, 1=low, considering due dates (overdue or soon = higher), importance and age. Only use column_id values from the provided columns. Respond with ONLY a compact JSON array like [{"id":12,"column_id":"todo","priority":3}] — no markdown, no commentary.',
			),
			array(
				'role'    => 'user',
				'content' => 'Current date/time: ' . $now . "\n\nColumns:\n" . wp_json_encode( $col_ctx ) . "\n\nTasks:\n" . wp_json_encode( $list ),
			),
		);

		$resp = AIEH_OpenAI_Client::chat( $messages, array( 'max_tokens' => 900, 'temperature' => 0.1 ) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$parsed = self::extract_json_array( $resp );
		if ( empty( $parsed ) ) {
			return new WP_Error( 'aieh_ai_parse', __( 'Could not read the AI prioritisation response.', 'ai-email-helper' ) );
		}

		global $wpdb;
		$table = AIEH_Activator::tasks_table();
		foreach ( $parsed as $row ) {
			if ( ! isset( $row['id'], $row['priority'] ) ) {
				continue;
			}
			$fields  = array( 'priority' => max( 1, min( 3, (int) $row['priority'] ) ), 'updated_at' => current_time( 'mysql', true ) );
			$formats = array( '%d', '%s' );
			// Move to the AI-chosen column when it is a valid non-done column.
			if ( isset( $row['column_id'] ) && isset( $valid[ $row['column_id'] ] ) ) {
				$fields['column_id'] = $row['column_id'];
				$formats[]           = '%s';
			}
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				$fields,
				array( 'id' => (int) $row['id'] ),
				$formats,
				array( '%d' )
			);
		}

		self::sort_columns_by_priority();
		return true;
	}

	/**
	 * Reorder each column by priority (high first), then due date, then age.
	 */
	public static function sort_columns_by_priority() {
		global $wpdb;
		$table = AIEH_Activator::tasks_table();
		foreach ( self::columns() as $col ) {
			$ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB
				"SELECT id FROM {$table} WHERE column_id = %s ORDER BY priority DESC, (due_date IS NULL) ASC, due_date ASC, created_at ASC",
				$col['id']
			) );
			self::set_column_order( $col['id'], array_map( 'intval', $ids ) );
		}
	}

	/**
	 * Generate a short AI briefing of the board: what's urgent and what to do next.
	 *
	 * @return string|WP_Error
	 */
	public static function ai_overview() {
		$tasks = self::all();
		if ( empty( $tasks ) ) {
			return __( 'Your board is empty — add some cards first.', 'ai-email-helper' );
		}

		$cols    = self::columns();
		$labels  = array();
		foreach ( $cols as $c ) {
			$labels[ $c['id'] ] = $c['label'];
		}
		$done_id = self::done_column_id();

		$list = array();
		foreach ( $tasks as $t ) {
			$list[] = array(
				'title'    => $t->title,
				'column'   => isset( $labels[ $t->column_id ] ) ? $labels[ $t->column_id ] : $t->column_id,
				'priority' => (int) $t->priority,
				'due_date' => $t->due_date,
				'done'     => ( $t->column_id === $done_id ),
			);
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a concise executive assistant. Given the current date/time and a JSON list of Kanban tasks, write a short briefing (a few sentences plus a short bullet list) of what is most important, what is overdue or due soon, and what the user should tackle next. Ignore tasks already in the done column except to acknowledge progress. Plain text only.',
			),
			array(
				'role'    => 'user',
				'content' => 'Current date/time: ' . current_time( 'mysql' ) . "\n\nTasks:\n" . wp_json_encode( $list ),
			),
		);

		return AIEH_OpenAI_Client::chat( $messages, array( 'max_tokens' => 500, 'temperature' => 0.4 ) );
	}

	/**
	 * Pull the first JSON array out of a model response.
	 *
	 * @param string $text Raw response.
	 * @return array
	 */
	private static function extract_json_array( $text ) {
		$text  = trim( (string) $text );
		$start = strpos( $text, '[' );
		$end   = strrpos( $text, ']' );
		if ( false === $start || false === $end || $end < $start ) {
			return array();
		}
		$json = substr( $text, $start, $end - $start + 1 );
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}
}
