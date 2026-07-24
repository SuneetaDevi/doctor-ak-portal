<?php
/**
 * Template: Doctors directory grid for the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $doctors_html    Pre-rendered directory/doctor-card.php output, one per doctor.
 * @var string[] $specializations Specialization slug => label, only those at least one listed doctor has.
 * @var string[] $locations       Distinct physical clinic addresses across every listed doctor.
 * @var string[] $clinics         Distinct physical clinic names across every listed doctor.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory">
	<div class="dak-directory-header">
		<h1><?php esc_html_e( 'Our Doctors', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Browse our specialists and book a clinic visit or an online video consultation.', 'doctor-ak-portal' ); ?></p>
	</div>

	<?php if ( ! empty( $doctors_html ) ) : ?>
		<div class="dak-directory-filters">
			<div class="dak-directory-search">
				<span class="dak-directory-search-icon" aria-hidden="true">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg>
				</span>
				<input type="search" id="dak-directory-search-input" placeholder="<?php esc_attr_e( 'Search by doctor name…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search by doctor name', 'doctor-ak-portal' ); ?>">
			</div>
			<?php if ( ! empty( $specializations ) ) : ?>
				<select id="dak-directory-specialization-filter" aria-label="<?php esc_attr_e( 'Filter by specialization', 'doctor-ak-portal' ); ?>">
					<option value=""><?php esc_html_e( 'All specializations', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $specializations as $label ) : ?>
						<option value="<?php echo esc_attr( mb_strtolower( $label ) ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<?php if ( ! empty( $locations ) ) : ?>
				<select id="dak-directory-location-filter" aria-label="<?php esc_attr_e( 'Filter by location', 'doctor-ak-portal' ); ?>">
					<option value=""><?php esc_html_e( 'All locations', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $locations as $label ) : ?>
						<option value="<?php echo esc_attr( mb_strtolower( $label ) ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<?php if ( ! empty( $clinics ) ) : ?>
				<select id="dak-directory-clinic-filter" aria-label="<?php esc_attr_e( 'Filter by clinic', 'doctor-ak-portal' ); ?>">
					<option value=""><?php esc_html_e( 'All clinics', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $clinics as $label ) : ?>
						<option value="<?php echo esc_attr( mb_strtolower( $label ) ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( empty( $doctors_html ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-directory-grid" id="dak-directory-grid">
			<?php foreach ( $doctors_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>
		<p class="dak-empty-state dak-hidden" id="dak-directory-no-results"><?php esc_html_e( 'No doctors match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</div>
