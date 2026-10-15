<?php
/**
 * Stores, validates and versions the Symptom Checker's clinical rules.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Symptom_Rules
 *
 * The Symptom Checker's clinical content — symptoms and their synonyms,
 * follow-up questions and branching, warning signs, possible explanations,
 * next steps, conflict checks and the specialties each one points to — is
 * data, never code. It moves through two stages:
 *
 *  1. Draft. An administrator edits the ruleset as JSON in the dashboard's
 *     Symptom Checker section (or loads the demonstration set to test with).
 *     Saving a draft never changes what patients see.
 *  2. Published. A designated clinical reviewer — an administrator account
 *     listed under "Clinical reviewers", who is NOT the person who last
 *     edited the draft — approves it, confirming they have reviewed the
 *     content. That creates a new numbered version. Patients only ever see
 *     a published version, and only while the checker is switched on.
 *
 * The demonstration rules (meta.is_demo) can be previewed by staff but can
 * never be approved, so they can never reach patients.
 */
class Symptom_Rules {

	/**
	 * Option: the draft { json, updated_by, updated_at, based_on }.
	 *
	 * @var string
	 */
	const DRAFT_OPTION = 'doctor_ak_symptom_rules_draft';

	/**
	 * Option: the approved, published version.
	 *
	 * @var string
	 */
	const PUBLISHED_OPTION = 'doctor_ak_symptom_rules_published';

	/**
	 * Option: earlier published versions (newest first).
	 *
	 * @var string
	 */
	const HISTORY_OPTION = 'doctor_ak_symptom_rules_history';

	/**
	 * Option: user IDs allowed to approve a draft.
	 *
	 * @var string
	 */
	const REVIEWERS_OPTION = 'doctor_ak_symptom_rules_reviewers';

	/**
	 * Option: 'yes' when the public Symptom Checker is switched on.
	 *
	 * @var string
	 */
	const ENABLED_OPTION = 'doctor_ak_symptom_checker_enabled';

	/**
	 * How many earlier versions are kept for the audit trail.
	 *
	 * @var int
	 */
	const HISTORY_LIMIT = 15;

	/**
	 * Largest ruleset accepted, in bytes of JSON.
	 *
	 * @var int
	 */
	const MAX_BYTES = 300000;

	/**
	 * Question types the checker knows how to ask.
	 *
	 * @var string[]
	 */
	const QUESTION_TYPES = array( 'single', 'multi', 'duration', 'severity', 'text' );

	/**
	 * Next-step urgency levels, most urgent first.
	 *
	 * @var string[]
	 */
	const URGENCIES = array( 'urgent', 'soon', 'routine', 'self_care' );

	/**
	 * Path of the bundled demonstration ruleset.
	 *
	 * @return string
	 */
	public static function demo_path() {
		return DOCTOR_AK_PORTAL_PATH . 'includes/data/symptom-checker-demo-rules.json';
	}

	/**
	 * The demonstration ruleset as JSON text, or ''.
	 *
	 * @return string
	 */
	public static function demo_json() {
		$path = self::demo_path();

		return is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled file.
	}

	/**
	 * The saved draft.
	 *
	 * @return array { json: string, updated_by: int, updated_at: string, based_on: int }
	 */
	public static function get_draft() {
		$draft = get_option( self::DRAFT_OPTION, array() );

		return wp_parse_args(
			is_array( $draft ) ? $draft : array(),
			array(
				'json'       => '',
				'updated_by' => 0,
				'updated_at' => '',
				'based_on'   => 0,
			)
		);
	}

	/**
	 * The published version, or null when nothing has been approved.
	 *
	 * @return array|null { rules, version, approved_by, approved_at, edited_by, note }
	 */
	public static function get_published() {
		$published = get_option( self::PUBLISHED_OPTION, array() );

		return is_array( $published ) && ! empty( $published['rules'] ) && is_array( $published['rules'] ) ? $published : null;
	}

