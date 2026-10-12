<?php
/**
 * Template: Blog listing for the [blogs_directory] shortcode — a compact
 * header, a filter bar (article search, the most-used topics as chips, and an
 * "All topics" panel listing every topic with its own search), then the card
 * grid. doctor-ak-blogs-directory.js filters every card on the page and shows
 * them a page at a time behind "Load more"; without JavaScript every card
 * simply shows.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $blogs_html   Pre-rendered directory/blog-card.php output, one per published post.
 * @var array    $topics       Every topic in use, alphabetical: slug => array( slug, label, count ).
 * @var array    $quick_topics The most-used topics, for the always-visible chips.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_total = count( $blogs_html );
?>
<div class="dak-portal dak-directory dak-blog-listing">
	<header class="dak-blog-listing-head">
		<h1><?php esc_html_e( 'Health Articles & Insights', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Explore articles on health conditions, treatments, and everyday wellbeing.', 'doctor-ak-portal' ); ?></p>
	</header>

	<?php if ( 0 === $dak_total ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No articles are available yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-blog-filters" id="dak-blog-filters">
			<div class="dak-blog-search">
				<label class="dak-blog-search-label" for="dak-blog-search"><?php esc_html_e( 'Search articles', 'doctor-ak-portal' ); ?></label>
				<div class="dak-blog-search-field">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="M13.5 13.5L17 17"/></svg>
					<input type="search" id="dak-blog-search" placeholder="<?php esc_attr_e( 'e.g. hepatitis, endoscopy, nutrition', 'doctor-ak-portal' ); ?>" autocomplete="off" aria-controls="dak-blogs-directory-grid">
				</div>
			</div>

			<?php if ( ! empty( $topics ) ) : ?>
				<div class="dak-blog-topics">
					<span class="dak-blog-topics-label" id="dak-blog-topics-label"><?php esc_html_e( 'Topics', 'doctor-ak-portal' ); ?></span>
					<div class="dak-blog-chips" role="group" aria-labelledby="dak-blog-topics-label">
						<button type="button" class="dak-blog-chip is-active" data-topic-filter="" aria-pressed="true"><?php esc_html_e( 'All', 'doctor-ak-portal' ); ?></button>
						<?php foreach ( $quick_topics as $dak_topic ) : ?>
							<button type="button" class="dak-blog-chip" data-topic-filter="<?php echo esc_attr( $dak_topic['slug'] ); ?>" aria-pressed="false"><?php echo esc_html( $dak_topic['label'] ); ?></button>
						<?php endforeach; ?>
						<?php // A topic picked from the panel that has no chip of its own shows here. ?>
						<button type="button" class="dak-blog-chip is-active dak-blog-chip-picked" data-topic-filter="" aria-pressed="true" hidden></button>

						<?php if ( count( $topics ) > count( $quick_topics ) ) : ?>
							<div class="dak-blog-alltopics">
								<button type="button" class="dak-blog-alltopics-toggle" id="dak-blog-alltopics-toggle" aria-expanded="false" aria-controls="dak-blog-alltopics-panel">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of topics. */
											__( 'All topics (%d)', 'doctor-ak-portal' ),
											count( $topics )
										)
									);
									?>
									<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8l5 5 5-5"/></svg>
								</button>
								<div class="dak-blog-alltopics-panel" id="dak-blog-alltopics-panel" role="dialog" aria-label="<?php esc_attr_e( 'All topics', 'doctor-ak-portal' ); ?>" hidden>
									<label class="dak-blog-visually-hidden" for="dak-blog-topic-search"><?php esc_html_e( 'Find a topic', 'doctor-ak-portal' ); ?></label>
									<input type="search" id="dak-blog-topic-search" class="dak-blog-topic-search" placeholder="<?php esc_attr_e( 'Find a topic', 'doctor-ak-portal' ); ?>" autocomplete="off">
									<ul class="dak-blog-topic-list" id="dak-blog-topic-list">
										<?php foreach ( $topics as $dak_topic ) : ?>
											<li>
												<button type="button" class="dak-blog-topic-option" data-topic-filter="<?php echo esc_attr( $dak_topic['slug'] ); ?>" data-topic-label="<?php echo esc_attr( $dak_topic['label'] ); ?>" aria-pressed="false">
													<span><?php echo esc_html( $dak_topic['label'] ); ?></span>
													<span class="dak-blog-topic-count"><?php echo esc_html( number_format_i18n( $dak_topic['count'] ) ); ?></span>
												</button>
											</li>
										<?php endforeach; ?>
									</ul>
									<p class="dak-blog-topic-empty" hidden><?php esc_html_e( 'No topic matches that search.', 'doctor-ak-portal' ); ?></p>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="dak-blog-results-bar">
			<p class="dak-blog-results-count" id="dak-blog-results-count" aria-live="polite">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of articles. */
						_n( '%d article', '%d articles', $dak_total, 'doctor-ak-portal' ),
						$dak_total
					)
				);
				?>
			</p>
			<button type="button" class="dak-blog-reset" id="dak-blog-reset" hidden><?php esc_html_e( 'Clear filters', 'doctor-ak-portal' ); ?></button>
		</div>

		<h2 class="dak-blog-visually-hidden"><?php esc_html_e( 'Articles', 'doctor-ak-portal' ); ?></h2>
		<div class="dak-blog-grid" id="dak-blogs-directory-grid" data-page-size="9">
			<?php foreach ( $blogs_html as $card_html ) : ?>
				<?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
			<?php endforeach; ?>
		</div>

		<div class="dak-blog-noresults" id="dak-blog-noresults" hidden>
			<h2><?php esc_html_e( 'No articles match your search', 'doctor-ak-portal' ); ?></h2>
			<p><?php esc_html_e( 'Try a different word, or browse all topics.', 'doctor-ak-portal' ); ?></p>
			<button type="button" class="dak-blog-reset-btn" data-blog-reset><?php esc_html_e( 'Show all articles', 'doctor-ak-portal' ); ?></button>
		</div>

		<div class="dak-blog-more" id="dak-blog-more" hidden>
			<button type="button" class="dak-blog-more-btn" id="dak-blog-more-btn"><?php esc_html_e( 'Load more articles', 'doctor-ak-portal' ); ?></button>
		</div>

		<script type="application/json" id="dak-blog-i18n"><?php
			echo wp_json_encode(
				array(
					/* translators: 1: number of articles shown, 2: number of matching articles. */
					'showing'  => __( 'Showing %1$d of %2$d articles', 'doctor-ak-portal' ),
					/* translators: %d: number of articles. */
					'countOne' => __( '%d article', 'doctor-ak-portal' ),
					/* translators: %d: number of articles. */
					'countMany' => __( '%d articles', 'doctor-ak-portal' ),
					/* translators: %s: topic name. */
					'inTopic'  => __( 'in %s', 'doctor-ak-portal' ),
					/* translators: %s: search words. */
					'forQuery' => __( 'for “%s”', 'doctor-ak-portal' ),
				)
			);
		?></script>
	<?php endif; ?>
</div>
