<?php
/**
 * Template: Public service detail page for the [service_profile_view]
 * shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array|null $service {
 *     Null if no valid/active service_id was given.
 *
 *     @type int    $id               Service's ID (a Services row — see Services class).
 *     @type string $name             Service name.
 *     @type string $description      Full description.
 *     @type string $price_label      Formatted price, e.g. "PKR 5,000" or "Free".
 *     @type string $image_url        Service image URL, or '' if none uploaded.
 *     @type array  $clinic_locations   Clinics this service is offered at { id, name, address, area_label, city_label, phone }.
 *     @type string $doctor_name        Name of the doctor who provides this service.
 *     @type string $doctor_avatar_url  Doctor's photo (or fallback avatar) URL, or '' if no doctor was resolved.
 *     @type string $doctor_profile_url URL of the doctor's own [doctor_profile_view] page, or '' if no doctor was resolved.
 * }
 * @var string $directory_url "All Services" breadcrumb link.
 * @var string $booking_url   "Book Appointment" button target (pre-selects the doctor when exactly one is associated).
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
	<?php if ( ! $service ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'Service not found.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php if ( $directory_url ) : ?>
			<nav class="dak-profile-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'doctor-ak-portal' ); ?>">
				<a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></a>
				<span aria-hidden="true">›</span>
				<span><?php echo esc_html( $service['name'] ); ?></span>
			</nav>
		<?php endif; ?>

		<div class="dak-profile-header-card">
			<span class="dak-avatar dak-avatar-lg">
				<?php if ( $service['image_url'] ) : ?>
					<img src="<?php echo esc_url( $service['image_url'] ); ?>" alt="">
				<?php else : ?>
					<?php echo $dak_service_view_icons['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</span>

			<div class="dak-profile-header-main">
				<h1><?php echo esc_html( $service['name'] ); ?></h1>

				<div class="dak-profile-stats">
					<span class="dak-profile-stat">
						<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_service_view_icons['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html( $service['price_label'] ); ?></strong>
						<span><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></span>
					</span>

					<?php if ( ! empty( $service['clinic_locations'] ) ) : ?>
						<a class="dak-profile-stat dak-profile-stat-link" href="#dak-service-clinics">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_service_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php echo count( $service['clinic_locations'] ); ?></strong>
							<span><?php echo esc_html( _n( 'Clinic', 'Clinics', count( $service['clinic_locations'] ), 'doctor-ak-portal' ) ); ?></span>
						</a>
					<?php endif; ?>

					<?php if ( '' !== $service['doctor_name'] ) : ?>
						<span class="dak-profile-stat">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_service_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php echo esc_html( sprintf( 'Dr. %s', $service['doctor_name'] ) ); ?></strong>
							<span><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></span>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( $booking_url ) : ?>
					<div class="dak-profile-header-actions">
						<a class="dak-button dak-button-primary" href="<?php echo esc_url( $booking_url ); ?>">
							<?php echo $dak_service_view_icons['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="dak-profile-layout">
			<div class="dak-profile-main">
				<?php if ( '' !== $service['doctor_name'] ) : ?>
					<div class="dak-profile-card">
						<h2>
							<span class="dak-profile-card-title-icon" aria-hidden="true"><?php echo $dak_service_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Provided By', 'doctor-ak-portal' ); ?>
						</h2>

						<div class="dak-profile-clinic-row">
							<div class="dak-profile-clinic-info dak-service-doctor-info">
								<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
									<?php if ( $service['doctor_avatar_url'] ) : ?>
										<img src="<?php echo esc_url( $service['doctor_avatar_url'] ); ?>" alt="">
									<?php else : ?>
										<?php echo $dak_service_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php endif; ?>
								</span>
								<?php if ( $service['doctor_profile_url'] ) : ?>
									<a class="dak-profile-clinic-info-link" href="<?php echo esc_url( $service['doctor_profile_url'] ); ?>"><strong><?php echo esc_html( sprintf( 'Dr. %s', $service['doctor_name'] ) ); ?></strong></a>
								<?php else : ?>
									<strong><?php echo esc_html( sprintf( 'Dr. %s', $service['doctor_name'] ) ); ?></strong>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $service['description'] ) : ?>
					<div class="dak-profile-card">
						<h2><?php esc_html_e( 'About This Service', 'doctor-ak-portal' ); ?></h2>
						<p class="dak-profile-expertise"><?php echo nl2br( esc_html( $service['description'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nl2br() output of already-escaped text. ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $service['clinic_locations'] ) ) : ?>
					<div class="dak-profile-card" id="dak-service-clinics">
						<h2>
							<span class="dak-profile-card-title-icon" aria-hidden="true"><?php echo $dak_service_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Available At', 'doctor-ak-portal' ); ?>
						</h2>

						<div class="dak-profile-clinics">
							<?php foreach ( $service['clinic_locations'] as $clinic_location ) : ?>
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
										<strong><?php echo esc_html( $service['price_label'] ); ?></strong>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

			</div>

			<aside class="dak-profile-sidebar">
				<div class="dak-profile-card dak-profile-booking-card">
					<span class="dak-eyebrow"><?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?></span>
					<h2><?php echo esc_html( $service['name'] ); ?></h2>
					<?php if ( '' !== $service['doctor_name'] ) : ?>
						<p class="dak-profile-booking-hint"><?php echo esc_html( sprintf( /* translators: %s: doctor's name. */ __( 'Provided by Dr. %s', 'doctor-ak-portal' ), $service['doctor_name'] ) ); ?></p>
					<?php endif; ?>
					<p class="dak-profile-booking-hint"><?php esc_html_e( 'Choose a clinic, date and time that works for you on the next step.', 'doctor-ak-portal' ); ?></p>

					<div class="dak-profile-booking-fee">
						<span><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></span>
						<strong><?php echo esc_html( $service['price_label'] ); ?></strong>
					</div>

					<?php if ( $booking_url ) : ?>
						<a class="dak-button dak-button-primary dak-button-block" href="<?php echo esc_url( $booking_url ); ?>">
							<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	<?php endif; ?>
</div>
