<?php
/**
 * Template: "Find a doctor by symptoms" ([symptom_checker]).
 *
 * The page frame, input and notices are rendered here;
 * doctor-ak-symptom-finder.js handles suggestions, clarification, the
 * clinician-approved follow-up questions, safety messages and results.
 * Nothing the visitor enters leaves their browser.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string   $state          'live', 'preview' (staff only) or 'unavailable'.
 * @var string   $notice_text    The emergency notice (approved, or the working copy in a preview).
 * @var bool     $notice_pending The previewed notice hasn't been approved yet.
 * @var string[] $missing        What's still needed before patients can see the page.
 * @var string   $directory_url  Find a Doctor page, or ''.
 * @var string   $manage_url     Where staff manage symptoms, or ''.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_sf_alert_icon = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10 2.8 18 17H2z"/><path d="M10 8v4M10 14.5v.01"/></svg>';
?>
<div class="dak-portal dak-sf" data-dak-sf>
	<?php if ( 'preview' === $state ) : ?>
		<div class="dak-sf-preview" role="note">
			<strong><?php esc_html_e( 'Staff preview', 'doctor-ak-portal' ); ?></strong>
			<span><?php esc_html_e( 'This preview uses the working copy of every symptom, question and safety rule, including drafts that patients can’t see.', 'doctor-ak-portal' ); ?></span>
			<?php if ( ! empty( $missing ) ) : ?>
				<span><?php esc_html_e( 'Not yet visible to patients:', 'doctor-ak-portal' ); ?> <?php echo esc_html( implode( ' ', $missing ) ); ?></span>
			<?php endif; ?>
			<?php if ( $manage_url ) : ?>
				<a href="<?php echo esc_url( $manage_url ); ?>"><?php esc_html_e( 'Manage symptoms', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<header class="dak-sf-head">
		<h1 tabindex="-1" data-sf-title><?php esc_html_e( 'How can we help you today?', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Tell us your symptoms and we’ll show doctors in related specialties. This tool helps you find a doctor — it doesn’t diagnose you or tell you how serious your symptoms are.', 'doctor-ak-portal' ); ?></p>
	</header>

	<?php if ( '' !== $notice_text ) : ?>
		<div class="dak-sf-notice<?php echo $notice_pending ? ' is-draft' : ''; ?>" role="note" aria-label="<?php esc_attr_e( 'Emergency notice', 'doctor-ak-portal' ); ?>">
			<?php echo $dak_sf_alert_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
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
		<div class="dak-sf-live dak-visually-hidden" aria-live="polite" data-sf-live></div>

		<nav class="dak-sf-steps" aria-label="<?php esc_attr_e( 'Progress', 'doctor-ak-portal' ); ?>">
			<ol data-sf-steps>
				<li data-sf-step-item="symptoms" aria-current="step"><span class="dak-sf-step-n" aria-hidden="true">1</span><span><?php esc_html_e( 'Your symptoms', 'doctor-ak-portal' ); ?></span></li>
				<li data-sf-step-item="questions"><span class="dak-sf-step-n" aria-hidden="true">2</span><span><?php esc_html_e( 'A few questions', 'doctor-ak-portal' ); ?></span></li>
				<li data-sf-step-item="results"><span class="dak-sf-step-n" aria-hidden="true">3</span><span><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></span></li>
			</ol>
		</nav>

		<?php // Clinician-approved safety messages appear here as soon as an answer triggers one, above everything else. ?>
		<div class="dak-sf-alerts" data-sf-alerts hidden></div>

		<section class="dak-sf-card" data-sf-step="symptoms" aria-labelledby="dak-sf-input-label">
			<label class="dak-sf-label dak-sf-label-lg" id="dak-sf-input-label" for="dak-sf-input"><?php esc_html_e( 'What symptoms do you have?', 'doctor-ak-portal' ); ?></label>
			<p class="dak-sf-hint" id="dak-sf-input-hint"><?php esc_html_e( 'Start typing and choose from the list, or write a few in your own words separated by commas. English and Roman Urdu both work, e.g. “fever” or “bukhar”.', 'doctor-ak-portal' ); ?></p>

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
					placeholder="<?php esc_attr_e( 'e.g. headache, stomach pain', 'doctor-ak-portal' ); ?>"
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
				<button type="button" class="dak-button dak-button-primary dak-sf-btn" data-sf-find><?php esc_html_e( 'Continue', 'doctor-ak-portal' ); ?></button>
				<p class="dak-sf-hint"><?php esc_html_e( 'When we show doctors, your browser will ask to share your location so they can be sorted by distance. It’s only used on this page and is never saved. You can say no.', 'doctor-ak-portal' ); ?></p>
			</div>
		</section>

		<section class="dak-sf-card dak-sf-questions" data-sf-step="questions" aria-labelledby="dak-sf-q-title" hidden></section>

		<section class="dak-sf-results" data-sf-results hidden aria-labelledby="dak-sf-results-title"></section>

		<aside class="dak-sf-limits" aria-labelledby="dak-sf-limits-title">
			<h2 id="dak-sf-limits-title"><?php esc_html_e( 'About this tool', 'doctor-ak-portal' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'It helps you find doctors in specialties linked to the symptoms you choose. It is not a diagnosis and not a substitute for advice from a doctor.', 'doctor-ak-portal' ); ?></li>
				<li><?php esc_html_e( 'It doesn’t check for every serious condition, and it can’t tell you that something serious has been ruled out.', 'doctor-ak-portal' ); ?></li>
				<li><?php esc_html_e( 'If your symptoms are severe, sudden or getting worse, or you’re worried, get medical help straight away — don’t wait to book an appointment.', 'doctor-ak-portal' ); ?></li>
				<li><?php esc_html_e( 'What you type and answer stays on this page in your browser. It isn’t sent to us, saved or added to your appointment.', 'doctor-ak-portal' ); ?></li>
			</ul>
		</aside>
	<?php endif; ?>
</div>
