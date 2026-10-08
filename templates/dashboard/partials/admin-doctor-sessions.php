<?php
/**
 * Template: "Doctor Sessions" admin table — every doctor's every clinic
 * (physical location or video-consultation entry), its weekly enabled days
 * and appointment slot duration, flattened into one table.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $clinics         Rows from Clinics::all_flat_for_admin(), each with an added 'doctor' sub-array.
 * @var string $section_url     This section's own URL (?section=doctor-sessions or ?section=clinic), for building the Add/Edit links.
 * @var string $filtered_doctor Name of the doctor being filtered to (via the Doctors directory's "View Sessions" action), or '' if unfiltered.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$dak_session_day_labels    = \DoctorAKPortal\Includes\Clinics::session_days();
$dak_session_period_labels = \DoctorAKPortal\Includes\Clinics::session_periods();
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Doctor Sessions', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( "Every doctor's clinics and their weekly session hours, in one place.", 'doctor-ak-portal' ); ?></p>
	</div>
	<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'form', $section_url ) ); ?>"><?php esc_html_e( '+ Add Session', 'doctor-ak-portal' ); ?></a>
</div>

<?php if ( '' !== $filtered_doctor ) : ?>
	<div class="dak-alert dak-alert-success">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: doctor's name. */
				__( 'Showing sessions for Dr. %s.', 'doctor-ak-portal' ),
				$filtered_doctor
			)
		);
		?>
		<a class="dak-link" href="<?php echo esc_url( $section_url ); ?>"><?php esc_html_e( 'Clear filter', 'doctor-ak-portal' ); ?></a>
	</div>
<?php endif; ?>

<section class="dak-results" id="dak-doctor-sessions-list" aria-labelledby="dak-doctor-sessions-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-doctor-sessions-title"><?php esc_html_e( 'Sessions', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $clinics ) ) ); ?></span></h2>
		<?php if ( ! empty( $clinics ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-doctor-sessions-list" placeholder="<?php esc_attr_e( 'Search doctor, clinic or city', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search sessions', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $clinics ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors have added any clinics or sessions yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-sessions-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Clinic / visit mode', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Slot length', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Weekly schedule', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $clinics as $clinic ) : ?>
						<?php
						$dak_durations = array();
						$dak_hours     = array();

						foreach ( $clinic['sessions'] as $dak_day_slug => $dak_session_day ) {
							foreach ( $dak_session_day as $dak_period_slug => $dak_session_period ) {
								if ( empty( $dak_session_period['enabled'] ) ) {
									continue;
								}

								if ( (int) $dak_session_period['slot_duration_minutes'] > 0 ) {
									$dak_durations[ (int) $dak_session_period['slot_duration_minutes'] ] = true;
								}

								$dak_hours[ $dak_day_slug ][] = sprintf(
									'%1$s–%2$s',
									\DoctorAKPortal\Includes\Dashboard_Format::time( $dak_session_period['start'] ),
									\DoctorAKPortal\Includes\Dashboard_Format::time( $dak_session_period['end'] )
								);
							}
						}

						ksort( $dak_durations );
						$dak_duration_label = empty( $dak_durations )
							? '—'
							: implode( ', ', array_map( array( '\DoctorAKPortal\Includes\Dashboard_Format', 'duration' ), array_keys( $dak_durations ) ) );
						$dak_is_video   = 'video' === $clinic['type'];
						$dak_place      = $dak_is_video ? __( 'Video visit', 'doctor-ak-portal' ) : $clinic['name'];
						$dak_place_sub  = $dak_is_video ? __( 'Online consultation', 'doctor-ak-portal' ) : implode( ', ', array_filter( array( $clinic['area_label'], $clinic['city_label'] ) ) );
						$dak_hours_id   = 'dak-session-' . $clinic['id'] . '-hours';
						?>
						<tr id="dak-clinic-<?php echo esc_attr( $clinic['id'] ); ?>" data-row data-clinic-row="<?php echo esc_attr( $clinic['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $clinic['name'] . ' ' . $clinic['city_label'] . ' ' . $clinic['doctor']['name'] ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-primary"><?php echo esc_html( sprintf( 'Dr. %s', $clinic['doctor']['name'] ) ); ?></span>
							</td>
							<td data-label="<?php esc_attr_e( 'Clinic / visit mode', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( $dak_place ); ?></span>
									<?php if ( '' !== $dak_place_sub ) : ?>
										<span class="dak-cell-sub"><?php echo esc_html( $dak_place_sub ); ?></span>
									<?php endif; ?>
									<?php echo $dak_is_video ? '' : \DoctorAKPortal\Includes\Dashboard_Format::map_link_html( \DoctorAKPortal\Includes\Clinics::map_url( $clinic ), $clinic['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside map_link_html(). ?>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Slot length', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $dak_duration_label ); ?></td>
							<td data-label="<?php esc_attr_e( 'Weekly schedule', 'doctor-ak-portal' ); ?>">
								<?php if ( empty( $clinic['enabled_days'] ) ) : ?>
									<span class="dak-cell-stack">
										<span class="dak-status-pill dak-status-pill-is-neutral"><?php esc_html_e( 'Not available', 'doctor-ak-portal' ); ?></span>
										<span class="dak-cell-note"><?php esc_html_e( 'No days open', 'doctor-ak-portal' ); ?></span>
									</span>
								<?php else : ?>
									<span class="dak-cell-stack">
										<span class="dak-status-pill dak-status-pill-is-active"><?php esc_html_e( 'Available', 'doctor-ak-portal' ); ?></span>
										<span class="dak-cell-sub"><?php echo esc_html( implode( ', ', array_map( function ( $label ) { return mb_substr( $label, 0, 3 ); }, $clinic['enabled_days'] ) ) ); ?></span>
										<details class="dak-cell-details">
											<summary><?php esc_html_e( 'Hours', 'doctor-ak-portal' ); ?></summary>
											<dl class="dak-detail-list dak-detail-list-compact" id="<?php echo esc_attr( $dak_hours_id ); ?>">
												<?php foreach ( $dak_hours as $dak_day_slug => $dak_day_hours ) : ?>
													<div><dt><?php echo esc_html( isset( $dak_session_day_labels[ $dak_day_slug ] ) ? $dak_session_day_labels[ $dak_day_slug ] : $dak_day_slug ); ?></dt><dd class="is-tabular"><?php echo esc_html( implode( ', ', $dak_day_hours ) ); ?></dd></div>
												<?php endforeach; ?>
											</dl>
										</details>
									</span>
								<?php endif; ?>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<a class="dak-text-action" href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'clinic_id' => $clinic['id'] ), $section_url ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: doctor name, 2: clinic. */ __( 'Edit session for Dr. %1$s at %2$s', 'doctor-ak-portal' ), $clinic['doctor']['name'], $dak_place ) ); ?>"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></a>
									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: doctor name. */ __( 'More actions for Dr. %s', 'doctor-ak-portal' ), $clinic['doctor']['name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-admin-session-delete data-clinic-id="<?php echo esc_attr( $clinic['id'] ); ?>"><?php esc_html_e( 'Delete session', 'doctor-ak-portal' ); ?></button>
										</div>
									</details>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="dak-empty-state dak-results-empty dak-hidden" data-list-search-empty><?php esc_html_e( 'No sessions match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
</div>