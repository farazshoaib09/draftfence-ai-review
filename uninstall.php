<?php
/**
 * Remove DraftFence AI Review data when the plugin is uninstalled.
 *
 * @package DraftFence AI Review
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'draftfence_ai_review_settings' );
delete_option( 'draftfence_ai_review_db_version' );
wp_clear_scheduled_hook( 'draftfence_ai_review_daily_maintenance' );

$roles = wp_roles();

if ( $roles ) {
	foreach ( array_keys( $roles->roles ) as $role_name ) {
		$role = get_role( $role_name );

		if ( ! $role ) {
			continue;
		}

		foreach ( array( 'draftfence_ai_review_use_ai', 'draftfence_ai_review_review_proposals', 'draftfence_ai_review_manage_settings', 'draftfence_ai_review_view_logs' ) as $capability ) {
			$role->remove_cap( $capability );
		}
	}
}

$user_ids = get_users(
	array(
		'fields'     => 'ids',
		'capability' => 'draftfence_ai_review_use_ai',
	)
);

foreach ( $user_ids as $user_id ) {
	$user = get_user_by( 'id', absint( $user_id ) );

	if ( $user ) {
		$user->remove_cap( 'draftfence_ai_review_use_ai' );
	}
}

$logs_table     = esc_sql( $wpdb->prefix . 'draftfence_ai_review_logs' );
$requests_table = esc_sql( $wpdb->prefix . 'draftfence_ai_review_requests' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup removes DraftFence AI Review's own escaped custom table.
$wpdb->query( "DROP TABLE IF EXISTS {$logs_table}" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup removes DraftFence AI Review's own escaped custom table.
$wpdb->query( "DROP TABLE IF EXISTS {$requests_table}" );
