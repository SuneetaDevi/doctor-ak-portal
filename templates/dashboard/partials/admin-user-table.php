<?php
/**
 * Template: Doctors/Patients table for the admin dashboard.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $users   Row view-models, see Admin_Dashboard::row_data().
 * @var string $section 'doctors' or 'patients'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( empty( $users ) ) : ?>
	<p class="dak-empty-state">
		<?php
		echo 'doctors' === $section
			? esc_html__( 'No doctors have been added yet.', 'doctor-ak-portal' )
			: esc_html__( 'No patients have been added yet.', 'doctor-ak-portal' );
		?>
	</p>
<?php else : ?>
	<div class="dak-table-scroll">
		<table class="dak-admin-users-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Email Address', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
					<th class="dak-admin-users-actions-col"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $users as $row ) : ?>
					<tr data-user-row="<?php echo esc_attr( $row['id'] ); ?>">
						<td data-label="<?php esc_attr_e( 'Name', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['name'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Location', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['location'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Specialization', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['specialization_label'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Email Address', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['email'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
							<span class="dak-status-badge <?php echo $row['is_disabled'] ? 'is-disabled' : 'is-active'; ?>" data-status-badge>
								<?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?>
							</span>
						</td>
						<td class="dak-admin-users-actions-col">
							<div class="dak-admin-users-actions">
								<button
									type="button"
									class="dak-icon-button"
									data-admin-edit-user
									data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
									data-first-name="<?php echo esc_attr( $row['first_name'] ); ?>"
									data-last-name="<?php echo esc_attr( $row['last_name'] ); ?>"
									data-email="<?php echo esc_attr( $row['email'] ); ?>"
									data-location="<?php echo esc_attr( $row['location'] ); ?>"
									data-specializations="<?php echo esc_attr( implode( ',', $row['specializations'] ) ); ?>"
									title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
								><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg></button>
								<button
									type="button"
									class="dak-icon-button<?php echo $row['is_disabled'] ? ' dak-icon-button-success' : ' dak-icon-button-warning'; ?>"
									data-admin-toggle-status
									data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
									data-is-disabled="<?php echo $row['is_disabled'] ? '1' : '0'; ?>"
									title="<?php echo $row['is_disabled'] ? esc_attr__( 'Activate', 'doctor-ak-portal' ) : esc_attr__( 'Deactivate', 'doctor-ak-portal' ); ?>"
									aria-label="<?php echo $row['is_disabled'] ? esc_attr__( 'Activate', 'doctor-ak-portal' ) : esc_attr__( 'Deactivate', 'doctor-ak-portal' ); ?>"
								><?php if ( $row['is_disabled'] ) : ?><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.5v6"/><path d="M5.5 5.2a6.5 6.5 0 1 0 9 0"/></svg><?php else : ?><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="M7.5 7.5v5M12.5 7.5v5"/></svg><?php endif; ?></button>
								<button
									type="button"
									class="dak-icon-button dak-icon-button-danger"
									data-admin-delete-user
									data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
									title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
								><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg></button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
