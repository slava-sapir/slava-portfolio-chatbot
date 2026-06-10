<?php
/**
 * Admin page.
 *
 * @package Slava_Portfolio_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin admin screen.
 */
class SPC_Admin_Page {
	/**
	 * Settings manager.
	 *
	 * @var SPC_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SPC_Settings $settings Settings manager.
	 */
	public function __construct( SPC_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Portfolio Chatbot', 'slava-portfolio-chatbot' ),
			__( 'Portfolio Chatbot', 'slava-portfolio-chatbot' ),
			'manage_options',
			'slava-portfolio-chatbot',
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			58
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';

		if ( ! in_array( $active_tab, array( 'settings', 'leads', 'analytics' ), true ) ) {
			$active_tab = 'settings';
		}

		?>
		<div class="wrap spc-admin-page">
			<h1><?php esc_html_e( 'Portfolio Chatbot', 'slava-portfolio-chatbot' ); ?></h1>

			<nav class="nav-tab-wrapper spc-admin-tabs" aria-label="<?php esc_attr_e( 'Portfolio Chatbot sections', 'slava-portfolio-chatbot' ); ?>">
				<a class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=slava-portfolio-chatbot' ) ); ?>">
					<?php esc_html_e( 'Settings', 'slava-portfolio-chatbot' ); ?>
				</a>
				<a class="nav-tab <?php echo 'leads' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=slava-portfolio-chatbot&tab=leads' ) ); ?>">
					<?php esc_html_e( 'Leads', 'slava-portfolio-chatbot' ); ?>
				</a>
				<a class="nav-tab <?php echo 'analytics' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=slava-portfolio-chatbot&tab=analytics' ) ); ?>">
					<?php esc_html_e( 'Analytics', 'slava-portfolio-chatbot' ); ?>
				</a>
			</nav>

			<?php
			if ( 'leads' === $active_tab ) {
				$this->render_leads_tab();
			} elseif ( 'analytics' === $active_tab ) {
				$this->render_analytics_tab();
			} else {
				$this->render_settings_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the settings tab.
	 *
	 * @return void
	 */
	private function render_settings_tab() {
		$settings      = $this->settings->get_settings();
		$pages         = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);
		$selected_pages = array_map( 'absint', (array) $settings['source_page_ids'] );
		$masked_secret  = $this->settings->get_masked_secret();
		?>
		<p>
			<?php esc_html_e( 'Configure the AI, Supabase, approved content sources, and MVP behavior for the chatbot.', 'slava-portfolio-chatbot' ); ?>
		</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'spc_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="spc_chatbot_enabled"><?php esc_html_e( 'Enable chatbot', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<label>
								<input
									type="checkbox"
									id="spc_chatbot_enabled"
									name="spc_settings[chatbot_enabled]"
									value="1"
									<?php checked( '1', $settings['chatbot_enabled'] ); ?>
								/>
								<?php esc_html_e( 'Load chatbot assets on the frontend when the widget is implemented.', 'slava-portfolio-chatbot' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_openai_api_key"><?php esc_html_e( 'OpenAI API key', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="spc_openai_api_key"
								name="spc_settings[openai_api_key]"
								value="<?php echo esc_attr( '' !== $settings['openai_api_key'] ? $masked_secret : '' ); ?>"
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description"><?php esc_html_e( 'Stored server-side only. Leave unchanged to keep the saved key.', 'slava-portfolio-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_openai_chat_model"><?php esc_html_e( 'OpenAI chat model', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="spc_openai_chat_model"
								name="spc_settings[openai_chat_model]"
								value="<?php echo esc_attr( $settings['openai_chat_model'] ); ?>"
								class="regular-text"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_openai_embedding_model"><?php esc_html_e( 'OpenAI embedding model', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="spc_openai_embedding_model"
								name="spc_settings[openai_embedding_model]"
								value="<?php echo esc_attr( $settings['openai_embedding_model'] ); ?>"
								class="regular-text"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_supabase_url"><?php esc_html_e( 'Supabase URL', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="spc_supabase_url"
								name="spc_settings[supabase_url]"
								value="<?php echo esc_attr( $settings['supabase_url'] ); ?>"
								class="regular-text"
								placeholder="https://example.supabase.co"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_supabase_service_key"><?php esc_html_e( 'Supabase Secret / Service Role Key', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="spc_supabase_service_key"
								name="spc_settings[supabase_service_key]"
								value="<?php echo esc_attr( '' !== $settings['supabase_service_key'] ? $masked_secret : '' ); ?>"
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description"><?php esc_html_e( 'Use only from PHP backend code. Never expose this key to browser JavaScript.', 'slava-portfolio-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Knowledge base source pages', 'slava-portfolio-chatbot' ); ?></th>
						<td>
							<?php if ( empty( $pages ) ) : ?>
								<p><?php esc_html_e( 'No pages found.', 'slava-portfolio-chatbot' ); ?></p>
							<?php else : ?>
								<fieldset class="spc-page-list">
									<?php foreach ( $pages as $page ) : ?>
										<label>
											<input
												type="checkbox"
												name="spc_settings[source_page_ids][]"
												value="<?php echo esc_attr( $page->ID ); ?>"
												<?php checked( in_array( (int) $page->ID, $selected_pages, true ) ); ?>
											/>
											<?php echo esc_html( get_the_title( $page ) ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Only selected pages will be used later for knowledge base sync.', 'slava-portfolio-chatbot' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_default_language"><?php esc_html_e( 'Default language', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="spc_default_language"
								name="spc_settings[default_language]"
								value="<?php echo esc_attr( $settings['default_language'] ); ?>"
								class="small-text"
							/>
							<p class="description"><?php esc_html_e( 'Use a short code such as en, fr, or es.', 'slava-portfolio-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_system_prompt"><?php esc_html_e( 'System prompt', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<textarea
								id="spc_system_prompt"
								name="spc_settings[system_prompt]"
								rows="8"
								class="large-text code"
							><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_chat_log_retention_days"><?php esc_html_e( 'Chat log retention', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="spc_chat_log_retention_days"
								name="spc_settings[chat_log_retention_days]"
								value="<?php echo esc_attr( $settings['chat_log_retention_days'] ); ?>"
								min="1"
								max="365"
								class="small-text"
							/>
							<?php esc_html_e( 'days', 'slava-portfolio-chatbot' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_analytics_retention_days"><?php esc_html_e( 'Analytics retention', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="spc_analytics_retention_days"
								name="spc_settings[analytics_retention_days]"
								value="<?php echo esc_attr( $settings['analytics_retention_days'] ); ?>"
								min="1"
								max="365"
								class="small-text"
							/>
							<?php esc_html_e( 'days', 'slava-portfolio-chatbot' ); ?>
							<p class="description"><?php esc_html_e( 'Old lightweight analytics events are deleted by the daily cleanup job.', 'slava-portfolio-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_daily_openai_request_cap"><?php esc_html_e( 'Daily OpenAI request cap', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="spc_daily_openai_request_cap"
								name="spc_settings[daily_openai_request_cap]"
								value="<?php echo esc_attr( $settings['daily_openai_request_cap'] ); ?>"
								min="1"
								max="10000"
								class="small-text"
							/>
							<p class="description"><?php esc_html_e( 'Maximum chat requests that may call OpenAI per day. This protects your API budget on the live site.', 'slava-portfolio-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_allowed_link_domains"><?php esc_html_e( 'Allowed assistant link domains', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<textarea
								id="spc_allowed_link_domains"
								name="spc_settings[allowed_link_domains]"
								rows="4"
								class="regular-text code"
							><?php echo esc_textarea( $settings['allowed_link_domains'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One domain per line. Assistant URLs are only clickable when their domain is listed here.', 'slava-portfolio-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="spc_email_notifications"><?php esc_html_e( 'Lead email notifications', 'slava-portfolio-chatbot' ); ?></label>
						</th>
						<td>
							<label>
								<input
									type="checkbox"
									id="spc_email_notifications"
									name="spc_settings[email_notifications]"
									value="1"
									<?php checked( '1', $settings['email_notifications'] ); ?>
								/>
								<?php esc_html_e( 'Email the site admin when a lead is captured.', 'slava-portfolio-chatbot' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Knowledge Base', 'slava-portfolio-chatbot' ); ?></h2>
			<p><?php esc_html_e( 'Refresh approved source pages into Supabase after changing selected pages or AI settings.', 'slava-portfolio-chatbot' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="spc_refresh_kb" />
				<?php wp_nonce_field( 'spc_refresh_kb', 'spc_refresh_kb_nonce' ); ?>
				<?php submit_button( __( 'Refresh Knowledge Base', 'slava-portfolio-chatbot' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Embedded Page Q&A', 'slava-portfolio-chatbot' ); ?></h2>
			<p><?php esc_html_e( 'Place a page-specific AI Q&A block into approved source pages with this shortcode:', 'slava-portfolio-chatbot' ); ?></p>
			<p><code>[slava_portfolio_qa page_id="123" title="Ask About Slava" scope="site"]</code></p>
			<p class="description"><?php esc_html_e( 'Replace 123 with the WordPress page ID. Use scope="page" for that page only or scope="site" for the whole approved knowledge base with the current page prioritized.', 'slava-portfolio-chatbot' ); ?></p>
		<?php
	}

	/**
	 * Render the leads tab.
	 *
	 * @return void
	 */
	private function render_leads_tab() {
		global $wpdb;

		$table        = $wpdb->prefix . 'spc_leads';
		$lead_id      = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;
		$selected_lead = $lead_id ? $this->get_lead( $lead_id ) : null;
		$leads        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at, name, email, visitor_type, interest_area, source_page, conversation_id, status, contacted_at FROM {$table} ORDER BY created_at DESC LIMIT %d",
				100
			)
		);
		?>
		<h2><?php esc_html_e( 'Captured Leads', 'slava-portfolio-chatbot' ); ?></h2>
		<p><?php esc_html_e( 'Recent leads submitted from the chatbot.', 'slava-portfolio-chatbot' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spc-leads-actions">
			<input type="hidden" name="action" value="spc_export_leads_csv" />
			<?php wp_nonce_field( 'spc_export_leads_csv', 'spc_export_leads_csv_nonce' ); ?>
			<?php submit_button( __( 'Export CSV', 'slava-portfolio-chatbot' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php if ( $selected_lead ) : ?>
			<?php $this->render_lead_detail( $selected_lead ); ?>
		<?php endif; ?>

		<?php if ( empty( $leads ) ) : ?>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'No leads captured yet. Submit a test lead from the chat widget to verify the flow.', 'slava-portfolio-chatbot' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat fixed striped spc-leads-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Date', 'slava-portfolio-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Name', 'slava-portfolio-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Email', 'slava-portfolio-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Visitor Type', 'slava-portfolio-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Interest Area', 'slava-portfolio-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source Page', 'slava-portfolio-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Conversation ID', 'slava-portfolio-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'slava-portfolio-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'slava-portfolio-chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $leads as $lead ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->created_at ) ); ?></td>
							<td><?php echo esc_html( $lead->name ); ?></td>
							<td>
								<a href="mailto:<?php echo esc_attr( $lead->email ); ?>"><?php echo esc_html( $lead->email ); ?></a>
							</td>
							<td><?php echo esc_html( $lead->visitor_type ); ?></td>
							<td><?php echo esc_html( $lead->interest_area ); ?></td>
							<td>
								<?php if ( ! empty( $lead->source_page ) ) : ?>
									<a href="<?php echo esc_url( $lead->source_page ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( wp_parse_url( $lead->source_page, PHP_URL_PATH ) ? wp_parse_url( $lead->source_page, PHP_URL_PATH ) : $lead->source_page ); ?>
									</a>
								<?php else : ?>
									<?php esc_html_e( 'Not recorded', 'slava-portfolio-chatbot' ); ?>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $lead->conversation_id ); ?></code></td>
							<td>
								<?php echo esc_html( $lead->status ? ucfirst( $lead->status ) : 'New' ); ?>
								<?php if ( ! empty( $lead->contacted_at ) ) : ?>
									<br />
									<small><?php echo esc_html( mysql2date( get_option( 'date_format' ), $lead->contacted_at ) ); ?></small>
								<?php endif; ?>
							</td>
							<td class="spc-lead-row-actions">
								<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=slava-portfolio-chatbot&tab=leads&lead_id=' . absint( $lead->id ) ) ); ?>">
									<?php esc_html_e( 'View', 'slava-portfolio-chatbot' ); ?>
								</a>
								<?php if ( 'contacted' !== $lead->status ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="spc_mark_lead_contacted" />
										<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead->id ); ?>" />
										<?php wp_nonce_field( 'spc_mark_lead_contacted_' . absint( $lead->id ), 'spc_mark_lead_contacted_nonce' ); ?>
										<button type="submit" class="button button-small"><?php esc_html_e( 'Mark contacted', 'slava-portfolio-chatbot' ); ?></button>
									</form>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this lead permanently?', 'slava-portfolio-chatbot' ) ); ?>');">
									<input type="hidden" name="action" value="spc_delete_lead" />
									<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead->id ); ?>" />
									<?php wp_nonce_field( 'spc_delete_lead_' . absint( $lead->id ), 'spc_delete_lead_nonce' ); ?>
									<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'slava-portfolio-chatbot' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render analytics tab.
	 *
	 * @return void
	 */
	private function render_analytics_tab() {
		$analytics = new SPC_Analytics();
		$summary   = $analytics->get_summary();
		$interests = $analytics->get_top_interest_areas();
		$events    = $analytics->get_recent_events();
		?>
		<h2><?php esc_html_e( 'Chatbot Analytics', 'slava-portfolio-chatbot' ); ?></h2>
		<p><?php esc_html_e( 'Lightweight MVP analytics for chatbot usage, fallbacks, and lead conversion.', 'slava-portfolio-chatbot' ); ?></p>

		<div class="spc-analytics-cards">
			<div class="spc-analytics-card">
				<strong><?php echo esc_html( $summary['questions'] ); ?></strong>
				<span><?php esc_html_e( 'Questions Asked', 'slava-portfolio-chatbot' ); ?></span>
			</div>
			<div class="spc-analytics-card">
				<strong><?php echo esc_html( $summary['leads'] ); ?></strong>
				<span><?php esc_html_e( 'Leads Captured', 'slava-portfolio-chatbot' ); ?></span>
			</div>
			<div class="spc-analytics-card">
				<strong><?php echo esc_html( $summary['fallbacks'] ); ?></strong>
				<span><?php esc_html_e( 'Fallbacks', 'slava-portfolio-chatbot' ); ?></span>
			</div>
			<div class="spc-analytics-card">
				<strong><?php echo esc_html( $summary['fallback_rate'] ); ?>%</strong>
				<span><?php esc_html_e( 'Fallback Rate', 'slava-portfolio-chatbot' ); ?></span>
			</div>
			<div class="spc-analytics-card">
				<strong><?php echo esc_html( $summary['lead_rate'] ); ?>%</strong>
				<span><?php esc_html_e( 'Question-to-Lead Rate', 'slava-portfolio-chatbot' ); ?></span>
			</div>
		</div>

		<div class="spc-analytics-grid">
			<section>
				<h3><?php esc_html_e( 'Top Interest Areas', 'slava-portfolio-chatbot' ); ?></h3>
				<?php if ( empty( $interests ) ) : ?>
					<p><?php esc_html_e( 'No interest area data yet.', 'slava-portfolio-chatbot' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Interest Area', 'slava-portfolio-chatbot' ); ?></th>
								<th><?php esc_html_e( 'Events', 'slava-portfolio-chatbot' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $interests as $interest ) : ?>
								<tr>
									<td><?php echo esc_html( $interest->interest_area ); ?></td>
									<td><?php echo esc_html( $interest->total ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>

			<section>
				<h3><?php esc_html_e( 'Recent Events', 'slava-portfolio-chatbot' ); ?></h3>
				<?php if ( empty( $events ) ) : ?>
					<p><?php esc_html_e( 'No analytics events recorded yet.', 'slava-portfolio-chatbot' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'slava-portfolio-chatbot' ); ?></th>
								<th><?php esc_html_e( 'Event', 'slava-portfolio-chatbot' ); ?></th>
								<th><?php esc_html_e( 'Interest', 'slava-portfolio-chatbot' ); ?></th>
								<th><?php esc_html_e( 'Fallback', 'slava-portfolio-chatbot' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $events as $event ) : ?>
								<tr>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->created_at ) ); ?></td>
									<td><?php echo esc_html( $event->event_type ); ?></td>
									<td><?php echo esc_html( $event->interest_area ); ?></td>
									<td><?php echo $event->is_fallback ? esc_html__( 'Yes', 'slava-portfolio-chatbot' ) : esc_html__( 'No', 'slava-portfolio-chatbot' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Get one lead.
	 *
	 * @param int $lead_id Lead ID.
	 *
	 * @return object|null
	 */
	private function get_lead( $lead_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'spc_leads';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$lead_id
			)
		);
	}

	/**
	 * Render selected lead detail and related conversation.
	 *
	 * @param object $lead Lead row.
	 *
	 * @return void
	 */
	private function render_lead_detail( $lead ) {
		global $wpdb;

		$logs_table = $wpdb->prefix . 'spc_chat_logs';
		$messages   = array();

		if ( ! empty( $lead->conversation_id ) ) {
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT created_at, role, message_text FROM {$logs_table} WHERE conversation_id = %s ORDER BY created_at ASC LIMIT %d",
					$lead->conversation_id,
					50
				)
			);
		}
		?>
		<div class="spc-lead-detail">
			<h3><?php esc_html_e( 'Lead Detail', 'slava-portfolio-chatbot' ); ?></h3>
			<dl>
				<dt><?php esc_html_e( 'Name', 'slava-portfolio-chatbot' ); ?></dt>
				<dd><?php echo esc_html( $lead->name ); ?></dd>
				<dt><?php esc_html_e( 'Email', 'slava-portfolio-chatbot' ); ?></dt>
				<dd><a href="mailto:<?php echo esc_attr( $lead->email ); ?>"><?php echo esc_html( $lead->email ); ?></a></dd>
				<dt><?php esc_html_e( 'Company', 'slava-portfolio-chatbot' ); ?></dt>
				<dd><?php echo esc_html( $lead->company ); ?></dd>
				<dt><?php esc_html_e( 'Country', 'slava-portfolio-chatbot' ); ?></dt>
				<dd><?php echo esc_html( $lead->country ); ?></dd>
				<dt><?php esc_html_e( 'Message', 'slava-portfolio-chatbot' ); ?></dt>
				<dd><?php echo nl2br( esc_html( $lead->message ) ); ?></dd>
			</dl>

			<h4><?php esc_html_e( 'Conversation', 'slava-portfolio-chatbot' ); ?></h4>
			<?php if ( empty( $messages ) ) : ?>
				<p><?php esc_html_e( 'No conversation messages found for this lead.', 'slava-portfolio-chatbot' ); ?></p>
			<?php else : ?>
				<div class="spc-conversation-log">
					<?php foreach ( $messages as $message ) : ?>
						<div class="spc-conversation-log__item">
							<strong><?php echo esc_html( ucfirst( $message->role ) ); ?></strong>
							<span><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message->created_at ) ); ?></span>
							<p><?php echo nl2br( esc_html( $message->message_text ) ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle mark contacted action.
	 *
	 * @return void
	 */
	public function handle_mark_lead_contacted() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update leads.', 'slava-portfolio-chatbot' ) );
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		check_admin_referer( 'spc_mark_lead_contacted_' . $lead_id, 'spc_mark_lead_contacted_nonce' );

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'spc_leads',
			array(
				'status'       => 'contacted',
				'contacted_at' => current_time( 'mysql' ),
			),
			array( 'id' => $lead_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=slava-portfolio-chatbot&tab=leads' ) );
		exit;
	}

	/**
	 * Handle delete lead action.
	 *
	 * @return void
	 */
	public function handle_delete_lead() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete leads.', 'slava-portfolio-chatbot' ) );
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		check_admin_referer( 'spc_delete_lead_' . $lead_id, 'spc_delete_lead_nonce' );

		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'spc_leads',
			array( 'id' => $lead_id ),
			array( '%d' )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=slava-portfolio-chatbot&tab=leads' ) );
		exit;
	}

	/**
	 * Export leads as CSV.
	 *
	 * @return void
	 */
	public function handle_export_leads_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export leads.', 'slava-portfolio-chatbot' ) );
		}

		check_admin_referer( 'spc_export_leads_csv', 'spc_export_leads_csv_nonce' );

		global $wpdb;
		$leads = $wpdb->get_results( "SELECT created_at, name, email, company, country, visitor_type, interest_area, message, source_page, conversation_id, status, contacted_at FROM {$wpdb->prefix}spc_leads ORDER BY created_at DESC" );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=portfolio-chatbot-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Date', 'Name', 'Email', 'Company', 'Country', 'Visitor Type', 'Interest Area', 'Message', 'Source Page', 'Conversation ID', 'Status', 'Contacted At' ) );

		foreach ( $leads as $lead ) {
			fputcsv(
				$output,
				array(
					$lead->created_at,
					$lead->name,
					$lead->email,
					$lead->company,
					$lead->country,
					$lead->visitor_type,
					$lead->interest_area,
					$lead->message,
					$lead->source_page,
					$lead->conversation_id,
					$lead->status,
					$lead->contacted_at,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle the admin knowledge base refresh action.
	 *
	 * @return void
	 */
	public function handle_refresh_kb() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to refresh the knowledge base.', 'slava-portfolio-chatbot' ) );
		}

		check_admin_referer( 'spc_refresh_kb', 'spc_refresh_kb_nonce' );

		$sync   = new SPC_KB_Sync( $this->settings );
		$result = $sync->refresh();

		set_transient(
			'spc_kb_refresh_result_' . get_current_user_id(),
			$result,
			MINUTE_IN_SECONDS * 5
		);

		wp_safe_redirect( admin_url( 'admin.php?page=slava-portfolio-chatbot&spc_kb_refresh=1' ) );
		exit;
	}

	/**
	 * Render one-time admin notices after KB refresh.
	 *
	 * @return void
	 */
	public function render_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_GET['page'] ) || 'slava-portfolio-chatbot' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( empty( $_GET['spc_kb_refresh'] ) ) {
			return;
		}

		$transient_key = 'spc_kb_refresh_result_' . get_current_user_id();
		$result        = get_transient( $transient_key );

		if ( false === $result || ! is_array( $result ) ) {
			return;
		}

		delete_transient( $transient_key );

		$status       = isset( $result['status'] ) ? $result['status'] : 'success';
		$notice_class = 'success' === $status ? 'notice-success' : 'notice-warning';

		if ( 'error' === $status ) {
			$notice_class = 'notice-error';
		}

		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Knowledge base refresh complete.', 'slava-portfolio-chatbot' ); ?></strong>
				<?php
				printf(
					/* translators: 1: refreshed docs, 2: refreshed chunks, 3: skipped docs */
					esc_html__( 'Documents refreshed: %1$d. Chunks refreshed: %2$d. Documents skipped: %3$d.', 'slava-portfolio-chatbot' ),
					isset( $result['refreshed_documents_count'] ) ? absint( $result['refreshed_documents_count'] ) : 0,
					isset( $result['refreshed_chunks_count'] ) ? absint( $result['refreshed_chunks_count'] ) : 0,
					isset( $result['skipped_documents_count'] ) ? absint( $result['skipped_documents_count'] ) : 0
				);
				?>
			</p>
			<?php if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) : ?>
				<ul>
					<?php foreach ( $result['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
