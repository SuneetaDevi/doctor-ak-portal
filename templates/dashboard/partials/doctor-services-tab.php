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
	'pin'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-alert dak-alert-success dak-hidden" id="dak-services-success" role="status"></div>
<div class="dak-alert dak-alert-error dak-hidden" id="dak-services-general-error" role="alert"></div>

<?php if ( empty( $services ) ) : ?>
	<p class="dak-empty-state"><?php esc_html_e( "You haven't added any services yet. Add one below.", 'doctor-ak-portal' ); ?></p>
<?php else : ?>
	<div class="dak-table-scroll">
		<table class="dak-admin-users-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Charges', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Category', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
					<th class="dak-admin-users-actions-col"><?php esc_html_e( 'Action', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $services as $service ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Service', 'doctor-ak-portal' ); ?>">
							<span class="dak-clinic-card-icon" aria-hidden="true"><?php echo $dak_service_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php echo esc_html( $service['name'] ); ?>
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
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<button type="button" class="dak-button dak-button-secondary" id="dak-service-add">
	<?php esc_html_e( '+ Add Service', 'doctor-ak-portal' ); ?>
</button>

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
