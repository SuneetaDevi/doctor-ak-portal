<?php
/**
 * Template: "Medicines" admin table — every doctor's medicine list (name +
 * default dosage), picked from when writing a prescription during an
 * encounter (see Medicines class).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $medicines       Rows from Medicines::all_flat_for_admin(), each with an added 'doctor' sub-array.
 * @var string $section_url     This section's own URL (?section=medicines), for the "Clear filter" link.
 * @var string $filtered_doctor Name of the doctor being filtered to, or '' if unfiltered.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_medicine_icons = array(
	'pill'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8.5" width="14" height="6" rx="3" transform="rotate(-25 10 11.5)"/><path d="M9 9.5l2 4"/></svg>',
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Medicines', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( "Each doctor's medicine list, picked from when writing a prescription during an encounter.", 'doctor-ak-portal' ); ?></p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-medicine-add"><?php esc_html_e( '+ Add Medicine', 'doctor-ak-portal' ); ?></button>
</div>

<?php if ( '' !== $filtered_doctor ) : ?>
	<div class="dak-alert dak-alert-success">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: doctor's name. */
				__( 'Showing medicines for Dr. %s.', 'doctor-ak-portal' ),
				$filtered_doctor
			)
		);
		?>
		<a class="dak-link" href="<?php echo esc_url( $section_url ); ?>"><?php esc_html_e( 'Clear filter', 'doctor-ak-portal' ); ?></a>
	</div>
<?php endif; ?>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Medicine directory', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $medicines ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors have added any medicines yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $medicines as $medicine ) : ?>
			<div class="dak-admin-record-row" data-medicine-row="<?php echo esc_attr( $medicine['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo $dak_medicine_icons['pill']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $medicine['name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( 'Dr. %1$s &middot; %2$s', $medicine['doctor']['name'], $medicine['doctor']['email'] ) ); ?></span>
					</span>

					<span class="dak-admin-record-row-meta">
						<?php echo '' !== $medicine['default_dosage'] ? esc_html( $medicine['default_dosage'] ) : esc_html__( 'No default dosage', 'doctor-ak-portal' ); ?>
					</span>

					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline <?php echo $medicine['active'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-disabled'; ?>">
							<?php echo $medicine['active'] ? esc_html__( 'Active', 'doctor-ak-portal' ) : esc_html__( 'Inactive', 'doctor-ak-portal' ); ?>
						</span>
					</span>

					<span class="dak-admin-record-row-actions">
						<button
							type="button"
							class="dak-icon-button"
							data-admin-medicine-edit
							data-medicine-id="<?php echo esc_attr( $medicine['id'] ); ?>"
							data-doctor-id="<?php echo esc_attr( $medicine['doctor_id'] ); ?>"
							data-name="<?php echo esc_attr( $medicine['name'] ); ?>"
							data-default-dosage="<?php echo esc_attr( $medicine['default_dosage'] ); ?>"
							data-active="<?php echo $medicine['active'] ? '1' : '0'; ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_medicine_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-admin-medicine-delete
							data-medicine-id="<?php echo esc_attr( $medicine['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_medicine_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
