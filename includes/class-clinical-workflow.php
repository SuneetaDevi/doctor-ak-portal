<?php
/**
 * Review lifecycle shared by symptoms and safety rules.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clinical_Workflow
 *
 * Every piece of clinical content (a symptom with its follow-up questions,
 * or a safety rule) has two copies:
 *
 * - the working copy, which staff edit, and whose status is
 *   Draft → Pending review → Reviewed → Active (or Inactive);
 * - the published copy: the exact content a clinical reviewer last
 *   activated. Patients only ever receive this, and only while `is_live`.
 *
 * Editing never touches the published copy. A live item keeps showing its
 * last approved version until the edited version has been submitted,
 * approved and activated — so fixing a typo can't silently take an
 * emergency message off the page, and nothing unreviewed can reach it.
 * Taking an item offline (Deactivate) is immediate.
 *
 * Rules enforced here, on the server:
 * - Submit / archive / restore: anyone who can manage content.
 * - Approve, return for revision, activate, deactivate: clinical reviewers.
 * - Content with validation errors can't be submitted, approved or activated.
 */
class Clinical_Workflow {

	const STATUS_DRAFT    = 'draft';
	const STATUS_PENDING  = 'pending';
	const STATUS_REVIEWED = 'reviewed';
	const STATUS_ACTIVE   = 'active';
	const STATUS_INACTIVE = 'inactive';

	const STATUSES = array( 'draft', 'pending', 'reviewed', 'active', 'inactive' );

	const MAX_REASON = 500;

	/**
	 * Workflow fields every item carries, with their defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'status'            => self::STATUS_DRAFT,
			'is_live'           => false,
			'published'         => null,
			'published_version' => 0,
			'published_at'      => '',
			'published_by'      => 0,
			'created_at'        => '',
			'created_by'        => 0,
			'updated_at'        => '',
			'updated_by'        => 0,
			'submitted_at'      => '',
			'submitted_by'      => 0,
			'reviewed_at'       => '',
			'reviewed_by'       => 0,
			'activated_at'      => '',
			'activated_by'      => 0,
			'return_reason'     => '',
		);
	}

	/**
	 * Fills and coerces the workflow fields of a stored row.
	 *
	 * Rows saved before the published copy existed are upgraded here: an
	 * Active row's current content becomes its published copy (it was what
	 * patients saw), so nothing live disappears and nothing new goes live.
	 *
	 * @param array    $row  Stored row.
	 * @param string[] $keys Content fields of this kind of item.
	 * @return array
	 */
	public static function normalize( array $row, array $keys ) {
		$legacy = ! array_key_exists( 'published', $row );
		$row    = wp_parse_args( $row, self::defaults() );

		if ( ! in_array( $row['status'], self::STATUSES, true ) ) {
			$row['status'] = self::STATUS_DRAFT;
		}

		if ( $legacy && self::STATUS_ACTIVE === $row['status'] ) {
			$row['published']         = self::content( $row, $keys );
			$row['is_live']           = true;
			$row['published_version'] = 1;
			$row['published_at']      = $row['activated_at'];
			$row['published_by']      = (int) $row['activated_by'];
		}

		if ( '' === $row['created_at'] ) {
			$row['created_at'] = $row['updated_at'];
			$row['created_by'] = (int) $row['updated_by'];
		}

		foreach ( array( 'published_version', 'published_by', 'created_by', 'updated_by', 'submitted_by', 'reviewed_by', 'activated_by' ) as $int ) {
			$row[ $int ] = (int) $row[ $int ];
		}

		$row['published'] = is_array( $row['published'] ) ? $row['published'] : null;
		$row['is_live']   = (bool) $row['is_live'] && null !== $row['published'];

		return $row;
	}

	/**
	 * The content fields of a row (what review covers).
	 *
	 * @param array    $row  Row.
	 * @param string[] $keys Content fields.
	 * @return array
	 */
	public static function content( array $row, array $keys ) {
		$out = array();

		foreach ( $keys as $key ) {
			$out[ $key ] = isset( $row[ $key ] ) ? $row[ $key ] : null;
		}

		return $out;
	}

	/**
	 * Whether a live item's working copy differs from what patients see.
	 *
	 * @param array    $row  Row.
	 * @param string[] $keys Content fields.
	 * @return bool
	 */
	public static function has_unpublished_changes( array $row, array $keys ) {
		return null !== $row['published'] && self::content( $row, $keys ) !== self::content( $row['published'], $keys );
	}

