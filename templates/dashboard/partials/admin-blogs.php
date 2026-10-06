<?php
/**
 * Template: "Blogs" admin table — every blog post regardless of status.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $blogs       Rows from Blogs::all_flat_for_admin(), each with an added 'author' sub-array.
 * @var string $section_url This section's own URL (?section=blogs), for the Add/Edit form's `?view=form` links.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Blogs', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Articles shown on the public Blog page.', 'doctor-ak-portal' ); ?></p>
	</div>
	<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'form', $section_url ) ); ?>"><?php esc_html_e( '+ Add Post', 'doctor-ak-portal' ); ?></a>
</div>

<section class="dak-results" id="dak-blogs-list" aria-labelledby="dak-blogs-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-blogs-title"><?php esc_html_e( 'Posts', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $blogs ) ) ); ?></span></h2>
		<?php if ( ! empty( $blogs ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box"><span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-blogs-list" placeholder="<?php esc_attr_e( 'Search title or author', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search posts', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $blogs ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No blog posts yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-blogs-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Post', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $blogs as $blog ) : ?>
						<tr id="dak-blog-<?php echo esc_attr( $blog['id'] ); ?>" data-row data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $blog['title'] . ' ' . $blog['author']['name'] ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Post', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $blog['title'] ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( sprintf( /* translators: %s: author name. */ __( 'By %s', 'doctor-ak-portal' ), $blog['author']['name'] ) ); ?></span>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Date', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $blog['published_at'] ? $blog['published_at'] : $blog['created_at'] ) ); ?></span>
									<span class="dak-cell-sub"><?php echo $blog['published_at'] ? esc_html__( 'Published', 'doctor-ak-portal' ) : esc_html__( 'Created', 'doctor-ak-portal' ); ?></span>
								</span>
							</td>
							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-status-pill <?php echo 'published' === $blog['status'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral'; ?>"><?php echo esc_html( $blog['status_label'] ); ?></span>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<a class="dak-text-action" href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'blog_id' => $blog['id'] ), $section_url ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post title. */ __( 'Edit %s', 'doctor-ak-portal' ), $blog['title'] ) ); ?>"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></a>
									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post title. */ __( 'More actions for %s', 'doctor-ak-portal' ), $blog['title'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-admin-blog-delete data-blog-id="<?php echo esc_attr( $blog['id'] ); ?>"><?php esc_html_e( 'Delete post', 'doctor-ak-portal' ); ?></button>
										</div>
									</details>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="dak-empty-state dak-results-empty dak-hidden" data-list-search-empty><?php esc_html_e( 'No posts match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
</div>