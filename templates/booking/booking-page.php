<?php
/**
 * Template: Full booking page for the [book_appointment] shortcode —
 * doctor/service cards, appointment-type toggle, a month calendar with
 * availability dots, grouped time-slot cards, and a running "Your Booking"
 * summary sidebar with a Continue-to-confirm step.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $doctor_cards         Doctor cards, see Booking_Page::doctor_cards_data().
 * @var int    $selected_doctor_id   Preselected doctor's user ID, or 0.
 * @var string $selected_doctor_name Preselected doctor's display name (no "Dr." prefix).
 * @var string $selected_type        'clinic' or 'video'.
 * @var bool   $video_disabled       Whether the preselected doctor doesn't offer video consultations.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-booking-page">
	<div class="dak-booking-page-header">
		<span class="dak-eyebrow"><?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?></span>
		<h1><?php esc_html_e( 'Book a Consultation', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Pick a doctor, choose an open time slot, and confirm your details.', 'doctor-ak-portal' ); ?></p>
	</div>

	<ol class="dak-booking-steps" id="dak-booking-steps">
		<li class="dak-booking-step" data-step="1"><span class="dak-booking-step-badge">1</span><?php esc_html_e( 'Doctor & Service', 'doctor-ak-portal' ); ?></li>
		<li class="dak-booking-step" data-step="2"><span class="dak-booking-step-badge">2</span><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></li>
		<li class="dak-booking-step" data-step="3"><span class="dak-booking-step-badge">3</span><?php esc_html_e( 'Time', 'doctor-ak-portal' ); ?></li>
	</ol>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-booking-error" role="alert"></div>
	<div class="dak-alert dak-alert-success dak-hidden" id="dak-booking-success" role="status"></div>

	<form id="dak-booking-form" novalidate>
		<input type="hidden" name="doctor_id" id="dak-booking-doctor-id" value="<?php echo esc_attr( $selected_doctor_id ); ?>">
		<input type="hidden" name="type" id="dak-booking-type" value="<?php echo esc_attr( $selected_type ); ?>">
		<input type="hidden" name="service_id" id="dak-booking-service-id" value="">
		<input type="hidden" name="date" id="dak-booking-date" value="">
		<input type="hidden" name="time" id="dak-booking-time" value="">

		<div class="dak-booking-layout">
			<div class="dak-booking-main">

				<section class="dak-booking-card">
					<h2 class="dak-booking-card-title"><?php esc_html_e( 'Doctor & Service', 'doctor-ak-portal' ); ?></h2>

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

					<div class="dak-booking-field-label"><?php esc_html_e( 'Appointment type', 'doctor-ak-portal' ); ?></div>
					<div class="dak-booking-segmented" role="tablist">
						<button type="button" class="dak-booking-segment <?php echo 'clinic' === $selected_type ? 'is-active' : ''; ?>" data-type="clinic" role="tab" aria-selected="<?php echo 'clinic' === $selected_type ? 'true' : 'false'; ?>"><?php esc_html_e( 'Clinic Visit', 'doctor-ak-portal' ); ?></button>
						<button type="button" class="dak-booking-segment <?php echo 'video' === $selected_type ? 'is-active' : ''; ?>" data-type="video" role="tab" aria-selected="<?php echo 'video' === $selected_type ? 'true' : 'false'; ?>" <?php disabled( $video_disabled ); ?>><?php esc_html_e( 'Online Video', 'doctor-ak-portal' ); ?></button>
					</div>
					<p class="dak-field-hint" id="dak-booking-clinic-hint"><?php esc_html_e( 'Clinic address shared upon confirmation.', 'doctor-ak-portal' ); ?></p>
					<p class="dak-field-hint dak-hidden" id="dak-booking-video-unavailable"><?php esc_html_e( 'This doctor does not offer online video consultations.', 'doctor-ak-portal' ); ?></p>

					<div id="dak-booking-service-section" class="<?php echo 'video' === $selected_type ? 'dak-hidden' : ''; ?>">
						<div class="dak-booking-field-label"><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></div>
						<div class="dak-booking-service-cards" id="dak-booking-service-cards">
							<p class="dak-field-hint" id="dak-booking-no-services"><?php esc_html_e( 'Select a doctor to see their services.', 'doctor-ak-portal' ); ?></p>
						</div>
						<span class="dak-field-error" data-field="service_id"></span>
					</div>
				</section>

				<section class="dak-booking-card">
					<div class="dak-booking-calendar-toolbar">
						<h2 class="dak-booking-card-title"><?php esc_html_e( 'Choose a date', 'doctor-ak-portal' ); ?></h2>
						<div class="dak-booking-quick-dates">
							<button type="button" class="dak-booking-quick-date" id="dak-booking-today-btn"><?php esc_html_e( 'Today', 'doctor-ak-portal' ); ?></button>
							<button type="button" class="dak-booking-quick-date" id="dak-booking-tomorrow-btn"><?php esc_html_e( 'Tomorrow', 'doctor-ak-portal' ); ?></button>
						</div>
					</div>

					<div class="dak-booking-calendar-header">
						<button type="button" class="dak-icon-button" id="dak-booking-cal-prev" aria-label="<?php esc_attr_e( 'Previous month', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.5 5l-5 5 5 5"/></svg></button>
						<span id="dak-booking-cal-title" class="dak-booking-calendar-title"></span>
						<button type="button" class="dak-icon-button" id="dak-booking-cal-next" aria-label="<?php esc_attr_e( 'Next month', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 5l5 5-5 5"/></svg></button>
					</div>
					<div class="dak-booking-calendar-weekdays">
						<span><?php esc_html_e( 'Sun', 'doctor-ak-portal' ); ?></span>
						<span><?php esc_html_e( 'Mon', 'doctor-ak-portal' ); ?></span>
						<span><?php esc_html_e( 'Tue', 'doctor-ak-portal' ); ?></span>
						<span><?php esc_html_e( 'Wed', 'doctor-ak-portal' ); ?></span>
						<span><?php esc_html_e( 'Thu', 'doctor-ak-portal' ); ?></span>
						<span><?php esc_html_e( 'Fri', 'doctor-ak-portal' ); ?></span>
						<span><?php esc_html_e( 'Sat', 'doctor-ak-portal' ); ?></span>
					</div>
					<div class="dak-booking-calendar-grid" id="dak-booking-calendar-grid"></div>
					<span class="dak-field-error" data-field="date"></span>

					<div class="dak-booking-calendar-legend">
						<span class="dak-booking-legend-item"><span class="dak-booking-dot is-many" aria-hidden="true"></span><?php esc_html_e( 'Many slots', 'doctor-ak-portal' ); ?></span>
						<span class="dak-booking-legend-item"><span class="dak-booking-dot is-few" aria-hidden="true"></span><?php esc_html_e( 'Few left', 'doctor-ak-portal' ); ?></span>
						<span class="dak-booking-legend-item"><span class="dak-booking-dot is-full" aria-hidden="true"></span><?php esc_html_e( 'Full / past', 'doctor-ak-portal' ); ?></span>
					</div>
				</section>

				<section class="dak-booking-card dak-hidden" id="dak-booking-slots-section">
					<div class="dak-booking-slots-toolbar">
						<h2 class="dak-booking-card-title" id="dak-booking-slots-date-label"><?php esc_html_e( 'Available times', 'doctor-ak-portal' ); ?></h2>
					</div>

					<div class="dak-booking-legend">
						<span class="dak-booking-legend-item"><span class="dak-booking-legend-swatch is-available" aria-hidden="true"></span><?php esc_html_e( 'Available', 'doctor-ak-portal' ); ?></span>
						<span class="dak-booking-legend-item"><span class="dak-booking-legend-swatch is-booked" aria-hidden="true"></span><?php esc_html_e( 'Booked', 'doctor-ak-portal' ); ?></span>
						<span class="dak-booking-legend-item"><span class="dak-booking-legend-swatch is-past" aria-hidden="true"></span><?php esc_html_e( 'Past', 'doctor-ak-portal' ); ?></span>
						<span class="dak-booking-legend-item"><span class="dak-booking-legend-swatch is-selected" aria-hidden="true"></span><?php esc_html_e( 'Selected', 'doctor-ak-portal' ); ?></span>
					</div>

					<div id="dak-booking-slots-groups"></div>
					<p class="dak-empty-state dak-hidden" id="dak-booking-no-slots"><?php esc_html_e( 'No time slots are configured for this doctor on this date.', 'doctor-ak-portal' ); ?></p>
					<span class="dak-field-error" data-field="time"></span>
				</section>

				<section class="dak-booking-card dak-hidden" id="dak-booking-details">
					<h2 class="dak-booking-card-title"><?php esc_html_e( 'Confirm your details', 'doctor-ak-portal' ); ?></h2>

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
								<label for="dak-booking-guest-phone"><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?></label>
								<input type="tel" id="dak-booking-guest-phone" name="guest_phone">
								<span class="dak-field-error" data-field="guest_phone"></span>
							</div>
						</div>
					</div>

					<div class="dak-field">
						<label for="dak-booking-notes"><?php esc_html_e( 'Notes (optional)', 'doctor-ak-portal' ); ?></label>
						<textarea id="dak-booking-notes" name="notes" rows="2"></textarea>
					</div>

					<input type="hidden" name="payment_choice" id="dak-booking-payment-choice" value="later">

					<div id="dak-booking-submit-single">
						<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-booking-submit">
							<span class="dak-button-label"><?php esc_html_e( 'Book Consultation', 'doctor-ak-portal' ); ?></span>
						</button>
					</div>

					<div class="dak-booking-payment-choice dak-hidden" id="dak-booking-submit-choice">
						<p class="dak-field-hint"><?php esc_html_e( 'This appointment has a charge. Pay now online, or book now and pay at the clinic.', 'doctor-ak-portal' ); ?></p>
						<button type="submit" class="dak-button dak-button-secondary dak-button-block" id="dak-booking-pay-later" data-payment-choice="later">
							<span class="dak-button-label"><?php esc_html_e( 'Book Now — Pay Later', 'doctor-ak-portal' ); ?></span>
						</button>
						<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-booking-pay-now" data-payment-choice="now">
							<span class="dak-button-label"><?php esc_html_e( 'Pay Now', 'doctor-ak-portal' ); ?></span>
						</button>
					</div>
				</section>

			</div>

			<aside class="dak-booking-sidebar">
				<div class="dak-booking-summary-card">
					<h3><?php esc_html_e( 'Your Booking', 'doctor-ak-portal' ); ?></h3>

					<ul class="dak-booking-summary-list" id="dak-booking-summary-list">
						<li class="dak-hidden" data-summary-row="doctor"><span class="dak-booking-summary-icon" aria-hidden="true">&#128100;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="type"><span class="dak-booking-summary-icon" aria-hidden="true">&#128205;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="service"><span class="dak-booking-summary-icon" aria-hidden="true">&#128203;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="date"><span class="dak-booking-summary-icon" aria-hidden="true">&#128197;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="time"><span class="dak-booking-summary-icon" aria-hidden="true">&#128337;</span><span data-summary-value></span></li>
						<li class="dak-hidden" data-summary-row="instant"><span class="dak-booking-summary-icon" aria-hidden="true">&#9889;</span><span data-summary-value></span></li>
					</ul>

					<p class="dak-empty-state dak-booking-summary-empty" id="dak-booking-summary-empty"><?php esc_html_e( 'Choose a doctor, date, and time to see your booking summary.', 'doctor-ak-portal' ); ?></p>

					<div class="dak-booking-summary-total">
						<span><?php esc_html_e( 'Total', 'doctor-ak-portal' ); ?></span>
						<span id="dak-booking-summary-total-amount">&mdash;</span>
					</div>

					<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-booking-continue"><?php esc_html_e( 'Continue to confirm', 'doctor-ak-portal' ); ?></button>
					<p class="dak-booking-summary-note" id="dak-booking-summary-cancellation-note"><?php esc_html_e( 'Choose a doctor to see their cancellation policy.', 'doctor-ak-portal' ); ?></p>
				</div>
			</aside>
		</div>
	</form>
</div>
