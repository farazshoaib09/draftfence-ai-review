<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DraftFence_AI_Review_Activator {
	const OPTION_DB_VERSION = 'draftfence_ai_review_db_version';
	const DB_VERSION        = '0.3.0';

	public static function activate() {
		self::create_tables();
		self::add_capabilities();
		self::schedule_maintenance();

		if ( false === get_option( DraftFence_AI_Review::OPTION_SETTINGS, false ) ) {
			add_option( DraftFence_AI_Review::OPTION_SETTINGS, DraftFence_AI_Review::default_settings() );
		}

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( self::DB_VERSION === get_option( self::OPTION_DB_VERSION ) ) {
			return;
		}

		self::create_tables();
		self::add_capabilities();
		self::schedule_maintenance();
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	private static function add_capabilities() {
		$admin = get_role( 'administrator' );

		if ( $admin ) {
			$admin->remove_cap( 'draftfence_ai_review_use_ai' );

			foreach ( array( 'draftfence_ai_review_review_proposals', 'draftfence_ai_review_manage_settings', 'draftfence_ai_review_view_logs' ) as $capability ) {
				$admin->add_cap( $capability );
			}
		}

		$editor = get_role( 'editor' );

		if ( $editor ) {
			$editor->remove_cap( 'draftfence_ai_review_use_ai' );
		}
	}

	private static function schedule_maintenance() {
		if ( ! wp_next_scheduled( 'draftfence_ai_review_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'draftfence_ai_review_daily_maintenance' );
		}
	}

	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$logs_table      = $wpdb->prefix . 'draftfence_ai_review_logs';
		$requests_table  = $wpdb->prefix . 'draftfence_ai_review_requests';

		$sql_logs = "CREATE TABLE $logs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NULL,
			actor varchar(191) NOT NULL DEFAULT '',
			action varchar(191) NOT NULL DEFAULT '',
			object_type varchar(50) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NULL,
			status varchar(30) NOT NULL DEFAULT '',
			message text NULL,
			context longtext NULL,
			ip_address varchar(100) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY action (action),
			KEY status (status),
			KEY object_id (object_id)
		) $charset_collate;";

		$sql_requests = "CREATE TABLE $requests_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			created_by bigint(20) unsigned NULL,
			post_id bigint(20) unsigned NULL,
			post_type varchar(50) NOT NULL DEFAULT 'post',
			action varchar(50) NOT NULL DEFAULT 'propose_update',
			status varchar(30) NOT NULL DEFAULT 'pending',
			title text NULL,
			content longtext NULL,
			excerpt text NULL,
			seo_title text NULL,
			seo_description text NULL,
			original_title text NULL,
			original_content longtext NULL,
			original_excerpt text NULL,
			original_seo_title text NULL,
			original_seo_description text NULL,
			base_modified_gmt datetime NULL,
			reason text NULL,
			approved_by bigint(20) unsigned NULL,
			applied_at datetime NULL,
			rolled_back_by bigint(20) unsigned NULL,
			rolled_back_at datetime NULL,
			notes text NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status),
			KEY post_id (post_id),
			KEY post_type (post_type)
		) $charset_collate;";

		dbDelta( $sql_logs );
		dbDelta( $sql_requests );
	}
}
