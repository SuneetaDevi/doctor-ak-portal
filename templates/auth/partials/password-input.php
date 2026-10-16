<?php
/**
 * Template partial: a password input with a keyboard-accessible Show/Hide
 * toggle (doctor-ak-password-toggle.js). Paste and password managers work
 * normally — nothing here blocks them.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $id           Input id.
 * @var string $name         Input name.
 * @var string $autocomplete 'current-password' or 'new-password'.
 * @var string $describedby  Optional space-separated ids for aria-describedby.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$describedby = isset( $describedby ) ? trim( (string) $describedby ) : '';
?>
<div class="dak-password-field">
	<input
		type="password"
		id="<?php echo esc_attr( $id ); ?>"
		name="<?php echo esc_attr( $name ); ?>"
		autocomplete="<?php echo esc_attr( $autocomplete ); ?>"
		required
		aria-required="true"
		<?php echo '' !== $describedby ? 'aria-describedby="' . esc_attr( $describedby ) . '"' : ''; ?>
	>
	<button type="button" class="dak-password-toggle" aria-controls="<?php echo esc_attr( $id ); ?>" aria-pressed="false">
		<svg class="dak-icon-eye" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6-8.5-6-8.5-6z"/><circle cx="10" cy="10" r="2.5"/></svg>
		<svg class="dak-icon-eye-off" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 2.5l15 15"/><path d="M8.3 4.3C8.85 4.1 9.42 4 10 4c5.5 0 8.5 6 8.5 6a14.9 14.9 0 0 1-3 3.9M5.5 5.6C3 7.2 1.5 10 1.5 10s3 6 8.5 6c1.1 0 2.1-.24 3-.63"/><path d="M7.8 7.8a2.5 2.5 0 0 0 3.4 3.5"/></svg>
		<span class="dak-password-toggle-text"><?php esc_html_e( 'Show', 'doctor-ak-portal' ); ?></span>
	</button>
</div>