	/**
	 * Applies an edit to the working copy. A change to any content field
	 * sends the working copy back to Draft (the published copy is kept).
	 *
	 * @param array    $row     Row (normalized).
	 * @param array    $fields  New values (content and non-content fields).
	 * @param string[] $keys    Content fields.
	 * @param int      $user_id Editing user.
	 * @return array { row: array, changed: bool }
	 */
	public static function apply_edit( array $row, array $fields, array $keys, $user_id ) {
		$before = self::content( $row, $keys );
		$row    = array_merge( $row, $fields );

		if ( self::content( $row, $keys ) === $before ) {
			return array(
				'row'     => $row,
				'changed' => false,
			);
		}

		$row = array_merge(
			$row,
			array(
				'status'        => self::STATUS_DRAFT,
				'updated_at'    => current_time( 'mysql' ),
				'updated_by'    => (int) $user_id,
				'submitted_at'  => '',
				'submitted_by'  => 0,
				'reviewed_at'   => '',
				'reviewed_by'   => 0,
				'return_reason' => '',
			)
		);

		return array(
			'row'     => $row,
			'changed' => true,
		);
	}

	/**
	 * Moves an item through the lifecycle.
	 *
	 * @param array    $row     Row (normalized).
	 * @param string   $action  submit | approve | return | activate | deactivate | archive | restore.
	 * @param int      $user_id Acting user.
	 * @param array    $opts    {
	 *     @type bool     $can_manage May edit this kind of content.
	 *     @type bool     $can_review Is a clinical reviewer.
	 *     @type string[] $errors     Validation problems in the working copy.
	 *     @type string   $reason     Why it's being returned (required for 'return').
	 *     @type string[] $keys       Content fields.
	 * }
	 * @return array|\WP_Error Updated row.
	 */
	public static function transition( array $row, $action, $user_id, array $opts ) {
		$opts = wp_parse_args(
			$opts,
			array(
				'can_manage' => false,
				'can_review' => false,
				'errors'     => array(),
				'reason'     => '',
				'keys'       => array(),
			)
		);

		$reviewer_only = array( 'approve', 'return', 'activate', 'deactivate' );

		if ( in_array( $action, $reviewer_only, true ) && ! $opts['can_review'] ) {
			return new \WP_Error( 'doctor_ak_cw_forbidden', __( 'Only a clinical reviewer can do that.', 'doctor-ak-portal' ) );
		}

		if ( ! in_array( $action, $reviewer_only, true ) && ! $opts['can_manage'] ) {
			return new \WP_Error( 'doctor_ak_cw_forbidden', __( 'You do not have permission to do that.', 'doctor-ak-portal' ) );
		}

		$now    = current_time( 'mysql' );
		$status = $row['status'];
		$errors = array_values( array_filter( (array) $opts['errors'] ) );

		switch ( $action ) {
			case 'submit':
				if ( self::STATUS_DRAFT !== $status ) {
					return new \WP_Error( 'doctor_ak_cw_state', __( 'Only drafts can be submitted for review.', 'doctor-ak-portal' ) );
				}

				if ( $errors ) {
					return new \WP_Error( 'doctor_ak_cw_invalid', __( 'Fix these problems before submitting:', 'doctor-ak-portal' ) . ' ' . implode( ' ', $errors ) );
				}

				$row['status']        = self::STATUS_PENDING;
				$row['submitted_at']  = $now;
				$row['submitted_by']  = (int) $user_id;
				$row['return_reason'] = '';
				break;

			case 'approve':
				if ( self::STATUS_PENDING !== $status ) {
					return new \WP_Error( 'doctor_ak_cw_state', __( 'Only items pending review can be approved.', 'doctor-ak-portal' ) );
				}

				if ( $errors ) {
					return new \WP_Error( 'doctor_ak_cw_invalid', implode( ' ', $errors ) );
				}

				$row['status']      = self::STATUS_REVIEWED;
				$row['reviewed_at'] = $now;
				$row['reviewed_by'] = (int) $user_id;
				break;

			case 'return':
				if ( ! in_array( $status, array( self::STATUS_PENDING, self::STATUS_REVIEWED ), true ) ) {
					return new \WP_Error( 'doctor_ak_cw_state', __( 'Only items pending review or reviewed can be returned for revision.', 'doctor-ak-portal' ) );
				}

				$reason = trim( sanitize_textarea_field( (string) $opts['reason'] ) );

				if ( '' === $reason ) {
					return new \WP_Error( 'doctor_ak_cw_reason', __( 'Say what needs to change, so the author knows what to revise.', 'doctor-ak-portal' ) );
				}

				$row['status']        = self::STATUS_DRAFT;
				$row['return_reason'] = function_exists( 'mb_substr' ) ? mb_substr( $reason, 0, self::MAX_REASON ) : substr( $reason, 0, self::MAX_REASON );
				$row['submitted_at']  = '';
				$row['submitted_by']  = 0;
				$row['reviewed_at']   = '';
				$row['reviewed_by']   = 0;
				break;

			case 'activate':
				if ( self::STATUS_REVIEWED === $status ) {
					if ( $errors ) {
						return new \WP_Error( 'doctor_ak_cw_invalid', implode( ' ', $errors ) );
					}

					$row['published']         = self::content( $row, $opts['keys'] );
					$row['published_version'] = (int) $row['published_version'] + 1;
					$row['published_at']      = $now;
					$row['published_by']      = (int) $user_id;
				} elseif ( self::STATUS_INACTIVE === $status && null !== $row['published'] && ! self::has_unpublished_changes( $row, $opts['keys'] ) ) {
					// Re-activating exactly the version that was approved before.
					if ( $errors ) {
						return new \WP_Error( 'doctor_ak_cw_invalid', implode( ' ', $errors ) );
					}
				} else {
					return new \WP_Error( 'doctor_ak_cw_state', __( 'It must be reviewed before it can be activated.', 'doctor-ak-portal' ) );
				}

				$row['status']       = self::STATUS_ACTIVE;
				$row['is_live']      = true;
				$row['activated_at'] = $now;
				$row['activated_by'] = (int) $user_id;
				break;

			case 'deactivate':
				if ( ! $row['is_live'] ) {
					return new \WP_Error( 'doctor_ak_cw_state', __( 'This is not live.', 'doctor-ak-portal' ) );
				}

				$row['is_live'] = false;

				if ( self::STATUS_ACTIVE === $status ) {
					$row['status'] = self::STATUS_INACTIVE;
				}
				break;

			case 'archive':
				if ( $row['is_live'] ) {
					return new \WP_Error( 'doctor_ak_cw_state', __( 'Deactivate it first — a clinical reviewer takes live content off the page.', 'doctor-ak-portal' ) );
				}

				if ( self::STATUS_INACTIVE === $status ) {
					return new \WP_Error( 'doctor_ak_cw_state', __( 'It is already inactive.', 'doctor-ak-portal' ) );
				}

				$row['status'] = self::STATUS_INACTIVE;
				break;

			case 'restore':
				if ( self::STATUS_INACTIVE !== $status || $row['is_live'] ) {
					return new \WP_Error( 'doctor_ak_cw_state', __( 'Only inactive items can be restored.', 'doctor-ak-portal' ) );
				}

				$row['status']      = self::STATUS_DRAFT;
				$row['reviewed_at'] = '';
				$row['reviewed_by'] = 0;
				break;

			default:
				return new \WP_Error( 'doctor_ak_cw_action', __( 'Unknown action.', 'doctor-ak-portal' ) );
		}

		return $row;
	}

