<?php
/**
 * Privacy and retention helper.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPC_Privacy {
	/**
	 * Settings manager.
	 *
	 * @var SPC_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SPC_Settings|null $settings Settings manager.
	 */
	public function __construct( SPC_Settings $settings = null ) {
		$this->settings = $settings ? $settings : new SPC_Settings();
	}

	/**
	 * Get short privacy notice text.
	 *
	 * @return string
	 */
	public function get_notice() {
		return __( 'Messages may be stored to help with follow-up and improve this portfolio assistant.', 'slava-portfolio-chatbot' );
	}

	/**
	 * Delete chat logs older than the configured retention period.
	 *
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public function delete_old_chat_logs() {
		global $wpdb;

		$retention_days = absint( $this->settings->get( 'chat_log_retention_days', 90 ) );
		$retention_days = max( 1, min( 365, $retention_days ) );
		$table          = $wpdb->prefix . 'spc_chat_logs';

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)",
				current_time( 'mysql' ),
				$retention_days
			)
		);
	}

	/**
	 * Ensure the daily cleanup event exists.
	 *
	 * @return void
	 */
	public static function ensure_cleanup_schedule() {
		if ( ! wp_next_scheduled( 'spc_cleanup_chat_logs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'spc_cleanup_chat_logs' );
		}
	}

	/**
	 * Clear the daily cleanup event.
	 *
	 * @return void
	 */
	public static function clear_cleanup_schedule() {
		wp_clear_scheduled_hook( 'spc_cleanup_chat_logs' );
	}
}
