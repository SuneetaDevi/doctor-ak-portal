<?php
/**
 * Template: Public clinic detail page for the [clinic_profile_view]
 * shortcode — the clinic's own details plus every doctor aligned to it.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array|null $clinic Clinic_Locations::find() row, or null if no valid clinic_id was given.
 * @var string[]   $doctors_html Pre-rendered directory/doctor-card.php output, one per doctor at this clinic.
 * @var string     $directory_url "All Clinics" breadcrumb link.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_clinic_view_icons = array(
	'pin'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'phone' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h2.3l1 3.3-1.6 1.4a9 9 0 0 0 4.1 4.1l1.4-1.6 3.3 1v2.3c0 .8-.7 1.4-1.5 1.3C8.7 15 5 11.3 4.2 6c-.1-.8.5-1.5 1.3-1.5z"/></svg>',
	'mail'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="15" height="11" rx="1.5"/><path d="M3 5.5l7 5.5 7-5.5"/></svg>',
);

$dak_clinic_location_line = $clinic ? implode( ', ', array_filter( array( $clinic['address'], $clinic['area_label'], $clinic['city_label'] ) ) ) : '';
?>
<div class="dak-portal dak-directory">
	<nav class="dak-profile-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'doctor-ak-portal' ); ?>">
		<?php if ( $directory_url ) : ?>
			<a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
		<?php endif; ?>
		<span><?php echo esc_html( $clinic ? $clinic['name'] : __( 'Clinic not found', 'doctor-ak-portal' ) ); ?></span>
	</nav>

	<?php if ( ! $clinic ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'This clinic is no longer available.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-profile-header-card">
			<span class="dak-avatar dak-avatar-lg">
				<?php echo $dak_clinic_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>

			<div class="dak-profile-header-main">
				<h1><?php echo esc_html( $clinic['name'] ); ?></h1>

				<?php if ( '' !== $dak_clinic_location_line ) : ?>
					<div class="dak-doctor-card-location">
						<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_clinic_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><?php echo esc_html( $dak_clinic_location_line ); ?></span>
					</div>
				<?php endif; ?>

				<div class="dak-profile-stats">
					<?php if ( '' !== $clinic['phone'] ) : ?>
						<a class="dak-profile-stat dak-profile-stat-link" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $clinic['phone'] ) ); ?>">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_clinic_view_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php echo esc_html( $clinic['phone'] ); ?></strong>
							<span><?php esc_html_e( 'Call', 'doctor-ak-portal' ); ?></span>
						</a>
					<?php endif; ?>

					<?php if ( '' !== $clinic['contact_email'] ) : ?>
						<a class="dak-profile-stat dak-profile-stat-link" href="mailto:<?php echo esc_attr( $clinic['contact_email'] ); ?>">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_clinic_view_icons['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?></strong>
							<span><?php echo esc_html( $clinic['contact_email'] ); ?></span>
						</a>
					<?php endif; ?>

					<span class="dak-profile-stat">
						<strong><?php echo count( $doctors_html ); ?></strong>
						<span><?php echo esc_html( _n( 'Doctor', 'Doctors', count( $doctors_html ), 'doctor-ak-portal' ) ); ?></span>
					</span>
				</div>
			</div>
		</div>

		<div class="dak-profile-card">
			<h2><?php esc_html_e( 'Doctors at this Clinic', 'doctor-ak-portal' ); ?></h2>

			<?php if ( empty( $doctors_html ) ) : ?>
				<p class="dak-empty-state"><?php esc_html_e( 'No doctors are listed at this clinic yet.', 'doctor-ak-portal' ); ?></p>
			<?php else : ?>
				<div class="dak-directory-grid">
					<?php foreach ( $doctors_html as $card_html ) : ?>
						<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
