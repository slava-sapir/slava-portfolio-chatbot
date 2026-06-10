<?php
/**
 * Embedded Q&A controller.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles page-specific Q&A REST requests.
 */
class SPC_QA_Controller {
	/**
	 * Settings manager.
	 *
	 * @var SPC_Settings
	 */
	private $settings;

	/**
	 * OpenAI client.
	 *
	 * @var SPC_OpenAI_Client
	 */
	private $openai;

	/**
	 * Supabase client.
	 *
	 * @var SPC_Supabase_Client
	 */
	private $supabase;

	/**
	 * Guardrails helper.
	 *
	 * @var SPC_Guardrails
	 */
	private $guardrails;

	/**
	 * Rate limiter.
	 *
	 * @var SPC_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Analytics helper.
	 *
	 * @var SPC_Analytics
	 */
	private $analytics;

	/**
	 * Constructor.
	 *
	 * @param SPC_Settings $settings Settings manager.
	 */
	public function __construct( SPC_Settings $settings ) {
		$this->settings     = $settings;
		$this->openai       = new SPC_OpenAI_Client( $settings );
		$this->supabase     = new SPC_Supabase_Client( $settings );
		$this->guardrails   = new SPC_Guardrails();
		$this->rate_limiter = new SPC_Rate_Limiter();
		$this->analytics    = new SPC_Analytics();
	}

