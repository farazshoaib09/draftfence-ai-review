<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DraftFence_AI_Review_Abilities {
	public static function register_categories() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'draftfence-ai-review-content-seo',
			array(
				'label'       => __( 'DraftFence AI Review Content & SEO', 'draftfence-ai-review' ),
				'description' => __( 'Safe WordPress content and SEO operations controlled by DraftFence AI Review.', 'draftfence-ai-review' ),
			)
		);
	}

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'draftfence-ai-review/status',
			array(
				'label'               => __( 'Get DraftFence AI Review Status', 'draftfence-ai-review' ),
				'description'         => __( 'Returns enabled DraftFence AI Review permissions and supported AI-safe actions.', 'draftfence-ai-review' ),
				'category'            => 'draftfence-ai-review-content-seo',
				'execute_callback'    => array( __CLASS__, 'status' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'output_schema'       => self::status_output_schema(),
				'meta'                => array(
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		wp_register_ability(
			'draftfence-ai-review/list-content',
			array(
				'label'               => __( 'List Allowed Content', 'draftfence-ai-review' ),
				'description'         => __( 'Lists posts, pages, or allowed public post types available to the authenticated AI identity.', 'draftfence-ai-review' ),
				'category'            => 'draftfence-ai-review-content-seo',
				'execute_callback'    => array( __CLASS__, 'list_content' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'input_schema'        => self::list_content_input_schema(),
				'output_schema'       => self::list_content_output_schema(),
				'meta'                => array(
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		wp_register_ability(
			'draftfence-ai-review/create-draft',
			array(
				'label'               => __( 'Create Draft Content', 'draftfence-ai-review' ),
				'description'         => __( 'Creates a draft post/page only. It never publishes content.', 'draftfence-ai-review' ),
				'category'            => 'draftfence-ai-review-content-seo',
				'execute_callback'    => array( __CLASS__, 'create_draft' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'input_schema'        => self::draft_input_schema(),
				'output_schema'       => self::draft_output_schema(),
				'meta'                => array(
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);

		wp_register_ability(
			'draftfence-ai-review/propose-content-seo-update',
			array(
				'label'               => __( 'Propose Content/SEO Update', 'draftfence-ai-review' ),
				'description'         => __( 'Creates a pending proposal for a human admin to approve before applying content or SEO changes.', 'draftfence-ai-review' ),
				'category'            => 'draftfence-ai-review-content-seo',
				'execute_callback'    => array( __CLASS__, 'create_proposal' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'input_schema'        => self::proposal_input_schema(),
				'output_schema'       => self::proposal_output_schema(),
				'meta'                => array(
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);

		wp_register_ability(
			'draftfence-ai-review/list-pending-proposals',
			array(
				'label'               => __( 'List Pending DraftFence AI Review Proposals', 'draftfence-ai-review' ),
				'description'         => __( 'Lists pending AI proposals waiting for review.', 'draftfence-ai-review' ),
				'category'            => 'draftfence-ai-review-content-seo',
				'execute_callback'    => array( __CLASS__, 'list_proposals' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'input_schema'        => self::proposals_input_schema(),
				'output_schema'       => self::proposals_output_schema(),
				'meta'                => array(
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		wp_register_ability(
			'draftfence-ai-review/approve-proposal',
			array(
				'label'               => __( 'Approve DraftFence AI Review Proposal', 'draftfence-ai-review' ),
				'description'         => __( 'Approves and applies a pending DraftFence AI Review proposal when no content conflict exists.', 'draftfence-ai-review' ),
				'category'            => 'draftfence-ai-review-content-seo',
				'execute_callback'    => array( __CLASS__, 'approve_proposal' ),
				'permission_callback' => array( __CLASS__, 'can_review_proposals' ),
				'input_schema'        => self::proposal_review_input_schema(),
				'output_schema'       => self::proposal_output_schema(),
				'meta'                => array(
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);

		wp_register_ability(
			'draftfence-ai-review/reject-proposal',
			array(
				'label'               => __( 'Reject DraftFence AI Review Proposal', 'draftfence-ai-review' ),
				'description'         => __( 'Rejects a pending DraftFence AI Review proposal without applying changes.', 'draftfence-ai-review' ),
				'category'            => 'draftfence-ai-review-content-seo',
				'execute_callback'    => array( __CLASS__, 'reject_proposal' ),
				'permission_callback' => array( __CLASS__, 'can_review_proposals' ),
				'input_schema'        => self::proposal_review_input_schema(),
				'output_schema'       => self::proposal_output_schema(),
				'meta'                => array(
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);

		wp_register_ability(
			'draftfence-ai-review/rollback-approved-proposal',
			array(
				'label'               => __( 'Rollback Approved DraftFence AI Review Proposal', 'draftfence-ai-review' ),
				'description'         => __( 'Restores original content and SEO values captured before an approved proposal was applied.', 'draftfence-ai-review' ),
				'category'            => 'draftfence-ai-review-content-seo',
				'execute_callback'    => array( __CLASS__, 'rollback_proposal' ),
				'permission_callback' => array( __CLASS__, 'can_review_proposals' ),
				'input_schema'        => self::proposal_review_input_schema(),
				'output_schema'       => self::proposal_output_schema(),
				'meta'                => array(
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	public static function status() {
		return DraftFence_AI_Review_Actions::status();
	}

	public static function list_content( $input = array() ) {
		return DraftFence_AI_Review_Actions::list_content( is_array( $input ) ? $input : array() );
	}

	public static function create_draft( $input = array() ) {
		return DraftFence_AI_Review_Actions::create_draft( is_array( $input ) ? $input : array() );
	}

	public static function create_proposal( $input = array() ) {
		return DraftFence_AI_Review_Actions::create_proposal( is_array( $input ) ? $input : array() );
	}

	public static function list_proposals( $input = array() ) {
		return DraftFence_AI_Review_Actions::list_proposals( is_array( $input ) ? $input : array() );
	}

	public static function rollback_proposal( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return DraftFence_AI_Review_Actions::rollback_proposal( absint( $input['proposal_id'] ?? 0 ), $input['notes'] ?? '' );
	}

	public static function approve_proposal( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return DraftFence_AI_Review_Actions::approve_proposal( absint( $input['proposal_id'] ?? 0 ), $input['notes'] ?? '' );
	}

	public static function reject_proposal( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return DraftFence_AI_Review_Actions::reject_proposal( absint( $input['proposal_id'] ?? 0 ), $input['notes'] ?? '' );
	}

	public static function can_edit_posts() {
		return DraftFence_AI_Review::current_user_can_use_ai() && current_user_can( 'edit_posts' );
	}

	public static function can_review_proposals() {
		return DraftFence_AI_Review::current_user_can_review();
	}

	private static function status_output_schema() {
		return array(
			'type'        => 'object',
			'description' => __( 'DraftFence AI Review status and policy settings.', 'draftfence-ai-review' ),
			'properties'  => array(
				'enabled'              => array( 'type' => 'boolean' ),
				'allowed_post_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'read_content'         => array( 'type' => 'boolean' ),
				'create_drafts'        => array( 'type' => 'boolean' ),
				'propose_edits'        => array( 'type' => 'boolean' ),
				'seo_fields'           => array( 'type' => 'boolean' ),
				'approval_required'    => array( 'type' => 'boolean' ),
				'rate_limit_per_hour'  => array( 'type' => 'integer' ),
				'audit_retention_days' => array( 'type' => 'integer' ),
				'wordpress_abilities'  => array( 'type' => 'boolean' ),
				'draftfence-ai-review_version'   => array( 'type' => 'string' ),
			),
		);
	}

	private static function list_content_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array( 'type' => 'string', 'default' => 'post' ),
				'search'    => array( 'type' => 'string' ),
				'limit'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
			),
		);
	}

	private static function list_content_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'count' => array( 'type' => 'integer' ),
			),
		);
	}

	private static function draft_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'           => array( 'type' => 'string', 'minLength' => 1 ),
				'content'         => array( 'type' => 'string' ),
				'excerpt'         => array( 'type' => 'string' ),
				'post_type'       => array( 'type' => 'string', 'default' => 'post' ),
				'seo_title'       => array( 'type' => 'string' ),
				'seo_description' => array( 'type' => 'string' ),
			),
		);
	}

	private static function draft_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'   => array( 'type' => 'integer' ),
				'edit_link' => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
			),
		);
	}

	private static function proposal_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id'         => array( 'type' => 'integer', 'minimum' => 1 ),
				'title'           => array( 'type' => 'string' ),
				'content'         => array( 'type' => 'string' ),
				'excerpt'         => array( 'type' => 'string' ),
				'seo_title'       => array( 'type' => 'string' ),
				'seo_description' => array( 'type' => 'string' ),
				'reason'          => array( 'type' => 'string' ),
			),
		);
	}

	private static function proposal_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'proposal_id' => array( 'type' => 'integer' ),
				'status'      => array( 'type' => 'string' ),
			),
		);
	}

	private static function proposals_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status' => array( 'type' => 'string', 'default' => 'pending' ),
				'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
			),
		);
	}

	private static function proposals_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'count' => array( 'type' => 'integer' ),
			),
		);
	}

	private static function proposal_review_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'proposal_id' ),
			'properties' => array(
				'proposal_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'notes'       => array( 'type' => 'string' ),
			),
		);
	}
}
