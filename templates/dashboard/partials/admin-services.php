<?php
/**
 * Template: "Services" admin table — every doctor's bookable services
 * (e.g. "OPD Consultation"), each with its own category, charge, and
 * duration. Patients pick from these when booking (see Services class).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $services        Rows from Services::all_flat_for_admin(), each with an added 'doctor' sub-array. Also carries 'description'/'image_url'/'clinic_locations' — this same list feeds the public [services_directory]/[service_profile_view] pages (see the Services class), so an admin adding those here is all it takes.
 * @var string $section_url     This section's own URL (?section=services), for the "Clear filter" link and the Add/Edit form's `?view=form` links.
 * @var string $categories_url  URL of this section's "Categories" tab (?section=services&view=categories), see admin-service-categories.php.
 * @var string $filtered_doctor Name of the doctor being filtered to (via the Doctors directory's "View Services" action), or '' if unfiltered.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Every service doctors offer, with its charge and duration, ready for patients to book.', 'doctor-ak-portal' ); ?></p>
	</div>
	<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'form', $section_url ) ); ?>"><?php esc_html_e( '+ Add Service', 'doctor-ak-portal' ); ?></a>
</div>

<div class="dak-tabs">
	<a class="dak-tab is-active" href="<?php echo esc_url( $section_url ); ?>" aria-current="page"><?php esc_html_e( 'All Services', 'doctor-ak-portal' ); ?></a>
	<a class="dak-tab" href="<?php echo esc_url( $categories_url ); ?>"><?php esc_html_e( 'Categories', 'doctor-ak-portal' ); ?></a>
</div>

<?php if ( '' !== $filtered_doctor ) : ?>
	<div class="dak-alert dak-alert-success">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: doctor's name. */
				__( 'Showing services for Dr. %s.', 'doctor-ak-portal' ),
				$filtered_doctor
			)
		);
		?>
		<a class="dak-link" href="<?php echo esc_url( $section_url ); ?>"><?php esc_html_e( 'Clear filter', 'doctor-ak-portal' ); ?></a>
	</div>
<?php endif; ?>

<section class="dak-results" id="dak-services-list" aria-labelledby="dak-services-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-services-title"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $services ) ) ); ?></span></h2>
		<?php if ( ! empty( $services ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-services-list" placeholder="<?php esc_attr_e( 'Search service, category or doctor', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $services ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors have added any services yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-services-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Duration', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-num"><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $services as $service ) : ?>
						<?php $dak_service_split = \DoctorAKPortal\Includes\Revenue_Split::split( $service['doctor_id'], $service['charge'] ); ?>
						<tr id="dak-service-<?php echo esc_attr( $service['id'] ); ?>" data-row data-service-row="<?php echo esc_attr( $service['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $service['name'] . ' ' . $service['category_label'] . ' ' . $service['doctor']['name'] ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Service', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $service['name'] ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( '' !== $service['category_label'] ? $service['category_label'] : __( 'Uncategorised', 'doctor-ak-portal' ) ); ?></span>
									<?php if ( empty( $service['requires_doctor'] ) ) : ?>
										<span class="dak-cell-note"><?php esc_html_e( 'No doctor required', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( sprintf( 'Dr. %s', $service['doctor']['name'] ) ); ?></span>
									<span class="dak-cell-sub dak-cell-email"><?php echo \DoctorAKPortal\Includes\Dashboard_Format::email_html( $service['doctor']['email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside email_html(). ?></span>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Duration', 'doctor-ak-portal' ); ?>"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::duration( $service['duration_minutes'] ) ); ?></td>
							<td class="dak-col-num" data-label="<?php esc_attr_e( 'Price', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $service['charge'], __( 'Free', 'doctor-ak-portal' ) ) ); ?></span>
									<?php // Revenue split and per-clinic prices: labelled, expandable, unchanged values. ?>
									<details class="dak-cell-details">
										<summary><?php esc_html_e( 'Pricing details', 'doctor-ak-portal' ); ?></summary>
										<dl class="dak-detail-list dak-detail-list-compact">
											<div><dt><?php esc_html_e( "Doctor's share", 'doctor-ak-portal' ); ?></dt><dd class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_service_split['doctor_share'] ) ); ?></dd></div>
											<div><dt><?php esc_html_e( "Hospital's share", 'doctor-ak-portal' ); ?></dt><dd class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $dak_service_split['hospital_share'] ) ); ?></dd></div>
											<?php foreach ( $service['clinic_locations'] as $dak_clinic_price ) : ?>
												<div><dt><?php echo esc_html( $dak_clinic_price['name'] ); ?></dt><dd class="is-tabular"><?php echo esc_html( $dak_clinic_price['price_label'] ); ?></dd></div>
											<?php endforeach; ?>
										</dl>
									</details>
								</span>
							</td>
							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-status-pill <?php echo $service['active'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral'; ?>"><?php echo $service['active'] ? esc_html__( 'Active', 'doctor-ak-portal' ) : esc_html__( 'Inactive', 'doctor-ak-portal' ); ?></span>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<a class="dak-text-action" href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'service_id' => $service['id'] ), $section_url ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: service name. */ __( 'Edit %s', 'doctor-ak-portal' ), $service['name'] ) ); ?>"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></a>
									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: service name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $service['name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-admin-service-delete data-service-id="<?php echo esc_attr( $service['id'] ); ?>"><?php esc_html_e( 'Delete service', 'doctor-ak-portal' ); ?></button>
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