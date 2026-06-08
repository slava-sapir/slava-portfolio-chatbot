<?php
/**
 * Supabase client.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backend-only Supabase API client.
 */
class SPC_Supabase_Client {
	/**
	 * Settings manager.
	 *
	 * @var SPC_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SPC_Settings|null $settings Settings manager.
	 */
	public function __construct( SPC_Settings $settings = null ) {
		$this->settings = $settings ? $settings : new SPC_Settings();
	}

	/**
	 * Upsert a knowledge base document.
	 *
	 * @param array $document Document fields.
	 *
	 * @return array
	 */
	public function upsert_document( array $document ) {
		$required = array( 'source_type', 'source_id', 'title', 'url', 'language', 'checksum' );

		foreach ( $required as $field ) {
			if ( ! isset( $document[ $field ] ) || '' === (string) $document[ $field ] ) {
				return $this->error_response( 'missing_document_field', 'Missing required document field: ' . $field );
			}
		}

		$payload = array(
			'source_type'       => sanitize_text_field( $document['source_type'] ),
			'source_id'         => sanitize_text_field( $document['source_id'] ),
			'title'             => sanitize_text_field( $document['title'] ),
			'url'               => esc_url_raw( $document['url'] ),
			'language'          => sanitize_text_field( $document['language'] ),
			'checksum'          => sanitize_text_field( $document['checksum'] ),
			'metadata'          => isset( $document['metadata'] ) && is_array( $document['metadata'] ) ? $document['metadata'] : array(),
			'source_updated_at' => isset( $document['source_updated_at'] ) ? sanitize_text_field( $document['source_updated_at'] ) : null,
		);

		$url = $this->get_rest_url( 'kb_documents' ) . '?on_conflict=source_type,source_id,language';

		$response = $this->request(
			'POST',
			$url,
			array( $payload ),
			array(
				'Prefer' => 'resolution=merge-duplicates,return=representation',
			)
		);

		if ( ! $response['success'] ) {
			return $response;
		}

		if ( empty( $response['data'][0]['id'] ) ) {
			return $this->error_response( 'invalid_document_upsert_response', 'Supabase did not return the upserted document ID.' );
		}

		return array(
			'success' => true,
			'data'    => $response['data'][0],
		);
	}

	/**
	 * Get a document by WordPress source identity.
	 *
	 * @param string $source_type Source type.
	 * @param string $source_id   Source ID.
	 * @param string $language    Language code.
	 *
	 * @return array
	 */
	public function get_document_by_source( $source_type, $source_id, $language = 'en' ) {
		$query = add_query_arg(
			array(
				'source_type' => 'eq.' . sanitize_text_field( $source_type ),
				'source_id'   => 'eq.' . sanitize_text_field( $source_id ),
				'language'    => 'eq.' . sanitize_text_field( $language ),
				'select'      => 'id,checksum,title,url,language,source_updated_at',
				'limit'       => 1,
			),
			$this->get_rest_url( 'kb_documents' )
		);

		$response = $this->request( 'GET', $query );

		if ( ! $response['success'] ) {
			return $response;
		}

		return array(
			'success' => true,
			'data'    => ! empty( $response['data'][0] ) ? $response['data'][0] : null,
		);
	}

	/**
	 * Delete existing chunks for a document and insert replacement chunks.
	 *
	 * @param string $document_id Supabase document UUID.
	 * @param array  $chunks      Chunk rows.
	 *
	 * @return array
	 */
	public function replace_chunks( $document_id, array $chunks ) {
		$document_id = sanitize_text_field( $document_id );

		if ( '' === $document_id ) {
			return $this->error_response( 'missing_document_id', 'Document ID is required to replace chunks.' );
		}

		$delete_response = $this->request(
			'DELETE',
			$this->get_rest_url( 'kb_chunks' ) . '?document_id=eq.' . rawurlencode( $document_id ),
			null,
			array(
				'Prefer' => 'return=minimal',
			)
		);

		if ( ! $delete_response['success'] ) {
			return $delete_response;
		}

		if ( empty( $chunks ) ) {
			return array(
				'success' => true,
				'data'    => array(),
			);
		}

		$rows = array();

		foreach ( $chunks as $chunk ) {
			if ( empty( $chunk['content'] ) || empty( $chunk['embedding'] ) || ! is_array( $chunk['embedding'] ) ) {
				return $this->error_response( 'invalid_chunk', 'Each chunk requires content and an embedding array.' );
			}

			$rows[] = array(
				'document_id' => $document_id,
				'chunk_index' => isset( $chunk['chunk_index'] ) ? absint( $chunk['chunk_index'] ) : count( $rows ),
				'content'     => sanitize_textarea_field( $chunk['content'] ),
				'token_count' => isset( $chunk['token_count'] ) ? absint( $chunk['token_count'] ) : 0,
				'embedding'   => $chunk['embedding'],
				'metadata'    => isset( $chunk['metadata'] ) && is_array( $chunk['metadata'] ) ? $chunk['metadata'] : array(),
			);
		}

		return $this->request(
			'POST',
			$this->get_rest_url( 'kb_chunks' ),
			$rows,
			array(
				'Prefer' => 'return=representation',
			)
		);
	}

