<?php
/**
 * Chat widget template.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="spc-chatbot" data-spc-chatbot>
	<button class="spc-chatbot__launcher" type="button" aria-label="<?php esc_attr_e( 'Open portfolio assistant', 'slava-portfolio-chatbot' ); ?>" aria-expanded="false">
		<svg class="spc-chatbot__launcher-icon" aria-hidden="true" viewBox="0 0 24 24" focusable="false">
			<path d="M12 3.5c-5 0-9 3.3-9 7.4 0 2.4 1.4 4.6 3.7 5.9l-.7 3c-.1.4.3.7.7.5l3.7-2.2c.5.1 1.1.1 1.6.1 5 0 9-3.3 9-7.4s-4-7.3-9-7.3Zm-4 8.2c-.7 0-1.2-.5-1.2-1.1S7.3 9.5 8 9.5s1.2.5 1.2 1.1-.5 1.1-1.2 1.1Zm4 0c-.7 0-1.2-.5-1.2-1.1s.5-1.1 1.2-1.1 1.2.5 1.2 1.1-.5 1.1-1.2 1.1Zm4 0c-.7 0-1.2-.5-1.2-1.1s.5-1.1 1.2-1.1 1.2.5 1.2 1.1-.5 1.1-1.2 1.1Z" />
		</svg>
	</button>

	<section class="spc-chatbot__panel" aria-label="<?php esc_attr_e( 'Portfolio assistant chat', 'slava-portfolio-chatbot' ); ?>" hidden>
		<header class="spc-chatbot__header">
			<div>
				<h2><?php esc_html_e( 'Portfolio Assistant', 'slava-portfolio-chatbot' ); ?></h2>
				<p><?php esc_html_e( 'Ask about Slava\'s skills, projects, and services.', 'slava-portfolio-chatbot' ); ?></p>
			</div>
			<button class="spc-chatbot__close" type="button" aria-label="<?php esc_attr_e( 'Close chat', 'slava-portfolio-chatbot' ); ?>">&times;</button>
		</header>

		<div class="spc-chatbot__privacy">
			<?php esc_html_e( 'Messages may be stored for follow-up, assistant improvement, and abuse prevention.', 'slava-portfolio-chatbot' ); ?>
		</div>

		<div class="spc-chatbot__survey" data-spc-survey hidden>
			<form data-spc-survey-form>
				<div class="spc-chatbot__survey-fields">
					<label>
						<span><?php esc_html_e( 'Language', 'slava-portfolio-chatbot' ); ?></span>
						<select name="language">
							<option value="en"><?php esc_html_e( 'English', 'slava-portfolio-chatbot' ); ?></option>
							<option value="fr"><?php esc_html_e( 'French', 'slava-portfolio-chatbot' ); ?></option>
							<option value="es"><?php esc_html_e( 'Spanish', 'slava-portfolio-chatbot' ); ?></option>
							<option value="uk"><?php esc_html_e( 'Ukrainian', 'slava-portfolio-chatbot' ); ?></option>
						</select>
					</label>

					<label>
						<span><?php esc_html_e( 'I am a', 'slava-portfolio-chatbot' ); ?></span>
						<select name="visitor_type">
							<option value=""><?php esc_html_e( 'Select', 'slava-portfolio-chatbot' ); ?></option>
							<option value="recruiter"><?php esc_html_e( 'Recruiter', 'slava-portfolio-chatbot' ); ?></option>
							<option value="client"><?php esc_html_e( 'Client', 'slava-portfolio-chatbot' ); ?></option>
							<option value="hiring manager"><?php esc_html_e( 'Hiring manager', 'slava-portfolio-chatbot' ); ?></option>
							<option value="collaborator"><?php esc_html_e( 'Collaborator', 'slava-portfolio-chatbot' ); ?></option>
						</select>
					</label>

					<label>
						<span><?php esc_html_e( 'Interested in', 'slava-portfolio-chatbot' ); ?></span>
						<select name="interest_area">
							<option value=""><?php esc_html_e( 'Select', 'slava-portfolio-chatbot' ); ?></option>
							<option value="WordPress"><?php esc_html_e( 'WordPress', 'slava-portfolio-chatbot' ); ?></option>
							<option value="frontend/React"><?php esc_html_e( 'Frontend/React', 'slava-portfolio-chatbot' ); ?></option>
							<option value="AI/automation"><?php esc_html_e( 'AI/automation', 'slava-portfolio-chatbot' ); ?></option>
							<option value="SEO/web improvements"><?php esc_html_e( 'SEO/web improvements', 'slava-portfolio-chatbot' ); ?></option>
						</select>
					</label>
				</div>

				<div class="spc-chatbot__survey-actions">
					<button type="submit"><?php esc_html_e( 'Apply', 'slava-portfolio-chatbot' ); ?></button>
					<button type="button" data-spc-survey-skip><?php esc_html_e( 'Skip', 'slava-portfolio-chatbot' ); ?></button>
				</div>
			</form>
		</div>

		<div class="spc-chatbot__messages" data-spc-messages aria-live="polite">
			<div class="spc-chatbot__message spc-chatbot__message--assistant">
				<?php esc_html_e( 'Hi, I can help you explore Slava\'s portfolio. What would you like to know?', 'slava-portfolio-chatbot' ); ?>
			</div>
		</div>

		<div class="spc-chatbot__quick-replies" data-spc-quick-replies>
			<button type="button" data-spc-quick-reply="What can Slava help with?"><?php esc_html_e( 'What can Slava help with?', 'slava-portfolio-chatbot' ); ?></button>
			<button type="button" data-spc-quick-reply="Show relevant projects"><?php esc_html_e( 'Show relevant projects', 'slava-portfolio-chatbot' ); ?></button>
		</div>

		<div class="spc-chatbot__links" data-spc-links hidden></div>

		<div class="spc-chatbot__lead" data-spc-lead hidden>
			<p><?php esc_html_e( 'Interested in working with Slava? Send your details and he can follow up directly.', 'slava-portfolio-chatbot' ); ?></p>
			<?php include SPC_PLUGIN_DIR . 'templates/lead-form.php'; ?>
		</div>

		<form class="spc-chatbot__form" data-spc-form>
			<label class="screen-reader-text" for="spc-chatbot-message"><?php esc_html_e( 'Message', 'slava-portfolio-chatbot' ); ?></label>
			<textarea id="spc-chatbot-message" data-spc-input rows="1" maxlength="2000" placeholder="<?php esc_attr_e( 'Ask about skills, projects, or services...', 'slava-portfolio-chatbot' ); ?>"></textarea>
			<button type="submit"><?php esc_html_e( 'Send', 'slava-portfolio-chatbot' ); ?></button>
		</form>
	</section>
</div>
