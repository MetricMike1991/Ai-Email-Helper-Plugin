<?php
/**
 * Main plugin loader: wires up hooks for admin, AJAX and scheduled fetching.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Plugin {

	/**
	 * Register hooks.
	 */
	public function run() {
		// Admin UI.
		$admin = new AIEH_Admin();
		add_action( 'admin_menu', array( $admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $admin, 'maybe_save_settings' ) );

		// Create/upgrade DB tables for existing installs (e.g. new tasks table).
		add_action( 'admin_init', array( 'AIEH_Activator', 'maybe_upgrade' ) );

		// AJAX endpoints.
		$ajax = new AIEH_Ajax();
		$ajax->register();

		// Scheduled email fetch.
		add_action( 'aieh_fetch_emails_event', array( $this, 'cron_fetch' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
		if ( ! wp_next_scheduled( 'aieh_fetch_emails_event' ) && AIEH_Settings::is_imap_ready() ) {
			wp_schedule_event( time() + 300, 'aieh_ten_minutes', 'aieh_fetch_emails_event' );
		}
	}

	/**
	 * Add a 10-minute cron interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['aieh_ten_minutes'] = array(
			'interval' => 600,
			'display'  => __( 'Every 10 minutes (AI Email Helper)', 'ai-email-helper' ),
		);
		return $schedules;
	}

	/**
	 * Cron callback: fetch new emails and optionally auto-summarize.
	 */
	public function cron_fetch() {
		if ( ! AIEH_Settings::is_imap_ready() ) {
			return;
		}
		$new = AIEH_Imap_Client::fetch_recent();
		if ( is_wp_error( $new ) ) {
			return;
		}
		if ( $new > 0 && AIEH_Settings::get( 'auto_summarize' ) && AIEH_Settings::is_openai_ready() ) {
			$this->auto_process_new();
		}
	}

	/**
	 * Auto-summarize (and optionally draft) messages still marked 'new'.
	 */
	private function auto_process_new() {
		global $wpdb;
		$table = AIEH_Activator::messages_table();
		$ids   = $wpdb->get_col( "SELECT id FROM {$table} WHERE status = 'unread' ORDER BY received_at DESC LIMIT 10" ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			AIEH_Email_Processor::summarize( (int) $id );
			if ( AIEH_Settings::get( 'auto_draft' ) ) {
				AIEH_Email_Processor::draft_reply( (int) $id );
			}
		}
	}
}
