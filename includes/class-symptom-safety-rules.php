<?php
/**
 * Clinician-written safety messages for "Find a doctor by symptoms".
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Symptom_Safety_Rules
 *
 * A safety rule is a message a clinician wrote — e.g. "Get emergency help
 * now" with instructions — plus the answers that show it:
 *
 * - a symptom being selected, or
 * - an answer to one of that symptom's follow-up questions (chosen
 *   options, or a number within a range the clinician entered).
 *
 * Any one trigger shows the rule. Rules never score, rank or rule anything
 * out; they only show the reviewed instructions, immediately, above any
 * doctor suggestions. "Emergency" rules always stop the remaining
 * questions; "Urgent" rules do when `interrupt` is set.
 *
 * Same lifecycle as symptoms (Clinical_Workflow): a live rule keeps its
 * approved wording and triggers until a revision is approved and activated.
 * A rule can only be activated when every trigger points at a live symptom
 * whose live version contains that question and those answers, so a rule
 * that looks live can always actually fire.
 */
class Symptom_Safety_Rules {

	const OPTION_RULES   = 'doctor_ak_symptom_safety_rules';
	const OPTION_NEXT_ID = 'doctor_ak_symptom_safety_rules_next_id';

	const LEVELS = array( 'emergency', 'urgent' );

	const MAX_RULES    = 200;
	const MAX_TRIGGERS = 20;
	const MAX_TITLE    = 120;
	const MAX_MESSAGE  = 1000;

	/** Fields covered by clinical review. */
	const CONTENT_KEYS = array( 'title', 'level', 'message', 'interrupt', 'triggers' );

	/**
	 * Normalized rows for this request (cleared on every write).
	 *
	 * @var array[]|null
	 */
	private static $cache = null;

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------- */

	/**
	 * Every rule, in title order.
	 *
	 * @return array[]
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$rows = get_option( self::OPTION_RULES, array() );
		$rows = is_array( $rows ) ? array_values( array_filter( array_map( array( __CLASS__, 'normalize_row' ), $rows ) ) ) : array();

		usort(
			$rows,
			function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
		);

		self::$cache = $rows;

		return $rows;
	}

	/**
	 * One rule by ID.
	 *
	 * @param int $id Rule ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		foreach ( self::all() as $row ) {
			if ( (int) $row['id'] === (int) $id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * What the patient page receives: published copies of live rules (or,
	 * for a staff preview, working copies of everything not inactive).
	 *
	 * @param bool $preview Staff preview.
	 * @return array[] Each { id, title, level, message, interrupt, triggers[] }.
	 */
	public static function public_items( $preview = false ) {
		$out = array();

		foreach ( self::all() as $row ) {
			if ( $preview ) {
				if ( Clinical_Workflow::STATUS_INACTIVE === $row['status'] && ! $row['is_live'] ) {
					continue;
				}

				$content = Clinical_Workflow::content( $row, self::CONTENT_KEYS );
			} elseif ( $row['is_live'] ) {
				$content = self::normalize_content( $row['published'] );
			} else {
				continue;
			}

			$out[] = array_merge( array( 'id' => (int) $row['id'] ), $content );
		}

		return $out;
	}

	/**
	 * Fills defaults; drops malformed rows.
	 *
	 * @param mixed $row Stored row.
	 * @return array|null
	 */
	private static function normalize_row( $row ) {
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			return null;
		}

		$row          = array_merge( wp_parse_args( $row, array( 'notes' => '' ) ), self::normalize_content( $row ) );
		$row['id']    = (int) $row['id'];
		$row['notes'] = (string) $row['notes'];

