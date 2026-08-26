<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DraftFence_AI_Review_REST {
	const NAMESPACE = 'draftfence-ai-review/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => array( __CLASS__, 'can_read_status' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_content' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'args'                => array(
					'post_type' => array(
						'type'              => 'string',
						'default'           => 'post',
						'sanitize_callback' => 'sanitize_key',
					),
					'search'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'     => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/drafts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_draft' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_proposals' ),
					'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_proposal' ),
					'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<id>\d+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'approve_proposal' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<id>\d+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'reject_proposal' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<id>\d+)/rollback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rollback_proposal' ),
				'permission_callback' => array( __CLASS__, 'can_review_proposals' ),
			)
		);
	}

	public static function status() {
		return rest_ensure_response( DraftFence_AI_Review_Actions::status() );
	}

	public static function list_content( WP_REST_Request $request ) {
		return rest_ensure_response( DraftFence_AI_Review_Actions::list_content( $request->get_params() ) );
	}

	public static function create_draft( WP_REST_Request $request ) {
		return rest_ensure_response( DraftFence_AI_Review_Actions::create_draft( self::json_params( $request ) ) );
	}

	public static function list_proposals( WP_REST_Request $request ) {
		return rest_ensure_response( DraftFence_AI_Review_Actions::list_proposals( $request->get_params() ) );
	}

	public static function create_proposal( WP_REST_Request $request ) {
		return rest_ensure_response( DraftFence_AI_Review_Actions::create_proposal( self::json_params( $request ) ) );
	}

	public static function approve_proposal( WP_REST_Request $request ) {
		$params = self::json_params( $request );
		return rest_ensure_response( DraftFence_AI_Review_Actions::approve_proposal( absint( $request['id'] ), $params['notes'] ?? '' ) );
	}

	public static function reject_proposal( WP_REST_Request $request ) {
		$params = self::json_params( $request );
		return rest_ensure_response( DraftFence_AI_Review_Actions::reject_proposal( absint( $request['id'] ), $params['notes'] ?? '' ) );
	}

	public static function rollback_proposal( WP_REST_Request $request ) {
		$params = self::json_params( $request );
		return rest_ensure_response( DraftFence_AI_Review_Actions::rollback_proposal( absint( $request['id'] ), $params['notes'] ?? '' ) );
	}

	public static function can_read_status() {
		return is_user_logged_in() && ( DraftFence_AI_Review::current_user_can_use_ai() || DraftFence_AI_Review::current_user_can_review() );
	}

	public static function can_edit_posts() {
		return DraftFence_AI_Review::current_user_can_use_ai() && current_user_can( 'edit_posts' );
	}

	public static function can_manage_options() {
		return self::can_review_proposals();
	}

	public static function can_review_proposals() {
		return DraftFence_AI_Review::current_user_can_review();
	}

	private static function json_params( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		return is_array( $params ) ? $params : array();
	}
}
