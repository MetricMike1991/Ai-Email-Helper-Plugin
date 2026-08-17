<?php
/**
 * Plugin Name:       AI Email Helper
 * Plugin URI:        https://github.com/MetricMike1991/Ai-Email-Helper-Plugin
 * Description:        Connect your (SiteGround/IMAP) email inbox to WordPress and use OpenAI to summarize messages, suggest reply drafts, learn from your past replies, and answer using scanned FAQ pages.
 * Version:           0.3.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            MetricMike1991
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-email-helper
 * Domain Path:       /languages
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'AIEH_VERSION', '0.3.2' );
define( 'AIEH_PLUGIN_FILE', __FILE__ );
define( 'AIEH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIEH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIEH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-crypto.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-settings.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-activator.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-imap-client.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-smtp-mailer.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-openai-client.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-faq-scanner.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-learning-store.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-tasks.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-email-processor.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-ajax.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-admin.php';
require_once AIEH_PLUGIN_DIR . 'includes/class-aieh-plugin.php';

register_activation_hook( __FILE__, array( 'AIEH_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AIEH_Activator', 'deactivate' ) );

/**
 * Boot the plugin.
 */
function aieh() {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new AIEH_Plugin();
		$plugin->run();
	}
	return $plugin;
}
add_action( 'plugins_loaded', 'aieh' );
