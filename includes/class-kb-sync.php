<?php
/**
 * Knowledge base sync service.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs approved WordPress content into Supabase for retrieval.
 */
class SPC_KB_Sync {
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
	 * Logger.
	 *
	 * @var SPC_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param SPC_Settings|null        $settings Settings manager.
	 * @param SPC_OpenAI_Client|null   $openai   OpenAI client.
	 * @param SPC_Supabase_Client|null $supabase Supabase client.
	 * @param SPC_Logger|null          $logger   Logger.
	 */
	public function __construct( SPC_Settings $settings = null, SPC_OpenAI_Client $openai = null, SPC_Supabase_Client $supabase = null, SPC_Logger $logger = null ) {
		$this->settings = $settings ? $settings : new SPC_Settings();
		$this->openai   = $openai ? $openai : new SPC_OpenAI_Client( $this->settings );
		$this->supabase = $supabase ? $supabase : new SPC_Supabase_Client( $this->settings );
		$this->logger   = $logger ? $logger : new SPC_Logger();
	}

	/**
	 * Run knowledge base refresh.
	 *
	 * @return array
	 */
	public function refresh() {
		$page_ids = array_map( 'absint', (array) $this->settings->get( 'source_page_ids', array() ) );
		$page_ids = array_values( array_filter( $page_ids ) );
		$language = sanitize_text_field( $this->settings->get( 'default_language', 'en' ) );

		$result = array(
			'status'                    => 'success',
			'refreshed_documents_count' => 0,
			'refreshed_chunks_count'    => 0,
			'skipped_documents_count'   => 0,
			'errors'                    => array(),
		);

		if ( empty( $page_ids ) ) {
			$result['status']   = 'error';
			$result['errors'][] = 'No source pages selected.';

			return $result;
		}

		foreach ( $page_ids as $page_id ) {
			$page_result = $this->sync_page( $page_id, $language );

			if ( ! empty( $page_result['skipped'] ) ) {
				$result['skipped_documents_count']++;
				continue;
			}

			if ( empty( $page_result['success'] ) ) {
				$result['status']   = 'partial_error';
				$result['errors'][] = $page_result['message'];
				continue;
			}

			$result['refreshed_documents_count']++;
			$result['refreshed_chunks_count'] += isset( $page_result['chunks_count'] ) ? absint( $page_result['chunks_count'] ) : 0;
		}

		if ( 0 === $result['refreshed_documents_count'] && 0 === $result['skipped_documents_count'] && ! empty( $result['errors'] ) ) {
			$result['status'] = 'error';
		}

		return $result;
	}

	/**
	 * Sync one WordPress page.
	 *
	 * @param int    $page_id  Page ID.
	 * @param string $language Language code.
	 *
	 * @return array
	 */
	private function sync_page( $page_id, $language ) {
		$page = get_post( $page_id );

		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return array(
				'success' => false,
				'message' => 'Page is missing or not published: ' . $page_id,
			);
		}

		$title        = get_the_title( $page );
		$url          = get_permalink( $page );
		$modified_gmt = get_post_modified_time( 'c', true, $page );
		$text         = $this->extract_clean_text( $page );

		if ( '' === $text ) {
			return array(
				'success' => false,
				'message' => 'No clean text found for page: ' . $title,
			);
		}

		$checksum = hash( 'sha256', wp_json_encode( array( $title, $url, $modified_gmt, $text ) ) );

		$existing = $this->supabase->get_document_by_source( 'wp_page', (string) $page_id, $language );

		if ( ! $existing['success'] ) {
			$this->logger->log( 'error', 'Supabase document lookup failed.', array( 'page_id' => $page_id ) );

			return array(
				'success' => false,
				'message' => 'Could not check existing Supabase document for page: ' . $title,
			);
		}

		if ( ! empty( $existing['data']['checksum'] ) && hash_equals( $existing['data']['checksum'], $checksum ) ) {
			return array(
				'success' => true,
				'skipped' => true,
				'message' => 'Skipped unchanged page: ' . $title,
			);
		}

		$text_chunks = $this->chunk_text( $text );
		$chunks      = array();

