<?php
/**
 * Template: public clinic page for the [clinic_profile_view] shortcode —
 * the clinic's essentials, then every doctor practising there with what
 * applies at this clinic.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array|null $clinic        Clinic_Public_Data::locations() row, or null when the clinic_id is missing/unknown.
 * @var array      $doctors       Clinic_Profile_View::doctors_at() rows.
 * @var array      $specialties   Lowercase key => label, for the specialty filter.
 * @var bool       $show_filters  Whether to show doctor search + specialty filter.
 * @var array|null $booking_line  { display, href } or null.
 * @var string     $directory_url Clinics directory URL, or ''.
 * @var string     $home_url      Home page URL.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoctorAKPortal\Frontend\Clinic_Public_Data;
use DoctorAKPortal\Frontend\Public_Pages;

$dak_cp_count = count( $doctors );
?>
<div class="dak-pub dak-pub-clinic">
	<div class="pub-wrap pub-page">
		<nav class="pub-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'doctor-ak-portal' ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( $home_url ); ?>"><?php esc_html_e( 'Home', 'doctor-ak-portal' ); ?></a></li>
				<?php if ( $directory_url ) : ?>
					<li><a href="<?php echo esc_url( $directory_url ); ?>" data-clinics-back><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></a></li>
				<?php endif; ?>
				<li><span aria-current="page"><?php echo esc_html( $clinic ? $clinic['name'] : __( 'Clinic not found', 'doctor-ak-portal' ) ); ?></span></li>
			</ol>
		</nav>

		<?php if ( ! $clinic ) : ?>
			<div class="pub-state">
				<span class="pub-state-icon"><?php echo Public_Pages::icon( 'building' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<h1 class="pub-h2"><?php esc_html_e( 'We couldn’t find that clinic', 'doctor-ak-portal' ); ?></h1>
				<p><?php esc_html_e( 'The link may be out of date, or this clinic is no longer listed. You can browse all of our current clinics instead.', 'doctor-ak-portal' ); ?></p>
				<div class="pub-state-actions">
					<?php if ( $directory_url ) : ?>
						<a class="pub-btn pub-btn-primary" href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Browse clinics', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
					<?php if ( $booking_line ) : ?>
						<a class="pub-btn pub-btn-secondary" href="<?php echo esc_attr( $booking_line['href'] ); ?>"><?php echo Public_Pages::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( sprintf( /* translators: %s: phone number. */ __( 'Call %s', 'doctor-ak-portal' ), $booking_line['display'] ) ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<header class="pub-card pub-clinic-hero">
				<div class="pub-clinic-hero-main">
					<h1 class="pub-h1"><?php echo esc_html( $clinic['name'] ); ?></h1>
					<?php if ( '' !== $clinic['place'] ) : ?>
						<p class="pub-clinic-place"><?php echo esc_html( $clinic['place'] ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $clinic['address_short'] ) : ?>
						<p class="pub-meta">
							<span class="pub-icon"><?php echo Public_Pages::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span><?php echo esc_html( $clinic['address_short'] ); ?></span>
						</p>
					<?php endif; ?>

					<div class="pub-clinic-facts">
						<p class="pub-meta">
							<span class="pub-icon"><?php echo Public_Pages::icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span>
								<?php
								if ( $dak_cp_count > 0 ) {
									/* translators: %s: number of doctors. */
									echo esc_html( sprintf( _n( '%s doctor listed', '%s doctors listed', $dak_cp_count, 'doctor-ak-portal' ), number_format_i18n( $dak_cp_count ) ) );
								} else {
									esc_html_e( 'No doctors listed', 'doctor-ak-portal' );
								}
								?>
							</span>
						</p>
						<?php if ( '' !== $clinic['phone_href'] ) : ?>
							<p class="pub-meta">
								<span class="pub-icon"><?php echo Public_Pages::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
								<span>
									<?php echo esc_html( 'central' === $clinic['phone_kind'] ? __( 'Booking line:', 'doctor-ak-portal' ) : __( 'Clinic phone:', 'doctor-ak-portal' ) ); ?>
									<a class="pub-phone-link" href="<?php echo esc_attr( $clinic['phone_href'] ); ?>"><?php echo esc_html( Clinic_Public_Data::format_phone( $clinic['phone'] ) ); ?></a>
								</span>
							</p>
						<?php endif; ?>
						<?php if ( '' !== $clinic['contact_email'] ) : ?>
							<p class="pub-meta">
								<span class="pub-icon"><?php echo Public_Pages::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
								<a class="pub-phone-link" href="mailto:<?php echo esc_attr( $clinic['contact_email'] ); ?>"><?php echo esc_html( $clinic['contact_email'] ); ?></a>
							</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="pub-clinic-hero-actions">
					<?php if ( '' !== $clinic['map_url'] ) : ?>
						<a class="pub-btn pub-btn-secondary" href="<?php echo esc_url( $clinic['map_url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo Public_Pages::icon( 'map' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Get directions', 'doctor-ak-portal' ); ?>
							<span class="pub-sr-only"><?php esc_html_e( '(opens Google Maps in a new tab)', 'doctor-ak-portal' ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( '' !== $clinic['phone_href'] ) : ?>
						<a class="pub-btn pub-btn-secondary" href="<?php echo esc_attr( $clinic['phone_href'] ); ?>">
							<?php echo Public_Pages::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo esc_html( 'central' === $clinic['phone_kind'] ? __( 'Call booking line', 'doctor-ak-portal' ) : __( 'Call clinic', 'doctor-ak-portal' ) ); ?>
						</a>
					<?php endif; ?>
				</div>
			</header>

			<section aria-labelledby="dak-cp-doctors-title">
				<div class="pub-section-head">
					<div>
						<h2 class="pub-h2" id="dak-cp-doctors-title"><?php esc_html_e( 'Doctors at this clinic', 'doctor-ak-portal' ); ?></h2>
						<?php if ( $dak_cp_count > 0 ) : ?>
							<p class="pub-muted pub-small"><?php esc_html_e( 'Days, hours and fees shown are for this clinic. Exact appointment times are shown when you book.', 'doctor-ak-portal' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( 0 === $dak_cp_count ) : ?>
					<div class="pub-state">
						<span class="pub-state-icon"><?php echo Public_Pages::icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<h3 class="pub-h3"><?php esc_html_e( 'No doctors are listed at this clinic', 'doctor-ak-portal' ); ?></h3>
						<p><?php esc_html_e( 'Appointments can’t be booked online at this location right now. Browse our other clinics to find a doctor, or call us and we’ll help you book.', 'doctor-ak-portal' ); ?></p>
						<div class="pub-state-actions">
							<?php if ( $directory_url ) : ?>
								<a class="pub-btn pub-btn-primary" href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Browse other clinics', 'doctor-ak-portal' ); ?></a>
							<?php endif; ?>
							<?php if ( $booking_line ) : ?>
								<a class="pub-btn pub-btn-secondary" href="<?php echo esc_attr( $booking_line['href'] ); ?>"><?php echo Public_Pages::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( sprintf( /* translators: %s: phone number. */ __( 'Booking help: %s', 'doctor-ak-portal' ), $booking_line['display'] ) ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				<?php else : ?>
					<?php if ( $show_filters ) : ?>
						<div class="pub-card pub-filters pub-doctor-tools" role="search" aria-label="<?php esc_attr_e( 'Filter doctors', 'doctor-ak-portal' ); ?>" data-doctor-filter>
							<div class="pub-field">
								<label for="dak-cp-q"><?php esc_html_e( 'Doctor name', 'doctor-ak-portal' ); ?></label>
								<div class="pub-input-icon">
									<span class="pub-icon"><?php echo Public_Pages::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									<input class="pub-input" type="search" id="dak-cp-q" placeholder="<?php esc_attr_e( 'Search by name', 'doctor-ak-portal' ); ?>" autocomplete="off">
								</div>
							</div>
							<?php if ( count( $specialties ) > 1 ) : ?>
								<div class="pub-field">
									<label for="dak-cp-specialty"><?php esc_html_e( 'Specialty', 'doctor-ak-portal' ); ?></label>
									<select class="pub-select" id="dak-cp-specialty">
										<option value=""><?php esc_html_e( 'All specialties', 'doctor-ak-portal' ); ?></option>
										<?php foreach ( $specialties as $dak_cp_key => $dak_cp_label ) : ?>
											<option value="<?php echo esc_attr( $dak_cp_key ); ?>"><?php echo esc_html( $dak_cp_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endif; ?>
						</div>
						<div class="pub-results-bar">
							<p class="pub-results-count" id="dak-cp-count" role="status" aria-live="polite" data-total="<?php echo esc_attr( $dak_cp_count ); ?>" data-one="<?php /* translators: 1: shown, 2: total. */ echo esc_attr( __( 'Showing %1$s of %2$s doctors', 'doctor-ak-portal' ) ); ?>" data-all="<?php /* translators: %s: number of doctors. */ echo esc_attr( _n( '%s doctor', '%s doctors', $dak_cp_count, 'doctor-ak-portal' ) ); ?>">
								<?php /* translators: %s: number of doctors. */ echo esc_html( sprintf( _n( '%s doctor', '%s doctors', $dak_cp_count, 'doctor-ak-portal' ), number_format_i18n( $dak_cp_count ) ) ); ?>
							</p>
						</div>
					<?php endif; ?>

					<div class="pub-doctor-list<?php echo 1 === $dak_cp_count ? ' is-single' : ''; ?>" id="dak-cp-list">
						<?php foreach ( $doctors as $dak_cp_doctor ) : ?>
							<?php $dak_cp_title_id = 'dak-cp-doctor-' . (int) $dak_cp_doctor['id']; ?>
							<article
								class="pub-card pub-doctor"
								aria-labelledby="<?php echo esc_attr( $dak_cp_title_id ); ?>"
								data-doctor
								data-name="<?php echo esc_attr( mb_strtolower( $dak_cp_doctor['name'] ) ); ?>"
								data-specialties="<?php echo esc_attr( mb_strtolower( implode( '|', $dak_cp_doctor['specialties'] ) ) ); ?>"
							>
								<span class="pub-avatar" aria-hidden="true">
									<?php if ( '' !== $dak_cp_doctor['photo_url'] ) : ?>
										<img src="<?php echo esc_url( $dak_cp_doctor['photo_url'] ); ?>" alt="" loading="lazy">
									<?php else : ?>
										<?php echo esc_html( $dak_cp_doctor['initials'] ); ?>
									<?php endif; ?>
								</span>

								<div class="pub-doctor-main">
									<div class="pub-doctor-name">
										<h3 id="<?php echo esc_attr( $dak_cp_title_id ); ?>"><a href="<?php echo esc_url( $dak_cp_doctor['profile_url'] ); ?>"><?php echo esc_html( $dak_cp_doctor['name'] ); ?></a></h3>
										<?php if ( ! empty( $dak_cp_doctor['specialties'] ) || $dak_cp_doctor['years'] > 0 ) : ?>
											<div class="pub-doctor-tags">
												<?php foreach ( $dak_cp_doctor['specialties'] as $dak_cp_specialty ) : ?>
													<span class="pub-badge pub-badge-primary"><?php echo esc_html( $dak_cp_specialty ); ?></span>
												<?php endforeach; ?>
												<?php if ( $dak_cp_doctor['years'] > 0 ) : ?>
													<span class="pub-muted pub-small">
														<?php /* translators: %d: years of experience. */ echo esc_html( sprintf( _n( '%d year of experience', '%d years of experience', $dak_cp_doctor['years'], 'doctor-ak-portal' ), $dak_cp_doctor['years'] ) ); ?>
													</span>
												<?php endif; ?>
											</div>
										<?php endif; ?>
									</div>

									<div class="pub-doctor-here">
										<span class="pub-doctor-here-title"><?php esc_html_e( 'At this clinic', 'doctor-ak-portal' ); ?></span>
										<?php if ( ! empty( $dak_cp_doctor['schedule'] ) ) : ?>
											<?php foreach ( $dak_cp_doctor['schedule'] as $dak_cp_line ) : ?>
												<p class="pub-meta">
													<span class="pub-icon"><?php echo Public_Pages::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
													<span><?php echo esc_html( $dak_cp_line ); ?></span>
												</p>
											<?php endforeach; ?>
										<?php else : ?>
											<p class="pub-meta">
												<span class="pub-icon"><?php echo Public_Pages::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
												<span><?php esc_html_e( 'Check appointment times when you book', 'doctor-ak-portal' ); ?></span>
											</p>
										<?php endif; ?>
										<?php if ( $dak_cp_doctor['fee_from'] > 0 ) : ?>
											<p class="pub-meta">
												<span class="pub-icon"><?php echo Public_Pages::icon( 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
												<span><?php /* translators: %s: amount in PKR. */ echo esc_html( sprintf( __( 'Fees from PKR %s', 'doctor-ak-portal' ), number_format_i18n( $dak_cp_doctor['fee_from'] ) ) ); ?></span>
											</p>
										<?php endif; ?>
									</div>

									<?php if ( ! empty( $dak_cp_doctor['other_locations'] ) ) : ?>
										<details class="pub-disclosure">
											<summary>
												<?php
												$dak_cp_other = count( $dak_cp_doctor['other_locations'] );
												/* translators: %s: number of other locations. */
												echo esc_html( sprintf( _n( 'Also practises at %s other location', 'Also practises at %s other locations', $dak_cp_other, 'doctor-ak-portal' ), number_format_i18n( $dak_cp_other ) ) );
												?>
											</summary>
											<ul>
												<?php foreach ( $dak_cp_doctor['other_locations'] as $dak_cp_location ) : ?>
													<li><?php echo esc_html( $dak_cp_location ); ?></li>
												<?php endforeach; ?>
											</ul>
										</details>
									<?php endif; ?>

									<div class="pub-doctor-actions">
										<?php if ( '' !== $dak_cp_doctor['book_url'] ) : ?>
											<a class="pub-btn pub-btn-primary" href="<?php echo esc_url( $dak_cp_doctor['book_url'] ); ?>" aria-describedby="<?php echo esc_attr( $dak_cp_title_id ); ?>">
												<?php echo Public_Pages::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												<?php esc_html_e( 'Book clinic visit', 'doctor-ak-portal' ); ?>
											</a>
										<?php endif; ?>
										<a class="pub-btn pub-btn-secondary" href="<?php echo esc_url( $dak_cp_doctor['profile_url'] ); ?>" aria-describedby="<?php echo esc_attr( $dak_cp_title_id ); ?>"><?php esc_html_e( 'View profile', 'doctor-ak-portal' ); ?></a>
										<?php if ( '' !== $dak_cp_doctor['video_url'] ) : ?>
											<a class="pub-btn pub-btn-ghost" href="<?php echo esc_url( $dak_cp_doctor['video_url'] ); ?>" aria-describedby="<?php echo esc_attr( $dak_cp_title_id ); ?>">
												<?php echo Public_Pages::icon( 'video' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												<?php esc_html_e( 'Online video visit', 'doctor-ak-portal' ); ?>
											</a>
										<?php endif; ?>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>

					<?php if ( $show_filters ) : ?>
						<div class="pub-state dak-hidden" id="dak-cp-empty">
							<span class="pub-state-icon"><?php echo Public_Pages::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<h3 class="pub-h3"><?php esc_html_e( 'No doctors match', 'doctor-ak-portal' ); ?></h3>
							<p><?php esc_html_e( 'Try another name or specialty.', 'doctor-ak-portal' ); ?></p>
							<div class="pub-state-actions">
								<button type="button" class="pub-btn pub-btn-primary" id="dak-cp-reset"><?php esc_html_e( 'Show all doctors', 'doctor-ak-portal' ); ?></button>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</div>
