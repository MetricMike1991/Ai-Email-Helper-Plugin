<?php
/**
 * Admin menus, settings save handling, and asset loading.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Admin {

	const SLUG = 'ai-email-helper';

	/**
	 * Register admin menu + subpages.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'AI Email Helper', 'ai-email-helper' ),
			__( 'AI Email', 'ai-email-helper' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_inbox' ),
			'dashicons-email-alt',
			26
		);
		add_submenu_page( self::SLUG, __( 'Inbox', 'ai-email-helper' ), __( 'Inbox', 'ai-email-helper' ), 'manage_options', self::SLUG, array( $this, 'render_inbox' ) );
		add_submenu_page( self::SLUG, __( 'FAQ Sources', 'ai-email-helper' ), __( 'FAQ Sources', 'ai-email-helper' ), 'manage_options', self::SLUG . '-faq', array( $this, 'render_faq' ) );
		add_submenu_page( self::SLUG, __( 'To-Do Board', 'ai-email-helper' ), __( 'To-Do Board', 'ai-email-helper' ), 'manage_options', self::SLUG . '-todo', array( $this, 'render_todo' ) );
		add_submenu_page( self::SLUG, __( 'Learning', 'ai-email-helper' ), __( 'Learning', 'ai-email-helper' ), 'manage_options', self::SLUG . '-learning', array( $this, 'render_learning' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'ai-email-helper' ), __( 'Settings', 'ai-email-helper' ), 'manage_options', self::SLUG . '-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * Handle settings form submission.
	 */
	public function maybe_save_settings() {
		if ( ! isset( $_POST['aieh_settings_submit'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'aieh_save_settings' );

		$fields = array_keys( AIEH_Settings::defaults() );
		$input  = array();
		foreach ( $fields as $key ) {
			$input[ $key ] = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		}
		AIEH_Settings::save( $input );

		add_settings_error( 'aieh', 'saved', __( 'Settings saved.', 'ai-email-helper' ), 'updated' );
		set_transient( 'aieh_settings_saved', 1, 30 );
	}

	/**
	 * Enqueue admin CSS/JS only on our pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		$css = AIEH_PLUGIN_DIR . 'admin/css/admin.css';
		$js  = AIEH_PLUGIN_DIR . 'admin/js/admin.js';
		// Version by file mtime so browsers always load the current asset.
		$css_ver = file_exists( $css ) ? (string) filemtime( $css ) : AIEH_VERSION;
		$js_ver  = file_exists( $js ) ? (string) filemtime( $js ) : AIEH_VERSION;

		wp_enqueue_style( 'aieh-admin', AIEH_PLUGIN_URL . 'admin/css/admin.css', array(), $css_ver );
		wp_enqueue_script( 'aieh-admin', AIEH_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), $js_ver, true );
		wp_localize_script(
			'aieh-admin',
			'AIEH',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( AIEH_Ajax::NONCE ),
				'i18n'    => array(
					'working' => __( 'Working…', 'ai-email-helper' ),
					'confirm' => __( 'Send this reply now?', 'ai-email-helper' ),
				),
			)
		);
	}

	/**
	 * Inbox page.
	 */
	public function render_inbox() {
		require AIEH_PLUGIN_DIR . 'admin/views/inbox.php';
	}

	/**
	 * FAQ page.
	 */
	public function render_faq() {
		require AIEH_PLUGIN_DIR . 'admin/views/faq.php';
	}

	/**
	 * To-Do (Kanban) page.
	 */
	public function render_todo() {
		require AIEH_PLUGIN_DIR . 'admin/views/todo.php';
	}

	/**
	 * Learning page.
	 */
	public function render_learning() {
		require AIEH_PLUGIN_DIR . 'admin/views/learning.php';
	}

	/**
	 * Settings page.
	 */
	public function render_settings() {
		require AIEH_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
