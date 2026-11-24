<?php
/**
 * Template: Dashboard → Reviews. Every review of every doctor (filter by
 * doctor, stars and status), publishing reviews sent from a review link,
 * hiding/showing any review, and each doctor's shareable review link.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $rows        Reviews on this page — see Admin_Reviews::section_html().
 * @var int    $total       Reviews matching the filters.
 * @var int    $page        Current page.
 * @var int    $pages       Page count.
 * @var array  $counts      status => count, all doctors.
 * @var array  $statuses    status => label.
 * @var array  $doctors     id => { id, name, is_public, link }.
 * @var array  $filters     { doctor_id, rating, status }.
 * @var int    $share_id    Doctor preselected in the share card.
 * @var string $notice      Result of the last action, or ''.
 * @var string $section_url This section, no filters.
 * @var string $return_url  This exact view (filters + page).
 * @var array  $filter_args Active filters, for links.
 * @var string $action_url  admin-post.php.
 * @var string $nonce       Nonce for the action forms.
 */

use DoctorAKPortal\Frontend\Admin_Reviews;
use DoctorAKPortal\Includes\Doctor_Reviews;
use DoctorAKPortal\Includes\Dashboard_Format;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_notices = array(
	Doctor_Reviews::STATUS_PUBLISHED => array( 'success', __( 'Review published. It now shows on the doctor’s profile.', 'doctor-ak-portal' ) ),
	Doctor_Reviews::STATUS_HIDDEN    => array( 'success', __( 'Review hidden. It no longer shows on the profile or counts toward the rating.', 'doctor-ak-portal' ) ),
	'link_reset'                     => array( 'success', __( 'New review link created. The old link no longer works.', 'doctor-ak-portal' ) ),
	'error'                          => array( 'error', __( 'That didn’t work. Please try again.', 'doctor-ak-portal' ) ),
);

$dak_pill = array(
	Doctor_Reviews::STATUS_PENDING   => 'dak-status-pill-is-pending',
	Doctor_Reviews::STATUS_PUBLISHED => 'dak-status-pill-is-active',
	Doctor_Reviews::STATUS_HIDDEN    => 'dak-status-pill-is-neutral',
);

$dak_all_count = array_sum( $counts );
$dak_share     = isset( $doctors[ $share_id ] ) ? $doctors[ $share_id ] : null;

$dak_stars = function ( $n ) {
	$out = '';
	for ( $i = 1; $i <= 5; $i++ ) {
		$out .= '<span class="dak-admin-review-star' . ( $i <= $n ? ' is-on' : '' ) . '" aria-hidden="true">&#9733;</span>';
	}
	return $out;
};

$dak_tab_url = function ( $status ) use ( $section_url, $filter_args ) {
	$args = $filter_args;
	unset( $args['status'] );

	if ( '' !== $status ) {
		$args['status'] = $status;
	}

	return add_query_arg( $args, $section_url );
};

