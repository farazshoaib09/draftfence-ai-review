<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DraftFence_AI_Review_Actions {
	const MAX_TITLE_LENGTH           = 200;
	const MAX_EXCERPT_LENGTH         = 5000;
	const MAX_REASON_LENGTH          = 2000;
	const MAX_SEO_TITLE_LENGTH       = 200;
	const MAX_SEO_DESCRIPTION_LENGTH = 500;

	public static function requests_table() {
		global $wpdb;
		return $wpdb->prefix . 'draftfence_ai_review_requests';
	}

	public static function status() {
		$settings = DraftFence_AI_Review::get_settings();

		return array(
			'enabled'              => (bool) $settings['enabled'],
			'allowed_post_types'   => $settings['allowed_post_types'],
			'read_content'         => (bool) $settings['allow_read_content'],
			'create_drafts'        => (bool) $settings['allow_create_drafts'],
			'propose_edits'        => (bool) $settings['allow_propose_edits'],
			'seo_fields'           => (bool) $settings['allow_seo_fields'],
			'approval_required'    => (bool) $settings['require_approval_for_edits'],
			'rate_limit_per_hour'  => absint( $settings['rate_limit_per_hour'] ),
			'audit_retention_days' => absint( $settings['audit_retention_days'] ),
			'current_agent_profile' => DraftFence_AI_Review::current_user_can_use_ai() ? DraftFence_AI_Review::get_current_agent_profile() : null,
			'wordpress_abilities'  => function_exists( 'wp_register_ability' ),
			'draftfence-ai-review_version'   => DRAFTFENCE_AI_REVIEW_VERSION,
		);
	}

	public static function list_content( $input = array() ) {
		if ( ! self::can_read_content() ) {
			DraftFence_AI_Review_Audit_Log::add( 'list-content', 'blocked', 'Content read blocked by DraftFence AI Review settings.' );
			return new WP_Error( 'draftfence-ai-review_read_blocked', __( 'DraftFence AI Review is not allowing content reads.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$rate_limit = self::check_rate_limit( 'list-content' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$settings  = DraftFence_AI_Review::get_settings();
		$post_type = sanitize_key( $input['post_type'] ?? 'post' );

		if ( ! DraftFence_AI_Review::is_allowed_post_type( $post_type ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'list-content', 'blocked', 'Post type is not allowed.', array( 'post_type' => $post_type ) );
			return new WP_Error( 'draftfence-ai-review_post_type_blocked', __( 'This post type is not allowed for AI access.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		if ( ! DraftFence_AI_Review::profile_allows_post_type( $post_type ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'list-content', 'blocked', 'Agent profile does not allow this post type.', array( 'post_type' => $post_type ) );
			return new WP_Error( 'draftfence-ai-review_profile_post_type_blocked', __( 'Your DraftFence AI Review identity is not allowed to access this post type.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : $settings['max_list_results'];
		$limit = max( 1, min( $settings['max_list_results'], $limit ) );

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => $limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$query = new WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$items[] = self::format_post_summary( $post );
		}

		DraftFence_AI_Review_Audit_Log::add( 'list-content', 'allowed', 'AI content list request allowed.', array( 'post_type' => $post_type, 'count' => count( $items ) ) );

		return array(
			'items' => $items,
			'count' => count( $items ),
		);
	}

	public static function create_draft( $input = array() ) {
		if ( ! self::can_create_drafts() ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-draft', 'blocked', 'Draft creation blocked by DraftFence AI Review settings.' );
			return new WP_Error( 'draftfence-ai-review_draft_blocked', __( 'DraftFence AI Review is not allowing AI draft creation.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$post_type = sanitize_key( $input['post_type'] ?? 'post' );

		if ( ! DraftFence_AI_Review::is_allowed_post_type( $post_type ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-draft', 'blocked', 'Post type is not allowed.', array( 'post_type' => $post_type ) );
			return new WP_Error( 'draftfence-ai-review_post_type_blocked', __( 'This post type is not allowed for AI access.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		if ( ! DraftFence_AI_Review::profile_allows_post_type( $post_type ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-draft', 'blocked', 'Agent profile does not allow this post type.', array( 'post_type' => $post_type ) );
			return new WP_Error( 'draftfence-ai-review_profile_post_type_blocked', __( 'Your DraftFence AI Review identity is not allowed to create this post type.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'draftfence-ai-review_permission_denied', __( 'You do not have permission to create drafts.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		if ( ! self::current_user_can_create_post_type( $post_type ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-draft', 'blocked', 'User cannot create this post type.', array( 'post_type' => $post_type ) );
			return new WP_Error( 'draftfence-ai-review_post_type_permission_denied', __( 'You do not have permission to create drafts for this post type.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$rate_limit = self::check_rate_limit( 'create-draft' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$title = sanitize_text_field( $input['title'] ?? '' );

		if ( '' === $title ) {
			return new WP_Error( 'draftfence-ai-review_missing_title', __( 'Draft title is required.', 'draftfence-ai-review' ), array( 'status' => 400 ) );
		}

		$validation = self::validate_payload_size( $input );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => wp_kses_post( $input['content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-draft', 'failed', $post_id->get_error_message(), array( 'post_type' => $post_type ) );
			return $post_id;
		}

		self::maybe_update_seo_meta( $post_id, $input );

		DraftFence_AI_Review_Audit_Log::add(
			'create-draft',
			'allowed',
			'AI draft created.',
			array(
				'object' => array(
					'type' => $post_type,
					'id'   => $post_id,
				),
			)
		);

		return array(
			'post_id'   => $post_id,
			'edit_link' => get_edit_post_link( $post_id, 'raw' ),
			'status'    => 'draft',
		);
	}

	public static function create_proposal( $input = array() ) {
		global $wpdb;

		if ( ! self::can_propose_edits() ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-proposal', 'blocked', 'Edit proposal blocked by DraftFence AI Review settings.' );
			return new WP_Error( 'draftfence-ai-review_proposal_blocked', __( 'DraftFence AI Review is not allowing AI edit proposals.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new WP_Error( 'draftfence-ai-review_post_not_found', __( 'Post not found.', 'draftfence-ai-review' ), array( 'status' => 404 ) );
		}

		if ( ! DraftFence_AI_Review::is_allowed_post_type( $post->post_type ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-proposal', 'blocked', 'Post type is not allowed.', array( 'post_type' => $post->post_type ) );
			return new WP_Error( 'draftfence-ai-review_post_type_blocked', __( 'This post type is not allowed for AI access.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		if ( ! DraftFence_AI_Review::profile_allows_post_type( $post->post_type ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-proposal', 'blocked', 'Agent profile does not allow this post type.', array( 'post_type' => $post->post_type ) );
			return new WP_Error( 'draftfence-ai-review_profile_post_type_blocked', __( 'Your DraftFence AI Review identity is not allowed to propose edits for this post type.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'draftfence-ai-review_permission_denied', __( 'You do not have permission to propose edits for this content.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$rate_limit = self::check_rate_limit( 'create-proposal' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$validation = self::validate_payload_size( $input );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$risk = self::assess_proposal_risk(
			array(
				'post_id'         => $post_id,
				'title'           => array_key_exists( 'title', $input ) ? sanitize_text_field( $input['title'] ) : null,
				'content'         => array_key_exists( 'content', $input ) ? wp_kses_post( $input['content'] ) : null,
				'excerpt'         => array_key_exists( 'excerpt', $input ) ? sanitize_textarea_field( $input['excerpt'] ) : null,
				'seo_title'       => array_key_exists( 'seo_title', $input ) ? sanitize_text_field( $input['seo_title'] ) : null,
				'seo_description' => array_key_exists( 'seo_description', $input ) ? sanitize_textarea_field( $input['seo_description'] ) : null,
			),
			$post
		);

		if ( ! self::profile_allows_risk( $risk['level'] ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-proposal', 'blocked', 'Proposal risk exceeds agent profile limit.', array( 'proposal_risk' => $risk['level'] ) );
			return new WP_Error( 'draftfence-ai-review_risk_blocked', __( 'This proposal exceeds the risk level allowed for your DraftFence AI Review identity.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$original_seo_title       = self::get_first_meta( $post_id, array( '_yoast_wpseo_title', 'rank_math_title', '_aioseo_title' ) );
		$original_seo_description = self::get_first_meta( $post_id, array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description' ) );

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- DraftFence AI Review stores proposals in its own custom table.
		$inserted = $wpdb->insert(
			self::requests_table(),
			array(
				'created_at'      => $now,
				'updated_at'      => $now,
				'created_by'      => get_current_user_id() ? get_current_user_id() : null,
				'post_id'         => $post_id,
				'post_type'       => $post->post_type,
				'action'          => 'propose_update',
				'status'          => 'pending',
				'title'           => array_key_exists( 'title', $input ) ? sanitize_text_field( $input['title'] ) : null,
				'content'         => array_key_exists( 'content', $input ) ? wp_kses_post( $input['content'] ) : null,
				'excerpt'         => array_key_exists( 'excerpt', $input ) ? sanitize_textarea_field( $input['excerpt'] ) : null,
				'seo_title'       => array_key_exists( 'seo_title', $input ) ? sanitize_text_field( $input['seo_title'] ) : null,
				'seo_description' => array_key_exists( 'seo_description', $input ) ? sanitize_textarea_field( $input['seo_description'] ) : null,
				'original_title'   => $post->post_title,
				'original_content' => $post->post_content,
				'original_excerpt' => $post->post_excerpt,
				'original_seo_title' => $original_seo_title,
				'original_seo_description' => $original_seo_description,
				'base_modified_gmt' => $post->post_modified_gmt,
				'reason'          => sanitize_textarea_field( $input['reason'] ?? '' ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			DraftFence_AI_Review_Audit_Log::add( 'create-proposal', 'failed', 'Proposal could not be stored.', array( 'post_type' => $post->post_type, 'post_id' => $post_id ) );
			return new WP_Error( 'draftfence-ai-review_proposal_insert_failed', __( 'The proposal could not be stored.', 'draftfence-ai-review' ), array( 'status' => 500 ) );
		}

		$request_id = absint( $wpdb->insert_id );

		DraftFence_AI_Review_Audit_Log::add(
			'create-proposal',
			'allowed',
			'AI edit proposal created.',
			array(
				'proposal_id' => $request_id,
				'object'      => array(
					'type' => $post->post_type,
					'id'   => $post_id,
				),
			)
		);

		self::send_proposal_notification( $request_id, $post );

		return array(
			'proposal_id' => $request_id,
			'status'      => 'pending',
		);
	}

	public static function list_proposals( $input = array() ) {
		global $wpdb;

		if ( ! DraftFence_AI_Review::current_user_can_review() && ! DraftFence_AI_Review::current_user_can_use_ai() ) {
			return new WP_Error( 'draftfence-ai-review_permission_denied', __( 'You do not have permission to list proposals.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$status = sanitize_key( $input['status'] ?? 'pending' );
		$limit  = max( 1, min( 100, absint( $input['limit'] ?? 50 ) ) );
		$table  = esc_sql( self::requests_table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DraftFence AI Review proposals are stored in a custom table.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; values are prepared.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; values are prepared.
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d",
				$status,
				$limit
			),
			ARRAY_A
		);

		$rows = array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					if ( DraftFence_AI_Review::current_user_can_review() ) {
						return current_user_can( 'edit_post', absint( $row['post_id'] ) );
					}

					return absint( $row['created_by'] ) === get_current_user_id() && current_user_can( 'edit_post', absint( $row['post_id'] ) );
				}
			)
		);

		return array(
			'items' => array_map( array( __CLASS__, 'format_proposal' ), $rows ),
			'count' => count( $rows ),
		);
	}

	public static function approve_proposal( $proposal_id, $notes = '' ) {
		global $wpdb;

		if ( ! DraftFence_AI_Review::current_user_can_review() ) {
			return new WP_Error( 'draftfence-ai-review_permission_denied', __( 'Only administrators can approve DraftFence AI Review proposals.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$proposal = self::get_proposal( $proposal_id );

		if ( ! $proposal ) {
			return new WP_Error( 'draftfence-ai-review_proposal_not_found', __( 'Proposal not found.', 'draftfence-ai-review' ), array( 'status' => 404 ) );
		}

		if ( 'pending' !== $proposal['status'] ) {
			return new WP_Error( 'draftfence-ai-review_proposal_not_pending', __( 'Only pending proposals can be approved.', 'draftfence-ai-review' ), array( 'status' => 409 ) );
		}

		if ( absint( $proposal['created_by'] ) && absint( $proposal['created_by'] ) === get_current_user_id() ) {
			DraftFence_AI_Review_Audit_Log::add( 'approve-proposal', 'blocked', 'Proposal creator cannot approve their own proposal.', array( 'proposal_id' => $proposal_id ) );
			return new WP_Error( 'draftfence-ai-review_self_approval_blocked', __( 'DraftFence AI Review requires a different human reviewer to approve this proposal.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$post = get_post( absint( $proposal['post_id'] ) );

		if ( ! $post ) {
			return new WP_Error( 'draftfence-ai-review_post_not_found', __( 'The target post no longer exists.', 'draftfence-ai-review' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'draftfence-ai-review_permission_denied', __( 'You do not have permission to edit the target post.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		if ( self::proposal_has_conflict( $proposal, $post ) ) {
			DraftFence_AI_Review_Audit_Log::add( 'approve-proposal', 'blocked', 'Post changed after proposal was created.', array( 'proposal_id' => $proposal_id ) );
			return new WP_Error( 'draftfence-ai-review_proposal_conflict', __( 'This post changed after the proposal was created. Review the latest content before approving.', 'draftfence-ai-review' ), array( 'status' => 409 ) );
		}

		$update = array( 'ID' => $post->ID );

		foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' ) as $proposal_key => $post_key ) {
			if ( null !== $proposal[ $proposal_key ] && '' !== $proposal[ $proposal_key ] ) {
				$update[ $post_key ] = $proposal[ $proposal_key ];
			}
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );

			if ( is_wp_error( $result ) ) {
				DraftFence_AI_Review_Audit_Log::add( 'approve-proposal', 'failed', $result->get_error_message(), array( 'proposal_id' => $proposal_id ) );
				return $result;
			}
		}

		self::maybe_update_seo_meta(
			$post->ID,
			array(
				'seo_title'       => $proposal['seo_title'],
				'seo_description' => $proposal['seo_description'],
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DraftFence AI Review updates proposal status in its own custom table.
		$updated = $wpdb->update(
			self::requests_table(),
			array(
				'status'      => 'approved',
				'updated_at'  => current_time( 'mysql' ),
				'approved_by' => get_current_user_id(),
				'applied_at'  => current_time( 'mysql' ),
				'notes'       => sanitize_textarea_field( $notes ),
			),
			array( 'id' => absint( $proposal_id ) ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			DraftFence_AI_Review_Audit_Log::add( 'approve-proposal', 'failed', 'Proposal status could not be updated after applying changes.', array( 'proposal_id' => $proposal_id ) );
			return new WP_Error( 'draftfence-ai-review_approval_status_failed', __( 'The post was updated, but DraftFence AI Review could not mark the proposal approved.', 'draftfence-ai-review' ), array( 'status' => 500 ) );
		}

		DraftFence_AI_Review_Audit_Log::add(
			'approve-proposal',
			'allowed',
			'DraftFence AI Review proposal approved and applied.',
			array(
				'proposal_id' => absint( $proposal_id ),
				'object'      => array(
					'type' => $post->post_type,
					'id'   => $post->ID,
				),
			)
		);

		return array(
			'proposal_id' => absint( $proposal_id ),
			'post_id'     => $post->ID,
			'status'      => 'approved',
		);
	}

	public static function reject_proposal( $proposal_id, $notes = '' ) {
		global $wpdb;

		if ( ! DraftFence_AI_Review::current_user_can_review() ) {
			return new WP_Error( 'draftfence-ai-review_permission_denied', __( 'Only administrators can reject DraftFence AI Review proposals.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$proposal = self::get_proposal( $proposal_id );

		if ( ! $proposal ) {
			return new WP_Error( 'draftfence-ai-review_proposal_not_found', __( 'Proposal not found.', 'draftfence-ai-review' ), array( 'status' => 404 ) );
		}

		if ( 'pending' !== $proposal['status'] ) {
			return new WP_Error( 'draftfence-ai-review_proposal_not_pending', __( 'Only pending proposals can be rejected.', 'draftfence-ai-review' ), array( 'status' => 409 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DraftFence AI Review updates proposal status in its own custom table.
		$updated = $wpdb->update(
			self::requests_table(),
			array(
				'status'     => 'rejected',
				'updated_at' => current_time( 'mysql' ),
				'notes'      => sanitize_textarea_field( $notes ),
			),
			array( 'id' => absint( $proposal_id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			DraftFence_AI_Review_Audit_Log::add( 'reject-proposal', 'failed', 'Proposal status could not be updated.', array( 'proposal_id' => $proposal_id ) );
			return new WP_Error( 'draftfence-ai-review_reject_status_failed', __( 'DraftFence AI Review could not mark the proposal rejected.', 'draftfence-ai-review' ), array( 'status' => 500 ) );
		}

		DraftFence_AI_Review_Audit_Log::add( 'reject-proposal', 'allowed', 'DraftFence AI Review proposal rejected.', array( 'proposal_id' => absint( $proposal_id ) ) );

		return array(
			'proposal_id' => absint( $proposal_id ),
			'status'      => 'rejected',
		);
	}

	public static function rollback_proposal( $proposal_id, $notes = '' ) {
		global $wpdb;

		if ( ! DraftFence_AI_Review::current_user_can_review() ) {
			return new WP_Error( 'draftfence-ai-review_permission_denied', __( 'Only administrators can roll back DraftFence AI Review proposals.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$proposal = self::get_proposal( $proposal_id );

		if ( ! $proposal ) {
			return new WP_Error( 'draftfence-ai-review_proposal_not_found', __( 'Proposal not found.', 'draftfence-ai-review' ), array( 'status' => 404 ) );
		}

		if ( 'approved' !== $proposal['status'] ) {
			return new WP_Error( 'draftfence-ai-review_proposal_not_approved', __( 'Only approved proposals can be rolled back.', 'draftfence-ai-review' ), array( 'status' => 409 ) );
		}

		if ( ! empty( $proposal['rolled_back_at'] ) ) {
			return new WP_Error( 'draftfence-ai-review_proposal_already_rolled_back', __( 'This proposal has already been rolled back.', 'draftfence-ai-review' ), array( 'status' => 409 ) );
		}

		$post = get_post( absint( $proposal['post_id'] ) );

		if ( ! $post ) {
			return new WP_Error( 'draftfence-ai-review_post_not_found', __( 'The target post no longer exists.', 'draftfence-ai-review' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'draftfence-ai-review_permission_denied', __( 'You do not have permission to edit the target post.', 'draftfence-ai-review' ), array( 'status' => 403 ) );
		}

		$restore = array( 'ID' => $post->ID );

		foreach ( array( 'original_title' => 'post_title', 'original_content' => 'post_content', 'original_excerpt' => 'post_excerpt' ) as $proposal_key => $post_key ) {
			if ( array_key_exists( $proposal_key, $proposal ) && null !== $proposal[ $proposal_key ] ) {
				$restore[ $post_key ] = $proposal[ $proposal_key ];
			}
		}

		if ( count( $restore ) > 1 ) {
			$result = wp_update_post( $restore, true );

			if ( is_wp_error( $result ) ) {
				DraftFence_AI_Review_Audit_Log::add( 'rollback-proposal', 'failed', $result->get_error_message(), array( 'proposal_id' => $proposal_id ) );
				return $result;
			}
		}

		self::restore_seo_meta( $post->ID, $proposal );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DraftFence AI Review updates proposal rollback state in its own custom table.
		$updated = $wpdb->update(
			self::requests_table(),
			array(
				'status'         => 'rolled_back',
				'updated_at'     => current_time( 'mysql' ),
				'rolled_back_by' => get_current_user_id(),
				'rolled_back_at' => current_time( 'mysql' ),
				'notes'          => sanitize_textarea_field( $notes ),
			),
			array( 'id' => absint( $proposal_id ) ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			DraftFence_AI_Review_Audit_Log::add( 'rollback-proposal', 'failed', 'Proposal status could not be updated after rollback.', array( 'proposal_id' => $proposal_id ) );
			return new WP_Error( 'draftfence-ai-review_rollback_status_failed', __( 'The post was restored, but DraftFence AI Review could not mark the proposal rolled back.', 'draftfence-ai-review' ), array( 'status' => 500 ) );
		}

		DraftFence_AI_Review_Audit_Log::add(
			'rollback-proposal',
			'allowed',
			'DraftFence AI Review proposal rolled back.',
			array(
				'proposal_id' => absint( $proposal_id ),
				'object'      => array(
					'type' => $post->post_type,
					'id'   => $post->ID,
				),
			)
		);

		return array(
			'proposal_id' => absint( $proposal_id ),
			'post_id'     => $post->ID,
			'status'      => 'rolled_back',
		);
	}

	public static function get_proposal( $proposal_id ) {
		global $wpdb;

		$table = esc_sql( self::requests_table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DraftFence AI Review proposals are stored in a custom table.
		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; value is prepared.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped above; value is prepared.
				"SELECT * FROM {$table} WHERE id = %d",
				absint( $proposal_id )
			),
			ARRAY_A
		);
	}

	private static function can_read_content() {
		return DraftFence_AI_Review::feature_enabled( 'allow_read_content' ) && DraftFence_AI_Review::current_user_can_use_ai() && DraftFence_AI_Review::profile_allows_action( 'read' ) && current_user_can( 'edit_posts' );
	}

	private static function can_create_drafts() {
		return DraftFence_AI_Review::feature_enabled( 'allow_create_drafts' ) && DraftFence_AI_Review::current_user_can_use_ai() && DraftFence_AI_Review::profile_allows_action( 'draft' ) && current_user_can( 'edit_posts' );
	}

	private static function can_propose_edits() {
		return DraftFence_AI_Review::feature_enabled( 'allow_propose_edits' ) && DraftFence_AI_Review::current_user_can_use_ai() && DraftFence_AI_Review::profile_allows_action( 'proposal' ) && current_user_can( 'edit_posts' );
	}

	private static function current_user_can_create_post_type( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );

		if ( ! $post_type_object || empty( $post_type_object->cap->create_posts ) ) {
			return false;
		}

		return current_user_can( $post_type_object->cap->create_posts );
	}

	private static function check_rate_limit( $action ) {
		$settings = DraftFence_AI_Review::get_settings();
		$limit    = absint( $settings['rate_limit_per_hour'] );
		$user_id  = get_current_user_id();

		if ( ! $user_id || ! $limit ) {
			return true;
		}

		$since = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		$count = DraftFence_AI_Review_Audit_Log::count_since( $action, $user_id, get_date_from_gmt( $since ) );

		if ( $count >= $limit ) {
			DraftFence_AI_Review_Audit_Log::add( $action, 'blocked', 'Rate limit exceeded.', array( 'limit_per_hour' => $limit ) );
			return new WP_Error( 'draftfence-ai-review_rate_limited', __( 'DraftFence AI Review rate limit exceeded. Please try again later.', 'draftfence-ai-review' ), array( 'status' => 429 ) );
		}

		return true;
	}

	private static function format_post_summary( $post ) {
		$post = get_post( $post );

		return array(
			'id'              => $post->ID,
			'type'            => $post->post_type,
			'title'           => get_the_title( $post ),
			'status'          => $post->post_status,
			'modified'        => get_post_modified_time( DATE_ATOM, false, $post ),
			'edit_link'       => get_edit_post_link( $post->ID, 'raw' ),
			'permalink'       => get_permalink( $post ),
			'excerpt'         => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'seo_title'       => self::get_first_meta( $post->ID, array( '_yoast_wpseo_title', 'rank_math_title', '_aioseo_title' ) ),
			'seo_description' => self::get_first_meta( $post->ID, array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description' ) ),
		);
	}

	public static function format_proposal( $proposal ) {
		$post = ! empty( $proposal['post_id'] ) ? get_post( absint( $proposal['post_id'] ) ) : null;

		return array(
			'id'              => absint( $proposal['id'] ),
			'created_at'      => $proposal['created_at'],
			'updated_at'      => $proposal['updated_at'],
			'post_id'         => absint( $proposal['post_id'] ),
			'post_type'       => $proposal['post_type'],
			'post_title'      => $post ? get_the_title( $post ) : '',
			'status'          => $proposal['status'],
			'title'           => $proposal['title'],
			'content'         => $proposal['content'],
			'excerpt'         => $proposal['excerpt'],
			'seo_title'       => $proposal['seo_title'],
			'seo_description' => $proposal['seo_description'],
			'original_title'   => $proposal['original_title'] ?? '',
			'original_content' => $proposal['original_content'] ?? '',
			'original_excerpt' => $proposal['original_excerpt'] ?? '',
			'original_seo_title' => $proposal['original_seo_title'] ?? '',
			'original_seo_description' => $proposal['original_seo_description'] ?? '',
			'base_modified_gmt' => $proposal['base_modified_gmt'] ?? '',
			'created_by'       => absint( $proposal['created_by'] ?? 0 ),
			'approved_by'      => absint( $proposal['approved_by'] ?? 0 ),
			'applied_at'       => $proposal['applied_at'] ?? '',
			'rolled_back_by'   => absint( $proposal['rolled_back_by'] ?? 0 ),
			'rolled_back_at'   => $proposal['rolled_back_at'] ?? '',
			'has_conflict'     => self::proposal_has_conflict( $proposal, $post ),
			'reason'          => $proposal['reason'],
			'notes'           => $proposal['notes'],
			'risk'            => self::assess_proposal_risk( $proposal, $post ),
		);
	}

	public static function assess_proposal_risk( $proposal, $post = null ) {
		$post    = $post ? get_post( $post ) : get_post( absint( $proposal['post_id'] ?? 0 ) );
		$risk    = 'low';
		$reasons = array();
		$changed = 0;

		foreach ( array( 'title', 'content', 'excerpt', 'seo_title', 'seo_description' ) as $field ) {
			if ( isset( $proposal[ $field ] ) && null !== $proposal[ $field ] && '' !== $proposal[ $field ] ) {
				$changed++;
			}
		}

		if ( $changed >= 3 ) {
			$risk      = 'medium';
			$reasons[] = __( 'Multiple fields will change.', 'draftfence-ai-review' );
		}

		if ( $post && 'publish' === $post->post_status && ! empty( $proposal['content'] ) ) {
			$risk      = 'medium';
			$reasons[] = __( 'Published content body will be updated.', 'draftfence-ai-review' );
		}

		if ( $post && ! empty( $proposal['content'] ) ) {
			$current_length  = max( 1, strlen( wp_strip_all_tags( $post->post_content ) ) );
			$proposed_length = strlen( wp_strip_all_tags( $proposal['content'] ) );
			$delta           = abs( $proposed_length - $current_length ) / $current_length;

			if ( $delta > 0.4 ) {
				$risk      = 'high';
				$reasons[] = __( 'Content length changes by more than 40%.', 'draftfence-ai-review' );
			}
		}

		if ( ! empty( $proposal['seo_title'] ) && strlen( $proposal['seo_title'] ) > 70 ) {
			if ( 'low' === $risk ) {
				$risk = 'medium';
			}
			$reasons[] = __( 'SEO title is longer than the usual search-result range.', 'draftfence-ai-review' );
		}

		if ( ! empty( $proposal['seo_description'] ) && strlen( $proposal['seo_description'] ) > 170 ) {
			if ( 'low' === $risk ) {
				$risk = 'medium';
			}
			$reasons[] = __( 'SEO description is longer than the usual search-result range.', 'draftfence-ai-review' );
		}

		if ( empty( $reasons ) ) {
			$reasons[] = __( 'Small, reviewable change set.', 'draftfence-ai-review' );
		}

		return array(
			'level'   => $risk,
			'reasons' => $reasons,
		);
	}

	private static function validate_payload_size( $input ) {
		$profile       = DraftFence_AI_Review::get_current_agent_profile();
		$content_limit = max( 1000, absint( $profile['content_limit'] ?? 200000 ) );
		$limits        = array(
			'title'           => self::MAX_TITLE_LENGTH,
			'content'         => $content_limit,
			'excerpt'         => self::MAX_EXCERPT_LENGTH,
			'reason'          => self::MAX_REASON_LENGTH,
			'seo_title'       => self::MAX_SEO_TITLE_LENGTH,
			'seo_description' => self::MAX_SEO_DESCRIPTION_LENGTH,
		);

		foreach ( $limits as $field => $limit ) {
			if ( isset( $input[ $field ] ) && strlen( (string) $input[ $field ] ) > $limit ) {
				DraftFence_AI_Review_Audit_Log::add( 'payload-validation', 'blocked', 'Payload field exceeded DraftFence AI Review size limit.', array( 'field' => $field, 'limit' => $limit ) );
				/* translators: 1: payload field name, 2: maximum allowed character count. */
				return new WP_Error( 'draftfence-ai-review_payload_too_large', sprintf( __( '%1$s is longer than the DraftFence AI Review limit of %2$d characters.', 'draftfence-ai-review' ), $field, $limit ), array( 'status' => 413 ) );
			}
		}

		return true;
	}

	private static function profile_allows_risk( $risk ) {
		$profile = DraftFence_AI_Review::get_current_agent_profile();
		$order   = array(
			'low'    => 1,
			'medium' => 2,
			'high'   => 3,
		);

		$max_risk = sanitize_key( $profile['max_risk'] ?? 'high' );

		return ( $order[ sanitize_key( $risk ) ] ?? 3 ) <= ( $order[ $max_risk ] ?? 3 );
	}

	private static function proposal_has_conflict( $proposal, $post = null ) {
		$post = $post ? get_post( $post ) : get_post( absint( $proposal['post_id'] ?? 0 ) );

		if ( ! $post || empty( $proposal['base_modified_gmt'] ) ) {
			return false;
		}

		return $post->post_modified_gmt !== $proposal['base_modified_gmt'];
	}

	private static function send_proposal_notification( $proposal_id, $post ) {
		$settings = DraftFence_AI_Review::get_settings();

		if ( empty( $settings['notify_emails'] ) ) {
			return;
		}

		$emails = array_filter( array_map( 'sanitize_email', explode( ',', $settings['notify_emails'] ) ), 'is_email' );

		if ( empty( $emails ) ) {
			return;
		}

		/* translators: 1: site name, 2: DraftFence AI Review proposal ID. */
		$subject = sprintf( __( '[%1$s] DraftFence AI Review proposal #%2$d needs review', 'draftfence-ai-review' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), absint( $proposal_id ) );
		$message = implode(
			"\n",
			array(
				__( 'An AI identity submitted an DraftFence AI Review proposal.', 'draftfence-ai-review' ),
				'',
				/* translators: %d: DraftFence AI Review proposal ID. */
				sprintf( __( 'Proposal: #%d', 'draftfence-ai-review' ), absint( $proposal_id ) ),
				/* translators: %s: WordPress post title. */
				sprintf( __( 'Content: %s', 'draftfence-ai-review' ), get_the_title( $post ) ),
				/* translators: %d: WordPress post ID. */
				sprintf( __( 'Post ID: %d', 'draftfence-ai-review' ), absint( $post->ID ) ),
				/* translators: %s: proposal review URL. */
				sprintf( __( 'Reviewer: %s', 'draftfence-ai-review' ), admin_url( 'admin.php?page=draftfence-ai-review-proposals' ) ),
			)
		);

		wp_mail( $emails, $subject, $message );
	}

	private static function restore_seo_meta( $post_id, $proposal ) {
		if ( ! DraftFence_AI_Review::feature_enabled( 'allow_seo_fields' ) ) {
			return;
		}

		if ( array_key_exists( 'original_seo_title', $proposal ) ) {
			$value = sanitize_text_field( $proposal['original_seo_title'] );
			update_post_meta( $post_id, '_yoast_wpseo_title', $value );
			update_post_meta( $post_id, 'rank_math_title', $value );
			update_post_meta( $post_id, '_aioseo_title', $value );
		}

		if ( array_key_exists( 'original_seo_description', $proposal ) ) {
			$value = sanitize_textarea_field( $proposal['original_seo_description'] );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $value );
			update_post_meta( $post_id, 'rank_math_description', $value );
			update_post_meta( $post_id, '_aioseo_description', $value );
		}
	}

	private static function maybe_update_seo_meta( $post_id, $input ) {
		if ( ! DraftFence_AI_Review::feature_enabled( 'allow_seo_fields' ) ) {
			return;
		}

		if ( isset( $input['seo_title'] ) && '' !== $input['seo_title'] ) {
			$value = sanitize_text_field( $input['seo_title'] );
			update_post_meta( $post_id, '_yoast_wpseo_title', $value );
			update_post_meta( $post_id, 'rank_math_title', $value );
			update_post_meta( $post_id, '_aioseo_title', $value );
		}

		if ( isset( $input['seo_description'] ) && '' !== $input['seo_description'] ) {
			$value = sanitize_textarea_field( $input['seo_description'] );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $value );
			update_post_meta( $post_id, 'rank_math_description', $value );
			update_post_meta( $post_id, '_aioseo_description', $value );
		}
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
}
