=== DraftFence AI Review ===
Contributors: farazshoaib
Tags: ai, mcp, abilities, seo, permissions
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Govern AI agent access to WordPress with approvals, scoped content actions, rate limits, and audit logs.

== Description ==

DraftFence AI Review is a governance layer for AI agents connected to WordPress. It gives authenticated AI identities a narrow, reviewable set of capabilities: list allowed content summaries, create draft-only content, and submit content or SEO changes as proposals for human review.

DraftFence AI Review is designed for site owners, agencies, SEO teams, and editorial teams who want AI help without handing an assistant unrestricted publishing power.

== Key Features ==

* Emergency switch to disable DraftFence AI Review AI operations immediately.
* First-run onboarding wizard for guided setup.
* Protected MCP setup audit that shows abilities marked public to the default MCP Adapter, flags other exposed tools, and links to exposure settings.
* AI identity warning when a configured AI account can publish or edit others' posts through its WordPress role.
* Draft-only content creation for posts, pages, and selected REST-enabled public post types.
* Human approval workflow for edits to existing content.
* Before/after proposal review for titles, excerpts, and SEO fields.
* Full content comparison using the snapshot captured when the proposal was created.
* Risk badges that flag larger or more sensitive proposed changes.
* AI identity profiles for per-user action, post type, risk, and payload limits.
* Separate AI and human reviewer capabilities.
* Conflict detection that blocks approval when content changed after proposal creation.
* One-click rollback for approved proposals.
* Email notifications for pending proposal review.
* SEO title and description support for Yoast SEO, Rank Math, and AIOSEO-style meta keys.
* Per-user hourly rate limits for AI operations.
* Audit logs for allowed, blocked, failed, approved, and rejected actions.
* Filterable audit screen with CSV export.
* Configurable audit retention with daily pruning.
* WordPress Abilities API registration when available.
* REST API endpoints under /wp-json/draftfence-ai-review/v1/.

== Why DraftFence AI Review? ==

Many WordPress AI plugins focus on generating content or exposing a broad MCP bridge. DraftFence AI Review focuses on governance: clear boundaries, human approval, operational limits, and reviewable logs. It can sit beside your preferred AI client, MCP adapter, or SEO plugin as the policy layer for content and SEO changes.

== REST Endpoints ==

All endpoints require an authenticated WordPress user with the right capability. Application Passwords are recommended for AI client identities.

* GET /wp-json/draftfence-ai-review/v1/status
* GET /wp-json/draftfence-ai-review/v1/content?post_type=post&limit=20
* POST /wp-json/draftfence-ai-review/v1/drafts
* GET /wp-json/draftfence-ai-review/v1/proposals
* POST /wp-json/draftfence-ai-review/v1/proposals
* POST /wp-json/draftfence-ai-review/v1/proposals/{id}/approve
* POST /wp-json/draftfence-ai-review/v1/proposals/{id}/reject
* POST /wp-json/draftfence-ai-review/v1/proposals/{id}/rollback

== WordPress Abilities ==

When the WordPress Abilities API is available, DraftFence AI Review registers:

* draftfence-ai-review/status
* draftfence-ai-review/list-content
* draftfence-ai-review/create-draft
* draftfence-ai-review/propose-content-seo-update
* draftfence-ai-review/list-pending-proposals
* draftfence-ai-review/approve-proposal
* draftfence-ai-review/reject-proposal
* draftfence-ai-review/rollback-approved-proposal

== Installation ==

1. Upload the draftfence-ai-review folder to /wp-content/plugins/.
2. Activate DraftFence AI Review from the WordPress Plugins screen.
3. Open DraftFence AI Review > Settings.
4. Choose allowed post types, rate limits, retention, and permitted AI actions.
5. Create a dedicated WordPress user for your AI client and authenticate with an Application Password.
6. Open DraftFence AI Review > AI Identities and assign that user an DraftFence AI Review profile.
7. Or use DraftFence AI Review > Onboarding to complete the guided first-run setup.

