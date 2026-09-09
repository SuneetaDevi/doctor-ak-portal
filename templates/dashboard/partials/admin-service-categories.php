<?php
/**
 * Template: Services section's "Categories" tab — configure the list of
 * Service_Categories buckets services can be tagged with, shown as columns
 * in the site header's Services mega-menu (see Service_Categories,
 * Site_Header::service_categories_for_menu()).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $category_rows     Rows from Service_Categories::get_rows() — { slug, label, protected }.
 * @var array  $category_counts   Services::count_by_category() — category slug => number of services currently tagged with it.
 * @var string $services_list_url URL of this section's "All Services" tab.
 * @var string $categories_url    This tab's own URL (?section=services&view=categories).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_category_icons = array(
	'tag'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l6.5 6.5-7.5 7.5-6.5-6.5V3.5z"/><circle cx="6.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( "The categories services can be tagged with — shown as columns in the site header's Services menu.", 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<div class="dak-tabs">
	<a class="dak-tab" href="<?php echo esc_url( $services_list_url ); ?>"><?php esc_html_e( 'All Services', 'doctor-ak-portal' ); ?></a>
	<a class="dak-tab is-active" href="<?php echo esc_url( $categories_url ); ?>"><?php esc_html_e( 'Categories', 'doctor-ak-portal' ); ?></a>
</div>

<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-service-categories-general-error" role="alert"></div>

<section class="dak-dashboard-card" id="dak-service-categories-list">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Categories', 'doctor-ak-portal' ); ?></h2>
	</div>

	<div class="dak-service-category-add-row">
		<div class="dak-field">
			<label for="dak-admin-service-category-new-label" class="dak-visually-hidden"><?php esc_html_e( 'New category name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-service-category-new-label" placeholder="<?php esc_attr_e( 'e.g. Physiotherapy', 'doctor-ak-portal' ); ?>">
			<span class="dak-field-error" data-field="label"></span>
		</div>
		<button type="button" class="dak-button dak-button-primary" id="dak-admin-service-category-add"><?php esc_html_e( '+ Add Category', 'doctor-ak-portal' ); ?></button>
	</div>

	<div id="dak-admin-service-category-rows">
		<?php foreach ( $category_rows as $dak_category_row ) : ?>
			<?php $dak_count = isset( $category_counts[ $dak_category_row['slug'] ] ) ? (int) $category_counts[ $dak_category_row['slug'] ] : 0; ?>
			<div id="dak-service-category-<?php echo esc_attr( $dak_category_row['slug'] ); ?>" class="dak-admin-record-row" data-service-category-row="<?php echo esc_attr( $dak_category_row['slug'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo $dak_category_icons['tag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $dak_category_row['label'] ); ?></strong>
					</span>

					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of services in this category. */
									_n( '%d service', '%d services', $dak_count, 'doctor-ak-portal' ),
									$dak_count
								)
							);
							?>
						</span>
						<?php if ( ! empty( $dak_category_row['protected'] ) ) : ?>
							<span class="dak-status-pill dak-status-pill-outline"><?php esc_html_e( 'Default', 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-actions">
						<?php if ( empty( $dak_category_row['protected'] ) ) : ?>
							<button
								type="button"
								class="dak-icon-button dak-icon-button-danger"
								data-admin-service-category-delete
								data-slug="<?php echo esc_attr( $dak_category_row['slug'] ); ?>"
								title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
								aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							><?php echo $dak_category_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<?php else : ?>
							<span class="dak-field-hint"><?php esc_html_e( "Can't be deleted", 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
