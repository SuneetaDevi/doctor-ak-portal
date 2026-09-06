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

$dak_blog_icons = array(
	'post'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2.5" width="14" height="15" rx="1.5"/><path d="M6.5 6.5h7M6.5 10h7M6.5 13.5h4"/></svg>',
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Blogs', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Articles shown on the public Blog page.', 'doctor-ak-portal' ); ?></p>
	</div>
	<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'form', $section_url ) ); ?>"><?php esc_html_e( '+ Add Post', 'doctor-ak-portal' ); ?></a>
</div>

<section class="dak-dashboard-card" id="dak-blogs-list">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'All posts', 'doctor-ak-portal' ); ?></h2>
		<?php if ( ! empty( $blogs ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-blogs-list" placeholder="<?php esc_attr_e( 'Search posts', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search posts', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $blogs ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No blog posts yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $blogs as $blog ) : ?>
			<div id="dak-blog-<?php echo esc_attr( $blog['id'] ); ?>" class="dak-admin-record-row" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $blog['title'] . ' ' . $blog['author']['name'] ) ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
						<?php if ( $blog['image_url'] ) : ?>
							<img src="<?php echo esc_url( $blog['image_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo $dak_blog_icons['post']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $blog['title'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( $blog['author']['name'] ); ?></span>
					</span>

					<span class="dak-admin-record-row-meta">
						<?php
						echo $blog['published_at']
							? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $blog['published_at'] ) ) )
							: esc_html( date_i18n( get_option( 'date_format' ), strtotime( $blog['created_at'] ) ) );
						?>
					</span>

					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline <?php echo 'published' === $blog['status'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-disabled'; ?>">
							<?php echo esc_html( $blog['status_label'] ); ?>
						</span>
					</span>

					<span class="dak-admin-record-row-actions">
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'blog_id' => $blog['id'] ), $section_url ) ); ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_blog_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-admin-blog-delete
							data-blog-id="<?php echo esc_attr( $blog['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_blog_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No posts match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
