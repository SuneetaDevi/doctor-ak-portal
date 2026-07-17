<?php
/**
 * Template: Edit Profile body for the [doctor_profile] shortcode.
 *
 * The actual form lives in templates/profile/profile-form.php, shared with
 * the doctor dashboard's in-page "Profile"/"Availability" tabs; this file
 * only supplies the standalone page's card chrome and header.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $profile_form_html Pre-rendered output of profile/profile-form.php.
 * @var string $dashboard_url     URL back to the user's dashboard.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-profile-page" style="width:100%">
	<div class="dak-auth-card dak-profile-card">
		<div class="dak-auth-header dak-profile-header">
			<h1><?php esc_html_e( 'Edit Profile', 'doctor-ak-portal' ); ?></h1>
			<?php if ( $dashboard_url ) : ?>
				<a class="dak-link" href="<?php echo esc_url( $dashboard_url ); ?>">&larr; <?php esc_html_e( 'Back to Dashboard', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>

		<?php echo $profile_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own profile-form.php template, which escapes its own output. ?>
	</div>
</div>