== Frequently Asked Questions ==

= Does DraftFence AI Review publish AI-generated content? =

No. DraftFence AI Review creates new content as drafts. Existing content changes are stored as pending proposals until an administrator approves them.

= Can an AI approve its own changes? =

No. DraftFence AI Review separates AI identities from reviewer accounts. An AI identity can request work, but a different reviewer account must approve it.

= Can I roll back an approved AI change? =

Yes. DraftFence AI Review stores the original content and SEO values with each proposal and can restore them from the proposal review screen.

= Does DraftFence AI Review require an external AI service? =

No. DraftFence AI Review does not call an external AI provider. It controls what authenticated AI clients are allowed to do inside WordPress.

= Does DraftFence AI Review control other MCP tools or direct WordPress REST calls? =

No. DraftFence AI Review governs its own routes and abilities. The Connect & Test audit helps identify other abilities exposed by the default WordPress MCP Adapter, but does not inspect custom MCP servers, client-side tools, or direct REST access. Use a dedicated non-administrator account and restrict abilities in your MCP exposure plugin.

= Which SEO plugins are supported? =

DraftFence AI Review writes SEO title and description values to common Yoast SEO, Rank Math, and AIOSEO meta keys.

= Can I turn off AI access quickly? =

Yes. The emergency switch blocks DraftFence AI Review REST and Ability operations while leaving the admin review screens available to administrators.

= Does DraftFence AI Review replace a full activity log plugin? =

No. DraftFence AI Review logs its own AI-related operations. For full-site activity monitoring, use a dedicated activity log plugin alongside DraftFence AI Review.

== Screenshots ==

1. DraftFence AI Review dashboard with protection status, allowed content types, pending proposals, and Abilities API status.
2. Proposal review screen with risk badge, review notes, and before/after fields.
3. Settings screen for AI action permissions, post type scope, rate limits, and audit retention.
4. Audit log screen with filters and CSV export.
5. AI identity profile screen with per-user scopes and risk limits.
6. Onboarding wizard for first-run setup.

== Changelog ==

= 0.4.0 =
* Added a protected MCP setup audit based on abilities actually marked public to the default MCP Adapter, rather than all registered abilities.
* Listed other public MCP abilities and flagged write-like names for manual review, with a link to the exposure settings.
* Added a broad WordPress write-privilege warning for configured AI identities and a step-by-step verification checklist.
* Clarified the limits of DraftFence protection across other MCP servers and direct WordPress REST access.

= 0.3.3 =
* Added WordPress.org marketplace banner and icon assets.
* Simplified the Connect & Test screen around endpoint details, diagnostics, client manuals, test prompts, and safe ability exposure.
* Removed local-machine path guessing from the connection UI.
* Enforced explicit AI identity profiles for DraftFence AI Review AI access.
* Added plugin header license metadata for WordPress.org review.

= 0.3.0 =
* Added guided onboarding wizard for first-run setup.
* Added DraftFence AI Review-specific capabilities for AI identities, reviewers, managers, and log viewers.
* Added AI identity profiles with allowed actions, post types, risk ceilings, and content payload limits.
* Added conflict detection to block stale approvals when content changed after proposal creation.
* Added rollback for approved proposals using stored original content and SEO snapshots.
* Added proposal notification emails.
* Added full content before/after review snapshots.
* Hardened CSV export against spreadsheet formula injection.
* Stopped trusting spoofable forwarded IP headers by default.
* Added retention pruning for old non-pending proposals.

= 0.2.0 =
* Added proposal risk badges and before/after review UI.
* Added per-user hourly rate limits for DraftFence AI Review operations.
* Added audit log filtering, CSV export, and retention pruning.
* Hardened custom post type draft permissions.
* Filtered proposal visibility by post editing permissions.
* Added database insert failure handling for proposals.
* Improved WordPress Abilities API schemas.

= 0.1.0 =
* Initial MVP for content and SEO AI safety controls.
