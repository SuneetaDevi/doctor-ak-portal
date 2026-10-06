<?php
/**
 * Template: Doctor dashboard "Services" tab — manage the bookable services
 * this doctor offers (e.g. "OPD Consultation"), each with its own type,
 * category, charge, and duration. Patients pick from these when booking.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $services   Doctor's services, see Services::get_for_doctor().
 * @var array $categories Category slug => label, see Service_Categories::get_all().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_service_icons = array(
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Billable services offered at your clinics.', 'doctor-ak-portal' ); ?></p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-service-add"><?php esc_html_e( '+ Add Service', 'doctor-ak-portal' ); ?></button>
</div>

<div class="dak-alert dak-alert-success dak-hidden" id="dak-services-success" role="status"></div>
<div class="dak-alert dak-alert-error dak-hidden" id="dak-services-general-error" role="alert"></div>

<section class="dak-results" id="dak-doctor-services-list" aria-labelledby="dak-doctor-services-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-doctor-services-title"><?php esc_html_e( 'Your services', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $services ) ) ); ?></span></h2>
		<?php if ( ! empty( $services ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box"><span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-doctor-services-list" placeholder="<?php esc_attr_e( 'Search name or category', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $services ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( "You haven't added any services yet. Use “+ Add Service” to add your first one.", 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-services-table dak-doctor-services-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Duration', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $services as $service ) : ?>
						<?php $dak_service_split = \DoctorAKPortal\Includes\Revenue_Split::split( $service['doctor_id'], $service['charge'] ); ?>
						<tr id="dak-service-<?php echo esc_attr( $service['id'] ); ?>" data-row data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $service['name'] . ' ' . $service['category_label'] ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Service', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $service['name'] ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( '' !== $service['category_label'] ? $service['category_label'] : __( 'Uncategorised', 'doctor-ak-portal' ) ); ?></span>
									<?php if ( empty( $service['requires_doctor'] ) ) : ?>
										<span class="dak-cell-note"><?php esc_html_e( 'No doctor required', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Duration', 'doctor-ak-portal' ); ?>"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::duration( $service['duration_minutes'] ) ); ?></td>
							<td class="dak-col-num" data-label="<?php esc_attr_e( 'Price', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $service['charge'], __( 'Free', 'doctor-ak-portal' ) ) ); ?></span>
									<details class="dak-cell-details">
										<summary><?php esc_html_e( 'Pricing details', 'doctor-ak-portal' ); ?></summary>
										<dl class="dak-detail-list dak-detail-list-compact">
											<div><dt><?php esc_html_e( 'Your share', 'doctor-ak-portal' ); ?></dt><dd class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_service_split['doctor_share'] ) ); ?></dd></div>
											<div><dt><?php esc_html_e( "Hospital's share", 'doctor-ak-portal' ); ?></dt><dd class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_service_split['hospital_share'] ) ); ?></dd></div>
											<?php if ( empty( $service['clinic_locations'] ) ) : ?>
												<div><dt><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></dt><dd><?php esc_html_e( 'Same price at all your clinics', 'doctor-ak-portal' ); ?></dd></div>
											<?php else : ?>
												<?php foreach ( $service['clinic_locations'] as $dak_location ) : ?>
													<div><dt><?php echo esc_html( $dak_location['name'] ); ?></dt><dd class="is-tabular"><?php echo esc_html( $dak_location['price_label'] ); ?></dd></div>
												<?php endforeach; ?>
											<?php endif; ?>
										</dl>
									</details>
								</span>
							</td>
							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-status-pill <?php echo $service['active'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral'; ?>"><?php echo $service['active'] ? esc_html__( 'Active', 'doctor-ak-portal' ) : esc_html__( 'Inactive', 'doctor-ak-portal' ); ?></span>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<button
										type="button"
										class="dak-text-action"
										data-service-edit
										data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
										data-name="<?php echo esc_attr( $service['name'] ); ?>"
										data-category="<?php echo esc_attr( $service['category'] ); ?>"
										data-charge="<?php echo esc_attr( $service['charge'] ); ?>"
										data-duration-minutes="<?php echo esc_attr( $service['duration_minutes'] ); ?>"
										data-active="<?php echo $service['active'] ? '1' : '0'; ?>"
										data-requires-doctor="<?php echo $service['requires_doctor'] ? '1' : '0'; ?>"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: service name. */ __( 'Edit %s', 'doctor-ak-portal' ), $service['name'] ) ); ?>"
									><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></button>
									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: service name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $service['name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-service-delete data-service-id="<?php echo esc_attr( $service['id'] ); ?>"><?php esc_html_e( 'Delete service', 'doctor-ak-portal' ); ?></button>
										</div>
									</details>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="dak-empty-state dak-results-empty dak-hidden" data-list-search-empty><?php esc_html_e( 'No services match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
</div>
<div class="dak-portal dak-modal" id="dak-service-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-service-modal-close></div>

	<div class="dak-modal-dialog dak-modal-dialog-form" role="dialog" aria-modal="true" aria-labelledby="dak-service-modal-title">

		<div class="dak-modal-header">
			<h2 id="dak-service-modal-title"><?php esc_html_e( 'Add Service', 'doctor-ak-portal' ); ?></h2>
			<button type="button" class="dak-modal-close" data-dak-service-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
		</div>

		<div class="dak-modal-body">

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-service-general-error" role="alert"></div>

		<input type="hidden" id="dak-service-id" value="0">

		<div class="dak-field">
			<label for="dak-service-name"><?php esc_html_e( 'Service Name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-service-name" placeholder="<?php esc_attr_e( 'e.g. OPD Consultation', 'doctor-ak-portal' ); ?>">
			<span class="dak-field-error" data-field="name"></span>
		</div>

		<div class="dak-field">
			<label for="dak-service-category"><?php esc_html_e( 'Category', 'doctor-ak-portal' ); ?></label>
			<select id="dak-service-category">
				<option value=""><?php esc_html_e( 'None', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $categories as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-service-charge"><?php esc_html_e( 'Charge (PKR)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" step="0.01" id="dak-service-charge" value="0">
				<span class="dak-field-error" data-field="charge"></span>
			</div>
			<div class="dak-field">
				<label for="dak-service-duration"><?php esc_html_e( 'Duration (minutes)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" max="480" step="1" id="dak-service-duration" value="0">
				<span class="dak-field-error" data-field="duration_minutes"></span>
			</div>
		</div>

		<div class="dak-field">
			<label class="dak-checkbox">
				<input type="checkbox" id="dak-service-active" checked>
				<span><?php esc_html_e( 'Active (visible to patients when booking)', 'doctor-ak-portal' ); ?></span>
			</label>
		</div>

		<div class="dak-field">
			<label class="dak-checkbox">
				<input type="checkbox" id="dak-service-requires-doctor" checked>
				<span><?php esc_html_e( 'Requires a doctor (uncheck for a Lab/Pharmacy-style service patients can request without picking a doctor)', 'doctor-ak-portal' ); ?></span>
			</label>
		</div>

		</div>

		<div class="dak-modal-footer">
			<button type="button" class="dak-button dak-button-secondary" data-dak-service-modal-close><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></button>
			<button type="button" class="dak-button dak-button-primary" id="dak-service-save">
				<span class="dak-button-label"><?php esc_html_e( 'Save Service', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	</div>
</div>
