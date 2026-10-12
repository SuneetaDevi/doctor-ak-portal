<?php
/**
 * Template: the review form a patient reaches from their "How was your
 * appointment?" email, shown at the top of the doctor's "Patient Reviews"
 * card. A plain HTML form (real radio buttons for the rating, no script
 * needed): the rating from the email link is only preselected — nothing is
 * saved until the patient presses Submit. Also covers the link's other
 * states: submitted, already used, expired, invalid, no longer eligible,
 * and opened while signed in to a different account.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $review_request Review_Requests::page_context() output.
 * @var array  $doctor         Doctor being viewed (id, name).
 * @var string $login_url      Login page URL, or ''.
 */

use DoctorAKPortal\Includes\Doctor_Reviews;
use DoctorAKPortal\Includes\Review_Requests;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_rr      = $review_request;
$dak_status  = $dak_rr['status'];
$dak_summary = $dak_rr['appointment'];
$dak_errors  = array(
	'error_rating'  => __( 'Please choose a rating from 1 to 5 stars.', 'doctor-ak-portal' ),
	'error_save'    => __( 'Your review couldn’t be saved. Please try again.', 'doctor-ak-portal' ),
	'error_expired' => __( 'This review link is no longer valid, so your review wasn’t saved.', 'doctor-ak-portal' ),
	'error_session' => __( 'Your session expired before the review was sent. Please try again.', 'doctor-ak-portal' ),
);
?>
<section class="dak-review-request" id="<?php echo esc_attr( Review_Requests::PANEL_ANCHOR ); ?>" aria-labelledby="dak-review-request-title" tabindex="-1">
	<?php if ( 'valid' === $dak_status ) : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'Review your appointment', 'doctor-ak-portal' ); ?></h3>

		<?php if ( ! empty( $dak_summary ) ) : ?>
			<p class="dak-review-request-summary">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: doctor name, 2: date, 3: clinic or consultation type. */
						__( 'Dr. %1$s · %2$s · %3$s', 'doctor-ak-portal' ),
						$dak_summary['doctor_name'],
						$dak_summary['date_label'],
						$dak_summary['visit_label']
					)
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( '' !== $dak_rr['error'] && isset( $dak_errors[ $dak_rr['error'] ] ) ) : ?>
			<div class="dak-alert dak-alert-error" role="alert"><?php echo esc_html( $dak_errors[ $dak_rr['error'] ] ); ?></div>
		<?php endif; ?>

		<form class="dak-review-request-form" method="post" action="<?php echo esc_url( $dak_rr['action_url'] ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Review_Requests::SUBMIT_ACTION ); ?>">
			<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $doctor['id'] ); ?>">
			<input type="hidden" name="_dak_review_nonce" value="<?php echo esc_attr( $dak_rr['nonce'] ); ?>">

			<fieldset class="dak-review-request-rating">
				<legend><?php esc_html_e( 'Your rating', 'doctor-ak-portal' ); ?></legend>
				<div class="dak-review-request-stars">
					<?php for ( $dak_i = 1; $dak_i <= 5; $dak_i++ ) : ?>
						<input type="radio" id="dak-rr-star-<?php echo esc_attr( $dak_i ); ?>" name="rating" value="<?php echo esc_attr( $dak_i ); ?>" <?php checked( $dak_rr['rating'], $dak_i ); ?> required>
						<label for="dak-rr-star-<?php echo esc_attr( $dak_i ); ?>">
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
				<p class="dak-review-request-hint"><?php esc_html_e( '1 = poor, 5 = excellent. You can change the rating before submitting.', 'doctor-ak-portal' ); ?></p>
			</fieldset>

			<label class="dak-review-request-label" for="dak-rr-comment"><?php esc_html_e( 'Your review (optional)', 'doctor-ak-portal' ); ?></label>
			<textarea id="dak-rr-comment" name="comment" rows="4" maxlength="<?php echo esc_attr( Doctor_Reviews::COMMENT_MAX ); ?>" placeholder="<?php esc_attr_e( 'What went well, and what could be better?', 'doctor-ak-portal' ); ?>"></textarea>

			<p class="dak-review-request-public">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: doctor name. */
						__( 'Your rating and review will be shown publicly on Dr. %s’s profile with your first name only — please don’t include personal or medical details.', 'doctor-ak-portal' ),
						$doctor['name']
					)
				);
				?>
				<?php if ( $dak_rr['has_review'] ) : ?>
					<?php esc_html_e( 'You’ve reviewed this doctor before; submitting replaces your earlier review.', 'doctor-ak-portal' ); ?>
				<?php endif; ?>
			</p>

			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Submit review', 'doctor-ak-portal' ); ?></button>
		</form>

	<?php elseif ( 'submitted' === $dak_status ) : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'Thank you for your review', 'doctor-ak-portal' ); ?></h3>
		<p role="status">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: doctor name. */
					__( 'Your feedback has been submitted and now appears on Dr. %s’s profile.', 'doctor-ak-portal' ),
					$doctor['name']
				)
			);
			?>
		</p>

	<?php elseif ( 'used' === $dak_status ) : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'Review already submitted', 'doctor-ak-portal' ); ?></h3>
		<p><?php esc_html_e( 'This review link has already been used. Thank you for your feedback.', 'doctor-ak-portal' ); ?></p>
		<p><?php esc_html_e( 'To change your review, log in to your account and use the review form on this page.', 'doctor-ak-portal' ); ?></p>

	<?php elseif ( 'wrong_account' === $dak_status ) : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'This link is for a different account', 'doctor-ak-portal' ); ?></h3>
		<p><?php esc_html_e( 'You’re signed in as someone other than the patient this review link was sent to. Sign out and open the link again to leave the review.', 'doctor-ak-portal' ); ?></p>

	<?php elseif ( 'expired' === $dak_status ) : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'This review link has expired', 'doctor-ak-portal' ); ?></h3>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of days. */
					__( 'Review links work for %d days. You can still review this doctor by logging in to your account and using the review form on this page.', 'doctor-ak-portal' ),
					Review_Requests::TOKEN_TTL_DAYS
				)
			);
			?>
		</p>
		<?php if ( '' !== $login_url ) : ?>
			<p><a class="dak-button dak-button-secondary" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in', 'doctor-ak-portal' ); ?></a></p>
		<?php endif; ?>

	<?php elseif ( 'ineligible' === $dak_status ) : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'This appointment can’t be reviewed', 'doctor-ak-portal' ); ?></h3>
		<p><?php esc_html_e( 'Reviews are for completed appointments, and this one is no longer marked as completed. If you think this is a mistake, please contact us.', 'doctor-ak-portal' ); ?></p>

	<?php else : ?>
		<h3 id="dak-review-request-title"><?php esc_html_e( 'This review link isn’t valid', 'doctor-ak-portal' ); ?></h3>
		<p><?php esc_html_e( 'Please use the link from your most recent email exactly as it was sent. If you’re signed in, you can also review this doctor using the form on this page after a completed appointment.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
