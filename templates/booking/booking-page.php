<?php
/**
 * Template: Full booking page for the [book_appointment] shortcode — a
 * 4-step flow (Doctor & visit -> Date & time -> Your details -> Review &
 * payment) followed by a separate confirmation result, with a persistent
 * booking summary beside the active step (an expandable panel on mobile).
 *
 * Times are chosen before personal details so a patient can see what's
 * available first. "Your details" is skipped entirely for a logged-in
 * patient with a phone on file (see $identity_fully_known); "Doctor & visit"
 * is skipped when the entry link already decided everything it would ask
 * (see $selection_fully_known) — both computed by
 * Booking_Page::resolved_selection()/identity_fully_known() and re-applied
 * by assets/js/doctor-ak-booking-page.js. Field names, hidden-input IDs and
 * AJAX actions are unchanged, so Booking_Handler receives exactly what it
 * did before.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $doctor_cards           Doctor cards, see Booking_Page::doctor_cards_data().
 * @var array  $specialization_options Specialization slug => label, restricted to specializations at least one listed doctor has.
 * @var int    $selected_doctor_id     Preselected doctor's user ID, or 0.
 * @var string $selected_doctor_name   Preselected doctor's display name (no "Dr." prefix).
 * @var string $selected_type          'clinic' or 'video'.
 * @var bool   $video_disabled         Whether the preselected doctor doesn't offer video consultations.
 * @var int[]  $selected_service_ids   Preselected, validated service ids (clinic type only), or an empty array.
 * @var int    $selected_clinic_id     Preselected, validated Clinics row id, or 0.
 * @var bool   $selection_fully_known  Whether the Doctor & visit step can be skipped entirely.
 * @var bool   $identity_fully_known   Whether the Your details step can be skipped entirely.
 * @var string $contact_url            "Need help?" link target.
 * @var string $timezone_label         Site timezone every slot time is expressed in, e.g. "Asia/Karachi (UTC+05:00)".
 * @var bool   $is_staff               Whether the current viewer is an Administrator/Receptionist booking on behalf of a patient.
 * @var array  $patient_options        Patient user ID => display name, only populated when $is_staff.
 * @var int    $selected_patient_id    Preselected patient's user ID, or 0. Only meaningful when $is_staff.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$timezone_label = isset( $timezone_label ) ? $timezone_label : '';

$dak_bk_steps = array(
	'selection' => __( 'Doctor & visit', 'doctor-ak-portal' ),
	'schedule'  => __( 'Date & time', 'doctor-ak-portal' ),
	'identity'  => __( 'Your details', 'doctor-ak-portal' ),
	'review'    => __( 'Review & payment', 'doctor-ak-portal' ),
);

$dak_bk_icon_check = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>';
?>
<div class="dak-portal dak-booking-page dak-booking-wizard">
	<header class="dak-bk-head">
		<div>
			<h1><?php esc_html_e( 'Book an appointment', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Choose a doctor, see open times, then confirm your details.', 'doctor-ak-portal' ); ?></p>
		</div>
		<?php if ( $contact_url ) : ?>
			<a class="dak-bk-help" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Need help?', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</header>

	<nav class="dak-bk-progress-wrap" aria-label="<?php esc_attr_e( 'Booking progress', 'doctor-ak-portal' ); ?>">
		<ol class="dak-bk-progress" id="dak-booking-steps">
			<?php $dak_bk_n = 0; ?>
			<?php foreach ( $dak_bk_steps as $dak_bk_key => $dak_bk_label ) : ?>
				<?php ++$dak_bk_n; ?>
				<li class="dak-booking-wizard-step dak-bk-progress-step" data-step="<?php echo esc_attr( $dak_bk_key ); ?>">
					<button type="button" class="dak-bk-progress-btn" data-goto-step="<?php echo esc_attr( $dak_bk_key ); ?>" disabled>
						<span class="dak-bk-progress-num" aria-hidden="true"><span class="dak-bk-progress-n"><?php echo esc_html( $dak_bk_n ); ?></span><?php echo $dak_bk_icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<span class="dak-bk-progress-label"><?php echo esc_html( $dak_bk_label ); ?></span>
						<span class="dak-visually-hidden dak-bk-progress-state"></span>
					</button>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>

	<form id="dak-booking-form" novalidate>
		<input type="hidden" name="doctor_id" id="dak-booking-doctor-id" value="<?php echo esc_attr( $selected_doctor_id ); ?>">
		<input type="hidden" name="type" id="dak-booking-type" value="<?php echo esc_attr( $selected_type ); ?>">
		<input type="hidden" name="service_id" id="dak-booking-service-id" value="">
		<input type="hidden" name="clinic_id" id="dak-booking-clinic-id" value="">
		<input type="hidden" name="date" id="dak-booking-date" value="">
		<input type="hidden" name="time" id="dak-booking-time" value="">
		<input type="hidden" name="payment_choice" id="dak-booking-payment-choice" value="">

		<div class="dak-bk-layout">
			<div class="dak-bk-main">
				<div class="dak-alert dak-alert-error dak-hidden" id="dak-booking-error" role="alert" tabindex="-1"></div>

				<!-- Step 1: Doctor & visit -->
				<section class="dak-booking-card dak-bk-step" id="dak-booking-step-selection" aria-labelledby="dak-bk-title-selection">
					<div class="dak-bk-step-head">
						<span class="dak-bk-step-count"><?php esc_html_e( 'Step 1 of 4', 'doctor-ak-portal' ); ?></span>
						<h2 id="dak-bk-title-selection" tabindex="-1"><?php esc_html_e( 'Doctor & visit', 'doctor-ak-portal' ); ?></h2>
					</div>

					<div class="dak-bk-selected-doctor dak-hidden" id="dak-bk-selected-doctor">
						<span class="dak-bk-avatar" id="dak-bk-selected-doctor-avatar" aria-hidden="true"></span>
						<span class="dak-bk-selected-doctor-text">
							<span class="dak-bk-eyebrow"><?php esc_html_e( 'Your doctor', 'doctor-ak-portal' ); ?></span>
							<strong id="dak-bk-selected-doctor-name"></strong>
							<span id="dak-bk-selected-doctor-spec"></span>
						</span>
						<button type="button" class="dak-button dak-button-secondary dak-button-sm" id="dak-bk-change-doctor"><?php esc_html_e( 'Change doctor', 'doctor-ak-portal' ); ?></button>
					</div>

					<div class="dak-bk-doctor-picker" id="dak-bk-doctor-picker">
						<div class="dak-bk-filters">
							<div class="dak-field dak-bk-filter-search">
								<label for="dak-booking-doctor-search"><?php esc_html_e( 'Find a doctor', 'doctor-ak-portal' ); ?></label>
								<input type="search" id="dak-booking-doctor-search" placeholder="<?php esc_attr_e( 'Search by name', 'doctor-ak-portal' ); ?>" autocomplete="off">
							</div>
							<?php if ( ! empty( $specialization_options ) ) : ?>
								<div class="dak-field dak-bk-filter-spec">
									<label for="dak-booking-doctor-specialization-filter"><?php esc_html_e( 'Specialty', 'doctor-ak-portal' ); ?></label>
									<select id="dak-booking-doctor-specialization-filter">
										<option value=""><?php esc_html_e( 'All specialties', 'doctor-ak-portal' ); ?></option>
										<?php foreach ( $specialization_options as $dak_spec_slug => $dak_spec_label ) : ?>
											<option value="<?php echo esc_attr( $dak_spec_slug ); ?>"><?php echo esc_html( $dak_spec_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endif; ?>
						</div>
						<p class="dak-bk-result-count" id="dak-bk-doctor-count" aria-live="polite"></p>

						<ul class="dak-bk-doctor-list" id="dak-booking-doctor-cards" aria-label="<?php esc_attr_e( 'Doctors', 'doctor-ak-portal' ); ?>">
							<?php foreach ( $doctor_cards as $card ) : ?>
								<li class="dak-bk-doctor-item">
									<button
										type="button"
										class="dak-booking-doctor-card dak-bk-doctor<?php echo (int) $card['id'] === (int) $selected_doctor_id ? ' is-selected' : ''; ?>"
										aria-pressed="<?php echo (int) $card['id'] === (int) $selected_doctor_id ? 'true' : 'false'; ?>"
										data-doctor-card
										data-doctor-id="<?php echo esc_attr( $card['id'] ); ?>"
										data-doctor-name="<?php echo esc_attr( $card['name'] ); ?>"
										data-doctor-initials="<?php echo esc_attr( $card['initials'] ); ?>"
										data-doctor-avatar="<?php echo esc_url( $card['avatar_url'] ); ?>"
										data-doctor-spec="<?php echo esc_attr( '' !== $card['specialization'] ? $card['specialization'] : __( 'General Physician', 'doctor-ak-portal' ) ); ?>"
										data-search-name="<?php echo esc_attr( mb_strtolower( $card['name'] ) ); ?>"
										data-search-specializations="<?php echo esc_attr( implode( ',', $card['specialization_slugs'] ) ); ?>"
										<?php if ( $card['video_disabled'] ) : ?>data-video-disabled="1"<?php endif; ?>
									>
										<span class="dak-bk-avatar" aria-hidden="true">
											<?php if ( $card['avatar_url'] ) : ?>
												<img src="<?php echo esc_url( $card['avatar_url'] ); ?>" alt="" loading="lazy">
											<?php else : ?>
												<?php echo esc_html( $card['initials'] ); ?>
											<?php endif; ?>
										</span>
										<span class="dak-bk-doctor-text">
											<strong><?php echo esc_html( sprintf( 'Dr. %s', $card['name'] ) ); ?></strong>
											<span><?php echo esc_html( '' !== $card['specialization'] ? $card['specialization'] : __( 'General Physician', 'doctor-ak-portal' ) ); ?></span>
										</span>
										<span class="dak-bk-doctor-tags">
											<span class="dak-bk-tag"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></span>
											<?php if ( ! $card['video_disabled'] ) : ?>
												<span class="dak-bk-tag"><?php esc_html_e( 'Video', 'doctor-ak-portal' ); ?></span>
											<?php endif; ?>
										</span>
										<span class="dak-bk-check" aria-hidden="true"><?php echo $dak_bk_icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="dak-empty-state dak-hidden" id="dak-booking-doctor-no-results">
							<?php esc_html_e( 'No doctors match your search.', 'doctor-ak-portal' ); ?>
							<button type="button" class="dak-link-button" id="dak-bk-clear-doctor-filters"><?php esc_html_e( 'Clear filters', 'doctor-ak-portal' ); ?></button>
						</p>
					</div>
					<span class="dak-field-error" data-field="doctor_id"></span>

					<div class="dak-bk-visit dak-hidden" id="dak-bk-visit">
						<fieldset class="dak-bk-fieldset">
							<legend><?php esc_html_e( 'Visit type', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-bk-segmented" id="dak-bk-type-group">
								<label class="dak-bk-segment">
									<input type="radio" name="dak_bk_visit_type" value="clinic" class="dak-booking-segment" data-type="clinic" <?php checked( 'clinic', $selected_type ); ?>>
									<span><strong><?php esc_html_e( 'Clinic visit', 'doctor-ak-portal' ); ?></strong><small><?php esc_html_e( 'In person', 'doctor-ak-portal' ); ?></small></span>
								</label>
								<label class="dak-bk-segment">
									<input type="radio" name="dak_bk_visit_type" value="video" class="dak-booking-segment" data-type="video" <?php checked( 'video', $selected_type ); ?> <?php disabled( $video_disabled ); ?>>
									<span><strong><?php esc_html_e( 'Online video', 'doctor-ak-portal' ); ?></strong><small id="dak-bk-video-sub"><?php esc_html_e( 'From home', 'doctor-ak-portal' ); ?></small></span>
								</label>
							</div>
							<p class="dak-field-hint dak-hidden" id="dak-booking-video-unavailable"><?php esc_html_e( 'This doctor does not offer online video consultations.', 'doctor-ak-portal' ); ?></p>
						</fieldset>

						<fieldset class="dak-bk-fieldset dak-hidden" id="dak-booking-clinic-section">
							<legend><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-bk-options" id="dak-booking-clinic-cards"></div>
							<span class="dak-field-error" data-field="clinic_id"></span>
						</fieldset>
						<p class="dak-field-hint dak-hidden" id="dak-booking-clinic-hint"><?php esc_html_e( 'This doctor has no clinic locations listed yet — the clinic will share the address when your appointment is confirmed.', 'doctor-ak-portal' ); ?></p>

						<fieldset class="dak-bk-fieldset" id="dak-booking-service-section">
							<legend><span id="dak-booking-service-label"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></span> <span class="dak-bk-legend-hint" id="dak-bk-service-legend-hint"><?php esc_html_e( 'Choose one or more', 'doctor-ak-portal' ); ?></span></legend>
							<div class="dak-field dak-bk-service-search dak-hidden" id="dak-bk-service-search-wrap">
								<label class="dak-visually-hidden" for="dak-bk-service-search"><?php esc_html_e( 'Search services', 'doctor-ak-portal' ); ?></label>
								<input type="search" id="dak-bk-service-search" placeholder="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>" autocomplete="off">
							</div>
							<div class="dak-bk-options" id="dak-booking-service-cards"></div>
							<button type="button" class="dak-link-button dak-hidden" id="dak-bk-service-more" aria-expanded="false"></button>
							<div class="dak-bk-notice dak-hidden" id="dak-bk-no-services" role="status"></div>
							<span class="dak-field-error" data-field="service_id"></span>
						</fieldset>
					</div>

					<div class="dak-booking-wizard-nav dak-bk-nav">
						<span></span>
						<button type="button" class="dak-button dak-button-primary" data-wizard-next="schedule"><?php esc_html_e( 'Continue', 'doctor-ak-portal' ); ?></button>
					</div>
				</section>

				<!-- Step 2: Date & time -->
				<section class="dak-booking-card dak-bk-step dak-hidden" id="dak-booking-step-schedule" aria-labelledby="dak-bk-title-schedule">
					<div class="dak-bk-step-head">
						<span class="dak-bk-step-count"><?php esc_html_e( 'Step 2 of 4', 'doctor-ak-portal' ); ?></span>
						<h2 id="dak-bk-title-schedule" tabindex="-1"><?php esc_html_e( 'Date & time', 'doctor-ak-portal' ); ?></h2>
						<?php if ( '' !== $timezone_label ) : ?>
							<p class="dak-bk-tz"><?php echo esc_html( sprintf( /* translators: %s: timezone label. */ __( 'All times are in %s.', 'doctor-ak-portal' ), $timezone_label ) ); ?></p>
						<?php endif; ?>
					</div>

					<div class="dak-bk-cal">
						<div class="dak-bk-cal-head">
							<button type="button" class="dak-icon-button" id="dak-booking-strip-prev" aria-label="<?php esc_attr_e( 'Previous week', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.5 5l-5 5 5 5"/></svg></button>
							<span id="dak-booking-cal-title" class="dak-bk-cal-title" aria-live="polite"></span>
							<button type="button" class="dak-icon-button" id="dak-booking-strip-next" aria-label="<?php esc_attr_e( 'Next week', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 5l5 5-5 5"/></svg></button>
						</div>
						<div class="dak-bk-days" id="dak-booking-date-strip" role="group" aria-label="<?php esc_attr_e( 'Choose a date', 'doctor-ak-portal' ); ?>"></div>
						<ul class="dak-bk-legend" aria-label="<?php esc_attr_e( 'Date legend', 'doctor-ak-portal' ); ?>">
							<li><span class="dak-bk-swatch is-available" aria-hidden="true"></span><?php esc_html_e( 'Available', 'doctor-ak-portal' ); ?></li>
							<li><span class="dak-bk-swatch is-few" aria-hidden="true"></span><?php esc_html_e( 'Few left', 'doctor-ak-portal' ); ?></li>
							<li><span class="dak-bk-swatch is-full" aria-hidden="true"></span><?php esc_html_e( 'Fully booked', 'doctor-ak-portal' ); ?></li>
							<li><span class="dak-bk-swatch is-none" aria-hidden="true"></span><?php esc_html_e( 'Unavailable', 'doctor-ak-portal' ); ?></li>
						</ul>
					</div>
					<span class="dak-field-error" data-field="date"></span>

					<div class="dak-bk-slots">
						<h3 class="dak-bk-subhead" id="dak-bk-slots-title"><?php esc_html_e( 'Available times', 'doctor-ak-portal' ); ?></h3>
						<div class="dak-bk-slots-status" id="dak-bk-slots-status" role="status" aria-live="polite"></div>
						<div id="dak-booking-slots-groups" aria-labelledby="dak-bk-slots-title"></div>
					</div>
					<span class="dak-field-error" data-field="time"></span>

					<div class="dak-bk-picked dak-hidden" id="dak-booking-currently-selected" role="status">
						<span class="dak-bk-check" aria-hidden="true"><?php echo $dak_bk_icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<span><?php esc_html_e( 'Selected:', 'doctor-ak-portal' ); ?> <strong id="dak-booking-currently-selected-text"></strong></span>
					</div>

					<div class="dak-booking-wizard-nav dak-bk-nav">
						<button type="button" class="dak-button dak-button-secondary" data-wizard-back="selection"><?php esc_html_e( 'Back', 'doctor-ak-portal' ); ?></button>
						<button type="button" class="dak-button dak-button-primary" data-wizard-next="identity"><?php esc_html_e( 'Continue', 'doctor-ak-portal' ); ?></button>
					</div>
				</section>

				<!-- Step 3: Your details -->
				<section class="dak-booking-card dak-bk-step dak-hidden" id="dak-booking-step-identity" aria-labelledby="dak-bk-title-identity">
					<div class="dak-bk-step-head">
						<span class="dak-bk-step-count"><?php esc_html_e( 'Step 3 of 4', 'doctor-ak-portal' ); ?></span>
						<h2 id="dak-bk-title-identity" tabindex="-1"><?php echo esc_html( $is_staff ? __( 'Patient details', 'doctor-ak-portal' ) : __( 'Your details', 'doctor-ak-portal' ) ); ?></h2>
					</div>

					<div id="dak-booking-identity-staff" class="dak-hidden">
						<div class="dak-field">
							<span class="dak-field-label" id="dak-bk-patient-label"><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></span>
							<input type="hidden" id="dak-booking-patient-id" name="patient_id" value="<?php echo esc_attr( $selected_patient_id ); ?>">

							<?php if ( count( $patient_options ) > 8 ) : ?>
								<input type="search" class="dak-booking-patient-search" id="dak-booking-patient-search" placeholder="<?php esc_attr_e( 'Search patients…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search patients', 'doctor-ak-portal' ); ?>">
							<?php endif; ?>

							<div class="dak-booking-patient-cards dak-bk-options" id="dak-booking-patient-cards" role="group" aria-labelledby="dak-bk-patient-label">
								<button type="button" class="dak-booking-patient-card dak-bk-option dak-booking-patient-card-add<?php echo 0 === $selected_patient_id ? ' is-selected' : ''; ?>" aria-pressed="<?php echo 0 === $selected_patient_id ? 'true' : 'false'; ?>" data-patient-card data-patient-id="" data-patient-name="">
									<span class="dak-bk-option-text"><strong><?php esc_html_e( 'Add new patient', 'doctor-ak-portal' ); ?></strong><span><?php esc_html_e( 'Enter their details below', 'doctor-ak-portal' ); ?></span></span>
									<span class="dak-bk-check" aria-hidden="true"><?php echo $dak_bk_icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
								</button>
								<?php foreach ( $patient_options as $dak_patient_id => $dak_patient_name ) : ?>
									<button
										type="button"
										class="dak-booking-patient-card dak-bk-option<?php echo $selected_patient_id === $dak_patient_id ? ' is-selected' : ''; ?>"
										aria-pressed="<?php echo $selected_patient_id === $dak_patient_id ? 'true' : 'false'; ?>"
										data-patient-card
										data-patient-id="<?php echo esc_attr( $dak_patient_id ); ?>"
										data-patient-name="<?php echo esc_attr( mb_strtolower( $dak_patient_name ) ); ?>"
										data-patient-label="<?php echo esc_attr( $dak_patient_name ); ?>"
									>
										<span class="dak-bk-option-text"><strong><?php echo esc_html( $dak_patient_name ); ?></strong></span>
										<span class="dak-bk-check" aria-hidden="true"><?php echo $dak_bk_icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									</button>
								<?php endforeach; ?>
							</div>
							<p class="dak-field-hint dak-hidden" id="dak-booking-patient-cards-empty"><?php esc_html_e( 'No patients match your search.', 'doctor-ak-portal' ); ?></p>
							<span class="dak-field-error" data-field="patient_id"></span>
						</div>
					</div>

					<div id="dak-booking-identity-loggedin" class="dak-hidden">
						<dl class="dak-bk-facts">
							<div><dt><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></dt><dd id="dak-booking-loggedin-name"></dd></div>
							<div><dt><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?></dt><dd id="dak-booking-loggedin-email"></dd></div>
							<div><dt><?php esc_html_e( 'Mobile number', 'doctor-ak-portal' ); ?></dt><dd id="dak-booking-loggedin-phone"></dd></div>
						</dl>
						<p class="dak-field-hint"><?php esc_html_e( 'These come from your account.', 'doctor-ak-portal' ); ?> <a href="#" class="dak-bk-profile-link"><?php esc_html_e( 'Update your profile', 'doctor-ak-portal' ); ?></a></p>
						<div class="dak-bk-notice is-warning dak-hidden" id="dak-booking-loggedin-phone-missing" role="alert">
							<?php esc_html_e( 'A mobile number is required to book a video consultation.', 'doctor-ak-portal' ); ?>
							<a href="#" class="dak-bk-profile-link" id="dak-booking-loggedin-phone-missing-link"><?php esc_html_e( 'Add one to your profile', 'doctor-ak-portal' ); ?></a>
						</div>
					</div>

					<div id="dak-booking-identity-choice" class="dak-bk-account dak-hidden">
						<p><?php esc_html_e( 'Have an account? Sign in to manage this appointment from your dashboard. Your selections are kept.', 'doctor-ak-portal' ); ?></p>
						<div class="dak-bk-account-links">
							<a class="dak-button dak-button-secondary dak-button-sm" id="dak-booking-login-link" href="#"><?php esc_html_e( 'Log in', 'doctor-ak-portal' ); ?></a>
							<a class="dak-button dak-button-secondary dak-button-sm" id="dak-booking-register-link" href="#"><?php esc_html_e( 'Create an account', 'doctor-ak-portal' ); ?></a>
						</div>
					</div>

					<div id="dak-booking-identity-guest" class="dak-bk-guest dak-hidden">
						<p class="dak-bk-guest-intro" id="dak-bk-guest-intro"><?php esc_html_e( 'Or continue as a guest:', 'doctor-ak-portal' ); ?></p>
						<div class="dak-field">
							<label for="dak-booking-guest-name"><?php esc_html_e( 'Full name', 'doctor-ak-portal' ); ?> <span class="dak-bk-req"><?php esc_html_e( 'Required', 'doctor-ak-portal' ); ?></span></label>
							<input type="text" id="dak-booking-guest-name" name="guest_name" autocomplete="name" required aria-describedby="dak-bk-err-guest_name">
							<span class="dak-field-error" data-field="guest_name" id="dak-bk-err-guest_name"></span>
						</div>
						<div class="dak-field-row">
							<div class="dak-field">
								<label for="dak-booking-guest-email"><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?> <span class="dak-bk-req"><?php esc_html_e( 'Required', 'doctor-ak-portal' ); ?></span></label>
								<input type="email" id="dak-booking-guest-email" name="guest_email" autocomplete="email" inputmode="email" required aria-describedby="dak-bk-hint-email dak-bk-err-guest_email">
								<span class="dak-field-hint" id="dak-bk-hint-email"><?php esc_html_e( 'We send the appointment details here.', 'doctor-ak-portal' ); ?></span>
								<span class="dak-field-error" data-field="guest_email" id="dak-bk-err-guest_email"></span>
							</div>
							<div class="dak-field">
								<label for="dak-booking-guest-phone"><?php esc_html_e( 'Mobile number', 'doctor-ak-portal' ); ?> <span class="dak-bk-req dak-hidden" id="dak-booking-guest-phone-required"><?php esc_html_e( 'Required', 'doctor-ak-portal' ); ?></span><span class="dak-optional" id="dak-bk-phone-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></span></label>
								<input type="tel" id="dak-booking-guest-phone" name="guest_phone" autocomplete="tel" inputmode="tel" placeholder="03xxxxxxxxx" aria-describedby="dak-bk-hint-phone dak-bk-err-guest_phone">
								<span class="dak-field-hint" id="dak-bk-hint-phone"></span>
								<span class="dak-field-error" data-field="guest_phone" id="dak-bk-err-guest_phone"></span>
							</div>
						</div>
					</div>

					<div class="dak-booking-wizard-nav dak-bk-nav">
						<button type="button" class="dak-button dak-button-secondary" data-wizard-back="schedule"><?php esc_html_e( 'Back', 'doctor-ak-portal' ); ?></button>
						<button type="button" class="dak-button dak-button-primary" id="dak-booking-identity-next" data-wizard-next="review"><?php esc_html_e( 'Continue', 'doctor-ak-portal' ); ?></button>
					</div>
				</section>

				<!-- Step 4: Review & payment -->
				<section class="dak-booking-card dak-bk-step dak-hidden" id="dak-booking-step-review" aria-labelledby="dak-bk-title-review">
					<div class="dak-bk-step-head">
						<span class="dak-bk-step-count"><?php esc_html_e( 'Step 4 of 4', 'doctor-ak-portal' ); ?></span>
						<h2 id="dak-bk-title-review" tabindex="-1"><?php esc_html_e( 'Review & payment', 'doctor-ak-portal' ); ?></h2>
					</div>

					<div class="dak-bk-review">
						<div class="dak-bk-review-block">
							<div class="dak-bk-review-head">
								<h3 class="dak-bk-subhead"><?php esc_html_e( 'Appointment', 'doctor-ak-portal' ); ?></h3>
								<button type="button" class="dak-link-button" data-goto-step="selection"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?><span class="dak-visually-hidden"> <?php esc_html_e( 'doctor and visit', 'doctor-ak-portal' ); ?></span></button>
							</div>
							<dl class="dak-bk-facts" id="dak-bk-review-appointment"></dl>
						</div>
						<div class="dak-bk-review-block">
							<div class="dak-bk-review-head">
								<h3 class="dak-bk-subhead"><?php esc_html_e( 'Date & time', 'doctor-ak-portal' ); ?></h3>
								<button type="button" class="dak-link-button" data-goto-step="schedule"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?><span class="dak-visually-hidden"> <?php esc_html_e( 'date and time', 'doctor-ak-portal' ); ?></span></button>
							</div>
							<dl class="dak-bk-facts" id="dak-bk-review-when"></dl>
						</div>
						<div class="dak-bk-review-block">
							<div class="dak-bk-review-head">
								<h3 class="dak-bk-subhead"><?php echo esc_html( $is_staff ? __( 'Patient', 'doctor-ak-portal' ) : __( 'Your details', 'doctor-ak-portal' ) ); ?></h3>
								<button type="button" class="dak-link-button" id="dak-bk-review-edit-details" data-goto-step="identity"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?><span class="dak-visually-hidden"> <?php esc_html_e( 'details', 'doctor-ak-portal' ); ?></span></button>
							</div>
							<dl class="dak-bk-facts" id="dak-bk-review-patient"></dl>
						</div>

						<div class="dak-bk-review-block">
							<h3 class="dak-bk-subhead"><?php esc_html_e( 'Charges', 'doctor-ak-portal' ); ?></h3>
							<table class="dak-bk-charges" id="dak-bk-review-charges">
								<tbody></tbody>
								<tfoot><tr><th scope="row"><?php esc_html_e( 'Total', 'doctor-ak-portal' ); ?></th><td id="dak-bk-review-total"></td></tr></tfoot>
							</table>
						</div>

						<fieldset class="dak-bk-fieldset dak-bk-payment dak-hidden" id="dak-booking-submit-choice">
							<legend><?php esc_html_e( 'How would you like to pay?', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-bk-options">
								<label class="dak-bk-option dak-bk-option-radio">
									<input type="radio" name="dak_bk_payment" value="now" id="dak-booking-pay-now" data-payment-choice="now">
									<span class="dak-bk-option-text"><strong><?php esc_html_e( 'Pay now online', 'doctor-ak-portal' ); ?></strong><span id="dak-bk-pay-now-desc"></span></span>
								</label>
								<label class="dak-bk-option dak-bk-option-radio">
									<input type="radio" name="dak_bk_payment" value="later" id="dak-booking-pay-later" data-payment-choice="later">
									<span class="dak-bk-option-text"><strong><?php esc_html_e( 'Pay later', 'doctor-ak-portal' ); ?></strong><span id="dak-bk-pay-later-desc"></span></span>
								</label>
							</div>
							<span class="dak-field-error" data-field="payment_choice"></span>
						</fieldset>
						<p class="dak-bk-notice dak-hidden" id="dak-bk-payment-offline-note"></p>

						<p class="dak-bk-terms" id="dak-booking-summary-cancellation-note"></p>
					</div>

					<div class="dak-booking-wizard-nav dak-bk-nav">
						<button type="button" class="dak-button dak-button-secondary" data-wizard-back="identity"><?php esc_html_e( 'Back', 'doctor-ak-portal' ); ?></button>
						<button type="submit" class="dak-button dak-button-primary" id="dak-booking-submit">
							<span class="dak-button-label"><?php esc_html_e( 'Book appointment', 'doctor-ak-portal' ); ?></span>
						</button>
					</div>
				</section>

				<!-- Result: Confirmation -->
				<section class="dak-booking-card dak-bk-step dak-bk-result dak-hidden" id="dak-booking-step-confirmation" aria-labelledby="dak-bk-title-confirmation">
					<span class="dak-bk-result-icon" aria-hidden="true"><?php echo $dak_bk_icon_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<h2 id="dak-bk-title-confirmation" tabindex="-1"><?php esc_html_e( 'Appointment booked', 'doctor-ak-portal' ); ?></h2>
					<p class="dak-bk-result-message" id="dak-booking-success" role="status"></p>
					<dl class="dak-bk-facts" id="dak-bk-result-details"></dl>
					<div class="dak-bk-result-actions" id="dak-bk-result-actions"></div>
				</section>
			</div>

			<aside class="dak-bk-aside" aria-label="<?php esc_attr_e( 'Booking summary', 'doctor-ak-portal' ); ?>">
				<details class="dak-bk-summary" id="dak-bk-summary" open>
					<summary>
						<span class="dak-bk-summary-title"><?php esc_html_e( 'Booking summary', 'doctor-ak-portal' ); ?></span>
						<span class="dak-bk-summary-total-mini" id="dak-bk-summary-total-mini"></span>
					</summary>
					<ul class="dak-bk-summary-list" id="dak-booking-summary-list">
						<li data-summary-row="doctor"><span class="dak-bk-summary-k"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></span><span class="dak-bk-summary-v" data-summary-value></span><button type="button" class="dak-link-button" data-goto-step="selection"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?><span class="dak-visually-hidden"> <?php esc_html_e( 'doctor', 'doctor-ak-portal' ); ?></span></button></li>
						<li data-summary-row="type"><span class="dak-bk-summary-k"><?php esc_html_e( 'Visit', 'doctor-ak-portal' ); ?></span><span class="dak-bk-summary-v" data-summary-value></span></li>
						<li data-summary-row="clinic"><span class="dak-bk-summary-k"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></span><span class="dak-bk-summary-v" data-summary-value></span></li>
						<li data-summary-row="service"><span class="dak-bk-summary-k"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></span><span class="dak-bk-summary-v" data-summary-value></span></li>
						<li data-summary-row="when"><span class="dak-bk-summary-k"><?php esc_html_e( 'Date & time', 'doctor-ak-portal' ); ?></span><span class="dak-bk-summary-v" data-summary-value></span><button type="button" class="dak-link-button" data-goto-step="schedule"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?><span class="dak-visually-hidden"> <?php esc_html_e( 'date and time', 'doctor-ak-portal' ); ?></span></button></li>
					</ul>
					<div class="dak-bk-summary-total">
						<span><?php esc_html_e( 'Total', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-booking-summary-total-amount"></strong>
					</div>
					<p class="dak-bk-summary-note" id="dak-bk-summary-note"></p>
				</details>
			</aside>
		</div>
	</form>
</div>