	/**
	 * Public Q&A permission check.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return true;
	}

	/**
	 * Handle page-specific Q&A request.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_qa( WP_REST_Request $request ) {
		if ( ! $this->settings->is_chatbot_enabled() ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Q&A is disabled.', 'slava-portfolio-chatbot' ),
				),
				403
			);
		}

		if ( ! $this->rate_limiter->is_allowed( 'qa', 20, 10 * MINUTE_IN_SECONDS ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Too many Q&A requests. Please try again shortly.', 'slava-portfolio-chatbot' ),
				),
				429
			);
		}

		$message = sanitize_textarea_field( wp_unslash( $request->get_param( 'message' ) ) );
		$page_id = absint( $request->get_param( 'page_id' ) );
		$scope   = 'site' === sanitize_key( wp_unslash( $request->get_param( 'scope' ) ) ) ? 'site' : 'page';

		if ( '' === trim( $message ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Question is required.', 'slava-portfolio-chatbot' ),
				),
				400
			);
		}

		if ( strlen( $message ) > 1000 ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Question is too long. Please keep it under 1,000 characters.', 'slava-portfolio-chatbot' ),
				),
				400
			);
		}

		$page = $page_id ? get_post( $page_id ) : null;

		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'The requested Q&A source page is not available.', 'slava-portfolio-chatbot' ),
				),
				400
			);
		}

		$daily_openai_request_cap = absint( $this->settings->get( 'daily_openai_request_cap', 100 ) );

		if ( ! $this->rate_limiter->is_daily_cap_available( 'openai_qa', $daily_openai_request_cap ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'The Q&A assistant has reached today\'s usage limit. Please try again tomorrow.', 'slava-portfolio-chatbot' ),
					'code'    => 'daily_openai_cap_reached',
				),
				429
			);
		}

		$language     = sanitize_text_field( wp_unslash( $request->get_param( 'language' ) ) );
		$language     = $language ? $language : sanitize_text_field( $this->settings->get( 'default_language', 'en' ) );
		$page_title   = get_the_title( $page );
		$page_url     = get_permalink( $page );
		$session_hash = $this->rate_limiter->get_session_hash();

		$this->analytics->track_event(
			'qa_question',
			array(
				'conversation_id' => 'qa-page-' . $page_id,
				'language'        => $language,
				'interest_area'   => 'Page Q&A',
				'source_page'     => $page_url,
				'session_hash'    => $session_hash,
			)
		);

		$embedding = $this->openai->create_embedding( $page_title . ' page Q&A. ' . $message );

		if ( ! $embedding['success'] ) {
			return $this->error_response( $embedding, 502 );
		}

		$matches = $this->supabase->similarity_search( $embedding['data']['embedding'], 20, 0.15, $language );

		if ( ! $matches['success'] ) {
			return $this->error_response( $matches, 502 );
		}

		$chunks = is_array( $matches['data'] ) ? $this->prepare_chunks_for_scope( $matches['data'], $page_id, $page_url, $scope ) : array();
		$chunks = array_slice( $chunks, 0, 6 );

		$retrieval_evaluation = $this->guardrails->evaluate_retrieval( $chunks, $message );

		if ( $retrieval_evaluation['is_weak'] ) {
			$answer = $this->get_page_fallback_response( $page_title, $retrieval_evaluation['reason'] );

			$this->analytics->track_event(
				'fallback',
				array(
					'conversation_id' => 'qa-page-' . $page_id,
					'language'        => $language,
					'interest_area'   => 'Page Q&A',
					'source_page'     => $page_url,
					'is_fallback'     => true,
					'metadata'        => array(
						'mode'   => 'qa',
						'scope'  => $scope,
						'reason' => $retrieval_evaluation['reason'],
					),
					'session_hash'    => $session_hash,
				)
			);

			return rest_ensure_response(
				array(
					'answer'           => $answer,
					'suggested_links'  => $this->get_suggested_links( $chunks, $page_title, $page_url ),
					'source_labels'    => $this->get_source_labels( $chunks, $page_title ),
					'weak_retrieval'   => true,
					'fallback_reason'  => $retrieval_evaluation['reason'],
					'allowed_link_domains' => $this->get_allowed_link_domains_for_chunks( $chunks, $page_url ),
				)
			);
		}

		$chat_response = $this->openai->create_chat_response(
			$this->build_messages( $message, $chunks, $page_title, $page_url, $language, $scope ),
			array(
				'temperature' => 0.2,
			)
		);

		if ( ! $chat_response['success'] ) {
			return $this->error_response( $chat_response, 502 );
		}

		$this->analytics->track_event(
			'qa_answer',
			array(
				'conversation_id' => 'qa-page-' . $page_id,
				'language'        => $language,
				'interest_area'   => 'Page Q&A',
				'source_page'     => $page_url,
				'is_fallback'     => false,
				'session_hash'    => $session_hash,
			)
		);

		return rest_ensure_response(
			array(
				'answer'           => $chat_response['data']['message'],
				'suggested_links'  => $this->get_suggested_links( $chunks, $page_title, $page_url ),
				'source_labels'    => $this->get_source_labels( $chunks, $page_title ),
				'weak_retrieval'   => false,
				'allowed_link_domains' => $this->get_allowed_link_domains_for_chunks( $chunks, $page_url ),
			)
		);
	}

	/**
	 * Build OpenAI messages for page-specific grounded answering.
	 *
	 * @param string $message    User question.
	 * @param array  $chunks     Retrieved chunks.
	 * @param string $page_title Source page title.
	 * @param string $page_url   Source page URL.
	 * @param string $language   Language code.
	 *
	 * @return array
	 */
	private function build_messages( $message, array $chunks, $page_title, $page_url, $language, $scope = 'page' ) {
		$context_blocks = array();

		foreach ( $chunks as $index => $chunk ) {
			$context_blocks[] = sprintf(
				"Source %d\nPage: %s\nURL: %s\nContent: %s",
				$index + 1,
				isset( $chunk['page_title'] ) && $chunk['page_title'] ? $chunk['page_title'] : $page_title,
				isset( $chunk['page_url'] ) && $chunk['page_url'] ? $chunk['page_url'] : $page_url,
				isset( $chunk['content'] ) ? $chunk['content'] : ''
			);
		}

		$system_prompt = $this->guardrails->get_system_prompt( $this->settings->get( 'system_prompt', '' ) );
		$scope_rule    = 'site' === $scope
			? "- You may answer from any retrieved approved website source, but prioritize the current page when it is relevant.\n"
			: "- Answer only from the retrieved context for the current page.\n";

		return array(
			array(
				'role'    => 'system',
				'content' => $system_prompt . "\n\nEmbedded page Q&A rules:\n" . $scope_rule . "- Keep answers concise and useful for someone reading this page.\n- Use short paragraphs or bullets when helpful.\n- If the retrieved context does not support the answer, say that the approved website content does not include enough confirmed information.\n- Do not turn general page questions into sales or hiring messages unless the user asks about contacting or hiring Slava.",
			),
			array(
				'role'    => 'user',
				'content' => "Current page:\nTitle: {$page_title}\nURL: {$page_url}\nLanguage: {$language}\n\nRetrieved context from this page:\n" . implode( "\n\n---\n\n", $context_blocks ) . "\n\nQuestion:\n" . $message,
			),
		);
	}

