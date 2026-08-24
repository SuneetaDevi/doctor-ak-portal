<?php
/**
 * Template: Doctors/Patients/Receptionist table for the admin dashboard.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $users            Row view-models, see Admin_Dashboard::row_data().
 * @var string $section          'doctors', 'patients', or 'receptionist'.
 * @var string $appointments_url    Base URL of the admin Appointments section, for the "View Appointments" action.
 * @var string $encounters_url      Base URL of the admin Encounters section, for the Patients table's "Encounter" action. Empty if the current viewer (admin or Receptionist) can't access Encounters — see Admin_Dashboard::users_section_html().
 * @var string $booking_url         URL of the public booking page, for the Patients table's "Book Appointment" action (patient pre-selected via `?patient_id=`). Empty if the page can't be resolved.
 * @var string $services_url        Base URL of the admin Services section, for the Doctors table's "View Services" action.
 * @var string $doctor_sessions_url Base URL of the admin Doctor Sessions section, for the Doctors table's "View Sessions" action.
 * @var string $section_url      This section's own URL (no filters), for the filter form and "Clear" link.
 * @var array  $specializations  Specialization slug => label, see Specializations::get_all(). Empty outside the doctors table.
 * @var array  $clinic_locations Rows from Clinic_Locations::get_all(). Empty outside the patients table.
 * @var array  $filters          Active filter values: status, specialization, clinic_location_id, search.
 * @var bool   $read_only        Whether the viewer (a Receptionist) can only look, never add/edit/deactivate/delete —
 *                                only relevant for 'doctors'/'patients' (the 'receptionist' section itself is never
 *                                reachable by a receptionist viewer, see Admin_Dashboard::RECEPTIONIST_ALLOWED_SECTIONS).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dak_admin_user_table_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for an avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_admin_user_table_initials( $name ) {
		$words    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			if ( '' !== $word ) {
				$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
			}
		}

		return '' !== $initials ? $initials : '?';
	}
endif;

$dak_is_patients      = 'patients' === $section;
$dak_is_receptionists = 'receptionist' === $section;
$dak_is_doctors        = ! $dak_is_patients && ! $dak_is_receptionists;
$dak_has_filters       = '' !== $filters['status'] || '' !== $filters['specialization'] || ! empty( $filters['clinic_location_id'] ) || '' !== $filters['search'];
$dak_read_only         = ! empty( $read_only );
?>
	<section class="dak-dashboard-card dak-appt-filters-card">
		<form
			method="get"
			action="<?php echo esc_url( $section_url ); ?>"
			class="dak-appt-filters-form"
			data-live-filter="doctor_ak_admin_users_filter"
			data-live-filter-target="#dak-admin-users-tab-content"
			data-live-filter-nonce="dakAdminUsers"
		>
			<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>">
			<div class="dak-field">
				<label for="dak-admin-users-filter-search"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></label>
				<input type="search" id="dak-admin-users-filter-search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name or email…', 'doctor-ak-portal' ); ?>">
			</div>

			<?php if ( $dak_is_doctors ) : ?>
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

			<?php if ( $dak_is_patients ) : ?>
				<div class="dak-field">
					<label for="dak-admin-users-filter-clinic"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></label>
					<select id="dak-admin-users-filter-clinic" name="clinic_location_id">
						<option value=""><?php esc_html_e( 'All clinics', 'doctor-ak-portal' ); ?></option>
						<?php foreach ( $clinic_locations as $clinic_location ) : ?>
							<option value="<?php echo esc_attr( $clinic_location['id'] ); ?>" <?php selected( (int) $filters['clinic_location_id'], $clinic_location['id'] ); ?>>
								<?php echo esc_html( sprintf( '%1$s — %2$s, %3$s', $clinic_location['name'], $clinic_location['area_label'], $clinic_location['city_label'] ) ); ?>
							</option>
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
					<a
						class="dak-button dak-button-secondary"
						href="<?php echo esc_url( $section_url ); ?>"
						data-live-filter-clear
						data-live-filter="doctor_ak_admin_users_filter"
						data-live-filter-target="#dak-admin-users-tab-content"
						data-live-filter-nonce="dakAdminUsers"
					><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
	</section>

<?php if ( $dak_is_patients ) : ?>

	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Patient directory', 'doctor-ak-portal' ); ?></h2>
	</div>

		<?php if ( empty( $users ) ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No patients have been added yet.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<?php foreach ( $users as $row ) : ?>
				<div id="dak-user-<?php echo esc_attr( $row['id'] ); ?>" class="dak-admin-record-row" data-user-row="<?php echo esc_attr( $row['id'] ); ?>">
					<div class="dak-admin-patient-row">
						<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo esc_html( dak_admin_user_table_initials( $row['name'] ) ); ?></span>
						<span class="dak-admin-patient-row-info">
							<strong><?php echo esc_html( $row['name'] ); ?></strong>
							<span class="dak-admin-patient-row-id"><?php echo esc_html( sprintf( 'P-%03d', $row['id'] ) ); ?></span>
							<?php if ( $row['is_discharged'] ) : ?>
								<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-disabled"><?php esc_html_e( 'Discharged', 'doctor-ak-portal' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="dak-admin-patient-row-email"><?php echo esc_html( $row['email'] ); ?></span>
						<span class="dak-admin-patient-row-phone"><?php echo esc_html( '' !== $row['phone'] ? $row['phone'] : '—' ); ?></span>
						<span class="dak-admin-patient-row-clinic"><?php echo esc_html( '' !== $row['clinic_location_label'] ? $row['clinic_location_label'] : '—' ); ?></span>
						<span class="dak-admin-patient-row-since">
							<?php echo esc_html( sprintf( /* translators: %s: registration date. */ __( 'Since %s', 'doctor-ak-portal' ), $row['registered_date'] ) ); ?>
						</span>
						<span class="dak-admin-record-row-actions">
							<?php if ( $appointments_url ) : ?>
								<a
									class="dak-icon-button"
									href="<?php echo esc_url( add_query_arg( array( 'patient_id' => $row['id'], 'range' => '' ), $appointments_url ) ); ?>"
									title="<?php esc_attr_e( 'View Appointments', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'View Appointments', 'doctor-ak-portal' ); ?>"
								><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg></a>
							<?php endif; ?>
							<?php if ( $encounters_url ) : ?>
								<a
									class="dak-icon-button"
									href="<?php echo esc_url( add_query_arg( 'patient_id', $row['id'], $encounters_url ) ); ?>"
									title="<?php esc_attr_e( 'Encounter', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Encounter', 'doctor-ak-portal' ); ?>"
								><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3.5h6a1 1 0 0 1 1 1V16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M8 2.5h4v2H8z"/><path d="M7.5 9h5M7.5 12h5M7.5 15h3"/></svg></a>
							<?php endif; ?>
							<?php if ( $booking_url && 'patients' === $section ) : ?>
								<a
									class="dak-icon-button"
									href="<?php echo esc_url( add_query_arg( 'patient_id', $row['id'], $booking_url ) ); ?>"
									title="<?php esc_attr_e( 'Book Appointment', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Book Appointment', 'doctor-ak-portal' ); ?>"
								><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 4v12M4 10h12"/><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/></svg></a>
							<?php endif; ?>
							<?php if ( ! $dak_read_only ) : ?>
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
									class="dak-icon-button<?php echo $row['is_discharged'] ? ' dak-icon-button-success' : ' dak-icon-button-warning'; ?>"
									data-admin-toggle-discharge
									data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
									data-is-discharged="<?php echo $row['is_discharged'] ? '1' : '0'; ?>"
									title="<?php echo $row['is_discharged'] ? esc_attr__( 'Readmit', 'doctor-ak-portal' ) : esc_attr__( 'Discharge', 'doctor-ak-portal' ); ?>"
									aria-label="<?php echo $row['is_discharged'] ? esc_attr__( 'Readmit', 'doctor-ak-portal' ) : esc_attr__( 'Discharge', 'doctor-ak-portal' ); ?>"
								><?php if ( $row['is_discharged'] ) : ?><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4v-.5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V16"/><path d="M8 10h9M17 10l-2.5-2.5M17 10l-2.5 2.5"/></svg><?php else : ?><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v-.5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V16"/><path d="M17 10H8M8 10l2.5-2.5M8 10l2.5 2.5"/></svg><?php endif; ?></button>
								<button
									type="button"
									class="dak-icon-button dak-icon-button-danger"
									data-admin-delete-user
									data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
									title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
								><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg></button>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

