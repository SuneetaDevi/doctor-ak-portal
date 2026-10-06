<?php
/**
 * Template: Admin dashboard → Doctors directory.
 *
 * A semantic table — Doctor | Specialization | Clinics / visit options |
 * Account status | Actions — with supporting detail in a per-row
 * <template> that fills the shared "Doctor details" dialog
 * (templates/modal/admin-doctor-view-modal.php). Replaces the old
 * per-row grid (admin-user-table.php, still used for Patients and
 * Receptionists), whose columns never lined up between rows.
 *
 * Every existing action keeps its exact data-* contract, so the same
 * document-level handlers in doctor-ak-admin-dashboard.js (and their
 * confirm prompts and server-side checks) still run:
 * data-admin-toggle-status / data-admin-delete-user.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $users               Row view-models, see Admin_Dashboard::row_data().
 * @var string $section             Always 'doctors' here.
 * @var string $appointments_url    Base URL of the admin Appointments section.
 * @var string $services_url        Base URL of the admin Services section.
 * @var string $doctor_sessions_url Base URL of the admin Doctor Sessions section.
 * @var string $section_url         This section's own URL (no filters).
 * @var array  $specializations     Specialization slug => label.
 * @var array  $filters             Active filter values: status, specialization, clinic_location_id, search.
 * @var bool   $read_only           Whether Edit/Deactivate/Delete are hidden for this viewer.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dak_admin_doctors_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for the avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_admin_doctors_initials( $name ) {
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

$dak_has_filters = '' !== $filters['status'] || '' !== $filters['specialization'] || '' !== $filters['search'];
$dak_read_only   = ! empty( $read_only );
$dak_genders     = \DoctorAKPortal\Includes\Doctor_Gender::get_all();
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

			<div class="dak-field">
				<label for="dak-admin-users-filter-specialization"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-users-filter-specialization" name="specialization">
					<option value=""><?php esc_html_e( 'All specializations', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $specializations as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['specialization'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="dak-field">
				<label for="dak-admin-users-filter-status"><?php esc_html_e( 'Account status', 'doctor-ak-portal' ); ?></label>
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

	<div class="dak-dashboard-card-header">
		<h2>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of doctors listed. */
					_n( '%d doctor', '%d doctors', count( $users ), 'doctor-ak-portal' ),
					count( $users )
				)
			);
			?>
		</h2>
	</div>

	<?php if ( empty( $users ) ) : ?>
		<p class="dak-empty-state">
			<?php
			echo esc_html(
				$dak_has_filters
					? __( 'No doctors match these filters.', 'doctor-ak-portal' )
					: __( 'No doctors have been added yet.', 'doctor-ak-portal' )
			);
			?>
		</p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-doctors-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Clinics / visit options', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Account status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><span class="dak-visually-hidden"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $users as $row ) : ?>
						<?php
						$dak_doctor_name   = sprintf( 'Dr. %s', $row['name'] );
						$dak_specs         = array_values( array_filter( (array) $row['specialization_labels'] ) );
						$dak_clinics       = isset( $row['physical_clinic_labels'] ) ? (array) $row['physical_clinic_labels'] : array();
						$dak_spec_list_id  = 'dak-doctor-' . $row['id'] . '-specs';
						$dak_clinic_list_id = 'dak-doctor-' . $row['id'] . '-clinics';
						$dak_location_bits = array_filter(
							array(
								\DoctorAKPortal\Includes\Locations::area_label( $row['country'], $row['city'], $row['area'] ),
								\DoctorAKPortal\Includes\Locations::city_label( $row['country'], $row['city'] ),
							)
						);
						$dak_actions = array();

						if ( $appointments_url ) {
							$dak_actions[] = array( __( 'View appointments', 'doctor-ak-portal' ), add_query_arg( 'doctor_id', $row['id'], $appointments_url ) );
						}

						if ( $services_url ) {
							$dak_actions[] = array( __( 'View services', 'doctor-ak-portal' ), add_query_arg( 'doctor_id', $row['id'], $services_url ) );
						}

						if ( $doctor_sessions_url ) {
							$dak_actions[] = array( __( 'View sessions', 'doctor-ak-portal' ), add_query_arg( 'doctor_id', $row['id'], $doctor_sessions_url ) );
						}

						if ( ! $dak_read_only ) {
							$dak_actions[] = array( __( 'Edit doctor', 'doctor-ak-portal' ), add_query_arg( array( 'view' => 'form', 'user_id' => $row['id'] ), $section_url ) );
						}

						$dak_toggle_label = $row['is_disabled'] ? __( 'Reactivate account', 'doctor-ak-portal' ) : __( 'Deactivate account', 'doctor-ak-portal' );
						?>
						<tr id="dak-user-<?php echo esc_attr( $row['id'] ); ?>" data-row data-user-row="<?php echo esc_attr( $row['id'] ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
								<div class="dak-cell-identity">
									<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
										<?php if ( '' !== $row['avatar_url'] ) : ?>
											<img src="<?php echo esc_url( $row['avatar_url'] ); ?>" alt="">
										<?php else : ?>
											<?php echo esc_html( dak_admin_doctors_initials( $row['name'] ) ); ?>
										<?php endif; ?>
									</span>
									<span class="dak-cell-stack">
										<span class="dak-cell-primary"><?php echo esc_html( $dak_doctor_name ); ?></span>
										<span class="dak-cell-sub"><?php echo esc_html( $row['email'] ); ?></span>
									</span>
								</div>
							</td>

							<td data-label="<?php esc_attr_e( 'Specialization', 'doctor-ak-portal' ); ?>">
								<?php if ( empty( $dak_specs ) ) : ?>
									<span class="dak-cell-sub"><?php esc_html_e( 'Not set', 'doctor-ak-portal' ); ?></span>
								<?php else : ?>
									<span class="dak-cell-stack">
										<span><?php echo esc_html( $dak_specs[0] ); ?></span>
										<?php if ( count( $dak_specs ) > 1 ) : ?>
											<button
												type="button"
												class="dak-disclosure-toggle"
												data-dak-disclosure
												aria-expanded="false"
												aria-controls="<?php echo esc_attr( $dak_spec_list_id ); ?>"
												data-more-label="<?php echo esc_attr( sprintf( /* translators: %d: number of hidden items. */ __( '+%d more', 'doctor-ak-portal' ), count( $dak_specs ) - 1 ) ); ?>"
												data-less-label="<?php esc_attr_e( 'Show less', 'doctor-ak-portal' ); ?>"
											><?php echo esc_html( sprintf( /* translators: %d: number of hidden items. */ __( '+%d more', 'doctor-ak-portal' ), count( $dak_specs ) - 1 ) ); ?></button>
											<ul class="dak-disclosure-list" id="<?php echo esc_attr( $dak_spec_list_id ); ?>" hidden>
												<?php foreach ( array_slice( $dak_specs, 1 ) as $dak_spec ) : ?>
													<li><?php echo esc_html( $dak_spec ); ?></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</td>

							<td data-label="<?php esc_attr_e( 'Clinics / visit options', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<?php if ( ! empty( $dak_clinics ) ) : ?>
										<span><?php echo esc_html( $dak_clinics[0] ); ?></span>
										<?php if ( count( $dak_clinics ) > 1 ) : ?>
											<button
												type="button"
												class="dak-disclosure-toggle"
												data-dak-disclosure
												aria-expanded="false"
												aria-controls="<?php echo esc_attr( $dak_clinic_list_id ); ?>"
												data-more-label="<?php echo esc_attr( sprintf( /* translators: %d: number of other clinics. */ _n( '+%d clinic', '+%d clinics', count( $dak_clinics ) - 1, 'doctor-ak-portal' ), count( $dak_clinics ) - 1 ) ); ?>"
												data-less-label="<?php esc_attr_e( 'Show less', 'doctor-ak-portal' ); ?>"
											><?php echo esc_html( sprintf( /* translators: %d: number of other clinics. */ _n( '+%d clinic', '+%d clinics', count( $dak_clinics ) - 1, 'doctor-ak-portal' ), count( $dak_clinics ) - 1 ) ); ?></button>
											<ul class="dak-disclosure-list" id="<?php echo esc_attr( $dak_clinic_list_id ); ?>" hidden>
												<?php foreach ( array_slice( $dak_clinics, 1 ) as $dak_clinic_name ) : ?>
													<li><?php echo esc_html( $dak_clinic_name ); ?></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									<?php elseif ( ! $row['video_consultation_allowed'] ) : ?>
										<span class="dak-cell-sub"><?php esc_html_e( 'No clinics set', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
									<?php if ( $row['video_consultation_allowed'] ) : ?>
										<span class="dak-cell-sub"><?php esc_html_e( 'Video visits offered', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</span>
							</td>

							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Account status', 'doctor-ak-portal' ); ?>">
								<span class="dak-status-pill <?php echo $row['is_disabled'] ? 'dak-status-pill-is-neutral' : 'dak-status-pill-is-active'; ?>">
									<?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?>
								</span>
							</td>

							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<button
										type="button"
										class="dak-button dak-button-secondary dak-button-sm"
										data-admin-doctor-view="<?php echo esc_attr( $row['id'] ); ?>"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: doctor name. */ __( 'View details for %s', 'doctor-ak-portal' ), $dak_doctor_name ) ); ?>"
									><?php esc_html_e( 'View details', 'doctor-ak-portal' ); ?></button>

									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: doctor name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $dak_doctor_name ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<?php foreach ( $dak_actions as $dak_action ) : ?>
												<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $dak_action[1] ); ?>"><?php echo esc_html( $dak_action[0] ); ?></a>
											<?php endforeach; ?>
											<?php if ( ! $dak_read_only ) : ?>
												<hr class="dak-row-menu-sep">
												<button
													type="button"
													class="dak-row-menu-item"
													role="menuitem"
													data-admin-toggle-status
													data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
													data-is-disabled="<?php echo $row['is_disabled'] ? '1' : '0'; ?>"
												><?php echo esc_html( $dak_toggle_label ); ?></button>
												<button
													type="button"
													class="dak-row-menu-item is-danger"
													role="menuitem"
													data-admin-delete-user
													data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
												><?php esc_html_e( 'Delete doctor', 'doctor-ak-portal' ); ?></button>
											<?php endif; ?>
										</div>
									</details>
								</div>

								<template data-admin-doctor-template="<?php echo esc_attr( $row['id'] ); ?>">
									<div class="dak-detail-group">
										<h3 class="dak-detail-group-title"><?php esc_html_e( 'Profile', 'doctor-ak-portal' ); ?></h3>
										<div class="dak-cell-identity">
											<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
												<?php if ( '' !== $row['avatar_url'] ) : ?>
													<img src="<?php echo esc_url( $row['avatar_url'] ); ?>" alt="">
												<?php else : ?>
													<?php echo esc_html( dak_admin_doctors_initials( $row['name'] ) ); ?>
												<?php endif; ?>
											</span>
											<span class="dak-cell-stack">
												<strong><?php echo esc_html( $dak_doctor_name ); ?></strong>
												<span class="dak-cell-sub"><?php echo esc_html( $row['email'] ); ?></span>
											</span>
										</div>
										<dl class="dak-detail-list">
											<dt><?php esc_html_e( 'Account status', 'doctor-ak-portal' ); ?></dt>
											<dd><?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?></dd>
											<dt><?php esc_html_e( 'Qualification', 'doctor-ak-portal' ); ?></dt>
											<dd><?php echo esc_html( '' !== (string) $row['qualification'] ? $row['qualification'] : __( 'Not set', 'doctor-ak-portal' ) ); ?></dd>
											<dt><?php esc_html_e( 'Experience', 'doctor-ak-portal' ); ?></dt>
											<dd>
												<?php
												echo esc_html(
													'' !== (string) $row['years_experience']
														/* translators: %s: number of years. */
														? sprintf( __( '%s years', 'doctor-ak-portal' ), $row['years_experience'] )
														: __( 'Not set', 'doctor-ak-portal' )
												);
												?>
											</dd>
											<?php if ( '' !== (string) $row['gender'] ) : ?>
												<dt><?php esc_html_e( 'Gender', 'doctor-ak-portal' ); ?></dt>
												<dd><?php echo esc_html( isset( $dak_genders[ $row['gender'] ] ) ? $dak_genders[ $row['gender'] ] : $row['gender'] ); ?></dd>
											<?php endif; ?>
											<dt><?php esc_html_e( 'Phone', 'doctor-ak-portal' ); ?></dt>
											<dd><?php echo esc_html( '' !== (string) $row['phone'] ? $row['phone'] : __( 'Not set', 'doctor-ak-portal' ) ); ?></dd>
											<?php if ( ! empty( $dak_location_bits ) ) : ?>
												<dt><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></dt>
												<dd><?php echo esc_html( implode( ', ', $dak_location_bits ) ); ?></dd>
											<?php endif; ?>
											<dt><?php esc_html_e( 'Registered', 'doctor-ak-portal' ); ?></dt>
											<dd><?php echo esc_html( $row['registered_date'] ); ?></dd>
										</dl>
									</div>

									<div class="dak-detail-group">
										<h3 class="dak-detail-group-title"><?php esc_html_e( 'Specialties', 'doctor-ak-portal' ); ?></h3>
										<?php if ( empty( $dak_specs ) ) : ?>
											<p class="dak-cell-sub"><?php esc_html_e( 'No specialization set.', 'doctor-ak-portal' ); ?></p>
										<?php else : ?>
											<div class="dak-detail-tags">
												<?php foreach ( $dak_specs as $dak_spec ) : ?>
													<span class="dak-status-pill"><?php echo esc_html( $dak_spec ); ?></span>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									</div>

									<div class="dak-detail-group">
										<h3 class="dak-detail-group-title"><?php esc_html_e( 'Clinics and visit options', 'doctor-ak-portal' ); ?></h3>
										<dl class="dak-detail-list">
											<dt><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></dt>
											<dd><?php echo esc_html( ! empty( $dak_clinics ) ? implode( "\n", $dak_clinics ) : __( 'No clinics set', 'doctor-ak-portal' ) ); ?></dd>
											<dt><?php esc_html_e( 'Video visits', 'doctor-ak-portal' ); ?></dt>
											<dd><?php echo $row['video_consultation_allowed'] ? esc_html__( 'Offered', 'doctor-ak-portal' ) : esc_html__( 'Not offered', 'doctor-ak-portal' ); ?></dd>
										</dl>
									</div>

									<div class="dak-detail-group">
										<h3 class="dak-detail-group-title"><?php esc_html_e( 'Management', 'doctor-ak-portal' ); ?></h3>
										<div class="dak-detail-actions">
											<?php foreach ( $dak_actions as $dak_action ) : ?>
												<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $dak_action[1] ); ?>"><?php echo esc_html( $dak_action[0] ); ?></a>
											<?php endforeach; ?>
										</div>
										<?php if ( ! $dak_read_only ) : ?>
											<div class="dak-detail-actions is-danger-zone">
												<button
													type="button"
													class="dak-button dak-button-secondary dak-button-sm"
													data-admin-toggle-status
													data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
													data-is-disabled="<?php echo $row['is_disabled'] ? '1' : '0'; ?>"
												><?php echo esc_html( $dak_toggle_label ); ?></button>
												<button
													type="button"
													class="dak-button dak-button-secondary dak-button-sm is-danger"
													data-admin-delete-user
													data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
												><?php esc_html_e( 'Delete doctor', 'doctor-ak-portal' ); ?></button>
											</div>
										<?php endif; ?>
									</div>
								</template>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