	/**
	 * Filter retrieved chunks to the requested page.
	 *
	 * @param array  $chunks   Retrieved chunks.
	 * @param int    $page_id  WordPress page ID.
	 * @param string $page_url Page URL.
	 *
	 * @return array
	 */
	private function filter_chunks_for_page( array $chunks, $page_id, $page_url ) {
		$filtered = array();
		$page_url = untrailingslashit( $page_url );

		foreach ( $chunks as $chunk ) {
			$chunk_url = isset( $chunk['page_url'] ) ? untrailingslashit( (string) $chunk['page_url'] ) : '';
			$metadata  = isset( $chunk['metadata'] ) && is_array( $chunk['metadata'] ) ? $chunk['metadata'] : array();
			$meta_page_id = isset( $metadata['wp_page_id'] ) ? absint( $metadata['wp_page_id'] ) : 0;

			if ( $meta_page_id === absint( $page_id ) || $chunk_url === $page_url ) {
				$filtered[] = $chunk;
			}
		}

		return $filtered;
	}

	/**
	 * Prepare chunks based on page-only or site-wide scope.
	 *
	 * @param array  $chunks   Retrieved chunks.
	 * @param int    $page_id  WordPress page ID.
	 * @param string $page_url Page URL.
	 * @param string $scope    Q&A scope.
	 *
	 * @return array
	 */
	private function prepare_chunks_for_scope( array $chunks, $page_id, $page_url, $scope ) {
		$page_chunks = $this->filter_chunks_for_page( $chunks, $page_id, $page_url );

		if ( 'site' !== $scope ) {
			return $page_chunks;
		}

		$seen = array();
		$site_chunks = array();

		foreach ( array_merge( $page_chunks, $chunks ) as $chunk ) {
			$key = isset( $chunk['chunk_id'] ) ? (string) $chunk['chunk_id'] : md5( wp_json_encode( $chunk ) );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$site_chunks[] = $chunk;
		}

		return $site_chunks;
	}

	/**
	 * Build source links for the response.
	 *
	 * @param array  $chunks     Retrieved chunks.
	 * @param string $page_title Page title.
	 * @param string $page_url   Page URL.
	 *
	 * @return array
	 */
	private function get_suggested_links( array $chunks, $page_title, $page_url ) {
		$links = array(
			$page_url => array(
				'title' => sanitize_text_field( $page_title ),
				'url'   => esc_url_raw( $page_url ),
			),
		);

		foreach ( $chunks as $chunk ) {
			if ( ! empty( $chunk['page_url'] ) ) {
				$url = esc_url_raw( $chunk['page_url'] );

				if ( '' !== $url ) {
					$links[ $url ] = array(
						'title' => ! empty( $chunk['page_title'] ) ? sanitize_text_field( $chunk['page_title'] ) : wp_parse_url( $url, PHP_URL_HOST ),
						'url'   => $url,
					);
				}
			}

			if ( empty( $chunk['content'] ) || ! preg_match_all( '/https?:\/\/[^\s)]+/i', (string) $chunk['content'], $matches ) ) {
				continue;
			}

			foreach ( $matches[0] as $url ) {
				$url = esc_url_raw( rtrim( $url, '.,;:' ) );

				if ( '' !== $url ) {
					$links[ $url ] = array(
						'title' => wp_parse_url( $url, PHP_URL_HOST ),
						'url'   => $url,
					);
				}
			}
		}

		return array_values( $links );
	}