		return Clinical_Workflow::normalize( $row, self::CONTENT_KEYS );
	}

	/**
	 * Content fields with defaults and types.
	 *
	 * @param array $content Raw content.
	 * @return array
	 */
	private static function normalize_content( array $content ) {
		$content = wp_parse_args(
			$content,
			array(
				'title'     => '',
				'level'     => 'emergency',
				'message'   => '',
				'interrupt' => true,
				'triggers'  => array(),
			)
		);

		$level = in_array( $content['level'], self::LEVELS, true ) ? $content['level'] : 'emergency';

		return array(
			'title'     => (string) $content['title'],
			'level'     => $level,
			'message'   => (string) $content['message'],
			'interrupt' => 'emergency' === $level ? true : (bool) $content['interrupt'],
			'triggers'  => array_values( array_filter( (array) $content['triggers'], 'is_array' ) ),
		);
	}

	/**
	 * Writes the whole list.
	 *
	 * @param array[] $rows Rows.
	 * @return void
	 */
	private static function store( array $rows ) {
		update_option( self::OPTION_RULES, array_values( $rows ), false );
		self::$cache = null;
	}

	/**
	 * Next free ID.
	 *
	 * @return int
	 */
	private static function next_id() {
		$id = max( 1, (int) get_option( self::OPTION_NEXT_ID, 1 ) );

		foreach ( self::all() as $row ) {
			$id = max( $id, (int) $row['id'] + 1 );
		}

		update_option( self::OPTION_NEXT_ID, $id + 1, false );

		return $id;
	}

	/* ---------------------------------------------------------------------
	 * Validation
	 * ------------------------------------------------------------------- */

	/**
	 * A symptom's question by ID within one copy of its content.
	 *
	 * @param array|null $content Symptom content (working or published).
	 * @param string     $qid     Question ID.
	 * @return array|null
	 */
	private static function question_in( $content, $qid ) {
		if ( ! is_array( $content ) || empty( $content['questions'] ) ) {
			return null;
		}

		foreach ( (array) $content['questions'] as $question ) {
			if ( isset( $question['id'] ) && $question['id'] === $qid ) {
				return $question;
			}
		}

		return null;
	}

	/**
	 * Whether one trigger can fire against one copy of a symptom.
	 *
	 * @param array      $trigger Clean trigger.
	 * @param array|null $content Symptom content.
	 * @return bool
	 */
	private static function trigger_fits( array $trigger, $content ) {
		if ( ! is_array( $content ) ) {
			return false;
		}

		if ( '' === $trigger['question'] ) {
			return true;
		}

		$question = self::question_in( $content, $trigger['question'] );

		if ( ! $question ) {
			return false;
		}

		if ( 'number' === $question['type'] ) {
			return null !== $trigger['min'] || null !== $trigger['max'];
		}

		$valid = wp_list_pluck( (array) $question['options'], 'id' );

		return $trigger['answers'] && ! array_diff( $trigger['answers'], $valid );
	}

	/**
	 * Cleans and checks a rule.
	 *
	 * @param array $input { title, level, message, interrupt, triggers (array or JSON), notes }.
	 * @return array { fields: array, errors: array field => message, warnings: string[] }
	 */
	public static function validate( array $input ) {
		$errors   = array();
		$warnings = array();
		$title    = trim( sanitize_text_field( isset( $input['title'] ) ? (string) $input['title'] : '' ) );
		$message  = trim( sanitize_textarea_field( isset( $input['message'] ) ? (string) $input['message'] : '' ) );
		$level    = isset( $input['level'] ) && in_array( $input['level'], self::LEVELS, true ) ? $input['level'] : '';

		if ( '' === $title ) {
			$errors['title'] = __( 'Enter a heading, e.g. “Get emergency help now”.', 'doctor-ak-portal' );
		} elseif ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title ) ) > self::MAX_TITLE ) {
			/* translators: %d: maximum length. */
			$errors['title'] = sprintf( __( 'Keep the heading to %d characters.', 'doctor-ak-portal' ), self::MAX_TITLE );
		}

		if ( '' === $level ) {
			$errors['level'] = __( 'Choose how the message is shown.', 'doctor-ak-portal' );
			$level           = 'emergency';
		}

		if ( '' === $message ) {
			$errors['message'] = __( 'Enter the instructions patients should follow.', 'doctor-ak-portal' );
		} elseif ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $message ) : strlen( $message ) ) > self::MAX_MESSAGE ) {
			/* translators: %d: maximum length. */
			$errors['message'] = sprintf( __( 'Keep the instructions to %d characters.', 'doctor-ak-portal' ), self::MAX_MESSAGE );
		}

		// Wording the patient page must never show: it implies something
		// has been ruled out.
		if ( preg_match( '/\b(you are safe|you\'re safe|no need (to|for) (see|a) doctor|do(es)? not need medical|nothing (serious|to worry)|ruled out)\b/i', $title . ' ' . $message ) ) {
			$errors['message'] = __( 'Safety messages must not reassure patients that something is ruled out or that they don’t need care.', 'doctor-ak-portal' );
		}

		$raw = isset( $input['triggers'] ) ? $input['triggers'] : array();

		if ( is_string( $raw ) ) {
			$raw = '' === trim( $raw ) ? array() : json_decode( $raw, true );
			$raw = is_array( $raw ) ? $raw : array();
		}

		$symptoms = array();

		foreach ( Symptom_Mappings::all() as $symptom ) {
			$symptoms[ (int) $symptom['id'] ] = $symptom;
		}

		$triggers = array();
		$seen     = array();

		foreach ( array_values( array_slice( (array) $raw, 0, self::MAX_TRIGGERS ) ) as $i => $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}

			/* translators: %d: trigger number. */
			$where   = sprintf( __( 'When %d:', 'doctor-ak-portal' ), $i + 1 );
			$sid     = isset( $t['symptom'] ) ? absint( $t['symptom'] ) : 0;
			$qid     = isset( $t['question'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $t['question'] ) ) : '';
			$clean   = array(
				'symptom'  => $sid,
				'question' => $qid,
				'answers'  => array_values( array_unique( array_filter( array_map( 'sanitize_key', isset( $t['answers'] ) ? (array) $t['answers'] : array() ), 'strlen' ) ) ),
				'min'      => isset( $t['min'] ) && is_numeric( $t['min'] ) ? 0 + $t['min'] : null,
				'max'      => isset( $t['max'] ) && is_numeric( $t['max'] ) ? 0 + $t['max'] : null,
			);
			$symptom = isset( $symptoms[ $sid ] ) ? $symptoms[ $sid ] : null;

			if ( ! $symptom ) {
				$errors['triggers'][] = $where . ' ' . __( 'choose a symptom.', 'doctor-ak-portal' );
				continue;
			}

			if ( '' !== $qid ) {
				$question = self::question_in( $symptom, $qid );
				$question = $question ? $question : self::question_in( $symptom['published'], $qid );

				if ( ! $question ) {
					/* translators: %s: symptom name. */
					$errors['triggers'][] = $where . ' ' . sprintf( __( 'that question is no longer part of “%s”.', 'doctor-ak-portal' ), $symptom['name'] );
					continue;
				}

				if ( 'number' === $question['type'] ) {
					$clean['answers'] = array();

					if ( null === $clean['min'] && null === $clean['max'] ) {
						$errors['triggers'][] = $where . ' ' . __( 'enter the lowest and/or highest answer that shows this message.', 'doctor-ak-portal' );
					} elseif ( null !== $clean['min'] && null !== $clean['max'] && $clean['min'] > $clean['max'] ) {
						$errors['triggers'][] = $where . ' ' . __( 'the lowest answer is larger than the highest.', 'doctor-ak-portal' );
					}
				} else {
					$clean['min'] = null;
					$clean['max'] = null;

					if ( ! $clean['answers'] ) {
						$errors['triggers'][] = $where . ' ' . __( 'choose which answers show this message.', 'doctor-ak-portal' );
					}
				}

				if ( empty( $question['affects']['safety'] ) ) {
					/* translators: 1: question, 2: symptom. */
					$warnings[] = sprintf( __( '“%1$s” (%2$s) isn’t marked as affecting safety messages. Tick “Safety messages” on that question so reviewers see the link.', 'doctor-ak-portal' ), $question['text'], $symptom['name'] );
				}
			} else {
				$clean['answers'] = array();
				$clean['min']     = null;
				$clean['max']     = null;
			}

			$key = wp_json_encode( $clean );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$triggers[]   = $clean;

			if ( ! $symptom['is_live'] ) {
				/* translators: %s: symptom name. */
				$warnings[] = sprintf( __( '“%s” isn’t live yet, so this can’t show until it is.', 'doctor-ak-portal' ), $symptom['name'] );
			} elseif ( ! self::trigger_fits( $clean, $symptom['published'] ) ) {
				/* translators: %s: symptom name. */
				$warnings[] = sprintf( __( 'The live version of “%s” doesn’t have this question or answer yet — activate its revision first.', 'doctor-ak-portal' ), $symptom['name'] );
			}
		}

		if ( ! $triggers && empty( $errors['triggers'] ) ) {
			$errors['triggers'] = array( __( 'Add at least one “When” — the symptom or answer that shows this message.', 'doctor-ak-portal' ) );
		}

		if ( isset( $errors['triggers'] ) ) {
			$errors['triggers'] = implode( ' ', $errors['triggers'] );
		}

		return array(
			'fields'   => array(
				'title'     => $title,
				'level'     => $level,
				'message'   => $message,
				'interrupt' => 'emergency' === $level ? true : ! empty( $input['interrupt'] ),
				'triggers'  => $triggers,
				'notes'     => isset( $input['notes'] ) ? sanitize_textarea_field( (string) $input['notes'] ) : '',
			),
			'errors'   => $errors,
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * Problems that stop a rule being activated: every trigger must be able
	 * to fire against the live version of its symptom.
	 *
	 * @param array $row Rule row.
	 * @return string[]
	 */
	private static function activation_problems( array $row ) {
		$problems = array();

		foreach ( $row['triggers'] as $trigger ) {
			$symptom = Symptom_Mappings::find( $trigger['symptom'] );

			if ( ! $symptom || ! $symptom['is_live'] ) {
				/* translators: %s: symptom name. */
				$problems[] = sprintf( __( 'Activate the symptom “%s” first — this rule can only show for live symptoms.', 'doctor-ak-portal' ), $symptom ? $symptom['name'] : '#' . $trigger['symptom'] );
			} elseif ( ! self::trigger_fits( $trigger, $symptom['published'] ) ) {
				/* translators: %s: symptom name. */
				$problems[] = sprintf( __( 'The live version of “%s” doesn’t include the question or answer this rule uses. Activate the symptom’s revision first.', 'doctor-ak-portal' ), $symptom['name'] );
			}
		}

		return array_values( array_unique( $problems ) );
	}

	/**
	 * Titles of rules whose triggers mention a symptom.
	 *
	 * @param int  $symptom_id Symptom ID.
	 * @param bool $live_only  Only live rules.
	 * @return string[]
	 */
	public static function referencing_symptom( $symptom_id, $live_only = false ) {
		$titles = array();

		foreach ( self::all() as $rule ) {
			if ( $live_only && ! $rule['is_live'] ) {
				continue;
			}

			$copies = $live_only ? array( $rule['published'] ) : array( $rule, $rule['published'] );

			foreach ( array_filter( $copies ) as $copy ) {
				foreach ( (array) $copy['triggers'] as $trigger ) {
					if ( (int) $trigger['symptom'] === (int) $symptom_id ) {
						$titles[ $rule['id'] ] = $rule['title'];
					}
				}
			}
		}

		return array_values( $titles );
	}

	/**
	 * Problems activating a symptom's new content would cause: a live rule
	 * whose trigger needs a question or answer the new version drops.
	 *
	 * @param int   $symptom_id Symptom ID.
	 * @param array $content    The symptom content about to go live.
	 * @return string[]
	 */
	public static function broken_by_symptom( $symptom_id, array $content ) {
		$problems = array();

		foreach ( self::all() as $rule ) {
			if ( ! $rule['is_live'] ) {
				continue;
			}

			foreach ( (array) $rule['published']['triggers'] as $trigger ) {
				if ( (int) $trigger['symptom'] === (int) $symptom_id && ! self::trigger_fits( $trigger, $content ) ) {
					/* translators: %s: rule title. */
					$problems[] = sprintf( __( 'The live safety rule “%s” uses a question or answer this version removes. Update that rule first.', 'doctor-ak-portal' ), $rule['title'] );
				}
			}
		}

		return array_values( array_unique( $problems ) );
	}

	/* ---------------------------------------------------------------------
	 * Changes
	 * ------------------------------------------------------------------- */

	/**
	 * Adds or updates a rule (working copy back to Draft on any change).
	 *
	 * @param array $input   Fields (see validate()).
	 * @param int   $id      Rule ID, or 0 to add.
	 * @param int   $user_id Saving user.
	 * @return array|\WP_Error
	 */
	public static function save( array $input, $id, $user_id ) {
		$existing = $id ? self::find( $id ) : null;

		if ( $id && ! $existing ) {
			return new \WP_Error( 'doctor_ak_sr_missing', __( 'That safety rule no longer exists.', 'doctor-ak-portal' ) );
		}

		$check = self::validate( $input );

		if ( $check['errors'] ) {
			return new \WP_Error( 'doctor_ak_sr_invalid', __( 'Please fix the highlighted fields.', 'doctor-ak-portal' ), $check['errors'] );
		}

		$rows = self::all();

		if ( $existing ) {
			foreach ( $rows as &$row ) {
				if ( (int) $row['id'] === (int) $id ) {
					$result = Clinical_Workflow::apply_edit( $row, $check['fields'], self::CONTENT_KEYS, $user_id );
					$row    = $result['row'];
					$saved  = $row;
					Symptom_Audit::log( Symptom_Audit::TYPE_RULE, $id, $result['changed'] ? 'edit' : 'notes', $user_id, $result['changed'] && $row['is_live'] ? __( 'Revision started; the approved version stays live until this is approved and activated.', 'doctor-ak-portal' ) : '' );
				}
			}
			unset( $row );
		} else {
			if ( count( $rows ) >= self::MAX_RULES ) {
				return new \WP_Error( 'doctor_ak_sr_full', __( 'The maximum number of safety rules has been reached.', 'doctor-ak-portal' ) );
			}

			$now    = current_time( 'mysql' );
			$saved  = self::normalize_row(
				array_merge(
					$check['fields'],
					array(
						'id'         => self::next_id(),
						'published'  => null,
						'status'     => Clinical_Workflow::STATUS_DRAFT,
						'created_at' => $now,
						'created_by' => (int) $user_id,
						'updated_at' => $now,
						'updated_by' => (int) $user_id,
					)
				)
			);
			$rows[] = $saved;
			Symptom_Audit::log( Symptom_Audit::TYPE_RULE, $saved['id'], 'create', $user_id );
		}

		self::store( $rows );

		return array_merge( $saved, array( 'warnings' => $check['warnings'] ) );
	}

	/**
	 * Deletes a rule — administrators only (checked by the caller), never
	 * while it's live.
	 *
	 * @param int $id      Rule ID.
	 * @param int $user_id Acting user.
	 * @return true|\WP_Error
	 */
	public static function delete( $id, $user_id ) {
		$row = self::find( $id );

		if ( ! $row ) {
			return new \WP_Error( 'doctor_ak_sr_missing', __( 'That safety rule no longer exists.', 'doctor-ak-portal' ) );
		}

		if ( $row['is_live'] ) {
			return new \WP_Error( 'doctor_ak_sr_live', __( 'It’s live on the page. A clinical reviewer needs to deactivate it before it can be deleted.', 'doctor-ak-portal' ) );
		}

		self::store(
			array_filter(
				self::all(),
				function ( $other ) use ( $id ) {
					return (int) $other['id'] !== (int) $id;
				}
			)
		);

		Symptom_Audit::log( Symptom_Audit::TYPE_RULE, $id, 'delete', $user_id, $row['title'], Clinical_Workflow::content( $row, self::CONTENT_KEYS ) );

		return true;
	}

	/**
	 * Moves a rule through the lifecycle (see Clinical_Workflow).
	 *
	 * @param int    $id      Rule ID.
	 * @param string $action  Action.
	 * @param int    $user_id Acting user.
	 * @param string $reason  Reason (for 'return').
	 * @return array|\WP_Error
	 */
	public static function transition( $id, $action, $user_id, $reason = '' ) {
		$rows = self::all();

		foreach ( $rows as &$row ) {
			if ( (int) $row['id'] !== (int) $id ) {
				continue;
			}

			$errors = array();

			if ( in_array( $action, array( 'submit', 'approve', 'activate' ), true ) ) {
				$errors = array_values( self::validate( $row )['errors'] );
			}

			if ( 'activate' === $action ) {
				$errors = array_merge( $errors, self::activation_problems( $row ) );
			}

			$result = Clinical_Workflow::transition(
				$row,
				$action,
				$user_id,
				array(
					'can_manage' => Symptom_Mappings::can_manage( $user_id ),
					'can_review' => Symptom_Mappings::can_review( $user_id ),
					'errors'     => $errors,
					'reason'     => $reason,
					'keys'       => self::CONTENT_KEYS,
				)
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$row     = $result;
			$updated = $row;
		}
		unset( $row );

		if ( empty( $updated ) ) {
			return new \WP_Error( 'doctor_ak_sr_missing', __( 'That safety rule no longer exists.', 'doctor-ak-portal' ) );
		}

		self::store( $rows );

		Symptom_Audit::log(
			Symptom_Audit::TYPE_RULE,
			$id,
			$action,
			$user_id,
			Clinical_Workflow::audit_summary( $action, $updated ),
			'activate' === $action ? $updated['published'] : null
		);

		return $updated;
	}

	/**
	 * Rows shaped for the dashboard screen.
	 *
	 * @return array[]
	 */
	public static function for_admin() {
		return array_map(
			function ( $row ) {
				$check           = self::validate( $row );
				$row             = array_merge( $row, Clinical_Workflow::admin_fields( $row, self::CONTENT_KEYS ) );
				$row['problems'] = array_values( $check['errors'] );
				$row['warnings'] = $check['warnings'];
				return $row;
			},
			self::all()
		);
	}
}
