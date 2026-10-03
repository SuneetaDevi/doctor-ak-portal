<?php
/**
 * Template: compact signed-in identity at the top of every dashboard
 * sidebar (Admin/Receptionist, Doctor, Patient) — one small avatar, the
 * account's name and the portal it's in. Replaces each role's own larger
 * profile card so all three shells read as one product; fuller profile
 * details (specialties, rating, membership date) live on the Profile page.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $name       Display name to show (already prefixed, e.g. "Dr. Jane Doe", by the caller where appropriate).
 * @var string $role_label Portal/role line, e.g. "Receptionist portal".
 * @var string $avatar_url Profile picture URL, or '' for the initials fallback.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$avatar_url = isset( $avatar_url ) ? (string) $avatar_url : '';
$name       = isset( $name ) ? (string) $name : '';
$role_label = isset( $role_label ) ? (string) $role_label : '';

$dak_identity_initials = '';

foreach ( array_slice( preg_split( '/\s+/', trim( preg_replace( '/^Dr\.?\s+/i', '', $name ) ) ), 0, 2 ) as $dak_identity_word ) {
	if ( '' !== $dak_identity_word ) {
		$dak_identity_initials .= mb_strtoupper( mb_substr( $dak_identity_word, 0, 1 ) );
	}
}
?>
<div class="dak-sidebar-identity">
	<span class="dak-sidebar-identity-avatar" aria-hidden="true">
		<?php if ( '' !== $avatar_url ) : ?>
			<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
		<?php else : ?>
			<?php echo esc_html( '' !== $dak_identity_initials ? $dak_identity_initials : '?' ); ?>
		<?php endif; ?>
	</span>
	<span class="dak-sidebar-identity-text">
		<strong title="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name ); ?></strong>
		<span><?php echo esc_html( $role_label ); ?></span>
	</span>
</div>
