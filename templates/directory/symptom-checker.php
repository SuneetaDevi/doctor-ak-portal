<?php
/**
 * Template: the public Symptom Checker page ([symptom_checker]).
 *
 * The first screen is rendered here; doctor-ak-symptom-checker.js takes over
 * from "Start" (questions, summary, results). Nothing the visitor enters
 * leaves their browser.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string   $state         'live', 'preview' (administrators only) or 'unavailable'.
 * @var string   $preview_label What is being previewed ("Draft: …").
 * @var bool     $is_demo       The previewed rules are demonstration content.
 * @var string[] $errors        Validation problems in the previewed draft.
 * @var string   $directory_url Find a Doctor page, or ''.
 * @var string   $admin_url     Dashboard Symptom Checker section (administrators), or ''.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-sc" data-dak-sc>
	<?php if ( 'preview' === $state ) : ?>
		<div class="dak-sc-preview" role="note">
			<strong><?php esc_html_e( 'Staff preview — not visible to patients', 'doctor-ak-portal' ); ?></strong>
			<span><?php echo esc_html( $preview_label ); ?></span>
			<?php if ( $is_demo ) : ?>
				<span><?php esc_html_e( 'These are demonstration rules for testing the software only. They have not been clinically reviewed and can never be published.', 'doctor-ak-portal' ); ?></span>
			<?php else : ?>
				<span><?php esc_html_e( 'Patients will only see the Symptom Checker after a designated clinical reviewer approves the rules and it is switched on.', 'doctor-ak-portal' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $errors ) ) : ?>
				<span>
					<?php
					/* translators: %d: number of problems. */
					echo esc_html( sprintf( _n( 'The draft has %d problem to fix before it can be approved — see the dashboard.', 'The draft has %d problems to fix before it can be approved — see the dashboard.', count( $errors ), 'doctor-ak-portal' ), count( $errors ) ) );
					?>
				</span>
			<?php endif; ?>
			<?php if ( $admin_url ) : ?>
				<a href="<?php echo esc_url( $admin_url ); ?>"><?php esc_html_e( 'Open Symptom Checker settings', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( 'unavailable' === $state ) : ?>
		<section class="dak-sc-card dak-sc-unavailable">
			<h1><?php esc_html_e( 'Symptom Checker', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'The Symptom Checker isn\'t available at the moment.', 'doctor-ak-portal' ); ?></p>
			<?php if ( $admin_url ) : ?>
				<p><?php esc_html_e( 'No ruleset has been drafted or approved yet.', 'doctor-ak-portal' ); ?> <a href="<?php echo esc_url( $admin_url ); ?>"><?php esc_html_e( 'Open Symptom Checker settings', 'doctor-ak-portal' ); ?></a></p>
			<?php endif; ?>
			<?php if ( $directory_url ) : ?>
				<p><a class="dak-button dak-button-primary dak-sc-btn" href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Find a doctor', 'doctor-ak-portal' ); ?></a></p>
			<?php endif; ?>
			<p class="dak-sc-small"><?php esc_html_e( 'If you think you may have a medical emergency, go to the nearest emergency department or contact your local emergency services now.', 'doctor-ak-portal' ); ?></p>
		</section>
	<?php else : ?>
		<div class="dak-sc-live" aria-live="polite" aria-atomic="true" data-sc-live></div>

		<section class="dak-sc-card dak-sc-stage" data-sc-stage="intro" aria-labelledby="dak-sc-intro-title">
			<h1 id="dak-sc-intro-title" tabindex="-1"><?php esc_html_e( 'How are you feeling?', 'doctor-ak-portal' ); ?></h1>
			<p class="dak-sc-lead"><?php esc_html_e( 'Tell us about your symptoms. We\'ll ask a few questions and help you find suitable care near you.', 'doctor-ak-portal' ); ?></p>
			<p class="dak-sc-note"><?php esc_html_e( 'This tool provides general guidance and helps you find care. It does not provide a diagnosis.', 'doctor-ak-portal' ); ?></p>

			<label class="dak-sc-label" for="dak-sc-text"><?php esc_html_e( 'Your symptoms', 'doctor-ak-portal' ); ?></label>
			<textarea id="dak-sc-text" class="dak-sc-textarea" rows="4" maxlength="1000" autocomplete="off" spellcheck="true" placeholder="<?php esc_attr_e( 'Describe what you\'re feeling and when it started…', 'doctor-ak-portal' ); ?>" data-sc-text></textarea>

			<div class="dak-sc-picker" data-sc-picker>
				<label class="dak-sc-label" for="dak-sc-search"><?php esc_html_e( 'Or choose from a list', 'doctor-ak-portal' ); ?></label>
				<input type="search" id="dak-sc-search" class="dak-sc-input" autocomplete="off" placeholder="<?php esc_attr_e( 'Search symptoms, e.g. stomach pain', 'doctor-ak-portal' ); ?>" aria-describedby="dak-sc-search-hint" data-sc-search>
				<p id="dak-sc-search-hint" class="dak-sc-small"><?php esc_html_e( 'Tap a symptom to add it. Tap it again to remove it.', 'doctor-ak-portal' ); ?></p>
				<div class="dak-sc-chips" role="group" aria-label="<?php esc_attr_e( 'Symptoms', 'doctor-ak-portal' ); ?>" data-sc-chips></div>
			</div>

			<p class="dak-sc-error" role="alert" data-sc-intro-error hidden></p>

			<div class="dak-sc-actions">
				<button type="button" class="dak-button dak-button-primary dak-sc-btn" data-sc-start><?php esc_html_e( 'Start', 'doctor-ak-portal' ); ?></button>
			</div>
			<p class="dak-sc-small dak-sc-location-line"><?php esc_html_e( 'We use your location to find relevant doctors near you.', 'doctor-ak-portal' ); ?></p>

			<noscript><p class="dak-sc-error"><?php esc_html_e( 'The Symptom Checker needs JavaScript. You can still find a doctor from our directory.', 'doctor-ak-portal' ); ?></p></noscript>

			<p class="dak-sc-emergency-hint">
				<?php esc_html_e( 'If you think you may have a medical emergency, don\'t use this tool — go to the nearest emergency department or contact your local emergency services now.', 'doctor-ak-portal' ); ?>
			</p>
		</section>

		<div data-sc-app></div>
	<?php endif; ?>
</div>