<?php elseif ( $dak_is_receptionists ) : ?>

	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Receptionist accounts', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $users ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No receptionist accounts have been added yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $users as $row ) : ?>
			<div id="dak-user-<?php echo esc_attr( $row['id'] ); ?>" class="dak-admin-record-row" data-user-row="<?php echo esc_attr( $row['id'] ); ?>">
				<div class="dak-admin-patient-row">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo esc_html( dak_admin_user_table_initials( $row['name'] ) ); ?></span>
					<span class="dak-admin-patient-row-info">
						<strong><?php echo esc_html( $row['name'] ); ?></strong>
					</span>
					<span class="dak-admin-patient-row-email"><?php echo esc_html( $row['email'] ); ?></span>
					<span class="dak-status-pill dak-status-pill-outline <?php echo $row['is_disabled'] ? 'dak-status-pill-is-disabled' : 'dak-status-pill-is-active'; ?>">
						<?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?>
					</span>
					<span class="dak-admin-patient-row-since">
						<?php echo esc_html( sprintf( /* translators: %s: registration date. */ __( 'Since %s', 'doctor-ak-portal' ), $row['registered_date'] ) ); ?>
					</span>
					<span class="dak-admin-record-row-actions">
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
					</span>
				</div>
				<div class="dak-admin-record-row-secondary">
					<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Clinics:', 'doctor-ak-portal' ); ?></span>
					<span><?php echo esc_html( implode( ', ', $row['clinic_labels'] ) ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

<?php elseif ( empty( $users ) ) : ?>
	<p class="dak-empty-state"><?php esc_html_e( 'No doctors have been added yet.', 'doctor-ak-portal' ); ?></p>
<?php else : ?>

	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Doctor directory', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php foreach ( $users as $row ) : ?>
		<div id="dak-user-<?php echo esc_attr( $row['id'] ); ?>" class="dak-admin-record-row" data-user-row="<?php echo esc_attr( $row['id'] ); ?>">
			<div class="dak-admin-record-row-main">
				<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo esc_html( dak_admin_user_table_initials( $row['name'] ) ); ?></span>
				<span class="dak-admin-record-row-info">
					<strong><?php echo esc_html( sprintf( 'Dr. %s', $row['name'] ) ); ?></strong>
					<span class="dak-admin-record-row-id"><?php echo esc_html( $row['email'] ); ?></span>
				</span>

				<span class="dak-admin-record-row-tags">
					<?php if ( empty( $row['specialization_labels'] ) ) : ?>
						<span class="dak-status-pill dak-status-pill-outline">&mdash;</span>
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
				</span>

				<span class="dak-status-pill dak-status-pill-outline <?php echo $row['is_disabled'] ? 'dak-status-pill-is-disabled' : 'dak-status-pill-is-active'; ?>">
					<?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?>
				</span>

				<span class="dak-admin-record-row-actions">
					<?php if ( $appointments_url ) : ?>
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( add_query_arg( 'doctor_id', $row['id'], $appointments_url ) ); ?>"
							title="<?php esc_attr_e( 'View Appointments', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'View Appointments', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg></a>
					<?php endif; ?>
					<?php if ( $services_url ) : ?>
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( add_query_arg( 'doctor_id', $row['id'], $services_url ) ); ?>"
							title="<?php esc_attr_e( 'View Services', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'View Services', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 2.5h7l3 3V17a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1z"/><path d="M12 2.5V6h3"/><path d="M6.5 10.5h7M6.5 13.5h5"/></svg></a>
					<?php endif; ?>
					<?php if ( $doctor_sessions_url ) : ?>
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( add_query_arg( 'doctor_id', $row['id'], $doctor_sessions_url ) ); ?>"
							title="<?php esc_attr_e( 'View Sessions', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'View Sessions', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10.5" r="7"/><path d="M10 6.5v4l3 2"/><path d="M7.5 2.5h5"/></svg></a>
					<?php endif; ?>
				</span>

				<?php if ( ! $dak_read_only ) : ?>
					<span class="dak-admin-record-row-actions">
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
					</span>
				<?php endif; ?>
			</div>

			<div class="dak-admin-record-row-secondary">
				<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Clinics:', 'doctor-ak-portal' ); ?></span>
				<?php if ( empty( $row['clinic_labels'] ) ) : ?>
					<span class="dak-admin-record-row-secondary-empty">&mdash;</span>
				<?php else : ?>
					<div class="dak-specialty-tags dak-table-specialty-tags" data-specialty-tags>
						<?php foreach ( $row['clinic_labels'] as $dak_clinic_index => $dak_clinic_label ) : ?>
							<span class="dak-specialty-tag<?php echo $dak_clinic_index >= 2 ? ' dak-specialty-tag-extra dak-hidden' : ''; ?>"><?php echo esc_html( $dak_clinic_label ); ?></span>
						<?php endforeach; ?>
						<?php if ( count( $row['clinic_labels'] ) > 2 ) : ?>
							<button
								type="button"
								class="dak-specialty-tag dak-specialty-tag-more"
								data-specialty-toggle
								data-more-label="<?php echo esc_attr( sprintf( '+%d', count( $row['clinic_labels'] ) - 2 ) ); ?>"
								data-less-label="<?php esc_attr_e( 'Show less', 'doctor-ak-portal' ); ?>"
							><?php echo esc_html( sprintf( '+%d', count( $row['clinic_labels'] ) - 2 ) ); ?></button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<span class="dak-status-pill dak-status-pill-outline <?php echo $row['video_consultation_allowed'] ? 'dak-status-pill-is-active' : ''; ?>">
					<?php
					echo esc_html(
						$row['video_consultation_allowed']
							? __( 'Video Consultation: Yes', 'doctor-ak-portal' )
							: __( 'Video Consultation: No', 'doctor-ak-portal' )
					);
					?>
				</span>
			</div>
		</div>
	<?php endforeach; ?>
<?php endif; ?>
