<?php
/**
 * AJAX handlers for the dashboard's Symptom Checker section.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Symptom_Rules;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Symptom_Checker_Admin_Handler
 *
 * Administrators only (manage_options) — receptionists never reach the
 * section or these endpoints. Approval additionally requires the user to
 * be a designated clinical reviewer who didn't make the latest draft
 * changes (enforced in Symptom_Rules::approve()).
 */
class Symptom_Checker_Admin_Handler {

	/**
	 * Nonce action shared with doctor-ak-admin-symptom-checker.js.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_admin_symptom_checker';

	/**
	 * Saves the draft JSON (never affects what patients see).
	 *
	 * @return void
	 */
	public function handle_save_draft() {
		$this->guard();

		$result = Symptom_Rules::save_draft( $this->posted_json() );

		if ( ! $result['saved'] ) {
			wp_send_json_error( array( 'message' => implode( ' ', $result['errors'] ) ) );
		}

		wp_send_json_success(
			array(
				'message' => empty( $result['errors'] )
					? __( 'Draft saved. It has no problems and is ready for clinical review.', 'doctor-ak-portal' )
					: __( 'Draft saved, but it has problems that must be fixed before it can be approved.', 'doctor-ak-portal' ),
				'errors'  => $result['errors'],
			)
		);
	}

	/**
	 * Checks JSON without saving it.
	 *
	 * @return void
	 */
	public function handle_validate() {
		$this->guard();

		$parsed = Symptom_Rules::parse( $this->posted_json() );

		wp_send_json_success(
			array(
				'message' => empty( $parsed['errors'] ) ? __( 'No problems found.', 'doctor-ak-portal' ) : __( 'Problems found — see the list.', 'doctor-ak-portal' ),
				'errors'  => $parsed['errors'],
				'is_demo' => $parsed['rules'] && Symptom_Rules::is_demo( $parsed['rules'] ),
			)
		);
	}

	/**
	 * Replaces the draft with the bundled demonstration rules.
	 *
	 * @return void
	 */
	public function handle_load_demo() {
		$this->guard();

		$json = Symptom_Rules::demo_json();

		if ( '' === $json ) {
			wp_send_json_error( array( 'message' => __( 'The demonstration rules file is missing.', 'doctor-ak-portal' ) ) );
		}

		Symptom_Rules::save_draft( $json );

		wp_send_json_success( array( 'message' => __( 'Demonstration rules loaded into the draft. They are for testing only and can never be approved.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Approves the draft as a new published version.
	 *
	 * @return void
	 */
	public function handle_approve() {
		$this->guard();

		if ( empty( $_POST['confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
			wp_send_json_error( array( 'message' => __( 'Please confirm that you have clinically reviewed the whole draft.', 'doctor-ak-portal' ) ) );
		}

		$note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$result = Symptom_Rules::approve( get_current_user_id(), $note );

		if ( is_wp_error( $result ) ) {
			$errors = $result->get_error_data();

			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'errors'  => is_array( $errors ) ? $errors : array(),
				)
			);
		}

		wp_send_json_success(
			array(
				/* translators: %d: version number. */
				'message' => sprintf( __( 'Approved and published as version %d.', 'doctor-ak-portal' ), $result['version'] ),
			)
		);
	}

	/**
	 * Switches the public checker on or off.
	 *
	 * @return void
	 */
	public function handle_toggle() {
		$this->guard();

		$enabled = ! empty( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$result  = Symptom_Rules::set_enabled( $enabled );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => $enabled ? __( 'The Symptom Checker is now available to patients.', 'doctor-ak-portal' ) : __( 'The Symptom Checker is now hidden from patients.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * Saves the clinical reviewer list.
	 *
	 * @return void
	 */
	public function handle_save_reviewers() {
		$this->guard();

		$ids   = isset( $_POST['reviewers'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['reviewers'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in guard(); absint()ed.
		$saved = Symptom_Rules::save_reviewers( $ids );

		wp_send_json_success(
			array(
				/* translators: %d: number of reviewers. */
				'message' => sprintf( _n( '%d clinical reviewer saved.', '%d clinical reviewers saved.', count( $saved ), 'doctor-ak-portal' ), count( $saved ) ),
			)
		);
	}

	/**
	 * The posted ruleset JSON.
	 *
	 * @return string
	 */
	private function posted_json() {
		// Raw JSON: decoded and every string stripped of markup in Symptom_Rules::parse().
		return isset( $_POST['json'] ) ? (string) wp_unslash( $_POST['json'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in guard(); sanitized on decode.
	}

	/**
	 * Nonce + administrator check for every handler in this class.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'doctor-ak-portal' ) ), 403 );
		}
	}
}
