<?php
/**
 * Template: Full-screen Add/Edit Blog Post form — replaces the Blogs table's
 * content area (see Admin_Dashboard::blog_form_screen_html()) when the URL
 * has `?view=form`. Mirrors the Add/Edit Service screen's own pattern.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array      $status_options Status slug => label, see Blogs::status_options().
 * @var string     $list_url       Back-to-list URL (the Blogs table).
 * @var array|null $editing_blog   Decoded blog row (see Blogs::find()) when editing, null when adding.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_is_editing = null !== $editing_blog;
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<a class="dak-back-link" href="<?php echo esc_url( $list_url ); ?>">
			&larr; <?php esc_html_e( 'Back to Blogs', 'doctor-ak-portal' ); ?>
		</a>
		<h1><?php echo esc_html( $dak_is_editing ? __( 'Edit Post', 'doctor-ak-portal' ) : __( 'Add Post', 'doctor-ak-portal' ) ); ?></h1>
	</div>
</div>

<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-blog-general-error" role="alert"></div>

<section class="dak-dashboard-card dak-admin-user-form-card">
	<form id="dak-admin-blog-form" novalidate data-list-url="<?php echo esc_url( $list_url ); ?>">
		<input type="hidden" name="blog_id" value="<?php echo esc_attr( $dak_is_editing ? $editing_blog['id'] : 0 ); ?>">
		<input type="hidden" id="dak-admin-blog-image-id" name="image_id" value="<?php echo esc_attr( $dak_is_editing ? $editing_blog['image_id'] : 0 ); ?>">

		<div class="dak-field">
			<label for="dak-admin-blog-title"><?php esc_html_e( 'Title', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-blog-title" name="title" placeholder="<?php esc_attr_e( 'e.g. 5 Tips for a Healthy Digestive System', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( $dak_is_editing ? $editing_blog['title'] : '' ); ?>">
			<span class="dak-field-error" data-field="title"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-blog-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-blog-status" name="status">
				<?php foreach ( $status_options as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $dak_is_editing ? $editing_blog['status'] === $slug : 'draft' === $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="dak-field-hint"><?php esc_html_e( 'Only Published posts show on the public Blog page.', 'doctor-ak-portal' ); ?></p>
		</div>

		<div class="dak-field">
			<label for="dak-admin-blog-content"><?php esc_html_e( 'Content', 'doctor-ak-portal' ); ?></label>
			<div class="dak-rich-text" data-rich-text>
				<?php echo \DoctorAKPortal\Includes\Rich_Text::toolbar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapes its own output. ?>
				<div
					id="dak-admin-blog-content"
					class="dak-rich-text-editor"
					contenteditable="true"
					role="textbox"
					aria-multiline="true"
					aria-label="<?php esc_attr_e( 'Content', 'doctor-ak-portal' ); ?>"
					data-placeholder="<?php esc_attr_e( 'Write the post…', 'doctor-ak-portal' ); ?>"
				><?php echo $dak_is_editing ? wp_kses_post( $editing_blog['content'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() output; pre-fills the editor with the existing (already-sanitized) formatted content. ?></div>
				<input type="hidden" name="content" value="<?php echo esc_attr( $dak_is_editing ? $editing_blog['content'] : '' ); ?>" data-rich-text-value>
			</div>
			<span class="dak-field-error" data-field="content"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-blog-image"><?php esc_html_e( 'Featured Image', 'doctor-ak-portal' ); ?></label>
			<div class="dak-service-portfolio-image-picker">
				<span class="dak-avatar dak-avatar-lg" id="dak-admin-blog-image-preview-wrap">
					<img id="dak-admin-blog-image-preview" src="<?php echo esc_url( $dak_is_editing ? $editing_blog['image_url'] : '' ); ?>" alt="" class="<?php echo ( $dak_is_editing && $editing_blog['image_url'] ) ? '' : 'dak-hidden'; ?>">
				</span>
				<input type="file" id="dak-admin-blog-image" name="image" accept="image/jpeg,image/png,image/webp">
			</div>
			<p class="dak-field-hint"><?php esc_html_e( 'Shown on the Blog listing and the post itself — optional.', 'doctor-ak-portal' ); ?></p>
			<span class="dak-field-error" data-field="image"></span>
		</div>

		<div class="dak-admin-user-form-actions">
			<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></a>
			<button type="submit" class="dak-button dak-button-primary" id="dak-admin-blog-submit">
				<span class="dak-button-label"><?php esc_html_e( 'Save Post', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	</form>
</section>
