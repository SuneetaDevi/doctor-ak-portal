<?php
/**
 * Template: Dashboard → Lab Tests (Admin_Labs::section_html()).
 *
 * Views: orders (default — booked tests to collect, results to upload),
 * labs (partner labs) and lab (add/edit a lab and its price for each test),
 * tests (the catalog doctors pick from) and test (add/edit a test).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $view         'orders' | 'labs' | 'lab' | 'tests' | 'test'.
 * @var string $notice       Success notice slug, or ''.
 * @var string $error        Error message, or ''.
 * @var string $nonce_action Nonce action for every form.
 * @var string $post_url     admin-post.php URL.
 * @var array  $urls         { orders, labs, tests } view URLs.
 * @var array  $actions      { order, upload, lab, test } admin-post actions.
 * @var array  $counts       Lab_Orders::status_counts().
 */

use DoctorAKPortal\Includes\Lab_Orders;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_notices = array(
	'collected'  => __( 'Marked as sample collected.', 'doctor-ak-portal' ),
	'paid'       => __( 'Payment recorded.', 'doctor-ak-portal' ),
	'cancelled'  => __( 'Order cancelled.', 'doctor-ak-portal' ),
	'uploaded'   => __( 'Results uploaded. The patient and doctor have been notified.', 'doctor-ak-portal' ),
	'lab_saved'  => __( 'Lab saved.', 'doctor-ak-portal' ),
	'test_saved' => __( 'Test saved.', 'doctor-ak-portal' ),
);

