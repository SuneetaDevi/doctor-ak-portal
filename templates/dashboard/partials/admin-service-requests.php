<?php
/**
 * Template: "Service Requests" admin table — patient submissions for a
 * Services row with requires_doctor = 0 (a Lab test, a Pharmacy order,
 * etc. — see Service_Requests, [service_profile_view]'s "Request This
 * Service" form). No date/time slot is attached to these; the clinic
 * follows up by phone to actually arrange it, tracked here via status.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $requests Rows from Service_Requests::all_flat_for_admin().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$dak_request_statuses = \DoctorAKPortal\Includes\Service_Requests::statuses();
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Service Requests', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Patients who requested a "without doctor" service (Labs, Pharmacy, etc.) — follow up by phone to arrange it.', 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<section class="dak-results" id="dak-service-requests-list" aria-labelledby="dak-service-requests-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-service-requests-title"><?php esc_html_e( 'Requests', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $requests ) ) ); ?></span></h2>
		<?php if ( ! empty( $requests ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box"><span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-service-requests-list" placeholder="<?php esc_attr_e( 'Search service, patient or phone', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search requests', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $requests ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No service requests yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-service-requests-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Requested', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $requests as $dak_request ) : ?>
						<?php $dak_requested_ts = strtotime( (string) $dak_request['created_at'] ); ?>
						<tr id="dak-service-request-<?php echo esc_attr( $dak_request['id'] ); ?>" data-row data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $dak_request['service_name'] . ' ' . $dak_request['patient_name'] . ' ' . $dak_request['patient_phone'] ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Service', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $dak_request['service_name'] ); ?></span>
									<?php if ( '' !== $dak_request['notes'] ) : ?>
										<details class="dak-cell-details">
											<summary><?php esc_html_e( 'Patient notes', 'doctor-ak-portal' ); ?></summary>
											<p class="dak-cell-details-text"><?php echo esc_html( $dak_request['notes'] ); ?></p>
										</details>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Patient', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( $dak_request['patient_name'] ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( '' !== (string) $dak_request['patient_phone'] ? $dak_request['patient_phone'] : __( 'No phone', 'doctor-ak-portal' ) ); ?></span>
									<?php if ( '' !== $dak_request['patient_email'] ) : ?>
										<span class="dak-cell-sub dak-cell-email"><?php echo \DoctorAKPortal\Includes\Dashboard_Format::email_html( $dak_request['patient_email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside email_html(). ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Requested', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $dak_request['created_at'] ) ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( false !== $dak_requested_ts ? date_i18n( 'h:i A', $dak_requested_ts ) : '' ); ?></span>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<select class="dak-service-request-status" data-service-request-status data-id="<?php echo esc_attr( $dak_request['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'Request status for %s', 'doctor-ak-portal' ), $dak_request['patient_name'] ) ); ?>">
									<?php foreach ( $dak_request_statuses as $dak_status_slug => $dak_status_label ) : ?>
										<option value="<?php echo esc_attr( $dak_status_slug ); ?>" <?php selected( $dak_request['status'], $dak_status_slug ); ?>><?php echo esc_html( $dak_status_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $dak_request['patient_name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-service-request-delete data-id="<?php echo esc_attr( $dak_request['id'] ); ?>"><?php esc_html_e( 'Delete request', 'doctor-ak-portal' ); ?></button>
										</div>
									</details>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="dak-empty-state dak-results-empty dak-hidden" data-list-search-empty><?php esc_html_e( 'No requests match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
</div>