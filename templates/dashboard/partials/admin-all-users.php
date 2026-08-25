<?php
/**
 * Template: "All Users" admin section — every account across every role
 * (Administrator/Doctor/Patient/Receptionist) in one directory, with a
 * search bar and a role filter, so an admin can see everyone's name, phone,
 * ID, email, and role(s) at once instead of checking each role's own tab.
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

if ( ! function_exists( 'dak_admin_all_users_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for an avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_admin_all_users_initials( $name ) {
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

$dak_has_filters = '' !== $filters['role'] || '' !== $filters['search'];
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
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

<section class="dak-dashboard-card dak-appt-filters-card">
	<form
		method="get"
		action="<?php echo esc_url( $section_url ); ?>"
		class="dak-appt-filters-form"
		data-live-filter="doctor_ak_admin_users_filter"
		data-live-filter-target="#dak-admin-section-content"
		data-live-filter-nonce="dakAdminUsers"
	>
		<input type="hidden" name="section" value="all-users">
		<div class="dak-field">
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

		<div class="dak-admin-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a
					class="dak-button dak-button-secondary"
					href="<?php echo esc_url( $section_url ); ?>"
					data-live-filter-clear
					data-live-filter="doctor_ak_admin_users_filter"
					data-live-filter-target="#dak-admin-section-content"
					data-live-filter-nonce="dakAdminUsers"
				><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'User directory', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $users ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No users match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $users as $row ) : ?>
			<?php
			$dak_manage_url = ( '' !== $row['manageable_role'] && ! empty( $manageable_section_urls[ $row['manageable_role'] ] ) )
				? $manageable_section_urls[ $row['manageable_role'] ]
				: '';
			?>
			<div id="dak-all-user-<?php echo esc_attr( $row['id'] ); ?>" class="dak-admin-record-row" data-user-row="<?php echo esc_attr( $row['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo esc_html( dak_admin_all_users_initials( $row['name'] ) ); ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $row['name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( 'U-%03d', $row['id'] ) ); ?></span>
					</span>

					<span class="dak-admin-patient-row-email"><?php echo esc_html( $row['email'] ); ?></span>
					<span class="dak-admin-patient-row-phone"><?php echo esc_html( '' !== $row['phone'] ? $row['phone'] : '—' ); ?></span>

					<span class="dak-admin-record-row-tags">
						<?php foreach ( $row['roles'] as $dak_role_label ) : ?>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php echo esc_html( $dak_role_label ); ?></span>
						<?php endforeach; ?>
						<?php if ( '' !== $row['manageable_role'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline <?php echo $row['is_disabled'] ? 'dak-status-pill-is-disabled' : 'dak-status-pill-is-active'; ?>">
								<?php echo $row['is_disabled'] ? esc_html__( 'Deactivated', 'doctor-ak-portal' ) : esc_html__( 'Active', 'doctor-ak-portal' ); ?>
							</span>
						<?php endif; ?>
					</span>

					<?php if ( $dak_manage_url ) : ?>
						<span class="dak-admin-record-row-actions">
							<a
								class="dak-icon-button"
								href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'user_id' => $row['id'] ), $dak_manage_url ) ); ?>"
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
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