	/**
	 * Earlier published versions, newest first (without their rules).
	 *
	 * @return array
	 */
	public static function get_history() {
		$history = get_option( self::HISTORY_OPTION, array() );

		return is_array( $history ) ? $history : array();
	}

	/**
	 * Designated clinical reviewers' user IDs.
	 *
	 * @return int[]
	 */
	public static function get_reviewers() {
		$ids = get_option( self::REVIEWERS_OPTION, array() );

		return array_values( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) ) );
	}

	/**
	 * Saves the reviewer list. Only administrator accounts qualify — they're
	 * the only people who can open the dashboard section to review.
	 *
	 * @param int[] $ids User IDs.
	 * @return int[] The IDs actually saved.
	 */
	public static function save_reviewers( array $ids ) {
		$valid = array();

		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			if ( $id > 0 && user_can( $id, 'manage_options' ) ) {
				$valid[] = $id;
			}
		}

		update_option( self::REVIEWERS_OPTION, $valid, false );

		return $valid;
	}

	/**
	 * Whether the switch is on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( self::ENABLED_OPTION, 'no' );
	}

	/**
	 * Turns the public checker on or off. Turning on needs an approved,
	 * non-demonstration version.
	 *
	 * @param bool $enabled On/off.
	 * @return true|\WP_Error
	 */
	public static function set_enabled( $enabled ) {
		if ( $enabled ) {
			$published = self::get_published();

			if ( ! $published ) {
				return new \WP_Error( 'doctor_ak_symptom_rules_unpublished', __( 'There is no clinically approved version yet. A designated reviewer must approve a ruleset before the Symptom Checker can be switched on.', 'doctor-ak-portal' ) );
			}

			if ( self::is_demo( $published['rules'] ) ) {
				return new \WP_Error( 'doctor_ak_symptom_rules_demo', __( 'The published version is demonstration content and can never be shown to patients.', 'doctor-ak-portal' ) );
			}
		}

		update_option( self::ENABLED_OPTION, $enabled ? 'yes' : 'no', true );

		return true;
	}

	/**
	 * Whether patients can use the checker right now: switched on, with an
	 * approved version that isn't demonstration content.
	 *
	 * @return bool
	 */
	public static function is_public() {
		$published = self::get_published();

		return self::is_enabled() && $published && ! self::is_demo( $published['rules'] );
	}

	/**
	 * Whether a ruleset is marked as demonstration content.
	 *
	 * @param array $rules Ruleset.
	 * @return bool
	 */
	public static function is_demo( array $rules ) {
		return ! empty( $rules['meta']['is_demo'] );
	}

	/**
	 * Saves the draft. Invalid JSON can still be saved (so work isn't lost)
	 * — it just can't be approved until it validates.
	 *
	 * @param string $json Ruleset JSON.
	 * @return array { saved: bool, errors: string[] }
	 */
	public static function save_draft( $json ) {
		$json = (string) $json;

		if ( strlen( $json ) > self::MAX_BYTES ) {
			return array(
				'saved'  => false,
				'errors' => array( __( 'The ruleset is too large to save.', 'doctor-ak-portal' ) ),
			);
		}

		$published = self::get_published();

		update_option(
			self::DRAFT_OPTION,
			array(
				'json'       => $json,
				'updated_by' => get_current_user_id(),
				'updated_at' => current_time( 'mysql' ),
				'based_on'   => $published ? (int) $published['version'] : 0,
			),
			false
		);

		$parsed = self::parse( $json );

		return array(
			'saved'  => true,
			'errors' => $parsed['errors'],
		);
	}

	/**
	 * Approves the current draft as a new published version.
	 *
	 * @param int    $user_id Approving user.
	 * @param string $note    Reviewer's note (what was reviewed / changed).
	 * @return array|\WP_Error The new published record.
	 */
	public static function approve( $user_id, $note ) {
		$user_id = (int) $user_id;

		if ( ! in_array( $user_id, self::get_reviewers(), true ) || ! user_can( $user_id, 'manage_options' ) ) {
			return new \WP_Error( 'doctor_ak_symptom_rules_not_reviewer', __( 'Only a designated clinical reviewer can approve the rules.', 'doctor-ak-portal' ) );
		}

		$draft = self::get_draft();

		if ( '' === trim( $draft['json'] ) ) {
			return new \WP_Error( 'doctor_ak_symptom_rules_empty', __( 'There is no draft to approve.', 'doctor-ak-portal' ) );
		}

		if ( (int) $draft['updated_by'] === $user_id ) {
			return new \WP_Error( 'doctor_ak_symptom_rules_self_approval', __( 'You made the latest changes to this draft, so a different reviewer must approve it.', 'doctor-ak-portal' ) );
		}

		$parsed = self::parse( $draft['json'] );

		if ( ! empty( $parsed['errors'] ) ) {
			return new \WP_Error( 'doctor_ak_symptom_rules_invalid', __( 'The draft has problems that must be fixed before it can be approved.', 'doctor-ak-portal' ), $parsed['errors'] );
		}

		if ( self::is_demo( $parsed['rules'] ) ) {
			return new \WP_Error( 'doctor_ak_symptom_rules_demo', __( 'This draft is the demonstration ruleset (marked "is_demo"). Demonstration content can never be approved. Replace it with clinically written rules and remove the "is_demo" flag.', 'doctor-ak-portal' ) );
		}

		$note = sanitize_textarea_field( (string) $note );

		if ( '' === trim( $note ) ) {
			return new \WP_Error( 'doctor_ak_symptom_rules_note', __( 'Please add a short review note — what you reviewed and on what basis.', 'doctor-ak-portal' ) );
		}

		$previous = self::get_published();
		$history  = self::get_history();

		if ( $previous ) {
			array_unshift(
				$history,
				array(
					'version'     => (int) $previous['version'],
					'approved_by' => (int) $previous['approved_by'],
					'approved_at' => $previous['approved_at'],
					'edited_by'   => (int) $previous['edited_by'],
					'note'        => $previous['note'],
					'name'        => isset( $previous['rules']['meta']['name'] ) ? (string) $previous['rules']['meta']['name'] : '',
				)
			);
			update_option( self::HISTORY_OPTION, array_slice( $history, 0, self::HISTORY_LIMIT ), false );
		}

		$record = array(
			'rules'       => $parsed['rules'],
			'version'     => $previous ? (int) $previous['version'] + 1 : 1,
			'approved_by' => $user_id,
			'approved_at' => current_time( 'mysql' ),
			'edited_by'   => (int) $draft['updated_by'],
			'note'        => $note,
		);

		update_option( self::PUBLISHED_OPTION, $record, false );

		$draft['based_on'] = $record['version'];
		update_option( self::DRAFT_OPTION, $draft, false );

		return $record;
	}

	/**
	 * Parses and validates ruleset JSON.
	 *
	 * @param string $json Ruleset JSON.
	 * @return array { rules: array|null, errors: string[] }
	 */
	public static function parse( $json ) {
		$json = (string) $json;

		if ( '' === trim( $json ) ) {
			return array(
				'rules'  => null,
				'errors' => array( __( 'The ruleset is empty.', 'doctor-ak-portal' ) ),
			);
		}

		if ( strlen( $json ) > self::MAX_BYTES ) {
			return array(
				'rules'  => null,
				'errors' => array( __( 'The ruleset is too large.', 'doctor-ak-portal' ) ),
			);
		}

		$rules = json_decode( $json, true );

		if ( ! is_array( $rules ) ) {
			return array(
				'rules'  => null,
				/* translators: %s: JSON parser error message. */
				'errors' => array( sprintf( __( 'This is not valid JSON: %s', 'doctor-ak-portal' ), json_last_error_msg() ) ),
			);
		}

		$rules  = self::clean( $rules );
		$errors = self::validate( $rules );

		return array(
			'rules'  => $rules,
			'errors' => $errors,
		);
	}

	/**
	 * Strips markup from every string — the page renders all of it as plain
	 * text anyway.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function clean( $value ) {
		if ( is_array( $value ) ) {
			$out = array();

			foreach ( $value as $key => $item ) {
				$key         = is_string( $key ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', $key ) : $key;
				$out[ $key ] = self::clean( $item );
			}

			return $out;
		}

		if ( is_string( $value ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}

		return $value;
	}

	/**
	 * Checks a ruleset's structure and that every reference resolves.
	 *
	 * @param array $rules Decoded ruleset.
	 * @return string[] Problems, each naming where it is.
	 */
	public static function validate( array $rules ) {
		$errors      = array();
		$specialties = array_keys( Specializations::get_all() );
		$id_pattern  = '/^[a-z0-9_]{1,60}$/';

		if ( empty( $rules['emergency']['title'] ) || empty( $rules['emergency']['message'] ) ) {
			$errors[] = __( 'Emergency message: the heading and the text are both needed.', 'doctor-ak-portal' );
		}

		if ( ! empty( $rules['fallback_specialty'] ) && ! in_array( $rules['fallback_specialty'], $specialties, true ) ) {
			/* translators: %s: specialty slug. */
			$errors[] = sprintf( __( 'fallback_specialty: "%s" is not a specialty on this site.', 'doctor-ak-portal' ), $rules['fallback_specialty'] );
		}

		// Symptoms.
		$symptom_ids = array();

		if ( empty( $rules['symptoms'] ) || ! is_array( $rules['symptoms'] ) ) {
			$errors[] = __( 'Symptoms: add at least one symptom.', 'doctor-ak-portal' );
		} else {
			foreach ( array_values( $rules['symptoms'] ) as $i => $symptom ) {
				$where = self::describe( __( 'Symptom', 'doctor-ak-portal' ), isset( $symptom['label'] ) ? $symptom['label'] : '', $i );
				$id    = isset( $symptom['id'] ) ? (string) $symptom['id'] : '';

				if ( ! preg_match( $id_pattern, $id ) ) {
					$errors[] = $where . ': ' . __( 'id must be lowercase letters, numbers and underscores.', 'doctor-ak-portal' );
				} elseif ( isset( $symptom_ids[ $id ] ) ) {
					/* translators: %s: id. */
					$errors[] = $where . ': ' . sprintf( __( 'duplicate id "%s".', 'doctor-ak-portal' ), $id );
				}

				$symptom_ids[ $id ] = true;

				if ( empty( $symptom['label'] ) ) {
					$errors[] = $where . ': ' . __( 'the symptom name is empty.', 'doctor-ak-portal' );
				}

				if ( isset( $symptom['synonyms'] ) && ! self::is_string_list( $symptom['synonyms'] ) ) {
					$errors[] = $where . ': ' . __( 'synonyms must be a list of text.', 'doctor-ak-portal' );
				}

				$errors = array_merge( $errors, self::check_specialties( $symptom, $where, $specialties ) );
			}
		}

		// Questions.
		$questions = array();

		if ( isset( $rules['questions'] ) && ! is_array( $rules['questions'] ) ) {
			$errors[] = __( 'questions must be a list.', 'doctor-ak-portal' );
		} else {
			foreach ( array_values( isset( $rules['questions'] ) ? $rules['questions'] : array() ) as $i => $question ) {
				$where = self::describe( __( 'Question', 'doctor-ak-portal' ), isset( $question['text'] ) ? $question['text'] : '', $i );
				$id    = isset( $question['id'] ) ? (string) $question['id'] : '';

				if ( ! preg_match( $id_pattern, $id ) ) {
					$errors[] = $where . ': ' . __( 'id must be lowercase letters, numbers and underscores.', 'doctor-ak-portal' );
					continue;
				}

				if ( isset( $questions[ $id ] ) ) {
					/* translators: %s: id. */
					$errors[] = $where . ': ' . sprintf( __( 'duplicate id "%s".', 'doctor-ak-portal' ), $id );
				}

				$questions[ $id ] = $question;

				if ( empty( $question['text'] ) ) {
					$errors[] = $where . ': ' . __( 'the question text is empty.', 'doctor-ak-portal' );
				}

				$type = isset( $question['type'] ) ? $question['type'] : '';

				if ( ! in_array( $type, self::QUESTION_TYPES, true ) ) {
					/* translators: %s: allowed types. */
					$errors[] = $where . ': ' . sprintf( __( 'type must be one of: %s.', 'doctor-ak-portal' ), implode( ', ', self::QUESTION_TYPES ) );
				}

				if ( 'text' !== $type ) {
					if ( empty( $question['options'] ) || ! is_array( $question['options'] ) ) {
						$errors[] = $where . ': ' . __( 'add at least one answer patients can choose.', 'doctor-ak-portal' );
					} else {
						$values = array();

						foreach ( $question['options'] as $o => $option ) {
							$value = isset( $option['value'] ) ? (string) $option['value'] : '';

							if ( ! preg_match( $id_pattern, $value ) || 'unsure' === $value ) {
								/* translators: %d: option index. */
								$errors[] = $where . ': ' . sprintf( __( 'option %d needs a value of lowercase letters, numbers and underscores (and not "unsure", which is reserved).', 'doctor-ak-portal' ), $o );
							} elseif ( isset( $values[ $value ] ) ) {
								/* translators: %s: value. */
								$errors[] = $where . ': ' . sprintf( __( 'duplicate option value "%s".', 'doctor-ak-portal' ), $value );
							}

							$values[ $value ] = true;

							if ( empty( $option['label'] ) ) {
								/* translators: %d: option index. */
								$errors[] = $where . ': ' . sprintf( __( 'answer %d is empty.', 'doctor-ak-portal' ), $o + 1 );
							}

							foreach ( array( 'score', 'max_days' ) as $number ) {
								if ( isset( $option[ $number ] ) && ! is_numeric( $option[ $number ] ) ) {
									/* translators: 1: option index, 2: field name. */
									$errors[] = $where . ': ' . sprintf( __( 'option %1$d: %2$s must be a number.', 'doctor-ak-portal' ), $o, $number );
								}
							}
						}
					}
				}

				$applies = isset( $question['applies_to'] ) ? $question['applies_to'] : '*';

				if ( '*' !== $applies ) {
					if ( ! self::is_string_list( $applies ) ) {
						$errors[] = $where . ': ' . __( 'applies_to must be "*" or a list of symptom ids.', 'doctor-ak-portal' );
					} else {
						foreach ( $applies as $symptom_id ) {
							if ( ! isset( $symptom_ids[ $symptom_id ] ) ) {
								/* translators: %s: id. */
								$errors[] = $where . ': ' . sprintf( __( 'applies_to names an unknown symptom "%s".', 'doctor-ak-portal' ), $symptom_id );
							}
						}
					}
				}

				if ( is_array( $applies ) && empty( $applies ) ) {
					$errors[] = $where . ': ' . __( 'it is set to be asked only for certain symptoms, but none are chosen.', 'doctor-ak-portal' );
				}

				if ( isset( $question['priority'] ) && ! is_numeric( $question['priority'] ) ) {
					$errors[] = $where . ': ' . __( 'priority must be a number.', 'doctor-ak-portal' );
				}
			}
		}

		// Conditions everywhere, now every id is known.
		foreach ( $questions as $id => $question ) {
			if ( isset( $question['show_if'] ) ) {
				$errors = array_merge( $errors, self::check_condition( $question['show_if'], self::describe( __( 'Question', 'doctor-ak-portal' ), isset( $question['text'] ) ? $question['text'] : $id, 0 ) . ' — ' . __( '"Only ask when"', 'doctor-ak-portal' ), $symptom_ids, $questions ) );
			}
		}

		if ( isset( $rules['text_alerts'] ) ) {
			foreach ( (array) $rules['text_alerts'] as $i => $alert ) {
				if ( ! isset( $alert['question'] ) || ! isset( $questions[ $alert['question'] ] ) ) {
					$errors[] = self::describe( __( 'Warning phrases', 'doctor-ak-portal' ), isset( $alert['phrases'][0] ) ? (string) $alert['phrases'][0] : '', $i ) . ': ' . __( 'choose which question to ask.', 'doctor-ak-portal' );
				}

				if ( empty( $alert['phrases'] ) || ! self::is_string_list( $alert['phrases'] ) ) {
					$errors[] = self::describe( __( 'Warning phrases', 'doctor-ak-portal' ), '', $i ) . ': ' . __( 'add at least one phrase.', 'doctor-ak-portal' );
				}
			}
		}

		$lists = array(
			'red_flags'    => array( 'title' ),
			'next_steps'   => array( 'title', 'text', 'urgency' ),
			'explanations' => array( 'title', 'summary' ),
			'conflicts'    => array( 'message' ),
		);

		$has_fallback = false;
		$names        = array(
			'red_flags'    => __( 'Warning sign', 'doctor-ak-portal' ),
			'next_steps'   => __( 'Next step', 'doctor-ak-portal' ),
			'explanations' => __( 'Possible explanation', 'doctor-ak-portal' ),
			'conflicts'    => __( 'Answer check', 'doctor-ak-portal' ),
		);
		$field_names  = array(
			'title'   => __( 'the heading', 'doctor-ak-portal' ),
			'text'    => __( 'the advice', 'doctor-ak-portal' ),
			'summary' => __( 'the explanation', 'doctor-ak-portal' ),
			'message' => __( 'the message', 'doctor-ak-portal' ),
			'urgency' => __( '"How urgent"', 'doctor-ak-portal' ),
		);

		foreach ( $lists as $list => $required ) {
			if ( ! isset( $rules[ $list ] ) ) {
				continue;
			}

			if ( ! is_array( $rules[ $list ] ) ) {
				/* translators: %s: list name. */
				$errors[] = sprintf( __( '%s must be a list.', 'doctor-ak-portal' ), $list );
				continue;
			}

			$seen = array();

			foreach ( array_values( $rules[ $list ] ) as $i => $item ) {
				$id    = isset( $item['id'] ) ? (string) $item['id'] : '';
				$where = self::describe( $names[ $list ], isset( $item['title'] ) ? $item['title'] : ( isset( $item['message'] ) ? $item['message'] : '' ), $i );

				if ( ! preg_match( $id_pattern, $id ) ) {
					$errors[] = $where . ': ' . __( 'id must be lowercase letters, numbers and underscores.', 'doctor-ak-portal' );
				} elseif ( isset( $seen[ $id ] ) ) {
					/* translators: %s: id. */
					$errors[] = $where . ': ' . sprintf( __( 'duplicate id "%s".', 'doctor-ak-portal' ), $id );
				}

				$seen[ $id ] = true;

				foreach ( $required as $field ) {
					if ( empty( $item[ $field ] ) ) {
						/* translators: %s: field name. */
						/* translators: %s: field name. */
						$errors[] = $where . ': ' . sprintf( __( '%s is empty.', 'doctor-ak-portal' ), isset( $field_names[ $field ] ) ? $field_names[ $field ] : $field );
					}
				}

				if ( 'next_steps' === $list && ! empty( $item['urgency'] ) && ! in_array( $item['urgency'], self::URGENCIES, true ) ) {
					/* translators: %s: allowed values. */
					$errors[] = $where . ': ' . sprintf( __( 'urgency must be one of: %s.', 'doctor-ak-portal' ), implode( ', ', self::URGENCIES ) );
				}

				if ( 'next_steps' === $list && ! empty( $item['fallback'] ) ) {
					$has_fallback = true;
				}

				if ( ! array_key_exists( 'when', $item ) ) {
					$errors[] = $where . ': ' . __( 'when (the condition) is required.', 'doctor-ak-portal' );
				} elseif ( empty( $item['fallback'] ) && self::is_empty_condition( $item['when'] ) ) {
					// Would apply to every patient — almost certainly unfinished.
					$errors[] = $where . ': ' . __( 'add at least one condition (when it should apply).', 'doctor-ak-portal' );
				} else {
					$errors = array_merge( $errors, self::check_condition( $item['when'], $where . ' — ' . __( 'condition', 'doctor-ak-portal' ), $symptom_ids, $questions ) );
				}

				if ( 'explanations' === $list ) {
					$errors = array_merge( $errors, self::check_specialties( $item, $where, $specialties ) );
				}
			}
		}

		if ( ! $has_fallback ) {
			$errors[] = __( 'Next steps: tick "Default" on one of them — it is shown when no other next step applies.', 'doctor-ak-portal' );
		}

		return $errors;
	}

	/**
	 * Validates a condition (see doctor-ak-symptom-engine.js for the language).
	 *
	 * @param mixed  $cond        Condition.
	 * @param string $where       Location for messages.
	 * @param array  $symptom_ids id => true.
	 * @param array  $questions   id => question.
	 * @param int    $depth       Nesting depth.
	 * @return string[]
	 */
	private static function check_condition( $cond, $where, array $symptom_ids, array $questions, $depth = 0 ) {
		if ( $depth > 8 ) {
			return array( $where . ': ' . __( 'conditions are nested too deeply.', 'doctor-ak-portal' ) );
		}

		if ( ! is_array( $cond ) ) {
			return array( $where . ': ' . __( 'must be a condition object.', 'doctor-ak-portal' ) );
		}

		$errors = array();

		foreach ( array( 'all', 'any' ) as $combinator ) {
			if ( array_key_exists( $combinator, $cond ) ) {
				if ( ! is_array( $cond[ $combinator ] ) ) {
					return array( $where . '.' . $combinator . ': ' . __( 'must be a list of conditions.', 'doctor-ak-portal' ) );
				}

				foreach ( array_values( $cond[ $combinator ] ) as $i => $child ) {
					$errors = array_merge( $errors, self::check_condition( $child, $where, $symptom_ids, $questions, $depth + 1 ) );
				}

				return $errors;
			}
		}

		if ( array_key_exists( 'not', $cond ) ) {
			return self::check_condition( $cond['not'], $where, $symptom_ids, $questions, $depth + 1 );
		}

		if ( isset( $cond['symptom'] ) ) {
			/* translators: %s: id. */
			return isset( $symptom_ids[ $cond['symptom'] ] ) ? array() : array( $where . ': ' . sprintf( __( 'unknown symptom "%s".', 'doctor-ak-portal' ), $cond['symptom'] ) );
		}

		if ( isset( $cond['answered'] ) ) {
			/* translators: %s: id. */
			return isset( $questions[ $cond['answered'] ] ) ? array() : array( $where . ': ' . sprintf( __( 'unknown question "%s".', 'doctor-ak-portal' ), $cond['answered'] ) );
		}

		if ( isset( $cond['answer'] ) ) {
			if ( ! isset( $questions[ $cond['answer'] ] ) ) {
				/* translators: %s: id. */
				return array( $where . ': ' . sprintf( __( 'unknown question "%s".', 'doctor-ak-portal' ), $cond['answer'] ) );
			}

			$question = $questions[ $cond['answer'] ];
			$values   = array( 'unsure' => true );

			foreach ( isset( $question['options'] ) && is_array( $question['options'] ) ? $question['options'] : array() as $option ) {
				if ( isset( $option['value'] ) ) {
					$values[ (string) $option['value'] ] = true;
				}
			}

			$tested = array();

			if ( array_key_exists( 'equals', $cond ) ) {
				$tested[] = $cond['equals'];
			} elseif ( array_key_exists( 'includes', $cond ) ) {
				$tested[] = $cond['includes'];
			} elseif ( array_key_exists( 'in', $cond ) ) {
				if ( ! is_array( $cond['in'] ) ) {
					return array( $where . ': ' . __( '"in" must be a list.', 'doctor-ak-portal' ) );
				}

				$tested = $cond['in'];
			} elseif ( isset( $cond['gte'] ) || isset( $cond['lte'] ) ) {
				foreach ( array( 'gte', 'lte' ) as $bound ) {
					if ( isset( $cond[ $bound ] ) && ! is_numeric( $cond[ $bound ] ) ) {
						/* translators: %s: gte or lte. */
						$errors[] = $where . ': ' . sprintf( __( '%s must be a number.', 'doctor-ak-portal' ), $bound );
					}
				}

				return $errors;
			} else {
				return array( $where . ': ' . __( 'an answer test needs equals, in, includes, gte or lte.', 'doctor-ak-portal' ) );
			}

			foreach ( $tested as $value ) {
				if ( ! is_scalar( $value ) || ! isset( $values[ (string) $value ] ) ) {
					/* translators: 1: value, 2: question id. */
					$errors[] = $where . ': ' . sprintf( __( '"%1$s" is not an option of question "%2$s".', 'doctor-ak-portal' ), is_scalar( $value ) ? $value : '?', $cond['answer'] );
				}
			}

			return $errors;
		}

		return array( $where . ': ' . __( 'unrecognised condition.', 'doctor-ak-portal' ) );
	}

	/**
	 * A readable name for an item in problem messages: Kind “Title”, or
	 * Kind number N when it has no title yet.
	 *
	 * @param string $kind  e.g. "Warning sign".
	 * @param string $title The item's title/label.
	 * @param int    $index Position in its list (0-based).
	 * @return string
	 */
	private static function describe( $kind, $title, $index ) {
		$title = trim( (string) $title );

		if ( '' === $title ) {
			/* translators: 1: kind of item, 2: its position. */
			return sprintf( __( '%1$s number %2$d', 'doctor-ak-portal' ), $kind, $index + 1 );
		}

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $title ) > 60 ) {
			$title = mb_substr( $title, 0, 59 ) . '…';
		}

		return $kind . ' “' . $title . '”';
	}

	/**
	 * Whether a condition has no tests at all ({}, {"all": []}).
	 *
	 * @param mixed $cond Condition.
	 * @return bool
	 */
	private static function is_empty_condition( $cond ) {
		if ( ! is_array( $cond ) || empty( $cond ) ) {
			return true;
		}

		return 1 === count( $cond ) && isset( $cond['all'] ) && is_array( $cond['all'] ) && empty( $cond['all'] );
	}

	/**
	 * Checks an item's "specialties" list against the site's specialties.
	 *
	 * @param array    $item        Symptom or explanation.
	 * @param string   $where       Location for messages.
	 * @param string[] $specialties Known slugs.
	 * @return string[]
	 */
	private static function check_specialties( array $item, $where, array $specialties ) {
		if ( ! isset( $item['specialties'] ) ) {
			return array();
		}

		if ( ! self::is_string_list( $item['specialties'] ) ) {
			return array( $where . ': ' . __( 'specialties must be a list of specialty slugs.', 'doctor-ak-portal' ) );
		}

		$errors = array();

		foreach ( $item['specialties'] as $slug ) {
			if ( ! in_array( $slug, $specialties, true ) ) {
				/* translators: %s: specialty slug. */
				$errors[] = $where . ': ' . sprintf( __( '"%s" is not a specialty on this site (see Settings → specializations).', 'doctor-ak-portal' ), $slug );
			}
		}

		return $errors;
	}

	/**
	 * Whether a value is a list of strings.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_string_list( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( ! is_string( $item ) ) {
				return false;
			}
		}

		return true;
	}
}
