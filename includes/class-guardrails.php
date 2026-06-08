<?php
/**
 * Guardrails helper.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Portfolio-specific AI guardrails.
 */
class SPC_Guardrails {
	/**
	 * Similarity threshold for normal factual questions.
	 */
	const DEFAULT_SIMILARITY_THRESHOLD = 0.22;

	/**
	 * Stricter threshold for sensitive business questions.
	 */
	const SENSITIVE_SIMILARITY_THRESHOLD = 0.32;

	/**
	 * Get portfolio assistant system prompt.
	 *
	 * @param string $configured_prompt Optional admin configured prompt.
	 *
	 * @return string
	 */
	public function get_system_prompt( $configured_prompt = '' ) {
		$base_prompt = trim( (string) $configured_prompt );

		if ( '' === $base_prompt ) {
			$base_prompt = 'You are Slava portfolio assistant. Help visitors understand Slava\'s skills, projects, services, and experience using only approved portfolio knowledge base content.';
		}

		return $base_prompt . "\n\nGuardrails:\n"
			. "- Use only the retrieved portfolio context for factual claims about Slava.\n"
			. "- Do not invent skills, project work, client names, employer names, metrics, outcomes, certifications, education, pricing, timelines, or availability.\n"
			. "- If the retrieved context does not clearly support an answer, say you do not have enough confirmed information.\n"
			. "- For pricing, project timelines, availability, hiring terms, or custom project fit, do not guess. Suggest contacting Slava directly.\n"
			. "- For private or personal details not present in the retrieved context, politely decline and offer a contact handoff if appropriate.\n"
			. "- You may summarize retrieved content, ask clarifying questions, recommend relevant source pages, and suggest contacting Slava.\n"
			. "- Keep the tone professional, friendly, concise, and helpful.";
	}

	/**
	 * Get a basic fallback response.
	 *
	 * @param string $reason Optional reason code.
	 *
	 * @return string
	 */
	public function get_fallback_response( $reason = '' ) {
		switch ( $reason ) {
			case 'business_specific':
				return __( 'I do not have confirmed pricing, timeline, or availability details in Slava\'s portfolio content. Please contact Slava directly with your project details so he can give an accurate answer.', 'slava-portfolio-chatbot' );

			case 'private_details':
				return __( 'I cannot answer private or unlisted details from the available portfolio content. The best next step is to contact Slava directly.', 'slava-portfolio-chatbot' );

			case 'weak_retrieval':
			default:
				return __( 'I do not have enough confirmed portfolio information to answer that accurately. Please contact Slava directly for details.', 'slava-portfolio-chatbot' );
		}
	}

	/**
	 * Check whether a question asks for business details that should not be guessed.
	 *
	 * @param string $message User message.
	 *
	 * @return bool
	 */
	public function is_business_specific_question( $message ) {
		return (bool) preg_match( '/\b(price|pricing|cost|rate|budget|quote|estimate|timeline|deadline|delivery|deliver|availability|available|start date|how long|when can|schedule)\b/i', (string) $message );
	}

	/**
	 * Check whether a question asks for private or unlisted details.
	 *
	 * @param string $message User message.
	 *
	 * @return bool
	 */
	public function is_private_or_unlisted_question( $message ) {
		return (bool) preg_match( '/\b(age|address|phone|salary|income|personal email|private|family|married|password|secret|client list|references)\b/i', (string) $message );
	}

	/**
	 * Determine whether retrieved chunks are strong enough.
	 *
	 * @param array  $chunks  Retrieved chunks.
	 * @param string $message User message.
	 *
	 * @return array
	 */
	public function evaluate_retrieval( array $chunks, $message ) {
		if ( empty( $chunks ) ) {
			return array(
				'is_weak' => true,
				'reason'  => 'weak_retrieval',
			);
		}

		if ( $this->is_private_or_unlisted_question( $message ) ) {
			return array(
				'is_weak' => true,
				'reason'  => 'private_details',
			);
		}

		$is_business_specific = $this->is_business_specific_question( $message );
		$threshold            = $is_business_specific ? self::SENSITIVE_SIMILARITY_THRESHOLD : self::DEFAULT_SIMILARITY_THRESHOLD;
		$top_similarity       = isset( $chunks[0]['similarity'] ) ? (float) $chunks[0]['similarity'] : 0.0;
		$total_content_length = 0;

		foreach ( $chunks as $chunk ) {
			$total_content_length += isset( $chunk['content'] ) ? strlen( trim( $chunk['content'] ) ) : 0;
		}

		if ( $top_similarity < $threshold || $total_content_length < 120 ) {
			return array(
				'is_weak' => true,
				'reason'  => $is_business_specific ? 'business_specific' : 'weak_retrieval',
			);
		}

		return array(
			'is_weak' => false,
			'reason'  => '',
		);
	}
}
