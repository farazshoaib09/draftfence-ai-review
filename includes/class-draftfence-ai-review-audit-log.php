<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DraftFence_AI_Review_Audit_Log {
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'draftfence_ai_review_logs';
	}

	public static function add( $action, $status, $message = '', $context = array() ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$object  = isset( $context['object'] ) && is_array( $context['object'] ) ? $context['object'] : array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- DraftFence AI Review writes audit events to its own custom table.
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'  => current_time( 'mysql' ),
				'user_id'     => $user_id ? $user_id : null,
				'actor'       => DraftFence_AI_Review::current_actor(),
				'action'      => sanitize_key( $action ),
				'object_type' => sanitize_key( $object['type'] ?? '' ),
				'object_id'   => isset( $object['id'] ) ? absint( $object['id'] ) : null,
				'status'      => sanitize_key( $status ),
				'message'     => sanitize_text_field( $message ),
				'context'     => wp_json_encode( $context ),
				'ip_address'  => self::get_ip_address(),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	public static function recent( $limit = 50 ) {
		return self::query( array( 'limit' => $limit ) );
	}

	public static function query( $args = array() ) {
		global $wpdb;

		$limit  = max( 1, min( 500, absint( $args['limit'] ?? 50 ) ) );
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$values[] = sanitize_key( $args['action'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		$table    = esc_sql( self::table_name() );
		$sql      = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d';
		$values[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DraftFence AI Review audit logs are stored in a custom table.
		return $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and an escaped table name; all values are prepared.
			$wpdb->prepare( $sql, $values )
		);
	}

	public static function count_since( $action, $user_id, $since ) {
		global $wpdb;

		$table = esc_sql( self::table_name() );

		return absint(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Used for DraftFence AI Review custom-table rate limiting.
			$wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; values are prepared.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; values are prepared.
					"SELECT COUNT(*) FROM {$table} WHERE action = %s AND user_id = %d AND created_at >= %s",
					sanitize_key( $action ),
					absint( $user_id ),
					$since
				)
			)
		);
	}

	public static function prune() {
		global $wpdb;

		$settings = DraftFence_AI_Review::get_settings();
		$days     = max( 7, absint( $settings['audit_retention_days'] ) );
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$logs_table     = esc_sql( self::table_name() );
		$requests_table = esc_sql( DraftFence_AI_Review_Actions::requests_table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled pruning for DraftFence AI Review custom audit table.
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; value is prepared.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; value is prepared.
				"DELETE FROM {$logs_table} WHERE created_at < %s",
				get_date_from_gmt( $cutoff )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled pruning for DraftFence AI Review custom proposal table.
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; values are prepared.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; values are prepared.
				"DELETE FROM {$requests_table} WHERE status != %s AND created_at < %s",
				'pending',
				get_date_from_gmt( $cutoff )
			)
		);
	}

	private static function get_ip_address() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
}
