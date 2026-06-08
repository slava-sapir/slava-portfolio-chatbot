<?php
/**
 * Rate limiter.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic transient-backed public endpoint rate limiting.
 */
class SPC_Rate_Limiter {
	/**
	 * Check whether a request is allowed.
	 *
	 * @param string $action Action key.
	 * @param int    $limit  Max requests.
	 * @param int    $window Window in seconds.
	 *
	 * @return bool
	 */
	public function is_allowed( $action, $limit = 20, $window = 600 ) {
		$key   = $this->get_transient_key( $action );
		$count = absint( get_transient( $key ) );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );

		return true;
	}

	/**
	 * Get a non-raw visitor hash for logs and rate limiting.
	 *
	 * @return string
	 */
	public function get_session_hash() {
		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';
		$salt       = wp_salt( 'nonce' );

		return hash( 'sha256', $ip . '|' . $user_agent . '|' . $salt );
	}

	/**
	 * Build transient key.
	 *
	 * @param string $action Action key.
	 *
	 * @return string
	 */
	private function get_transient_key( $action ) {
		return 'spc_rate_' . sanitize_key( $action ) . '_' . substr( $this->get_session_hash(), 0, 32 );
	}
}
