<?php
/**
 * Template: Encounter detail screen — reached by clicking "Check In" (then
 * "Open Encounter") on an appointment, on EITHER the doctor dashboard or the
 * admin dashboard (Admin_Dashboard's 'encounter' section renders this same
 * file verbatim — see its docblock). All content (patient/doctor/clinic
 * summary, Problems, Prescriptions, Bill, Reports, running total) is
 * fetched and rendered client-side (see assets/js/doctor-ak-encounter.js
 * and Encounter_Handler::handle_get_encounter()) so every add/delete can
 * re-render in place instead of a full page reload.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $encounter_id     The encounter to load, or 0 if it couldn't be found/isn't this account's.
 * @var string $appointments_url "Back to Appointments" link target.
 * @var bool   $is_closed        Whether this encounter is already closed — hides "Close encounter" up front (client-side render() also keeps this in sync after every fetch, but this avoids it flashing visible for a moment on a closed encounter's first load).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( $appointments_url ) : ?>
	<a class="dak-encounter-back-link" href="<?php echo esc_url( $appointments_url ); ?>">&larr; <?php esc_html_e( 'Back to Appointments', 'doctor-ak-portal' ); ?></a>
<?php endif; ?>

<?php if ( $encounter_id <= 0 ) : ?>
	<section class="dak-dashboard-card">
		<p class="dak-empty-state"><?php esc_html_e( "That encounter couldn't be found.", 'doctor-ak-portal' ); ?></p>
	</section>
<?php else : ?>
	<div id="dak-encounter-root" data-encounter-id="<?php echo esc_attr( $encounter_id ); ?>">
		<div class="dak-encounter-banner">
			<span class="dak-avatar dak-avatar-lg" id="dak-encounter-avatar" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3"/><path d="M3.5 17c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/></svg></span>
			<div class="dak-encounter-banner-info">
				<h1>
					<span id="dak-encounter-patient-name">&nbsp;</span>
					<span class="dak-status-pill dak-status-pill-outline" id="dak-encounter-status"></span>
				</h1>
				<p class="dak-field-hint" id="dak-encounter-meta">&nbsp;</p>
			</div>
			<div class="dak-encounter-banner-actions">
				<a class="dak-button dak-button-secondary dak-button-sm" id="dak-encounter-download-prescription" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Prescription PDF', 'doctor-ak-portal' ); ?></a>
				<a class="dak-button dak-button-secondary dak-button-sm" id="dak-encounter-download-bill" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Bill PDF', 'doctor-ak-portal' ); ?></a>
			</div>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-encounter-error" role="alert"></div>

		<div class="dak-encounter-grid">
			<div class="dak-encounter-main">

				<!-- Problems -->
				<section class="dak-dashboard-card dak-encounter-section">
					<div class="dak-encounter-section-header">
						<span class="dak-encounter-section-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3.5c-2 0-3.5 1.5-3.5 3.5 0 2 1.5 2.5 1.5 4.5v2h4v-2c0-2 1.5-2.5 1.5-4.5 0-2-1.5-3.5-3.5-3.5z"/><path d="M8.3 16.5h3.4"/></svg></span>
						<div class="dak-encounter-section-title">
							<h2><?php esc_html_e( 'Problems & diagnosis', 'doctor-ak-portal' ); ?></h2>
							<p><?php esc_html_e( 'What brought the patient in today', 'doctor-ak-portal' ); ?></p>
						</div>
						<span class="dak-encounter-section-count" id="dak-encounter-problems-count">0</span>
					</div>
					<div id="dak-encounter-problems-list"></div>
					<form id="dak-encounter-add-problem-form" class="dak-encounter-inline-form">
						<div class="dak-field">
							<label for="dak-encounter-problem-description"><?php esc_html_e( 'Problem / diagnosis', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-encounter-problem-description" placeholder="<?php esc_attr_e( 'e.g. Acute pharyngitis', 'doctor-ak-portal' ); ?>">
						</div>
						<div class="dak-field">
							<label for="dak-encounter-problem-notes"><?php esc_html_e( 'Notes (optional)', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-encounter-problem-notes" placeholder="<?php esc_attr_e( 'Context, onset, severity…', 'doctor-ak-portal' ); ?>">
						</div>
						<div class="dak-encounter-suggestion-chips" id="dak-encounter-problem-suggestions">
							<?php
							foreach ( array( __( 'Fever', 'doctor-ak-portal' ), __( 'Abdominal pain', 'doctor-ak-portal' ), __( 'GERD', 'doctor-ak-portal' ), __( 'Constipation', 'doctor-ak-portal' ) ) as $dak_suggestion ) :
								?>
								<button type="button" class="dak-encounter-suggestion-chip" data-suggest-problem="<?php echo esc_attr( $dak_suggestion ); ?>"><?php echo esc_html( $dak_suggestion ); ?></button>
							<?php endforeach; ?>
						</div>
						<button type="submit" class="dak-button dak-button-primary dak-button-sm"><?php esc_html_e( '+ Add Problem', 'doctor-ak-portal' ); ?></button>
					</form>
				</section>

				<!-- Prescription -->
				<section class="dak-dashboard-card dak-encounter-section">
					<div class="dak-encounter-section-header">
						<span class="dak-encounter-section-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="12" height="12" rx="3" transform="rotate(45 10 10)"/><path d="M7 10h6"/></svg></span>
						<div class="dak-encounter-section-title">
							<h2><?php esc_html_e( 'Prescription', 'doctor-ak-portal' ); ?></h2>
							<p><?php esc_html_e( 'Medicines, dosage and instructions', 'doctor-ak-portal' ); ?></p>
						</div>
						<span class="dak-encounter-section-count" id="dak-encounter-prescriptions-count">0</span>
					</div>
					<div id="dak-encounter-prescriptions-list"></div>
					<form id="dak-encounter-add-prescription-form" class="dak-encounter-inline-form">
						<div class="dak-field">
							<label for="dak-encounter-prescription-medicine"><?php esc_html_e( 'Medicine', 'doctor-ak-portal' ); ?></label>
							<select id="dak-encounter-prescription-medicine">
								<option value="0"><?php esc_html_e( '— Type a medicine name instead —', 'doctor-ak-portal' ); ?></option>
							</select>
						</div>
						<div class="dak-field" id="dak-encounter-prescription-medicine-name-field">
							<label for="dak-encounter-prescription-medicine-name"><?php esc_html_e( 'Medicine name', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-encounter-prescription-medicine-name" placeholder="<?php esc_attr_e( 'e.g. Amoxicillin', 'doctor-ak-portal' ); ?>">
						</div>
						<div class="dak-field">
							<label for="dak-encounter-prescription-dosage"><?php esc_html_e( 'Dosage', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-encounter-prescription-dosage" placeholder="<?php esc_attr_e( 'e.g. 500mg', 'doctor-ak-portal' ); ?>">
						</div>
						<div class="dak-field">
							<label for="dak-encounter-prescription-frequency"><?php esc_html_e( 'Frequency', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-encounter-prescription-frequency" placeholder="<?php esc_attr_e( 'e.g. 1-0-1', 'doctor-ak-portal' ); ?>">
						</div>
						<div class="dak-field">
							<label for="dak-encounter-prescription-duration"><?php esc_html_e( 'Duration', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-encounter-prescription-duration" placeholder="<?php esc_attr_e( 'e.g. 5 days', 'doctor-ak-portal' ); ?>">
						</div>
						<div class="dak-field">
							<label for="dak-encounter-prescription-instructions"><?php esc_html_e( 'Instructions (optional)', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-encounter-prescription-instructions" placeholder="<?php esc_attr_e( 'e.g. after meals', 'doctor-ak-portal' ); ?>">
						</div>
						<button type="submit" class="dak-button dak-button-primary dak-button-sm"><?php esc_html_e( '+ Add Medicine', 'doctor-ak-portal' ); ?></button>
					</form>
				</section>

				<!-- Bill -->
				<section class="dak-dashboard-card dak-encounter-section">
					<div class="dak-encounter-section-header">
						<span class="dak-encounter-section-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="14" height="12" rx="1.5"/><path d="M6.5 8h7M6.5 11h4"/></svg></span>
						<div class="dak-encounter-section-title">
							<h2><?php esc_html_e( 'Bill', 'doctor-ak-portal' ); ?></h2>
							<p><?php esc_html_e( 'Services and charges for this visit', 'doctor-ak-portal' ); ?></p>
						</div>
						<span class="dak-encounter-section-count" id="dak-encounter-bill-count">0</span>
					</div>
					<div id="dak-encounter-bill-list"></div>
					<p class="dak-encounter-bill-total"><?php esc_html_e( 'Total:', 'doctor-ak-portal' ); ?> <strong id="dak-encounter-bill-total">PKR 0</strong></p>
					<form id="dak-encounter-add-bill-item-form" class="dak-encounter-inline-form">
						<div class="dak-field">
							<label for="dak-encounter-bill-service"><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></label>
							<select id="dak-encounter-bill-service">
								<option value="0"><?php esc_html_e( '— Custom charge —', 'doctor-ak-portal' ); ?></option>
							</select>
						</div>
						<div class="dak-field">
							<label for="dak-encounter-bill-description"><?php esc_html_e( 'Service / description', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-encounter-bill-description" placeholder="<?php esc_attr_e( 'e.g. Dressing', 'doctor-ak-portal' ); ?>">
						</div>
						<div class="dak-field">
							<label for="dak-encounter-bill-amount"><?php esc_html_e( 'Amount (PKR)', 'doctor-ak-portal' ); ?></label>
							<input type="number" id="dak-encounter-bill-amount" min="0" step="0.01" placeholder="0">
						</div>
						<button type="submit" class="dak-button dak-button-primary dak-button-sm"><?php esc_html_e( '+ Add Charge', 'doctor-ak-portal' ); ?></button>
					</form>
				</section>

				<!-- Reports -->
				<section class="dak-dashboard-card dak-encounter-section">
					<div class="dak-encounter-section-header">
						<span class="dak-encounter-section-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.5h6a1 1 0 0 1 1 1V16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M7.5 9h5M7.5 12h5"/></svg></span>
						<div class="dak-encounter-section-title">
							<h2><?php esc_html_e( 'Reports', 'doctor-ak-portal' ); ?></h2>
							<p><?php esc_html_e( 'PDF, JPG, PNG or WebP up to 10 MB', 'doctor-ak-portal' ); ?></p>
						</div>
						<span class="dak-encounter-section-count" id="dak-encounter-reports-count">0</span>
					</div>
					<div id="dak-encounter-reports-list"></div>
					<form id="dak-encounter-upload-report-form">
						<label class="dak-encounter-upload-dropzone" for="dak-encounter-report-file">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13.5V5M6.5 8.5 10 5l3.5 3.5"/><path d="M4 14.5v1a1.5 1.5 0 0 0 1.5 1.5h9a1.5 1.5 0 0 0 1.5-1.5v-1"/></svg>
							<strong><?php esc_html_e( 'Drag & drop a report, or click to browse', 'doctor-ak-portal' ); ?></strong>
							<span><?php esc_html_e( 'Attached reports appear on the patient timeline', 'doctor-ak-portal' ); ?></span>
							<input type="file" id="dak-encounter-report-file" accept="application/pdf,image/jpeg,image/png,image/webp" class="dak-visually-hidden">
						</label>
					</form>
				</section>

			</div>

			<div class="dak-encounter-sidebar">
				<section class="dak-dashboard-card dak-encounter-summary-card">
					<div class="dak-encounter-section-header">
						<span class="dak-encounter-section-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="12" height="14" rx="1.5"/><path d="M7.5 7.5h5M7.5 10.5h5M7.5 13.5h3"/></svg></span>
						<div class="dak-encounter-section-title">
							<h2><?php esc_html_e( 'Encounter summary', 'doctor-ak-portal' ); ?></h2>
						</div>
					</div>

					<div class="dak-encounter-summary-row">
						<span><?php esc_html_e( 'Problems', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-encounter-summary-problems">0</strong>
					</div>
					<div class="dak-encounter-summary-row">
						<span><?php esc_html_e( 'Medicines', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-encounter-summary-medicines">0</strong>
					</div>
					<div class="dak-encounter-summary-row">
						<span><?php esc_html_e( 'Charges', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-encounter-summary-charges">0</strong>
					</div>
					<div class="dak-encounter-summary-row">
						<span><?php esc_html_e( 'Reports', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-encounter-summary-reports">0</strong>
					</div>

					<div class="dak-encounter-summary-amount-row">
						<span><?php esc_html_e( 'Amount due', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-encounter-summary-amount">PKR 0</strong>
					</div>

					<button type="button" class="dak-button dak-button-primary dak-button-block<?php echo $is_closed ? ' dak-hidden' : ''; ?>" id="dak-encounter-close" data-encounter-close>
						<?php esc_html_e( 'Close encounter', 'doctor-ak-portal' ); ?>
					</button>
					<p class="dak-field-hint<?php echo $is_closed ? ' dak-hidden' : ''; ?>" id="dak-encounter-close-hint"><?php esc_html_e( 'Checks the patient out and locks this record.', 'doctor-ak-portal' ); ?></p>
					<p class="dak-field-hint<?php echo $is_closed ? '' : ' dak-hidden'; ?>" id="dak-encounter-closed-hint"><?php esc_html_e( 'This encounter is closed. The patient has been checked out.', 'doctor-ak-portal' ); ?></p>
				</section>
			</div>
		</div>
	</div>
<?php endif; ?>