$dak_nonce = wp_create_nonce( $nonce_action );
$dak_money = function ( $amount ) {
	return 'PKR ' . number_format_i18n( (float) $amount );
};
$dak_pill  = array(
	'suggested' => 'dak-status-pill-is-pending',
	'booked'    => 'dak-status-pill-is-confirmed',
	'collected' => 'dak-status-pill-is-checked-in',
	'ready'     => 'dak-status-pill-is-active',
	'cancelled' => 'dak-status-pill-is-neutral',
);
$dak_section = in_array( $view, array( 'labs', 'lab' ), true ) ? 'labs' : ( in_array( $view, array( 'tests', 'test' ), true ) ? 'tests' : 'orders' );
?>
<div class="dak-list-page dak-admin-labs">
	<div class="dak-page-head">
		<div>
			<h1><?php esc_html_e( 'Lab Tests', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Doctors suggest tests at a visit; patients book them with a partner lab. Collect samples, record payment and upload results here.', 'doctor-ak-portal' ); ?></p>
		</div>
		<?php if ( 'labs' === $view ) : ?>
			<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'lab', $urls['labs'] ) ); ?>"><?php esc_html_e( '+ Add lab', 'doctor-ak-portal' ); ?></a>
		<?php elseif ( 'tests' === $view ) : ?>
			<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'test', $urls['tests'] ) ); ?>"><?php esc_html_e( '+ Add test', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</div>

	<nav class="dak-tabs dak-admin-labs-views" aria-label="<?php esc_attr_e( 'Lab Tests sections', 'doctor-ak-portal' ); ?>">
		<a class="dak-tab<?php echo 'orders' === $dak_section ? ' is-active' : ''; ?>" href="<?php echo esc_url( $urls['orders'] ); ?>"<?php echo 'orders' === $dak_section ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Orders', 'doctor-ak-portal' ); ?></a>
		<a class="dak-tab<?php echo 'labs' === $dak_section ? ' is-active' : ''; ?>" href="<?php echo esc_url( $urls['labs'] ); ?>"<?php echo 'labs' === $dak_section ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Partner labs & prices', 'doctor-ak-portal' ); ?></a>
		<a class="dak-tab<?php echo 'tests' === $dak_section ? ' is-active' : ''; ?>" href="<?php echo esc_url( $urls['tests'] ); ?>"<?php echo 'tests' === $dak_section ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Test catalog', 'doctor-ak-portal' ); ?></a>
	</nav>

	<?php if ( '' !== $error ) : ?>
		<div class="dak-alert dak-alert-error" role="alert"><?php echo esc_html( $error ); ?></div>
	<?php elseif ( isset( $dak_notices[ $notice ] ) ) : ?>
		<div class="dak-alert dak-alert-success" role="status"><?php echo esc_html( $dak_notices[ $notice ] ); ?></div>
	<?php endif; ?>

	<?php if ( 'orders' === $view ) : ?>
		<?php
		$dak_status_tabs = array(
			'booked'    => __( 'To collect', 'doctor-ak-portal' ),
			'collected' => __( 'Awaiting results', 'doctor-ak-portal' ),
			'ready'     => __( 'Results ready', 'doctor-ak-portal' ),
			'suggested' => __( 'Not booked yet', 'doctor-ak-portal' ),
			'cancelled' => __( 'Cancelled', 'doctor-ak-portal' ),
			'all'       => __( 'All', 'doctor-ak-portal' ),
		);
		?>
		<nav class="dak-lab-status-tabs" aria-label="<?php esc_attr_e( 'Order status', 'doctor-ak-portal' ); ?>">
			<?php foreach ( $dak_status_tabs as $dak_slug => $dak_label ) : ?>
				<?php $dak_count = 'all' === $dak_slug ? array_sum( $counts ) : $counts[ $dak_slug ]; ?>
				<a class="dak-lab-status-tab<?php echo $dak_slug === $status ? ' is-active' : ''; ?><?php echo 'booked' === $dak_slug && $dak_count > 0 ? ' has-work' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'status', $dak_slug, $urls['orders'] ) ); ?>"<?php echo $dak_slug === $status ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $dak_label ); ?>
					<span><?php echo esc_html( number_format_i18n( $dak_count ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<form class="dak-lab-search" method="get" action="<?php echo esc_url( $urls['orders'] ); ?>">
			<?php foreach ( wp_parse_args( wp_parse_url( $urls['orders'], PHP_URL_QUERY ) ) as $dak_key => $dak_value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $dak_key ); ?>" value="<?php echo esc_attr( $dak_value ); ?>">
			<?php endforeach; ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search patient name or phone…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search patient name or phone', 'doctor-ak-portal' ); ?>">
			<button type="submit" class="dak-button dak-button-secondary dak-button-sm"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></button>
		</form>

		<?php if ( empty( $orders ) ) : ?>
			<p class="dak-empty-state">
				<?php
				echo esc_html(
					'booked' === $status && '' === $search
						? __( 'No samples waiting to be collected.', 'doctor-ak-portal' )
						: __( 'No lab orders here.', 'doctor-ak-portal' )
				);
				?>
			</p>
		<?php endif; ?>

		<div class="dak-lab-admin-orders">
			<?php foreach ( $orders as $order ) : ?>
				<?php
				$dak_hidden = function () use ( $order, $status, $search, $dak_nonce ) {
					return '<input type="hidden" name="_wpnonce" value="' . esc_attr( $dak_nonce ) . '">'
						. '<input type="hidden" name="order_id" value="' . esc_attr( $order['id'] ) . '">'
						. '<input type="hidden" name="return_status" value="' . esc_attr( $status ) . '">'
						. '<input type="hidden" name="return_search" value="' . esc_attr( $search ) . '">';
				};
				?>
				<article class="dak-lab-admin-order is-<?php echo esc_attr( $order['status'] ); ?>">
					<div class="dak-lab-admin-col dak-lab-admin-who">
						<strong><?php echo esc_html( $order['patient_name'] ); ?></strong>
						<?php if ( '' !== $order['patient_phone'] ) : ?>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $order['patient_phone'] ) ); ?>"><?php echo esc_html( $order['patient_phone'] ); ?></a>
						<?php endif; ?>
						<span class="dak-lab-admin-sub">
							<?php
							echo esc_html(
								'' !== $order['doctor_name']
									? sprintf(
										/* translators: 1: order number, 2: doctor name. */
										__( 'LAB-%1$04d · Dr. %2$s', 'doctor-ak-portal' ),
										$order['id'],
										$order['doctor_name']
									)
									: sprintf(
										/* translators: %d: order number. */
										__( 'LAB-%04d · Booked online by the patient', 'doctor-ak-portal' ),
										$order['id']
									)
							);
							?>
						</span>
					</div>

					<div class="dak-lab-admin-col dak-lab-admin-tests">
						<ul>
							<?php foreach ( $order['items'] as $item ) : ?>
								<li><?php echo esc_html( $item['test_name'] ); ?><?php if ( $item['price'] > 0 ) : ?> <span><?php echo esc_html( $dak_money( $item['price'] ) ); ?></span><?php endif; ?></li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div class="dak-lab-admin-col dak-lab-admin-when">
						<?php if ( '' !== $order['lab_name'] ) : ?>
							<strong><?php echo esc_html( $order['lab_name'] ); ?></strong>
							<span><?php echo esc_html( $order['visit_label'] . ' · ' . $order['time_window_label'] ); ?></span>
							<span class="dak-lab-admin-where<?php echo 'home' === $order['collection'] ? ' is-home' : ''; ?>">
								<?php echo esc_html( 'home' === $order['collection'] ? __( 'Home collection', 'doctor-ak-portal' ) . ( '' !== $order['address'] ? ': ' . $order['address'] : '' ) : __( 'Lab visit', 'doctor-ak-portal' ) ); ?>
							</span>
							<?php if ( '' !== $order['patient_notes'] ) : ?>
								<span class="dak-lab-admin-sub"><?php echo esc_html( $order['patient_notes'] ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="dak-lab-admin-sub"><?php esc_html_e( 'Not booked yet', 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</div>

					<div class="dak-lab-admin-col dak-lab-admin-money">
						<?php if ( $order['total'] > 0 ) : ?>
							<strong><?php echo esc_html( $dak_money( $order['total'] ) ); ?></strong>
							<span class="dak-status-pill <?php echo $order['is_paid'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-pending'; ?>"><?php echo esc_html( $order['is_paid'] ? __( 'Paid', 'doctor-ak-portal' ) : __( 'Pay at collection', 'doctor-ak-portal' ) ); ?></span>
						<?php endif; ?>
						<span class="dak-status-pill <?php echo esc_attr( $dak_pill[ $order['status'] ] ); ?>"><?php echo esc_html( $order['status_label'] ); ?></span>
					</div>

					<div class="dak-lab-admin-col dak-lab-admin-actions">
						<?php if ( Lab_Orders::STATUS_BOOKED === $order['status'] ) : ?>
							<form method="post" action="<?php echo esc_url( $post_url ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( $actions['order'] ); ?>">
								<input type="hidden" name="do" value="collected">
								<?php echo $dak_hidden(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
								<button type="submit" class="dak-button dak-button-primary dak-button-sm"><?php esc_html_e( 'Sample collected', 'doctor-ak-portal' ); ?></button>
							</form>
						<?php endif; ?>

						<?php if ( in_array( $order['status'], array( Lab_Orders::STATUS_BOOKED, Lab_Orders::STATUS_COLLECTED, Lab_Orders::STATUS_READY ), true ) ) : ?>
							<form method="post" action="<?php echo esc_url( $post_url ); ?>" enctype="multipart/form-data" class="dak-lab-upload">
								<input type="hidden" name="action" value="<?php echo esc_attr( $actions['upload'] ); ?>">
								<?php echo $dak_hidden(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
								<label class="dak-button dak-button-<?php echo Lab_Orders::STATUS_COLLECTED === $order['status'] ? 'primary' : 'secondary'; ?> dak-button-sm dak-lab-upload-button">
									<?php echo esc_html( Lab_Orders::STATUS_READY === $order['status'] ? __( 'Replace results', 'doctor-ak-portal' ) : __( 'Upload results', 'doctor-ak-portal' ) ); ?>
									<input type="file" name="results" accept="application/pdf,image/jpeg,image/png,image/webp" class="dak-visually-hidden" onchange="this.form.submit()">
								</label>
							</form>
						<?php endif; ?>

						<?php if ( '' !== $order['report_url'] ) : ?>
							<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $order['report_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View results', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>

						<?php if ( $order['total'] > 0 && ! $order['is_paid'] && Lab_Orders::STATUS_CANCELLED !== $order['status'] ) : ?>
							<form method="post" action="<?php echo esc_url( $post_url ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( $actions['order'] ); ?>">
								<input type="hidden" name="do" value="paid">
								<?php echo $dak_hidden(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
								<button type="submit" class="dak-button dak-button-secondary dak-button-sm"><?php esc_html_e( 'Mark paid', 'doctor-ak-portal' ); ?></button>
							</form>
						<?php endif; ?>

						<?php if ( ! in_array( $order['status'], array( Lab_Orders::STATUS_READY, Lab_Orders::STATUS_CANCELLED ), true ) ) : ?>
							<form method="post" action="<?php echo esc_url( $post_url ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Cancel this lab order?', 'doctor-ak-portal' ) ); ?>');">
								<input type="hidden" name="action" value="<?php echo esc_attr( $actions['order'] ); ?>">
								<input type="hidden" name="do" value="cancel">
								<?php echo $dak_hidden(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
								<button type="submit" class="dak-text-action dak-lab-cancel"><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></button>
							</form>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if ( $pages > 1 ) : ?>
			<nav class="dak-lab-pagination" aria-label="<?php esc_attr_e( 'Pages', 'doctor-ak-portal' ); ?>">
				<?php for ( $dak_n = 1; $dak_n <= $pages; $dak_n++ ) : ?>
					<a class="<?php echo $dak_n === $page ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array_filter( array( 'status' => $status, 'search' => $search, 'lpage' => $dak_n > 1 ? $dak_n : '' ) ), $urls['orders'] ) ); ?>"<?php echo $dak_n === $page ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $dak_n ); ?></a>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>

	<?php elseif ( 'labs' === $view ) : ?>

		<?php if ( empty( $labs ) ) : ?>
			<div class="dak-lab-setup">
				<h2><?php esc_html_e( 'Add your first partner lab', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'Add the labs you work with and what each charges per test. Patients compare them and book the one they want. Add your tests in the Test catalog first, so you can price them.', 'doctor-ak-portal' ); ?></p>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'lab', $urls['labs'] ) ); ?>"><?php esc_html_e( '+ Add lab', 'doctor-ak-portal' ); ?></a>
			</div>
		<?php else : ?>
			<div class="dak-data-table-wrap">
				<table class="dak-data-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Lab', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Tests priced', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Home collection', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><span class="dak-visually-hidden"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $labs as $lab ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Lab', 'doctor-ak-portal' ); ?>"><strong><?php echo esc_html( $lab['name'] ); ?></strong><br><span class="dak-lab-admin-sub"><?php echo esc_html( implode( ', ', array_filter( array( $lab['address'], $lab['city'] ) ) ) ); ?></span></td>
								<td data-label="<?php esc_attr_e( 'Tests priced', 'doctor-ak-portal' ); ?>"><?php echo esc_html( number_format_i18n( isset( $offered[ $lab['id'] ] ) ? $offered[ $lab['id'] ] : 0 ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Home collection', 'doctor-ak-portal' ); ?>">
									<?php
									echo esc_html(
										$lab['home_collection']
											? ( $lab['home_collection_fee'] > 0 ? sprintf( /* translators: %s: fee. */ __( 'Yes, +%s', 'doctor-ak-portal' ), $dak_money( $lab['home_collection_fee'] ) ) : __( 'Yes, free', 'doctor-ak-portal' ) )
											: __( 'No', 'doctor-ak-portal' )
									);
									?>
								</td>
								<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>"><span class="dak-status-pill <?php echo $lab['active'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral'; ?>"><?php echo esc_html( $lab['active'] ? __( 'Active', 'doctor-ak-portal' ) : __( 'Off', 'doctor-ak-portal' ) ); ?></span></td>
								<td class="dak-col-actions"><a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( add_query_arg( array( 'view' => 'lab', 'id' => $lab['id'] ), $urls['labs'] ) ); ?>"><?php esc_html_e( 'Edit & prices', 'doctor-ak-portal' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	<?php elseif ( 'lab' === $view ) : ?>

		<form class="dak-form-card dak-form-card-padded dak-lab-form" method="post" action="<?php echo esc_url( $post_url ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $actions['lab'] ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $dak_nonce ); ?>">
			<input type="hidden" name="id" value="<?php echo esc_attr( $lab ? $lab['id'] : 0 ); ?>">

			<h2><?php echo esc_html( $lab ? __( 'Edit lab', 'doctor-ak-portal' ) : __( 'Add lab', 'doctor-ak-portal' ) ); ?></h2>

			<div class="dak-field-row">
				<div class="dak-field">
					<label for="dak-lab-name"><?php esc_html_e( 'Lab name', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-lab-name" name="name" required value="<?php echo esc_attr( $lab ? $lab['name'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Chughtai Lab — Clifton', 'doctor-ak-portal' ); ?>">
				</div>
				<div class="dak-field">
					<label for="dak-lab-city"><?php esc_html_e( 'Area / city', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-lab-city" name="city" value="<?php echo esc_attr( $lab ? $lab['city'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Clifton, Karachi', 'doctor-ak-portal' ); ?>">
				</div>
			</div>

			<div class="dak-field">
				<label for="dak-lab-address"><?php esc_html_e( 'Address', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-lab-address" name="address" value="<?php echo esc_attr( $lab ? $lab['address'] : '' ); ?>">
			</div>

			<div class="dak-field-row">
				<label class="dak-lab-check">
					<input type="checkbox" name="home_collection" value="1"<?php checked( $lab && $lab['home_collection'] ); ?>>
					<span><?php esc_html_e( 'Collects samples at home', 'doctor-ak-portal' ); ?></span>
				</label>
				<div class="dak-field">
					<label for="dak-lab-home-fee"><?php esc_html_e( 'Home collection fee (PKR)', 'doctor-ak-portal' ); ?></label>
					<input type="number" id="dak-lab-home-fee" name="home_collection_fee" min="0" step="1" value="<?php echo esc_attr( $lab ? (float) $lab['home_collection_fee'] : 0 ); ?>">
				</div>
			</div>

			<label class="dak-lab-check">
				<input type="checkbox" name="active" value="1"<?php checked( ! $lab || $lab['active'] ); ?>>
				<span><?php esc_html_e( 'Active — patients can book this lab', 'doctor-ak-portal' ); ?></span>
			</label>

			<h3 class="dak-lab-form-sub"><?php esc_html_e( 'Price per test', 'doctor-ak-portal' ); ?></h3>
			<p class="dak-field-hint"><?php esc_html_e( 'Leave a test blank if this lab doesn’t offer it.', 'doctor-ak-portal' ); ?></p>

			<?php if ( empty( $tests ) ) : ?>
				<p class="dak-empty-state">
					<?php esc_html_e( 'No tests in the catalog yet.', 'doctor-ak-portal' ); ?>
					<a href="<?php echo esc_url( add_query_arg( 'view', 'test', $urls['tests'] ) ); ?>"><?php esc_html_e( 'Add a test', 'doctor-ak-portal' ); ?></a>
				</p>
			<?php else : ?>
				<input type="search" class="dak-lab-price-filter" placeholder="<?php esc_attr_e( 'Filter tests…', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Filter tests', 'doctor-ak-portal' ); ?>" oninput="var q=this.value.toLowerCase();this.parentNode.querySelectorAll('[data-lab-price-row]').forEach(function(r){r.hidden=q&&r.getAttribute('data-lab-price-row').indexOf(q)===-1;});">
				<div class="dak-lab-prices">
					<?php foreach ( $tests as $test ) : ?>
						<label class="dak-lab-price" data-lab-price-row="<?php echo esc_attr( mb_strtolower( $test['name'] ) ); ?>">
							<span class="dak-lab-price-name">
								<?php echo esc_html( $test['name'] ); ?>
								<?php if ( ! $test['active'] ) : ?>
									<em><?php esc_html_e( '(off)', 'doctor-ak-portal' ); ?></em>
								<?php endif; ?>
							</span>
							<span class="dak-lab-price-input">
								<span aria-hidden="true">PKR</span>
								<input type="number" name="prices[<?php echo esc_attr( $test['id'] ); ?>]" min="0" step="1" inputmode="numeric" value="<?php echo isset( $prices[ $test['id'] ] ) ? esc_attr( (float) $prices[ $test['id'] ] ) : ''; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: test name. */ __( 'Price for %s', 'doctor-ak-portal' ), $test['name'] ) ); ?>">
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="dak-lab-form-actions">
				<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Save lab', 'doctor-ak-portal' ); ?></button>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $urls['labs'] ); ?>"><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></a>
			</div>
		</form>

	<?php elseif ( 'tests' === $view ) : ?>

		<?php if ( empty( $tests ) ) : ?>
			<div class="dak-lab-setup">
				<h2><?php esc_html_e( 'Build your test catalog', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'Add the tests doctors can suggest (e.g. CBC, Lipid Profile, HbA1c), with the sample, how to prepare and when the report is ready. Then price them for each partner lab.', 'doctor-ak-portal' ); ?></p>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'test', $urls['tests'] ) ); ?>"><?php esc_html_e( '+ Add test', 'doctor-ak-portal' ); ?></a>
			</div>
		<?php else : ?>
			<div class="dak-data-table-wrap">
				<table class="dak-data-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Test', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sample', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Preparation', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Report', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><span class="dak-visually-hidden"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tests as $test ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Test', 'doctor-ak-portal' ); ?>"><strong><?php echo esc_html( $test['name'] ); ?></strong></td>
								<td data-label="<?php esc_attr_e( 'Sample', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $test['sample_type_label'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Preparation', 'doctor-ak-portal' ); ?>"><?php echo esc_html( '' !== $test['preparation'] ? $test['preparation'] : '—' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Report', 'doctor-ak-portal' ); ?>"><?php echo esc_html( '' !== $test['report_time'] ? $test['report_time'] : '—' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>"><span class="dak-status-pill <?php echo $test['active'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral'; ?>"><?php echo esc_html( $test['active'] ? __( 'Active', 'doctor-ak-portal' ) : __( 'Off', 'doctor-ak-portal' ) ); ?></span></td>
								<td class="dak-col-actions"><a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( add_query_arg( array( 'view' => 'test', 'id' => $test['id'] ), $urls['tests'] ) ); ?>"><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	<?php elseif ( 'test' === $view ) : ?>

		<form class="dak-form-card dak-form-card-padded dak-lab-form" method="post" action="<?php echo esc_url( $post_url ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $actions['test'] ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $dak_nonce ); ?>">
			<input type="hidden" name="id" value="<?php echo esc_attr( $test ? $test['id'] : 0 ); ?>">

			<h2><?php echo esc_html( $test ? __( 'Edit test', 'doctor-ak-portal' ) : __( 'Add test', 'doctor-ak-portal' ) ); ?></h2>

			<div class="dak-field-row">
				<div class="dak-field">
					<label for="dak-test-name"><?php esc_html_e( 'Test name', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-test-name" name="name" required value="<?php echo esc_attr( $test ? $test['name'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Complete Blood Count (CBC)', 'doctor-ak-portal' ); ?>">
				</div>
				<div class="dak-field">
					<label for="dak-test-sample"><?php esc_html_e( 'Sample', 'doctor-ak-portal' ); ?></label>
					<select id="dak-test-sample" name="sample_type">
						<?php foreach ( $sample_types as $dak_slug => $dak_label ) : ?>
							<option value="<?php echo esc_attr( $dak_slug ); ?>"<?php selected( $test ? $test['sample_type'] : 'blood', $dak_slug ); ?>><?php echo esc_html( $dak_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="dak-field-row">
				<div class="dak-field">
					<label for="dak-test-prep"><?php esc_html_e( 'Preparation (shown to the patient)', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-test-prep" name="preparation" value="<?php echo esc_attr( $test ? $test['preparation'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Fasting 10–12 hours, water allowed', 'doctor-ak-portal' ); ?>">
				</div>
				<div class="dak-field">
					<label for="dak-test-report"><?php esc_html_e( 'Report ready in', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-test-report" name="report_time" value="<?php echo esc_attr( $test ? $test['report_time'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Same day', 'doctor-ak-portal' ); ?>">
				</div>
			</div>

			<div class="dak-field">
				<label for="dak-test-keywords"><?php esc_html_e( 'Other names (optional)', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-test-keywords" name="keywords" value="<?php echo esc_attr( $test ? $test['keywords'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. CBC, blood count', 'doctor-ak-portal' ); ?>">
			</div>

			<label class="dak-lab-check">
				<input type="checkbox" name="active" value="1"<?php checked( ! $test || $test['active'] ); ?>>
				<span><?php esc_html_e( 'Active — doctors can suggest this test', 'doctor-ak-portal' ); ?></span>
			</label>

			<div class="dak-lab-form-actions">
				<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Save test', 'doctor-ak-portal' ); ?></button>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $urls['tests'] ); ?>"><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></a>
			</div>
		</form>

	<?php endif; ?>
</div>
