<?php
/**
 * Template: Administrator dashboard's "Dashboard" overview (stat cards).
 *
 * Total Doctors, Total Patients, Total Clinics and Total Appointments are
 * backed by real data; Active Services and Total Revenue still show
 * "Coming soon" since neither a Services CRUD nor payment-revenue tracking
 * exists yet.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int $total_doctors      Count of users holding the Doctor role.
 * @var int $total_patients     Count of users holding the Patient role.
 * @var int $total_clinics      Count of clinic rows across every doctor.
 * @var int $total_appointments Count of published appointments ever booked.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_overview_icons = array(
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'users'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="2.8"/><path d="M1.8 16c0-2.9 2.3-4.8 5.2-4.8s5.2 1.9 5.2 4.8"/><path d="M13 7.2a2.6 2.6 0 1 1 3.6 2.4"/><path d="M14.5 11.3c2 .3 3.7 1.7 3.7 4"/></svg>',
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'person'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'settings' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="2.6"/><path d="M10 2.8v2M10 15.2v2M17.2 10h-2M4.8 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/></svg>',
	'money'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2v7.6M12.2 8.1c0-1-1-1.6-2.2-1.6s-2.2.6-2.2 1.5c0 2.2 4.4 1 4.4 3.2 0 .9-1 1.5-2.2 1.5s-2.2-.6-2.2-1.6"/></svg>',
);

$cards = array(
	array(
		'icon'  => 'calendar',
		'value' => $total_appointments,
		'label' => __( 'Total Appointments', 'doctor-ak-portal' ),
	),
	array(
		'icon'  => 'users',
		'value' => $total_patients,
		'label' => __( 'Total Patients', 'doctor-ak-portal' ),
	),
	array(
		'icon'  => 'pin',
		'value' => $total_clinics,
		'label' => __( 'Total Clinics', 'doctor-ak-portal' ),
	),
	array(
		'icon'  => 'person',
		'value' => $total_doctors,
		'label' => __( 'Total Doctors', 'doctor-ak-portal' ),
	),
	array(
		'icon'  => 'settings',
		'value' => null,
		'label' => __( 'Active Services', 'doctor-ak-portal' ),
	),
	array(
		'icon'  => 'money',
		'value' => null,
		'label' => __( 'Total Revenue', 'doctor-ak-portal' ),
	),
);
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Insights', 'doctor-ak-portal' ); ?></h1>
</div>

<section class="dak-dashboard-statistics">
	<?php foreach ( $cards as $card ) : ?>
		<div class="dak-stat-card">
			<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_overview_icons[ $card['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php if ( null !== $card['value'] ) : ?>
				<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $card['value'] ) ); ?></span>
			<?php else : ?>
				<span class="dak-stat-value dak-stat-value-pending"><?php esc_html_e( 'Coming soon', 'doctor-ak-portal' ); ?></span>
			<?php endif; ?>
			<span class="dak-stat-label"><?php echo esc_html( $card['label'] ); ?></span>
		</div>
	<?php endforeach; ?>
</section>

<p class="dak-empty-state">
	<?php esc_html_e( 'Deeper appointment insights (top doctors, booking status breakdown) are coming soon.', 'doctor-ak-portal' ); ?>
</p>
