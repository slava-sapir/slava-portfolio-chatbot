<?php
/**
 * Chat controller.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles chatbot REST requests.
 */
class SPC_Chat_Controller {
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
		$this->logger       = new SPC_Logger();
		$this->rate_limiter = new SPC_Rate_Limiter();
		$this->analytics    = new SPC_Analytics();
	}

	/**
	 * Public chat permission check.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return true;
	}

	/**
	 * Handle chat request.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_chat( WP_REST_Request $request ) {
		if ( ! $this->settings->is_chatbot_enabled() ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Chatbot is disabled.', 'slava-portfolio-chatbot' ),
				),
				403
			);
		}

		if ( ! $this->rate_limiter->is_allowed( 'chat', 20, 10 * MINUTE_IN_SECONDS ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Too many chat requests. Please try again shortly.', 'slava-portfolio-chatbot' ),
				),
				429
			);
		}

		$message = sanitize_textarea_field( wp_unslash( $request->get_param( 'message' ) ) );

		if ( '' === trim( $message ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Message is required.', 'slava-portfolio-chatbot' ),
				),
				400
			);
		}

		if ( strlen( $message ) > 2000 ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Message is too long. Please keep it under 2,000 characters.', 'slava-portfolio-chatbot' ),
				),
				400
			);
		}

		$daily_openai_request_cap = absint( $this->settings->get( 'daily_openai_request_cap', 100 ) );

		if ( ! $this->rate_limiter->is_daily_cap_available( 'openai_chat', $daily_openai_request_cap ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'The assistant has reached today\'s usage limit. Please try again tomorrow or contact Slava directly.', 'slava-portfolio-chatbot' ),
					'code'    => 'daily_openai_cap_reached',
				),
				429
			);
		}

		$conversation_id = sanitize_text_field( wp_unslash( $request->get_param( 'conversation_id' ) ) );
		$language        = sanitize_text_field( wp_unslash( $request->get_param( 'language' ) ) );
		$visitor_type    = sanitize_text_field( wp_unslash( $request->get_param( 'visitor_type' ) ) );
		$interest_area   = sanitize_text_field( wp_unslash( $request->get_param( 'interest_area' ) ) );
		$source_page     = esc_url_raw( wp_unslash( $request->get_param( 'source_page' ) ) );
		$session_hash    = $this->rate_limiter->get_session_hash();

		if ( '' === $conversation_id ) {
			$conversation_id = wp_generate_uuid4();
		}

		if ( '' === $language ) {
			$language = sanitize_text_field( $this->settings->get( 'default_language', 'en' ) );
		}

		$this->logger->log_chat_message(
			array(
				'conversation_id' => $conversation_id,
				'role'            => 'user',
				'message_text'    => $message,
				'language'        => $language,
				'source_page'     => $source_page,
				'session_hash'    => $session_hash,
			)
		);

		$this->analytics->track_event(
			'chat_question',
			array(
				'conversation_id' => $conversation_id,
				'language'        => $language,
				'visitor_type'    => $visitor_type,
				'interest_area'   => $interest_area,
				'source_page'     => $source_page,
				'session_hash'    => $session_hash,
			)
		);

		$is_project_query = $this->is_project_query( $message );
		$retrieval_query  = $is_project_query
			? 'Portfolio projects case studies selected work examples project names project descriptions. ' . $message
			: $message;

		$embedding = $this->openai->create_embedding( $retrieval_query );

		if ( ! $embedding['success'] ) {
			return $this->error_response( $embedding, 502 );
		}

		$matches = $this->supabase->similarity_search( $embedding['data']['embedding'], 8, 0.2, $language );

		if ( ! $matches['success'] ) {
			return $this->error_response( $matches, 502 );
		}

		$chunks         = is_array( $matches['data'] ) ? $this->prioritize_chunks_for_intent( $matches['data'], $message ) : array();
		$chunks         = $is_project_query ? $this->prefer_project_chunks( $chunks ) : $chunks;
		$retrieval_evaluation = $this->guardrails->evaluate_retrieval( $chunks, $message );
		$weak_retrieval       = $retrieval_evaluation['is_weak'];
		$chunk_ids      = wp_list_pluck( $chunks, 'chunk_id' );
		$suggested_links = $this->get_suggested_links( $chunks );
		$source_labels   = $this->get_source_labels( $chunks );
		$source_snippets = $this->get_source_snippets( $chunks, $message );
		$show_lead_form  = $this->should_show_lead_form( $message );

		if ( $show_lead_form ) {
			$assistant_message = $this->get_lead_capture_intro();

			$this->logger->log_chat_message(
				array(
					'conversation_id'     => $conversation_id,
					'role'                => 'assistant',
					'message_text'        => $assistant_message,
					'language'            => $language,
					'source_page'         => $source_page,
					'retrieved_chunk_ids' => $chunk_ids,
					'lead_captured'       => false,
					'session_hash'        => $session_hash,
				)
			);

			$this->analytics->track_event(
				'chat_answer',
				array(
					'conversation_id' => $conversation_id,
					'language'        => $language,
					'visitor_type'    => $visitor_type,
					'interest_area'   => $interest_area,
					'source_page'     => $source_page,
					'is_fallback'     => $weak_retrieval,
					'metadata'        => array( 'lead_form_shown' => true ),
					'session_hash'    => $session_hash,
				)
			);

			return rest_ensure_response(
				array(
					'conversation_id'   => $conversation_id,
					'assistant_message' => $assistant_message,
					'quick_replies'     => $this->get_quick_replies( true ),
					'suggested_links'   => $suggested_links,
					'source_labels'     => $source_labels,
					'source_snippets'   => $source_snippets,
					'show_lead_form'    => true,
					'weak_retrieval'    => $weak_retrieval,
				)
			);
		}

		if ( $weak_retrieval ) {
			$assistant_message = $this->guardrails->get_fallback_response( $retrieval_evaluation['reason'] );

			$this->logger->log_chat_message(
				array(
					'conversation_id'     => $conversation_id,
					'role'                => 'assistant',
					'message_text'        => $assistant_message,
					'language'            => $language,
					'source_page'         => $source_page,
					'retrieved_chunk_ids' => $chunk_ids,
					'lead_captured'       => false,
					'session_hash'        => $session_hash,
				)
			);

			$this->analytics->track_event(
				'fallback',
				array(
					'conversation_id' => $conversation_id,
					'language'        => $language,
					'visitor_type'    => $visitor_type,
					'interest_area'   => $interest_area,
					'source_page'     => $source_page,
					'is_fallback'     => true,
					'metadata'        => array( 'reason' => $retrieval_evaluation['reason'] ),
					'session_hash'    => $session_hash,
				)
			);

			return rest_ensure_response(
				array(
					'conversation_id'    => $conversation_id,
					'assistant_message'  => $assistant_message,
					'quick_replies'      => $this->get_quick_replies( $show_lead_form ),
					'suggested_links'    => $suggested_links,
					'source_labels'      => $source_labels,
					'source_snippets'    => $source_snippets,
					'show_lead_form'     => $show_lead_form,
					'weak_retrieval'     => true,
					'fallback_reason'    => $retrieval_evaluation['reason'],
				)
			);
		}

		$chat_response = $this->openai->create_chat_response(
			$this->build_messages( $message, $chunks, $language, $visitor_type, $interest_area )
		);

		if ( ! $chat_response['success'] ) {
			return $this->error_response( $chat_response, 502 );
		}

		$assistant_message = $chat_response['data']['message'];
		$show_lead_form    = $this->should_show_lead_form( $assistant_message );

		$this->logger->log_chat_message(
			array(
				'conversation_id'     => $conversation_id,
				'role'                => 'assistant',
				'message_text'        => $assistant_message,
				'language'            => $language,
				'source_page'         => $source_page,
				'retrieved_chunk_ids' => $chunk_ids,
				'lead_captured'       => false,
				'session_hash'        => $session_hash,
			)
		);

		$this->analytics->track_event(
			'chat_answer',
			array(
				'conversation_id' => $conversation_id,
				'language'        => $language,
				'visitor_type'    => $visitor_type,
				'interest_area'   => $interest_area,
				'source_page'     => $source_page,
				'is_fallback'     => false,
				'session_hash'    => $session_hash,
			)
		);

		return rest_ensure_response(
			array(
				'conversation_id'   => $conversation_id,
				'assistant_message' => $assistant_message,
				'quick_replies'     => $this->get_quick_replies( $show_lead_form ),
				'suggested_links'   => $suggested_links,
				'source_labels'     => $source_labels,
				'source_snippets'   => $source_snippets,
				'show_lead_form'    => $show_lead_form,
				'weak_retrieval'    => false,
			)
		);
	}

	/**
	 * Build OpenAI messages for grounded RAG answering.
	 *
	 * @param string $message       User message.
	 * @param array  $chunks        Retrieved chunks.
	 * @param string $language      Language.
	 * @param string $visitor_type  Visitor type.
	 * @param string $interest_area Interest area.
	 *
	 * @return array
	 */
	private function build_messages( $message, array $chunks, $language, $visitor_type, $interest_area ) {
		$context_blocks = array();

		foreach ( $chunks as $index => $chunk ) {
			$context_blocks[] = sprintf(
				"Source %d\nTitle: %s\nURL: %s\nContent: %s",
				$index + 1,
				isset( $chunk['page_title'] ) ? $chunk['page_title'] : '',
				isset( $chunk['page_url'] ) ? $chunk['page_url'] : '',
				isset( $chunk['content'] ) ? $chunk['content'] : ''
			);
		}

		$system_prompt = $this->guardrails->get_system_prompt( $this->settings->get( 'system_prompt', '' ) );

		return array(
			array(
				'role'    => 'system',
				'content' => $system_prompt . "\n\nProject-answer guidance:\n- When the user asks for projects, examples, portfolio work, or case studies, include relevant project links if the retrieved context contains URLs.\n- Put each project URL next to the project name or in the same bullet.\n- Do not invent project URLs. If only a portfolio page or GitHub link is available, use that instead.",
			),
			array(
				'role'    => 'user',
				'content' => "Visitor context:\nLanguage: {$language}\nVisitor type: {$visitor_type}\nInterest area: {$interest_area}\n\nRetrieved context:\n" . implode( "\n\n---\n\n", $context_blocks ) . "\n\nUser question:\n" . $message,
			),
		);
	}

	/**
	 * Build suggested source links.
	 *
	 * @param array $chunks Retrieved chunks.
	 *
	 * @return array
	 */
	private function get_suggested_links( array $chunks ) {
		$links = array();

		foreach ( $chunks as $chunk ) {
			$url   = isset( $chunk['page_url'] ) ? esc_url_raw( $chunk['page_url'] ) : '';
			$title = isset( $chunk['page_title'] ) ? sanitize_text_field( $chunk['page_title'] ) : '';

			if ( '' === $url || isset( $links[ $url ] ) ) {
				continue;
			}

			$links[ $url ] = array(
				'title' => $title,
				'url'   => $url,
			);
		}

		return array_values( $links );
	}

	/**
	 * Build human-readable source labels.
	 *
	 * @param array $chunks Retrieved chunks.
	 *
	 * @return array
	 */
	private function get_source_labels( array $chunks ) {
		$labels = array();

		foreach ( $chunks as $chunk ) {
			if ( ! empty( $chunk['page_title'] ) ) {
				$labels[] = sanitize_text_field( $chunk['page_title'] );
			}
		}

		return array_values( array_unique( $labels ) );
	}

	/**
	 * Build short source snippets from retrieved chunks.
	 *
	 * @param array $chunks Retrieved chunks.
	 *
	 * @return array
	 */
	private function get_source_snippets( array $chunks, $message ) {
		$snippets = array();

		foreach ( $chunks as $chunk ) {
			$url     = isset( $chunk['page_url'] ) ? esc_url_raw( $chunk['page_url'] ) : '';
			$title   = isset( $chunk['page_title'] ) ? sanitize_text_field( $chunk['page_title'] ) : '';
			$content = isset( $chunk['content'] ) ? $this->make_snippet( $chunk['content'], $message ) : '';

			if ( '' === $url || '' === $content || isset( $snippets[ $url ] ) ) {
				continue;
			}

			$snippets[ $url ] = array(
				'title'      => $title,
				'url'        => $url,
				'snippet'    => $content,
				'similarity' => isset( $chunk['similarity'] ) ? round( (float) $chunk['similarity'], 3 ) : null,
			);

			if ( count( $snippets ) >= 3 ) {
				break;
			}
		}

		return array_values( $snippets );
	}

	/**
	 * Trim chunk content into a compact citation snippet.
	 *
	 * @param string $content Chunk content.
	 *
	 * @return string
	 */
	private function make_snippet( $content, $message = '' ) {
		$content = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $content ) ) );

		if ( strlen( $content ) <= 220 ) {
			return $content;
		}

		$position = $this->find_best_snippet_position( $content, $message );

		if ( $position > 0 ) {
			$start   = max( 0, $position - 80 );
			$snippet = substr( $content, $start, 240 );
			$prefix  = $start > 0 ? '...' : '';
			$suffix  = ( $start + strlen( $snippet ) ) < strlen( $content ) ? '...' : '';

			return $prefix . rtrim( trim( $snippet ), " \t\n\r\0\x0B.,;:" ) . $suffix;
		}

		return rtrim( substr( $content, 0, 220 ), " \t\n\r\0\x0B.,;:" ) . '...';
	}

	/**
	 * Prioritize retrieved chunks for obvious user intents.
	 *
	 * @param array  $chunks  Retrieved chunks.
	 * @param string $message User message.
	 *
	 * @return array
	 */
	private function prioritize_chunks_for_intent( array $chunks, $message ) {
		if ( ! $this->is_project_query( $message ) ) {
			return $chunks;
		}

		$project_chunks = array();
		$other_chunks   = array();

		foreach ( $chunks as $chunk ) {
			$title = isset( $chunk['page_title'] ) ? strtolower( (string) $chunk['page_title'] ) : '';
			$url   = isset( $chunk['page_url'] ) ? strtolower( (string) $chunk['page_url'] ) : '';

			if ( preg_match( '/portfolio|project|projects|work/i', $title . ' ' . $url ) ) {
				$project_chunks[] = $chunk;
			} else {
				$other_chunks[] = $chunk;
			}
		}

		return ! empty( $project_chunks ) ? array_merge( $project_chunks, $other_chunks ) : $chunks;
	}

	/**
	 * For project queries, use project/portfolio chunks only when available.
	 *
	 * @param array $chunks Retrieved chunks.
	 *
	 * @return array
	 */
	private function prefer_project_chunks( array $chunks ) {
		$project_chunks = array();

		foreach ( $chunks as $chunk ) {
			$title   = isset( $chunk['page_title'] ) ? strtolower( (string) $chunk['page_title'] ) : '';
			$url     = isset( $chunk['page_url'] ) ? strtolower( (string) $chunk['page_url'] ) : '';
			$content = isset( $chunk['content'] ) ? strtolower( (string) $chunk['content'] ) : '';

			if ( preg_match( '/portfolio|project|projects|case stud|selected work|work samples/i', $title . ' ' . $url . ' ' . $content ) ) {
				$project_chunks[] = $chunk;
			}
		}

		return ! empty( $project_chunks ) ? $project_chunks : $chunks;
	}

	/**
	 * Is this a project/portfolio query?
	 *
	 * @param string $message User message.
	 *
	 * @return bool
	 */
	private function is_project_query( $message ) {
		return (bool) preg_match( '/\b(project|projects|portfolio|case stud|examples|work samples|show.*work|show.*project)\b/i', (string) $message );
	}

	/**
	 * Find the best snippet starting point based on user query terms.
	 *
	 * @param string $content Chunk content.
	 * @param string $message User message.
	 *
	 * @return int
	 */
	private function find_best_snippet_position( $content, $message ) {
		$terms = preg_split( '/[^a-z0-9]+/i', strtolower( (string) $message ) );
		$terms = array_values(
			array_filter(
				$terms,
				function ( $term ) {
					$stopwords = array( 'show', 'tell', 'about', 'what', 'can', 'does', 'the', 'and', 'for', 'with', 'slava', 'relevant' );

					return strlen( $term ) >= 4 && ! in_array( $term, $stopwords, true );
				}
			)
		);

		foreach ( $terms as $term ) {
			$position = stripos( $content, $term );

			if ( false !== $position ) {
				return (int) $position;
			}
		}

		return 0;
	}

	/**
	 * Get quick replies.
	 *
	 * @param bool $show_lead_form Whether lead form is suggested.
	 *
	 * @return array
	 */
	private function get_quick_replies( $show_lead_form ) {
		if ( $show_lead_form ) {
			return array( 'Contact Slava', 'View projects', 'Ask another question' );
		}

		return array( 'Tell me about Slava', 'Show relevant projects', 'How can I contact Slava?' );
	}

	/**
	 * Get a concise lead capture intro.
	 *
	 * @return string
	 */
	private function get_lead_capture_intro() {
		return __( 'Sure. Please send your details in the form below, and Slava can follow up with you directly.', 'slava-portfolio-chatbot' );
	}

	/**
	 * Detect basic hiring/contact intent.
	 *
	 * @param string $text Text to inspect.
	 *
	 * @return bool
	 */
	private function should_show_lead_form( $text ) {
		return (bool) preg_match( '/\b(hire|hiring|available|availability|contact|email|quote|proposal|work together|collaborate|start a project|new project|need a website|build a website|website help)\b/i', $text );
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
		$message = isset( $error_response['error']['message'] ) ? $error_response['error']['message'] : __( 'Chat request failed.', 'slava-portfolio-chatbot' );
		$code    = isset( $error_response['error']['code'] ) ? $error_response['error']['code'] : 'chat_error';

		return new WP_REST_Response(
			array(
				'message' => $message,
				'code'    => $code,
			),
			$status
		);
	}
}
