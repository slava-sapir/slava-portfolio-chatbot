<?php
/**
 * Frontend shortcodes.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin shortcodes.
 */
class SPC_Shortcodes {
	/**
	 * Settings manager.
	 *
	 * @var SPC_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SPC_Settings $settings Settings manager.
	 */
	public function __construct( SPC_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'slava_portfolio_qa', array( $this, 'render_qa_shortcode' ) );
	}

	/**
	 * Render embedded page Q&A shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_qa_shortcode( $atts ) {
		if ( ! $this->settings->is_chatbot_enabled() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'page_id' => get_the_ID(),
				'title'   => __( 'Ask About This Page', 'slava-portfolio-chatbot' ),
			),
			$atts,
			'slava_portfolio_qa'
		);

		$page_id = absint( $atts['page_id'] );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return '';
		}

		$this->enqueue_assets();

		$title       = sanitize_text_field( $atts['title'] );
		$page_title  = get_the_title( $page );
		$privacy     = new SPC_Privacy( $this->settings );
		$template    = SPC_PLUGIN_DIR . 'templates/qa-block.php';

		if ( ! file_exists( $template ) ) {
			return '';
		}

		ob_start();
		include $template;

		return ob_get_clean();
	}

	/**
	 * Enqueue embedded Q&A assets.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'spc-qa-block',
			SPC_PLUGIN_URL . 'public/css/qa-block.css',
			array(),
			SPC_VERSION
		);

		wp_enqueue_script(
			'spc-qa-block',
			SPC_PLUGIN_URL . 'public/js/qa-block.js',
			array(),
			SPC_VERSION,
			true
		);

		wp_localize_script(
			'spc-qa-block',
			'SPC_QA_BLOCK',
			array(
				'restUrl'            => esc_url_raw( rest_url( 'spc/v1/' ) ),
				'nonce'              => wp_create_nonce( 'wp_rest' ),
				'defaultLanguage'    => sanitize_text_field( $this->settings->get( 'default_language', 'en' ) ),
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
}
