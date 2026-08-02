<?php
/**
 * Template: "Clinic" admin table — the master list of physical clinics
 * (Country/City/Area/Name) doctors get aligned to from the "Doctor Sessions"
 * form, see Clinic_Locations.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $clinic_locations Rows from Clinic_Locations::get_all().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_clinic_location_icons = array(
	'pin'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'The master list of clinic locations (Country / City / Area / Name). Align doctors to one of these from the Doctor Sessions form instead of typing a location for each doctor.', 'doctor-ak-portal' ); ?></p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-clinic-location-add"><?php esc_html_e( '+ Add Clinic', 'doctor-ak-portal' ); ?></button>
</div>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Clinic directory', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $clinic_locations ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No clinics added yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $clinic_locations as $clinic_location ) : ?>
			<div class="dak-admin-record-row" data-clinic-location-row="<?php echo esc_attr( $clinic_location['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo $dak_clinic_location_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $clinic_location['name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( '' !== $clinic_location['address'] ? $clinic_location['address'] : '—' ); ?></span>
					</span>

					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $clinic_location['area_label'] ); ?></span>
						<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $clinic_location['city_label'] ); ?></span>
						<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $clinic_location['country_label'] ); ?></span>
					</span>

					<span class="dak-admin-record-row-actions">
						<button
							type="button"
							class="dak-icon-button"
							data-admin-clinic-location-edit
							data-id="<?php echo esc_attr( $clinic_location['id'] ); ?>"
							data-name="<?php echo esc_attr( $clinic_location['name'] ); ?>"
							data-address="<?php echo esc_attr( $clinic_location['address'] ); ?>"
							data-country="<?php echo esc_attr( $clinic_location['country'] ); ?>"
							data-city="<?php echo esc_attr( $clinic_location['city'] ); ?>"
							data-area="<?php echo esc_attr( $clinic_location['area'] ); ?>"
							data-phone="<?php echo esc_attr( $clinic_location['phone'] ); ?>"
							data-contact-email="<?php echo esc_attr( $clinic_location['contact_email'] ); ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_clinic_location_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-admin-clinic-location-delete
							data-id="<?php echo esc_attr( $clinic_location['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_clinic_location_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
