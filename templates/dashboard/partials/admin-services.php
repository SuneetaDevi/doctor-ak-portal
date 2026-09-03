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
 * @var string $filtered_doctor Name of the doctor being filtered to (via the Doctors directory's "View Services" action), or '' if unfiltered.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_service_icons = array(
	'pin'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Every service doctors offer, with its charge and duration, ready for patients to book.', 'doctor-ak-portal' ); ?></p>
	</div>
	<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'form', $section_url ) ); ?>"><?php esc_html_e( '+ Add Service', 'doctor-ak-portal' ); ?></a>
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

<section class="dak-dashboard-card" id="dak-services-list">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Service directory', 'doctor-ak-portal' ); ?></h2>
		<?php if ( ! empty( $services ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-services-list" placeholder="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $services ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors have added any services yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $services as $service ) : ?>
			<?php
			$dak_service_split       = \DoctorAKPortal\Includes\Revenue_Split::split( $service['doctor_id'], $service['charge'] );
			$dak_service_clinic_tags = array_map(
				function ( $clinic_location ) {
					return sprintf( '%1$s (%2$s)', $clinic_location['name'], $clinic_location['price_label'] );
				},
				$service['clinic_locations']
			);
			?>
			<div id="dak-service-<?php echo esc_attr( $service['id'] ); ?>" class="dak-admin-record-row" data-service-row="<?php echo esc_attr( $service['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $service['name'] . ' ' . $service['category_label'] . ' ' . $service['doctor']['name'] ) ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
						<?php if ( $service['image_url'] ) : ?>
							<img src="<?php echo esc_url( $service['image_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo $dak_service_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $service['name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( 'Dr. %1$s &middot; %2$s', $service['doctor']['name'], $service['doctor']['email'] ) ); ?></span>
					</span>

					<span class="dak-admin-record-row-meta">
						<?php echo $service['charge'] > 0 ? esc_html( 'PKR ' . number_format( $service['charge'], 0 ) ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?>
						<?php if ( $service['duration_minutes'] > 0 ) : ?>
							&middot; <?php echo esc_html( sprintf( /* translators: %d: minutes. */ __( '%d min', 'doctor-ak-portal' ), $service['duration_minutes'] ) ); ?>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-tags">
						<?php if ( '' !== $service['category_label'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $service['category_label'] ); ?></span>
						<?php endif; ?>
						<span class="dak-status-pill dak-status-pill-outline <?php echo $service['active'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-disabled'; ?>">
							<?php echo $service['active'] ? esc_html__( 'Active', 'doctor-ak-portal' ) : esc_html__( 'Inactive', 'doctor-ak-portal' ); ?>
						</span>
					</span>

					<span class="dak-admin-record-row-actions">
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'service_id' => $service['id'] ), $section_url ) ); ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_service_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-admin-service-delete
							data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_service_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>

				<div class="dak-admin-record-row-secondary">
					<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Revenue split:', 'doctor-ak-portal' ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( /* translators: %s: doctor's share amount. */ __( "Doctor's share: PKR %s", 'doctor-ak-portal' ), number_format( $dak_service_split['doctor_share'], 0 ) ) ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( /* translators: %s: hospital's share amount. */ __( "Hospital's share: PKR %s", 'doctor-ak-portal' ), number_format( $dak_service_split['hospital_share'], 0 ) ) ); ?></span>
				</div>

				<?php if ( ! empty( $dak_service_clinic_tags ) ) : ?>
					<div class="dak-admin-record-row-secondary">
						<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Clinics:', 'doctor-ak-portal' ); ?></span>
						<span><?php echo esc_html( implode( ', ', $dak_service_clinic_tags ) ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No services match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
