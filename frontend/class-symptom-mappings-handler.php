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
use DoctorAKPortal\Includes\Symptom_Audit;
use DoctorAKPortal\Includes\Symptom_Mappings;
use DoctorAKPortal\Includes\Symptom_Safety_Rules;
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
 * - edit (symptoms, safety rules, import, examples, legacy copy, notice
 *   text), submit for review, archive, restore, export, history:
 *   administrators and clinical reviewers (Symptom_Mappings::can_manage());
 * - approve / return / activate / deactivate / approve notice: clinical
 *   reviewers only (Symptom_Mappings::REVIEW_CAP) — enforced in
 *   Clinical_Workflow;
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

	/** Largest import accepted, in bytes. */
	const MAX_IMPORT_BYTES = 1048576;

	/** Lifecycle actions the screen can request. */
	const TRANSITIONS = array( 'submit', 'approve', 'review', 'return', 'activate', 'deactivate', 'archive', 'restore' );

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
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'pageUrl'  => Page_Finder::url_for_shortcode( Symptom_Checker::SHORTCODE_TAG ),
				'state'    => self::screen_state(),
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
			'rules'       => Symptom_Safety_Rules::for_admin(),
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
			'languages'   => Symptom_Mappings::languages(),
			'csvColumns'  => Symptom_Mappings::csv_columns(),
			'limits'      => array(
				'questions' => Symptom_Mappings::MAX_QUESTIONS,
				'options'   => Symptom_Mappings::MAX_OPTIONS,
				'triggers'  => Symptom_Safety_Rules::MAX_TRIGGERS,
			),
			'can'         => array(
				'manage' => Symptom_Mappings::can_manage(),
				'review' => Symptom_Mappings::can_review(),
				'admin'  => $is_admin,
			),
			'userId'        => get_current_user_id(),
			// So the screen can explain why an approval is blocked (nobody
			// approves their own edit) without listing other users.
			'reviewerCount' => count( Symptom_Mappings::reviewers() ),
			'legacyCount'   => count( Symptom_Mappings::legacy_rows() ),
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

		if ( in_array( $op, self::TRANSITIONS, true ) ) {
			$this->transition( 'symptom', $id, $op, $user_id );
		}

		if ( 0 === strpos( $op, 'rule_' ) && in_array( substr( $op, 5 ), self::TRANSITIONS, true ) ) {
			$this->transition( 'rule', $id, substr( $op, 5 ), $user_id );
		}

		switch ( $op ) {
			case 'state':
				$this->ok( '' );
				break;

			case 'save':
				$this->save_symptom( $id, $user_id );
				break;

			case 'delete':
				$this->require_admin( $admin );
				$this->result( Symptom_Mappings::delete( $id, $user_id ), __( 'Symptom deleted. Its history is kept in the audit log.', 'doctor-ak-portal' ) );
				break;

			case 'bulk':
				$this->bulk( $user_id, $admin );
				break;

			case 'import_preview':
				$rows = Symptom_Mappings::parse_import( $this->import_text() );
				wp_send_json_success(
					array(
						'preview' => Symptom_Mappings::preview_import( $rows ),
						'state'   => self::screen_state(),
					)
				);
				break;

			case 'import':
				$this->import_result( Symptom_Mappings::import( Symptom_Mappings::parse_import( $this->import_text() ), $user_id, 'import' ) );
				break;

			case 'export':
				wp_send_json_success(
					array(
						'csv'      => Symptom_Mappings::export_csv(),
						'filename' => 'symptom-mappings-' . gmdate( 'Y-m-d' ) . '.csv',
					)
				);
				break;

			case 'examples':
				$this->import_result( Symptom_Mappings::import( Symptom_Mappings::example_rows(), $user_id, 'example' ) );
				break;

			case 'legacy':
				$this->import_result( Symptom_Mappings::import( Symptom_Mappings::legacy_rows(), $user_id, 'legacy' ) );
				break;

			case 'history':
				$type = sanitize_key( $this->text( 'type' ) );

				if ( ! in_array( $type, array( Symptom_Audit::TYPE_SYMPTOM, Symptom_Audit::TYPE_RULE, Symptom_Audit::TYPE_NOTICE, Symptom_Audit::TYPE_PAGE ), true ) ) {
					wp_send_json_error( array( 'message' => __( 'Unknown request.', 'doctor-ak-portal' ) ) );
				}

				wp_send_json_success( array( 'history' => Symptom_Audit::for_object( $type, $id ) ) );
				break;

			case 'history_snapshot':
				$entry = Symptom_Audit::snapshot( absint( $this->text( 'entry' ) ) );

				if ( ! $entry ) {
					wp_send_json_error( array( 'message' => __( 'That version could not be found.', 'doctor-ak-portal' ) ) );
				}

				wp_send_json_success( array( 'entry' => $entry ) );
				break;

			case 'rule_save':
				$this->save_rule( $id, $user_id );
				break;

			case 'rule_delete':
				$this->require_admin( $admin );
				$this->result( Symptom_Safety_Rules::delete( $id, $user_id ), __( 'Safety rule deleted. Its history is kept in the audit log.', 'doctor-ak-portal' ) );
				break;

			case 'notice_save':
				$this->result( Symptom_Mappings::save_notice( $this->raw( 'text' ), $user_id ), __( 'Notice saved. Patients keep seeing the approved wording until a clinical reviewer approves this version.', 'doctor-ak-portal' ) );
				break;

			case 'notice_approve':
				$this->result( Symptom_Mappings::approve_notice( $user_id ), __( 'Emergency notice approved.', 'doctor-ak-portal' ) );
				break;

			case 'toggle':
				$this->require_admin( $admin );
				$on = '1' === $this->text( 'enabled' );
				$this->result( Symptom_Mappings::set_enabled( $on ), $on ? __( 'The page is now available to patients.', 'doctor-ak-portal' ) : __( 'The page is now hidden from patients.', 'doctor-ak-portal' ) );
				break;

			case 'reviewer_add':
			case 'reviewer_remove':
				$this->require_admin( $admin );
				$this->result( Symptom_Mappings::set_reviewer( absint( $this->text( 'user_id' ) ), 'reviewer_add' === $op ), 'reviewer_add' === $op ? __( 'Clinical reviewer added.', 'doctor-ak-portal' ) : __( 'Clinical reviewer removed.', 'doctor-ak-portal' ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown request.', 'doctor-ak-portal' ) ) );
		}
	}

	/**
	 * Saves a symptom from the editor.
	 *
	 * @param int $id      Symptom ID (0 = new).
	 * @param int $user_id Acting user.
	 * @return void
	 */
	private function save_symptom( $id, $user_id ) {
		$phrases = array();

		foreach ( array_keys( Symptom_Mappings::languages() ) as $code ) {
			$phrases[ $code ] = $this->multiline( 'phrases_' . $code );
		}

		$result = Symptom_Mappings::save(
			array(
				'name'        => $this->text( 'name' ),
				'slug'        => $this->text( 'slug' ),
				'description' => $this->multiline( 'description' ),
				'synonyms'    => $this->multiline( 'synonyms' ),
				'phrases'     => $phrases,
				'specialties' => $this->text_list( 'specialties' ),
				'questions'   => $this->raw( 'questions' ),
				'notes'       => $this->multiline( 'notes' ),
			),
			$id,
			$user_id
		);

		if ( is_wp_error( $result ) ) {
			$this->fail_with_fields( $result );
		}

		if ( ! $id ) {
			$this->ok( __( 'Added as a draft. Submit it for review when it’s ready.', 'doctor-ak-portal' ) );
		}

		if ( Symptom_Mappings::STATUS_DRAFT !== $result['status'] ) {
			$this->ok( __( 'Notes saved. The clinical content is unchanged, so its review still stands.', 'doctor-ak-portal' ) );
		}

		$this->ok(
			$result['is_live']
				? __( 'Saved as a draft revision. Patients keep seeing the approved version until this one is reviewed and activated.', 'doctor-ak-portal' )
				: __( 'Saved as a draft. Submit it for review when it’s ready.', 'doctor-ak-portal' )
		);
	}

	/**
	 * Saves a safety rule from the editor.
	 *
	 * @param int $id      Rule ID (0 = new).
	 * @param int $user_id Acting user.
	 * @return void
	 */
	private function save_rule( $id, $user_id ) {
		$result = Symptom_Safety_Rules::save(
			array(
				'title'     => $this->text( 'title' ),
				'level'     => $this->text( 'level' ),
				'message'   => $this->multiline( 'message' ),
				'interrupt' => '1' === $this->text( 'interrupt' ),
				'triggers'  => $this->raw( 'triggers' ),
				'notes'     => $this->multiline( 'notes' ),
			),
			$id,
			$user_id
		);

		if ( is_wp_error( $result ) ) {
			$this->fail_with_fields( $result );
		}

		$message = $result['is_live'] && 'draft' === $result['status']
			? __( 'Saved as a draft revision. Patients keep seeing the approved version until this one is reviewed and activated.', 'doctor-ak-portal' )
			: __( 'Safety rule saved as a draft. Submit it for review when it’s ready.', 'doctor-ak-portal' );

		if ( $result['warnings'] ) {
			$message .= ' ' . implode( ' ', $result['warnings'] );
		}

		$this->ok( $message );
	}

	/**
	 * Runs one lifecycle action and replies.
	 *
	 * @param string $type    'symptom' or 'rule'.
	 * @param int    $id      Item ID.
	 * @param string $action  Action.
	 * @param int    $user_id Acting user.
	 * @return void
	 */
	private function transition( $type, $id, $action, $user_id ) {
		$reason = $this->multiline( 'reason' );
		$result = 'rule' === $type
			? Symptom_Safety_Rules::transition( $id, $action, $user_id, $reason )
			: Symptom_Mappings::transition( $id, $action, $user_id, $reason );

		$labels = array(
			'submit'     => __( 'Submitted for clinical review.', 'doctor-ak-portal' ),
			'approve'    => __( 'Approved. A clinical reviewer can now activate it.', 'doctor-ak-portal' ),
			'review'     => __( 'Approved. A clinical reviewer can now activate it.', 'doctor-ak-portal' ),
			'return'     => __( 'Returned for revision. The reason is shown to the author.', 'doctor-ak-portal' ),
			'activate'   => __( 'Activated — this version is now used on the page (while the page is switched on).', 'doctor-ak-portal' ),
			'deactivate' => __( 'Deactivated — no longer used on the page.', 'doctor-ak-portal' ),
			'archive'    => __( 'Moved to Inactive.', 'doctor-ak-portal' ),
			'restore'    => __( 'Restored as a draft. It needs a new review before it can go live.', 'doctor-ak-portal' ),
		);

		$this->result( $result, $labels[ $action ] );
	}

	/**
	 * Applies an action to several symptoms; reports per-item problems
	 * instead of stopping at the first.
	 *
	 * @param int  $user_id Acting user.
	 * @param bool $admin   Whether they're an administrator.
	 * @return void
	 */
	private function bulk( $user_id, $admin ) {
		$action = sanitize_key( $this->text( 'bulk_action' ) );
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); absint()ed.
		$done   = 0;
		$failed = array();

		if ( 'delete' === $action ) {
			$this->require_admin( $admin );
		} elseif ( ! in_array( $action, self::TRANSITIONS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown request.', 'doctor-ak-portal' ) ) );
		}

		foreach ( array_slice( array_unique( $ids ), 0, Symptom_Mappings::MAX_MAPPINGS ) as $id ) {
			$row    = Symptom_Mappings::find( $id );
			$result = 'delete' === $action ? Symptom_Mappings::delete( $id, $user_id ) : Symptom_Mappings::transition( $id, $action, $user_id );

			if ( is_wp_error( $result ) ) {
				$failed[] = ( $row ? $row['name'] . ': ' : '' ) . $result->get_error_message();
			} else {
				++$done;
			}
		}

		/* translators: %d: number of symptoms. */
		$message = sprintf( _n( '%d symptom updated.', '%d symptoms updated.', $done, 'doctor-ak-portal' ), $done );

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
		$this->ok( sprintf( __( '%1$d added as drafts, %2$d skipped (duplicates or problems). Nothing existing was changed.', 'doctor-ak-portal' ), $result['added'], $result['skipped'] ) );
	}

	/**
	 * Replies with a model call's outcome.
	 *
	 * @param mixed  $result  Result (WP_Error on failure).
	 * @param string $message Success message.
	 * @return void
	 */
	private function result( $result, $message ) {
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'state'   => self::screen_state(),
				)
			);
		}

		$this->ok( $message );
	}

	/**
	 * Replies with field errors from a failed save.
	 *
	 * @param \WP_Error $error Error with field => message data.
	 * @return void
	 */
	private function fail_with_fields( \WP_Error $error ) {
		wp_send_json_error(
			array(
				'message' => $error->get_error_message(),
				'errors'  => (array) $error->get_error_data(),
			)
		);
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
	 * The posted import text, refused when too large.
	 *
	 * @return string
	 */
	private function import_text() {
		$text = $this->raw( 'text' );

		if ( strlen( $text ) > self::MAX_IMPORT_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'That file is too large. Import at most 1 MB at a time.', 'doctor-ak-portal' ) ) );
		}

		return $text;
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
	 * A posted multi-line value.
	 *
	 * @param string $key Field.
	 * @return string
	 */
	private function multiline( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle().
	}

	/**
	 * A posted list of single-line values.
	 *
	 * @param string $key Field.
	 * @return string[]
	 */
	private function text_list( $key ) {
		return isset( $_POST[ $key ] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST[ $key ] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); sanitized per item.
	}

	/**
	 * A posted value cleaned field by field later (JSON, import text).
	 *
	 * @param string $key Field.
	 * @return string
	 */
	private function raw( $key ) {
		return isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); each value is sanitized in Symptom_Mappings / Symptom_Safety_Rules.
	}
}
