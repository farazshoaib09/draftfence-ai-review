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

$draftfence_ai_review_roles = wp_roles();

if ( $draftfence_ai_review_roles ) {
	foreach ( array_keys( $draftfence_ai_review_roles->roles ) as $draftfence_ai_review_role_name ) {
		$draftfence_ai_review_role = get_role( $draftfence_ai_review_role_name );

		if ( ! $draftfence_ai_review_role ) {
			continue;
		}

		foreach ( array( 'draftfence_ai_review_use_ai', 'draftfence_ai_review_review_proposals', 'draftfence_ai_review_manage_settings', 'draftfence_ai_review_view_logs' ) as $draftfence_ai_review_capability ) {
			$draftfence_ai_review_role->remove_cap( $draftfence_ai_review_capability );
		}
	}
}

$draftfence_ai_review_user_ids = get_users(
	array(
		'fields'     => 'ids',
		'capability' => 'draftfence_ai_review_use_ai',
	)
);

foreach ( $draftfence_ai_review_user_ids as $draftfence_ai_review_user_id ) {
	$draftfence_ai_review_user = get_user_by( 'id', absint( $draftfence_ai_review_user_id ) );

	if ( $draftfence_ai_review_user ) {
		$draftfence_ai_review_user->remove_cap( 'draftfence_ai_review_use_ai' );
	}
}

$draftfence_ai_review_logs_table     = esc_sql( $wpdb->prefix . 'draftfence_ai_review_logs' );
$draftfence_ai_review_requests_table = esc_sql( $wpdb->prefix . 'draftfence_ai_review_requests' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup removes DraftFence AI Review's own escaped custom table.
$wpdb->query( "DROP TABLE IF EXISTS {$draftfence_ai_review_logs_table}" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup removes DraftFence AI Review's own escaped custom table.
$wpdb->query( "DROP TABLE IF EXISTS {$draftfence_ai_review_requests_table}" );
