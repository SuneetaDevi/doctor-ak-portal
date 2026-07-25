<?php
/**
 * Template: Doctors/Patients table for the admin dashboard.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $users            Row view-models, see Admin_Dashboard::row_data().
 * @var string $section          'doctors' or 'patients'.
 * @var string $appointments_url Base URL of the admin Appointments section, for the patients table's "View Appointments" action.
 * @var string $section_url      This section's own URL (no filters), for the filter form and "Clear" link.
 * @var array  $specializations  Specialization slug => label, see Specializations::get_all(). Empty for the patients table.
 * @var array  $filters          Active filter values: status, specialization.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_is_patients = 'patients' === $section;
$dak_has_filters  = '' !== $filters['status'] || '' !== $filters['specialization'];
?>
<section class="dak-dashboard-card">
	<form method="get" action="<?php echo esc_url( $section_url ); ?>" class="dak-field-row">
		<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>">
		<?php if ( ! $dak_is_patients ) : ?>
			<div class="dak-field">
				<label for="dak-admin-users-filter-specialization"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-users-filter-specialization" name="specialization">
					<option value=""><?php esc_html_e( 'All specializations', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $specializations as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['specialization'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<div class="dak-field">
			<label for="dak-admin-users-filter-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-users-filter-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<option value="active" <?php selected( $filters['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'doctor-ak-portal' ); ?></option>
				<option value="disabled" <?php selected( $filters['status'], 'disabled' ); ?>><?php esc_html_e( 'Deactivated', 'doctor-ak-portal' ); ?></option>
			</select>
		</div>

		<div class="dak-admin-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $section_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</section>

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
					<?php if ( $dak_is_patients ) : ?>
						<th><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?></th>
					<?php else : ?>
						<th><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></th>
						<th><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></th>
					<?php endif; ?>
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
						<?php if ( $dak_is_patients ) : ?>
							<td data-label="<?php esc_attr_e( 'Phone Number', 'doctor-ak-portal' ); ?>"><?php echo esc_html( '' !== $row['phone'] ? $row['phone'] : '—' ); ?></td>
						<?php else : ?>
							<td data-label="<?php esc_attr_e( 'Specialization', 'doctor-ak-portal' ); ?>">
								<?php if ( empty( $row['specialization_labels'] ) ) : ?>
									&mdash;
								<?php else : ?>
									<div class="dak-specialty-tags dak-table-specialty-tags" data-specialty-tags>
										<?php foreach ( $row['specialization_labels'] as $dak_spec_index => $dak_spec_label ) : ?>
											<span class="dak-specialty-tag<?php echo $dak_spec_index >= 2 ? ' dak-specialty-tag-extra dak-hidden' : ''; ?>"><?php echo esc_html( $dak_spec_label ); ?></span>
										<?php endforeach; ?>
										<?php if ( count( $row['specialization_labels'] ) > 2 ) : ?>
											<button
												type="button"
												class="dak-specialty-tag dak-specialty-tag-more"
												data-specialty-toggle
												data-more-label="<?php echo esc_attr( sprintf( '+%d', count( $row['specialization_labels'] ) - 2 ) ); ?>"
												data-less-label="<?php esc_attr_e( 'Show less', 'doctor-ak-portal' ); ?>"
											><?php echo esc_html( sprintf( '+%d', count( $row['specialization_labels'] ) - 2 ) ); ?></button>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Video Consultation', 'doctor-ak-portal' ); ?>">
								<span class="dak-status-badge <?php echo $row['video_consultation_allowed'] ? 'is-active' : 'is-disabled'; ?>">
									<?php echo $row['video_consultation_allowed'] ? esc_html__( 'Yes', 'doctor-ak-portal' ) : esc_html__( 'No', 'doctor-ak-portal' ); ?>
								</span>
							</td>
						<?php endif; ?>
						<td data-label="<?php esc_attr_e( 'Email Address', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['email'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
							<span class="dak-status-badge <?php echo $row['is_disabled'] ? 'is-disabled' : 'is-active'; ?>" data-status-badge>
								<?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?>
							</span>
						</td>
						<td class="dak-admin-users-actions-col">
							<div class="dak-admin-users-actions">
								<?php if ( $dak_is_patients && $appointments_url ) : ?>
									<a
										class="dak-icon-button"
										href="<?php echo esc_url( add_query_arg( 'patient_id', $row['id'], $appointments_url ) ); ?>"
										title="<?php esc_attr_e( 'View Appointments', 'doctor-ak-portal' ); ?>"
										aria-label="<?php esc_attr_e( 'View Appointments', 'doctor-ak-portal' ); ?>"
									><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg></a>
								<?php endif; ?>
								<a
									class="dak-icon-button"
									href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'user_id' => $row['id'] ), $section_url ) ); ?>"
									title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
								><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg></a>
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
