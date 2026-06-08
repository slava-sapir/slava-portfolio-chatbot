<?php
/**
 * OpenAI client.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backend-only OpenAI API client.
 */
class SPC_OpenAI_Client {
	/**
	 * Settings manager.
	 *
	 * @var SPC_Settings
	 */
	private $settings;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.openai.com/v1';

	/**
	 * Constructor.
	 *
	 * @param SPC_Settings|null $settings Settings manager.
	 */
	public function __construct( SPC_Settings $settings = null ) {
		$this->settings = $settings ? $settings : new SPC_Settings();
	}

	/**
	 * Create an embedding for one text input.
	 *
	 * @param string $text Input text.
	 *
	 * @return array
	 */
	public function create_embedding( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return $this->error_response( 'empty_input', 'Text is required to create an embedding.' );
		}

		$api_key = $this->get_api_key();

		if ( '' === $api_key ) {
			return $this->error_response( 'missing_openai_api_key', 'OpenAI API key is not configured.' );
		}

		$response = wp_remote_post(
			$this->api_base . '/embeddings',
			array(
				'timeout' => 90,
				'headers' => $this->get_headers( $api_key ),
				'body'    => wp_json_encode(
					array(
						'model' => $this->settings->get( 'openai_embedding_model', 'text-embedding-3-small' ),
						'input' => $text,
					)
				),
			)
		);

		$parsed = $this->parse_response( $response );

		if ( ! $parsed['success'] ) {
			return $parsed;
		}

		if ( empty( $parsed['data']['data'][0]['embedding'] ) || ! is_array( $parsed['data']['data'][0]['embedding'] ) ) {
			return $this->error_response( 'invalid_embedding_response', 'OpenAI did not return an embedding.' );
		}

		return array(
			'success' => true,
			'data'    => array(
				'embedding' => $parsed['data']['data'][0]['embedding'],
				'model'     => isset( $parsed['data']['model'] ) ? $parsed['data']['model'] : $this->settings->get( 'openai_embedding_model' ),
				'usage'     => isset( $parsed['data']['usage'] ) ? $parsed['data']['usage'] : array(),
			),
		);
	}

	/**
	 * Create a chat response.
	 *
	 * @param array $messages Chat messages in OpenAI chat format.
	 * @param array $options  Optional request overrides.
	 *
	 * @return array
	 */
	public function create_chat_response( array $messages, array $options = array() ) {
		if ( empty( $messages ) ) {
			return $this->error_response( 'empty_messages', 'At least one chat message is required.' );
		}

		$api_key = $this->get_api_key();

		if ( '' === $api_key ) {
			return $this->error_response( 'missing_openai_api_key', 'OpenAI API key is not configured.' );
		}

		$body = wp_parse_args(
			$options,
			array(
				'model'       => $this->settings->get( 'openai_chat_model', 'gpt-4.1-mini' ),
				'messages'    => $messages,
				'temperature' => 0.3,
			)
		);

		$response = wp_remote_post(
			$this->api_base . '/chat/completions',
			array(
				'timeout' => 90,
				'headers' => $this->get_headers( $api_key ),
				'body'    => wp_json_encode( $body ),
			)
		);

		$parsed = $this->parse_response( $response );

		if ( ! $parsed['success'] ) {
			return $parsed;
		}

		if ( empty( $parsed['data']['choices'][0]['message']['content'] ) ) {
			return $this->error_response( 'invalid_chat_response', 'OpenAI did not return a chat message.' );
		}

		return array(
			'success' => true,
			'data'    => array(
				'message' => $parsed['data']['choices'][0]['message']['content'],
				'raw'     => $parsed['data'],
				'usage'   => isset( $parsed['data']['usage'] ) ? $parsed['data']['usage'] : array(),
			),
		);
	}

	/**
	 * Get authorization headers.
	 *
	 * @param string $api_key OpenAI API key.
	 *
	 * @return array
	 */
	private function get_headers( $api_key ) {
		return array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Get configured API key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		return trim( (string) $this->settings->get( 'openai_api_key', '' ) );
	}

	/**
	 * Parse a WordPress HTTP API response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 *
	 * @return array
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $this->error_response( 'openai_http_error', $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'OpenAI request failed.';

			if ( ! isset( $data['error']['message'] ) ) {
				$body_excerpt = $this->get_safe_body_excerpt( $body );

				if ( '' !== $body_excerpt ) {
					$message .= ' Response excerpt: ' . $body_excerpt;
				}
			}

			return $this->error_response( 'openai_api_error', $message, $status_code );
		}

		if ( ! is_array( $data ) ) {
			return $this->error_response( 'openai_invalid_json', 'OpenAI returned an invalid JSON response.', $status_code );
		}

		return array(
			'success'     => true,
			'status_code' => $status_code,
			'data'        => $data,
		);
	}

	/**
	 * Build a structured error response.
	 *
	 * @param string $code        Error code.
	 * @param string $message     Human-readable message.
	 * @param int    $status_code Optional HTTP status code.
	 *
	 * @return array
	 */
	private function error_response( $code, $message, $status_code = 0 ) {
		return array(
			'success'     => false,
			'error'       => array(
				'code'    => $code,
				'message' => $message,
			),
			'status_code' => $status_code,
		);
	}

	/**
	 * Get a short sanitized body excerpt for diagnostics.
	 *
	 * @param string $body Response body.
	 *
	 * @return string
	 */
	private function get_safe_body_excerpt( $body ) {
		$body = trim( wp_strip_all_tags( (string) $body ) );
		$body = preg_replace( '/\s+/u', ' ', $body );

		if ( '' === $body ) {
			return '';
		}

		return substr( sanitize_text_field( $body ), 0, 300 );
	}
}
