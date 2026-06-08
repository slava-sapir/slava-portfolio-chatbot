<?php
/**
 * Analytics helper.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores lightweight chatbot analytics events.
 */
class SPC_Analytics {
	/**
	 * Track an analytics event.
	 *
	 * @param string $event_type Event type.
	 * @param array  $data       Event data.
	 *
	 * @return bool
	 */
	public function track_event( $event_type, array $data = array() ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'spc_analytics_events',
			array(
				'created_at'      => current_time( 'mysql' ),
				'event_type'      => sanitize_key( $event_type ),
				'conversation_id' => isset( $data['conversation_id'] ) ? sanitize_text_field( $data['conversation_id'] ) : '',
				'language'        => isset( $data['language'] ) ? sanitize_text_field( $data['language'] ) : '',
				'visitor_type'    => isset( $data['visitor_type'] ) ? sanitize_text_field( $data['visitor_type'] ) : '',
				'interest_area'   => isset( $data['interest_area'] ) ? sanitize_text_field( $data['interest_area'] ) : '',
				'source_page'     => isset( $data['source_page'] ) ? esc_url_raw( $data['source_page'] ) : '',
				'is_fallback'     => ! empty( $data['is_fallback'] ) ? 1 : 0,
				'metadata'        => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : '',
				'session_hash'    => isset( $data['session_hash'] ) ? sanitize_text_field( $data['session_hash'] ) : '',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * Get summary metrics for the admin dashboard.
	 *
	 * @return array
	 */
	public function get_summary() {
		global $wpdb;

		$table = $wpdb->prefix . 'spc_analytics_events';

		$questions = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE event_type = 'chat_question'" ) );
		$answers   = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE event_type = 'chat_answer'" ) );
		$fallbacks = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE event_type = 'fallback'" ) );
		$leads     = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE event_type = 'lead_captured'" ) );

		return array(
			'questions'     => $questions,
			'answers'       => $answers,
			'fallbacks'     => $fallbacks,
			'leads'         => $leads,
			'fallback_rate' => $questions > 0 ? round( ( $fallbacks / $questions ) * 100, 1 ) : 0,
			'lead_rate'     => $questions > 0 ? round( ( $leads / $questions ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get top interest areas.
	 *
	 * @param int $limit Max rows.
	 *
	 * @return array
	 */
	public function get_top_interest_areas( $limit = 5 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT interest_area, COUNT(*) as total FROM {$wpdb->prefix}spc_analytics_events WHERE interest_area <> '' GROUP BY interest_area ORDER BY total DESC LIMIT %d",
				absint( $limit )
			)
		);
	}

	/**
	 * Get recent events.
	 *
	 * @param int $limit Max rows.
	 *
	 * @return array
	 */
	public function get_recent_events( $limit = 20 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT created_at, event_type, conversation_id, interest_area, is_fallback FROM {$wpdb->prefix}spc_analytics_events ORDER BY created_at DESC LIMIT %d",
				absint( $limit )
			)
		);
	}
}
