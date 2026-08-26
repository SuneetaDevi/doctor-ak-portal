<?php
/**
 * Template: Public service detail page for the [service_profile_view]
 * shortcode — one service NAME, with a "Doctors & Pricing" breakdown (see
 * Service_Profile_View::build_group()).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array|null $group {
 *     Null if no valid/active service_id was given.
 *
 *     @type string $name          Service name.
 *     @type string $description   Full description, or '' if none set on any doctor's row.
 *     @type string $image_url     Service image URL, or '' if none uploaded on any doctor's row.
 *     @type string $price_label   Overall price range across every doctor, e.g. "PKR 5,000" or "From PKR 5,000".
 *     @type array  $doctor_offers One entry per doctor offering this service {
 *         @type int    $doctor_id          Doctor's user ID.
 *         @type string $doctor_name        Doctor's display name.
 *         @type string $doctor_avatar_url  Doctor's photo (or fallback avatar) URL.
 *         @type string $doctor_profile_url URL of the doctor's own [doctor_profile_view] page.
 *         @type string $price_label        This doctor's own price (or price range across their clinics).
 *         @type array  $clinic_locations   This doctor's clinics offering it, each with an added 'price'/'price_label'.
 *         @type string $booking_url        "Book Appointment" link, pre-selecting this doctor.
 *     }
 * }
 * @var string $directory_url "All Services" breadcrumb link.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_service_view_icons = array(
	'image'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><circle cx="7" cy="8" r="1.5"/><path d="M17.5 13.5l-4-4-3 3-2.5-2.5-5 5"/></svg>',
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'person'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'badge'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l1.7 1.9 2.5-.4.4 2.5 1.9 1.7-1.9 1.7-.4 2.5-2.5-.4L10 13.9l-1.7-1.9-2.5.4-.4-2.5-1.9-1.7 1.9-1.7.4-2.5 2.5.4z"/><path d="M8 10l1.4 1.4L12.5 8"/></svg>',
);
?>
<div class="dak-portal dak-directory">
	<?php if ( ! $group ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'Service not found.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php if ( $directory_url ) : ?>
			<nav class="dak-profile-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'doctor-ak-portal' ); ?>">
				<a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></a>
				<span aria-hidden="true">›</span>
				<span><?php echo esc_html( $group['name'] ); ?></span>
			</nav>
		<?php endif; ?>

		<div class="dak-profile-header-card">
			<span class="dak-avatar dak-avatar-lg">
				<?php if ( $group['image_url'] ) : ?>
					<img src="<?php echo esc_url( $group['image_url'] ); ?>" alt="">
				<?php else : ?>
					<?php echo $dak_service_view_icons['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</span>

			<div class="dak-profile-header-main">
				<h1><?php echo esc_html( $group['name'] ); ?></h1>

				<div class="dak-profile-stats">
					<span class="dak-profile-stat">
						<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_service_view_icons['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html( $group['price_label'] ); ?></strong>
						<span><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></span>
					</span>

					<?php if ( ! empty( $group['doctor_offers'] ) ) : ?>
						<a class="dak-profile-stat dak-profile-stat-link" href="#dak-service-doctors">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_service_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php echo count( $group['doctor_offers'] ); ?></strong>
							<span><?php echo esc_html( _n( 'Doctor', 'Doctors', count( $group['doctor_offers'] ), 'doctor-ak-portal' ) ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="dak-profile-layout">
			<div class="dak-profile-main">
				<?php if ( '' !== $group['description'] ) : ?>
					<div class="dak-profile-card">
						<h2><?php esc_html_e( 'About This Service', 'doctor-ak-portal' ); ?></h2>
						<p class="dak-profile-expertise"><?php echo nl2br( esc_html( $group['description'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nl2br() output of already-escaped text. ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $group['doctor_offers'] ) ) : ?>
					<div class="dak-profile-card" id="dak-service-doctors">
						<h2>
							<span class="dak-profile-card-title-icon" aria-hidden="true"><?php echo $dak_service_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Doctors & Pricing', 'doctor-ak-portal' ); ?>
						</h2>

						<div class="dak-service-doctor-offers">
							<?php foreach ( $group['doctor_offers'] as $dak_offer ) : ?>
								<div class="dak-service-doctor-offer">
									<div class="dak-service-doctor-offer-header">
										<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
											<?php if ( $dak_offer['doctor_avatar_url'] ) : ?>
												<img src="<?php echo esc_url( $dak_offer['doctor_avatar_url'] ); ?>" alt="">
											<?php else : ?>
												<?php echo $dak_service_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php endif; ?>
										</span>

										<div class="dak-service-doctor-offer-info">
											<?php if ( $dak_offer['doctor_profile_url'] ) : ?>
												<a class="dak-profile-clinic-info-link" href="<?php echo esc_url( $dak_offer['doctor_profile_url'] ); ?>"><strong><?php echo esc_html( sprintf( 'Dr. %s', $dak_offer['doctor_name'] ) ); ?></strong></a>
											<?php else : ?>
												<strong><?php echo esc_html( sprintf( 'Dr. %s', $dak_offer['doctor_name'] ) ); ?></strong>
											<?php endif; ?>
											<span class="dak-service-doctor-offer-price"><?php echo esc_html( $dak_offer['price_label'] ); ?></span>
										</div>

										<?php if ( $dak_offer['booking_url'] ) : ?>
											<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $dak_offer['booking_url'] ); ?>">
												<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
											</a>
										<?php endif; ?>
									</div>

									<?php if ( ! empty( $dak_offer['clinic_locations'] ) ) : ?>
										<div class="dak-profile-clinics dak-service-doctor-offer-clinics">
											<?php foreach ( $dak_offer['clinic_locations'] as $clinic_location ) : ?>
												<div class="dak-profile-clinic-row">
													<div class="dak-profile-clinic-info">
														<strong><?php echo esc_html( $clinic_location['name'] ); ?></strong>
														<?php if ( '' !== $clinic_location['address'] || '' !== $clinic_location['area_label'] || '' !== $clinic_location['city_label'] ) : ?>
															<span class="dak-profile-clinic-meta">
																<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_service_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
																<?php echo esc_html( implode( ', ', array_filter( array( $clinic_location['address'], $clinic_location['area_label'], $clinic_location['city_label'] ) ) ) ); ?>
															</span>
														<?php endif; ?>
													</div>

													<div class="dak-profile-clinic-fee">
														<span><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></span>
														<strong><?php echo esc_html( $clinic_location['price_label'] ); ?></strong>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<aside class="dak-profile-sidebar">
				<div class="dak-profile-card dak-profile-booking-card">
					<span class="dak-eyebrow"><?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?></span>
					<h2><?php echo esc_html( $group['name'] ); ?></h2>
					<p class="dak-profile-booking-hint"><?php esc_html_e( 'Pick a doctor above to book with — you\'ll choose a clinic, date and time on the next step.', 'doctor-ak-portal' ); ?></p>

					<div class="dak-profile-booking-fee">
						<span><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></span>
						<strong><?php echo esc_html( $group['price_label'] ); ?></strong>
					</div>

					<?php if ( ! empty( $group['doctor_offers'] ) ) : ?>
						<a class="dak-button dak-button-primary dak-button-block" href="#dak-service-doctors">
							<?php esc_html_e( 'Choose a Doctor', 'doctor-ak-portal' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	<?php endif; ?>
</div>