		foreach ( $text_chunks as $index => $chunk_text ) {
			$embedding = $this->openai->create_embedding( $chunk_text );

			if ( ! $embedding['success'] ) {
				$message = isset( $embedding['error']['message'] ) ? $embedding['error']['message'] : 'Unknown OpenAI embedding error.';
				$code    = isset( $embedding['error']['code'] ) ? $embedding['error']['code'] : '';
				$status  = ! empty( $embedding['status_code'] ) ? absint( $embedding['status_code'] ) : 0;

				if ( $code ) {
					$message .= ' Code: ' . $code . '.';
				}

				if ( $status ) {
					$message .= ' HTTP status: ' . $status . '.';
				}

				return array(
					'success' => false,
					'message' => 'Embedding failed for page "' . $title . '": ' . $message,
				);
			}

			$chunks[] = array(
				'chunk_index' => $index,
				'content'     => $chunk_text,
				'token_count' => $this->estimate_token_count( $chunk_text ),
				'embedding'   => $embedding['data']['embedding'],
				'metadata'    => array(
					'page_title'    => $title,
					'page_url'      => $url,
					'section_name'  => 'WordPress page',
					'language'      => $language,
					'wp_page_id'    => $page_id,
					'source_type'   => 'wp_page',
					'chunk_index'   => $index,
				),
			);
		}

		$document = $this->supabase->upsert_document(
			array(
				'source_type'       => 'wp_page',
				'source_id'         => (string) $page_id,
				'title'             => $title,
				'url'               => $url,
				'language'          => $language,
				'checksum'          => $checksum,
				'metadata'          => array(
					'wp_post_type' => 'page',
					'wp_page_id'   => $page_id,
				),
				'source_updated_at' => $modified_gmt,
			)
		);

		if ( ! $document['success'] ) {
			$message = isset( $document['error']['message'] ) ? $document['error']['message'] : 'Unknown Supabase document upsert error.';

			return array(
				'success' => false,
				'message' => 'Supabase document upsert failed for page "' . $title . '": ' . $message,
			);
		}

		$replace = $this->supabase->replace_chunks( $document['data']['id'], $chunks );

		if ( ! $replace['success'] ) {
			$message = isset( $replace['error']['message'] ) ? $replace['error']['message'] : 'Unknown Supabase chunk replace error.';

			return array(
				'success' => false,
				'message' => 'Supabase chunk replace failed for page "' . $title . '": ' . $message,
			);
		}

		return array(
			'success'      => true,
			'skipped'      => false,
			'chunks_count' => count( $chunks ),
			'message'      => 'Refreshed page: ' . $title,
		);
	}

	/**
	 * Render and clean WordPress page content.
	 *
	 * @param WP_Post $page Page post.
	 *
	 * @return string
	 */
	private function extract_clean_text( WP_Post $page ) {
		$content = apply_filters( 'the_content', $page->post_content );
		$content = wp_strip_all_tags( $content, true );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$content = preg_replace( '/\s+/u', ' ', $content );

		return trim( $content );
	}

	/**
	 * Split text into overlapping chunks.
	 *
	 * @param string $text Input text.
	 *
	 * @return array
	 */
	private function chunk_text( $text ) {
		$max_length = 3500;
		$overlap    = 400;
		$text       = trim( (string) $text );
		$length     = strlen( $text );

		if ( $length <= $max_length ) {
			return array( $text );
		}

		$chunks = array();
		$start  = 0;

		while ( $start < $length ) {
			$chunk = substr( $text, $start, $max_length );

			if ( $start + $max_length < $length ) {
				$last_period = strrpos( $chunk, '. ' );

				if ( false !== $last_period && $last_period > 1200 ) {
					$chunk = substr( $chunk, 0, $last_period + 1 );
				}
			}

			$chunk = trim( $chunk );

			if ( '' !== $chunk ) {
				$chunks[] = $chunk;
			}

			$start += max( 1, strlen( $chunk ) - $overlap );
		}

		return $chunks;
	}

	/**
	 * Estimate token count for metadata/debugging.
	 *
	 * @param string $text Text.
	 *
	 * @return int
	 */
	private function estimate_token_count( $text ) {
		$word_count = str_word_count( wp_strip_all_tags( $text ) );

		return (int) ceil( $word_count * 1.33 );
	}
}
