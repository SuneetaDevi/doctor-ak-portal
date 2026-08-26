<?php
/**
 * Template: "Service Portfolio" admin section — the public-facing service
 * catalog shown on the [services_directory] page (name, description, image,
 * price, which clinics offer it, which doctors provide it). Distinct from
 * the "Services" section (Services class), which manages each doctor's own
 * bookable line-items used at checkout.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $services Rows from Service_Catalog::all_flat_for_admin().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_portfolio_icons = array(
	'image'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><circle cx="7" cy="8" r="1.5"/><path d="M17.5 13.5l-4-4-3 3-2.5-2.5-5 5"/></svg>',
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Service Portfolio', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Public services shown on the website — name, description, image, price, clinics, and doctors.', 'doctor-ak-portal' ); ?></p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-service-portfolio-add"><?php esc_html_e( '+ Add Service', 'doctor-ak-portal' ); ?></button>
</div>

<section class="dak-dashboard-card" id="dak-service-portfolio-list">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h2>
		<?php if ( ! empty( $services ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-service-portfolio-list" placeholder="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search services', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $services ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No services have been added to the portfolio yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $services as $service ) : ?>
			<?php
			$dak_clinic_labels = wp_list_pluck( $service['clinic_locations'], 'name' );
			$dak_doctor_labels = wp_list_pluck( $service['doctors'], 'name' );
			?>
			<div id="dak-service-portfolio-<?php echo esc_attr( $service['id'] ); ?>" class="dak-admin-record-row" data-service-portfolio-row="<?php echo esc_attr( $service['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $service['name'] . ' ' . implode( ' ', $dak_clinic_labels ) . ' ' . implode( ' ', $dak_doctor_labels ) ) ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
						<?php if ( $service['image_url'] ) : ?>
							<img src="<?php echo esc_url( $service['image_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo $dak_portfolio_icons['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $service['name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( $service['price_label'] ); ?></span>
					</span>

					<span class="dak-admin-record-row-tags">
						<?php if ( empty( $dak_clinic_labels ) ) : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'No clinics assigned', 'doctor-ak-portal' ); ?></span>
						<?php else : ?>
							<?php foreach ( array_slice( $dak_clinic_labels, 0, 2 ) as $dak_clinic_label ) : ?>
								<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $dak_clinic_label ); ?></span>
							<?php endforeach; ?>
							<?php if ( count( $dak_clinic_labels ) > 2 ) : ?>
								<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( sprintf( '+%d', count( $dak_clinic_labels ) - 2 ) ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
						<span class="dak-status-pill dak-status-pill-outline <?php echo $service['active'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-disabled'; ?>">
							<?php echo $service['active'] ? esc_html__( 'Active', 'doctor-ak-portal' ) : esc_html__( 'Inactive', 'doctor-ak-portal' ); ?>
						</span>
					</span>

					<span class="dak-admin-record-row-actions">
						<button
							type="button"
							class="dak-icon-button"
							data-admin-service-portfolio-edit
							data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
							data-name="<?php echo esc_attr( $service['name'] ); ?>"
							data-description="<?php echo esc_attr( $service['description'] ); ?>"
							data-price="<?php echo esc_attr( $service['price'] ); ?>"
							data-active="<?php echo $service['active'] ? '1' : '0'; ?>"
							data-image-id="<?php echo esc_attr( $service['image_id'] ); ?>"
							data-image-url="<?php echo esc_attr( $service['image_url'] ); ?>"
							data-clinic-location-ids="<?php echo esc_attr( wp_json_encode( $service['clinic_location_ids'] ) ); ?>"
							data-doctor-ids="<?php echo esc_attr( wp_json_encode( $service['doctor_ids'] ) ); ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_portfolio_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-admin-service-portfolio-delete
							data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_portfolio_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>

				<div class="dak-admin-record-row-secondary">
					<span class="dak-admin-record-row-secondary-label"><?php esc_html_e( 'Doctors:', 'doctor-ak-portal' ); ?></span>
					<span><?php echo empty( $dak_doctor_labels ) ? '&mdash;' : esc_html( implode( ', ', $dak_doctor_labels ) ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No services match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