$dak_page_url = function ( $n ) use ( $section_url, $filter_args ) {
	return add_query_arg( array_merge( $filter_args, $n > 1 ? array( 'rpage' => $n ) : array() ), $section_url );
};
?>
<div class="dak-list-page dak-admin-reviews">
	<div class="dak-page-head">
		<div>
			<h1><?php esc_html_e( 'Reviews', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Every review patients leave for your doctors. Approve the ones sent from a review link, and hide any that shouldn’t show.', 'doctor-ak-portal' ); ?></p>
		</div>
	</div>

	<?php if ( isset( $dak_notices[ $notice ] ) ) : ?>
		<div class="dak-alert dak-alert-<?php echo esc_attr( $dak_notices[ $notice ][0] ); ?>" role="status"><?php echo esc_html( $dak_notices[ $notice ][1] ); ?></div>
	<?php endif; ?>

	<section class="dak-results dak-review-share" aria-labelledby="dak-review-share-title" data-dak-review-share>
		<div class="dak-results-tools">
			<h2 class="dak-results-title" id="dak-review-share-title"><?php esc_html_e( 'Share a review link', 'doctor-ak-portal' ); ?></h2>
		</div>
		<div class="dak-results-body">
			<p class="dak-review-share-intro"><?php esc_html_e( 'Send a doctor’s link by WhatsApp, SMS or email. Anyone who opens it can rate the doctor without an account. Their review waits below for your approval before it appears on the profile.', 'doctor-ak-portal' ); ?></p>

			<?php if ( empty( $doctors ) ) : ?>
				<p class="dak-empty-state"><?php esc_html_e( 'No doctors yet.', 'doctor-ak-portal' ); ?></p>
			<?php else : ?>
				<div class="dak-review-share-row">
					<div class="dak-field dak-review-share-doctor">
						<label for="dak-review-share-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
						<select id="dak-review-share-doctor" data-dak-review-share-doctor>
							<option value="" data-link=""><?php esc_html_e( 'Choose a doctor…', 'doctor-ak-portal' ); ?></option>
							<?php foreach ( $doctors as $dak_doc ) : ?>
								<option value="<?php echo esc_attr( $dak_doc['id'] ); ?>" data-link="<?php echo esc_attr( $dak_doc['link'] ); ?>" data-name="<?php echo esc_attr( $dak_doc['name'] ); ?>" <?php selected( $share_id, $dak_doc['id'] ); ?>>
									<?php echo esc_html( $dak_doc['is_public'] ? $dak_doc['name'] : sprintf( /* translators: %s: doctor name. */ __( '%s (profile not public)', 'doctor-ak-portal' ), $dak_doc['name'] ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="dak-field dak-review-share-link">
						<label for="dak-review-share-link"><?php esc_html_e( 'Review link', 'doctor-ak-portal' ); ?></label>
						<input type="text" id="dak-review-share-link" readonly data-dak-review-share-link value="<?php echo esc_attr( $dak_share ? $dak_share['link'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Choose a doctor to get their link', 'doctor-ak-portal' ); ?>">
					</div>
				</div>

				<p class="dak-review-share-note" data-dak-review-share-note<?php echo $dak_share && ! $dak_share['is_public'] ? '' : ' hidden'; ?>><?php esc_html_e( 'This doctor’s profile isn’t public yet, so their link can’t be shared.', 'doctor-ak-portal' ); ?></p>

				<div class="dak-review-share-actions">
					<button type="button" class="dak-button dak-button-primary dak-button-sm" data-dak-review-copy<?php echo $dak_share && '' !== $dak_share['link'] ? '' : ' disabled'; ?>><?php esc_html_e( 'Copy link', 'doctor-ak-portal' ); ?></button>
					<a class="dak-button dak-button-secondary dak-button-sm<?php echo $dak_share && '' !== $dak_share['link'] ? '' : ' is-disabled'; ?>" data-dak-review-whatsapp href="#" target="_blank" rel="noopener noreferrer"
						data-message="<?php /* translators: 1: doctor name, 2: link. Keep %1$s and %2$s. */ esc_attr_e( 'How was your visit with Dr. %1$s? We’d love your feedback — it takes a minute: %2$s', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Share on WhatsApp', 'doctor-ak-portal' ); ?></a>
					<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="dak-review-share-reset" data-dak-review-reset data-confirm="<?php esc_attr_e( 'Create a new link for this doctor? The current link will stop working for anyone you’ve already sent it to.', 'doctor-ak-portal' ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Reviews::RESET_ACTION ); ?>">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
						<input type="hidden" name="return" value="<?php echo esc_url( $return_url ); ?>">
						<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $share_id ); ?>" data-dak-review-reset-doctor>
						<button type="submit" class="dak-button dak-button-secondary dak-button-sm"<?php echo $dak_share && '' !== $dak_share['link'] ? '' : ' disabled'; ?>><?php esc_html_e( 'Reset link', 'doctor-ak-portal' ); ?></button>
					</form>
					<span class="dak-review-share-status" role="status" aria-live="polite" data-dak-review-copy-status data-copied="<?php esc_attr_e( 'Link copied.', 'doctor-ak-portal' ); ?>" data-copy-failed="<?php esc_attr_e( 'Couldn’t copy. Select the link and copy it.', 'doctor-ak-portal' ); ?>"></span>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<nav class="dak-tabs dak-admin-reviews-tabs" aria-label="<?php esc_attr_e( 'Review status', 'doctor-ak-portal' ); ?>">
		<a class="dak-tab<?php echo '' === $filters['status'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $dak_tab_url( '' ) ); ?>"<?php echo '' === $filters['status'] ? ' aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'All', 'doctor-ak-portal' ); ?><span class="dak-admin-reviews-tab-count"><?php echo esc_html( number_format_i18n( $dak_all_count ) ); ?></span>
		</a>
		<?php foreach ( $statuses as $dak_slug => $dak_label ) : ?>
			<a class="dak-tab<?php echo $dak_slug === $filters['status'] ? ' is-active' : ''; ?><?php echo Doctor_Reviews::STATUS_PENDING === $dak_slug && $counts[ $dak_slug ] > 0 ? ' has-pending' : ''; ?>" href="<?php echo esc_url( $dak_tab_url( $dak_slug ) ); ?>"<?php echo $dak_slug === $filters['status'] ? ' aria-current="page"' : ''; ?>>
				<?php echo esc_html( $dak_label ); ?><span class="dak-admin-reviews-tab-count"><?php echo esc_html( number_format_i18n( $counts[ $dak_slug ] ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="dak-list-toolbar">
		<form method="get" action="<?php echo esc_url( $section_url ); ?>" class="dak-list-filters" aria-label="<?php esc_attr_e( 'Filter reviews', 'doctor-ak-portal' ); ?>">
			<input type="hidden" name="section" value="<?php echo esc_attr( Admin_Reviews::SECTION ); ?>">
			<?php if ( '' !== $filters['status'] ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>">
			<?php endif; ?>
			<div class="dak-field">
				<label for="dak-admin-reviews-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-reviews-doctor" name="doctor_id" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>">
					<option value="0"><?php esc_html_e( 'All doctors', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $doctors as $dak_doc ) : ?>
						<option value="<?php echo esc_attr( $dak_doc['id'] ); ?>" <?php selected( $filters['doctor_id'], $dak_doc['id'] ); ?>><?php echo esc_html( $dak_doc['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="dak-field">
				<label for="dak-admin-reviews-rating"><?php esc_html_e( 'Rating', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-reviews-rating" name="rating">
					<option value="0"><?php esc_html_e( 'All ratings', 'doctor-ak-portal' ); ?></option>
					<?php for ( $dak_r = 5; $dak_r >= 1; $dak_r-- ) : ?>
						<option value="<?php echo esc_attr( $dak_r ); ?>" <?php selected( $filters['rating'], $dak_r ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of stars. */ _n( '%d star', '%d stars', $dak_r, 'doctor-ak-portal' ), $dak_r ) ); ?></option>
					<?php endfor; ?>
				</select>
			</div>
			<div class="dak-list-filter-actions">
				<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
				<?php if ( $filters['doctor_id'] || $filters['rating'] ) : ?>
					<a class="dak-button dak-button-secondary" href="<?php echo esc_url( add_query_arg( '' !== $filters['status'] ? array( 'status' => $filters['status'] ) : array(), $section_url ) ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
	</div>

	<section class="dak-results" aria-labelledby="dak-admin-reviews-title">
		<div class="dak-results-tools">
			<h2 class="dak-results-title" id="dak-admin-reviews-title"><?php esc_html_e( 'Reviews', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span></h2>
		</div>

		<?php if ( empty( $rows ) ) : ?>
			<p class="dak-empty-state">
				<?php
				echo esc_html(
					Doctor_Reviews::STATUS_PENDING === $filters['status'] && ! $filters['doctor_id'] && ! $filters['rating']
						? __( 'Nothing waiting for approval.', 'doctor-ak-portal' )
						: ( $dak_all_count > 0 ? __( 'No reviews match these filters.', 'doctor-ak-portal' ) : __( 'No reviews yet. Share a doctor’s review link above to start collecting them.', 'doctor-ak-portal' ) )
				);
				?>
			</p>
		<?php else : ?>
			<div class="dak-data-table-wrap">
				<table class="dak-data-table dak-ui-table dak-admin-reviews-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Review', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
							<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $dak_row ) : ?>
							<?php
							$dak_actions = array();

							if ( Doctor_Reviews::STATUS_PUBLISHED !== $dak_row['status'] ) {
								$dak_actions[] = array( Doctor_Reviews::STATUS_PUBLISHED, Doctor_Reviews::STATUS_PENDING === $dak_row['status'] ? __( 'Publish', 'doctor-ak-portal' ) : __( 'Show', 'doctor-ak-portal' ), 'dak-button-primary' );
							}

							if ( Doctor_Reviews::STATUS_HIDDEN !== $dak_row['status'] ) {
								$dak_actions[] = array( Doctor_Reviews::STATUS_HIDDEN, __( 'Hide', 'doctor-ak-portal' ), 'dak-button-secondary' );
							}
							?>
							<tr id="dak-admin-review-<?php echo esc_attr( $dak_row['id'] ); ?>" class="dak-admin-review-row is-<?php echo esc_attr( $dak_row['status'] ); ?>" data-row>
								<td class="dak-col-primary dak-admin-review-main" data-label="<?php esc_attr_e( 'Review', 'doctor-ak-portal' ); ?>">
									<span class="dak-admin-review-stars" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rating 1-5. */ __( '%d out of 5 stars', 'doctor-ak-portal' ), $dak_row['rating'] ) ); ?>"><?php echo $dak_stars( $dak_row['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
									<?php if ( '' !== trim( $dak_row['comment'] ) ) : ?>
										<p class="dak-admin-review-comment"><?php echo esc_html( $dak_row['comment'] ); ?></p>
									<?php else : ?>
										<p class="dak-admin-review-comment is-empty"><?php esc_html_e( 'Rating only, no comment.', 'doctor-ak-portal' ); ?></p>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-strong"><?php echo esc_html( $dak_row['doctor_name'] ); ?></span>
								</td>
								<td data-label="<?php esc_attr_e( 'From', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-cell-strong"><?php echo esc_html( $dak_row['reviewer'] ); ?></span>
										<?php if ( '' !== $dak_row['contact'] ) : ?>
											<span class="dak-cell-sub dak-cell-email"><?php echo esc_html( $dak_row['contact'] ); ?></span>
										<?php endif; ?>
										<span class="dak-admin-review-source<?php echo $dak_row['from_link'] ? ' is-link' : ' is-verified'; ?>">
											<?php echo esc_html( $dak_row['from_link'] ? __( 'Via review link', 'doctor-ak-portal' ) : __( 'Patient with a visit', 'doctor-ak-portal' ) ); ?>
										</span>
									</span>
								</td>
								<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Date', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-cell-strong is-tabular"><?php echo esc_html( Dashboard_Format::date( $dak_row['created_at'] ) ); ?></span>
										<?php if ( $dak_row['edited'] ) : ?>
											<span class="dak-cell-sub"><?php esc_html_e( 'Edited', 'doctor-ak-portal' ); ?></span>
										<?php endif; ?>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
									<span class="dak-status-pill <?php echo esc_attr( isset( $dak_pill[ $dak_row['status'] ] ) ? $dak_pill[ $dak_row['status'] ] : 'dak-status-pill-is-neutral' ); ?>"><?php echo esc_html( isset( $statuses[ $dak_row['status'] ] ) ? $statuses[ $dak_row['status'] ] : $dak_row['status'] ); ?></span>
								</td>
								<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
									<div class="dak-row-actions dak-admin-review-actions">
										<?php foreach ( $dak_actions as $dak_action ) : ?>
											<form method="post" action="<?php echo esc_url( $action_url ); ?>">
												<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Reviews::MODERATE_ACTION ); ?>">
												<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
												<input type="hidden" name="return" value="<?php echo esc_url( $return_url ); ?>">
												<input type="hidden" name="review_id" value="<?php echo esc_attr( $dak_row['id'] ); ?>">
												<input type="hidden" name="status" value="<?php echo esc_attr( $dak_action[0] ); ?>">
												<button type="submit" class="dak-button <?php echo esc_attr( $dak_action[2] ); ?> dak-button-sm" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: action, 2: reviewer name. */ __( '%1$s review from %2$s', 'doctor-ak-portal' ), $dak_action[1], $dak_row['reviewer'] ) ); ?>"><?php echo esc_html( $dak_action[1] ); ?></button>
											</form>
										<?php endforeach; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $pages > 1 ) : ?>
				<nav class="dak-admin-reviews-pager" aria-label="<?php esc_attr_e( 'Review pages', 'doctor-ak-portal' ); ?>">
					<?php if ( $page > 1 ) : ?>
						<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $dak_page_url( $page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
					<span><?php echo esc_html( sprintf( /* translators: 1: current page, 2: page count. */ __( 'Page %1$d of %2$d', 'doctor-ak-portal' ), $page, $pages ) ); ?></span>
					<?php if ( $page < $pages ) : ?>
						<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $dak_page_url( $page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</section>
</div>
