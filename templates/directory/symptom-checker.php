<?php
/**
 * Template: "Find a doctor by symptoms" ([symptom_checker]).
 *
 * The input and notices are rendered here; doctor-ak-symptom-finder.js
 * handles suggestions, clarification and results. Nothing the visitor
 * enters leaves their browser.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string   $state          'live', 'preview' (staff only) or 'unavailable'.
 * @var string   $notice_text    The emergency notice (approved, or the draft in a preview).
 * @var bool     $notice_pending The notice hasn't been approved yet.
 * @var string[] $missing        What's still needed before patients can see the page.
 * @var string   $directory_url  Find a Doctor page, or ''.
 * @var string   $manage_url     Where staff manage mappings, or ''.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-sf" data-dak-sf>
	<?php if ( 'preview' === $state ) : ?>
		<div class="dak-sf-preview" role="note">
			<strong><?php esc_html_e( 'Staff preview', 'doctor-ak-portal' ); ?></strong>
			<span><?php esc_html_e( 'This preview uses every symptom mapping, including drafts that patients can’t see.', 'doctor-ak-portal' ); ?></span>
			<?php if ( ! empty( $missing ) ) : ?>
				<span><?php esc_html_e( 'Not yet visible to patients:', 'doctor-ak-portal' ); ?> <?php echo esc_html( implode( ' ', $missing ) ); ?></span>
			<?php endif; ?>
			<?php if ( $manage_url ) : ?>
				<a href="<?php echo esc_url( $manage_url ); ?>"><?php esc_html_e( 'Manage symptom mappings', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<header class="dak-sf-head">
		<h1 tabindex="-1" data-sf-title><?php esc_html_e( 'Find doctors based on your symptoms', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Tell us what you’re feeling and we’ll show doctors in related specialties, nearest first. This helps you find a doctor — it does not give a diagnosis or tell you whether your symptoms are urgent.', 'doctor-ak-portal' ); ?></p>
	</header>

	<?php if ( '' !== $notice_text ) : ?>
		<div class="dak-sf-notice<?php echo $notice_pending ? ' is-draft' : ''; ?>" role="note">
			<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.8 18 17H2z"/><path d="M10 8v4M10 14.5v.01"/></svg>
			<p>
				<?php if ( $notice_pending ) : ?>
					<strong><?php esc_html_e( 'Draft — awaiting clinical approval:', 'doctor-ak-portal' ); ?></strong>
				<?php endif; ?>
				<?php echo esc_html( $notice_text ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( 'unavailable' === $state ) : ?>
		<section class="dak-sf-card">
			<p><?php esc_html_e( 'Finding doctors by symptoms isn’t available at the moment.', 'doctor-ak-portal' ); ?></p>
			<?php if ( $directory_url ) : ?>
				<p><a class="dak-button dak-button-primary dak-sf-btn" href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Search all doctors', 'doctor-ak-portal' ); ?></a></p>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<section class="dak-sf-card" aria-labelledby="dak-sf-input-label">
			<div class="dak-sf-live dak-visually-hidden" aria-live="polite" data-sf-live></div>

			<label class="dak-sf-label" id="dak-sf-input-label" for="dak-sf-input"><?php esc_html_e( 'What are you feeling?', 'doctor-ak-portal' ); ?></label>
			<p class="dak-sf-hint" id="dak-sf-input-hint"><?php esc_html_e( 'Type a symptom and choose from the list, or write a few in your own words, separated by commas.', 'doctor-ak-portal' ); ?></p>

			<div class="dak-sf-combo" data-sf-combo>
				<input
					type="text"
					id="dak-sf-input"
					class="dak-sf-input"
					autocomplete="off"
					autocapitalize="none"
					spellcheck="true"
					role="combobox"
					aria-autocomplete="list"
					aria-expanded="false"
					aria-controls="dak-sf-suggestions"
					aria-describedby="dak-sf-input-hint"
					placeholder="<?php esc_attr_e( 'e.g. stomach pain, fever', 'doctor-ak-portal' ); ?>"
					data-sf-input
				>
				<button type="button" class="dak-button dak-button-secondary dak-sf-add" data-sf-add><?php esc_html_e( 'Add', 'doctor-ak-portal' ); ?></button>
				<ul class="dak-sf-suggestions" id="dak-sf-suggestions" role="listbox" aria-label="<?php esc_attr_e( 'Matching symptoms', 'doctor-ak-portal' ); ?>" hidden data-sf-suggestions></ul>
			</div>

			<div class="dak-sf-clarify" data-sf-clarify hidden></div>

			<div class="dak-sf-selected" data-sf-selected-wrap hidden>
				<p class="dak-sf-label" id="dak-sf-selected-label"><?php esc_html_e( 'Your symptoms', 'doctor-ak-portal' ); ?></p>
				<ul class="dak-sf-chips" aria-labelledby="dak-sf-selected-label" data-sf-chips></ul>
			</div>

			<p class="dak-sf-error" role="alert" data-sf-error hidden></p>

			<div class="dak-sf-actions">
				<button type="button" class="dak-button dak-button-primary dak-sf-btn" data-sf-find><?php esc_html_e( 'Find doctors', 'doctor-ak-portal' ); ?></button>
				<p class="dak-sf-hint"><?php esc_html_e( 'Your browser will ask to share your location so doctors can be sorted by distance. It’s only used on this page and is never saved. You can say no.', 'doctor-ak-portal' ); ?></p>
			</div>
		</section>

		<section class="dak-sf-results" data-sf-results hidden aria-labelledby="dak-sf-results-title"></section>
	<?php endif; ?>
</div>
