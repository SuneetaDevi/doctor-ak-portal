<?php
/**
 * Template: Public service detail page for the [service_profile_view]
 * shortcode — one service NAME, laid out like the doctor profile: a header
 * with the service's photo, price and key facts; pinned section tabs;
 * "Doctors & prices" (grouped by clinic — pick one to book); About; a
 * request form for services that need no doctor; related services; and a
 * booking card beside it (a fixed bottom bar on phones).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array|null $group {
 *     Null if no valid/active service_id was given.
 *
 *     @type string $name          Service name.
 *     @type string $description   Full description — may contain rich-text HTML (bold/italic/lists/links) from the admin's formatting toolbar, or '' if none set on any doctor's row.
 *     @type string $image_url     Service image URL, or '' if none uploaded on any doctor's row.
 *     @type string $price_label      Overall price range across every doctor, e.g. "PKR 5,000" or "From PKR 5,000".
 *     @type int    $duration         Longest set duration in minutes across doctors (0 = not set).
 *     @type int    $max_discount     Biggest discount running now on any doctor's row (0 = none).
 *     @type string $category         Service category slug, or ''.
 *     @type string $category_label   Service category name, or ''.
 *     @type bool   $requires_doctor  Whether patients pick a doctor/time slot to book this (Services::decode_row()), or just submit a request (see the "request this service" form below when false).
 *     @type int    $service_id       The specific Services row this page was reached via (?service_id=) — what the request form posts.
 *     @type int    $doctor_count     Distinct doctors offering it.
 *     @type array  $doctor_offers One entry per doctor offering this service, cheapest first — only used here for the "N doctors" figures; the "Doctors & prices" list itself is $clinic_groups below.
 *     @type array  $clinic_groups The "Doctors & prices" list itself, one entry per physical clinic, alphabetical by clinic name ("Other Locations" last) — see Service_Profile_View::build_clinic_groups() {
 *         @type string $label   Clinic name (or "Other Locations" for the catch-all group).
 *         @type string $meta    Address/area/city line, or '' for the catch-all group.
 *         @type array  $doctors Doctors offering this service at this clinic, cheapest first — { doctor_id, doctor_name, doctor_avatar_url, doctor_profile_url, price, price_label, discount_percent, was_price_label, specialty, next_available, booking_url }.
 *     }
 *     @type array  $related       Other services in the same category — { name, image_url, price_label, discount_percent, provider_count, url } (Service_Profile_View::related_services()).
 * }
 * @var string $directory_url "Services" breadcrumb link.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_sv_icons = array(
	'pulse'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 16.2S3.8 12.4 3.8 8.1A3.1 3.1 0 0 1 10 6.3a3.1 3.1 0 0 1 6.2 1.8c0 4.3-6.2 8.1-6.2 8.1z"/><path d="M6.5 10h2l1-2 1.5 4 1-2h1.5"/></svg>',
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'person'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'tag'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l6.5 6.5-7.5 7.5-6.5-6.5V3.5z"/><circle cx="6.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>',
	'clock'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6v4l3 2"/></svg>',
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'info'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 9v4.5"/><path d="M10 6.5h.01"/></svg>',
	'check'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>',
	'arrow'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 10h12"/><path d="M11 5.5l4.5 4.5-4.5 4.5"/></svg>',
	'send'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 2.5 9 11"/><path d="M17.5 2.5 12 17.5l-3-6.5-6.5-3z"/></svg>',
	'grid'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="5.5" height="5.5" rx="1.2"/><rect x="11.5" y="3" width="5.5" height="5.5" rx="1.2"/><rect x="3" y="11.5" width="5.5" height="5.5" rx="1.2"/><rect x="11.5" y="11.5" width="5.5" height="5.5" rx="1.2"/></svg>',
);
?>
<div class="dak-portal dak-directory dak-profile-view dak-service-view">
	<?php if ( ! $group ) : ?>
		<div class="dak-profile-unavailable">
			<span class="dak-profile-unavailable-icon" aria-hidden="true"><?php echo $dak_sv_icons['pulse']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h1><?php esc_html_e( 'This service isn\'t available', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'The link you followed may be out of date, or this service may no longer be offered.', 'doctor-ak-portal' ); ?></p>
			<?php if ( $directory_url ) : ?>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Browse All Services', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<?php
		$dak_needs_doctor  = ! empty( $group['requires_doctor'] );
		$dak_clinic_groups = $dak_needs_doctor ? (array) $group['clinic_groups'] : array();
		$dak_has_doctors   = ! empty( $dak_clinic_groups );
		$dak_related       = isset( $group['related'] ) ? (array) $group['related'] : array();
		$dak_category      = isset( $group['category_label'] ) ? (string) $group['category_label'] : '';
		$dak_max_discount  = isset( $group['max_discount'] ) ? (int) $group['max_discount'] : 0;
		$dak_other_label   = __( 'Other Locations', 'doctor-ak-portal' );

		// Real clinics (not the "Other Locations" catch-all) and doctor rows.
		$dak_clinic_count = 0;
		$dak_row_count    = 0;

		foreach ( $dak_clinic_groups as $dak_clinic_group ) {
			$dak_clinic_count += $dak_other_label === $dak_clinic_group['label'] ? 0 : 1;
			$dak_row_count    += count( $dak_clinic_group['doctors'] );
		}

		$dak_duration_label = '';

		if ( ! empty( $group['duration'] ) ) {
			$dak_minutes        = (int) $group['duration'];
			$dak_duration_label = $dak_minutes < 60
				/* translators: %d: minutes. */
				? sprintf( __( '%d min', 'doctor-ak-portal' ), $dak_minutes )
				/* translators: 1: hours, 2: minutes. */
				: ( $dak_minutes % 60 ? sprintf( __( '%1$d hr %2$d min', 'doctor-ak-portal' ), intdiv( $dak_minutes, 60 ), $dak_minutes % 60 ) : sprintf( __( '%d hr', 'doctor-ak-portal' ), intdiv( $dak_minutes, 60 ) ) );
		}

		// The header shows a short plain-text intro; the full description
		// (with its formatting) is in About.
		$dak_plain_text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( str_replace( '<', ' <', (string) $group['description'] ) ), ENT_QUOTES, 'UTF-8' ) ) );
		$dak_intro      = $dak_plain_text;
		$dak_intro_cut  = false;

		if ( mb_strlen( $dak_intro ) > 200 ) {
			$dak_intro     = mb_substr( $dak_intro, 0, 200 );
			$dak_space     = mb_strrpos( $dak_intro, ' ' );
			$dak_intro     = rtrim( false !== $dak_space ? mb_substr( $dak_intro, 0, $dak_space ) : $dak_intro, " ,.;:" ) . '…';
			$dak_intro_cut = true;
		}

		$dak_category_url = '' !== $dak_category && $directory_url ? add_query_arg( 'category', $group['category'], $directory_url ) : '';

		// Where the main button goes before a doctor is chosen.
		if ( $dak_needs_doctor ) {
			$dak_cta_href  = $dak_has_doctors ? '#dak-service-doctors' : '';
			$dak_cta_label = __( 'Choose a doctor', 'doctor-ak-portal' );
		} else {
			$dak_cta_href  = '#dak-service-request';
			$dak_cta_label = __( 'Request this service', 'doctor-ak-portal' );
		}
		?>

		<?php if ( $directory_url ) : ?>
			<nav class="dak-profile-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'doctor-ak-portal' ); ?>">
				<a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?></a>
				<?php if ( '' !== $dak_category_url ) : ?>
					<span aria-hidden="true">›</span>
					<a href="<?php echo esc_url( $dak_category_url ); ?>"><?php echo esc_html( $dak_category ); ?></a>
				<?php endif; ?>
				<span aria-hidden="true">›</span>
				<span><?php echo esc_html( $group['name'] ); ?></span>
			</nav>
		<?php endif; ?>

		<div class="dak-profile-layout">
			<div class="dak-profile-main">
				<section class="dak-dp-hero" id="dak-service-overview" aria-labelledby="dak-sv-name">
					<div class="dak-dp-identity">
						<span class="dak-dp-photo dak-sv-photo">
							<?php if ( $group['image_url'] ) : ?>
								<img src="<?php echo esc_url( $group['image_url'] ); ?>" alt="" width="128" height="128">
							<?php else : ?>
								<?php echo $dak_sv_icons['pulse']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</span>

						<div class="dak-dp-identity-text">
							<h1 class="dak-dp-name" id="dak-sv-name"><?php echo esc_html( $group['name'] ); ?></h1>

							<?php if ( '' !== $dak_category || $dak_max_discount > 0 ) : ?>
								<div class="dak-specialty-tags dak-dp-specialties">
									<?php if ( '' !== $dak_category ) : ?>
										<span class="dak-specialty-tag"><?php echo esc_html( $dak_category ); ?></span>
									<?php endif; ?>
									<?php if ( $dak_max_discount > 0 ) : ?>
										<span class="dak-sv-offer-tag">
											<?php echo $dak_sv_icons['tag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php
											/* translators: %d: discount percentage. */
											echo esc_html( sprintf( __( 'Up to %d%% off now', 'doctor-ak-portal' ), $dak_max_discount ) );
											?>
										</span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( '' !== $dak_intro ) : ?>
						<p class="dak-dp-intro"><?php echo esc_html( $dak_intro ); ?><?php if ( $dak_intro_cut ) : ?> <a class="dak-dp-more" href="#dak-service-about"><?php esc_html_e( 'Read more', 'doctor-ak-portal' ); ?></a><?php endif; ?></p>
					<?php endif; ?>

					<ul class="dak-dp-facts">
						<li class="dak-dp-fact dak-dp-fact--static">
							<span class="dak-dp-fact-icon" aria-hidden="true"><?php echo $dak_sv_icons['tag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="dak-dp-fact-text">
								<strong><?php echo esc_html( $group['price_label'] ); ?></strong>
								<span><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></span>
							</span>
						</li>

						<?php if ( '' !== $dak_duration_label ) : ?>
							<li class="dak-dp-fact dak-dp-fact--static">
								<span class="dak-dp-fact-icon" aria-hidden="true"><?php echo $dak_sv_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="dak-dp-fact-text">
									<strong><?php echo esc_html( $dak_duration_label ); ?></strong>
									<span><?php esc_html_e( 'Duration', 'doctor-ak-portal' ); ?></span>
								</span>
							</li>
						<?php endif; ?>

						<?php if ( $dak_has_doctors ) : ?>
							<li class="dak-dp-fact">
								<a class="dak-dp-fact-link" href="#dak-service-doctors">
									<span class="dak-dp-fact-icon" aria-hidden="true"><?php echo $dak_sv_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span class="dak-dp-fact-text">
										<strong><?php echo esc_html( number_format_i18n( $group['doctor_count'] ) ); ?></strong>
										<span><?php echo esc_html( _n( 'Doctor', 'Doctors', $group['doctor_count'], 'doctor-ak-portal' ) ); ?></span>
									</span>
								</a>
							</li>
						<?php endif; ?>

						<?php if ( $dak_clinic_count > 0 ) : ?>
							<li class="dak-dp-fact">
								<a class="dak-dp-fact-link" href="#dak-service-doctors">
									<span class="dak-dp-fact-icon" aria-hidden="true"><?php echo $dak_sv_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span class="dak-dp-fact-text">
										<strong><?php echo esc_html( number_format_i18n( $dak_clinic_count ) ); ?></strong>
										<span><?php echo esc_html( _n( 'Clinic location', 'Clinic locations', $dak_clinic_count, 'doctor-ak-portal' ) ); ?></span>
									</span>
								</a>
							</li>
						<?php endif; ?>

						<?php if ( ! $dak_needs_doctor ) : ?>
							<li class="dak-dp-fact dak-dp-fact--static">
								<span class="dak-dp-fact-icon" aria-hidden="true"><?php echo $dak_sv_icons['send']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="dak-dp-fact-text">
									<strong><?php esc_html_e( 'No doctor needed', 'doctor-ak-portal' ); ?></strong>
									<span><?php esc_html_e( 'Request it, we’ll call you', 'doctor-ak-portal' ); ?></span>
								</span>
							</li>
						<?php endif; ?>
					</ul>

					<?php // Shown only while the booking card isn't beside the header (tablet); phones use the bottom bar. ?>
					<?php if ( '' !== $dak_cta_href ) : ?>
						<div class="dak-dp-hero-actions">
							<a class="dak-button dak-button-primary dak-profile-cta" id="dak-service-header-cta" href="<?php echo esc_attr( $dak_cta_href ); ?>"><?php echo esc_html( $dak_cta_label ); ?></a>
						</div>
					<?php endif; ?>
				</section>

				<nav class="dak-dp-tabs" aria-label="<?php esc_attr_e( 'Service sections', 'doctor-ak-portal' ); ?>" data-dak-dp-tabs>
					<?php if ( $dak_has_doctors ) : ?>
						<a href="#dak-service-doctors">
							<?php esc_html_e( 'Doctors & prices', 'doctor-ak-portal' ); ?>
							<span class="dak-dp-tab-count"><?php echo esc_html( number_format_i18n( $group['doctor_count'] ) ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( ! $dak_needs_doctor ) : ?>
						<a href="#dak-service-request"><?php esc_html_e( 'Request', 'doctor-ak-portal' ); ?></a>
					<?php endif; ?>
					<a href="#dak-service-about"><?php esc_html_e( 'About', 'doctor-ak-portal' ); ?></a>
					<?php if ( ! empty( $dak_related ) ) : ?>
						<a href="#dak-service-related">
							<?php esc_html_e( 'Related', 'doctor-ak-portal' ); ?>
							<span class="dak-dp-tab-count"><?php echo esc_html( number_format_i18n( count( $dak_related ) ) ); ?></span>
						</a>
					<?php endif; ?>
				</nav>

				<?php if ( $dak_has_doctors ) : ?>
					<section class="dak-profile-card dak-dp-section" id="dak-service-doctors" aria-labelledby="dak-sv-doctors-title">
						<header class="dak-dp-section-head">
							<h2 class="dak-dp-section-title" id="dak-sv-doctors-title">
								<span class="dak-dp-section-icon" aria-hidden="true"><?php echo $dak_sv_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php esc_html_e( 'Doctors & prices', 'doctor-ak-portal' ); ?>
							</h2>

							<?php if ( $dak_row_count > 1 ) : ?>
								<div class="dak-sv-sort">
									<label for="dak-service-filter-sort"><?php esc_html_e( 'Sort', 'doctor-ak-portal' ); ?></label>
									<select id="dak-service-filter-sort">
										<option value="price-asc"><?php esc_html_e( 'Price: low to high', 'doctor-ak-portal' ); ?></option>
										<option value="price-desc"><?php esc_html_e( 'Price: high to low', 'doctor-ak-portal' ); ?></option>
										<option value="name"><?php esc_html_e( 'Doctor name (A–Z)', 'doctor-ak-portal' ); ?></option>
									</select>
								</div>
							<?php endif; ?>
						</header>
						<p class="dak-dp-section-lead"><?php esc_html_e( 'Prices can differ by doctor and clinic. Pick one to book — you’ll choose a date and time next.', 'doctor-ak-portal' ); ?></p>

						<?php if ( count( $dak_clinic_groups ) > 1 ) : ?>
							<div class="dak-sv-clinic-chips" role="group" aria-label="<?php esc_attr_e( 'Show clinic', 'doctor-ak-portal' ); ?>">
								<button type="button" class="dak-sv-chip is-active" data-sv-clinic-filter="" aria-pressed="true">
									<?php esc_html_e( 'All clinics', 'doctor-ak-portal' ); ?>
								</button>
								<?php foreach ( $dak_clinic_groups as $dak_group_index => $dak_clinic_group ) : ?>
									<button type="button" class="dak-sv-chip" data-sv-clinic-filter="<?php echo esc_attr( $dak_group_index ); ?>" aria-pressed="false">
										<?php echo esc_html( $dak_clinic_group['label'] ); ?>
										<span class="dak-sv-chip-count"><?php echo esc_html( number_format_i18n( count( $dak_clinic_group['doctors'] ) ) ); ?></span>
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="dak-sv-clinics" id="dak-service-doctor-offers">
							<?php $dak_offer_index = 0; ?>
							<?php foreach ( $dak_clinic_groups as $dak_group_index => $dak_clinic_group ) : ?>
								<?php $dak_is_other = $dak_other_label === $dak_clinic_group['label']; ?>
								<div class="dak-sv-clinic" data-service-clinic-group="<?php echo esc_attr( $dak_group_index ); ?>">
									<div class="dak-sv-clinic-head">
										<span class="dak-sv-clinic-icon" aria-hidden="true"><?php echo $dak_sv_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<div class="dak-sv-clinic-info">
											<strong><?php echo esc_html( $dak_clinic_group['label'] ); ?></strong>
											<?php if ( '' !== $dak_clinic_group['meta'] ) : ?>
												<span><?php echo esc_html( $dak_clinic_group['meta'] ); ?></span>
											<?php endif; ?>
										</div>
									</div>

									<ul class="dak-sv-doctors" data-sv-doctor-list>
										<?php foreach ( $dak_clinic_group['doctors'] as $dak_offer ) : ?>
											<?php
											$dak_offer_index++;
											$dak_doctor_label = sprintf( 'Dr. %s', $dak_offer['doctor_name'] );
											$dak_has_booking  = '' !== $dak_offer['booking_url'];
											?>
											<li
												class="dak-sv-doctor"
												data-service-doctor-offer
												data-doctor-name="<?php echo esc_attr( $dak_doctor_label ); ?>"
												data-clinic-name="<?php echo esc_attr( $dak_is_other ? '' : $dak_clinic_group['label'] ); ?>"
												data-price="<?php echo esc_attr( $dak_offer['price'] ); ?>"
												data-price-label="<?php echo esc_attr( $dak_offer['price_label'] ); ?>"
												data-booking-url="<?php echo esc_attr( $dak_offer['booking_url'] ); ?>"
											>
												<label class="dak-sv-doctor-pick">
													<input type="radio" class="dak-sv-doctor-radio" name="dak-service-doctor-select" value="<?php echo esc_attr( $dak_offer_index ); ?>"<?php disabled( ! $dak_has_booking ); ?>>

													<span class="dak-sv-doctor-photo" aria-hidden="true">
														<?php if ( $dak_offer['doctor_avatar_url'] ) : ?>
															<img src="<?php echo esc_url( $dak_offer['doctor_avatar_url'] ); ?>" alt="" width="52" height="52" loading="lazy">
														<?php else : ?>
															<?php echo $dak_sv_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
														<?php endif; ?>
													</span>

													<span class="dak-sv-doctor-info">
														<strong class="dak-sv-doctor-name"><?php echo esc_html( $dak_doctor_label ); ?><?php echo \DoctorAKPortal\Includes\Pmdc::tick_html( $dak_offer['doctor_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Pmdc::tick_html(). ?></strong>
														<?php if ( ! empty( $dak_offer['specialty'] ) ) : ?>
															<span class="dak-sv-doctor-specialty"><?php echo esc_html( $dak_offer['specialty'] ); ?></span>
														<?php endif; ?>
														<?php if ( ! empty( $dak_offer['next_available'] ) ) : ?>
															<span class="dak-sv-doctor-next">
																<?php echo $dak_sv_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
																<?php
																/* translators: %s: next open slot, e.g. "Today, 4:30 pm · Clinic". */
																echo esc_html( sprintf( __( 'Next: %s', 'doctor-ak-portal' ), $dak_offer['next_available'] ) );
																?>
															</span>
														<?php endif; ?>
													</span>

													<span class="dak-sv-doctor-price">
														<?php if ( ! empty( $dak_offer['discount_percent'] ) ) : ?>
															<span class="dak-sv-doctor-deal">
																<span class="dak-service-offer-discount"><?php echo esc_html( sprintf( /* translators: %d: discount percentage. */ __( '%d%% OFF', 'doctor-ak-portal' ), $dak_offer['discount_percent'] ) ); ?></span>
																<s class="dak-service-offer-was"><span class="dak-visually-hidden"><?php esc_html_e( 'Was', 'doctor-ak-portal' ); ?> </span><?php echo esc_html( $dak_offer['was_price_label'] ); ?></s>
															</span>
														<?php endif; ?>
														<strong><?php echo esc_html( $dak_offer['price_label'] ); ?></strong>
													</span>
												</label>

												<?php if ( $dak_offer['doctor_profile_url'] ) : ?>
													<a class="dak-sv-doctor-profile" href="<?php echo esc_url( $dak_offer['doctor_profile_url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: doctor's name. */ __( 'View %s\'s profile', 'doctor-ak-portal' ), $dak_doctor_label ) ); ?>">
														<?php esc_html_e( 'Profile', 'doctor-ak-portal' ); ?>
														<?php echo $dak_sv_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
													</a>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php elseif ( $dak_needs_doctor ) : ?>
					<section class="dak-profile-card dak-dp-section" id="dak-service-doctors" aria-labelledby="dak-sv-doctors-title">
						<header class="dak-dp-section-head">
							<h2 class="dak-dp-section-title" id="dak-sv-doctors-title">
								<span class="dak-dp-section-icon" aria-hidden="true"><?php echo $dak_sv_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php esc_html_e( 'Doctors & prices', 'doctor-ak-portal' ); ?>
							</h2>
						</header>
						<p class="dak-dp-section-lead"><?php esc_html_e( 'No doctor is taking bookings for this service right now. Please check back soon, or browse other services.', 'doctor-ak-portal' ); ?></p>
					</section>
				<?php endif; ?>

				<?php if ( ! $dak_needs_doctor ) : ?>
					<section class="dak-profile-card dak-dp-section" id="dak-service-request" aria-labelledby="dak-sv-request-title">
						<header class="dak-dp-section-head">
							<h2 class="dak-dp-section-title" id="dak-sv-request-title">
								<span class="dak-dp-section-icon" aria-hidden="true"><?php echo $dak_sv_icons['send']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php esc_html_e( 'Request this service', 'doctor-ak-portal' ); ?>
							</h2>
						</header>
						<p class="dak-dp-section-lead"><?php esc_html_e( 'No appointment needed — send your details and our team will call you to arrange it.', 'doctor-ak-portal' ); ?></p>

						<div id="dak-service-request-success" class="dak-alert dak-alert-success dak-hidden" role="status"></div>
						<div id="dak-service-request-general-error" class="dak-alert dak-alert-error dak-hidden" role="alert"></div>

						<form id="dak-service-request-form" class="dak-sv-request-form">
							<input type="hidden" name="service_id" value="<?php echo esc_attr( $group['service_id'] ); ?>">

							<div class="dak-sv-request-grid">
								<div class="dak-field">
									<label for="dak-service-request-name"><?php esc_html_e( 'Your name', 'doctor-ak-portal' ); ?></label>
									<input type="text" id="dak-service-request-name" name="patient_name" autocomplete="name">
									<span class="dak-field-error" data-field="patient_name"></span>
								</div>

								<div class="dak-field">
									<label for="dak-service-request-phone"><?php esc_html_e( 'Phone number', 'doctor-ak-portal' ); ?></label>
									<input type="tel" id="dak-service-request-phone" name="patient_phone" autocomplete="tel">
									<span class="dak-field-error" data-field="patient_phone"></span>
								</div>
							</div>

							<div class="dak-field">
								<label for="dak-service-request-email"><?php esc_html_e( 'Email (optional)', 'doctor-ak-portal' ); ?></label>
								<input type="email" id="dak-service-request-email" name="patient_email" autocomplete="email">
								<span class="dak-field-error" data-field="patient_email"></span>
							</div>

							<div class="dak-field">
								<label for="dak-service-request-notes"><?php esc_html_e( 'Notes (optional)', 'doctor-ak-portal' ); ?></label>
								<textarea id="dak-service-request-notes" name="notes" rows="3"></textarea>
							</div>

							<button type="submit" class="dak-button dak-button-primary" id="dak-service-request-submit">
								<?php esc_html_e( 'Send request', 'doctor-ak-portal' ); ?>
							</button>
						</form>
					</section>
				<?php endif; ?>

				<section class="dak-profile-card dak-dp-section" id="dak-service-about" aria-labelledby="dak-sv-about-title">
					<header class="dak-dp-section-head">
						<h2 class="dak-dp-section-title" id="dak-sv-about-title">
							<span class="dak-dp-section-icon" aria-hidden="true"><?php echo $dak_sv_icons['info']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'About this service', 'doctor-ak-portal' ); ?>
						</h2>
					</header>

					<?php if ( '' !== $dak_plain_text ) : ?>
						<div class="dak-profile-expertise dak-rich-text-content dak-sv-about-text"><?php echo wp_kses_post( $group['description'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() output. ?></div>
					<?php endif; ?>

					<div class="dak-dp-sub">
						<h3 class="dak-dp-sub-title"><?php esc_html_e( 'Good to know', 'doctor-ak-portal' ); ?></h3>
						<ul class="dak-sv-know">
							<?php if ( $dak_needs_doctor ) : ?>
								<li><?php echo $dak_sv_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Choose your doctor and clinic, then a date and time that suits you.', 'doctor-ak-portal' ); ?></li>
							<?php else : ?>
								<li><?php echo $dak_sv_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'No appointment or doctor needed — our team calls you to arrange it.', 'doctor-ak-portal' ); ?></li>
							<?php endif; ?>
							<?php if ( '' !== $dak_duration_label ) : ?>
								<li>
									<?php echo $dak_sv_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php
									/* translators: %s: duration, e.g. "30 min". */
									echo esc_html( sprintf( __( 'Takes about %s.', 'doctor-ak-portal' ), $dak_duration_label ) );
									?>
								</li>
							<?php endif; ?>
							<li><?php echo $dak_sv_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'The price is shown in PKR before you confirm — no hidden charges.', 'doctor-ak-portal' ); ?></li>
							<?php if ( $dak_max_discount > 0 ) : ?>
								<li>
									<?php echo $dak_sv_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php
									/* translators: %d: discount percentage. */
									echo esc_html( sprintf( __( 'Up to %d%% off right now — the reduced price is already shown above.', 'doctor-ak-portal' ), $dak_max_discount ) );
									?>
								</li>
							<?php endif; ?>
							<?php if ( $dak_needs_doctor ) : ?>
								<li><?php echo $dak_sv_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Reschedule or cancel from your dashboard.', 'doctor-ak-portal' ); ?></li>
							<?php endif; ?>
						</ul>
					</div>
				</section>

				<?php if ( ! empty( $dak_related ) ) : ?>
					<section class="dak-profile-card dak-dp-section" id="dak-service-related" aria-labelledby="dak-sv-related-title">
						<header class="dak-dp-section-head">
							<h2 class="dak-dp-section-title" id="dak-sv-related-title">
								<span class="dak-dp-section-icon" aria-hidden="true"><?php echo $dak_sv_icons['grid']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php
								/* translators: %s: service category name. */
								echo esc_html( '' !== $dak_category ? sprintf( __( 'More in %s', 'doctor-ak-portal' ), $dak_category ) : __( 'Related services', 'doctor-ak-portal' ) );
								?>
							</h2>
							<?php if ( '' !== $dak_category_url ) : ?>
								<a class="dak-sv-see-all" href="<?php echo esc_url( $dak_category_url ); ?>"><?php esc_html_e( 'See all', 'doctor-ak-portal' ); ?> <?php echo $dak_sv_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
							<?php endif; ?>
						</header>

						<ul class="dak-sv-related">
							<?php foreach ( $dak_related as $dak_rel ) : ?>
								<li>
									<a class="dak-sv-related-card" href="<?php echo esc_url( $dak_rel['url'] ); ?>">
										<span class="dak-sv-related-media" aria-hidden="true">
											<?php if ( '' !== $dak_rel['image_url'] ) : ?>
												<img src="<?php echo esc_url( $dak_rel['image_url'] ); ?>" alt="" loading="lazy">
											<?php else : ?>
												<?php echo $dak_sv_icons['pulse']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php endif; ?>
											<?php if ( $dak_rel['discount_percent'] > 0 ) : ?>
												<span class="dak-sv-related-off"><?php echo esc_html( sprintf( /* translators: %d: discount percentage. */ __( '%d%% OFF', 'doctor-ak-portal' ), $dak_rel['discount_percent'] ) ); ?></span>
											<?php endif; ?>
										</span>
										<span class="dak-sv-related-body">
											<strong><?php echo esc_html( $dak_rel['name'] ); ?></strong>
											<span class="dak-sv-related-price"><?php echo esc_html( $dak_rel['price_label'] ); ?></span>
											<?php if ( $dak_rel['provider_count'] > 0 ) : ?>
												<span class="dak-sv-related-meta"><?php echo esc_html( sprintf( /* translators: %d: number of doctors. */ _n( '%d doctor', '%d doctors', $dak_rel['provider_count'], 'doctor-ak-portal' ), $dak_rel['provider_count'] ) ); ?></span>
											<?php endif; ?>
										</span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
			</div>

			<aside class="dak-profile-sidebar" aria-label="<?php echo esc_attr( $dak_needs_doctor ? __( 'Book this service', 'doctor-ak-portal' ) : __( 'Request this service', 'doctor-ak-portal' ) ); ?>">
				<div class="dak-profile-card dak-profile-booking-card" id="dak-service-booking-card">
					<p class="dak-dp-book-eyebrow"><?php echo esc_html( $dak_needs_doctor ? __( 'Book this service', 'doctor-ak-portal' ) : __( 'Request this service', 'doctor-ak-portal' ) ); ?></p>
					<p class="dak-sv-book-name"><?php echo esc_html( $group['name'] ); ?></p>

					<div class="dak-profile-booking-fee">
						<span id="dak-service-booking-fee-label"><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-service-booking-fee"><?php echo esc_html( $group['price_label'] ); ?></strong>
					</div>

					<?php if ( $dak_max_discount > 0 ) : ?>
						<p class="dak-sv-book-offer">
							<?php echo $dak_sv_icons['tag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php
							/* translators: %d: discount percentage. */
							echo esc_html( sprintf( __( 'Up to %d%% off right now', 'doctor-ak-portal' ), $dak_max_discount ) );
							?>
						</p>
					<?php endif; ?>

					<p class="dak-profile-booking-hint" id="dak-service-booking-hint">
						<?php
						if ( ! $dak_needs_doctor ) {
							esc_html_e( 'Send your details and our team will call you to arrange it.', 'doctor-ak-portal' );
						} elseif ( $dak_has_doctors ) {
							esc_html_e( 'Choose a doctor and clinic to see the exact price.', 'doctor-ak-portal' );
						} else {
							esc_html_e( 'Not available to book right now.', 'doctor-ak-portal' );
						}
						?>
					</p>

					<?php if ( '' !== $dak_cta_href ) : ?>
						<a class="dak-button dak-button-primary dak-button-block dak-profile-cta" href="<?php echo esc_attr( $dak_cta_href ); ?>" id="dak-service-booking-button"><?php echo esc_html( $dak_cta_label ); ?></a>
					<?php endif; ?>

					<?php if ( $dak_has_doctors ) : ?>
						<p class="dak-profile-booking-note"><?php esc_html_e( 'You’ll pick a date and time on the next step.', 'doctor-ak-portal' ); ?></p>
					<?php endif; ?>
				</div>
			</aside>
		</div>

		<?php if ( '' !== $dak_cta_href ) : ?>
			<div class="dak-profile-mobile-bar" id="dak-service-mobile-bar">
				<div class="dak-profile-mobile-bar-summary" id="dak-service-mobile-summary">
					<?php echo esc_html( $group['price_label'] ); ?>
				</div>
				<a class="dak-button dak-button-primary" href="<?php echo esc_attr( $dak_cta_href ); ?>" id="dak-service-mobile-cta"><?php echo esc_html( $dak_cta_label ); ?></a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
