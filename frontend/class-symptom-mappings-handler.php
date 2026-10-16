<?php
/**
 * AJAX endpoint for the "Symptom mappings" screen.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Specializations;
use DoctorAKPortal\Includes\Symptom_Mappings;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Symptom_Mappings_Handler
 *
 * One action (`doctor_ak_symptom_mappings`) with an `op` parameter. Every
 * op checks the nonce and its own permission on the server:
 *
 * - edit (save, import, examples, legacy copy, notice text): administrators
 *   and clinical reviewers (Symptom_Mappings::can_manage());
 * - review / activate / deactivate / approve notice: clinical reviewers only
 *   (Symptom_Mappings::REVIEW_CAP), never on their own latest edit;
 * - delete, switch on/off, choose reviewers: administrators only.
 *
 * Every response carries the screen's fresh state so it can redraw.
 */
class Symptom_Mappings_Handler {

	/**
	 * Nonce action shared with doctor-ak-symptom-mappings-admin.js.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'doctor_ak_symptom_mappings';

	/**
	 * Enqueues the screen's script and styles (admin dashboard section and
	 * the reviewers' doctor-dashboard tab). Callers check permission first.
	 *
	 * @return void
	 */
	public static function enqueue_screen_assets() {
		wp_enqueue_style( 'doctor-ak-portal-symptom-mappings', DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-symptom-mappings.css', array(), Assets::version( 'assets/css/doctor-ak-symptom-mappings.css' ) );
		wp_enqueue_script( 'doctor-ak-portal-symptom-match', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-symptom-match.js', array(), Assets::version( 'assets/js/doctor-ak-symptom-match.js' ), true );
		wp_enqueue_script( 'doctor-ak-portal-symptom-mappings', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-symptom-mappings-admin.js', array( 'doctor-ak-portal-symptom-match' ), Assets::version( 'assets/js/doctor-ak-symptom-mappings-admin.js' ), true );

		wp_localize_script(
			'doctor-ak-portal-symptom-mappings',
			'dakSymptomMappings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'state'   => self::screen_state(),
			)
		);
	}

	/**
	 * The screen's markup.
	 *
	 * @param Template_Loader $template_loader Template loader.
	 * @return string
	 */
	public static function screen_html( Template_Loader $template_loader ) {
		if ( ! Symptom_Mappings::can_manage() ) {
			return '';
		}

		return $template_loader->get_template(
			'dashboard/partials/symptom-mappings.php',
			array(
				'page_url' => Page_Finder::url_for_shortcode( Symptom_Checker::SHORTCODE_TAG ),
				'is_admin' => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Everything the screen shows, for the current user.
	 *
	 * @return array
	 */
	public static function screen_state() {
		$is_admin  = current_user_can( 'manage_options' );
		$notice    = Symptom_Mappings::notice();
		$user_name = function ( $user_id ) {
			$user = $user_id ? get_userdata( $user_id ) : false;
			return $user ? $user->display_name : '';
		};

		$state = array(
			'mappings'    => Symptom_Mappings::for_admin(),
			'notice'      => array_merge(
				$notice,
				array(
					'updated_by_name'  => $user_name( (int) $notice['updated_by'] ),
					'approved_by_name' => $user_name( (int) $notice['approved_by'] ),
				)
			),
			'enabled'     => Symptom_Mappings::is_enabled(),
			'isPublic'    => Symptom_Mappings::is_public(),
			'missing'     => Symptom_Mappings::missing_requirements(),
			'specialties' => Specializations::get_all(),
			'can'         => array(
				'manage' => Symptom_Mappings::can_manage(),
				'review' => Symptom_Mappings::can_review(),
				'admin'  => $is_admin,
			),
			'userId'      => get_current_user_id(),
			'legacyCount' => count( Symptom_Mappings::legacy_rows() ),
		);

		if ( $is_admin ) {
			$state['reviewers'] = array_map(
				function ( $user ) {
					return array(
						'id'   => (int) $user->ID,
						'name' => $user->display_name,
						'role' => in_array( 'administrator', (array) $user->roles, true ) ? __( 'Administrator', 'doctor-ak-portal' ) : __( 'Doctor', 'doctor-ak-portal' ),
					);
				},
				Symptom_Mappings::reviewers()
			);
			$state['candidates'] = array_map(
				function ( $user ) {
					return array(
						'id'   => (int) $user->ID,
						'name' => $user->display_name,
					);
				},
				Symptom_Mappings::reviewer_candidates()
			);
		}

		return $state;
	}

	/**
	 * Handles every op.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		if ( ! Symptom_Mappings::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'doctor-ak-portal' ) ), 403 );
		}

		$op      = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$user_id = get_current_user_id();
		$id      = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$admin   = current_user_can( 'manage_options' );

		switch ( $op ) {
			case 'state':
				$this->ok( '' );
				break;

			case 'save':
				$result = Symptom_Mappings::save(
					array(
						'name'        => $this->text( 'name' ),
						'synonyms'    => isset( $_POST['synonyms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['synonyms'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
						'specialties' => isset( $_POST['specialties'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['specialties'] ) ) : array(), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; sanitized per item.
						'notes'       => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
					),
					$id,
					$user_id
				);

				if ( is_wp_error( $result ) ) {
					wp_send_json_error(
						array(
							'message' => $result->get_error_message(),
							'errors'  => (array) $result->get_error_data(),
						)
					);
				}

				if ( ! $id ) {
					$this->ok( __( 'Added as a draft.', 'doctor-ak-portal' ) );
				}

				$this->ok( Symptom_Mappings::STATUS_DRAFT === $result['status'] ? __( 'Saved. The mapping is a draft until a reviewer checks it.', 'doctor-ak-portal' ) : __( 'Notes saved. The symptom, phrases and specialties are unchanged, so its review still stands.', 'doctor-ak-portal' ) );
				break;

			case 'delete':
				$this->require_admin( $admin );
				Symptom_Mappings::delete( $id );
				$this->ok( __( 'Mapping deleted.', 'doctor-ak-portal' ) );
				break;

			case 'review':
			case 'activate':
			case 'deactivate':
				$result = Symptom_Mappings::transition( $id, $op, $user_id );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$labels = array(
					'review'     => __( 'Marked as reviewed.', 'doctor-ak-portal' ),
					'activate'   => __( 'Activated — patients can now match this symptom (once the page is switched on).', 'doctor-ak-portal' ),
					'deactivate' => __( 'Deactivated — no longer used on the page.', 'doctor-ak-portal' ),
				);
				$this->ok( $labels[ $op ] );
				break;

			case 'bulk':
				$this->bulk( $user_id, $admin );
				break;

			case 'import_preview':
				$rows = Symptom_Mappings::parse_import( $this->raw( 'text' ) );
				wp_send_json_success(
					array(
						'preview' => Symptom_Mappings::preview_import( $rows ),
						'state'   => self::screen_state(),
					)
				);
				break;

			case 'import':
				$this->import_result( Symptom_Mappings::import( Symptom_Mappings::parse_import( $this->raw( 'text' ) ), $user_id, 'import' ) );
				break;

			case 'examples':
				$this->import_result( Symptom_Mappings::import( Symptom_Mappings::example_rows(), $user_id, 'example' ) );
				break;

			case 'legacy':
				$this->import_result( Symptom_Mappings::import( Symptom_Mappings::legacy_rows(), $user_id, 'legacy' ) );
				break;

			case 'notice_save':
				$result = Symptom_Mappings::save_notice( $this->raw( 'text' ), $user_id );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$this->ok( __( 'Notice saved as a draft. A clinical reviewer needs to approve it.', 'doctor-ak-portal' ) );
				break;

			case 'notice_approve':
				$result = Symptom_Mappings::approve_notice( $user_id );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$this->ok( __( 'Emergency notice approved.', 'doctor-ak-portal' ) );
				break;

			case 'toggle':
				$this->require_admin( $admin );
				$on     = ! empty( $_POST['enabled'] ) && '1' === $this->text( 'enabled' );
				$result = Symptom_Mappings::set_enabled( $on );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$this->ok( $on ? __( 'The page is now available to patients.', 'doctor-ak-portal' ) : __( 'The page is now hidden from patients.', 'doctor-ak-portal' ) );
				break;

			case 'reviewer_add':
			case 'reviewer_remove':
				$this->require_admin( $admin );
				$result = Symptom_Mappings::set_reviewer( absint( $this->text( 'user_id' ) ), 'reviewer_add' === $op );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$this->ok( 'reviewer_add' === $op ? __( 'Clinical reviewer added.', 'doctor-ak-portal' ) : __( 'Clinical reviewer removed.', 'doctor-ak-portal' ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown request.', 'doctor-ak-portal' ) ) );
		}
	}

	/**
	 * Applies review/activate/delete to several mappings; reports per-item
	 * problems instead of stopping at the first.
	 *
	 * @param int  $user_id Acting user.
	 * @param bool $admin   Whether they're an administrator.
	 * @return void
	 */
	private function bulk( $user_id, $admin ) {
		$action = $this->text( 'bulk_action' );
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); absint()ed.
		$done   = 0;
		$failed = array();

		if ( 'delete' === $action ) {
			$this->require_admin( $admin );
		}

		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				Symptom_Mappings::delete( $id );
				++$done;
				continue;
			}

			$result = Symptom_Mappings::transition( $id, $action, $user_id );

			if ( is_wp_error( $result ) ) {
				$row      = Symptom_Mappings::find( $id );
				$failed[] = ( $row ? $row['name'] . ': ' : '' ) . $result->get_error_message();
			} else {
				++$done;
			}
		}

		/* translators: %d: number of mappings. */
		$message = sprintf( _n( '%d mapping updated.', '%d mappings updated.', $done, 'doctor-ak-portal' ), $done );

		wp_send_json_success(
			array(
				'message' => $message,
				'failed'  => $failed,
				'state'   => self::screen_state(),
			)
		);
	}

	/**
	 * Sends an import's outcome.
	 *
	 * @param array $result { added, skipped }.
	 * @return void
	 */
	private function import_result( array $result ) {
		/* translators: 1: number added, 2: number skipped. */
		$this->ok( sprintf( __( '%1$d added as drafts, %2$d skipped (duplicates or problems).', 'doctor-ak-portal' ), $result['added'], $result['skipped'] ) );
	}

	/**
	 * Success with fresh state.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function ok( $message ) {
		wp_send_json_success(
			array(
				'message' => $message,
				'state'   => self::screen_state(),
			)
		);
	}

	/**
	 * Stops unless the user is an administrator.
	 *
	 * @param bool $admin Whether they are.
	 * @return void
	 */
	private function require_admin( $admin ) {
		if ( ! $admin ) {
			wp_send_json_error( array( 'message' => __( 'Only an administrator can do that.', 'doctor-ak-portal' ) ), 403 );
		}
	}

	/**
	 * A posted single-line value.
	 *
	 * @param string $key Field.
	 * @return string
	 */
	private function text( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle().
	}

	/**
	 * A posted multi-line value (cleaned field by field later).
	 *
	 * @param string $key Field.
	 * @return string
	 */
	private function raw( $key ) {
		return isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); each value is sanitized in Symptom_Mappings.
	}
}
