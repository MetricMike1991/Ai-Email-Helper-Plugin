<?php
/**
 * Activation / deactivation: creates and removes the plugin database tables.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Activator {

	/**
	 * Table name helpers.
	 */
	public static function messages_table() {
		global $wpdb;
		return $wpdb->prefix . 'aieh_messages';
	}
	public static function drafts_table() {
		global $wpdb;
		return $wpdb->prefix . 'aieh_drafts';
	}
	public static function faqs_table() {
		global $wpdb;
		return $wpdb->prefix . 'aieh_faqs';
	}
	public static function learning_table() {
		global $wpdb;
		return $wpdb->prefix . 'aieh_learning';
	}
	public static function tasks_table() {
		global $wpdb;
		return $wpdb->prefix . 'aieh_tasks';
	}

	/**
	 * Create tables on activation.
	 */
	public static function activate() {
		self::install_tables();
	}

	/**
	 * Run table creation / upgrades if the stored DB version is out of date.
	 * Hooked on admin_init so existing installs pick up new tables (e.g. tasks).
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'aieh_db_version' ) !== AIEH_VERSION ) {
			self::install_tables();
		}
	}

	/**
	 * Create/upgrade all plugin tables (idempotent via dbDelta).
	 */
	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$messages = self::messages_table();
		$drafts   = self::drafts_table();
		$faqs     = self::faqs_table();
		$learning = self::learning_table();
		$tasks    = self::tasks_table();

		$sql = array();

		// Cached emails fetched from IMAP.
		$sql[] = "CREATE TABLE {$messages} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			imap_uid BIGINT UNSIGNED NOT NULL DEFAULT 0,
			folder VARCHAR(191) NOT NULL DEFAULT 'INBOX',
			message_id VARCHAR(191) NOT NULL DEFAULT '',
			from_email VARCHAR(191) NOT NULL DEFAULT '',
			from_name VARCHAR(191) NOT NULL DEFAULT '',
			to_email TEXT NULL,
			subject TEXT NULL,
			body_text LONGTEXT NULL,
			summary LONGTEXT NULL,
			category VARCHAR(60) NOT NULL DEFAULT '',
			priority TINYINT NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'new',
			received_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uid_folder (imap_uid, folder),
			KEY status (status),
			KEY received_at (received_at)
		) {$charset_collate};";

		// AI generated reply drafts.
		$sql[] = "CREATE TABLE {$drafts} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			draft_text LONGTEXT NULL,
			model VARCHAR(60) NOT NULL DEFAULT '',
			status VARCHAR(30) NOT NULL DEFAULT 'suggested',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY message_id (message_id)
		) {$charset_collate};";

		// Scanned FAQ sources (external URLs).
		$sql[] = "CREATE TABLE {$faqs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_url VARCHAR(500) NOT NULL DEFAULT '',
			title TEXT NULL,
			content LONGTEXT NULL,
			word_count INT NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			scanned_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		// Learned examples from the user's approved/edited replies.
		$sql[] = "CREATE TABLE {$learning} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			prompt_summary LONGTEXT NULL,
			user_reply LONGTEXT NULL,
			tone_notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY message_id (message_id)
		) {$charset_collate};";

		// Kanban to-do cards (manual or linked to an email).
		$sql[] = "CREATE TABLE {$tasks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title TEXT NULL,
			notes LONGTEXT NULL,
			column_id VARCHAR(60) NOT NULL DEFAULT 'todo',
			position INT NOT NULL DEFAULT 0,
			priority TINYINT NOT NULL DEFAULT 0,
			category VARCHAR(60) NOT NULL DEFAULT '',
			source VARCHAR(20) NOT NULL DEFAULT 'manual',
			message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			email_from VARCHAR(191) NOT NULL DEFAULT '',
			email_subject TEXT NULL,
			due_date DATETIME NULL,
			recurrence VARCHAR(20) NOT NULL DEFAULT '',
			completed_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY column_id (column_id),
			KEY priority (priority)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'aieh_db_version', AIEH_VERSION );
	}

	/**
	 * Deactivation: clear scheduled events. Tables are kept (removed on uninstall).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'aieh_fetch_emails_event' );
	}
}
