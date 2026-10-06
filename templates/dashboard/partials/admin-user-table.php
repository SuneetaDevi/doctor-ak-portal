<?php
/**
 * Template: Patients / Receptionists directories for the admin dashboard —
 * the shared list pattern (filter toolbar + one results surface + a
 * semantic table, see section 20 of doctor-ak-dashboard-ui.css). Doctors
 * have their own directory, admin-doctors-table.php.
 *
 * Every row action keeps its exact data-* contract (data-admin-toggle-status
 * / data-admin-toggle-discharge / data-admin-delete-user), so the same
 * document-level handlers in doctor-ak-admin-dashboard.js — with their
 * confirm prompts and server-side permission checks — still run; routine
 * actions sit under "More", destructive ones below a separator.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $users            Row view-models, see Admin_Dashboard::row_data().
 * @var string $section          'doctors', 'patients', or 'receptionist'.
 * @var string $appointments_url    Base URL of the admin Appointments section, for the "Appointments" action.
 * @var string $encounters_url      Base URL of the admin Encounters section, for the Patients table's "Encounters" action. Empty if the current viewer (admin or Receptionist) can't access Encounters — see Admin_Dashboard::users_section_html().
 * @var string $booking_url         URL of the public booking page, for the Patients table's "Book appointment" action (patient pre-selected via `?patient_id=`). Empty if the page can't be resolved.
 * @var string $services_url        Base URL of the admin Services section (Doctors directory only).
 * @var string $doctor_sessions_url Base URL of the admin Doctor Sessions section (Doctors directory only).
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

// The Doctors directory is its own table (admin-doctors-table.php); kept
// here only so any caller still passing 'doctors' gets the current one.
if ( 'patients' !== $section && 'receptionist' !== $section ) {
	echo ( new \DoctorAKPortal\Includes\Template_Loader() )->get_template( 'dashboard/partials/admin-doctors-table.php', get_defined_vars() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
	return;
}

$dak_is_patients = 'patients' === $section;
$dak_has_filters = '' !== $filters['status'] || ! empty( $filters['clinic_location_id'] ) || '' !== $filters['search'];
$dak_read_only   = ! empty( $read_only );
$dak_live_attrs  = ' data-live-filter="doctor_ak_admin_users_filter" data-live-filter-target="#dak-admin-users-tab-content" data-live-filter-nonce="dakAdminUsers"';
$dak_noun        = $dak_is_patients ? __( 'Patients', 'doctor-ak-portal' ) : __( 'Receptionists', 'doctor-ak-portal' );
?>
<div class="dak-list-toolbar">
	<form
		method="get"
		action="<?php echo esc_url( $section_url ); ?>"
		class="dak-list-filters"
		<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>
	>
		<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>">
		<div class="dak-field is-search">
			<label for="dak-admin-users-filter-search"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></label>
			<input type="search" id="dak-admin-users-filter-search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name or email…', 'doctor-ak-portal' ); ?>">
		</div>

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
			<label for="dak-admin-users-filter-status"><?php esc_html_e( 'Account status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-users-filter-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<option value="active" <?php selected( $filters['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'doctor-ak-portal' ); ?></option>
				<option value="disabled" <?php selected( $filters['status'], 'disabled' ); ?>><?php esc_html_e( 'Deactivated', 'doctor-ak-portal' ); ?></option>
			</select>
		</div>

		<div class="dak-list-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $section_url ); ?>" data-live-filter-clear<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</div>

<section class="dak-results" aria-labelledby="dak-admin-users-results-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-admin-users-results-title"><?php echo esc_html( $dak_noun ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $users ) ) ); ?></span></h2>
	</div>

	<?php if ( empty( $users ) ) : ?>
		<p class="dak-empty-state">
			<?php
			if ( $dak_has_filters ) {
				esc_html_e( 'No accounts match these filters.', 'doctor-ak-portal' );
			} elseif ( $dak_is_patients ) {
				esc_html_e( 'No patients have been added yet.', 'doctor-ak-portal' );
			} else {
				esc_html_e( 'No receptionist accounts have been added yet.', 'doctor-ak-portal' );
			}
			?>
		</p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table <?php echo $dak_is_patients ? 'dak-patients-table' : 'dak-receptionists-table'; ?>">
				<thead>
					<tr>
						<th scope="col"><?php echo $dak_is_patients ? esc_html__( 'Patient', 'doctor-ak-portal' ) : esc_html__( 'Receptionist', 'doctor-ak-portal' ); ?></th>
						<?php if ( $dak_is_patients ) : ?>
							<th scope="col"><?php esc_html_e( 'Contact', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></th>
						<?php else : ?>
							<th scope="col"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></th>
						<?php endif; ?>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Registered', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $users as $row ) : ?>
						<?php
						$dak_edit_url     = add_query_arg( array( 'view' => 'form', 'user_id' => $row['id'] ), $section_url );
						$dak_toggle_label = $row['is_disabled'] ? __( 'Reactivate account', 'doctor-ak-portal' ) : __( 'Deactivate account', 'doctor-ak-portal' );
						$dak_clinics      = array_values( array_filter( (array) $row['clinic_labels'] ) );
						$dak_clinics_id   = 'dak-user-' . $row['id'] . '-clinics';

						// The one visible action, then everything else under More.
						$dak_primary = null;
						$dak_menu    = array();

						if ( $dak_is_patients ) {
							if ( $appointments_url ) {
								$dak_primary = array( __( 'Appointments', 'doctor-ak-portal' ), add_query_arg( array( 'patient_id' => $row['id'], 'range' => '' ), $appointments_url ) );
							}

							if ( $encounters_url ) {
								$dak_menu[] = array( __( 'View encounters', 'doctor-ak-portal' ), add_query_arg( 'patient_id', $row['id'], $encounters_url ) );
							}

							if ( $booking_url ) {
								$dak_menu[] = array( __( 'Book appointment', 'doctor-ak-portal' ), add_query_arg( 'patient_id', $row['id'], $booking_url ) );
							}

							if ( ! $dak_read_only ) {
								$dak_menu[] = array( __( 'Edit patient', 'doctor-ak-portal' ), $dak_edit_url );
							}
						} elseif ( ! $dak_read_only ) {
							$dak_primary = array( __( 'Edit', 'doctor-ak-portal' ), $dak_edit_url );
						}

						if ( ! $dak_primary && ! empty( $dak_menu ) ) {
							$dak_primary = array_shift( $dak_menu );
						}

						$dak_has_menu = ! empty( $dak_menu ) || ! $dak_read_only;
						?>
						<tr id="dak-user-<?php echo esc_attr( $row['id'] ); ?>" data-row data-user-row="<?php echo esc_attr( $row['id'] ); ?>">
							<td class="dak-col-primary" data-label="<?php echo $dak_is_patients ? esc_attr__( 'Patient', 'doctor-ak-portal' ) : esc_attr__( 'Receptionist', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $row['name'] ); ?></span>
									<?php if ( $dak_is_patients ) : ?>
										<span class="dak-cell-sub dak-cell-id"><?php echo esc_html( sprintf( 'P-%03d', $row['id'] ) ); ?></span>
									<?php else : ?>
										<span class="dak-cell-sub dak-cell-email"><?php echo \DoctorAKPortal\Includes\Dashboard_Format::email_html( $row['email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside email_html(). ?></span>
									<?php endif; ?>
								</span>
							</td>

							<?php if ( $dak_is_patients ) : ?>
								<td data-label="<?php esc_attr_e( 'Contact', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-cell-email"><?php echo \DoctorAKPortal\Includes\Dashboard_Format::email_html( $row['email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside email_html(). ?></span>
										<span class="dak-cell-sub is-tabular"><?php echo esc_html( '' !== (string) $row['phone'] ? $row['phone'] : __( 'No phone', 'doctor-ak-portal' ) ); ?></span>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Clinic', 'doctor-ak-portal' ); ?>">
									<?php if ( '' !== $row['clinic_location_label'] ) : ?>
										<?php echo esc_html( $row['clinic_location_label'] ); ?>
									<?php else : ?>
										<span class="dak-cell-sub"><?php esc_html_e( 'Not assigned', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</td>
							<?php else : ?>
								<td data-label="<?php esc_attr_e( 'Clinics', 'doctor-ak-portal' ); ?>">
									<?php if ( empty( $dak_clinics ) ) : ?>
										<span class="dak-cell-sub"><?php esc_html_e( 'None', 'doctor-ak-portal' ); ?></span>
									<?php else : ?>
										<span class="dak-cell-stack">
											<span><?php echo esc_html( $dak_clinics[0] ); ?></span>
											<?php if ( count( $dak_clinics ) > 1 ) : ?>
												<button
													type="button"
													class="dak-disclosure-toggle"
													data-dak-disclosure
													aria-expanded="false"
													aria-controls="<?php echo esc_attr( $dak_clinics_id ); ?>"
													data-more-label="<?php echo esc_attr( sprintf( /* translators: %d: number of other clinics. */ _n( '+%d clinic', '+%d clinics', count( $dak_clinics ) - 1, 'doctor-ak-portal' ), count( $dak_clinics ) - 1 ) ); ?>"
													data-less-label="<?php esc_attr_e( 'Show less', 'doctor-ak-portal' ); ?>"
												><?php echo esc_html( sprintf( /* translators: %d: number of other clinics. */ _n( '+%d clinic', '+%d clinics', count( $dak_clinics ) - 1, 'doctor-ak-portal' ), count( $dak_clinics ) - 1 ) ); ?></button>
												<ul class="dak-disclosure-list" id="<?php echo esc_attr( $dak_clinics_id ); ?>" hidden>
													<?php foreach ( array_slice( $dak_clinics, 1 ) as $dak_clinic_label ) : ?>
														<li><?php echo esc_html( $dak_clinic_label ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>
										</span>
									<?php endif; ?>
								</td>
							<?php endif; ?>

							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-status-pill <?php echo $row['is_disabled'] ? 'dak-status-pill-is-neutral' : 'dak-status-pill-is-active'; ?>"><?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?></span>
									<?php if ( $dak_is_patients && $row['is_discharged'] ) : ?>
										<span class="dak-cell-note"><?php esc_html_e( 'Discharged', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</span>
							</td>

							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Registered', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-sub is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $row['registered_date'], $row['registered_date'] ) ); ?></span>
							</td>

							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<?php if ( $dak_primary ) : ?>
										<a class="dak-text-action" href="<?php echo esc_url( $dak_primary[1] ); ?>" aria-label="<?php echo esc_attr( $dak_primary[0] . ' — ' . $row['name'] ); ?>"><?php echo esc_html( $dak_primary[0] ); ?></a>
									<?php endif; ?>

									<?php if ( $dak_has_menu ) : ?>
										<details class="dak-row-menu">
											<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: person's name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $row['name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
											<div class="dak-row-menu-panel" role="menu">
												<?php foreach ( $dak_menu as $dak_item ) : ?>
													<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $dak_item[1] ); ?>"><?php echo esc_html( $dak_item[0] ); ?></a>
												<?php endforeach; ?>
												<?php if ( ! $dak_read_only ) : ?>
													<?php if ( ! empty( $dak_menu ) ) : ?>
														<hr class="dak-row-menu-sep">
													<?php endif; ?>
													<button type="button" class="dak-row-menu-item" role="menuitem" data-admin-toggle-status data-user-id="<?php echo esc_attr( $row['id'] ); ?>" data-is-disabled="<?php echo $row['is_disabled'] ? '1' : '0'; ?>"><?php echo esc_html( $dak_toggle_label ); ?></button>
													<?php if ( $dak_is_patients ) : ?>
														<button type="button" class="dak-row-menu-item" role="menuitem" data-admin-toggle-discharge data-user-id="<?php echo esc_attr( $row['id'] ); ?>" data-is-discharged="<?php echo $row['is_discharged'] ? '1' : '0'; ?>"><?php echo $row['is_discharged'] ? esc_html__( 'Readmit patient', 'doctor-ak-portal' ) : esc_html__( 'Discharge patient', 'doctor-ak-portal' ); ?></button>
													<?php endif; ?>
													<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-admin-delete-user data-user-id="<?php echo esc_attr( $row['id'] ); ?>"><?php echo $dak_is_patients ? esc_html__( 'Delete patient', 'doctor-ak-portal' ) : esc_html__( 'Delete receptionist', 'doctor-ak-portal' ); ?></button>
												<?php endif; ?>
											</div>
										</details>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
