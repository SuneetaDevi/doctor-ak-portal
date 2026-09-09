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

$dak_request_icons = array(
	'inbox'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 11 5 4.5h10L17.5 11"/><path d="M2.5 11v4a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-4h-4.3a2.2 2.2 0 0 1-4.4 0H2.5z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);

$dak_request_statuses = \DoctorAKPortal\Includes\Service_Requests::statuses();
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Service Requests', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Patients who requested a "without doctor" service (Labs, Pharmacy, etc.) — follow up by phone to arrange it.', 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<section class="dak-dashboard-card" id="dak-service-requests-list">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Requests', 'doctor-ak-portal' ); ?></h2>
		<?php if ( ! empty( $requests ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-service-requests-list" placeholder="<?php esc_attr_e( 'Search requests', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search requests', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $requests ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No service requests yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $requests as $dak_request ) : ?>
			<div id="dak-service-request-<?php echo esc_attr( $dak_request['id'] ); ?>" class="dak-admin-record-row" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $dak_request['service_name'] . ' ' . $dak_request['patient_name'] . ' ' . $dak_request['patient_phone'] ) ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo $dak_request_icons['inbox']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $dak_request['service_name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( '%1$s &middot; %2$s', $dak_request['patient_name'], $dak_request['patient_phone'] ) ); ?></span>
					</span>

					<span class="dak-admin-record-row-meta">
						<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dak_request['created_at'] ) ); ?>
					</span>

					<span class="dak-admin-record-row-actions">
						<select class="dak-service-request-status" data-service-request-status data-id="<?php echo esc_attr( $dak_request['id'] ); ?>">
							<?php foreach ( $dak_request_statuses as $dak_status_slug => $dak_status_label ) : ?>
								<option value="<?php echo esc_attr( $dak_status_slug ); ?>" <?php selected( $dak_request['status'], $dak_status_slug ); ?>><?php echo esc_html( $dak_status_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-service-request-delete
							data-id="<?php echo esc_attr( $dak_request['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_request_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>

				<?php if ( '' !== $dak_request['patient_email'] || '' !== $dak_request['notes'] ) : ?>
					<div class="dak-admin-record-row-secondary">
						<?php if ( '' !== $dak_request['patient_email'] ) : ?>
							<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Email:', 'doctor-ak-portal' ); ?></span>
							<span><?php echo esc_html( $dak_request['patient_email'] ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $dak_request['notes'] ) : ?>
							<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Notes:', 'doctor-ak-portal' ); ?></span>
							<span><?php echo esc_html( $dak_request['notes'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No requests match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
