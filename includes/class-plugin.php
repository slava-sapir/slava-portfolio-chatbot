<?php
/**
 * Main plugin bootstrap.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SPC_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once SPC_PLUGIN_DIR . 'includes/class-settings.php';
require_once SPC_PLUGIN_DIR . 'includes/class-rest-routes.php';
require_once SPC_PLUGIN_DIR . 'includes/class-chat-controller.php';
require_once SPC_PLUGIN_DIR . 'includes/class-qa-controller.php';
require_once SPC_PLUGIN_DIR . 'includes/class-lead-controller.php';
require_once SPC_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once SPC_PLUGIN_DIR . 'includes/class-kb-sync.php';
require_once SPC_PLUGIN_DIR . 'includes/class-openai-client.php';
require_once SPC_PLUGIN_DIR . 'includes/class-supabase-client.php';
require_once SPC_PLUGIN_DIR . 'includes/class-logger.php';
require_once SPC_PLUGIN_DIR . 'includes/class-guardrails.php';
require_once SPC_PLUGIN_DIR . 'includes/class-rate-limiter.php';
require_once SPC_PLUGIN_DIR . 'includes/class-privacy.php';
require_once SPC_PLUGIN_DIR . 'includes/class-analytics.php';

/**
 * Coordinates plugin services and WordPress hooks.
 */
class SPC_Plugin {
	/**
	 * Settings manager.
	 *
	 * @var SPC_Settings
	 */
	private $settings;

	/**
	 * Admin page service.
	 *
	 * @var SPC_Admin_Page
	 */
	private $admin_page;

	/**
	 * REST route service.
	 *
	 * @var SPC_REST_Routes
	 */
	private $rest_routes;

	/**
	 * Shortcode service.
	 *
	 * @var SPC_Shortcodes
	 */
	private $shortcodes;

	/**
	 * Privacy and retention service.
	 *
	 * @var SPC_Privacy
	 */
	private $privacy;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings    = new SPC_Settings();
		$this->admin_page  = new SPC_Admin_Page( $this->settings );
		$this->rest_routes = new SPC_REST_Routes( $this->settings );
		$this->shortcodes  = new SPC_Shortcodes( $this->settings );
		$this->privacy     = new SPC_Privacy( $this->settings );
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'admin_menu', array( $this->admin_page, 'register_menu' ) );
		add_action( 'admin_init', array( 'SPC_Activator', 'maybe_upgrade' ) );
		add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
		add_action( 'admin_notices', array( $this->admin_page, 'render_notices' ) );
		add_action( 'admin_post_spc_refresh_kb', array( $this->admin_page, 'handle_refresh_kb' ) );
		add_action( 'admin_post_spc_delete_lead', array( $this->admin_page, 'handle_delete_lead' ) );
		add_action( 'admin_post_spc_mark_lead_contacted', array( $this->admin_page, 'handle_mark_lead_contacted' ) );
		add_action( 'admin_post_spc_export_leads_csv', array( $this->admin_page, 'handle_export_leads_csv' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_chat_widget' ) );
		add_action( 'rest_api_init', array( $this->rest_routes, 'register_routes' ) );
		add_action( 'spc_cleanup_chat_logs', array( $this->privacy, 'run_cleanup' ) );
		add_action( 'init', array( $this->shortcodes, 'register' ) );

		SPC_Privacy::ensure_cleanup_schedule();
	}

	/**
	 * Enqueue admin assets only on this plugin's admin page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_slava-portfolio-chatbot' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'spc-admin',
			SPC_PLUGIN_URL . 'admin/css/admin-page.css',
			array(),
			SPC_VERSION
		);

		wp_enqueue_script(
			'spc-admin',
			SPC_PLUGIN_URL . 'admin/js/admin-kb-refresh.js',
			array(),
			SPC_VERSION,
			true
		);
	}

	/**
	 * Enqueue public chatbot assets.
	 *
	 * @return void
	 */
	public function enqueue_public_assets() {
		if ( ! $this->settings->is_chatbot_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'spc-chatbot-widget',
			SPC_PLUGIN_URL . 'public/css/chatbot-widget.css',
			array(),
			SPC_VERSION
		);

		wp_enqueue_script(
			'spc-chatbot-widget',
			SPC_PLUGIN_URL . 'public/js/chatbot-widget.js',
			array(),
			SPC_VERSION,
			true
		);

		wp_localize_script(
			'spc-chatbot-widget',
			'SPC_CHATBOT',
			array(
				'restUrl' => esc_url_raw( rest_url( 'spc/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'enabled' => true,
				'allowedLinkDomains' => $this->get_allowed_link_domains(),
			)
		);
	}

	/**
	 * Get sanitized link domains for frontend link allowlisting.
	 *
	 * @return array
	 */
	private function get_allowed_link_domains() {
		$raw     = (string) $this->settings->get( 'allowed_link_domains', '' );
		$lines   = preg_split( '/\r\n|\r|\n/', $raw );
		$domains = array();

		foreach ( $lines as $line ) {
			$domain = strtolower( trim( $line ) );

			if ( '' !== $domain ) {
				$domains[] = $domain;
			}
		}

		return array_values( array_unique( $domains ) );
	}

	/**
	 * Render the frontend chatbot widget template.
	 *
	 * @return void
	 */
	public function render_chat_widget() {
		if ( ! $this->settings->is_chatbot_enabled() ) {
			return;
		}

		$template = SPC_PLUGIN_DIR . 'templates/chat-widget.php';

		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
