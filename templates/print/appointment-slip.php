<?php
/**
 * Template: Standalone printable appointment slip, opened in a new tab by
 * the admin Appointments table's "Print" action.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $appointment  Appointment array from Appointments::find().
 * @var string $patient_name Resolved patient/guest display name.
 * @var string $doctor_name  Resolved doctor display name.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_slip_type = 'video' === $appointment['type'] ? __( 'Online Video Consultation', 'doctor-ak-portal' ) : __( 'Clinic Appointment', 'doctor-ak-portal' );
$dak_slip_paid = 'paid' === $appointment['payment_status'];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title><?php echo esc_html( sprintf( /* translators: %d: appointment ID. */ __( 'Appointment Slip #%d', 'doctor-ak-portal' ), $appointment['id'] ) ); ?></title>
	<style>
		body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; max-width: 480px; margin: 40px auto; padding: 0 20px; }
		h1 { font-size: 1.3rem; margin-bottom: 4px; }
		.dak-slip-meta { color: #666; font-size: 0.85rem; margin-bottom: 24px; }
		table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
		td { padding: 8px 0; border-bottom: 1px solid #eee; vertical-align: top; }
		td:first-child { color: #666; width: 40%; }
		td:last-child { font-weight: 600; text-align: right; }
		.dak-slip-status { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
		.dak-slip-status.is-paid { background: #e3f6e8; color: #157a3c; }
		.dak-slip-status.is-pending { background: #fff4e0; color: #b45f06; }
		.dak-slip-print { margin-top: 8px; padding: 10px 18px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; cursor: pointer; }
		@media print { .dak-slip-print { display: none; } }
	</style>
</head>
<body>
	<h1><?php esc_html_e( 'Appointment Slip', 'doctor-ak-portal' ); ?></h1>
	<p class="dak-slip-meta"><?php echo esc_html( sprintf( /* translators: %d: appointment ID. */ __( 'Reference #%d', 'doctor-ak-portal' ), $appointment['id'] ) ); ?></p>

	<table>
		<tr><td><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></td><td><?php echo esc_html( $patient_name ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></td><td><?php echo esc_html( sprintf( 'Dr. %s', $doctor_name ) ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Type', 'doctor-ak-portal' ); ?></td><td><?php echo esc_html( $dak_slip_type ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></td><td><?php echo esc_html( '' !== $appointment['service_name'] ? $appointment['service_name'] : '—' ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Date & Time', 'doctor-ak-portal' ); ?></td><td><?php echo esc_html( $appointment['date'] . ' ' . $appointment['time'] ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Charges', 'doctor-ak-portal' ); ?></td><td><?php echo $appointment['charge'] > 0 ? esc_html( 'PKR ' . number_format( (float) $appointment['charge'], 0 ) . '/-' ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Payment Mode', 'doctor-ak-portal' ); ?></td><td><?php echo esc_html( 'online' === $appointment['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : __( 'Manual', 'doctor-ak-portal' ) ); ?></td></tr>
		<tr>
			<td><?php esc_html_e( 'Payment Status', 'doctor-ak-portal' ); ?></td>
			<td><span class="dak-slip-status <?php echo $dak_slip_paid ? 'is-paid' : 'is-pending'; ?>"><?php echo $dak_slip_paid ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Pending', 'doctor-ak-portal' ); ?></span></td>
		</tr>
	</table>

	<button type="button" class="dak-slip-print" onclick="window.print();"><?php esc_html_e( 'Print', 'doctor-ak-portal' ); ?></button>

	<script>window.addEventListener( 'load', function () { window.print(); } );</script>
</body>
</html>
