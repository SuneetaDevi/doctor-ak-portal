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

$dak_view_icon = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10s2.7-5.5 8-5.5S18 10 18 10s-2.7 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.2"/></svg>';

if ( ! function_exists( 'dak_doctor_request_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for an avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_doctor_request_initials( $name ) {
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
	<section class="dak-dashboard-card" id="dak-doctor-requests-list">
		<div class="dak-dashboard-card-header">
			<h2><?php esc_html_e( 'Pending requests', 'doctor-ak-portal' ); ?></h2>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-doctor-requests-list" placeholder="<?php esc_attr_e( 'Search requests', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search requests', 'doctor-ak-portal' ); ?>">
			</div>
		</div>

		<?php foreach ( $pending_doctors as $row ) : ?>
			<div id="dak-doctor-request-<?php echo esc_attr( $row['id'] ); ?>" class="dak-admin-record-row" data-doctor-request-row="<?php echo esc_attr( $row['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $row['name'] . ' ' . $row['email'] ) ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
						<?php if ( $row['avatar_url'] ) : ?>
							<img src="<?php echo esc_url( $row['avatar_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo esc_html( dak_doctor_request_initials( $row['name'] ) ); ?>
						<?php endif; ?>
					</span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( sprintf( 'Dr. %s', $row['name'] ) ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( $row['email'] ); ?></span>
					</span>

					<span class="dak-admin-record-row-meta">
						<?php echo esc_html( '' !== $row['qualification'] ? $row['qualification'] : '—' ); ?>
						<?php if ( '' !== $row['years_experience'] ) : ?>
							&middot;
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: years of experience. */
									_n( '%s year', '%s years', (int) $row['years_experience'], 'doctor-ak-portal' ),
									$row['years_experience']
								)
							);
							?>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-pending"><?php esc_html_e( 'Pending', 'doctor-ak-portal' ); ?></span>
						<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( /* translators: %s: registration date. */ __( 'Registered %s', 'doctor-ak-portal' ), $row['registered_date'] ) ); ?></span>
					</span>

					<span class="dak-admin-record-row-actions">
						<button
							type="button"
							class="dak-icon-button"
							data-doctor-view-open
							data-user-id="<?php echo esc_attr( $row['id'] ); ?>"
							title="<?php esc_attr_e( 'View Profile', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'View Profile', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_view_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<button type="button" class="dak-button dak-button-primary dak-button-sm" data-doctor-request-approve data-user-id="<?php echo esc_attr( $row['id'] ); ?>">
							<?php esc_html_e( 'Approve', 'doctor-ak-portal' ); ?>
						</button>
						<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-doctor-request-reject data-user-id="<?php echo esc_attr( $row['id'] ); ?>">
							<?php esc_html_e( 'Reject', 'doctor-ak-portal' ); ?>
						</button>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No requests match your search.', 'doctor-ak-portal' ); ?></p>
	</section>

	<?php foreach ( $pending_doctors as $row ) : ?>
		<template data-doctor-profile-template data-user-id="<?php echo esc_attr( $row['id'] ); ?>">
			<div class="dak-doctor-profile-header">
				<span class="dak-avatar dak-avatar-lg">
					<?php if ( $row['avatar_url'] ) : ?>
						<img src="<?php echo esc_url( $row['avatar_url'] ); ?>" alt="">
					<?php else : ?>
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>
					<?php endif; ?>
				</span>
				<span class="dak-doctor-profile-header-info">
					<strong><?php echo esc_html( sprintf( 'Dr. %s', $row['name'] ) ); ?></strong>
					<span><?php echo esc_html( '' !== $row['qualification'] ? $row['qualification'] : '—' ); ?></span>
				</span>
			</div>

			<?php if ( ! empty( $row['specialization_labels'] ) ) : ?>
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></span>
					<div class="dak-specialty-tags">
						<?php foreach ( $row['specialization_labels'] as $dak_specialization ) : ?>
							<span class="dak-specialty-tag"><?php echo esc_html( $dak_specialization ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="dak-doctor-profile-grid">
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'Experience', 'doctor-ak-portal' ); ?></span>
					<p>
						<?php
						echo '' !== $row['years_experience']
							? esc_html( sprintf( _n( '%s year', '%s years', (int) $row['years_experience'], 'doctor-ak-portal' ), $row['years_experience'] ) )
							: '—';
						?>
					</p>
				</div>
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></span>
					<?php $dak_profile_location = implode( ', ', array_filter( array( $row['area'], $row['city'], $row['country'] ) ) ); ?>
					<p><?php echo esc_html( '' !== $dak_profile_location ? $dak_profile_location : '—' ); ?></p>
				</div>
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'Email Address', 'doctor-ak-portal' ); ?></span>
					<p><?php echo esc_html( $row['email'] ); ?></p>
				</div>
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'Phone', 'doctor-ak-portal' ); ?></span>
					<p><?php echo esc_html( '' !== $row['phone'] ? $row['phone'] : '—' ); ?></p>
				</div>
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'Registered', 'doctor-ak-portal' ); ?></span>
					<p><?php echo esc_html( $row['registered_date'] ); ?></p>
				</div>
			</div>

			<?php if ( '' !== $row['short_description'] ) : ?>
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'About', 'doctor-ak-portal' ); ?></span>
					<p><?php echo esc_html( $row['short_description'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $row['expertise'] ) : ?>
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'Other Expertise', 'doctor-ak-portal' ); ?></span>
					<p><?php echo esc_html( $row['expertise'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $row['awards'] ) ) : ?>
				<div class="dak-doctor-profile-section">
					<span class="dak-doctor-profile-label"><?php esc_html_e( 'Awards & Recognition', 'doctor-ak-portal' ); ?></span>
					<ul class="dak-doctor-profile-awards">
						<?php foreach ( $row['awards'] as $dak_award ) : ?>
							<li>
								<?php echo esc_html( isset( $dak_award['title'] ) ? $dak_award['title'] : '' ); ?>
								<?php if ( ! empty( $dak_award['year'] ) ) : ?>
									<span><?php echo esc_html( $dak_award['year'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</template>
	<?php endforeach; ?>

	<div class="dak-portal dak-modal" id="dak-doctor-view-modal" aria-hidden="true">
		<div class="dak-modal-overlay" data-doctor-view-close></div>

		<div class="dak-modal-dialog dak-doctor-profile-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-doctor-view-modal-title">
			<button type="button" class="dak-modal-close" data-doctor-view-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

			<div class="dak-modal-header">
				<h2 id="dak-doctor-view-modal-title"><?php esc_html_e( 'Doctor Profile', 'doctor-ak-portal' ); ?></h2>
			</div>

			<div id="dak-doctor-view-modal-body"></div>
		</div>
	</div>
<?php endif; ?>
