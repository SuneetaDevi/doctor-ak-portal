<?php
/**
 * Template: CRM — the admin dashboard section and the doctor dashboard's
 * CRM tab (same screen; what each person can do comes from their role and
 * is enforced on the server by Crm_Handler).
 *
 * The table, filters, side panel and dialogs are drawn by
 * assets/js/doctor-ak-crm.js. The Emails tab (group emails and templates)
 * is for clinic staff only.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var bool $is_staff Administrator or receptionist (doctors see only their own patients).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-list-page dak-crm" data-dak-crm>
	<div class="dak-page-head">
		<div>
			<h1><?php esc_html_e( 'CRM', 'doctor-ak-portal' ); ?></h1>
			<p>
				<?php
				echo $is_staff
					? esc_html__( 'Everyone who has enquired, booked or visited — leads and patients in one list. Open a row for their history, notes and tasks.', 'doctor-ak-portal' )
					: esc_html__( 'Patients who have booked or visited you. Open a row for your visits with them, notes and follow-ups.', 'doctor-ak-portal' );
				?>
			</p>
		</div>
		<div class="dak-crm-head-actions" data-crm-head-actions></div>
	</div>

	<p class="dak-crm-flash" role="status" aria-live="polite" data-crm-flash hidden></p>
	<div class="dak-crm-import" role="status" aria-live="polite" data-crm-import hidden></div>

	<div class="dak-crm-switch" role="tablist" aria-label="<?php esc_attr_e( 'CRM sections', 'doctor-ak-portal' ); ?>">
		<button type="button" role="tab" id="dak-crm-tab-people" aria-controls="dak-crm-people" aria-selected="true" data-crm-switch="people"><?php esc_html_e( 'People', 'doctor-ak-portal' ); ?></button>
		<button type="button" role="tab" id="dak-crm-tab-tasks" aria-controls="dak-crm-tasks" aria-selected="false" tabindex="-1" data-crm-switch="tasks"><?php esc_html_e( 'Tasks', 'doctor-ak-portal' ); ?> <span class="dak-crm-count" data-crm-task-count hidden></span></button>
		<?php if ( $is_staff ) : ?>
			<button type="button" role="tab" id="dak-crm-tab-emails" aria-controls="dak-crm-emails" aria-selected="false" tabindex="-1" data-crm-switch="emails"><?php esc_html_e( 'Emails', 'doctor-ak-portal' ); ?></button>
		<?php endif; ?>
	</div>

	<section class="dak-dashboard-card dak-crm-card" id="dak-crm-people" role="tabpanel" aria-labelledby="dak-crm-tab-people" data-crm-people>
		<div class="dak-crm-views" role="tablist" aria-label="<?php esc_attr_e( 'Saved views', 'doctor-ak-portal' ); ?>" data-crm-views></div>
		<form class="dak-crm-filters" data-crm-filters role="search" aria-label="<?php esc_attr_e( 'Filter people', 'doctor-ak-portal' ); ?>"></form>
		<div class="dak-crm-bulk" data-crm-bulk hidden></div>
		<div class="dak-crm-summary-row">
			<p class="dak-crm-summary" aria-live="polite" data-crm-summary></p>
			<div class="dak-crm-summary-actions" data-crm-summary-actions></div>
		</div>
		<div class="dak-crm-table-wrap" data-crm-table></div>
		<nav class="dak-crm-pager" aria-label="<?php esc_attr_e( 'Pages', 'doctor-ak-portal' ); ?>" data-crm-pager></nav>
	</section>

	<section class="dak-dashboard-card dak-crm-card" id="dak-crm-tasks" role="tabpanel" aria-labelledby="dak-crm-tab-tasks" data-crm-tasks hidden></section>

	<?php if ( $is_staff ) : ?>
		<section class="dak-dashboard-card dak-crm-card" id="dak-crm-emails" role="tabpanel" aria-labelledby="dak-crm-tab-emails" data-crm-emails hidden></section>
	<?php endif; ?>
</div>

<div class="dak-portal dak-crm-drawer" data-crm-drawer hidden>
	<div class="dak-crm-drawer-backdrop" data-crm-close></div>
	<aside class="dak-crm-panel" role="dialog" aria-modal="true" aria-labelledby="dak-crm-panel-title" tabindex="-1">
		<button type="button" class="dak-crm-panel-close" data-crm-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
		<div data-crm-panel-body></div>
	</aside>
</div>

<div class="dak-portal dak-modal dak-crm-modal" id="dak-crm-dialog" aria-hidden="true">
	<div class="dak-modal-overlay" data-crm-dialog-close></div>
	<div class="dak-modal-dialog dak-crm-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-crm-dialog-title" tabindex="-1">
		<button type="button" class="dak-modal-close" data-crm-dialog-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
		<h2 id="dak-crm-dialog-title" data-crm-dialog-title></h2>
		<div data-crm-dialog-body></div>
	</div>
</div>
