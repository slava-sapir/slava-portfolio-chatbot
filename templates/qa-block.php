<?php
/**
 * Embedded Q&A block template.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section class="spc-qa" data-spc-qa data-page-id="<?php echo esc_attr( $page_id ); ?>" data-scope="<?php echo esc_attr( $scope ); ?>">
	<div class="spc-qa__header">
		<div class="spc-qa__eyebrow"><?php esc_html_e( 'AI Q&A', 'slava-portfolio-chatbot' ); ?></div>
		<h2><?php echo esc_html( $title ); ?></h2>
		<p>
			<?php
			if ( 'site' === $scope ) {
				printf(
					/* translators: %s: page title */
					esc_html__( 'Ask questions from %s with answers grounded in the approved website knowledge base.', 'slava-portfolio-chatbot' ),
					esc_html( $page_title )
				);
			} else {
				printf(
					/* translators: %s: page title */
					esc_html__( 'Ask focused questions about %s using approved page content.', 'slava-portfolio-chatbot' ),
					esc_html( $page_title )
				);
			}
			?>
		</p>
	</div>

	<div class="spc-qa__privacy">
		<?php echo esc_html( $privacy->get_notice() ); ?>
	</div>

	<div class="spc-qa__suggestions" data-spc-qa-suggestions>
		<button type="button" data-spc-qa-question="<?php esc_attr_e( 'What should I know from this page?', 'slava-portfolio-chatbot' ); ?>">
			<?php esc_html_e( 'Summarize this page', 'slava-portfolio-chatbot' ); ?>
		</button>
		<button type="button" data-spc-qa-question="<?php esc_attr_e( 'What experience does Slava describe here?', 'slava-portfolio-chatbot' ); ?>">
			<?php esc_html_e( 'Experience', 'slava-portfolio-chatbot' ); ?>
		</button>
		<button type="button" data-spc-qa-question="<?php esc_attr_e( 'What technologies are mentioned on this page?', 'slava-portfolio-chatbot' ); ?>">
			<?php esc_html_e( 'Technologies', 'slava-portfolio-chatbot' ); ?>
		</button>
	</div>

	<div class="spc-qa__answer" data-spc-qa-answer aria-live="polite" hidden></div>
	<div class="spc-qa__sources" data-spc-qa-sources hidden></div>
	<div class="spc-qa__cta" data-spc-qa-cta hidden></div>

	<form class="spc-qa__form" data-spc-qa-form>
		<label class="screen-reader-text" for="spc-qa-question-<?php echo esc_attr( $page_id ); ?>">
			<?php esc_html_e( 'Ask a page question', 'slava-portfolio-chatbot' ); ?>
		</label>
		<input
			id="spc-qa-question-<?php echo esc_attr( $page_id ); ?>"
			type="text"
			name="message"
			maxlength="1000"
			placeholder="<?php esc_attr_e( 'Ask about this page...', 'slava-portfolio-chatbot' ); ?>"
			data-spc-qa-input
		/>
		<button type="submit"><?php esc_html_e( 'Ask', 'slava-portfolio-chatbot' ); ?></button>
	</form>
</section>
