<?php
/**
 * Plugin Name: DraftFence AI Review
 * Description: Govern AI agent access to WordPress with safe draft creation, approval workflows, scoped SEO changes, rate limits, and audit logs.
 * Version: 0.3.3
 * Author: Faraz Shoaib
 * Text Domain: draftfence-ai-review
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DRAFTFENCE_AI_REVIEW_VERSION', '0.3.3' );
define( 'DRAFTFENCE_AI_REVIEW_FILE', __FILE__ );
define( 'DRAFTFENCE_AI_REVIEW_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRAFTFENCE_AI_REVIEW_URL', plugin_dir_url( __FILE__ ) );

require_once DRAFTFENCE_AI_REVIEW_DIR . 'includes/class-draftfence-ai-review-activator.php';
require_once DRAFTFENCE_AI_REVIEW_DIR . 'includes/class-draftfence-ai-review.php';

register_activation_hook( __FILE__, array( 'DraftFence_AI_Review_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, 'draftfence_ai_review_deactivate' );

function draftfence_ai_review_deactivate() {
	wp_clear_scheduled_hook( 'draftfence_ai_review_daily_maintenance' );
}

function draftfence_ai_review_run() {
	$plugin = new DraftFence_AI_Review();
	$plugin->run();
}

draftfence_ai_review_run();