	/**
	 * Search for similar knowledge base chunks.
	 *
	 * @param array $embedding Query embedding.
	 * @param int   $limit     Max results.
	 * @param float $threshold Similarity threshold.
	 * @param string|null $language Optional language filter.
	 *
	 * @return array
	 */
	public function similarity_search( array $embedding, $limit = 5, $threshold = 0.2, $language = null ) {
		if ( empty( $embedding ) ) {
			return $this->error_response( 'missing_embedding', 'Query embedding is required for similarity search.' );
		}

		$payload = array(
			'query_embedding' => $embedding,
			'match_count'     => absint( $limit ),
			'match_threshold' => (float) $threshold,
			'filter_language' => $language ? sanitize_text_field( $language ) : null,
		);

		$response = $this->request(
			'POST',
			$this->get_rest_url( 'rpc/match_kb_chunks' ),
			$payload
		);

		if ( ! $response['success'] ) {
			return $response;
		}

		return array(
			'success' => true,
			'data'    => is_array( $response['data'] ) ? $response['data'] : array(),
		);
	}

	/**
	 * Send a Supabase REST request.
	 *
	 * @param string     $method  HTTP method.
	 * @param string     $url     Request URL.
	 * @param array|null $body    JSON request body.
	 * @param array      $headers Extra headers.
	 *
	 * @return array
	 */
	private function request( $method, $url, $body = null, array $headers = array() ) {
		$config = $this->get_config();

		if ( ! $config['success'] ) {
			return $config;
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array_merge(
				array(
					'apikey'        => $config['data']['key'],
					'Authorization' => 'Bearer ' . $config['data']['key'],
					'Content-Type'  => 'application/json',
				),
				$headers
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		return $this->parse_response( $response );
	}

	/**
	 * Build a Supabase REST URL.
	 *
	 * @param string $path REST path.
	 *
	 * @return string
	 */
	private function get_rest_url( $path ) {
		$base_url = untrailingslashit( $this->settings->get( 'supabase_url', '' ) );

		return $base_url . '/rest/v1/' . ltrim( $path, '/' );
	}

	/**
	 * Get configured Supabase URL and key.
	 *
	 * @return array
	 */
	private function get_config() {
		$url = trim( (string) $this->settings->get( 'supabase_url', '' ) );
		$key = trim( (string) $this->settings->get( 'supabase_service_key', '' ) );

		if ( '' === $url ) {
			return $this->error_response( 'missing_supabase_url', 'Supabase URL is not configured.' );
		}

		if ( '' === $key ) {
			return $this->error_response( 'missing_supabase_key', 'Supabase Secret / Service Role key is not configured.' );
		}

		return array(
			'success' => true,
			'data'    => array(
				'url' => untrailingslashit( $url ),
				'key' => $key,
			),
		);
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
			return $this->error_response( 'supabase_http_error', $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = '' !== $body ? json_decode( $body, true ) : array();

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = 'Supabase request failed.';

			if ( isset( $data['message'] ) ) {
				$message = $data['message'];
			} elseif ( isset( $data['error'] ) ) {
				$message = is_string( $data['error'] ) ? $data['error'] : $message;
			}

			return $this->error_response( 'supabase_api_error', $message, $status_code );
		}

		if ( '' !== $body && ! is_array( $data ) ) {
			return $this->error_response( 'supabase_invalid_json', 'Supabase returned an invalid JSON response.', $status_code );
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
}
