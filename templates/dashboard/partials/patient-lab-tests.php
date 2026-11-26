<?php
/**
 * Template: the patient's "Lab tests" tab — tests their doctors suggested,
 * booked in one step (cheapest lab, lab visit, first open day and time
 * already chosen), then tracked through to the results.
 *
 * Driven by doctor-ak-patient-labs.js (data-* attributes below).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $orders        Patient_Lab_Handler::view_model() orders.
 * @var array  $days          Bookable days — { value, label, isToday }.
 * @var array  $windows       Time windows, slug => label.
 * @var int    $hour          Current hour (site time), for today's windows.
 * @var string $saved_address Last home-collection address used.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_lab_icons = array(
	'flask'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2.5h4M8.5 2.5v5L4.2 15a1.5 1.5 0 0 0 1.3 2.3h9a1.5 1.5 0 0 0 1.3-2.3L11.5 7.5v-5"/><path d="M6 12.5h8"/></svg>',
	'alert'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.5v4"/><path d="M10 13.5h.01"/></svg>',
	'home'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 10 3.5l7 6"/><path d="M5 8.2V16.5h10V8.2"/><path d="M8.3 16.5v-4.3h3.4v4.3"/></svg>',
	'pin'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'check'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>',
	'file'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.5 2.5H6A1.5 1.5 0 0 0 4.5 4v12A1.5 1.5 0 0 0 6 17.5h8a1.5 1.5 0 0 0 1.5-1.5V6.5z"/><path d="M11.5 2.5v4h4"/><path d="M7.5 10.5h5M7.5 13.5h5"/></svg>',
);

$dak_money = function ( $amount ) {
	return 'PKR ' . number_format_i18n( (float) $amount );
};

$dak_status_pill = array(
	'suggested' => 'dak-status-pill-is-pending',
	'booked'    => 'dak-status-pill-is-confirmed',
	'collected' => 'dak-status-pill-is-checked-in',
	'ready'     => 'dak-status-pill-is-active',
	'cancelled' => 'dak-status-pill-is-neutral',
);

// Each window's closing hour, so today's past windows can't be picked.
$dak_window_ends = array( 'morning' => 12, 'afternoon' => 16, 'evening' => 20 );
$dak_phone       = \DoctorAKPortal\Includes\Clinic_Locations::website_contact()['phone'];
?>
<div class="dak-labs" data-lab-hour="<?php echo esc_attr( $hour ); ?>">
	<?php if ( empty( $orders ) ) : ?>
		<div class="dak-dashboard-card dak-labs-empty">
			<span class="dak-labs-empty-icon" aria-hidden="true"><?php echo $dak_lab_icons['flask']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h2><?php esc_html_e( 'No lab tests yet', 'doctor-ak-portal' ); ?></h2>
			<p><?php esc_html_e( 'When your doctor suggests tests at a visit, they’ll appear here — compare labs and book a lab visit or home sample collection in one tap.', 'doctor-ak-portal' ); ?></p>
			<?php $dak_lab_page = \DoctorAKPortal\Frontend\Lab_Tests_Page::public_url(); ?>
			<?php if ( '' !== $dak_lab_page ) : ?>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( $dak_lab_page ); ?>"><?php esc_html_e( 'Book a lab test yourself', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php foreach ( $orders as $order ) : ?>
		<?php
		$dak_is_open  = 'suggested' === $order['status'];
		$dak_covering = array_values( array_filter( $order['quotes'], function ( $quote ) {
			return $quote['covers_all'];
		} ) );
		$dak_partial  = array_values( array_filter( $order['quotes'], function ( $quote ) {
			return ! $quote['covers_all'];
		} ) );
		$dak_first    = $dak_covering ? $dak_covering[0] : null;
		$dak_order_id = 'dak-lab-order-' . $order['id'];
		?>
		<article class="dak-dashboard-card dak-lab-order is-<?php echo esc_attr( $order['status'] ); ?>" id="<?php echo esc_attr( $dak_order_id ); ?>" data-lab-order="<?php echo esc_attr( $order['id'] ); ?>">
			<header class="dak-lab-order-head">
				<span class="dak-lab-order-icon" aria-hidden="true"><?php echo $dak_lab_icons['flask']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div class="dak-lab-order-title">
					<h2>
						<?php
						echo esc_html(
							'' !== $order['doctor_name']
								/* translators: %s: doctor's name. */
								? sprintf( __( 'Suggested by Dr. %s', 'doctor-ak-portal' ), $order['doctor_name'] )
								: __( 'Booked by you', 'doctor-ak-portal' )
						);
						?>
					</h2>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: date, 2: number of tests. */
								_n( '%1$s · %2$d test', '%1$s · %2$d tests', count( $order['items'] ), 'doctor-ak-portal' ),
								$order['date_label'],
								count( $order['items'] )
							)
						);
						?>
					</p>
				</div>
				<span class="dak-status-pill <?php echo esc_attr( isset( $dak_status_pill[ $order['status'] ] ) ? $dak_status_pill[ $order['status'] ] : 'dak-status-pill-is-neutral' ); ?>"><?php echo esc_html( $order['status_label'] ); ?></span>
			</header>

			<ul class="dak-lab-tests">
				<?php foreach ( $order['items'] as $item ) : ?>
					<li class="dak-lab-test">
						<div class="dak-lab-test-main">
							<strong><?php echo esc_html( $item['test_name'] ); ?></strong>
							<span class="dak-lab-test-meta">
								<?php echo esc_html( implode( ' · ', array_filter( array( $item['sample'] ? sprintf( /* translators: %s: sample type, e.g. Blood. */ __( '%s sample', 'doctor-ak-portal' ), $item['sample'] ) : '', $item['report_time'] ? sprintf( /* translators: %s: e.g. "Same day". */ __( 'Report: %s', 'doctor-ak-portal' ), $item['report_time'] ) : '' ) ) ) ); ?>
							</span>
							<?php if ( '' !== $item['note'] ) : ?>
								<span class="dak-lab-test-note"><?php echo esc_html( sprintf( /* translators: %s: doctor's note. */ __( 'Doctor’s note: %s', 'doctor-ak-portal' ), $item['note'] ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $item['preparation'] && in_array( $order['status'], array( 'suggested', 'booked' ), true ) ) : ?>
							<span class="dak-lab-prep">
								<?php echo $dak_lab_icons['alert']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php echo esc_html( $item['preparation'] ); ?>
							</span>
						<?php endif; ?>
						<?php if ( ! $dak_is_open && $item['price'] > 0 ) : ?>
							<span class="dak-lab-test-price"><?php echo esc_html( $dak_money( $item['price'] ) ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $dak_is_open ) : ?>

				<?php if ( ! $dak_first ) : ?>
					<p class="dak-lab-unavailable">
						<?php esc_html_e( 'None of our partner labs offers all of these tests online yet.', 'doctor-ak-portal' ); ?>
						<?php if ( '' !== $dak_phone ) : ?>
							<?php esc_html_e( 'Call us and we’ll arrange them:', 'doctor-ak-portal' ); ?>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $dak_phone ) ); ?>"><?php echo esc_html( $dak_phone ); ?></a>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<form class="dak-lab-book" data-lab-book novalidate>
						<input type="hidden" name="order_id" value="<?php echo esc_attr( $order['id'] ); ?>">

						<fieldset class="dak-lab-step">
							<legend><span class="dak-lab-step-num">1</span><?php esc_html_e( 'Choose a lab', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-lab-choices">
								<?php foreach ( $dak_covering as $dak_index => $quote ) : ?>
									<label class="dak-lab-choice">
										<input type="radio" name="lab_id" value="<?php echo esc_attr( $quote['lab_id'] ); ?>" data-subtotal="<?php echo esc_attr( $quote['subtotal'] ); ?>" data-home="<?php echo $quote['home'] ? '1' : '0'; ?>" data-home-fee="<?php echo esc_attr( $quote['home_fee'] ); ?>"<?php checked( 0, $dak_index ); ?>>
										<span class="dak-lab-choice-body">
											<span class="dak-lab-choice-top">
												<strong><?php echo esc_html( $quote['name'] ); ?></strong>
												<span class="dak-lab-choice-price"><?php echo esc_html( $dak_money( $quote['subtotal'] ) ); ?></span>
											</span>
											<?php if ( '' !== $quote['place'] ) : ?>
												<span class="dak-lab-choice-place"><?php echo $dak_lab_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $quote['place'] ); ?></span>
											<?php endif; ?>
											<span class="dak-lab-choice-tags">
												<?php if ( 0 === $dak_index && count( $dak_covering ) > 1 ) : ?>
													<span class="dak-lab-tag is-best"><?php esc_html_e( 'Lowest price', 'doctor-ak-portal' ); ?></span>
												<?php endif; ?>
												<?php if ( $quote['home'] ) : ?>
													<span class="dak-lab-tag">
														<?php
														echo esc_html(
															$quote['home_fee'] > 0
																/* translators: %s: home collection fee. */
																? sprintf( __( 'Home collection +%s', 'doctor-ak-portal' ), $dak_money( $quote['home_fee'] ) )
																: __( 'Free home collection', 'doctor-ak-portal' )
														);
														?>
													</span>
												<?php else : ?>
													<span class="dak-lab-tag is-muted"><?php esc_html_e( 'Lab visit only', 'doctor-ak-portal' ); ?></span>
												<?php endif; ?>
											</span>
										</span>
									</label>
								<?php endforeach; ?>

								<?php foreach ( $dak_partial as $quote ) : ?>
									<div class="dak-lab-choice is-unavailable" aria-disabled="true">
										<span class="dak-lab-choice-body">
											<span class="dak-lab-choice-top"><strong><?php echo esc_html( $quote['name'] ); ?></strong></span>
											<span class="dak-lab-choice-place">
												<?php
												/* translators: %s: comma-separated test names. */
												echo esc_html( sprintf( __( 'Doesn’t offer: %s', 'doctor-ak-portal' ), implode( ', ', $quote['missing'] ) ) );
												?>
											</span>
										</span>
									</div>
								<?php endforeach; ?>
							</div>
						</fieldset>

						<fieldset class="dak-lab-step">
							<legend><span class="dak-lab-step-num">2</span><?php esc_html_e( 'Where', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-lab-pills">
								<label class="dak-lab-pill">
									<input type="radio" name="collection" value="lab" checked>
									<span><?php echo $dak_lab_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Visit the lab', 'doctor-ak-portal' ); ?></span>
								</label>
								<label class="dak-lab-pill" data-lab-home-option>
									<input type="radio" name="collection" value="home"<?php disabled( ! $dak_first['home'] ); ?>>
									<span><?php echo $dak_lab_icons['home']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Home sample collection', 'doctor-ak-portal' ); ?></span>
								</label>
							</div>
							<div class="dak-field dak-lab-address dak-hidden" data-lab-address>
								<label for="<?php echo esc_attr( $dak_order_id ); ?>-address"><?php esc_html_e( 'Address for sample collection', 'doctor-ak-portal' ); ?></label>
								<textarea id="<?php echo esc_attr( $dak_order_id ); ?>-address" name="address" rows="2" autocomplete="street-address" placeholder="<?php esc_attr_e( 'House, street, area, city', 'doctor-ak-portal' ); ?>"><?php echo esc_textarea( $saved_address ); ?></textarea>
							</div>
						</fieldset>

						<fieldset class="dak-lab-step">
							<legend><span class="dak-lab-step-num">3</span><?php esc_html_e( 'When', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-lab-days" role="radiogroup" aria-label="<?php esc_attr_e( 'Day', 'doctor-ak-portal' ); ?>">
								<?php foreach ( $days as $dak_day_index => $day ) : ?>
									<label class="dak-lab-day">
										<input type="radio" name="date" value="<?php echo esc_attr( $day['value'] ); ?>" data-today="<?php echo $day['isToday'] ? '1' : '0'; ?>"<?php checked( 0, $dak_day_index ); ?>>
										<span><?php echo esc_html( $day['label'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							<div class="dak-lab-pills dak-lab-windows" role="radiogroup" aria-label="<?php esc_attr_e( 'Time', 'doctor-ak-portal' ); ?>">
								<?php $dak_window_index = 0; ?>
								<?php foreach ( $windows as $dak_window => $dak_window_label ) : ?>
									<label class="dak-lab-pill">
										<input type="radio" name="time_window" value="<?php echo esc_attr( $dak_window ); ?>" data-ends="<?php echo esc_attr( $dak_window_ends[ $dak_window ] ); ?>"<?php checked( 0, $dak_window_index++ ); ?>>
										<span><?php echo esc_html( $dak_window_label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</fieldset>

						<div class="dak-alert dak-alert-error dak-hidden" data-lab-error role="alert"></div>

						<div class="dak-lab-book-footer">
							<div class="dak-lab-total">
								<span><?php esc_html_e( 'Total', 'doctor-ak-portal' ); ?></span>
								<strong data-lab-total><?php echo esc_html( $dak_money( $dak_first['subtotal'] ) ); ?></strong>
								<span class="dak-lab-total-note"><?php esc_html_e( 'Pay at collection', 'doctor-ak-portal' ); ?></span>
							</div>
							<button type="submit" class="dak-button dak-button-primary" data-lab-submit><?php esc_html_e( 'Book tests', 'doctor-ak-portal' ); ?></button>
						</div>
					</form>
				<?php endif; ?>

			<?php elseif ( 'cancelled' !== $order['status'] ) : ?>
				<?php
				$dak_steps = array(
					'booked'    => __( 'Booked', 'doctor-ak-portal' ),
					'collected' => __( 'Sample collected', 'doctor-ak-portal' ),
					'ready'     => __( 'Results ready', 'doctor-ak-portal' ),
				);
				$dak_reached = array_search( $order['status'], array_keys( $dak_steps ), true );
				?>
				<ol class="dak-lab-track" aria-label="<?php esc_attr_e( 'Progress', 'doctor-ak-portal' ); ?>">
					<?php $dak_step_index = 0; ?>
					<?php foreach ( $dak_steps as $dak_step => $dak_step_label ) : ?>
						<li class="<?php echo $dak_step_index <= $dak_reached ? 'is-done' : ''; ?><?php echo $dak_step_index === $dak_reached ? ' is-current' : ''; ?>"<?php echo $dak_step_index === $dak_reached ? ' aria-current="step"' : ''; ?>>
							<span class="dak-lab-track-dot" aria-hidden="true"><?php echo $dak_step_index <= $dak_reached ? $dak_lab_icons['check'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span><?php echo esc_html( $dak_step_label ); ?></span>
						</li>
						<?php $dak_step_index++; ?>
					<?php endforeach; ?>
				</ol>

				<dl class="dak-lab-details">
					<div>
						<dt><?php esc_html_e( 'Lab', 'doctor-ak-portal' ); ?></dt>
						<dd><?php echo esc_html( $order['lab_name'] ); ?><?php if ( '' !== $order['lab_place'] && 'lab' === $order['collection'] ) : ?><span><?php echo esc_html( $order['lab_place'] ); ?></span><?php endif; ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'When', 'doctor-ak-portal' ); ?></dt>
						<dd><?php echo esc_html( $order['visit_label'] ); ?><span><?php echo esc_html( $order['time_window_label'] ); ?></span></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Where', 'doctor-ak-portal' ); ?></dt>
						<dd><?php echo esc_html( 'home' === $order['collection'] ? __( 'Home sample collection', 'doctor-ak-portal' ) : __( 'At the lab', 'doctor-ak-portal' ) ); ?><?php if ( 'home' === $order['collection'] && '' !== $order['address'] ) : ?><span><?php echo esc_html( $order['address'] ); ?></span><?php endif; ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Total', 'doctor-ak-portal' ); ?></dt>
						<dd><?php echo esc_html( $dak_money( $order['total'] ) ); ?><span><?php echo esc_html( $order['is_paid'] ? __( 'Paid', 'doctor-ak-portal' ) : __( 'Pay at collection', 'doctor-ak-portal' ) ); ?></span></dd>
					</div>
				</dl>

				<div class="dak-alert dak-alert-error dak-hidden" data-lab-error role="alert"></div>

				<div class="dak-lab-actions">
					<?php if ( 'ready' === $order['status'] && '' !== $order['report_url'] ) : ?>
						<a class="dak-button dak-button-primary" href="<?php echo esc_url( $order['report_url'] ); ?>" target="_blank" rel="noopener"><?php echo $dak_lab_icons['file']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'View results', 'doctor-ak-portal' ); ?></a>
					<?php elseif ( 'collected' === $order['status'] ) : ?>
						<p class="dak-lab-hint"><?php esc_html_e( 'Your sample has been collected. We’ll let you know as soon as the results are ready.', 'doctor-ak-portal' ); ?></p>
					<?php elseif ( 'booked' === $order['status'] ) : ?>
						<p class="dak-lab-hint"><?php esc_html_e( 'Please follow the preparation notes above before your sample is taken.', 'doctor-ak-portal' ); ?></p>
						<button type="button" class="dak-text-action dak-lab-cancel" data-lab-cancel="<?php echo esc_attr( $order['id'] ); ?>"><?php esc_html_e( 'Cancel booking', 'doctor-ak-portal' ); ?></button>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p class="dak-lab-hint"><?php esc_html_e( 'This order was cancelled.', 'doctor-ak-portal' ); ?></p>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
