<?php
/**
 * Template: Doctors directory grid for the [doctors_directory] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $doctors_html    Pre-rendered directory/doctor-card.php output, one per doctor.
 * @var string[] $specializations Specialization slug => label (falls back to the slug itself for custom, non-canonical specializations), only those at least one listed doctor has.
 * @var string[] $clinics         Distinct physical clinic name => its area slug (may be '' if unset) — the area slug lets the Clinic filter narrow down to the selected Area, see assets/js/doctor-ak-directory.js.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-directory">
	<div class="dak-directory-header">
		<span class="dak-eyebrow"><?php esc_html_e( 'Our Specialists', 'doctor-ak-portal' ); ?></span>
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
			<select id="dak-directory-country-filter" aria-label="<?php esc_attr_e( 'Filter by country', 'doctor-ak-portal' ); ?>"></select>
			<select id="dak-directory-city-filter" aria-label="<?php esc_attr_e( 'Filter by city', 'doctor-ak-portal' ); ?>" disabled></select>
			<select id="dak-directory-area-filter" aria-label="<?php esc_attr_e( 'Filter by area', 'doctor-ak-portal' ); ?>" disabled></select>
			<?php if ( ! empty( $clinics ) ) : ?>
				<select id="dak-directory-clinic-filter" aria-label="<?php esc_attr_e( 'Filter by clinic', 'doctor-ak-portal' ); ?>">
					<option value=""><?php esc_html_e( 'All clinics', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $clinics as $label => $area ) : ?>
						<option value="<?php echo esc_attr( mb_strtolower( $label ) ); ?>" data-area="<?php echo esc_attr( $area ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<div class="dak-directory-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Grid or list view', 'doctor-ak-portal' ); ?>">
				<button type="button" class="dak-directory-view-btn is-active" data-directory-view="grid" aria-pressed="true" title="<?php esc_attr_e( 'Grid view', 'doctor-ak-portal' ); ?>">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="2.5" width="6.5" height="6.5" rx="1"/><rect x="11" y="2.5" width="6.5" height="6.5" rx="1"/><rect x="2.5" y="11" width="6.5" height="6.5" rx="1"/><rect x="11" y="11" width="6.5" height="6.5" rx="1"/></svg>
					<span><?php esc_html_e( 'Grid', 'doctor-ak-portal' ); ?></span>
				</button>
				<button type="button" class="dak-directory-view-btn" data-directory-view="list" aria-pressed="false" title="<?php esc_attr_e( 'List view', 'doctor-ak-portal' ); ?>">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
					<span><?php esc_html_e( 'List', 'doctor-ak-portal' ); ?></span>
				</button>
			</div>
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
