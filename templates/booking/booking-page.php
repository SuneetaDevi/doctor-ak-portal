<?php
/**
 * Template: Full booking page for the [book_appointment] shortcode — a
 * 6-step wizard (Doctor -> Service -> Personal Details -> Date & Time ->
 * Payment -> Confirmation) with a persistent vertical step sidebar and
 * explicit Back/Next navigation between steps.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $doctor_cards         Doctor cards, see Booking_Page::doctor_cards_data().
 * @var int    $selected_doctor_id   Preselected doctor's user ID, or 0.
 * @var string $selected_doctor_name Preselected doctor's display name (no "Dr." prefix).
 * @var string $selected_type        'clinic' or 'video'.
 * @var bool   $video_disabled       Whether the preselected doctor doesn't offer video consultations.
 * @var string $contact_url          "Need help with booking?" link target.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-booking-page dak-booking-wizard">
	<div class="dak-booking-page-header">
		<span class="dak-eyebrow"><?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?></span>
		<h1><?php esc_html_e( 'Book a Consultation', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Pick a doctor, choose an open time slot, and confirm your details.', 'doctor-ak-portal' ); ?></p>
	</div>

	<form id="dak-booking-form" novalidate>
		<input type="hidden" name="doctor_id" id="dak-booking-doctor-id" value="<?php echo esc_attr( $selected_doctor_id ); ?>">
		<input type="hidden" name="type" id="dak-booking-type" value="<?php echo esc_attr( $selected_type ); ?>">
		<input type="hidden" name="service_id" id="dak-booking-service-id" value="">
		<input type="hidden" name="clinic_id" id="dak-booking-clinic-id" value="">
		<input type="hidden" name="date" id="dak-booking-date" value="">
		<input type="hidden" name="time" id="dak-booking-time" value="">
		<input type="hidden" name="payment_choice" id="dak-booking-payment-choice" value="later">

		<div class="dak-booking-wizard-layout">
			<aside class="dak-booking-wizard-sidebar">
				<ol class="dak-booking-wizard-steps" id="dak-booking-steps">
					<li class="dak-booking-wizard-step" data-step="doctor"><span class="dak-booking-wizard-step-badge" data-num="1"></span><span class="dak-booking-wizard-step-label"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></span></li>
					<li class="dak-booking-wizard-step" data-step="service"><span class="dak-booking-wizard-step-badge" data-num="2"></span><span class="dak-booking-wizard-step-label"><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></span></li>
					<li class="dak-booking-wizard-step" data-step="identity"><span class="dak-booking-wizard-step-badge" data-num="3"></span><span class="dak-booking-wizard-step-label"><?php esc_html_e( 'Personal Details', 'doctor-ak-portal' ); ?></span></li>
					<li class="dak-booking-wizard-step" data-step="datetime"><span class="dak-booking-wizard-step-badge" data-num="4"></span><span class="dak-booking-wizard-step-label"><?php esc_html_e( 'Date & Time', 'doctor-ak-portal' ); ?></span></li>
					<li class="dak-booking-wizard-step" data-step="payment"><span class="dak-booking-wizard-step-badge" data-num="5"></span><span class="dak-booking-wizard-step-label"><?php esc_html_e( 'Payment', 'doctor-ak-portal' ); ?></span></li>
					<li class="dak-booking-wizard-step" data-step="confirmation"><span class="dak-booking-wizard-step-badge" data-num="6"></span><span class="dak-booking-wizard-step-label"><?php esc_html_e( 'Confirmation', 'doctor-ak-portal' ); ?></span></li>
				</ol>

				<?php if ( $contact_url ) : ?>
					<a class="dak-booking-wizard-help" href="<?php echo esc_url( $contact_url ); ?>">
						<span class="dak-booking-wizard-help-icon" aria-hidden="true">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="7.5"/><path d="M7.8 7.8a2.2 2.2 0 1 1 3 2c-.7.5-1.3.9-1.3 1.9" stroke-linecap="round"/><circle cx="10" cy="14" r="0.6" fill="currentColor" stroke="none"/></svg>
						</span>
						<?php esc_html_e( 'Need help with booking?', 'doctor-ak-portal' ); ?>
					</a>
				<?php endif; ?>
			</aside>

			<div class="dak-booking-wizard-content">
				<div class="dak-alert dak-alert-error dak-hidden" id="dak-booking-error" role="alert"></div>

				<!-- Step 1: Doctor -->
				<section class="dak-booking-card" id="dak-booking-step-doctor">
					<h2 class="dak-booking-card-title"><?php esc_html_e( 'Choose a Doctor', 'doctor-ak-portal' ); ?></h2>

					<div class="dak-booking-doctor-cards" id="dak-booking-doctor-cards">
						<?php foreach ( $doctor_cards as $card ) : ?>
							<button
								type="button"
								class="dak-booking-doctor-card <?php echo (int) $card['id'] === (int) $selected_doctor_id ? 'is-selected' : ''; ?>"
								data-doctor-card
								data-doctor-id="<?php echo esc_attr( $card['id'] ); ?>"
								data-doctor-name="<?php echo esc_attr( $card['name'] ); ?>"
								<?php if ( $card['video_disabled'] ) : ?>data-video-disabled="1"<?php endif; ?>
							>
								<span class="dak-booking-doctor-avatar">
									<?php if ( $card['avatar_url'] ) : ?>
										<img src="<?php echo esc_url( $card['avatar_url'] ); ?>" alt="">
									<?php else : ?>
										<?php echo esc_html( $card['initials'] ); ?>
									<?php endif; ?>
								</span>
								<span class="dak-booking-doctor-info">
									<strong><?php echo esc_html( sprintf( 'Dr. %s', $card['name'] ) ); ?></strong>
									<span class="dak-booking-doctor-specialization"><?php echo esc_html( '' !== $card['specialization'] ? $card['specialization'] : __( 'General Physician', 'doctor-ak-portal' ) ); ?></span>
								</span>
							</button>
						<?php endforeach; ?>
					</div>
					<span class="dak-field-error" data-field="doctor_id"></span>

					<div class="dak-booking-wizard-nav">
						<span></span>
						<button type="button" class="dak-button dak-button-primary" data-wizard-next="service"><?php esc_html_e( 'Next', 'doctor-ak-portal' ); ?></button>
					</div>
				</section>

				<!-- Step 2: Service -->
				<section class="dak-booking-card dak-hidden" id="dak-booking-step-service">
					<h2 class="dak-booking-card-title"><?php esc_html_e( 'Appointment Type & Service', 'doctor-ak-portal' ); ?></h2>

					<div class="dak-booking-selected-doctor dak-hidden" id="dak-booking-service-doctor-summary">
						<span class="dak-booking-doctor-avatar" id="dak-booking-service-doctor-avatar"></span>
						<span class="dak-booking-doctor-info">
							<span class="dak-booking-selected-doctor-label"><?php esc_html_e( 'Booking with', 'doctor-ak-portal' ); ?></span>
							<strong id="dak-booking-service-doctor-name"></strong>
						</span>
						<button type="button" class="dak-button dak-button-secondary dak-booking-selected-doctor-change" data-wizard-back="doctor"><?php esc_html_e( 'Change', 'doctor-ak-portal' ); ?></button>
					</div>

					<div class="dak-booking-field-label"><?php esc_html_e( 'Appointment type', 'doctor-ak-portal' ); ?></div>
					<div class="dak-booking-segmented" role="tablist">
						<button type="button" class="dak-booking-segment <?php echo 'clinic' === $selected_type ? 'is-active' : ''; ?>" data-type="clinic" role="tab" aria-selected="<?php echo 'clinic' === $selected_type ? 'true' : 'false'; ?>"><?php esc_html_e( 'Clinic Visit', 'doctor-ak-portal' ); ?></button>
						<button type="button" class="dak-booking-segment <?php echo 'video' === $selected_type ? 'is-active' : ''; ?>" data-type="video" role="tab" aria-selected="<?php echo 'video' === $selected_type ? 'true' : 'false'; ?>" <?php disabled( $video_disabled ); ?>><?php esc_html_e( 'Online Video', 'doctor-ak-portal' ); ?></button>
					</div>
					<p class="dak-field-hint dak-hidden" id="dak-booking-clinic-hint"><?php esc_html_e( 'No specific clinic locations configured — the address will be shared upon confirmation.', 'doctor-ak-portal' ); ?></p>
					<p class="dak-field-hint dak-hidden" id="dak-booking-video-unavailable"><?php esc_html_e( 'This doctor does not offer online video consultations.', 'doctor-ak-portal' ); ?></p>

					<div id="dak-booking-clinic-section" class="dak-hidden">
						<div class="dak-booking-field-label"><?php esc_html_e( 'Select clinic', 'doctor-ak-portal' ); ?></div>
						<div class="dak-booking-service-cards" id="dak-booking-clinic-cards"></div>
						<span class="dak-field-error" data-field="clinic_id"></span>
					</div>

					<div id="dak-booking-service-section">
						<div class="dak-booking-field-label" id="dak-booking-service-label"><?php echo esc_html( 'video' === $selected_type ? __( 'Video Consultation Fee', 'doctor-ak-portal' ) : __( 'Service', 'doctor-ak-portal' ) ); ?></div>
						<div class="dak-booking-service-cards" id="dak-booking-service-cards">
							<p class="dak-field-hint" id="dak-booking-no-services"><?php esc_html_e( 'Select a doctor to see their services.', 'doctor-ak-portal' ); ?></p>
						</div>
						<span class="dak-field-error" data-field="service_id"></span>
					</div>

					<div class="dak-booking-wizard-nav">
						<button type="button" class="dak-button dak-button-secondary" data-wizard-back="doctor">&larr; <?php esc_html_e( 'Back', 'doctor-ak-portal' ); ?></button>
						<button type="button" class="dak-button dak-button-primary" data-wizard-next="identity"><?php esc_html_e( 'Next', 'doctor-ak-portal' ); ?></button>
					</div>
				</section>

				<!-- Step 3: Personal Details -->
				<section class="dak-booking-card dak-hidden" id="dak-booking-step-identity">
					<h2 class="dak-booking-card-title"><?php esc_html_e( 'Personal Details', 'doctor-ak-portal' ); ?></h2>

					<div id="dak-booking-identity-loggedin" class="dak-hidden">
						<div class="dak-field">
							<label><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-booking-loggedin-name" disabled>
						</div>
						<div class="dak-field-row">
							<div class="dak-field">
								<label><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?></label>
								<input type="text" id="dak-booking-loggedin-email" disabled>
							</div>
							<div class="dak-field">
								<label><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?></label>
								<input type="text" id="dak-booking-loggedin-phone" disabled>
							</div>
						</div>
						<p class="dak-alert dak-alert-error dak-hidden" id="dak-booking-loggedin-phone-missing">
							<?php esc_html_e( 'A phone number is required to book a video consultation.', 'doctor-ak-portal' ); ?>
							<a href="#" id="dak-booking-loggedin-phone-missing-link"><?php esc_html_e( 'Add one to your profile', 'doctor-ak-portal' ); ?></a>
						</p>
					</div>

					<div id="dak-booking-identity-choice" class="dak-hidden">
						<p class="dak-field-hint"><?php esc_html_e( 'Log in or register for faster booking, or continue without an account.', 'doctor-ak-portal' ); ?></p>
						<div class="dak-booking-identity-choices">
							<a class="dak-button dak-button-secondary" id="dak-booking-login-link" href="#"><?php esc_html_e( 'Log In', 'doctor-ak-portal' ); ?></a>
							<a class="dak-button dak-button-secondary" id="dak-booking-register-link" href="#"><?php esc_html_e( 'Register', 'doctor-ak-portal' ); ?></a>
							<button type="button" class="dak-button dak-button-secondary" id="dak-booking-guest-toggle"><?php esc_html_e( 'Continue as Guest', 'doctor-ak-portal' ); ?></button>
						</div>
					</div>

					<div id="dak-booking-identity-guest" class="dak-hidden">
						<div class="dak-field">
							<label for="dak-booking-guest-name"><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></label>
							<input type="text" id="dak-booking-guest-name" name="guest_name">
							<span class="dak-field-error" data-field="guest_name"></span>
						</div>
						<div class="dak-field-row">
							<div class="dak-field">
								<label for="dak-booking-guest-email"><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?></label>
								<input type="email" id="dak-booking-guest-email" name="guest_email">
								<span class="dak-field-error" data-field="guest_email"></span>
							</div>
							<div class="dak-field">
								<label for="dak-booking-guest-phone"><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?> <span class="dak-required dak-hidden" id="dak-booking-guest-phone-required">*</span></label>
								<input type="tel" id="dak-booking-guest-phone" name="guest_phone">
								<span class="dak-field-error" data-field="guest_phone"></span>
							</div>
						</div>
					</div>

					<div class="dak-booking-wizard-nav">
						<button type="button" class="dak-button dak-button-secondary" data-wizard-back="service">&larr; <?php esc_html_e( 'Back', 'doctor-ak-portal' ); ?></button>
						<button type="button" class="dak-button dak-button-primary" id="dak-booking-identity-next" data-wizard-next="datetime"><?php esc_html_e( 'Next', 'doctor-ak-portal' ); ?></button>
					</div>
				</section>

				<!-- Step 4: Date & Time -->
				<section class="dak-booking-card dak-hidden" id="dak-booking-step-datetime">
					<div class="dak-booking-calendar-toolbar">
						<h2 class="dak-booking-card-title"><?php esc_html_e( 'Select Date and Time', 'doctor-ak-portal' ); ?></h2>
						<div class="dak-booking-calendar-header">
							<button type="button" class="dak-icon-button" id="dak-booking-cal-prev" aria-label="<?php esc_attr_e( 'Previous week', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.5 5l-5 5 5 5"/></svg></button>
							<span id="dak-booking-cal-title" class="dak-booking-calendar-title"></span>
							<button type="button" class="dak-icon-button" id="dak-booking-cal-next" aria-label="<?php esc_attr_e( 'Next week', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 5l5 5-5 5"/></svg></button>
						</div>
					</div>

					<div class="dak-booking-date-strip-row">
						<button type="button" class="dak-icon-button dak-booking-date-strip-nav" id="dak-booking-strip-prev" aria-label="<?php esc_attr_e( 'Previous week', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.5 5l-5 5 5 5"/></svg></button>
						<div class="dak-booking-date-strip" id="dak-booking-date-strip"></div>
						<button type="button" class="dak-icon-button dak-booking-date-strip-nav" id="dak-booking-strip-next" aria-label="<?php esc_attr_e( 'Next week', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 5l5 5-5 5"/></svg></button>
					</div>

					<div class="dak-booking-quick-dates">
						<button type="button" class="dak-booking-quick-date" id="dak-booking-today-btn"><?php esc_html_e( 'Today', 'doctor-ak-portal' ); ?></button>
						<button type="button" class="dak-booking-quick-date" id="dak-booking-tomorrow-btn"><?php esc_html_e( 'Tomorrow', 'doctor-ak-portal' ); ?></button>
					</div>

					<div id="dak-booking-slots-groups"></div>
					<p class="dak-empty-state dak-hidden" id="dak-booking-no-slots"><?php esc_html_e( 'No time slots are configured for this doctor on this date.', 'doctor-ak-portal' ); ?></p>
					<span class="dak-field-error" data-field="date"></span>
					<span class="dak-field-error" data-field="time"></span>

					<div class="dak-booking-legend">
						<span class="dak-booking-legend-item"><span class="dak-booking-legend-swatch is-available" aria-hidden="true"></span><?php esc_html_e( 'Available', 'doctor-ak-portal' ); ?></span>
						<span class="dak-booking-legend-item"><span class="dak-booking-legend-swatch is-booked" aria-hidden="true"></span><?php esc_html_e( 'Booked', 'doctor-ak-portal' ); ?></span>
						<span class="dak-booking-legend-item"><span class="dak-booking-legend-swatch is-past" aria-hidden="true"></span><?php esc_html_e( 'Past', 'doctor-ak-portal' ); ?></span>
						<span class="dak-booking-legend-item"><span class="dak-booking-legend-swatch is-selected" aria-hidden="true"></span><?php esc_html_e( 'Selected', 'doctor-ak-portal' ); ?></span>
					</div>

					<div class="dak-booking-currently-selected dak-hidden" id="dak-booking-currently-selected">
						<span class="dak-booking-currently-selected-icon" aria-hidden="true">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2V10l2.8 1.8"/></svg>
						</span>
						<span>
							<strong><?php esc_html_e( 'Currently Selected:', 'doctor-ak-portal' ); ?></strong>
							<span id="dak-booking-currently-selected-text"></span>
						</span>
					</div>

					<div class="dak-booking-wizard-nav">
						<button type="button" class="dak-button dak-button-secondary" data-wizard-back="identity">&larr; <?php esc_html_e( 'Back', 'doctor-ak-portal' ); ?></button>
						<button type="button" class="dak-button dak-button-primary" data-wizard-next="payment"><?php esc_html_e( 'Next', 'doctor-ak-portal' ); ?></button>
					</div>
				</section>

				<!-- Step 5: Payment -->
				<section class="dak-booking-card dak-hidden" id="dak-booking-step-payment">
					<h2 class="dak-booking-card-title"><?php esc_html_e( 'Review & Payment', 'doctor-ak-portal' ); ?></h2>

					<ul class="dak-booking-summary-list" id="dak-booking-summary-list">
						<li class="dak-hidden" data-summary-row="doctor"><span class="dak-booking-summary-icon" aria-hidden="true">&#128100;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="type"><span class="dak-booking-summary-icon" aria-hidden="true">&#128205;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="clinic"><span class="dak-booking-summary-icon" aria-hidden="true">&#127973;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="service"><span class="dak-booking-summary-icon" aria-hidden="true">&#128203;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="date"><span class="dak-booking-summary-icon" aria-hidden="true">&#128197;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="time"><span class="dak-booking-summary-icon" aria-hidden="true">&#128337;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="instant"><span class="dak-booking-summary-icon" aria-hidden="true">&#9889;</span><span data-summary-value></span></li>
					</ul>

					<div class="dak-booking-summary-total">
						<span><?php esc_html_e( 'Total', 'doctor-ak-portal' ); ?></span>
						<span id="dak-booking-summary-total-amount">&mdash;</span>
					</div>

					<p class="dak-booking-summary-note" id="dak-booking-summary-cancellation-note"><?php esc_html_e( 'Choose a doctor to see their cancellation policy.', 'doctor-ak-portal' ); ?></p>

					<div id="dak-booking-submit-single">
						<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-booking-submit">
							<span class="dak-button-label"><?php esc_html_e( 'Book Consultation', 'doctor-ak-portal' ); ?></span>
						</button>
					</div>

					<div class="dak-booking-payment-choice dak-hidden" id="dak-booking-submit-choice">
						<p class="dak-field-hint" id="dak-booking-submit-choice-hint"><?php esc_html_e( 'This appointment has a charge. Pay now online, or book now and pay at the clinic.', 'doctor-ak-portal' ); ?></p>
						<button type="submit" class="dak-button dak-button-secondary dak-button-block" id="dak-booking-pay-later" data-payment-choice="later">
							<span class="dak-button-label"><?php esc_html_e( 'Book Now — Pay Later', 'doctor-ak-portal' ); ?></span>
						</button>
						<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-booking-pay-now" data-payment-choice="now">
							<span class="dak-button-label"><?php esc_html_e( 'Pay Now', 'doctor-ak-portal' ); ?></span>
						</button>
					</div>

					<div class="dak-booking-wizard-nav">
						<button type="button" class="dak-button dak-button-secondary" data-wizard-back="datetime">&larr; <?php esc_html_e( 'Back', 'doctor-ak-portal' ); ?></button>
						<span></span>
					</div>
				</section>

				<!-- Step 6: Confirmation -->
				<section class="dak-booking-card dak-hidden" id="dak-booking-step-confirmation">
					<h2 class="dak-booking-card-title"><?php esc_html_e( 'Confirmation', 'doctor-ak-portal' ); ?></h2>
					<div class="dak-alert dak-alert-success dak-hidden" id="dak-booking-success" role="status"></div>
				</section>
			</div>
		</div>
	</form>
</div>
