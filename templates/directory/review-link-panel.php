<?php
/**
 * Template: the review form reached from a doctor's shareable review link
 * (Dashboard → Reviews → Share a review link). Shown at the top of the
 * doctor's "Patient reviews" section. Anyone with the link may use it, so
 * the review is held for an admin to publish (see Review_Links). A plain
 * HTML form — works without JavaScript.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $review_link Review_Links::page_context() output.
 * @var array $doctor      Doctor being viewed (id, name).
 */

use DoctorAKPortal\Includes\Doctor_Reviews;
use DoctorAKPortal\Includes\Review_Links;
use DoctorAKPortal\Includes\Review_Requests;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_rl     = $review_link;
$dak_status = $dak_rl['status'];
$dak_errors = array(
	'link_error_rating' => __( 'Please choose a rating from 1 to 5 stars.', 'doctor-ak-portal' ),
	'link_error_name'   => __( 'Please enter your first name.', 'doctor-ak-portal' ),
	'link_error_spam'   => __( 'Your review couldn’t be sent. Please reload the page and try again.', 'doctor-ak-portal' ),
	'link_error_limit'  => __( 'Too many reviews have been sent from this connection. Please try again in an hour.', 'doctor-ak-portal' ),
	'link_error_save'   => __( 'Your review couldn’t be saved. Please try again.', 'doctor-ak-portal' ),
);
?>
<section class="dak-review-request dak-review-link" id="<?php echo esc_attr( Review_Requests::PANEL_ANCHOR ); ?>" aria-labelledby="dak-review-request-title" tabindex="-1">
	<?php if ( 'open' === $dak_status ) : ?>
		<h3 id="dak-review-request-title">
			<?php
			/* translators: %s: doctor name. */
			echo esc_html( sprintf( __( 'Review Dr. %s', 'doctor-ak-portal' ), $doctor['name'] ) );
			?>
		</h3>
		<p class="dak-review-request-summary"><?php esc_html_e( 'Tell other patients how your visit went. It takes about a minute.', 'doctor-ak-portal' ); ?></p>

		<?php if ( '' !== $dak_rl['error'] && isset( $dak_errors[ $dak_rl['error'] ] ) ) : ?>
			<div class="dak-alert dak-alert-error" role="alert"><?php echo esc_html( $dak_errors[ $dak_rl['error'] ] ); ?></div>
		<?php endif; ?>

		<form class="dak-review-request-form" method="post" action="<?php echo esc_url( $dak_rl['action_url'] ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Review_Links::SUBMIT_ACTION ); ?>">
			<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $doctor['id'] ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( $dak_rl['token'] ); ?>">
			<input type="hidden" name="stamp" value="<?php echo esc_attr( $dak_rl['stamp'] ); ?>">

			<div class="dak-review-link-trap" aria-hidden="true">
				<label for="dak-rl-website"><?php esc_html_e( 'Leave this field empty', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-rl-website" name="<?php echo esc_attr( Review_Links::HONEYPOT ); ?>" value="" tabindex="-1" autocomplete="off">
			</div>

			<fieldset class="dak-review-request-rating">
				<legend><?php esc_html_e( 'Your rating', 'doctor-ak-portal' ); ?></legend>
				<div class="dak-review-request-stars">
					<?php for ( $dak_i = 1; $dak_i <= 5; $dak_i++ ) : ?>
						<input type="radio" id="dak-rl-star-<?php echo esc_attr( $dak_i ); ?>" name="rating" value="<?php echo esc_attr( $dak_i ); ?>" required>
						<label for="dak-rl-star-<?php echo esc_attr( $dak_i ); ?>">
							<span aria-hidden="true">&#9733;</span>
							<span class="dak-visually-hidden">
								<?php
								/* translators: %d: number of stars. */
								echo esc_html( sprintf( _n( '%d star', '%d stars', $dak_i, 'doctor-ak-portal' ), $dak_i ) );
								?>
							</span>
						</label>
					<?php endfor; ?>
				</div>
				<p class="dak-review-request-hint"><?php esc_html_e( '1 = poor, 5 = excellent.', 'doctor-ak-portal' ); ?></p>
			</fieldset>

			<div class="dak-review-link-field">
				<label class="dak-review-request-label" for="dak-rl-name"><?php esc_html_e( 'Your first name', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-rl-name" name="reviewer_name" required minlength="2" maxlength="<?php echo esc_attr( Doctor_Reviews::NAME_MAX ); ?>" autocomplete="given-name">
			</div>

			<div class="dak-review-link-field">
				<label class="dak-review-request-label" for="dak-rl-comment"><?php esc_html_e( 'Your review (optional)', 'doctor-ak-portal' ); ?></label>
				<textarea id="dak-rl-comment" name="comment" rows="4" maxlength="<?php echo esc_attr( Doctor_Reviews::COMMENT_MAX ); ?>" placeholder="<?php esc_attr_e( 'What went well, and what could be better?', 'doctor-ak-portal' ); ?>"></textarea>
			</div>

			<div class="dak-review-link-field">
				<label class="dak-review-request-label" for="dak-rl-contact"><?php esc_html_e( 'Phone or email (optional)', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-rl-contact" name="reviewer_contact" maxlength="<?php echo esc_attr( Doctor_Reviews::CONTACT_MAX ); ?>" autocomplete="tel" aria-describedby="dak-rl-contact-hint">
				<p class="dak-review-request-hint" id="dak-rl-contact-hint"><?php esc_html_e( 'Only our team sees this, in case we need to follow up. It is never shown on the website.', 'doctor-ak-portal' ); ?></p>
			</div>

			<p class="dak-review-request-public">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: doctor name. */
						__( 'Your first name, rating and review will appear on Dr. %s’s profile once our team has checked it. Please don’t include medical details.', 'doctor-ak-portal' ),
						$doctor['name']
					)
				);
				?>
			</p>

			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Send review', 'doctor-ak-portal' ); ?></button>
		</form>

	<?php elseif ( 'submitted' === $dak_status ) : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'Thank you for your review', 'doctor-ak-portal' ); ?></h3>
		<p role="status">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: doctor name. */
					__( 'We’ve received it. It will appear on Dr. %s’s profile once our team has checked it.', 'doctor-ak-portal' ),
					$doctor['name']
				)
			);
			?>
		</p>

	<?php else : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'This review link isn’t valid', 'doctor-ak-portal' ); ?></h3>
		<p><?php esc_html_e( 'It may have been replaced with a new one. Please ask the clinic for the latest link.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
