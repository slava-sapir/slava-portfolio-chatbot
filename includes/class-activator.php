<?php
/**
 * Plugin activation tasks.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs when the plugin is activated.
 */
class SPC_Activator {
	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		add_option( 'spc_version', SPC_VERSION );
		add_option( 'spc_chatbot_enabled', '0' );

		self::create_tables();
		SPC_Privacy::ensure_cleanup_schedule();
	}

	/**
	 * Run schema upgrades when the installed DB version is old.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'spc_db_version' ) !== SPC_DB_VERSION ) {
			self::create_tables();
		}
	}

	/**
	 * Create or update plugin-owned database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$leads_table     = $wpdb->prefix . 'spc_leads';
		$logs_table      = $wpdb->prefix . 'spc_chat_logs';
		$analytics_table = $wpdb->prefix . 'spc_analytics_events';

		$leads_sql = "CREATE TABLE $leads_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			company varchar(190) NOT NULL DEFAULT '',
			country varchar(100) NOT NULL DEFAULT '',
			visitor_type varchar(100) NOT NULL DEFAULT '',
			interest_area varchar(190) NOT NULL DEFAULT '',
			message longtext NULL,
			source_page varchar(255) NOT NULL DEFAULT '',
			consent_given tinyint(1) NOT NULL DEFAULT 0,
			conversation_id varchar(100) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'new',
			contacted_at datetime NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY conversation_id (conversation_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		$logs_sql = "CREATE TABLE $logs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			role varchar(20) NOT NULL DEFAULT '',
			message_text longtext NULL,
			language varchar(20) NOT NULL DEFAULT '',
			source_page varchar(255) NOT NULL DEFAULT '',
			retrieved_chunk_ids text NULL,
			lead_captured tinyint(1) NOT NULL DEFAULT 0,
			session_hash varchar(128) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at),
			KEY role (role),
			KEY session_hash (session_hash)
		) $charset_collate;";

		$analytics_sql = "CREATE TABLE $analytics_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			event_type varchar(50) NOT NULL DEFAULT '',
			conversation_id varchar(100) NOT NULL DEFAULT '',
			language varchar(20) NOT NULL DEFAULT '',
			visitor_type varchar(100) NOT NULL DEFAULT '',
			interest_area varchar(190) NOT NULL DEFAULT '',
			source_page varchar(255) NOT NULL DEFAULT '',
			is_fallback tinyint(1) NOT NULL DEFAULT 0,
			metadata longtext NULL,
			session_hash varchar(128) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY created_at (created_at),
			KEY conversation_id (conversation_id),
			KEY is_fallback (is_fallback)
		) $charset_collate;";

		dbDelta( $leads_sql );
		dbDelta( $logs_sql );
		dbDelta( $analytics_sql );

		update_option( 'spc_db_version', SPC_DB_VERSION );
	}
}
