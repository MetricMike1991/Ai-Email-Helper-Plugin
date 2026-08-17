<?php
/**
 * Uninstall: remove plugin data (tables + options) when deleted from WordPress.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'aieh_messages',
	$wpdb->prefix . 'aieh_drafts',
	$wpdb->prefix . 'aieh_faqs',
	$wpdb->prefix . 'aieh_learning',
	$wpdb->prefix . 'aieh_tasks',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
}

delete_option( 'aieh_settings' );
delete_option( 'aieh_db_version' );
delete_option( 'aieh_kanban_columns' );
wp_clear_scheduled_hook( 'aieh_fetch_emails_event' );
