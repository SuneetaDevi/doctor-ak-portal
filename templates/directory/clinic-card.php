<?php
/**
 * Template: Single clinic card within the clinics directory grid.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $id            Clinic_Locations row ID.
 * @var string $name          Clinic name.
 * @var string $address       Street address, or ''.
 * @var string $area_label    Area label, or ''.
 * @var string $city_label    City label, or ''.
 * @var string $phone         Contact phone, or ''.
 * @var int    $doctor_count  Number of doctors aligned to this clinic.
 * @var string $profile_url   URL of this clinic's [clinic_profile_view] page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_clinic_card_icons = array(
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'person'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
);

$dak_clinic_card_location_line = implode( ', ', array_filter( array( $address, $area_label, $city_label ) ) );
?>
<div class="dak-service-card">
	<span class="dak-service-card-image">
		<?php echo $dak_clinic_card_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</span>

	<div class="dak-service-card-body">
		<h3 class="dak-service-card-name"><?php echo esc_html( $name ); ?></h3>

		<?php if ( '' !== $dak_clinic_card_location_line ) : ?>
			<p class="dak-service-card-excerpt"><?php echo esc_html( $dak_clinic_card_location_line ); ?></p>
		<?php endif; ?>

		<div class="dak-service-card-price">
			<span>
				<?php echo $dak_clinic_card_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of doctors at this clinic. */
						_n( '%d doctor', '%d doctors', $doctor_count, 'doctor-ak-portal' ),
						$doctor_count
					)
				);
				?>
			</span>
			<?php if ( '' !== $phone ) : ?>
				<span><?php echo esc_html( $phone ); ?></span>
			<?php endif; ?>
		</div>

		<div class="dak-service-card-actions">
			<a class="dak-button dak-button-primary dak-button-block" href="<?php echo esc_url( $profile_url ); ?>">
				<?php esc_html_e( 'View Doctors', 'doctor-ak-portal' ); ?>
			</a>
		</div>
	</div>
</div>
