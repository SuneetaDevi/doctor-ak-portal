<?php
/**
 * Template: "Doctor Requests" tab — doctor accounts still awaiting admin
 * approval (see Registration_Handler::handle_register(), which sets new
 * doctors to 'doctor_ak_registration_status' = 'pending' instead of
 * activating them immediately).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $pending_doctors Row view-models, see Admin_Dashboard::row_data().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Doctor Requests', 'doctor-ak-portal' ); ?></h1>
	<p><?php esc_html_e( 'New doctor registrations wait here until you approve them — they cannot log in until then.', 'doctor-ak-portal' ); ?></p>
</div>

<?php if ( empty( $pending_doctors ) ) : ?>
	<section class="dak-dashboard-card">
		<p class="dak-empty-state"><?php esc_html_e( 'No pending doctor registrations right now.', 'doctor-ak-portal' ); ?></p>
	</section>
<?php else : ?>
	<div class="dak-table-scroll">
		<table class="dak-admin-users-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Qualification', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Experience', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Email Address', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Registered', 'doctor-ak-portal' ); ?></th>
					<th class="dak-admin-users-actions-col"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pending_doctors as $row ) : ?>
					<tr data-doctor-request-row="<?php echo esc_attr( $row['id'] ); ?>">
						<td data-label="<?php esc_attr_e( 'Name', 'doctor-ak-portal' ); ?>">
							<?php echo esc_html( sprintf( 'Dr. %s', $row['name'] ) ); ?>
							<span class="dak-status-badge is-pending"><?php esc_html_e( 'Pending', 'doctor-ak-portal' ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'Qualification', 'doctor-ak-portal' ); ?>"><?php echo esc_html( '' !== $row['qualification'] ? $row['qualification'] : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'Specialization', 'doctor-ak-portal' ); ?>"><?php echo esc_html( '' !== $row['specialization_label'] ? $row['specialization_label'] : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'Experience', 'doctor-ak-portal' ); ?>">
							<?php
							echo '' !== $row['years_experience']
								? esc_html( sprintf( _n( '%s year', '%s years', (int) $row['years_experience'], 'doctor-ak-portal' ), $row['years_experience'] ) )
								: '—';
							?>
						</td>
						<td data-label="<?php esc_attr_e( 'Email Address', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['email'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Registered', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['registered_date'] ); ?></td>
						<td class="dak-admin-users-actions-col">
							<div class="dak-admin-users-actions">
								<button type="button" class="dak-button dak-button-primary dak-button-sm" data-doctor-request-approve data-user-id="<?php echo esc_attr( $row['id'] ); ?>">
									<?php esc_html_e( 'Approve', 'doctor-ak-portal' ); ?>
								</button>
								<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-doctor-request-reject data-user-id="<?php echo esc_attr( $row['id'] ); ?>">
									<?php esc_html_e( 'Reject', 'doctor-ak-portal' ); ?>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