	/**
	 * Build human-readable source labels.
	 *
	 * @param array  $chunks     Retrieved chunks.
	 * @param string $page_title Current page title.
	 *
	 * @return array
	 */
	private function get_source_labels( array $chunks, $page_title ) {
		$labels = array( sanitize_text_field( $page_title ) );

		foreach ( $chunks as $chunk ) {
			if ( ! empty( $chunk['page_title'] ) ) {
				$labels[] = sanitize_text_field( $chunk['page_title'] );
			}
		}

		return array_values( array_unique( array_filter( $labels ) ) );
	}

	/**
	 * Get domains allowed for links in this answer.
	 *
	 * @param array  $chunks   Retrieved chunks.
	 * @param string $page_url Page URL.
	 *
	 * @return array
	 */
	private function get_allowed_link_domains_for_chunks( array $chunks, $page_url ) {
		$domains = $this->get_allowed_link_domains_from_settings();
		$domains[] = $this->get_domain_from_url( $page_url );

		foreach ( $chunks as $chunk ) {
			if ( ! empty( $chunk['page_url'] ) ) {
				$domains[] = $this->get_domain_from_url( $chunk['page_url'] );
			}

			if ( empty( $chunk['content'] ) || ! preg_match_all( '/https?:\/\/[^\s)]+/i', (string) $chunk['content'], $matches ) ) {
				continue;
			}

			foreach ( $matches[0] as $url ) {
				$domains[] = $this->get_domain_from_url( $url );
			}
		}

		return array_values( array_filter( array_unique( $domains ) ) );
	}

	/**
	 * Get configured link allowlist domains.
	 *
	 * @return array
	 */
	private function get_allowed_link_domains_from_settings() {
		$raw     = (string) $this->settings->get( 'allowed_link_domains', '' );
		$lines   = preg_split( '/\r\n|\r|\n/', $raw );
		$domains = array();

		foreach ( $lines as $line ) {
			$domain = strtolower( trim( $line ) );

			if ( '' !== $domain ) {
				$domains[] = $domain;
			}
		}

		return $domains;
	}

	/**
	 * Extract a lowercase domain from a URL.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function get_domain_from_url( $url ) {
		$host = wp_parse_url( esc_url_raw( $url ), PHP_URL_HOST );

		return $host ? strtolower( $host ) : '';
	}

	/**
	 * Get page-focused fallback response.
	 *
	 * @param string $page_title Page title.
	 * @param string $reason     Fallback reason.
	 *
	 * @return string
	 */
	private function get_page_fallback_response( $page_title, $reason ) {
		if ( 'business_specific' === $reason ) {
			return __( 'This page does not include confirmed pricing, timing, or availability details. Please contact Slava directly for an accurate answer.', 'slava-portfolio-chatbot' );
		}

		if ( 'private_details' === $reason ) {
			return __( 'This page does not include private or unlisted personal details, so I cannot answer that from the approved content.', 'slava-portfolio-chatbot' );
		}

		return sprintf(
			/* translators: %s: page title */
			__( 'I do not have enough confirmed information from the %s page to answer that accurately.', 'slava-portfolio-chatbot' ),
			$page_title
		);
	}

	/**
	 * Convert a client error into a REST response.
	 *
	 * @param array $error_response Client response.
	 * @param int   $status         HTTP status.
	 *
	 * @return WP_REST_Response
	 */
	private function error_response( array $error_response, $status ) {
		$message = isset( $error_response['error']['message'] ) ? $error_response['error']['message'] : __( 'Q&A request failed.', 'slava-portfolio-chatbot' );
		$code    = isset( $error_response['error']['code'] ) ? $error_response['error']['code'] : 'qa_error';

		return new WP_REST_Response(
			array(
				'message' => $message,
				'code'    => $code,
			),
			$status
		);
	}
}
