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
 * @var array $encounters This patient's closed encounters, each with an added
 *                         'appointment', 'problems', 'prescriptions',
 *                         'prescription_pdf_url', and 'bill_pdf_url'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="dak-dashboard-card" id="dak-medical-history-list">
	<?php if ( empty( $encounters ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'You have no completed visits yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-list-search-header">
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-medical-history-list" placeholder="<?php esc_attr_e( 'Search visits', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search visits', 'doctor-ak-portal' ); ?>">
			</div>
		</div>
		<ul class="dak-patient-payments-list">
			<?php foreach ( $encounters as $encounter ) : ?>
				<?php
				$dak_appt = $encounter['appointment'];
				$dak_history_doctor_name = isset( $dak_appt['doctor_name'] ) ? $dak_appt['doctor_name'] : '';
				$dak_history_clinic_or_type = ! empty( $dak_appt['clinic_label'] ) ? $dak_appt['clinic_label'] : ( isset( $dak_appt['type_label'] ) ? $dak_appt['type_label'] : '' );
				?>
				<li id="dak-encounter-<?php echo esc_attr( $encounter['id'] ); ?>" class="dak-patient-payment-row" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $dak_history_doctor_name . ' ' . $dak_history_clinic_or_type ) ); ?>">
					<span class="dak-patient-appt-avatar">
						<?php if ( ! empty( $dak_appt['doctor_avatar_url'] ) ) : ?>
							<img src="<?php echo esc_url( $dak_appt['doctor_avatar_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo esc_html( mb_strtoupper( mb_substr( isset( $dak_appt['doctor_name'] ) ? $dak_appt['doctor_name'] : '?', 0, 1 ) ) ); ?>
						<?php endif; ?>
					</span>

					<div class="dak-patient-payment-info">
						<strong><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Dr. %s', 'doctor-ak-portal' ), isset( $dak_appt['doctor_name'] ) ? $dak_appt['doctor_name'] : '—' ) ); ?></strong>
						<span class="dak-patient-payment-meta">
							<?php
							echo esc_html( mysql2date( get_option( 'date_format' ), $encounter['checked_in_at'] ) );

							$dak_clinic_or_type = ! empty( $dak_appt['clinic_label'] ) ? $dak_appt['clinic_label'] : ( isset( $dak_appt['type_label'] ) ? $dak_appt['type_label'] : '' );

							if ( '' !== $dak_clinic_or_type ) {
								echo ' &middot; ' . esc_html( $dak_clinic_or_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() applied; the &middot; entity is deliberately not double-escaped so it renders as a middle dot.
							}
							?>
						</span>

						<div class="dak-medical-history-note">
							<span class="dak-field-label"><?php esc_html_e( 'Problems', 'doctor-ak-portal' ); ?></span>
							<?php if ( empty( $encounter['problems'] ) ) : ?>
								<p class="dak-medical-history-note-empty"><?php esc_html_e( 'No problem recorded for this visit.', 'doctor-ak-portal' ); ?></p>
							<?php else : ?>
								<ul class="dak-medical-history-problem-list">
									<?php foreach ( $encounter['problems'] as $dak_problem ) : ?>
										<li>
											<?php echo esc_html( $dak_problem['description'] ); ?>
											<?php if ( '' !== $dak_problem['notes'] ) : ?>
												<span class="dak-medical-history-problem-notes"> — <?php echo esc_html( $dak_problem['notes'] ); ?></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>

						<div class="dak-medical-history-note">
							<span class="dak-field-label"><?php esc_html_e( 'Prescription', 'doctor-ak-portal' ); ?></span>
							<?php if ( empty( $encounter['prescriptions'] ) ) : ?>
								<p class="dak-medical-history-note-empty"><?php esc_html_e( 'No medicines prescribed for this visit.', 'doctor-ak-portal' ); ?></p>
							<?php else : ?>
								<ul class="dak-medical-history-problem-list">
									<?php foreach ( $encounter['prescriptions'] as $dak_prescription ) : ?>
										<?php
										$dak_prescription_meta = array_filter( array( $dak_prescription['dosage'], $dak_prescription['frequency'], $dak_prescription['duration'] ) );
										?>
										<li>
											<strong><?php echo esc_html( $dak_prescription['medicine_name'] ); ?></strong>
											<?php if ( ! empty( $dak_prescription_meta ) ) : ?>
												<span class="dak-medical-history-problem-notes"> — <?php echo esc_html( implode( ' · ', $dak_prescription_meta ) ); ?></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>

						<div class="dak-medical-history-links">
							<a class="dak-link" href="<?php echo esc_url( $encounter['prescription_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download Prescription PDF', 'doctor-ak-portal' ); ?></a>
							<a class="dak-link" href="<?php echo esc_url( $encounter['bill_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download Bill PDF', 'doctor-ak-portal' ); ?></a>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No visits match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
