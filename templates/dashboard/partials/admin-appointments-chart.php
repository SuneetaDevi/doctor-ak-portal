<?php
/**
 * Template: Admin Dashboard overview's "Appointments" clustered bar chart —
 * appointment counts per status, bucketed by day/week/month, with a toggle
 * to switch between them (re-fetched over AJAX, no page reload — see
 * doctor-ak-admin-appointments-chart.js). Replaces the old static
 * "Appointments by status" snapshot list with a real time dimension.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $chart_rows    Rows from Appointments::status_counts_by_period() — [{key,label,counts:{status=>n}}], oldest first.
 * @var array  $statuses      Appointments::status_options() — status slug => label, defines series/legend order.
 * @var array  $status_colors Status slug => CSS custom-property name (without var()), see Admin_Dashboard::appointments_chart_status_colors().
 * @var string $period        Active period: 'day', 'week', or 'month'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_period_labels = array(
	'day'   => __( 'Day', 'doctor-ak-portal' ),
	'week'  => __( 'Week', 'doctor-ak-portal' ),
	'month' => __( 'Month', 'doctor-ak-portal' ),
);

$dak_chart_w    = 640;
$dak_chart_h    = 220;
$dak_pad_left   = 34;
$dak_pad_right  = 10;
$dak_pad_top    = 14;
$dak_pad_bottom = 28;
$dak_plot_w     = $dak_chart_w - $dak_pad_left - $dak_pad_right;
$dak_plot_h     = $dak_chart_h - $dak_pad_top - $dak_pad_bottom;

$dak_group_count  = count( $chart_rows );
$dak_series_slugs = array_keys( $statuses );
$dak_series_count = count( $dak_series_slugs );

$dak_max_count = 1;
foreach ( $chart_rows as $dak_row ) {
	foreach ( $dak_row['counts'] as $dak_count ) {
		$dak_max_count = max( $dak_max_count, $dak_count );
	}
}
$dak_scale     = pow( 10, floor( log10( $dak_max_count ) ) );
$dak_max_count = max( 1, (int) ( ceil( $dak_max_count / $dak_scale ) * $dak_scale ) );

$dak_group_gap = 6;
$dak_group_w   = $dak_group_count > 0 ? ( $dak_plot_w - ( $dak_group_count - 1 ) * $dak_group_gap ) / $dak_group_count : $dak_plot_w;
$dak_bar_gap   = 1.5;
$dak_bar_w     = $dak_series_count > 0 ? max( 1.5, ( $dak_group_w - ( $dak_series_count - 1 ) * $dak_bar_gap ) / $dak_series_count ) : $dak_group_w;
?>
<section class="dak-dashboard-card" id="dak-appointments-chart">
	<div class="dak-dashboard-card-header">
		<div>
			<h2><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></h2>
			<p class="dak-notifications-card-subtitle"><?php esc_html_e( 'Appointment counts by status, over time', 'doctor-ak-portal' ); ?></p>
		</div>
		<div class="dak-chart-period-toggle" role="group" aria-label="<?php esc_attr_e( 'Chart period', 'doctor-ak-portal' ); ?>">
			<?php foreach ( $dak_period_labels as $dak_period_slug => $dak_period_label ) : ?>
				<button
					type="button"
					class="dak-chart-period-btn <?php echo $dak_period_slug === $period ? 'is-active' : ''; ?>"
					data-chart-period="<?php echo esc_attr( $dak_period_slug ); ?>"
				><?php echo esc_html( $dak_period_label ); ?></button>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( empty( $chart_rows ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No appointments yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<svg class="dak-chart-svg" viewBox="0 0 <?php echo esc_attr( $dak_chart_w ); ?> <?php echo esc_attr( $dak_chart_h ); ?>" role="img" aria-label="<?php esc_attr_e( 'Clustered bar chart of appointment counts by status over time', 'doctor-ak-portal' ); ?>">
			<?php foreach ( array( 0, 0.5, 1 ) as $dak_fraction ) : ?>
				<?php $dak_gy = round( $dak_pad_top + $dak_plot_h * ( 1 - $dak_fraction ), 1 ); ?>
				<line class="dak-chart-gridline" x1="<?php echo esc_attr( $dak_pad_left ); ?>" y1="<?php echo esc_attr( $dak_gy ); ?>" x2="<?php echo esc_attr( $dak_chart_w - $dak_pad_right ); ?>" y2="<?php echo esc_attr( $dak_gy ); ?>"></line>
				<text class="dak-chart-axis-label" x="<?php echo esc_attr( $dak_pad_left - 6 ); ?>" y="<?php echo esc_attr( $dak_gy + 3 ); ?>" text-anchor="end"><?php echo esc_html( number_format_i18n( (int) round( $dak_max_count * $dak_fraction ) ) ); ?></text>
			<?php endforeach; ?>

			<?php foreach ( $chart_rows as $dak_gi => $dak_row ) : ?>
				<?php $dak_group_x = $dak_pad_left + $dak_gi * ( $dak_group_w + $dak_group_gap ); ?>
				<?php foreach ( $dak_series_slugs as $dak_si => $dak_status_slug ) : ?>
					<?php
					$dak_value     = isset( $dak_row['counts'][ $dak_status_slug ] ) ? (int) $dak_row['counts'][ $dak_status_slug ] : 0;
					$dak_bar_h     = $dak_value > 0 ? max( 1.5, ( $dak_value / $dak_max_count ) * $dak_plot_h ) : 0;
					$dak_bar_x     = $dak_group_x + $dak_si * ( $dak_bar_w + $dak_bar_gap );
					$dak_bar_y     = $dak_pad_top + $dak_plot_h - $dak_bar_h;
					$dak_color_var = isset( $status_colors[ $dak_status_slug ] ) ? $status_colors[ $dak_status_slug ] : '--dak-muted';
					?>
					<?php if ( $dak_value > 0 ) : ?>
						<rect
							class="dak-chart-cluster-bar"
							x="<?php echo esc_attr( round( $dak_bar_x, 1 ) ); ?>"
							y="<?php echo esc_attr( round( $dak_bar_y, 1 ) ); ?>"
							width="<?php echo esc_attr( round( $dak_bar_w, 1 ) ); ?>"
							height="<?php echo esc_attr( round( $dak_bar_h, 1 ) ); ?>"
							rx="1.5"
							style="fill: var(<?php echo esc_attr( $dak_color_var ); ?>);"
						><title><?php echo esc_html( $dak_row['label'] . ' — ' . $statuses[ $dak_status_slug ] . ': ' . number_format_i18n( $dak_value ) ); ?></title></rect>
					<?php endif; ?>
				<?php endforeach; ?>

				<text class="dak-chart-axis-label" x="<?php echo esc_attr( round( $dak_group_x + $dak_group_w / 2, 1 ) ); ?>" y="<?php echo esc_attr( $dak_chart_h - 8 ); ?>" text-anchor="middle"><?php echo esc_html( $dak_row['label'] ); ?></text>
			<?php endforeach; ?>
		</svg>

		<div class="dak-chart-legend">
			<?php foreach ( $statuses as $dak_status_slug => $dak_status_label ) : ?>
				<span class="dak-chart-legend-item">
					<span class="dak-chart-legend-swatch" style="background: var(<?php echo esc_attr( isset( $status_colors[ $dak_status_slug ] ) ? $status_colors[ $dak_status_slug ] : '--dak-muted' ); ?>);"></span>
					<?php echo esc_html( $dak_status_label ); ?>
				</span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
