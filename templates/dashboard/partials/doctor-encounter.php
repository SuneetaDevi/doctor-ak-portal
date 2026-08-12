<?php
/**
 * Template: Encounter detail screen — reached by clicking "Open Encounter"
 * on a checked-in appointment, on EITHER the doctor dashboard or the admin
 * dashboard (Admin_Dashboard's 'encounter' section renders this same file
 * verbatim — see its docblock). All content (patient/doctor/clinic
 * summary, Problems, Prescriptions, Bill, running total) is fetched and
 * rendered client-side (see assets/js/doctor-ak-encounter.js and
 * Encounter_Handler::handle_get_encounter()) so every add/delete can
 * re-render in place instead of a full page reload.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $encounter_id     The encounter to load, or 0 if it couldn't be found/isn't this account's.
 * @var string $appointments_url "Back to Appointments" link target.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Encounter', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'Record problems and a prescription for this visit, then close the encounter to check the patient out.', 'doctor-ak-portal' ); ?></p>
</div>

<?php if ( $encounter_id <= 0 ) : ?>
	<section class="dak-dashboard-card">
		<p class="dak-empty-state"><?php esc_html_e( "That encounter couldn't be found.", 'doctor-ak-portal' ); ?></p>
		<?php if ( $appointments_url ) : ?>
			<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $appointments_url ); ?>">&larr; <?php esc_html_e( 'Back to Appointments', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</section>
<?php else : ?>
	<div id="dak-encounter-root" data-encounter-id="<?php echo esc_attr( $encounter_id ); ?>">
		<section class="dak-dashboard-card">
			<div class="dak-dashboard-card-header">
				<h2 id="dak-encounter-patient-name">&nbsp;</h2>
				<span class="dak-status-pill dak-status-pill-outline" id="dak-encounter-status"></span>
			</div>
			<p class="dak-field-hint" id="dak-encounter-meta">&nbsp;</p>
			<?php if ( $appointments_url ) : ?>
				<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $appointments_url ); ?>">&larr; <?php esc_html_e( 'Back to Appointments', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</section>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-encounter-error" role="alert"></div>

		<!-- Problems -->
		<section class="dak-dashboard-card">
			<div class="dak-dashboard-card-header">
				<h2><?php esc_html_e( 'Problems', 'doctor-ak-portal' ); ?></h2>
			</div>
			<div id="dak-encounter-problems-list"></div>
			<form id="dak-encounter-add-problem-form" class="dak-encounter-inline-form">
				<div class="dak-field">
					<label for="dak-encounter-problem-description"><?php esc_html_e( 'Problem / diagnosis', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-encounter-problem-description" placeholder="<?php esc_attr_e( 'e.g. Acute pharyngitis', 'doctor-ak-portal' ); ?>">
				</div>
				<div class="dak-field">
					<label for="dak-encounter-problem-notes"><?php esc_html_e( 'Notes (optional)', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-encounter-problem-notes">
				</div>
				<button type="submit" class="dak-button dak-button-secondary dak-button-sm"><?php esc_html_e( '+ Add Problem', 'doctor-ak-portal' ); ?></button>
			</form>
		</section>

		<!-- Prescription -->
		<section class="dak-dashboard-card">
			<div class="dak-dashboard-card-header">
				<h2><?php esc_html_e( 'Prescription', 'doctor-ak-portal' ); ?></h2>
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
				<button type="submit" class="dak-button dak-button-secondary dak-button-sm"><?php esc_html_e( '+ Add Medicine', 'doctor-ak-portal' ); ?></button>
			</form>
		</section>

		<!-- Bill -->
		<section class="dak-dashboard-card">
			<div class="dak-dashboard-card-header">
				<h2><?php esc_html_e( 'Bill', 'doctor-ak-portal' ); ?></h2>
			</div>
			<div id="dak-encounter-bill-list"></div>
			<form id="dak-encounter-add-bill-item-form" class="dak-encounter-inline-form">
				<div class="dak-field">
					<label for="dak-encounter-bill-service"><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></label>
					<select id="dak-encounter-bill-service">
						<option value="0"><?php esc_html_e( '— Custom charge —', 'doctor-ak-portal' ); ?></option>
					</select>
				</div>
				<div class="dak-field">
					<label for="dak-encounter-bill-description"><?php esc_html_e( 'Description', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-encounter-bill-description" placeholder="<?php esc_attr_e( 'e.g. Dressing', 'doctor-ak-portal' ); ?>">
				</div>
				<div class="dak-field">
					<label for="dak-encounter-bill-amount"><?php esc_html_e( 'Amount (PKR)', 'doctor-ak-portal' ); ?></label>
					<input type="number" id="dak-encounter-bill-amount" min="0" step="0.01">
				</div>
				<button type="submit" class="dak-button dak-button-secondary dak-button-sm"><?php esc_html_e( '+ Add Charge', 'doctor-ak-portal' ); ?></button>
			</form>
			<p class="dak-encounter-bill-total"><?php esc_html_e( 'Total:', 'doctor-ak-portal' ); ?> <strong id="dak-encounter-bill-total">PKR 0</strong></p>
		</section>

		<!-- Reports -->
		<section class="dak-dashboard-card">
			<div class="dak-dashboard-card-header">
				<h2><?php esc_html_e( 'Reports', 'doctor-ak-portal' ); ?></h2>
			</div>
			<div id="dak-encounter-reports-list"></div>
			<form id="dak-encounter-upload-report-form" class="dak-encounter-inline-form">
				<div class="dak-field">
					<label for="dak-encounter-report-file"><?php esc_html_e( 'Upload report (PDF, JPG, PNG or WebP)', 'doctor-ak-portal' ); ?></label>
					<input type="file" id="dak-encounter-report-file" accept="application/pdf,image/jpeg,image/png,image/webp">
				</div>
				<button type="submit" class="dak-button dak-button-secondary dak-button-sm" id="dak-encounter-report-upload-button"><?php esc_html_e( '+ Add Report', 'doctor-ak-portal' ); ?></button>
			</form>
		</section>

		<!-- Actions -->
		<section class="dak-dashboard-card">
			<div class="dak-dashboard-card-header">
				<h2><?php esc_html_e( 'Documents & Checkout', 'doctor-ak-portal' ); ?></h2>
			</div>
			<div class="dak-encounter-actions">
				<a class="dak-button dak-button-secondary" id="dak-encounter-download-prescription" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Download Prescription PDF', 'doctor-ak-portal' ); ?></a>
				<a class="dak-button dak-button-secondary" id="dak-encounter-download-bill" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Download Bill PDF', 'doctor-ak-portal' ); ?></a>
				<button type="button" class="dak-button dak-button-primary" id="dak-encounter-close" data-encounter-close><?php esc_html_e( 'Close Encounter (Check Out Patient)', 'doctor-ak-portal' ); ?></button>
			</div>
			<p class="dak-field-hint dak-hidden" id="dak-encounter-closed-hint"><?php esc_html_e( 'This encounter is closed. The patient has been checked out.', 'doctor-ak-portal' ); ?></p>
		</section>
	</div>
<?php endif; ?>
