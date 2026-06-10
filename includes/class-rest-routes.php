<?php
/**
 * REST route registration.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin REST endpoints.
 */
class SPC_REST_Routes {
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
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$chat_controller = new SPC_Chat_Controller( $this->settings );
		$lead_controller = new SPC_Lead_Controller( $this->settings );
		$qa_controller   = new SPC_QA_Controller( $this->settings );

		register_rest_route(
			'spc/v1',
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $chat_controller, 'handle_chat' ),
				'permission_callback' => array( $chat_controller, 'permissions_check' ),
			)
		);

		register_rest_route(
			'spc/v1',
			'/lead',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $lead_controller, 'handle_lead' ),
				'permission_callback' => array( $lead_controller, 'permissions_check' ),
			)
		);

		register_rest_route(
			'spc/v1',
			'/qa',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $qa_controller, 'handle_qa' ),
				'permission_callback' => array( $qa_controller, 'permissions_check' ),
			)
		);
	}
}
