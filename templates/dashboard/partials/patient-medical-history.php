<?php
/**
 * Template: Patient dashboard "Medical History" tab — this patient's own
 * closed clinical Encounters (problems recorded, prescription, and links to
 * download the prescription/bill as a PDF). Medical History and Encounters
 * are the same underlying record — this is just its read-only, patient-
 * facing view (see Patient_Dashboard::render_medical_history_tab()).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $encounters      This patient's encounters (open and closed, newest first), each with an
 *                              added 'appointment', 'problems', 'prescriptions',
 *                              'prescription_pdf_url', and 'bill_pdf_url'.
 * @var string $history_pdf_url "Print medical history" PDF link for this patient, or ''.
 */

$history_pdf_url = isset( $history_pdf_url ) ? $history_pdf_url : '';

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="dak-results" id="dak-medical-history-list" aria-labelledby="dak-medical-history-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-medical-history-title"><?php esc_html_e( 'Your visits', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $encounters ) ) ); ?></span></h2>
		<?php if ( ! empty( $encounters ) ) : ?>
			<div class="dak-results-tools-actions">
				<div class="dak-dashboard-search dak-list-search-box">
					<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
					<input type="search" data-list-search="#dak-medical-history-list" placeholder="<?php esc_attr_e( 'Search doctor or clinic', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search visits', 'doctor-ak-portal' ); ?>">
				</div>
				<?php if ( '' !== $history_pdf_url ) : ?>
					<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $history_pdf_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print medical history', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $encounters ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'You have no visits recorded yet. Notes from your doctors appear here once you are checked in for a visit.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-visit-records">
			<?php foreach ( $encounters as $encounter ) : ?>
				<?php
				$dak_appt                = $encounter['appointment'];
				$dak_history_doctor_name = isset( $dak_appt['doctor_name'] ) ? $dak_appt['doctor_name'] : '';
				$dak_history_place       = ! empty( $dak_appt['clinic_name'] ) ? $dak_appt['clinic_name'] : ( ! empty( $dak_appt['clinic_label'] ) ? $dak_appt['clinic_label'] : ( isset( $dak_appt['type_label'] ) ? $dak_appt['type_label'] : '' ) );
				?>
				<article id="dak-encounter-<?php echo esc_attr( $encounter['id'] ); ?>" class="dak-visit-record" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $dak_history_doctor_name . ' ' . $dak_history_place ) ); ?>" aria-labelledby="dak-visit-<?php echo esc_attr( $encounter['id'] ); ?>-title">
					<header class="dak-visit-record-head">
						<div>
							<h3 class="dak-visit-record-title" id="dak-visit-<?php echo esc_attr( $encounter['id'] ); ?>-title">
								<?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Dr. %s', 'doctor-ak-portal' ), '' !== $dak_history_doctor_name ? $dak_history_doctor_name : '—' ) ); ?>
								<?php if ( \DoctorAKPortal\Includes\Encounters::STATUS_OPEN === $encounter['status'] ) : ?>
									<span class="dak-status-pill dak-status-pill-is-pending" title="<?php esc_attr_e( 'Your doctor may still update the notes for this visit.', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'In progress', 'doctor-ak-portal' ); ?></span>
								<?php endif; ?>
							</h3>
							<p class="dak-visit-record-meta">
								<span class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $encounter['checked_in_at'] ) ); ?></span>
								<?php if ( '' !== $dak_history_place ) : ?>
									· <?php echo esc_html( $dak_history_place ); ?>
								<?php endif; ?>
							</p>
						</div>
						<div class="dak-visit-record-links">
							<a class="dak-text-action" href="<?php echo esc_url( $encounter['prescription_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Prescription (PDF)', 'doctor-ak-portal' ); ?></a>
							<a class="dak-text-action" href="<?php echo esc_url( $encounter['bill_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Bill (PDF)', 'doctor-ak-portal' ); ?></a>
						</div>
					</header>

					<div class="dak-visit-record-body">
						<section class="dak-visit-record-section">
							<h4><?php esc_html_e( 'Problems & diagnosis', 'doctor-ak-portal' ); ?></h4>
							<?php if ( empty( $encounter['problems'] ) ) : ?>
								<p class="dak-cell-sub"><?php esc_html_e( 'No problem recorded for this visit.', 'doctor-ak-portal' ); ?></p>
							<?php else : ?>
								<ul class="dak-visit-record-list">
									<?php foreach ( $encounter['problems'] as $dak_problem ) : ?>
										<li>
											<span class="dak-cell-strong"><?php echo esc_html( $dak_problem['description'] ); ?></span>
											<?php if ( '' !== $dak_problem['notes'] ) : ?>
												<span class="dak-visit-record-notes"><?php echo esc_html( $dak_problem['notes'] ); ?></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</section>

						<section class="dak-visit-record-section">
							<h4><?php esc_html_e( 'Prescription', 'doctor-ak-portal' ); ?></h4>
							<?php if ( empty( $encounter['prescriptions'] ) ) : ?>
								<p class="dak-cell-sub"><?php esc_html_e( 'No medicines prescribed for this visit.', 'doctor-ak-portal' ); ?></p>
							<?php else : ?>
								<ul class="dak-visit-record-list">
									<?php foreach ( $encounter['prescriptions'] as $dak_prescription ) : ?>
										<?php $dak_prescription_meta = array_filter( array( $dak_prescription['dosage'], $dak_prescription['frequency'], $dak_prescription['duration'] ) ); ?>
										<li>
											<span class="dak-cell-strong"><?php echo esc_html( $dak_prescription['medicine_name'] ); ?></span>
											<?php if ( ! empty( $dak_prescription_meta ) ) : ?>
												<span class="dak-visit-record-notes"><?php echo esc_html( implode( ' · ', $dak_prescription_meta ) ); ?></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</section>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<p class="dak-empty-state dak-results-empty dak-hidden" data-list-search-empty><?php esc_html_e( 'No visits match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>