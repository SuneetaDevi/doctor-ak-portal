<?php
/**
 * Template: Reusable "country dial code + phone number" field pair — a
 * `<select>` of dial codes next to a plain number `<input>`, POSTed as two
 * separate fields and combined server-side via Phone::sanitize_from_request().
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $id_prefix Base id, e.g. 'dak-profile-phone' — the select becomes "{id_prefix}-code", the input "{id_prefix}-number".
 * @var string $dial_code Currently selected dial code, digits only (no '+'), e.g. '92'.
 * @var string $number    Currently entered national number, digits only (no leading 0 or dial code).
 * @var bool   $required  Whether the number input is marked required.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_phone_required = ! empty( $required );
?>
<div class="dak-phone-field">
	<select id="<?php echo esc_attr( $id_prefix ); ?>-code" name="phone_country_code" class="dak-phone-field-code">
		<?php foreach ( \DoctorAKPortal\Includes\Phone::dial_codes() as $dak_phone_code => $dak_phone_label ) : ?>
			<option value="<?php echo esc_attr( $dak_phone_code ); ?>" <?php selected( $dial_code, $dak_phone_code ); ?>><?php echo esc_html( $dak_phone_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<input
		type="tel"
		id="<?php echo esc_attr( $id_prefix ); ?>-number"
		name="phone_number"
		class="dak-phone-field-number"
		value="<?php echo esc_attr( $number ); ?>"
		placeholder="3001234567"
		<?php echo $dak_phone_required ? 'required' : ''; ?>
	>
</div>
