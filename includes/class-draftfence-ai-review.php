<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DraftFence_AI_Review {
	const OPTION_SETTINGS = 'draftfence_ai_review_settings';

	public function run() {
		$this->load_dependencies();
		DraftFence_AI_Review_Activator::maybe_upgrade();

		add_action( 'admin_menu', array( 'DraftFence_AI_Review_Admin', 'register_menu' ) );
		add_action( 'admin_init', array( 'DraftFence_AI_Review_Admin', 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( 'DraftFence_AI_Review_Admin', 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( 'DraftFence_AI_Review_REST', 'register_routes' ) );
		add_action( 'draftfence_ai_review_daily_maintenance', array( 'DraftFence_AI_Review_Audit_Log', 'prune' ) );

		if ( function_exists( 'wp_register_ability' ) ) {
			add_action( 'wp_abilities_api_categories_init', array( 'DraftFence_AI_Review_Abilities', 'register_categories' ) );
			add_action( 'wp_abilities_api_init', array( 'DraftFence_AI_Review_Abilities', 'register_abilities' ) );
		}
	}

	private function load_dependencies() {
		require_once DRAFTFENCE_AI_REVIEW_DIR . 'includes/class-draftfence-ai-review-audit-log.php';
		require_once DRAFTFENCE_AI_REVIEW_DIR . 'includes/class-draftfence-ai-review-actions.php';
		require_once DRAFTFENCE_AI_REVIEW_DIR . 'includes/class-draftfence-ai-review-admin.php';
		require_once DRAFTFENCE_AI_REVIEW_DIR . 'includes/class-draftfence-ai-review-rest.php';
		require_once DRAFTFENCE_AI_REVIEW_DIR . 'includes/class-draftfence-ai-review-abilities.php';
	}

	public static function default_settings() {
		return array(
			'enabled'                    => 1,
			'allow_read_content'         => 1,
			'allow_create_drafts'        => 1,
			'allow_propose_edits'        => 1,
			'allow_seo_fields'           => 1,
			'require_approval_for_edits' => 1,
			'allowed_post_types'         => array( 'post', 'page' ),
			'max_list_results'           => 20,
			'rate_limit_per_hour'        => 60,
			'audit_retention_days'       => 90,
			'agent_profiles'             => array(),
			'notify_emails'              => get_option( 'admin_email' ),
			'onboarding_complete'        => 0,
		);
	}

	public static function get_settings() {
		$saved = get_option( self::OPTION_SETTINGS, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, self::default_settings() );

		if ( ! is_array( $settings['allowed_post_types'] ) ) {
			$settings['allowed_post_types'] = array( 'post', 'page' );
		}

		$settings['allowed_post_types'] = array_values(
			array_intersect(
				array_map( 'sanitize_key', $settings['allowed_post_types'] ),
				array_keys( self::get_supported_post_types() )
			)
		);

		if ( empty( $settings['allowed_post_types'] ) ) {
			$settings['allowed_post_types'] = array( 'post', 'page' );
		}

		$settings['max_list_results'] = max( 1, min( 100, absint( $settings['max_list_results'] ) ) );
		$settings['rate_limit_per_hour'] = max( 1, min( 500, absint( $settings['rate_limit_per_hour'] ) ) );
		$settings['audit_retention_days'] = max( 7, min( 3650, absint( $settings['audit_retention_days'] ) ) );
		$settings['agent_profiles'] = self::sanitize_agent_profiles( $settings['agent_profiles'] ?? array() );
		$settings['notify_emails'] = self::sanitize_notification_emails( $settings['notify_emails'] ?? '' );

		return $settings;
	}

	public static function update_settings( $settings ) {
		$defaults = self::default_settings();
		$clean    = array();

		foreach ( $defaults as $key => $value ) {
			if ( 'allowed_post_types' === $key ) {
				$selected                  = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : array();
				$clean['allowed_post_types'] = array_values(
					array_intersect(
						array_map( 'sanitize_key', $selected ),
						array_keys( self::get_supported_post_types() )
					)
				);
				continue;
			}

			if ( 'max_list_results' === $key ) {
				$clean[ $key ] = max( 1, min( 100, absint( $settings[ $key ] ?? $value ) ) );
				continue;
			}

			if ( 'rate_limit_per_hour' === $key ) {
				$clean[ $key ] = max( 1, min( 500, absint( $settings[ $key ] ?? $value ) ) );
				continue;
			}

			if ( 'audit_retention_days' === $key ) {
				$clean[ $key ] = max( 7, min( 3650, absint( $settings[ $key ] ?? $value ) ) );
				continue;
			}

			if ( 'agent_profiles' === $key ) {
				$clean[ $key ] = self::sanitize_agent_profiles( $settings[ $key ] ?? array() );
				continue;
			}

			if ( 'notify_emails' === $key ) {
				$clean[ $key ] = self::sanitize_notification_emails( $settings[ $key ] ?? $value );
				continue;
			}

			$clean[ $key ] = 'require_approval_for_edits' === $key ? 1 : ( ! empty( $settings[ $key ] ) ? 1 : 0 );
		}

		if ( empty( $clean['allowed_post_types'] ) ) {
			$clean['allowed_post_types'] = array( 'post', 'page' );
		}

		update_option( self::OPTION_SETTINGS, $clean );
	}

	public static function is_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] );
	}

	public static function feature_enabled( $feature ) {
		$settings = self::get_settings();
		return self::is_enabled() && ! empty( $settings[ $feature ] );
	}

	public static function get_supported_post_types() {
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'objects' );
		$supported  = array();

		foreach ( $post_types as $name => $object ) {
			if ( empty( $object->public ) && ! in_array( $name, array( 'post', 'page' ), true ) ) {
				continue;
			}

			$supported[ $name ] = $object->labels->singular_name ?? $name;
		}

		if ( empty( $supported['post'] ) ) {
			$supported['post'] = __( 'Post', 'draftfence-ai-review' );
		}

		if ( empty( $supported['page'] ) ) {
			$supported['page'] = __( 'Page', 'draftfence-ai-review' );
		}

		return $supported;
	}

	public static function is_allowed_post_type( $post_type ) {
		$settings = self::get_settings();
		return in_array( sanitize_key( $post_type ), $settings['allowed_post_types'], true );
	}

	public static function current_user_can_use_ai() {
		if ( ! self::is_enabled() || ! current_user_can( 'draftfence_ai_review_use_ai' ) ) {
			return false;
		}

		$profile = self::get_current_agent_profile();

		return ! empty( $profile['enabled'] );
	}

	public static function current_user_can_review() {
		return current_user_can( 'draftfence_ai_review_review_proposals' ) && ! current_user_can( 'draftfence_ai_review_use_ai' );
	}

	public static function current_user_can_manage() {
		return current_user_can( 'draftfence_ai_review_manage_settings' );
	}

	public static function current_user_can_view_logs() {
		return current_user_can( 'draftfence_ai_review_view_logs' );
	}

	public static function get_current_agent_profile() {
		$user_id  = get_current_user_id();
		$settings = self::get_settings();

		if ( $user_id && ! empty( $settings['agent_profiles'][ $user_id ] ) ) {
			return $settings['agent_profiles'][ $user_id ];
		}

		return array();
	}

	public static function profile_allows_action( $action ) {
		$profile = self::get_current_agent_profile();

		return ! empty( $profile['enabled'] ) && in_array( sanitize_key( $action ), $profile['actions'], true );
	}

	public static function profile_allows_post_type( $post_type ) {
		$profile = self::get_current_agent_profile();

		return ! empty( $profile['enabled'] ) && in_array( sanitize_key( $post_type ), $profile['post_types'], true );
	}

	public static function current_actor() {
		$user = wp_get_current_user();

		if ( $user && $user->exists() ) {
			return $user->user_login;
		}

		return 'anonymous';
	}

	private static function sanitize_agent_profiles( $profiles ) {
		if ( ! is_array( $profiles ) ) {
			return array();
		}

		$clean           = array();
		$allowed_actions = array( 'read', 'draft', 'proposal' );
		$risk_levels     = array( 'low', 'medium', 'high' );
		$post_types      = array_keys( self::get_supported_post_types() );

		foreach ( $profiles as $user_id => $profile ) {
			$user_id = absint( $user_id );

			if ( ! $user_id || ! is_array( $profile ) || ! get_user_by( 'id', $user_id ) ) {
				continue;
			}

			$actions = isset( $profile['actions'] ) && is_array( $profile['actions'] ) ? $profile['actions'] : array();
			$types   = isset( $profile['post_types'] ) && is_array( $profile['post_types'] ) ? $profile['post_types'] : array();

			$clean[ $user_id ] = array(
				'label'         => sanitize_text_field( $profile['label'] ?? '' ),
				'actions'       => array_values( array_intersect( array_map( 'sanitize_key', $actions ), $allowed_actions ) ),
				'post_types'    => array_values( array_intersect( array_map( 'sanitize_key', $types ), $post_types ) ),
				'max_risk'      => in_array( sanitize_key( $profile['max_risk'] ?? 'high' ), $risk_levels, true ) ? sanitize_key( $profile['max_risk'] ) : 'high',
				'content_limit' => max( 1000, min( 500000, absint( $profile['content_limit'] ?? 200000 ) ) ),
				'enabled'       => ! empty( $profile['enabled'] ) ? 1 : 0,
			);

			if ( empty( $clean[ $user_id ]['actions'] ) ) {
				$clean[ $user_id ]['actions'] = array( 'read' );
			}

			if ( empty( $clean[ $user_id ]['post_types'] ) ) {
				$clean[ $user_id ]['post_types'] = array( 'post' );
			}
		}

		return $clean;
	}

	private static function sanitize_notification_emails( $emails ) {
		$emails = is_array( $emails ) ? implode( ',', $emails ) : (string) $emails;
		$clean  = array();

		foreach ( preg_split( '/[\s,]+/', $emails ) as $email ) {
			$email = sanitize_email( $email );

			if ( is_email( $email ) ) {
				$clean[] = $email;
			}
		}

		return implode( ',', array_unique( $clean ) );
	}
}
