<?php
/**
 * Logger.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal logger for debug events and chat records.
 */
class SPC_Logger {
	/**
	 * Log an event.
	 *
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Context data.
	 *
	 * @return void
	 */
	public function log( $level, $message, array $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SPC][' . sanitize_key( $level ) . '] ' . sanitize_text_field( $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Store a chat message in the plugin log table.
	 *
	 * @param array $data Log data.
	 *
	 * @return bool
	 */
	public function log_chat_message( array $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'spc_chat_logs';

		$inserted = $wpdb->insert(
			$table,
			array(
				'conversation_id'     => isset( $data['conversation_id'] ) ? sanitize_text_field( $data['conversation_id'] ) : '',
				'created_at'          => current_time( 'mysql' ),
				'role'                => isset( $data['role'] ) ? sanitize_text_field( $data['role'] ) : '',
				'message_text'        => isset( $data['message_text'] ) ? sanitize_textarea_field( $data['message_text'] ) : '',
				'language'            => isset( $data['language'] ) ? sanitize_text_field( $data['language'] ) : '',
				'source_page'         => isset( $data['source_page'] ) ? esc_url_raw( $data['source_page'] ) : '',
				'retrieved_chunk_ids' => isset( $data['retrieved_chunk_ids'] ) ? wp_json_encode( $data['retrieved_chunk_ids'] ) : '',
				'lead_captured'       => ! empty( $data['lead_captured'] ) ? 1 : 0,
				'session_hash'        => isset( $data['session_hash'] ) ? sanitize_text_field( $data['session_hash'] ) : '',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return false !== $inserted;
	}
}
