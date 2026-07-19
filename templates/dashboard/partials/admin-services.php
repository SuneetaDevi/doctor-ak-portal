<?php
/**
 * Template: "Services" admin table — every doctor's bookable services
 * (e.g. "OPD Consultation"), each with its own category, charge, and
 * duration. Patients pick from these when booking (see Services class).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $services Rows from Services::all_flat_for_admin(), each with an added 'doctor' sub-array.
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
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-service-add"><?php esc_html_e( '+ Add Service', 'doctor-ak-portal' ); ?></button>
</div>

<section class="dak-dashboard-card dak-admin-users-card">
<?php if ( empty( $services ) ) : ?>
	<p class="dak-empty-state"><?php esc_html_e( 'No doctors have added any services yet.', 'doctor-ak-portal' ); ?></p>
<?php else : ?>
	<div class="dak-table-scroll">
		<table class="dak-admin-users-table dak-admin-sessions-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Charges', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Category', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
					<th class="dak-admin-users-actions-col"><?php esc_html_e( 'Action', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $services as $service ) : ?>
					<tr data-service-row="<?php echo esc_attr( $service['id'] ); ?>">
						<td data-label="ID">#<?php echo esc_html( $service['id'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Service', 'doctor-ak-portal' ); ?>">
							<span class="dak-clinic-card-icon" aria-hidden="true"><?php echo $dak_service_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php echo esc_html( $service['name'] ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
							<strong><?php echo esc_html( sprintf( 'Dr. %s', $service['doctor']['name'] ) ); ?></strong><br>
							<span class="dak-clinic-card-meta"><?php echo esc_html( $service['doctor']['email'] ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'Charges', 'doctor-ak-portal' ); ?>">
							<?php echo $service['charge'] > 0 ? esc_html( 'PKR' . number_format( $service['charge'], 0 ) . '/-' ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Duration', 'doctor-ak-portal' ); ?>">
							<?php echo $service['duration_minutes'] > 0 ? esc_html( sprintf( /* translators: %d: minutes. */ __( '%d min', 'doctor-ak-portal' ), $service['duration_minutes'] ) ) : '—'; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Category', 'doctor-ak-portal' ); ?>"><?php echo esc_html( '' !== $service['category_label'] ? $service['category_label'] : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
							<span class="dak-status-badge <?php echo $service['active'] ? 'is-active' : 'is-disabled'; ?>">
								<?php echo $service['active'] ? esc_html__( 'Active', 'doctor-ak-portal' ) : esc_html__( 'Inactive', 'doctor-ak-portal' ); ?>
							</span>
						</td>
						<td class="dak-admin-users-actions-col">
							<div class="dak-admin-users-actions">
								<button
									type="button"
									class="dak-icon-button"
									data-admin-service-edit
									data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
									data-doctor-id="<?php echo esc_attr( $service['doctor_id'] ); ?>"
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
									data-admin-service-delete
									data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
									title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_service_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
</section>
