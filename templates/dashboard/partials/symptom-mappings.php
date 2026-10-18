<?php
/**
 * Template: "Symptom mappings" — the admin dashboard section and the
 * clinical reviewers' tab on the doctor dashboard (same screen; what each
 * person can do comes from their permissions, enforced on the server by
 * Symptom_Mappings_Handler).
 *
 * The lists and dialogs are drawn by doctor-ak-symptom-mappings-admin.js
 * from the localized state.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $page_url Public page URL, or ''.
 * @var bool   $is_admin Current user is an administrator.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-list-page dak-sm" data-dak-sm>
	<div class="dak-page-head">
		<div>
			<h1><?php esc_html_e( 'Symptom mappings', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Link symptoms, and the words patients use for them, to the specialties that see them, with optional follow-up questions and safety messages written by clinicians. Patients use these on “Find a doctor by symptoms” to find doctors — nothing here diagnoses or scores risk.', 'doctor-ak-portal' ); ?></p>
		</div>
		<?php if ( '' !== $page_url ) : ?>
			<a class="dak-button dak-button-secondary" href="<?php echo esc_url( add_query_arg( 'sc_preview', '1', $page_url ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview page', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</div>

	<p class="dak-sm-flash" role="status" aria-live="polite" data-sm-flash hidden></p>

	<section class="dak-dashboard-card dak-sm-card" aria-labelledby="dak-sm-status-title">
		<h2 id="dak-sm-status-title"><?php esc_html_e( 'Page status', 'doctor-ak-portal' ); ?></h2>
		<div data-sm-status></div>
	</section>

	<section class="dak-dashboard-card dak-sm-card" aria-labelledby="dak-sm-notice-title">
		<h2 id="dak-sm-notice-title"><?php esc_html_e( 'Emergency notice', 'doctor-ak-portal' ); ?></h2>
		<p class="dak-sm-help"><?php esc_html_e( 'A general notice shown at the top of the page whatever the patient enters. It must be approved by a clinical reviewer. After an edit, patients keep seeing the approved wording until the new text is approved.', 'doctor-ak-portal' ); ?></p>
		<div data-sm-notice></div>
	</section>

	<section class="dak-dashboard-card dak-sm-card" aria-labelledby="dak-sm-list-title">
		<div class="dak-sm-card-head">
			<h2 id="dak-sm-list-title"><?php esc_html_e( 'Symptoms', 'doctor-ak-portal' ); ?></h2>
			<div class="dak-sm-toolbar" data-sm-toolbar></div>
		</div>
		<p class="dak-sm-help"><?php esc_html_e( 'Draft → Pending review → Reviewed → Active. Only the approved version of an active symptom is used on the page. Editing a live symptom creates a revision: patients keep seeing the approved version until the revision is approved and activated. Only clinical reviewers can approve and activate.', 'doctor-ak-portal' ); ?></p>
		<div data-sm-list></div>
	</section>

	<section class="dak-dashboard-card dak-sm-card" aria-labelledby="dak-sm-rules-title">
		<h2 id="dak-sm-rules-title"><?php esc_html_e( 'Safety rules', 'doctor-ak-portal' ); ?></h2>
		<p class="dak-sm-help"><?php esc_html_e( 'Clinician-written instructions shown immediately, above any doctor suggestions, when a patient chooses a symptom or gives a particular answer. Same review workflow as symptoms. A rule can only be activated once the symptoms and questions it uses are live. Rules never score or rule anything out.', 'doctor-ak-portal' ); ?></p>
		<div data-sm-rules></div>
	</section>

	<section class="dak-dashboard-card dak-sm-card" aria-labelledby="dak-sm-preview-title">
		<h2 id="dak-sm-preview-title"><?php esc_html_e( 'Try matching', 'doctor-ak-portal' ); ?></h2>
		<p class="dak-sm-help"><?php esc_html_e( 'Type what a patient might write to see what would be recognised, what would need a “did you mean”, and which specialties would be shown. Nothing is saved.', 'doctor-ak-portal' ); ?></p>
		<div data-sm-preview></div>
	</section>

	<?php if ( $is_admin ) : ?>
		<section class="dak-dashboard-card dak-sm-card" aria-labelledby="dak-sm-reviewers-title">
			<h2 id="dak-sm-reviewers-title"><?php esc_html_e( 'Clinical reviewers', 'doctor-ak-portal' ); ?></h2>
			<p class="dak-sm-help"><?php esc_html_e( 'Reviewers approve, return and activate symptoms, questions and safety rules, and approve the emergency notice. They don’t need administrator access — doctors you add here get a “Symptom mappings” tab on their dashboard. Choose only people qualified to review clinical content.', 'doctor-ak-portal' ); ?></p>
			<div data-sm-reviewers></div>
		</section>
	<?php endif; ?>

	<div class="dak-sm-legacy" data-sm-legacy hidden></div>
</div>

<div class="dak-portal dak-modal dak-sm-modal" id="dak-sm-dialog" aria-hidden="true">
	<div class="dak-modal-overlay" data-sm-close></div>
	<div class="dak-modal-dialog dak-sm-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-sm-dialog-title" tabindex="-1">
		<button type="button" class="dak-modal-close" data-sm-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
		<h2 id="dak-sm-dialog-title" data-sm-dialog-title></h2>
		<div data-sm-dialog-body></div>
	</div>
</div>
