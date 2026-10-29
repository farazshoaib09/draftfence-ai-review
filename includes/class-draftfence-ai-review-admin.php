<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DraftFence_AI_Review_Admin {
	public static function register_menu() {
		add_menu_page(
			__( 'DraftFence AI Review', 'draftfence-ai-review' ),
			__( 'DraftFence AI Review', 'draftfence-ai-review' ),
			'draftfence_ai_review_review_proposals',
			'draftfence-ai-review',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page(
			'draftfence-ai-review',
			__( 'Pending Proposals', 'draftfence-ai-review' ),
			__( 'Pending Proposals', 'draftfence-ai-review' ),
			'draftfence_ai_review_review_proposals',
			'draftfence-ai-review-proposals',
			array( __CLASS__, 'render_proposals' )
		);

		add_submenu_page(
			'draftfence-ai-review',
			__( 'Onboarding', 'draftfence-ai-review' ),
			__( 'Onboarding', 'draftfence-ai-review' ),
			'draftfence_ai_review_manage_settings',
			'draftfence-ai-review-onboarding',
			array( __CLASS__, 'render_onboarding' )
		);

		add_submenu_page(
			'draftfence-ai-review',
			__( 'Connect & Test', 'draftfence-ai-review' ),
			__( 'Connect & Test', 'draftfence-ai-review' ),
			'draftfence_ai_review_manage_settings',
			'draftfence-ai-review-connect',
			array( __CLASS__, 'render_connect' )
		);

		add_submenu_page(
			'draftfence-ai-review',
			__( 'AI Identities', 'draftfence-ai-review' ),
			__( 'AI Identities', 'draftfence-ai-review' ),
			'draftfence_ai_review_manage_settings',
			'draftfence-ai-review-identities',
			array( __CLASS__, 'render_identities' )
		);

		add_submenu_page(
			'draftfence-ai-review',
			__( 'Audit Logs', 'draftfence-ai-review' ),
			__( 'Audit Logs', 'draftfence-ai-review' ),
			'draftfence_ai_review_view_logs',
			'draftfence-ai-review-logs',
			array( __CLASS__, 'render_logs' )
		);

		add_submenu_page(
			'draftfence-ai-review',
			__( 'Settings', 'draftfence-ai-review' ),
			__( 'Settings', 'draftfence-ai-review' ),
			'draftfence_ai_review_manage_settings',
			'draftfence-ai-review-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'draftfence-ai-review' ) ) {
			return;
		}

		wp_enqueue_style( 'draftfence-ai-review-admin', DRAFTFENCE_AI_REVIEW_URL . 'assets/admin.css', array(), DRAFTFENCE_AI_REVIEW_VERSION );
		wp_enqueue_script( 'draftfence-ai-review-admin', DRAFTFENCE_AI_REVIEW_URL . 'assets/admin.js', array(), DRAFTFENCE_AI_REVIEW_VERSION, true );
	}

	public static function handle_actions() {
		if ( isset( $_POST['draftfence-ai-review_save_settings'] ) ) {
			if ( ! DraftFence_AI_Review::current_user_can_manage() ) {
				return;
			}

			check_admin_referer( 'draftfence-ai-review_save_settings' );

			DraftFence_AI_Review::update_settings(
				array(
					'enabled'                    => isset( $_POST['enabled'] ),
					'allow_read_content'         => isset( $_POST['allow_read_content'] ),
					'allow_create_drafts'        => isset( $_POST['allow_create_drafts'] ),
					'allow_propose_edits'        => isset( $_POST['allow_propose_edits'] ),
					'allow_seo_fields'           => isset( $_POST['allow_seo_fields'] ),
					'allowed_post_types'         => isset( $_POST['allowed_post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['allowed_post_types'] ) ) : array(),
					'max_list_results'           => isset( $_POST['max_list_results'] ) ? absint( $_POST['max_list_results'] ) : 20,
					'rate_limit_per_hour'        => isset( $_POST['rate_limit_per_hour'] ) ? absint( $_POST['rate_limit_per_hour'] ) : 60,
					'audit_retention_days'       => isset( $_POST['audit_retention_days'] ) ? absint( $_POST['audit_retention_days'] ) : 90,
					'notify_emails'              => isset( $_POST['notify_emails'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_emails'] ) ) : '',
				)
			);

			wp_safe_redirect( add_query_arg( array( 'page' => 'draftfence-ai-review-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_POST['draftfence-ai-review_save_identities'] ) ) {
			if ( ! DraftFence_AI_Review::current_user_can_manage() ) {
				return;
			}

			check_admin_referer( 'draftfence-ai-review_save_identities' );
			self::save_identities();

			wp_safe_redirect( add_query_arg( array( 'page' => 'draftfence-ai-review-identities', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_POST['draftfence-ai-review_finish_onboarding'] ) ) {
			if ( ! DraftFence_AI_Review::current_user_can_manage() ) {
				return;
			}

			check_admin_referer( 'draftfence-ai-review_finish_onboarding' );
			self::save_onboarding();

			wp_safe_redirect( add_query_arg( array( 'page' => 'draftfence-ai-review', 'onboarding' => 'complete' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_POST['draftfence-ai-review_proposal_action'], $_POST['proposal_id'] ) ) {
			if ( ! DraftFence_AI_Review::current_user_can_review() ) {
				return;
			}

			check_admin_referer( 'draftfence-ai-review_proposal_action' );

			$proposal_id = absint( $_POST['proposal_id'] );
			$notes       = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
			$action      = sanitize_key( wp_unslash( $_POST['draftfence-ai-review_proposal_action'] ) );

			if ( 'approve' === $action ) {
				DraftFence_AI_Review_Actions::approve_proposal( $proposal_id, $notes );
			} elseif ( 'reject' === $action ) {
				DraftFence_AI_Review_Actions::reject_proposal( $proposal_id, $notes );
			} elseif ( 'rollback' === $action ) {
				DraftFence_AI_Review_Actions::rollback_proposal( $proposal_id, $notes );
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'draftfence-ai-review-proposals', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_GET['page'], $_GET['draftfence-ai-review_export_logs'] ) && 'draftfence-ai-review-logs' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			if ( ! DraftFence_AI_Review::current_user_can_view_logs() ) {
				return;
			}

			check_admin_referer( 'draftfence-ai-review_export_logs' );
			self::export_logs_csv();
		}
	}

	public static function render_dashboard() {
		$status    = DraftFence_AI_Review_Actions::status();
		$proposals = DraftFence_AI_Review_Actions::list_proposals( array( 'status' => 'pending', 'limit' => 5 ) );
		$logs      = DraftFence_AI_Review_Audit_Log::recent( 5 );
		$settings  = DraftFence_AI_Review::get_settings();

		?>
		<div class="wrap draftfence-ai-review-wrap">
			<h1 class="wp-heading-inline draftfence-ai-review-page-title"><?php esc_html_e( 'DraftFence AI Review', 'draftfence-ai-review' ); ?></h1>
			<?php self::render_header( __( 'DraftFence AI Review', 'draftfence-ai-review' ), __( 'Govern AI access with approvals, scoped identities, rollback, and a clean audit trail.', 'draftfence-ai-review' ), $status ); ?>

			<div class="draftfence-ai-review-cards">
				<div class="draftfence-ai-review-card">
					<h2><span class="dashicons dashicons-shield-alt"></span><?php esc_html_e( 'Protection', 'draftfence-ai-review' ); ?></h2>
					<p class="draftfence-ai-review-status <?php echo $status['enabled'] ? 'is-on' : 'is-off'; ?>">
						<?php echo $status['enabled'] ? esc_html__( 'Enabled', 'draftfence-ai-review' ) : esc_html__( 'Disabled', 'draftfence-ai-review' ); ?>
					</p>
				</div>
				<div class="draftfence-ai-review-card">
					<h2><span class="dashicons dashicons-category"></span><?php esc_html_e( 'Allowed Areas', 'draftfence-ai-review' ); ?></h2>
					<p><?php echo esc_html( implode( ', ', $status['allowed_post_types'] ) ); ?></p>
				</div>
				<div class="draftfence-ai-review-card">
					<h2><span class="dashicons dashicons-clipboard"></span><?php esc_html_e( 'Pending Proposals', 'draftfence-ai-review' ); ?></h2>
					<p class="draftfence-ai-review-big-number"><?php echo esc_html( is_wp_error( $proposals ) ? 0 : $proposals['count'] ); ?></p>
				</div>
				<div class="draftfence-ai-review-card">
					<h2><span class="dashicons dashicons-rest-api"></span><?php esc_html_e( 'Abilities API', 'draftfence-ai-review' ); ?></h2>
					<p><?php echo $status['wordpress_abilities'] ? esc_html__( 'Available', 'draftfence-ai-review' ) : esc_html__( 'Not detected', 'draftfence-ai-review' ); ?></p>
				</div>
			</div>

			<?php if ( empty( $settings['onboarding_complete'] ) ) : ?>
				<div class="draftfence-ai-review-onboarding-callout">
					<div>
						<strong><?php esc_html_e( 'Finish your AI safety setup', 'draftfence-ai-review' ); ?></strong>
						<p><?php esc_html_e( 'Create a scoped AI identity, choose safe actions, configure notifications, and run a readiness check.', 'draftfence-ai-review' ); ?></p>
					</div>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=draftfence-ai-review-onboarding' ) ); ?>"><?php esc_html_e( 'Start Onboarding', 'draftfence-ai-review' ); ?></a>
				</div>
			<?php endif; ?>

			<div class="draftfence-ai-review-checklist">
				<h2><?php esc_html_e( 'Launch Checklist', 'draftfence-ai-review' ); ?></h2>
				<ul>
					<li class="<?php echo $status['enabled'] ? 'is-done' : ''; ?>"><?php esc_html_e( 'Protection switch is enabled', 'draftfence-ai-review' ); ?></li>
					<li class="<?php echo ! empty( $settings['agent_profiles'] ) ? 'is-done' : ''; ?>"><?php esc_html_e( 'At least one AI identity profile is configured', 'draftfence-ai-review' ); ?></li>
					<li class="<?php echo ! empty( $settings['notify_emails'] ) ? 'is-done' : ''; ?>"><?php esc_html_e( 'Review notification email is configured', 'draftfence-ai-review' ); ?></li>
					<li class="is-done"><?php esc_html_e( 'Existing content edits require human approval', 'draftfence-ai-review' ); ?></li>
				</ul>
			</div>

			<div class="draftfence-ai-review-panel">
				<div class="draftfence-ai-review-panel-header">
					<h2><?php esc_html_e( 'Recent Activity', 'draftfence-ai-review' ); ?></h2>
				</div>
				<?php self::render_logs_table( $logs ); ?>
			</div>
		</div>
		<?php
	}

	public static function render_settings() {
		$settings   = DraftFence_AI_Review::get_settings();
		$post_types = DraftFence_AI_Review::get_supported_post_types();

		?>
		<div class="wrap draftfence-ai-review-wrap">
			<h1 class="wp-heading-inline draftfence-ai-review-page-title"><?php esc_html_e( 'DraftFence AI Review Settings', 'draftfence-ai-review' ); ?></h1>
			<?php self::render_header( __( 'Settings', 'draftfence-ai-review' ), __( 'Define the global policy every AI identity must obey.', 'draftfence-ai-review' ) ); ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag after a nonce-protected redirect. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'draftfence-ai-review' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="" class="draftfence-ai-review-panel">
				<?php wp_nonce_field( 'draftfence-ai-review_save_settings' ); ?>
				<div class="draftfence-ai-review-panel-header">
					<h2><?php esc_html_e( 'Global Policy', 'draftfence-ai-review' ); ?></h2>
					<p><?php esc_html_e( 'Keep defaults conservative, then open access deliberately.', 'draftfence-ai-review' ); ?></p>
				</div>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Emergency switch', 'draftfence-ai-review' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?> />
								<?php esc_html_e( 'Allow DraftFence AI Review AI access', 'draftfence-ai-review' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Turn this off to immediately block DraftFence AI Review REST and Ability operations.', 'draftfence-ai-review' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allowed actions', 'draftfence-ai-review' ); ?></th>
						<td>
							<label><input type="checkbox" name="allow_read_content" value="1" <?php checked( $settings['allow_read_content'] ); ?> /> <?php esc_html_e( 'Read/list content summaries', 'draftfence-ai-review' ); ?></label><br />
							<label><input type="checkbox" name="allow_create_drafts" value="1" <?php checked( $settings['allow_create_drafts'] ); ?> /> <?php esc_html_e( 'Create drafts only', 'draftfence-ai-review' ); ?></label><br />
							<label><input type="checkbox" name="allow_propose_edits" value="1" <?php checked( $settings['allow_propose_edits'] ); ?> /> <?php esc_html_e( 'Create pending edit proposals', 'draftfence-ai-review' ); ?></label><br />
							<label><input type="checkbox" name="allow_seo_fields" value="1" <?php checked( $settings['allow_seo_fields'] ); ?> /> <?php esc_html_e( 'Allow SEO title/description fields in drafts and proposals', 'draftfence-ai-review' ); ?></label>
							<p class="description"><?php esc_html_e( 'Existing content edits are always staged as proposals for administrator approval.', 'draftfence-ai-review' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allowed content types', 'draftfence-ai-review' ); ?></th>
						<td>
							<?php foreach ( $post_types as $name => $label ) : ?>
								<label class="draftfence-ai-review-checkbox">
									<input type="checkbox" name="allowed_post_types[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $settings['allowed_post_types'], true ) ); ?> />
									<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $name ); ?></code>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="max_list_results"><?php esc_html_e( 'Max list results', 'draftfence-ai-review' ); ?></label></th>
						<td>
							<input type="number" min="1" max="100" id="max_list_results" name="max_list_results" value="<?php echo esc_attr( $settings['max_list_results'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rate_limit_per_hour"><?php esc_html_e( 'AI rate limit', 'draftfence-ai-review' ); ?></label></th>
						<td>
							<input type="number" min="1" max="500" id="rate_limit_per_hour" name="rate_limit_per_hour" value="<?php echo esc_attr( $settings['rate_limit_per_hour'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Maximum allowed DraftFence AI Review actions per authenticated user each hour.', 'draftfence-ai-review' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="audit_retention_days"><?php esc_html_e( 'Audit retention', 'draftfence-ai-review' ); ?></label></th>
						<td>
							<input type="number" min="7" max="3650" id="audit_retention_days" name="audit_retention_days" value="<?php echo esc_attr( $settings['audit_retention_days'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Days to keep DraftFence AI Review audit events before daily pruning.', 'draftfence-ai-review' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="notify_emails"><?php esc_html_e( 'Review notifications', 'draftfence-ai-review' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="notify_emails" name="notify_emails" value="<?php echo esc_attr( $settings['notify_emails'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated email addresses notified when an AI identity submits a proposal.', 'draftfence-ai-review' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="draftfence-ai-review_save_settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'draftfence-ai-review' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	public static function render_connect() {
		$endpoint       = rest_url( 'mcp/mcp-adapter-default-server' );
		$status_url     = rest_url( 'draftfence-ai-review/v1/status' );
		$diagnostics    = self::get_connection_diagnostics();
		$sample_user    = wp_get_current_user();
		$sample_login   = $sample_user && $sample_user->exists() ? $sample_user->user_login : 'admin';
		$draftfence_ai_review_abilities = self::get_draftfence_ai_review_ability_names();
		$ability_names  = self::get_exposed_ability_names();
		$non_draftfence_ai_review_abilities = array_values(
			array_filter(
				$ability_names,
				static function ( $ability_name ) {
					return 0 !== strpos( $ability_name, 'draftfence-ai-review/' ) && 0 !== strpos( $ability_name, 'mcp-adapter/' );
				}
			)
		);
		$high_risk_abilities = array_values(
			array_filter(
				$non_draftfence_ai_review_abilities,
				static function ( $ability_name ) {
					return (bool) preg_match( '/(create|update|delete|remove|publish|upload|moderate|replace|assign|approve|install|activate|edit|write|set-|add-)/i', $ability_name );
				}
			)
		);
		$gemini_config  = self::pretty_json(
			array(
				'mcpServers' => array(
					'wordpress' => array(
						'httpUrl' => $endpoint,
						'headers' => array(
							'Authorization' => 'Basic YOUR_BASE64_CREDENTIAL',
						),
						'timeout' => 30000,
						'trust'   => true,
						'oauth'   => array(
							'enabled' => false,
						),
					),
				),
			)
		);
		$claude_config  = self::pretty_json(
			array(
				'mcpServers' => array(
					'wordpress' => array(
						'command' => 'npx',
						'args'    => array(
							'-y',
							'mcp-remote',
							$endpoint,
							'--header',
							'Authorization: Basic YOUR_BASE64_CREDENTIAL',
						),
					),
				),
			)
		);
		$manual_links   = array(
			'codex'       => 'https://developers.openai.com/codex/',
			'claude'      => 'https://support.claude.com/en/articles/10949351-getting-started-with-local-mcp-servers-on-claude-desktop',
			'gemini'      => 'https://github.com/google-gemini/gemini-cli/blob/main/docs/tools/mcp-server.md',
			'wp_adapter'  => 'https://wordpress.org/plugins/mcp-adapter/',
			'app_passwords' => 'https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/',
		);
		$support_report = self::build_connection_support_report(
			array(
				'endpoint'                   => $endpoint,
				'status_url'                 => $status_url,
				'user_login'                 => $sample_login,
				'ability_count'              => count( $ability_names ),
				'draftfence_ai_review_ability_count'   => count( array_intersect( $draftfence_ai_review_abilities, $ability_names ) ),
				'non_draftfence_ai_review_count'       => count( $non_draftfence_ai_review_abilities ),
				'high_risk_ability_count'    => count( $high_risk_abilities ),
				'diagnostics'                => $diagnostics,
			)
		);

		wp_localize_script(
			'draftfence-ai-review-admin',
			'draftFenceAiReviewConnect',
			array(
				'configs' => array(
					'draftfence-ai-review-config-gemini' => $gemini_config,
					'draftfence-ai-review-config-claude' => $claude_config,
				),
				'strings' => array(
					'missingCredentials' => __( 'Enter both the WordPress username and Application Password first.', 'draftfence-ai-review' ),
					'credentialGenerated' => __( 'Credential generated locally. Copy the updated config below.', 'draftfence-ai-review' ),
					'credentialError'     => __( 'Your browser could not generate the Base64 credential. Use the manual format username:application_password and encode it with a local terminal command.', 'draftfence-ai-review' ),
					'copied'              => __( 'Copied', 'draftfence-ai-review' ),
					'copyPrompt'          => __( 'Copy this value:', 'draftfence-ai-review' ),
				),
			)
		);

		?>
		<div class="wrap draftfence-ai-review-wrap">
			<h1 class="wp-heading-inline draftfence-ai-review-page-title"><?php esc_html_e( 'Connect & Test AI Client', 'draftfence-ai-review' ); ?></h1>
			<?php self::render_header( __( 'Connect & Test AI Client', 'draftfence-ai-review' ), __( 'Confirm DraftFence AI Review is ready, copy safe connection details, and use official client manuals for MCP setup.', 'draftfence-ai-review' ) ); ?>

			<div class="draftfence-ai-review-connect-hero">
				<div>
					<span class="draftfence-ai-review-eyebrow"><?php esc_html_e( 'Connection hub', 'draftfence-ai-review' ); ?></span>
					<h2><?php esc_html_e( 'DraftFence AI Review handles safety. Your AI client handles the MCP connection.', 'draftfence-ai-review' ); ?></h2>
					<p><?php esc_html_e( 'Use this page to confirm DraftFence AI Review is ready, copy safe connection details, and open the right client manual. DraftFence AI Review does not try to configure every hosting platform or AI app for you.', 'draftfence-ai-review' ); ?></p>
				</div>
				<div class="draftfence-ai-review-connect-hero-card">
					<span><?php esc_html_e( 'MCP endpoint', 'draftfence-ai-review' ); ?></span>
					<code id="draftfence-ai-review-mcp-endpoint"><?php echo esc_html( $endpoint ); ?></code>
					<button type="button" class="button button-primary draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-mcp-endpoint"><?php esc_html_e( 'Copy Endpoint', 'draftfence-ai-review' ); ?></button>
				</div>
			</div>

			<div class="draftfence-ai-review-warning-panel">
				<strong><?php esc_html_e( 'Product boundary:', 'draftfence-ai-review' ); ?></strong>
				<?php esc_html_e( 'DraftFence AI Review governs AI access inside WordPress. For external MCP client setup, use the official client manual or an existing connector/extension where available.', 'draftfence-ai-review' ); ?>
			</div>

			<div class="draftfence-ai-review-panel">
				<div class="draftfence-ai-review-panel-header">
					<h2><?php esc_html_e( 'Connection Diagnostics', 'draftfence-ai-review' ); ?></h2>
					<p><?php esc_html_e( 'If these checks fail, no AI client will connect reliably. Fix the red rows first.', 'draftfence-ai-review' ); ?></p>
				</div>
				<table class="widefat striped draftfence-ai-review-diagnostics-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Check', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'Status', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'What it means', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'Action', 'draftfence-ai-review' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $diagnostics as $row ) : ?>
							<?php self::render_diagnostic_row( $row ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="draftfence-ai-review-panel">
				<div class="draftfence-ai-review-panel-header">
					<h2><?php esc_html_e( 'Connection details', 'draftfence-ai-review' ); ?></h2>
					<p><?php esc_html_e( 'Most HTTP MCP clients need the endpoint plus a WordPress Application Password. Credentials are generated locally in your browser and are not saved by DraftFence AI Review.', 'draftfence-ai-review' ); ?></p>
				</div>
				<div class="draftfence-ai-review-connect-grid">
					<label>
						<span><?php esc_html_e( 'WordPress username', 'draftfence-ai-review' ); ?></span>
						<input type="text" id="draftfence-ai-review-connect-username" value="<?php echo esc_attr( $sample_login ); ?>" autocomplete="username" />
					</label>
					<label>
						<span><?php esc_html_e( 'Application Password', 'draftfence-ai-review' ); ?></span>
						<input type="password" id="draftfence-ai-review-connect-password" placeholder="<?php esc_attr_e( 'xxxx xxxx xxxx xxxx xxxx xxxx', 'draftfence-ai-review' ); ?>" autocomplete="off" />
					</label>
				</div>
				<p>
					<button type="button" class="button button-primary" id="draftfence-ai-review-generate-credential"><?php esc_html_e( 'Generate Credential Locally', 'draftfence-ai-review' ); ?></button>
					<button type="button" class="button" id="draftfence-ai-review-toggle-password"><?php esc_html_e( 'Show/Hide Password', 'draftfence-ai-review' ); ?></button>
				</p>
				<div class="draftfence-ai-review-js-message" id="draftfence-ai-review-connect-message" aria-live="polite"></div>
				<label class="draftfence-ai-review-wide-field">
					<span><?php esc_html_e( 'Generated Authorization header', 'draftfence-ai-review' ); ?></span>
					<input type="text" id="draftfence-ai-review-generated-header" value="Authorization: Basic YOUR_BASE64_CREDENTIAL" readonly />
				</label>
				<div class="draftfence-ai-review-connect-actions">
					<button type="button" class="button draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-generated-header"><?php esc_html_e( 'Copy Header', 'draftfence-ai-review' ); ?></button>
					<a class="button" href="<?php echo esc_url( $manual_links['app_passwords'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Application Passwords Manual', 'draftfence-ai-review' ); ?></a>
				</div>
			</div>

			<div class="draftfence-ai-review-panel">
				<div class="draftfence-ai-review-panel-header">
					<h2><?php esc_html_e( 'Recommended clients and manuals', 'draftfence-ai-review' ); ?></h2>
					<p><?php esc_html_e( 'Pick the client your user already has. DraftFence AI Review provides the endpoint, safe abilities, and test prompts; the client documentation owns the installation details.', 'draftfence-ai-review' ); ?></p>
				</div>

				<div class="draftfence-ai-review-client-tabs" aria-label="<?php esc_attr_e( 'AI client setup options', 'draftfence-ai-review' ); ?>">
					<div class="draftfence-ai-review-client-card">
						<div class="draftfence-ai-review-client-card-header">
							<div>
								<span class="draftfence-ai-review-client-kicker"><?php esc_html_e( 'Developer client', 'draftfence-ai-review' ); ?></span>
								<h3><?php esc_html_e( 'Gemini CLI', 'draftfence-ai-review' ); ?></h3>
							</div>
							<span class="draftfence-ai-review-client-pill"><?php esc_html_e( 'Manual', 'draftfence-ai-review' ); ?></span>
						</div>
						<p><?php esc_html_e( 'Use Gemini CLI MCP settings. Works well for local testing when configured with the endpoint and Basic Auth header.', 'draftfence-ai-review' ); ?></p>
						<div class="draftfence-ai-review-code-shell"><pre><code id="draftfence-ai-review-config-gemini"><?php echo esc_html( $gemini_config ); ?></code></pre></div>
						<div class="draftfence-ai-review-connect-actions">
							<button type="button" class="button button-primary draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-config-gemini"><?php esc_html_e( 'Copy Example Config', 'draftfence-ai-review' ); ?></button>
							<a class="button" href="<?php echo esc_url( $manual_links['gemini'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Gemini Manual', 'draftfence-ai-review' ); ?></a>
						</div>
					</div>

					<div class="draftfence-ai-review-client-card">
						<div class="draftfence-ai-review-client-card-header">
							<div>
								<span class="draftfence-ai-review-client-kicker"><?php esc_html_e( 'Desktop client', 'draftfence-ai-review' ); ?></span>
								<h3><?php esc_html_e( 'Claude Desktop', 'draftfence-ai-review' ); ?></h3>
							</div>
							<span class="draftfence-ai-review-client-pill"><?php esc_html_e( 'Manual', 'draftfence-ai-review' ); ?></span>
						</div>
						<p><?php esc_html_e( 'Use Claude Desktop local MCP setup or desktop extensions. Do not confuse this with the WordPress.com cloud connector.', 'draftfence-ai-review' ); ?></p>
						<div class="draftfence-ai-review-code-shell"><pre><code id="draftfence-ai-review-config-claude"><?php echo esc_html( $claude_config ); ?></code></pre></div>
						<div class="draftfence-ai-review-connect-actions">
							<button type="button" class="button button-primary draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-config-claude"><?php esc_html_e( 'Copy Example Config', 'draftfence-ai-review' ); ?></button>
							<a class="button" href="<?php echo esc_url( $manual_links['claude'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Claude Manual', 'draftfence-ai-review' ); ?></a>
						</div>
					</div>

					<div class="draftfence-ai-review-client-card">
						<div class="draftfence-ai-review-client-card-header">
							<div>
								<span class="draftfence-ai-review-client-kicker"><?php esc_html_e( 'Developer client', 'draftfence-ai-review' ); ?></span>
								<h3><?php esc_html_e( 'Codex CLI', 'draftfence-ai-review' ); ?></h3>
							</div>
							<span class="draftfence-ai-review-client-pill"><?php esc_html_e( 'Manual', 'draftfence-ai-review' ); ?></span>
						</div>
						<p><?php esc_html_e( 'Use Codex MCP configuration for stdio or streamable HTTP servers. For live sites, follow Codex and hosting-provider guidance rather than copying local machine paths from WordPress.', 'draftfence-ai-review' ); ?></p>
						<div class="draftfence-ai-review-manual-note">
							<strong><?php esc_html_e( 'Recommended:', 'draftfence-ai-review' ); ?></strong>
							<?php esc_html_e( 'Use DraftFence AI Review abilities only, then test with draftfence-ai-review/status before creating drafts or proposals.', 'draftfence-ai-review' ); ?>
						</div>
						<a class="button button-primary" href="<?php echo esc_url( $manual_links['codex'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Codex MCP Manual', 'draftfence-ai-review' ); ?></a>
					</div>

					<div class="draftfence-ai-review-client-card">
						<div class="draftfence-ai-review-client-card-header">
							<div>
								<span class="draftfence-ai-review-client-kicker"><?php esc_html_e( 'WordPress layer', 'draftfence-ai-review' ); ?></span>
								<h3><?php esc_html_e( 'MCP Adapter / Existing connectors', 'draftfence-ai-review' ); ?></h3>
							</div>
							<span class="draftfence-ai-review-client-pill"><?php esc_html_e( 'Docs', 'draftfence-ai-review' ); ?></span>
						</div>
						<p><?php esc_html_e( 'Use the WordPress MCP Adapter and any existing MCP-capable connector/plugin that fits your client. DraftFence AI Review should remain the governance layer, not the installer for every client.', 'draftfence-ai-review' ); ?></p>
						<div class="draftfence-ai-review-connect-actions">
							<a class="button button-primary" href="<?php echo esc_url( $manual_links['wp_adapter'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open MCP Adapter', 'draftfence-ai-review' ); ?></a>
							<button type="button" class="button draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-mcp-endpoint"><?php esc_html_e( 'Copy Endpoint', 'draftfence-ai-review' ); ?></button>
						</div>
					</div>
				</div>
			</div>

			<div class="draftfence-ai-review-panel">
				<div class="draftfence-ai-review-panel-header">
					<h2><?php esc_html_e( 'Safe ability exposure', 'draftfence-ai-review' ); ?></h2>
					<p><?php esc_html_e( 'This audit lists abilities marked public for the default WordPress MCP Adapter. It does not inspect a remote client, custom MCP servers, or direct REST access.', 'draftfence-ai-review' ); ?></p>
				</div>
				<div class="draftfence-ai-review-safe-grid">
					<div class="draftfence-ai-review-safe-card">
						<span><?php esc_html_e( 'Public MCP tools', 'draftfence-ai-review' ); ?></span>
						<strong><?php echo esc_html( count( $ability_names ) ); ?></strong>
						<p><?php esc_html_e( 'Tool-type abilities marked public for the default MCP Adapter.', 'draftfence-ai-review' ); ?></p>
					</div>
					<div class="draftfence-ai-review-safe-card">
						<span><?php esc_html_e( 'DraftFence AI Review abilities', 'draftfence-ai-review' ); ?></span>
						<strong><?php echo esc_html( count( array_intersect( $draftfence_ai_review_abilities, $ability_names ) ) ); ?>/<?php echo esc_html( count( $draftfence_ai_review_abilities ) ); ?></strong>
						<p><?php esc_html_e( 'Abilities this plugin governs directly.', 'draftfence-ai-review' ); ?></p>
					</div>
					<div class="draftfence-ai-review-safe-card <?php echo ! empty( $high_risk_abilities ) ? 'is-risky' : ''; ?>">
						<span><?php esc_html_e( 'Other write-like abilities', 'draftfence-ai-review' ); ?></span>
						<strong><?php echo esc_html( count( $high_risk_abilities ) ); ?></strong>
						<p><?php esc_html_e( 'Name-based warning only; review every non-DraftFence tool.', 'draftfence-ai-review' ); ?></p>
					</div>
				</div>
				<?php if ( ! empty( $non_draftfence_ai_review_abilities ) ) : ?>
					<div class="draftfence-ai-review-danger-panel">
						<strong><?php esc_html_e( 'Do not bypass DraftFence AI Review:', 'draftfence-ai-review' ); ?></strong>
						<?php esc_html_e( 'Other abilities are public to the default MCP Adapter. Prompts telling an agent to prefer DraftFence are not a security boundary. Disable unneeded abilities in your exposure plugin and verify the resulting tool list in your MCP client.', 'draftfence-ai-review' ); ?>
					</div>
					<details class="draftfence-ai-review-exposure-details">
						<summary><?php echo esc_html( sprintf( /* translators: %d: number of other public MCP abilities. */ _n( 'Review %d other public ability', 'Review %d other public abilities', count( $non_draftfence_ai_review_abilities ), 'draftfence-ai-review' ), count( $non_draftfence_ai_review_abilities ) ) ); ?></summary>
						<div class="draftfence-ai-review-exposure-list">
							<?php foreach ( $non_draftfence_ai_review_abilities as $ability_name ) : ?>
								<code><?php echo esc_html( $ability_name ); ?></code>
							<?php endforeach; ?>
						</div>
					</details>
				<?php endif; ?>
				<div class="draftfence-ai-review-manual-note">
					<strong><?php esc_html_e( 'Protected setup checklist:', 'draftfence-ai-review' ); ?></strong>
					<ol>
						<li><?php esc_html_e( 'Use a dedicated WordPress account for the AI client, never an administrator account.', 'draftfence-ai-review' ); ?></li>
						<li><?php esc_html_e( 'Expose only the DraftFence actions the client needs; disable other write-capable MCP abilities.', 'draftfence-ai-review' ); ?></li>
						<li><?php esc_html_e( 'Reconnect the client and inspect its discovered abilities. Confirm no direct post, SEO, or site-management writes remain.', 'draftfence-ai-review' ); ?></li>
						<li><?php esc_html_e( 'Test status, draft creation, and a pending proposal using that dedicated account.', 'draftfence-ai-review' ); ?></li>
					</ol>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( function_exists( 'ewpa_is_ability_enabled' ) ? 'options-general.php?page=ewpa-settings' : 'plugins.php?s=mcp&plugin_status=all' ) ); ?>"><?php esc_html_e( 'Open Ability Exposure Settings', 'draftfence-ai-review' ); ?></a>
				</div>
				<p><?php esc_html_e( 'These are DraftFence abilities, not all actions for one AI account. Approval and rollback require a separate human reviewer.', 'draftfence-ai-review' ); ?></p>
				<div class="draftfence-ai-review-code-shell"><pre><code id="draftfence-ai-review-safe-allowlist"><?php echo esc_html( implode( "\n", $draftfence_ai_review_abilities ) ); ?></code></pre></div>
				<button type="button" class="button draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-safe-allowlist"><?php esc_html_e( 'Copy Safe Ability List', 'draftfence-ai-review' ); ?></button>
			</div>

			<div class="draftfence-ai-review-panel">
				<div class="draftfence-ai-review-panel-header">
					<h2><?php esc_html_e( 'Test prompts and support report', 'draftfence-ai-review' ); ?></h2>
					<p><?php esc_html_e( 'After your MCP client is connected, use these prompts to confirm the safe DraftFence AI Review path works.', 'draftfence-ai-review' ); ?></p>
				</div>
				<div class="draftfence-ai-review-test-prompts">
					<div class="draftfence-ai-review-prompt-grid">
						<div>
							<strong><?php esc_html_e( '1. Status check', 'draftfence-ai-review' ); ?></strong>
							<pre><code id="draftfence-ai-review-prompt-status"><?php esc_html_e( 'Use wordpress MCP to execute draftfence-ai-review/status.', 'draftfence-ai-review' ); ?></code></pre>
							<button type="button" class="button draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-prompt-status"><?php esc_html_e( 'Copy Prompt', 'draftfence-ai-review' ); ?></button>
						</div>
						<div>
							<strong><?php esc_html_e( '2. List safe content', 'draftfence-ai-review' ); ?></strong>
							<pre><code id="draftfence-ai-review-prompt-list"><?php esc_html_e( 'Use wordpress MCP to execute draftfence-ai-review/list-content and show the latest posts.', 'draftfence-ai-review' ); ?></code></pre>
							<button type="button" class="button draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-prompt-list"><?php esc_html_e( 'Copy Prompt', 'draftfence-ai-review' ); ?></button>
						</div>
						<div>
							<strong><?php esc_html_e( '3. Create safe draft', 'draftfence-ai-review' ); ?></strong>
							<pre><code id="draftfence-ai-review-prompt-draft"><?php esc_html_e( 'Use wordpress MCP to execute draftfence-ai-review/create-draft with title "DraftFence AI Review Connection Test" and content "This draft was created safely through DraftFence AI Review MCP."', 'draftfence-ai-review' ); ?></code></pre>
							<button type="button" class="button draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-prompt-draft"><?php esc_html_e( 'Copy Prompt', 'draftfence-ai-review' ); ?></button>
						</div>
						<div>
							<strong><?php esc_html_e( '4. Pending proposal test', 'draftfence-ai-review' ); ?></strong>
							<pre><code id="draftfence-ai-review-prompt-proposal"><?php esc_html_e( 'Use wordpress MCP to execute draftfence-ai-review/propose-content-seo-update for an existing draft/post, then list pending proposals.', 'draftfence-ai-review' ); ?></code></pre>
							<button type="button" class="button draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-prompt-proposal"><?php esc_html_e( 'Copy Prompt', 'draftfence-ai-review' ); ?></button>
						</div>
					</div>
				</div>
				<div class="draftfence-ai-review-support-report">
					<div class="draftfence-ai-review-client-card-header">
						<div>
							<span class="draftfence-ai-review-client-kicker"><?php esc_html_e( 'No secrets included', 'draftfence-ai-review' ); ?></span>
							<h3><?php esc_html_e( 'Support report', 'draftfence-ai-review' ); ?></h3>
						</div>
					</div>
					<p><?php esc_html_e( 'Copy this when asking for help. It includes versions, routes, ability counts, and diagnostics, but no Application Passwords.', 'draftfence-ai-review' ); ?></p>
					<textarea id="draftfence-ai-review-support-report" readonly><?php echo esc_textarea( $support_report ); ?></textarea>
					<button type="button" class="button draftfence-ai-review-copy-button" data-copy-target="draftfence-ai-review-support-report"><?php esc_html_e( 'Copy Support Report', 'draftfence-ai-review' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_onboarding() {
		$settings   = DraftFence_AI_Review::get_settings();
		$post_types = DraftFence_AI_Review::get_supported_post_types();
		$users      = get_users(
			array(
				'capability' => 'edit_posts',
				'fields'     => array( 'ID', 'display_name', 'user_login', 'user_email' ),
			)
		);

		?>
		<div class="wrap draftfence-ai-review-wrap">
			<div class="draftfence-ai-review-wizard-hero">
				<div>
					<span class="draftfence-ai-review-eyebrow"><?php esc_html_e( 'First-run setup', 'draftfence-ai-review' ); ?></span>
					<h1 class="wp-heading-inline draftfence-ai-review-page-title"><?php esc_html_e( 'DraftFence AI Review Onboarding', 'draftfence-ai-review' ); ?></h1>
					<div class="draftfence-ai-review-wizard-title"><?php esc_html_e( 'Launch DraftFence AI Review in five safe steps', 'draftfence-ai-review' ); ?></div>
					<p><?php esc_html_e( 'Create a narrow AI identity, choose what it can do, route proposals to reviewers, and confirm your safety posture before connecting an agent.', 'draftfence-ai-review' ); ?></p>
				</div>
				<div class="draftfence-ai-review-wizard-meter">
					<span>1</span><span>2</span><span>3</span><span>4</span><span>5</span>
				</div>
			</div>

			<form method="post" class="draftfence-ai-review-wizard">
				<?php wp_nonce_field( 'draftfence-ai-review_finish_onboarding' ); ?>

				<section class="draftfence-ai-review-wizard-step">
					<div class="draftfence-ai-review-step-number">1</div>
					<div class="draftfence-ai-review-step-body">
						<h2><?php esc_html_e( 'Welcome', 'draftfence-ai-review' ); ?></h2>
						<p><?php esc_html_e( 'DraftFence AI Review works best when the AI gets a dedicated WordPress user. Human reviewers stay separate, so AI can request changes but cannot approve its own work.', 'draftfence-ai-review' ); ?></p>
						<div class="draftfence-ai-review-wizard-note">
							<strong><?php esc_html_e( 'Recommended:', 'draftfence-ai-review' ); ?></strong>
							<?php esc_html_e( 'Create a user like seo-agent or content-agent, then give it an Application Password.', 'draftfence-ai-review' ); ?>
						</div>
					</div>
				</section>

				<section class="draftfence-ai-review-wizard-step">
					<div class="draftfence-ai-review-step-number">2</div>
					<div class="draftfence-ai-review-step-body">
						<h2><?php esc_html_e( 'Choose AI Identity', 'draftfence-ai-review' ); ?></h2>
						<div class="draftfence-ai-review-field-grid">
							<label>
								<span><?php esc_html_e( 'WordPress user', 'draftfence-ai-review' ); ?></span>
								<select name="onboarding_user_id" required>
									<option value=""><?php esc_html_e( 'Select a user', 'draftfence-ai-review' ); ?></option>
									<?php foreach ( $users as $user ) : ?>
										<option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Identity label', 'draftfence-ai-review' ); ?></span>
								<input type="text" name="onboarding_label" value="<?php esc_attr_e( 'Content Safety Agent', 'draftfence-ai-review' ); ?>" />
							</label>
						</div>
					</div>
				</section>

				<section class="draftfence-ai-review-wizard-step">
					<div class="draftfence-ai-review-step-number">3</div>
					<div class="draftfence-ai-review-step-body">
						<h2><?php esc_html_e( 'Choose Allowed Actions', 'draftfence-ai-review' ); ?></h2>
						<div class="draftfence-ai-review-choice-grid">
							<label><input type="checkbox" name="onboarding_actions[]" value="read" checked /> <strong><?php esc_html_e( 'Read summaries', 'draftfence-ai-review' ); ?></strong><span><?php esc_html_e( 'List allowed posts and SEO metadata.', 'draftfence-ai-review' ); ?></span></label>
							<label><input type="checkbox" name="onboarding_actions[]" value="draft" checked /> <strong><?php esc_html_e( 'Create drafts', 'draftfence-ai-review' ); ?></strong><span><?php esc_html_e( 'Create draft-only content, never publish.', 'draftfence-ai-review' ); ?></span></label>
							<label><input type="checkbox" name="onboarding_actions[]" value="proposal" checked /> <strong><?php esc_html_e( 'Propose edits', 'draftfence-ai-review' ); ?></strong><span><?php esc_html_e( 'Submit existing-content changes for human review.', 'draftfence-ai-review' ); ?></span></label>
						</div>
						<div class="draftfence-ai-review-field-grid">
							<label>
								<span><?php esc_html_e( 'Max proposal risk', 'draftfence-ai-review' ); ?></span>
								<select name="onboarding_max_risk">
									<option value="low"><?php esc_html_e( 'Low', 'draftfence-ai-review' ); ?></option>
									<option value="medium" selected><?php esc_html_e( 'Medium', 'draftfence-ai-review' ); ?></option>
									<option value="high"><?php esc_html_e( 'High', 'draftfence-ai-review' ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Content payload limit', 'draftfence-ai-review' ); ?></span>
								<input type="number" min="1000" max="500000" name="onboarding_content_limit" value="200000" />
							</label>
						</div>
					</div>
				</section>

				<section class="draftfence-ai-review-wizard-step">
					<div class="draftfence-ai-review-step-number">4</div>
					<div class="draftfence-ai-review-step-body">
						<h2><?php esc_html_e( 'Scope Content and Notifications', 'draftfence-ai-review' ); ?></h2>
						<div class="draftfence-ai-review-choice-grid">
							<?php foreach ( $post_types as $name => $label ) : ?>
								<label><input type="checkbox" name="onboarding_post_types[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $settings['allowed_post_types'], true ) ); ?> /> <strong><?php echo esc_html( $label ); ?></strong><span><code><?php echo esc_html( $name ); ?></code></span></label>
							<?php endforeach; ?>
						</div>
						<label class="draftfence-ai-review-wide-field">
							<span><?php esc_html_e( 'Review notification email', 'draftfence-ai-review' ); ?></span>
							<input type="text" name="onboarding_notify_emails" value="<?php echo esc_attr( $settings['notify_emails'] ); ?>" />
						</label>
					</div>
				</section>

				<section class="draftfence-ai-review-wizard-step">
					<div class="draftfence-ai-review-step-number">5</div>
					<div class="draftfence-ai-review-step-body">
						<h2><?php esc_html_e( 'Test Connection Readiness', 'draftfence-ai-review' ); ?></h2>
						<div class="draftfence-ai-review-readiness-grid">
							<div><strong><?php esc_html_e( 'REST API', 'draftfence-ai-review' ); ?></strong><span><?php esc_html_e( 'Ready after save', 'draftfence-ai-review' ); ?></span></div>
							<div><strong><?php esc_html_e( 'Abilities API', 'draftfence-ai-review' ); ?></strong><span><?php echo function_exists( 'wp_register_ability' ) ? esc_html__( 'Available', 'draftfence-ai-review' ) : esc_html__( 'Not detected', 'draftfence-ai-review' ); ?></span></div>
							<div><strong><?php esc_html_e( 'Human approval', 'draftfence-ai-review' ); ?></strong><span><?php esc_html_e( 'Always required', 'draftfence-ai-review' ); ?></span></div>
						</div>
						<p class="draftfence-ai-review-wizard-note"><?php esc_html_e( 'After finishing, connect your AI client using the selected user and an Application Password.', 'draftfence-ai-review' ); ?></p>
						<button type="submit" name="draftfence-ai-review_finish_onboarding" class="button button-primary button-hero"><?php esc_html_e( 'Finish Setup', 'draftfence-ai-review' ); ?></button>
					</div>
				</section>
			</form>
		</div>
		<?php
	}

	public static function render_proposals() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter value.
		$status = isset( $_GET['proposal_status'] ) ? sanitize_key( wp_unslash( $_GET['proposal_status'] ) ) : 'pending';
		$result = DraftFence_AI_Review_Actions::list_proposals( array( 'status' => $status, 'limit' => 100 ) );
		$items  = is_wp_error( $result ) ? array() : $result['items'];

		?>
		<div class="wrap draftfence-ai-review-wrap">
			<h1 class="wp-heading-inline draftfence-ai-review-page-title"><?php esc_html_e( 'DraftFence AI Review Proposal Review', 'draftfence-ai-review' ); ?></h1>
			<?php self::render_header( __( 'Proposal Review', 'draftfence-ai-review' ), __( 'Approve, reject, or roll back AI-proposed content and SEO changes with full context.', 'draftfence-ai-review' ) ); ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag after a nonce-protected redirect. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Proposal updated.', 'draftfence-ai-review' ); ?></p></div>
			<?php endif; ?>

			<form method="get" class="draftfence-ai-review-log-filters">
				<input type="hidden" name="page" value="draftfence-ai-review-proposals" />
				<label>
					<?php esc_html_e( 'Status', 'draftfence-ai-review' ); ?>
					<select name="proposal_status">
						<?php foreach ( array( 'pending', 'approved', 'rejected', 'rolled_back' ) as $proposal_status ) : ?>
							<option value="<?php echo esc_attr( $proposal_status ); ?>" <?php selected( $status, $proposal_status ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $proposal_status ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'draftfence-ai-review' ); ?></button>
			</form>

			<?php if ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'No proposals match this status.', 'draftfence-ai-review' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $items as $item ) : ?>
				<?php $post = get_post( absint( $item['post_id'] ) ); ?>
				<div class="draftfence-ai-review-proposal">
					<div class="draftfence-ai-review-proposal-header">
						<div>
							<h2>
								<?php echo esc_html( $item['post_title'] ? $item['post_title'] : __( 'Untitled content', 'draftfence-ai-review' ) ); ?>
								<span>#<?php echo esc_html( $item['id'] ); ?></span>
							</h2>
							<p>
								<strong><?php esc_html_e( 'Post ID:', 'draftfence-ai-review' ); ?></strong> <?php echo esc_html( $item['post_id'] ); ?>
								<strong><?php esc_html_e( 'Type:', 'draftfence-ai-review' ); ?></strong> <?php echo esc_html( $item['post_type'] ); ?>
								<strong><?php esc_html_e( 'Created:', 'draftfence-ai-review' ); ?></strong> <?php echo esc_html( $item['created_at'] ); ?>
							</p>
						</div>
						<div class="draftfence-ai-review-proposal-badges">
							<span class="draftfence-ai-review-risk draftfence-ai-review-risk-neutral"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $item['status'] ) ) ); ?></span>
							<?php if ( ! empty( $item['risk']['level'] ) ) : ?>
								<span class="draftfence-ai-review-risk draftfence-ai-review-risk-<?php echo esc_attr( $item['risk']['level'] ); ?>"><?php echo esc_html( ucfirst( $item['risk']['level'] ) ); ?> <?php esc_html_e( 'risk', 'draftfence-ai-review' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['has_conflict'] ) && 'pending' === $item['status'] ) : ?>
								<span class="draftfence-ai-review-risk draftfence-ai-review-risk-high"><?php esc_html_e( 'Conflict', 'draftfence-ai-review' ); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( ! empty( $item['risk']['reasons'] ) ) : ?>
						<ul class="draftfence-ai-review-risk-reasons">
							<?php foreach ( $item['risk']['reasons'] as $reason ) : ?>
								<li><?php echo esc_html( $reason ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php self::render_proposal_diff( $item, $post ); ?>
					<?php self::render_proposal_field( __( 'Reason', 'draftfence-ai-review' ), $item['reason'] ); ?>

					<?php if ( ! empty( $item['content'] ) ) : ?>
						<details>
							<summary><?php esc_html_e( 'Suggested Content', 'draftfence-ai-review' ); ?></summary>
							<div class="draftfence-ai-review-content-compare">
								<div>
									<h3><?php esc_html_e( 'Current at Proposal Time', 'draftfence-ai-review' ); ?></h3>
									<div class="draftfence-ai-review-content-preview"><?php echo wp_kses_post( wpautop( $item['original_content'] ) ); ?></div>
								</div>
								<div>
									<h3><?php esc_html_e( 'Proposed', 'draftfence-ai-review' ); ?></h3>
									<div class="draftfence-ai-review-content-preview"><?php echo wp_kses_post( wpautop( $item['content'] ) ); ?></div>
								</div>
							</div>
						</details>
					<?php endif; ?>

					<form method="post" class="draftfence-ai-review-proposal-actions">
						<?php wp_nonce_field( 'draftfence-ai-review_proposal_action' ); ?>
						<input type="hidden" name="proposal_id" value="<?php echo esc_attr( $item['id'] ); ?>" />
						<textarea name="notes" rows="2" placeholder="<?php esc_attr_e( 'Optional review notes', 'draftfence-ai-review' ); ?>"></textarea>
						<?php if ( 'pending' === $item['status'] ) : ?>
							<button type="submit" name="draftfence-ai-review_proposal_action" value="approve" class="button button-primary" <?php disabled( ! empty( $item['has_conflict'] ) ); ?>><?php esc_html_e( 'Approve & Apply', 'draftfence-ai-review' ); ?></button>
							<button type="submit" name="draftfence-ai-review_proposal_action" value="reject" class="button"><?php esc_html_e( 'Reject', 'draftfence-ai-review' ); ?></button>
						<?php elseif ( 'approved' === $item['status'] && empty( $item['rolled_back_at'] ) ) : ?>
							<button type="submit" name="draftfence-ai-review_proposal_action" value="rollback" class="button button-secondary"><?php esc_html_e( 'Rollback', 'draftfence-ai-review' ); ?></button>
						<?php endif; ?>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function render_identities() {
		$settings   = DraftFence_AI_Review::get_settings();
		$post_types = DraftFence_AI_Review::get_supported_post_types();
		$profiles   = $settings['agent_profiles'];
		$agent_users = get_users( array( 'capability' => 'draftfence_ai_review_use_ai' ) );
		$all_users   = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_login', 'user_email' ),
			)
		);

		foreach ( $agent_users as $user ) {
			if ( empty( $profiles[ $user->ID ] ) ) {
				$profiles[ $user->ID ] = array(
					'label'         => $user->display_name,
					'actions'       => array( 'read', 'draft', 'proposal' ),
					'post_types'    => $settings['allowed_post_types'],
					'max_risk'      => 'high',
					'content_limit' => 200000,
					'enabled'       => 1,
				);
			}
		}

		$profiles['new'] = array(
			'label'         => '',
			'actions'       => array( 'read', 'draft', 'proposal' ),
			'post_types'    => $settings['allowed_post_types'],
			'max_risk'      => 'medium',
			'content_limit' => 200000,
			'enabled'       => 1,
		);

		?>
		<div class="wrap draftfence-ai-review-wrap">
			<h1 class="wp-heading-inline draftfence-ai-review-page-title"><?php esc_html_e( 'DraftFence AI Review AI Identities', 'draftfence-ai-review' ); ?></h1>
			<?php self::render_header( __( 'AI Identities', 'draftfence-ai-review' ), __( 'Create narrow, auditable operating profiles for each AI-connected WordPress user.', 'draftfence-ai-review' ) ); ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag after a nonce-protected redirect. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'AI identities saved.', 'draftfence-ai-review' ); ?></p></div>
			<?php endif; ?>

			<form method="post" class="draftfence-ai-review-panel">
				<?php wp_nonce_field( 'draftfence-ai-review_save_identities' ); ?>
				<div class="draftfence-ai-review-panel-header">
					<h2><?php esc_html_e( 'Identity Profiles', 'draftfence-ai-review' ); ?></h2>
					<p><?php esc_html_e( 'Only users listed here receive DraftFence AI Review AI capability.', 'draftfence-ai-review' ); ?></p>
				</div>
				<table class="widefat striped draftfence-ai-review-identity-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'WordPress user', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'Label', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'Allowed actions', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'Content types', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'Max risk', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'Content limit', 'draftfence-ai-review' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'draftfence-ai-review' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $profiles as $profile_id => $profile ) : ?>
							<?php $user = 'new' !== $profile_id ? get_user_by( 'id', absint( $profile_id ) ) : null; ?>
							<tr>
								<td>
									<select name="profiles[<?php echo esc_attr( $profile_id ); ?>][user_id]" class="draftfence-ai-review-user-select">
										<option value=""><?php esc_html_e( 'Select a WordPress user', 'draftfence-ai-review' ); ?></option>
										<?php foreach ( $all_users as $available_user ) : ?>
											<option value="<?php echo esc_attr( $available_user->ID ); ?>" <?php selected( 'new' !== $profile_id ? absint( $profile_id ) : 0, $available_user->ID ); ?>>
												<?php echo esc_html( sprintf( '%1$s (%2$s) — %3$s', $available_user->display_name, $available_user->user_login, $available_user->user_email ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<?php if ( $user ) : ?>
										<p class="description">
											<?php
											printf(
												/* translators: %d: WordPress user ID. */
												esc_html__( 'User ID: %d', 'draftfence-ai-review' ),
												absint( $user->ID )
											);
											?>
										</p>
									<?php endif; ?>
								</td>
								<td><input type="text" name="profiles[<?php echo esc_attr( $profile_id ); ?>][label]" value="<?php echo esc_attr( $profile['label'] ); ?>" /></td>
								<td>
									<?php foreach ( array( 'read' => __( 'Read', 'draftfence-ai-review' ), 'draft' => __( 'Draft', 'draftfence-ai-review' ), 'proposal' => __( 'Propose', 'draftfence-ai-review' ) ) as $action => $label ) : ?>
										<label><input type="checkbox" name="profiles[<?php echo esc_attr( $profile_id ); ?>][actions][]" value="<?php echo esc_attr( $action ); ?>" <?php checked( in_array( $action, $profile['actions'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
									<?php endforeach; ?>
								</td>
								<td>
									<?php foreach ( $post_types as $name => $label ) : ?>
										<label><input type="checkbox" name="profiles[<?php echo esc_attr( $profile_id ); ?>][post_types][]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $profile['post_types'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
									<?php endforeach; ?>
								</td>
								<td>
									<select name="profiles[<?php echo esc_attr( $profile_id ); ?>][max_risk]">
										<?php foreach ( array( 'low', 'medium', 'high' ) as $risk ) : ?>
											<option value="<?php echo esc_attr( $risk ); ?>" <?php selected( $profile['max_risk'], $risk ); ?>><?php echo esc_html( ucfirst( $risk ) ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="number" min="1000" max="500000" name="profiles[<?php echo esc_attr( $profile_id ); ?>][content_limit]" value="<?php echo esc_attr( $profile['content_limit'] ); ?>" /></td>
								<td><input type="checkbox" name="profiles[<?php echo esc_attr( $profile_id ); ?>][enabled]" value="1" <?php checked( ! empty( $profile['enabled'] ) ); ?> /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" name="draftfence-ai-review_save_identities" class="button button-primary"><?php esc_html_e( 'Save AI Identities', 'draftfence-ai-review' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	public static function render_logs() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter value.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter value.
		$action = isset( $_GET['log_action'] ) ? sanitize_key( wp_unslash( $_GET['log_action'] ) ) : '';
		$logs   = DraftFence_AI_Review_Audit_Log::query(
			array(
				'limit'  => 100,
				'status' => $status,
				'action' => $action,
			)
		);
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'                   => 'draftfence-ai-review-logs',
					'draftfence-ai-review_export_logs' => '1',
					'status'                 => $status,
					'log_action'             => $action,
				),
				admin_url( 'admin.php' )
			),
			'draftfence-ai-review_export_logs'
		);

		?>
		<div class="wrap draftfence-ai-review-wrap">
			<h1 class="wp-heading-inline draftfence-ai-review-page-title"><?php esc_html_e( 'DraftFence AI Review Audit Logs', 'draftfence-ai-review' ); ?></h1>
			<?php self::render_header( __( 'Audit Logs', 'draftfence-ai-review' ), __( 'Trace AI activity, blocked attempts, reviews, rollbacks, and exportable evidence.', 'draftfence-ai-review' ) ); ?>
			<form method="get" class="draftfence-ai-review-log-filters">
				<input type="hidden" name="page" value="draftfence-ai-review-logs" />
				<label>
					<?php esc_html_e( 'Status', 'draftfence-ai-review' ); ?>
					<input type="text" name="status" value="<?php echo esc_attr( $status ); ?>" placeholder="<?php esc_attr_e( 'allowed, blocked, failed', 'draftfence-ai-review' ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'Action', 'draftfence-ai-review' ); ?>
					<input type="text" name="log_action" value="<?php echo esc_attr( $action ); ?>" placeholder="<?php esc_attr_e( 'create-draft', 'draftfence-ai-review' ); ?>" />
				</label>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'draftfence-ai-review' ); ?></button>
				<a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'draftfence-ai-review' ); ?></a>
			</form>
			<?php self::render_logs_table( $logs ); ?>
		</div>
		<?php
	}

	private static function render_logs_table( $logs ) {
		?>
		<table class="widefat striped draftfence-ai-review-log-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'draftfence-ai-review' ); ?></th>
					<th><?php esc_html_e( 'Actor', 'draftfence-ai-review' ); ?></th>
					<th><?php esc_html_e( 'Action', 'draftfence-ai-review' ); ?></th>
					<th><?php esc_html_e( 'Status', 'draftfence-ai-review' ); ?></th>
					<th><?php esc_html_e( 'Object', 'draftfence-ai-review' ); ?></th>
					<th><?php esc_html_e( 'Message', 'draftfence-ai-review' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No activity logged yet.', 'draftfence-ai-review' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log->created_at ); ?></td>
						<td><?php echo esc_html( $log->actor ); ?></td>
						<td><code><?php echo esc_html( $log->action ); ?></code></td>
						<td><span class="draftfence-ai-review-pill draftfence-ai-review-pill-<?php echo esc_attr( $log->status ); ?>"><?php echo esc_html( $log->status ); ?></span></td>
						<td><?php echo esc_html( trim( $log->object_type . ' #' . $log->object_id ) ); ?></td>
						<td><?php echo esc_html( $log->message ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_header( $title, $lede, $status = null ) {
		?>
		<div class="draftfence-ai-review-hero">
			<div class="draftfence-ai-review-hero-main">
				<div class="draftfence-ai-review-logo">
					<span class="dashicons dashicons-shield-alt"></span>
				</div>
				<div>
					<div class="draftfence-ai-review-hero-title"><?php echo esc_html( $title ); ?></div>
					<p><?php echo esc_html( $lede ); ?></p>
				</div>
			</div>
			<?php if ( is_array( $status ) ) : ?>
				<div class="draftfence-ai-review-hero-status">
					<span class="draftfence-ai-review-status <?php echo $status['enabled'] ? 'is-on' : 'is-off'; ?>">
						<?php echo $status['enabled'] ? esc_html__( 'Protected', 'draftfence-ai-review' ) : esc_html__( 'Paused', 'draftfence-ai-review' ); ?>
					</span>
					<small>
						<?php
						printf(
							/* translators: %s: DraftFence AI Review plugin version. */
							esc_html__( 'v%s', 'draftfence-ai-review' ),
							esc_html( DRAFTFENCE_AI_REVIEW_VERSION )
						);
						?>
					</small>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function get_draftfence_ai_review_ability_names() {
		return array(
			'draftfence-ai-review/status',
			'draftfence-ai-review/list-content',
			'draftfence-ai-review/create-draft',
			'draftfence-ai-review/propose-content-seo-update',
			'draftfence-ai-review/list-pending-proposals',
			'draftfence-ai-review/approve-proposal',
			'draftfence-ai-review/reject-proposal',
			'draftfence-ai-review/rollback-approved-proposal',
		);
	}

	private static function get_exposed_ability_names() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$abilities = wp_get_abilities();
		$names     = array();

		if ( ! is_array( $abilities ) ) {
			return array();
		}

		foreach ( $abilities as $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) || ! method_exists( $ability, 'get_meta' ) ) {
				continue;
			}

			$meta = $ability->get_meta();
			if ( ! is_array( $meta ) || empty( $meta['mcp']['public'] ) || ( isset( $meta['mcp']['type'] ) && 'tool' !== $meta['mcp']['type'] ) ) {
				continue;
			}

			$names[] = $ability->get_name();
		}

		$names = array_values( array_unique( array_filter( array_map( 'strval', $names ) ) ) );
		sort( $names );

		return $names;
	}

	private static function build_connection_support_report( $context ) {
		$lines = array(
			'DraftFence AI Review Connection Support Report',
			'Generated: ' . current_time( 'mysql' ),
			'',
			'Versions',
			'- DraftFence AI Review: ' . DRAFTFENCE_AI_REVIEW_VERSION,
			'- WordPress: ' . get_bloginfo( 'version' ),
			'- PHP: ' . PHP_VERSION,
			'- Site URL: ' . site_url(),
			'- Environment: ' . ( function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown' ),
			'',
			'Routes',
			'- MCP endpoint: ' . $context['endpoint'],
			'- DraftFence AI Review status endpoint: ' . $context['status_url'],
			'- Suggested auth: WordPress Application Password / Basic Auth when supported by the MCP client.',
			'- Suggested WordPress user: ' . $context['user_login'],
			'- Setup note: Use the official MCP client manual or an existing connector/extension for transport-specific setup.',
			'',
			'Abilities',
			'- Public MCP tools count: ' . $context['ability_count'],
			'- DraftFence AI Review abilities found: ' . $context['draftfence_ai_review_ability_count'],
			'- Non-DraftFence AI Review abilities count: ' . $context['non_draftfence_ai_review_count'],
			'- High-risk ability indicators: ' . $context['high_risk_ability_count'],
			'',
			'Diagnostics',
		);

		foreach ( $context['diagnostics'] as $row ) {
			$lines[] = '- ' . $row['label'] . ': ' . ( ! empty( $row['pass'] ) ? 'PASS' : 'NEEDS ATTENTION' );
		}

		return implode( "\n", $lines );
	}

	private static function get_connection_diagnostics() {
		$settings         = DraftFence_AI_Review::get_settings();
		$active_plugins   = (array) get_option( 'active_plugins', array() );
		$mcp_route_exists = false;
		$draftfence_ai_review_routes = false;
		$wp_abilities_routes = false;

		if ( function_exists( 'rest_get_server' ) ) {
			$routes              = rest_get_server()->get_routes();
			$mcp_route_exists    = isset( $routes['/mcp/mcp-adapter-default-server'] );
			$draftfence_ai_review_routes   = isset( $routes['/draftfence-ai-review/v1/status'] );
			$wp_abilities_routes = isset( $routes['/wp-abilities/v1/abilities'] );
		}

		$mcp_adapter_active = in_array( 'mcp-adapter/mcp-adapter.php', $active_plugins, true ) || $mcp_route_exists;
		$abilities_helper_active = false;

		foreach ( $active_plugins as $plugin ) {
			if ( false !== strpos( $plugin, 'enable-abilities-for-mcp' ) ) {
				$abilities_helper_active = true;
				break;
			}
		}

		$ai_profiles = ! empty( $settings['agent_profiles'] );
		$privileged_ai_profiles = array();
		foreach ( $settings['agent_profiles'] as $user_id => $profile ) {
			if ( empty( $profile['enabled'] ) ) {
				continue;
			}
			$user = get_user_by( 'id', absint( $user_id ) );
			if ( $user && ( user_can( $user, 'manage_options' ) || user_can( $user, 'publish_posts' ) || user_can( $user, 'edit_others_posts' ) || user_can( $user, 'delete_others_posts' ) ) ) {
				$privileged_ai_profiles[] = $user->user_login;
			}
		}
		$public_abilities = self::get_exposed_ability_names();
		$other_public_abilities = array_values( array_filter( $public_abilities, static function ( $ability_name ) {
			return 0 !== strpos( $ability_name, 'draftfence-ai-review/' ) && 0 !== strpos( $ability_name, 'mcp-adapter/' );
		} ) );
		$pretty_permalinks = (bool) get_option( 'permalink_structure' );
		$env_type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$app_passwords_available = wp_is_application_passwords_available();

		return array(
			array(
				'label' => __( 'AI identities avoid broad WordPress write privileges', 'draftfence-ai-review' ),
				'pass'  => empty( $privileged_ai_profiles ) && $ai_profiles,
				'help'  => ! $ai_profiles ? __( 'Configure a dedicated AI identity before connecting an AI client.', 'draftfence-ai-review' ) : ( empty( $privileged_ai_profiles ) ? __( 'No configured AI identity can publish or edit others\' posts through its WordPress role. Direct REST access to content the user can edit still remains possible.', 'draftfence-ai-review' ) : sprintf( /* translators: %s: comma-separated usernames. */ __( 'These AI identities can publish or edit others\' posts through WordPress: %s. Use dedicated accounts with narrower roles.', 'draftfence-ai-review' ), implode( ', ', $privileged_ai_profiles ) ) ),
				'url'   => admin_url( 'admin.php?page=draftfence-ai-review-identities' ),
				'action_label' => __( 'Review Identities', 'draftfence-ai-review' ),
			),
			array(
				'label' => __( 'Default MCP exposure limited to DraftFence', 'draftfence-ai-review' ),
				'pass'  => empty( $other_public_abilities ) && ! empty( $public_abilities ),
				'help'  => __( 'Other public MCP abilities may bypass DraftFence approval. Review them individually. This check cannot inspect custom MCP servers or direct REST access.', 'draftfence-ai-review' ),
				'url'   => admin_url( function_exists( 'ewpa_is_ability_enabled' ) ? 'options-general.php?page=ewpa-settings' : 'plugins.php?s=mcp&plugin_status=all' ),
				'action_label' => __( 'Review Exposure', 'draftfence-ai-review' ),
			),
			array(
				'label' => __( 'DraftFence AI Review protection enabled', 'draftfence-ai-review' ),
				'pass'  => ! empty( $settings['enabled'] ),
				'help'  => __( 'The emergency switch must be on before AI clients can use DraftFence AI Review actions.', 'draftfence-ai-review' ),
				'url'   => admin_url( 'admin.php?page=draftfence-ai-review-settings' ),
				'action_label' => __( 'Open Settings', 'draftfence-ai-review' ),
			),
			array(
				'label' => __( 'WordPress REST API route registered', 'draftfence-ai-review' ),
				'pass'  => $draftfence_ai_review_routes,
				'help'  => __( 'DraftFence AI Review REST endpoints must be visible under /wp-json/draftfence-ai-review/v1/.', 'draftfence-ai-review' ),
				'url'   => rest_url( 'draftfence-ai-review/v1/status' ),
				'action_label' => __( 'Test Route', 'draftfence-ai-review' ),
				'external' => true,
			),
			array(
				'label' => __( 'WordPress Abilities API available', 'draftfence-ai-review' ),
				'pass'  => function_exists( 'wp_register_ability' ),
				'help'  => __( 'Required for exposing DraftFence AI Review actions as WordPress abilities.', 'draftfence-ai-review' ),
				'url'   => admin_url( 'plugins.php' ),
				'action_label' => __( 'Open Plugins', 'draftfence-ai-review' ),
			),
			array(
				'label' => __( 'Abilities REST routes available', 'draftfence-ai-review' ),
				'pass'  => $wp_abilities_routes,
				'help'  => __( 'The /wp-abilities/v1 routes let tools inspect and run registered abilities.', 'draftfence-ai-review' ),
				'url'   => rest_url( 'wp-abilities/v1/abilities' ),
				'action_label' => __( 'Test Route', 'draftfence-ai-review' ),
				'external' => true,
			),
			array(
				'label' => __( 'MCP Adapter endpoint detected', 'draftfence-ai-review' ),
				'pass'  => $mcp_adapter_active,
				'help'  => __( 'The MCP Adapter must expose /wp-json/mcp/mcp-adapter-default-server.', 'draftfence-ai-review' ),
				'url'   => admin_url( 'plugins.php?s=mcp&plugin_status=all' ),
				'action_label' => __( 'Open MCP Plugins', 'draftfence-ai-review' ),
			),
			array(
				'label' => __( 'Enable Abilities for MCP helper active', 'draftfence-ai-review' ),
				'pass'  => $abilities_helper_active,
				'help'  => __( 'Recommended when you want to control which abilities are exposed to MCP clients.', 'draftfence-ai-review' ),
				'url'   => admin_url( 'plugins.php?s=abilities%20mcp&plugin_status=all' ),
				'action_label' => __( 'Open Plugins', 'draftfence-ai-review' ),
			),
			array(
				'label' => __( 'Application Passwords available', 'draftfence-ai-review' ),
				'pass'  => $app_passwords_available,
				'help'  => sprintf(
					/* translators: %s: WordPress environment type. */
					__( 'Current environment type: %s. Local HTTP sites usually need WP_ENVIRONMENT_TYPE set to local.', 'draftfence-ai-review' ),
					$env_type
				),
				'url'   => admin_url( 'profile.php#application-passwords-section' ),
				'action_label' => __( 'Open Profile', 'draftfence-ai-review' ),
			),
			array(
				'label' => __( 'Pretty permalinks enabled', 'draftfence-ai-review' ),
				'pass'  => $pretty_permalinks,
				'help'  => __( 'Pretty permalinks make REST/MCP URLs predictable. Plain permalinks can confuse setup guides.', 'draftfence-ai-review' ),
				'url'   => admin_url( 'options-permalink.php' ),
				'action_label' => __( 'Open Permalinks', 'draftfence-ai-review' ),
			),
			array(
				'label' => __( 'At least one AI identity profile configured', 'draftfence-ai-review' ),
				'pass'  => $ai_profiles,
				'help'  => __( 'Dedicated AI users should receive DraftFence AI Review AI capability and a scoped identity profile.', 'draftfence-ai-review' ),
				'url'   => admin_url( 'admin.php?page=draftfence-ai-review-identities' ),
				'action_label' => __( 'Open Identities', 'draftfence-ai-review' ),
			),
		);
	}

	private static function render_diagnostic_row( $row ) {
		$pass = ! empty( $row['pass'] );
		$url = ! empty( $row['url'] ) ? $row['url'] : '';
		$action_label = ! empty( $row['action_label'] ) ? $row['action_label'] : __( 'Open', 'draftfence-ai-review' );
		$link_target = ! empty( $row['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

		?>
		<tr class="<?php echo $pass ? 'draftfence-ai-review-diagnostic-pass' : 'draftfence-ai-review-diagnostic-fail'; ?>">
			<td><?php echo esc_html( $row['label'] ); ?></td>
			<td>
				<span class="draftfence-ai-review-diagnostic-status <?php echo $pass ? 'is-pass' : 'is-fail'; ?>">
					<?php echo $pass ? esc_html__( 'Pass', 'draftfence-ai-review' ) : esc_html__( 'Needs attention', 'draftfence-ai-review' ); ?>
				</span>
			</td>
			<td><?php echo esc_html( $row['help'] ); ?></td>
			<td class="draftfence-ai-review-diagnostic-action">
				<?php if ( $url ) : ?>
					<a class="button <?php echo $pass ? 'button-secondary' : 'button-primary'; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $link_target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php echo esc_html( $pass ? __( 'View', 'draftfence-ai-review' ) : $action_label ); ?>
					</a>
				<?php else : ?>
					<span class="draftfence-ai-review-muted"><?php esc_html_e( 'No action', 'draftfence-ai-review' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private static function pretty_json( $value ) {
		return wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	private static function render_proposal_field( $label, $value ) {
		if ( null === $value || '' === $value ) {
			return;
		}

		?>
		<p><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo esc_html( $value ); ?></p>
		<?php
	}

	private static function render_proposal_diff( $item, $post ) {
		if ( ! $post ) {
			return;
		}

		$fields = array(
			'title'           => array( __( 'Title', 'draftfence-ai-review' ), $item['original_title'] ),
			'excerpt'         => array( __( 'Excerpt', 'draftfence-ai-review' ), $item['original_excerpt'] ),
			'seo_title'       => array( __( 'SEO Title', 'draftfence-ai-review' ), $item['original_seo_title'] ),
			'seo_description' => array( __( 'SEO Description', 'draftfence-ai-review' ), $item['original_seo_description'] ),
		);

		?>
		<div class="draftfence-ai-review-diff-grid">
			<?php foreach ( $fields as $key => $field ) : ?>
				<?php if ( null === $item[ $key ] || '' === $item[ $key ] ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="draftfence-ai-review-diff">
					<h3><?php echo esc_html( $field[0] ); ?></h3>
					<div>
						<strong><?php esc_html_e( 'Current', 'draftfence-ai-review' ); ?></strong>
						<p><?php echo esc_html( '' !== $field[1] ? $field[1] : __( 'Empty', 'draftfence-ai-review' ) ); ?></p>
					</div>
					<div>
						<strong><?php esc_html_e( 'Proposed', 'draftfence-ai-review' ); ?></strong>
						<p><?php echo esc_html( $item[ $key ] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function get_first_meta( $post_id, $keys ) {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	private static function csv_cell( $value ) {
		$value = (string) $value;

		if ( preg_match( '/^[=\-+@]/', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	private static function save_identities() {
		$settings = DraftFence_AI_Review::get_settings();
		$profiles = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in handle_actions() before this helper is called; each row value is sanitized below.
		$raw      = isset( $_POST['profiles'] ) && is_array( $_POST['profiles'] ) ? wp_unslash( $_POST['profiles'] ) : array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$user_id = absint( $row['user_id'] ?? 0 );
			$user    = $user_id ? get_user_by( 'id', $user_id ) : false;

			if ( ! $user ) {
				continue;
			}

			$enabled = ! empty( $row['enabled'] );

			if ( $enabled ) {
				$user->add_cap( 'draftfence_ai_review_use_ai' );
			} else {
				$user->remove_cap( 'draftfence_ai_review_use_ai' );
			}

			$profiles[ $user_id ] = array(
				'label'         => sanitize_text_field( $row['label'] ?? $user->display_name ),
				'actions'       => isset( $row['actions'] ) && is_array( $row['actions'] ) ? array_map( 'sanitize_key', $row['actions'] ) : array(),
				'post_types'    => isset( $row['post_types'] ) && is_array( $row['post_types'] ) ? array_map( 'sanitize_key', $row['post_types'] ) : array(),
				'max_risk'      => sanitize_key( $row['max_risk'] ?? 'high' ),
				'content_limit' => absint( $row['content_limit'] ?? 200000 ),
				'enabled'       => $enabled ? 1 : 0,
			);
		}

		$settings['agent_profiles'] = $profiles;
		DraftFence_AI_Review::update_settings( $settings );
	}

	private static function save_onboarding() {
		$settings = DraftFence_AI_Review::get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_actions() before this helper is called.
		$user_id  = absint( $_POST['onboarding_user_id'] ?? 0 );
		$user     = $user_id ? get_user_by( 'id', $user_id ) : false;

		if ( $user ) {
			$user->add_cap( 'draftfence_ai_review_use_ai' );

			$settings['agent_profiles'][ $user_id ] = array(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_actions() before this helper is called.
				'label'         => isset( $_POST['onboarding_label'] ) ? sanitize_text_field( wp_unslash( $_POST['onboarding_label'] ) ) : $user->display_name,
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_actions() before this helper is called.
				'actions'       => isset( $_POST['onboarding_actions'] ) && is_array( $_POST['onboarding_actions'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['onboarding_actions'] ) ) : array( 'read' ),
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_actions() before this helper is called.
				'post_types'    => isset( $_POST['onboarding_post_types'] ) && is_array( $_POST['onboarding_post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['onboarding_post_types'] ) ) : $settings['allowed_post_types'],
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_actions() before this helper is called.
				'max_risk'      => isset( $_POST['onboarding_max_risk'] ) ? sanitize_key( wp_unslash( $_POST['onboarding_max_risk'] ) ) : 'medium',
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_actions() before this helper is called.
				'content_limit' => isset( $_POST['onboarding_content_limit'] ) ? absint( $_POST['onboarding_content_limit'] ) : 200000,
				'enabled'       => 1,
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_actions() before this helper is called.
		if ( isset( $_POST['onboarding_notify_emails'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_actions() before this helper is called.
			$settings['notify_emails'] = sanitize_text_field( wp_unslash( $_POST['onboarding_notify_emails'] ) );
		}

		$settings['onboarding_complete'] = 1;
		DraftFence_AI_Review::update_settings( $settings );
	}

	private static function export_logs_csv() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Export nonce is verified before this helper is called.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Export nonce is verified before this helper is called.
		$action = isset( $_GET['log_action'] ) ? sanitize_key( wp_unslash( $_GET['log_action'] ) ) : '';
		$logs   = DraftFence_AI_Review_Audit_Log::query(
			array(
				'limit'  => 500,
				'status' => $status,
				'action' => $action,
			)
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=draftfence-ai-review-audit-log.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'created_at', 'actor', 'user_id', 'action', 'status', 'object_type', 'object_id', 'message', 'ip_address' ) );

		foreach ( $logs as $log ) {
			fputcsv(
				$output,
				array(
					$log->created_at,
					self::csv_cell( $log->actor ),
					$log->user_id,
					self::csv_cell( $log->action ),
					self::csv_cell( $log->status ),
					self::csv_cell( $log->object_type ),
					$log->object_id,
					self::csv_cell( $log->message ),
					self::csv_cell( $log->ip_address ),
				)
			);
		}

		exit;
	}
}
