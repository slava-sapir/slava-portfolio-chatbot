<?php
/**
 * Lead controller.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles lead capture REST requests.
 */
class SPC_Lead_Controller {
	/**
	 * Settings manager.
	 *
	 * @var SPC_Settings
	 */
	private $settings;

	/**
	 * Logger.
	 *
	 * @var SPC_Logger
	 */
	private $logger;

	/**
	 * Rate limiter.
	 *
	 * @var SPC_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Constructor.
	 *
	 * @param SPC_Settings $settings Settings manager.
	 */
	public function __construct( SPC_Settings $settings ) {
		$this->settings     = $settings;
		$this->logger       = new SPC_Logger();
		$this->rate_limiter = new SPC_Rate_Limiter();
	}

	/**
	 * Public lead permission check.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return true;
	}

	/**
	 * Handle lead request.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_lead( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) && ! $this->rate_limiter->is_allowed( 'lead', 3, HOUR_IN_SECONDS ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Too many lead submissions. Please try again later.', 'slava-portfolio-chatbot' ),
				),
				429
			);
		}

		$name            = sanitize_text_field( wp_unslash( $request->get_param( 'name' ) ) );
		$email           = sanitize_email( wp_unslash( $request->get_param( 'email' ) ) );
		$company         = sanitize_text_field( wp_unslash( $request->get_param( 'company' ) ) );
		$country         = sanitize_text_field( wp_unslash( $request->get_param( 'country' ) ) );
		$visitor_type    = sanitize_text_field( wp_unslash( $request->get_param( 'visitor_type' ) ) );
		$interest_area   = sanitize_text_field( wp_unslash( $request->get_param( 'interest_area' ) ) );
		$message         = sanitize_textarea_field( wp_unslash( $request->get_param( 'message' ) ) );
		$source_page     = esc_url_raw( wp_unslash( $request->get_param( 'source_page' ) ) );
		$conversation_id = sanitize_text_field( wp_unslash( $request->get_param( 'conversation_id' ) ) );
		$consent_given   = filter_var( $request->get_param( 'consent_given' ), FILTER_VALIDATE_BOOLEAN );

		if ( '' === $name ) {
			return $this->validation_error( __( 'Name is required.', 'slava-portfolio-chatbot' ) );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			return $this->validation_error( __( 'A valid email address is required.', 'slava-portfolio-chatbot' ) );
		}

		if ( ! $consent_given ) {
			return $this->validation_error( __( 'Consent is required before submitting your details.', 'slava-portfolio-chatbot' ) );
		}

		if ( strlen( $message ) > 2000 ) {
			return $this->validation_error( __( 'Message is too long. Please keep it under 2,000 characters.', 'slava-portfolio-chatbot' ) );
		}

		global $wpdb;

		$table    = $wpdb->prefix . 'spc_leads';
		$inserted = $wpdb->insert(
			$table,
			array(
				'created_at'      => current_time( 'mysql' ),
				'name'            => $name,
				'email'           => $email,
				'company'         => $company,
				'country'         => $country,
				'visitor_type'    => $visitor_type,
				'interest_area'   => $interest_area,
				'message'         => $message,
				'source_page'     => $source_page,
				'consent_given'   => 1,
				'conversation_id' => $conversation_id,
				'status'          => 'new',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Could not save the lead. Please try again.', 'slava-portfolio-chatbot' ),
				),
				500
			);
		}

		$this->logger->log_chat_message(
			array(
				'conversation_id' => $conversation_id,
				'role'            => 'system',
				'message_text'    => 'Lead captured.',
				'source_page'     => $source_page,
				'lead_captured'   => true,
				'session_hash'    => $this->rate_limiter->get_session_hash(),
			)
		);

		$this->maybe_send_notification( $name, $email, $company, $interest_area, $message, $source_page );

		return rest_ensure_response(
			array(
				'success'           => true,
				'thank_you_message' => __( 'Thanks. Your message has been saved, and Slava can follow up with you directly.', 'slava-portfolio-chatbot' ),
			)
		);
	}

	/**
	 * Build a validation error response.
	 *
	 * @param string $message Error message.
	 *
	 * @return WP_REST_Response
	 */
	private function validation_error( $message ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
			),
			400
		);
	}

	/**
	 * Send optional lead notification.
	 *
	 * @param string $name          Name.
	 * @param string $email         Email.
	 * @param string $company       Company.
	 * @param string $interest_area Interest area.
	 * @param string $message       Message.
	 * @param string $source_page   Source page.
	 *
	 * @return void
	 */
	private function maybe_send_notification( $name, $email, $company, $interest_area, $message, $source_page ) {
		if ( '1' !== $this->settings->get( 'email_notifications', '0' ) ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );

		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$subject = __( 'New portfolio chatbot lead', 'slava-portfolio-chatbot' );
		$body    = "A new lead was captured from the portfolio chatbot.\n\n"
			. "Name: {$name}\n"
			. "Email: {$email}\n"
			. "Company: {$company}\n"
			. "Interest area: {$interest_area}\n"
			. "Source page: {$source_page}\n\n"
			. "Message:\n{$message}\n";

		wp_mail( $admin_email, $subject, $body );
	}
}
