<?php
/**
 * Plugin Name: Slava Portfolio Chatbot
 * Plugin URI:  https://myportfolioonline.com
 * Description: AI-powered portfolio chatbot scaffold for grounded website Q&A and lead capture.
 * Version:     0.1.4
 * Author:      Slava
 * Text Domain: slava-portfolio-chatbot
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPC_VERSION', '0.1.4' );
define( 'SPC_DB_VERSION', '0.3.0' );
define( 'SPC_PLUGIN_FILE', __FILE__ );
define( 'SPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SPC_PLUGIN_DIR . 'includes/class-activator.php';
require_once SPC_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once SPC_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'SPC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SPC_Deactivator', 'deactivate' ) );

/**
 * Starts the plugin after WordPress has loaded all active plugins.
 */
function spc_run_plugin() {
	$plugin = new SPC_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'spc_run_plugin' );
