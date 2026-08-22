<?php
/**
 * Template: Doctor dashboard "Services" tab — manage the bookable services
 * this doctor offers (e.g. "OPD Consultation"), each with its own type,
 * category, charge, and duration. Patients pick from these when booking.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $services   Doctor's services, see Services::get_for_doctor().
 * @var array $categories Category slug => label, see Specializations::get_all().
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
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Billable services offered at your clinics', 'doctor-ak-portal' ); ?></p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-service-add"><?php esc_html_e( '+ Add Service', 'doctor-ak-portal' ); ?></button>
</div>

<section class="dak-dashboard-card" id="dak-doctor-services-list">
	<div class="dak-dashboard-card-header">
		<div>
			<h2><?php esc_html_e( 'All services', 'doctor-ak-portal' ); ?></h2>
			<p class="dak-notifications-card-subtitle">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of services. */
						_n( '%d service', '%d services', count( $services ), 'doctor-ak-portal' ),
						count( $services )
					)
				);
				?>
			</p>
		</div>
		<?php if ( ! empty( $services ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-doctor-services-list" placeholder="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<div class="dak-alert dak-alert-success dak-hidden" id="dak-services-success" role="status"></div>
	<div class="dak-alert dak-alert-error dak-hidden" id="dak-services-general-error" role="alert"></div>

	<?php if ( empty( $services ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( "You haven't added any services yet. Add one above.", 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $services as $service ) : ?>
			<?php $dak_service_split = \DoctorAKPortal\Includes\Revenue_Split::split( $service['doctor_id'], $service['charge'] ); ?>
			<div id="dak-service-<?php echo esc_attr( $service['id'] ); ?>" class="dak-admin-record-row" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $service['name'] . ' ' . $service['category_label'] ) ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $service['name'] ); ?></strong>
						<span class="dak-service-row-price">
							<?php echo $service['charge'] > 0 ? esc_html( 'PKR ' . number_format( $service['charge'], 0 ) ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?>
						</span>
					</span>

					<span class="dak-admin-record-row-tags">
						<?php if ( '' !== $service['category_label'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $service['category_label'] ); ?></span>
						<?php endif; ?>
						<?php if ( $service['duration_minutes'] > 0 ) : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( /* translators: %d: minutes. */ __( '%d min', 'doctor-ak-portal' ), $service['duration_minutes'] ) ); ?></span>
						<?php endif; ?>
					</span>

					<span class="dak-status-pill dak-status-pill-outline <?php echo $service['active'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-disabled'; ?>">
						<?php echo $service['active'] ? esc_html__( 'Active', 'doctor-ak-portal' ) : esc_html__( 'Inactive', 'doctor-ak-portal' ); ?>
					</span>

					<span class="dak-admin-record-row-actions">
						<button
							type="button"
							class="dak-icon-button"
							data-service-edit
							data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
							data-name="<?php echo esc_attr( $service['name'] ); ?>"
							data-category="<?php echo esc_attr( $service['category'] ); ?>"
							data-charge="<?php echo esc_attr( $service['charge'] ); ?>"
							data-duration-minutes="<?php echo esc_attr( $service['duration_minutes'] ); ?>"
							data-active="<?php echo $service['active'] ? '1' : '0'; ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_service_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-service-delete
							data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_service_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>

				<div class="dak-admin-record-row-secondary">
					<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Revenue split:', 'doctor-ak-portal' ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( /* translators: %s: doctor's share amount. */ __( "Your share: PKR %s", 'doctor-ak-portal' ), number_format( $dak_service_split['doctor_share'], 0 ) ) ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( /* translators: %s: hospital's share amount. */ __( "Hospital's share: PKR %s", 'doctor-ak-portal' ), number_format( $dak_service_split['hospital_share'], 0 ) ) ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No services match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>

<div class="dak-portal dak-modal" id="dak-service-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-service-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-service-modal-title">
		<button type="button" class="dak-modal-close" data-dak-service-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-service-modal-title"><?php esc_html_e( 'Add Service', 'doctor-ak-portal' ); ?></h2>
		</div>

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

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-service-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Service', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