	/**
	 * Summary for the audit trail of a transition.
	 *
	 * @param string $action Action.
	 * @param array  $row    Row after the transition.
	 * @return string
	 */
	public static function audit_summary( $action, array $row ) {
		if ( 'return' === $action ) {
			return $row['return_reason'];
		}

		if ( 'activate' === $action ) {
			/* translators: %d: published version number. */
			return sprintf( __( 'Version %d is live.', 'doctor-ak-portal' ), (int) $row['published_version'] );
		}

		return '';
	}

	/**
	 * Admin-facing fields describing the item's state.
	 *
	 * @param array    $row  Row.
	 * @param string[] $keys Content fields.
	 * @return array
	 */
	public static function admin_fields( array $row, array $keys ) {
		$name = function ( $user_id ) {
			$user = $user_id ? get_userdata( $user_id ) : false;
			return $user ? $user->display_name : '';
		};

		return array(
			'is_live'          => (bool) $row['is_live'],
			'pending_changes'  => $row['is_live'] && self::has_unpublished_changes( $row, $keys ),
			'created_by_name'  => $name( (int) $row['created_by'] ),
			'updated_by_name'  => $name( (int) $row['updated_by'] ),
			'submitted_by_name' => $name( (int) $row['submitted_by'] ),
			'reviewed_by_name' => $name( (int) $row['reviewed_by'] ),
			'activated_by_name' => $name( (int) $row['activated_by'] ),
		);
	}
}
