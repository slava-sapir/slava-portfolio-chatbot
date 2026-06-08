<?php
/**
 * Settings manager.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and registers plugin settings.
 */
class SPC_Settings {
	/**
	 * Main option name.
	 */
	const OPTION_NAME = 'spc_settings';

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'openai_api_key'          => '',
			'openai_chat_model'       => 'gpt-4.1-mini',
			'openai_embedding_model'  => 'text-embedding-3-small',
			'supabase_url'            => '',
			'supabase_service_key'    => '',
			'chatbot_enabled'         => '0',
			'source_page_ids'         => array(),
			'default_language'        => 'en',
			'system_prompt'           => $this->get_default_system_prompt(),
			'chat_log_retention_days' => 90,
			'email_notifications'     => '0',
		);
	}

	/**
	 * Register initial settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'spc_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_defaults(),
			)
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param mixed $input Raw settings input.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$old      = $this->get_settings();
		$settings = $this->get_defaults();

		$settings['openai_api_key'] = $this->sanitize_secret(
			isset( $input['openai_api_key'] ) ? $input['openai_api_key'] : '',
			isset( $old['openai_api_key'] ) ? $old['openai_api_key'] : ''
		);

		$settings['openai_chat_model'] = sanitize_text_field(
			isset( $input['openai_chat_model'] ) ? wp_unslash( $input['openai_chat_model'] ) : $settings['openai_chat_model']
		);

		$settings['openai_embedding_model'] = sanitize_text_field(
			isset( $input['openai_embedding_model'] ) ? wp_unslash( $input['openai_embedding_model'] ) : $settings['openai_embedding_model']
		);

		$settings['supabase_url'] = esc_url_raw(
			isset( $input['supabase_url'] ) ? wp_unslash( $input['supabase_url'] ) : ''
		);

		$settings['supabase_service_key'] = $this->sanitize_secret(
			isset( $input['supabase_service_key'] ) ? $input['supabase_service_key'] : '',
			isset( $old['supabase_service_key'] ) ? $old['supabase_service_key'] : ''
		);

		$settings['chatbot_enabled'] = isset( $input['chatbot_enabled'] ) && '1' === (string) $input['chatbot_enabled'] ? '1' : '0';

		$page_ids = isset( $input['source_page_ids'] ) && is_array( $input['source_page_ids'] ) ? $input['source_page_ids'] : array();
		$settings['source_page_ids'] = array_values(
			array_filter(
				array_map( 'absint', $page_ids )
			)
		);

		$settings['default_language'] = sanitize_text_field(
			isset( $input['default_language'] ) ? wp_unslash( $input['default_language'] ) : $settings['default_language']
		);

		$settings['system_prompt'] = sanitize_textarea_field(
			isset( $input['system_prompt'] ) ? wp_unslash( $input['system_prompt'] ) : $settings['system_prompt']
		);

		$retention_days = isset( $input['chat_log_retention_days'] ) ? absint( $input['chat_log_retention_days'] ) : 90;
		$settings['chat_log_retention_days'] = max( 1, min( 365, $retention_days ) );

		$settings['email_notifications'] = isset( $input['email_notifications'] ) && '1' === (string) $input['email_notifications'] ? '1' : '0';

		return $settings;
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Migration bridge from the scaffold's first checkbox option.
		if ( ! isset( $settings['chatbot_enabled'] ) ) {
			$settings['chatbot_enabled'] = get_option( 'spc_chatbot_enabled', '0' );
		}

		return wp_parse_args( $settings, $this->get_defaults() );
	}

	/**
	 * Get one setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 *
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Is the frontend chatbot enabled?
	 *
	 * @return bool
	 */
	public function is_chatbot_enabled() {
		return '1' === $this->get( 'chatbot_enabled', '0' );
	}

	/**
	 * Sanitize a secret field while preserving existing value when the field is blank or masked.
	 *
	 * @param mixed  $value     New raw value.
	 * @param string $old_value Existing stored value.
	 *
	 * @return string
	 */
	private function sanitize_secret( $value, $old_value ) {
		$value = is_string( $value ) ? trim( wp_unslash( $value ) ) : '';

		if ( '' === $value || $this->get_masked_secret() === $value ) {
			return $old_value;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Get text shown in saved secret fields.
	 *
	 * @return string
	 */
	public function get_masked_secret() {
		return '********';
	}

	/**
	 * Get the default system prompt.
	 *
	 * @return string
	 */
	private function get_default_system_prompt() {
		return 'You are Slava portfolio assistant. Help visitors understand Slava\'s skills, projects, services, and experience using only approved portfolio knowledge base content. If the retrieved content does not support a factual answer, say you do not have enough confirmed information and suggest contacting Slava directly.';
	}
}
