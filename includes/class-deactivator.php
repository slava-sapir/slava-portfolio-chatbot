<?php
/**
 * Plugin deactivation tasks.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs when the plugin is deactivated.
 */
class SPC_Deactivator {
	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		SPC_Privacy::clear_cleanup_schedule();

		// Keep settings and future data tables intact for safe reactivation.
	}
}
