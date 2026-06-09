<?php
/**
 * Lead form template.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<form class="spc-chatbot__lead-form" data-spc-lead-form>
	<div class="spc-chatbot__lead-grid">
		<label>
			<span><?php esc_html_e( 'Name', 'slava-portfolio-chatbot' ); ?></span>
			<input type="text" name="name" autocomplete="name" required />
		</label>

		<label>
			<span><?php esc_html_e( 'Email', 'slava-portfolio-chatbot' ); ?></span>
			<input type="email" name="email" autocomplete="email" required />
		</label>

		<label>
			<span><?php esc_html_e( 'Company', 'slava-portfolio-chatbot' ); ?></span>
			<input type="text" name="company" autocomplete="organization" />
		</label>

		<label>
			<span><?php esc_html_e( 'Country', 'slava-portfolio-chatbot' ); ?></span>
			<input type="text" name="country" autocomplete="country-name" />
		</label>
	</div>

	<label>
		<span><?php esc_html_e( 'Interest area', 'slava-portfolio-chatbot' ); ?></span>
		<select name="interest_area">
			<option value=""><?php esc_html_e( 'Select one', 'slava-portfolio-chatbot' ); ?></option>
			<option value="WordPress"><?php esc_html_e( 'WordPress', 'slava-portfolio-chatbot' ); ?></option>
			<option value="Frontend/React"><?php esc_html_e( 'Frontend/React', 'slava-portfolio-chatbot' ); ?></option>
			<option value="AI/Automation"><?php esc_html_e( 'AI/Automation', 'slava-portfolio-chatbot' ); ?></option>
			<option value="SEO/Web improvements"><?php esc_html_e( 'SEO/Web improvements', 'slava-portfolio-chatbot' ); ?></option>
		</select>
	</label>

	<label>
		<span><?php esc_html_e( 'Message', 'slava-portfolio-chatbot' ); ?></span>
		<textarea name="message" rows="3" maxlength="2000"></textarea>
	</label>

	<label class="spc-chatbot__consent">
		<input type="checkbox" name="consent_given" value="1" required />
		<span><?php esc_html_e( 'I agree that my details and message may be stored so Slava can follow up with me and improve this assistant.', 'slava-portfolio-chatbot' ); ?></span>
	</label>

	<button type="submit"><?php esc_html_e( 'Send details', 'slava-portfolio-chatbot' ); ?></button>
	<div class="spc-chatbot__lead-status" data-spc-lead-status aria-live="polite"></div>
</form>
