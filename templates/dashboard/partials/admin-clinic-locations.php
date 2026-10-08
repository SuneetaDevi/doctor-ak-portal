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
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'The master list of clinic locations. Doctors are aligned to one of these from the Doctor Sessions form.', 'doctor-ak-portal' ); ?></p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-clinic-location-add"><?php esc_html_e( '+ Add Clinic', 'doctor-ak-portal' ); ?></button>
</div>

<section class="dak-results" id="dak-clinic-locations-list" aria-labelledby="dak-clinic-locations-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-clinic-locations-title"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $clinic_locations ) ) ); ?></span></h2>
		<?php if ( ! empty( $clinic_locations ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-clinic-locations-list" placeholder="<?php esc_attr_e( 'Search clinics', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search clinics', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $clinic_locations ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No clinics added yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-clinic-locations-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Contact', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $clinic_locations as $clinic_location ) : ?>
						<?php $dak_place = implode( ', ', array_filter( array( $clinic_location['area_label'], $clinic_location['city_label'] ) ) ); ?>
						<tr id="dak-clinic-location-<?php echo esc_attr( $clinic_location['id'] ); ?>" data-row data-clinic-location-row="<?php echo esc_attr( $clinic_location['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $clinic_location['name'] . ' ' . $clinic_location['address'] . ' ' . $clinic_location['area_label'] . ' ' . $clinic_location['city_label'] ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Clinic', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $clinic_location['name'] ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( '' !== $clinic_location['address'] ? $clinic_location['address'] : __( 'No street address', 'doctor-ak-portal' ) ); ?></span>
									<?php echo \DoctorAKPortal\Includes\Dashboard_Format::map_link_html( $clinic_location['map_url'], $clinic_location['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside map_link_html(). ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Location', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span><?php echo esc_html( '' !== $dak_place ? $dak_place : '—' ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( $clinic_location['country_label'] ); ?></span>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Contact', 'doctor-ak-portal' ); ?>">
								<?php if ( '' === (string) $clinic_location['phone'] && '' === (string) $clinic_location['contact_email'] ) : ?>
									<span class="dak-cell-sub"><?php esc_html_e( 'Not set', 'doctor-ak-portal' ); ?></span>
								<?php else : ?>
									<span class="dak-cell-stack">
										<?php if ( '' !== (string) $clinic_location['phone'] ) : ?>
											<span class="is-tabular"><?php echo esc_html( $clinic_location['phone'] ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== (string) $clinic_location['contact_email'] ) : ?>
											<span class="dak-cell-sub dak-cell-email"><?php echo \DoctorAKPortal\Includes\Dashboard_Format::email_html( $clinic_location['contact_email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside email_html(). ?></span>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<button
										type="button"
										class="dak-text-action"
										data-admin-clinic-location-edit
										data-id="<?php echo esc_attr( $clinic_location['id'] ); ?>"
										data-name="<?php echo esc_attr( $clinic_location['name'] ); ?>"
										data-address="<?php echo esc_attr( $clinic_location['address'] ); ?>"
										data-country="<?php echo esc_attr( $clinic_location['country'] ); ?>"
										data-city="<?php echo esc_attr( $clinic_location['city'] ); ?>"
										data-area="<?php echo esc_attr( $clinic_location['area'] ); ?>"
										data-phone="<?php echo esc_attr( $clinic_location['phone'] ); ?>"
										data-contact-email="<?php echo esc_attr( $clinic_location['contact_email'] ); ?>"
										data-keywords="<?php echo esc_attr( $clinic_location['keywords'] ); ?>"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: clinic name. */ __( 'Edit %s', 'doctor-ak-portal' ), $clinic_location['name'] ) ); ?>"
									><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></button>
									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: clinic name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $clinic_location['name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-admin-clinic-location-delete data-id="<?php echo esc_attr( $clinic_location['id'] ); ?>"><?php esc_html_e( 'Delete clinic', 'doctor-ak-portal' ); ?></button>
										</div>
									</details>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="dak-empty-state dak-results-empty dak-hidden" data-list-search-empty><?php esc_html_e( 'No clinics match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
</div>