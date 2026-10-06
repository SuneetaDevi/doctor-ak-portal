<?php
/**
 * Template: "All Users" admin section — every account across every role
 * (Administrator/Doctor/Patient/Receptionist) in one directory, with a
 * search bar and a role filter, so an admin can see everyone's name, phone,
 * ID, email, and role(s) at once instead of checking each role's own tab.
 * Shared list pattern (see section 20 of doctor-ak-dashboard-ui.css).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $users                   Row view-models { id, name, email, phone, roles, manageable_role, is_disabled }, see Admin_Dashboard::all_users_section_html().
 * @var string $section_url             Unfiltered URL of this section, for the filter form and "Clear" link.
 * @var array  $role_labels             Role slug => label, for the Role filter's options.
 * @var array  $manageable_section_urls Doctor/Patient/Receptionist role slug => that role's own admin section URL, for a row's Edit link. A row whose 'manageable_role' is '' (an Administrator account) gets no Edit/Deactivate/Delete actions — see all_users_section_html().
 * @var array  $filters                 Active filter values: role, search.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_has_filters = '' !== $filters['role'] || '' !== $filters['search'];
$dak_live_attrs  = ' data-live-filter="doctor_ak_admin_users_filter" data-live-filter-target="#dak-admin-section-content" data-live-filter-nonce="dakAdminUsers"';
?>
<div class="dak-list-page">
	<div class="dak-page-head">
		<div>
			<h1><?php esc_html_e( 'All Users', 'doctor-ak-portal' ); ?></h1>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of users. */
						_n( '%d account', '%d accounts', count( $users ), 'doctor-ak-portal' ),
						count( $users )
					)
				);
				?>
			</p>
		</div>
	</div>

	<div class="dak-list-toolbar">
		<form
			method="get"
			action="<?php echo esc_url( $section_url ); ?>"
			class="dak-list-filters"
			<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>
		>
			<input type="hidden" name="section" value="all-users">
			<div class="dak-field is-search">
				<label for="dak-all-users-filter-search"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></label>
				<input type="search" id="dak-all-users-filter-search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name, email, or phone…', 'doctor-ak-portal' ); ?>">
			</div>

			<div class="dak-field">
				<label for="dak-all-users-filter-role"><?php esc_html_e( 'Role', 'doctor-ak-portal' ); ?></label>
				<select id="dak-all-users-filter-role" name="role">
					<option value=""><?php esc_html_e( 'All roles', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $role_labels as $dak_role_slug => $dak_role_label ) : ?>
						<option value="<?php echo esc_attr( $dak_role_slug ); ?>" <?php selected( $filters['role'], $dak_role_slug ); ?>><?php echo esc_html( $dak_role_label ); ?></option>
					<?php endforeach; ?>
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

	<section class="dak-results" aria-labelledby="dak-all-users-results-title">
		<div class="dak-results-tools">
			<h2 class="dak-results-title" id="dak-all-users-results-title"><?php esc_html_e( 'Users', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $users ) ) ); ?></span></h2>
		</div>

		<?php if ( empty( $users ) ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No users match these filters.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<div class="dak-data-table-wrap">
				<table class="dak-data-table dak-ui-table dak-all-users-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'User', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Contact', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Role', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Account status', 'doctor-ak-portal' ); ?></th>
							<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $users as $row ) : ?>
							<?php
							$dak_manage_url = ( '' !== $row['manageable_role'] && ! empty( $manageable_section_urls[ $row['manageable_role'] ] ) )
								? $manageable_section_urls[ $row['manageable_role'] ]
								: '';
							?>
							<tr id="dak-all-user-<?php echo esc_attr( $row['id'] ); ?>" data-row data-user-row="<?php echo esc_attr( $row['id'] ); ?>">
								<td class="dak-col-primary" data-label="<?php esc_attr_e( 'User', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-cell-primary"><?php echo esc_html( $row['name'] ); ?></span>
										<span class="dak-cell-sub dak-cell-id"><?php echo esc_html( sprintf( 'U-%03d', $row['id'] ) ); ?></span>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Contact', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-cell-email"><?php echo \DoctorAKPortal\Includes\Dashboard_Format::email_html( $row['email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside email_html(). ?></span>
										<span class="dak-cell-sub is-tabular"><?php echo esc_html( '' !== (string) $row['phone'] ? $row['phone'] : __( 'No phone', 'doctor-ak-portal' ) ); ?></span>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Role', 'doctor-ak-portal' ); ?>"><?php echo esc_html( implode( ', ', $row['roles'] ) ); ?></td>
								<td class="dak-col-status" data-label="<?php esc_attr_e( 'Account status', 'doctor-ak-portal' ); ?>">
									<?php if ( '' !== $row['manageable_role'] ) : ?>
										<span class="dak-status-pill <?php echo $row['is_disabled'] ? 'dak-status-pill-is-neutral' : 'dak-status-pill-is-active'; ?>"><?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?></span>
									<?php else : ?>
										<span class="dak-cell-sub"><?php esc_html_e( 'Managed in WordPress', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
									<?php if ( $dak_manage_url ) : ?>
										<div class="dak-row-actions">
											<a class="dak-text-action" href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'user_id' => $row['id'] ), $dak_manage_url ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: person's name. */ __( 'Edit %s', 'doctor-ak-portal' ), $row['name'] ) ); ?>"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></a>
											<details class="dak-row-menu">
												<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: person's name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $row['name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
												<div class="dak-row-menu-panel" role="menu">
													<button type="button" class="dak-row-menu-item" role="menuitem" data-admin-toggle-status data-user-id="<?php echo esc_attr( $row['id'] ); ?>" data-is-disabled="<?php echo $row['is_disabled'] ? '1' : '0'; ?>"><?php echo $row['is_disabled'] ? esc_html__( 'Reactivate account', 'doctor-ak-portal' ) : esc_html__( 'Deactivate account', 'doctor-ak-portal' ); ?></button>
													<hr class="dak-row-menu-sep">
													<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-admin-delete-user data-user-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Delete account', 'doctor-ak-portal' ); ?></button>
												</div>
											</details>
										</div>
									<?php else : ?>
										<span class="dak-cell-sub"><?php esc_html_e( 'No actions', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
