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
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( "The categories services can be tagged with — shown as columns in the site header's Services menu.", 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<div class="dak-tabs">
	<a class="dak-tab" href="<?php echo esc_url( $services_list_url ); ?>"><?php esc_html_e( 'All Services', 'doctor-ak-portal' ); ?></a>
	<a class="dak-tab is-active" href="<?php echo esc_url( $categories_url ); ?>" aria-current="page"><?php esc_html_e( 'Categories', 'doctor-ak-portal' ); ?></a>
</div>

<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-service-categories-general-error" role="alert"></div>

<div class="dak-list-toolbar">
	<div class="dak-list-filters dak-service-category-add-row">
		<div class="dak-field is-search">
			<label for="dak-admin-service-category-new-label"><?php esc_html_e( 'New category name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-service-category-new-label" placeholder="<?php esc_attr_e( 'e.g. Physiotherapy', 'doctor-ak-portal' ); ?>">
			<span class="dak-field-error" data-field="label"></span>
		</div>
		<div class="dak-list-filter-actions">
			<button type="button" class="dak-button dak-button-primary" id="dak-admin-service-category-add"><?php esc_html_e( '+ Add Category', 'doctor-ak-portal' ); ?></button>
		</div>
	</div>
</div>

<section class="dak-results" id="dak-service-categories-list" aria-labelledby="dak-service-categories-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-service-categories-title"><?php esc_html_e( 'Categories', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $category_rows ) ) ); ?></span></h2>
	</div>
	<?php if ( empty( $category_rows ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No categories yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-categories-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Category', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody id="dak-admin-service-category-rows">
					<?php foreach ( $category_rows as $dak_category_row ) : ?>
						<?php $dak_count = isset( $category_counts[ $dak_category_row['slug'] ] ) ? (int) $category_counts[ $dak_category_row['slug'] ] : 0; ?>
						<tr id="dak-service-category-<?php echo esc_attr( $dak_category_row['slug'] ); ?>" data-row data-service-category-row="<?php echo esc_attr( $dak_category_row['slug'] ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Category', 'doctor-ak-portal' ); ?>"><span class="dak-cell-primary"><?php echo esc_html( $dak_category_row['label'] ); ?></span></td>
							<td class="is-tabular" data-label="<?php esc_attr_e( 'Services', 'doctor-ak-portal' ); ?>"><?php echo esc_html( sprintf( /* translators: %d: number of services in this category. */ _n( '%d service', '%d services', $dak_count, 'doctor-ak-portal' ), $dak_count ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Type', 'doctor-ak-portal' ); ?>"><?php echo ! empty( $dak_category_row['protected'] ) ? esc_html__( 'Default', 'doctor-ak-portal' ) : esc_html__( 'Custom', 'doctor-ak-portal' ); ?></td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<?php if ( empty( $dak_category_row['protected'] ) ) : ?>
									<div class="dak-row-actions">
										<details class="dak-row-menu">
											<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: category name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $dak_category_row['label'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
											<div class="dak-row-menu-panel" role="menu">
												<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-admin-service-category-delete data-slug="<?php echo esc_attr( $dak_category_row['slug'] ); ?>"><?php esc_html_e( 'Delete category', 'doctor-ak-portal' ); ?></button>
											</div>
										</details>
									</div>
								<?php else : ?>
									<span class="dak-cell-sub"><?php esc_html_e( "Can't be deleted", 'doctor-ak-portal' ); ?></span>
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