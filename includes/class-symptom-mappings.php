<?php
/**
 * Symptoms for "Find a doctor by symptoms": names, the words patients use,
 * related specialties and optional follow-up questions.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Symptom_Mappings
 *
 * Each symptom links what patients type (its name, English alternative
 * phrases and phrases in other configured languages, e.g. Roman Urdu) to
 * the specialties that commonly see it, plus optional follow-up questions
 * written and approved by clinicians. They help patients find doctors —
 * nothing here diagnoses, scores risk or advises treatment.
 *
 * Lifecycle (Clinical_Workflow): Draft → Pending review → Reviewed → Active,
 * or Inactive. Editing an Active symptom creates a revision; patients keep
 * seeing the last approved version until the revision is approved and
 * activated. Example, imported and legacy symptoms always start as Drafts.
 *
 * The page is only shown to patients when it's switched on, at least one
 * symptom is live and the general emergency notice has an approved version
 * (is_public()).
 *
 * Storage: one option (OPTION_MAPPINGS). Rows written by earlier versions are
 * upgraded when read (normalize_row()) and persisted by migrate().
 */
class Symptom_Mappings {

	/** Capability that lets a user review/activate content and approve the notice. */
	const REVIEW_CAP = 'doctor_ak_review_symptom_mappings';

	const OPTION_MAPPINGS = 'doctor_ak_symptom_mappings';
	const OPTION_NEXT_ID  = 'doctor_ak_symptom_mappings_next_id';
	const OPTION_NOTICE   = 'doctor_ak_symptom_finder_notice';
	const OPTION_ENABLED  = 'doctor_ak_symptom_finder_enabled';
	const OPTION_SCHEMA   = 'doctor_ak_symptom_mappings_schema';

	/** Current row format (2 = published copies, questions, languages). */
	const SCHEMA = 2;

	const STATUS_DRAFT    = Clinical_Workflow::STATUS_DRAFT;
	const STATUS_PENDING  = Clinical_Workflow::STATUS_PENDING;
	const STATUS_REVIEWED = Clinical_Workflow::STATUS_REVIEWED;
	const STATUS_ACTIVE   = Clinical_Workflow::STATUS_ACTIVE;
	const STATUS_INACTIVE = Clinical_Workflow::STATUS_INACTIVE;

	const MAX_MAPPINGS    = 1000;
	const MAX_SYNONYMS    = 40;
	const MAX_TEXT        = 80;
	const MAX_DESCRIPTION = 500;
	const MAX_QUESTIONS   = 10;
	const MAX_OPTIONS     = 8;
	const MAX_QUESTION    = 200;
	const MAX_OPTION      = 100;
	const MAX_EXPLANATION = 400;

	/** Question types the patient page can ask. */
	const QUESTION_TYPES = array( 'single', 'multi', 'yesno', 'number' );

	/** Fields covered by clinical review (any change needs a new review). */
	const CONTENT_KEYS = array( 'name', 'slug', 'description', 'synonyms', 'phrases', 'specialties', 'questions' );

	/**
	 * Normalized rows for this request (cleared on every write).
	 *
	 * @var array[]|null
	 */
	private static $cache = null;

	/**
	 * Phrase / identifier index for duplicate checks (cleared on every write).
	 *
	 * @var array|null
	 */
	private static $index = null;

	/* ---------------------------------------------------------------------
	 * Permissions
	 * ------------------------------------------------------------------- */

	/**
	 * Whether a user may add/edit symptoms, safety rules and the notice.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function can_manage( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();

		return $user_id > 0 && ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, self::REVIEW_CAP ) );
	}

	/**
	 * Whether a user may review/activate content and approve the notice.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function can_review( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();

		return $user_id > 0 && user_can( $user_id, self::REVIEW_CAP );
	}

	/**
	 * Users currently holding the review capability.
	 *
	 * @return \WP_User[]
	 */
	public static function reviewers() {
		return array_values(
			array_filter(
				self::reviewer_candidates(),
				function ( $user ) {
					return user_can( $user, self::REVIEW_CAP );
				}
			)
		);
	}

	/**
	 * Users who may be made reviewers: administrators and doctors.
	 *
	 * @return \WP_User[]
	 */
	public static function reviewer_candidates() {
		return get_users(
			array(
				'role__in' => array( 'administrator', Roles::DOCTOR_ROLE ),
				'orderby'  => 'display_name',
				'number'   => 500,
			)
		);
	}

	/**
	 * Grants or removes the review capability (administrators only — checked
	 * by the caller).
	 *
	 * @param int  $user_id User ID.
	 * @param bool $grant   Grant (true) or remove (false).
	 * @return true|\WP_Error
	 */
	public static function set_reviewer( $user_id, $grant ) {
		$user = get_userdata( (int) $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'doctor_ak_sm_no_user', __( 'That user could not be found.', 'doctor-ak-portal' ) );
		}

		if ( $grant && ! array_intersect( array( 'administrator', Roles::DOCTOR_ROLE ), (array) $user->roles ) ) {
			return new \WP_Error( 'doctor_ak_sm_bad_role', __( 'Only doctors and administrators can be clinical reviewers.', 'doctor-ak-portal' ) );
		}

		if ( $grant ) {
			$user->add_cap( self::REVIEW_CAP );
		} else {
			$user->remove_cap( self::REVIEW_CAP );
		}

		Symptom_Audit::log( Symptom_Audit::TYPE_PAGE, 0, $grant ? 'reviewer_add' : 'reviewer_remove', get_current_user_id(), $user->display_name );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Languages
	 * ------------------------------------------------------------------- */

	/**
	 * Extra languages patients may type in, besides English (the main
	 * "alternative phrases"). code => label. Add more with the
	 * `doctor_ak_symptom_languages` filter.
	 *
	 * @return array
	 */
	public static function languages() {
		$languages = apply_filters( 'doctor_ak_symptom_languages', array( 'ur-roman' => __( 'Roman Urdu', 'doctor-ak-portal' ) ) );
		$clean     = array();

		foreach ( (array) $languages as $code => $label ) {
			$code = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $code ) );

			if ( '' !== $code && 'en' !== $code ) {
				$clean[ $code ] = (string) $label;
			}
		}

		return $clean;
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------- */

	/**
	 * Every symptom, in name order.
	 *
	 * @return array[]
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$rows = get_option( self::OPTION_MAPPINGS, array() );
		$rows = is_array( $rows ) ? array_values( array_filter( array_map( array( __CLASS__, 'normalize_row' ), $rows ) ) ) : array();

		usort(
			$rows,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		self::$cache = $rows;

		return $rows;
	}

	/**
	 * Live symptoms only (with their published content).
	 *
	 * @return array[]
	 */
	public static function active() {
		return array_values(
			array_filter(
				self::all(),
				function ( $row ) {
					return $row['is_live'];
				}
			)
		);
	}

	/**
	 * One symptom by ID.
	 *
	 * @param int $id Symptom ID.
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
	 * What the patient page receives: the published copy of every live
	 * symptom — or, for a staff preview, the working copy of everything
	 * that isn't inactive. Internal notes and reviewer details are never
	 * included.
	 *
	 * @param bool $preview Staff preview.
	 * @return array[] Each { id, slug, name, description, synonyms[], specialties[], questions[] }.
	 */
	public static function public_items( $preview = false ) {
		$out = array();

		foreach ( self::all() as $row ) {
			if ( $preview ) {
				if ( self::STATUS_INACTIVE === $row['status'] && ! $row['is_live'] ) {
					continue;
				}

				$content = Clinical_Workflow::content( $row, self::CONTENT_KEYS );
			} elseif ( $row['is_live'] ) {
				$content = self::normalize_content( $row['published'] );
			} else {
				continue;
			}

			$out[] = array(
				'id'          => (int) $row['id'],
				'slug'        => $content['slug'],
				'name'        => $content['name'],
				'description' => $content['description'],
				'synonyms'    => self::all_phrases( $content ),
				'specialties' => $content['specialties'],
				'questions'   => $content['questions'],
				'status'      => $preview ? $row['status'] : self::STATUS_ACTIVE,
			);
		}

		return $out;
	}

	/**
	 * Fills defaults on a stored row and upgrades older rows; drops
	 * malformed ones.
	 *
	 * @param mixed $row Stored row.
	 * @return array|null
	 */
	private static function normalize_row( $row ) {
		if ( ! is_array( $row ) || empty( $row['id'] ) || ! isset( $row['name'] ) ) {
			return null;
		}

		$row = wp_parse_args(
			$row,
			array(
				'source' => 'manual',
				'notes'  => '',
			)
		);

		// Schema 1 had "Reviewed" mean "approved, not yet live" and allowed
		// deactivating back to it; that's unchanged in meaning here.
		$row       = array_merge( $row, self::normalize_content( $row ) );
		$row['id'] = (int) $row['id'];

		return Clinical_Workflow::normalize( $row, self::CONTENT_KEYS );
	}

	/**
	 * Content fields with their defaults and types.
	 *
	 * @param array $content Raw content.
	 * @return array
	 */
	private static function normalize_content( array $content ) {
		$content = wp_parse_args(
			$content,
			array(
				'name'        => '',
				'slug'        => '',
				'description' => '',
				'synonyms'    => array(),
				'phrases'     => array(),
				'specialties' => array(),
				'questions'   => array(),
			)
		);

		$phrases = array();

		foreach ( (array) $content['phrases'] as $code => $list ) {
			$phrases[ (string) $code ] = array_values( array_map( 'strval', (array) $list ) );
		}

		ksort( $phrases );

		return array(
			'name'        => (string) $content['name'],
			'slug'        => '' !== (string) $content['slug'] ? (string) $content['slug'] : sanitize_title( (string) $content['name'] ),
			'description' => (string) $content['description'],
			'synonyms'    => array_values( array_map( 'strval', (array) $content['synonyms'] ) ),
			'phrases'     => $phrases,
			'specialties' => array_values( array_map( 'strval', (array) $content['specialties'] ) ),
			'questions'   => array_values( array_filter( (array) $content['questions'], 'is_array' ) ),
		);
	}

	/**
	 * Writes the whole list.
	 *
	 * @param array[] $rows Rows.
	 * @return void
	 */
	private static function store( array $rows ) {
		update_option( self::OPTION_MAPPINGS, array_values( $rows ), false );
		self::$cache = null;
		self::$index = null;
	}

	/**
	 * Persists the upgraded row format once (runs from DB_Installer).
	 * Existing symptoms keep their status; Active ones keep exactly the
	 * content patients already see as their published version.
	 *
	 * @return void
	 */
	public static function migrate() {
		if ( (int) get_option( self::OPTION_SCHEMA, 1 ) >= self::SCHEMA ) {
			return;
		}

		$raw = get_option( self::OPTION_MAPPINGS, null );

		if ( is_array( $raw ) && $raw ) {
			self::store( self::all() );
		}

		$notice = get_option( self::OPTION_NOTICE, null );

		if ( is_array( $notice ) ) {
			update_option( self::OPTION_NOTICE, self::notice(), false );
		}

		update_option( self::OPTION_SCHEMA, self::SCHEMA, false );
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
	 * Comparable form of a phrase: lower case, letters/digits only, single
	 * spaces, a trailing plural "s" dropped from each word.
	 *
	 * @param string $text Phrase.
	 * @return string
	 */
	public static function normalize_phrase( $text ) {
		$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );
		$text  = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
		$words = array_filter( explode( ' ', trim( (string) $text ) ), 'strlen' );

		$words = array_map(
			function ( $word ) {
				return ( strlen( $word ) > 3 && 's' === substr( $word, -1 ) && 'ss' !== substr( $word, -2 ) ) ? substr( $word, 0, -1 ) : $word;
			},
			$words
		);

		return implode( ' ', $words );
	}

	/**
	 * Resolves a specialty given as a slug or a label to its slug.
	 *
	 * @param string $value Slug or label.
	 * @return string Slug, or '' when it isn't a specialty on this site.
	 */
	public static function resolve_specialty( $value ) {
		$all   = Specializations::get_all();
		$value = trim( (string) $value );

		if ( isset( $all[ $value ] ) ) {
			return $value;
		}

		foreach ( $all as $slug => $label ) {
			if ( 0 === strcasecmp( $label, $value ) || sanitize_title( $value ) === $slug ) {
				return $slug;
			}
		}

		return '';
	}

	/**
	 * Cleans a list of phrases: trimmed, de-duplicated against $seen.
	 *
	 * @param mixed $list   Array or newline/comma/semicolon/pipe-separated text.
	 * @param array $seen   normalized phrase => true (updated).
	 * @param array $errors Errors (updated).
	 * @param string $field Error key.
	 * @return string[]
	 */
	private static function clean_phrases( $list, array &$seen, array &$errors, $field ) {
		if ( is_string( $list ) ) {
			$list = preg_split( '/[\r\n,;|]+/', $list );
		}

		$clean = array();

		foreach ( (array) $list as $phrase ) {
			$phrase = trim( sanitize_text_field( (string) $phrase ) );
			$key    = self::normalize_phrase( $phrase );

			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}

			if ( strlen( $phrase ) > self::MAX_TEXT ) {
				/* translators: %d: maximum length. */
				$errors[ $field ] = sprintf( __( 'Each alternative phrase must be %d characters or fewer.', 'doctor-ak-portal' ), self::MAX_TEXT );
			}

			$seen[ $key ] = true;
			$clean[]      = $phrase;
		}

		if ( count( $clean ) > self::MAX_SYNONYMS ) {
			/* translators: %d: maximum number. */
			$errors[ $field ] = sprintf( __( 'Use at most %d phrases in each list.', 'doctor-ak-portal' ), self::MAX_SYNONYMS );
		}

		return $clean;
	}

	/**
	 * Cleans a list of specialties to known slugs.
	 *
	 * @param mixed    $values  Slugs or labels.
	 * @param string[] $invalid Unknown values (updated).
	 * @return string[]
	 */
	private static function clean_specialties( $values, array &$invalid ) {
		if ( is_string( $values ) ) {
			$values = preg_split( '/[,;|]+/', $values );
		}

		$slugs = array();

		foreach ( (array) $values as $value ) {
			$slug = self::resolve_specialty( $value );

			if ( '' === $slug ) {
				if ( '' !== trim( (string) $value ) ) {
					$invalid[] = sanitize_text_field( (string) $value );
				}
			} elseif ( ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Trims text to a length (multibyte-safe).
	 *
	 * @param string $text Text.
	 * @param int    $max  Length.
	 * @return string
	 */
	private static function cut( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $max ) : substr( (string) $text, 0, $max );
	}

	/**
	 * Text length (multibyte-safe).
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function len( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text ) : strlen( (string) $text );
	}

	/**
	 * Cleans and checks follow-up questions.
	 *
	 * Questions are asked in list order. A question may be shown only when
	 * an earlier choice question was answered a certain way (show_if). Each
	 * says whether it affects navigation, safety messages or specialty
	 * matching; an answer can add specialties only when "matching" is set.
	 * There is no scoring: answers never produce a diagnosis or a risk.
	 *
	 * @param mixed $input Array of questions, or JSON text.
	 * @return array { questions: array[], errors: string[] }
	 */
	public static function validate_questions( $input ) {
		if ( is_string( $input ) ) {
			$input = '' === trim( $input ) ? array() : json_decode( $input, true );

			if ( ! is_array( $input ) ) {
				return array(
					'questions' => array(),
					'errors'    => array( __( 'The follow-up questions could not be read. Please try again.', 'doctor-ak-portal' ) ),
				);
			}
		}

		$errors    = array();
		$questions = array();
		$ids       = array();

		if ( count( (array) $input ) > self::MAX_QUESTIONS ) {
			/* translators: %d: maximum number. */
			$errors[] = sprintf( __( 'Use at most %d follow-up questions per symptom.', 'doctor-ak-portal' ), self::MAX_QUESTIONS );
		}

		foreach ( array_values( array_slice( (array) $input, 0, self::MAX_QUESTIONS ) ) as $i => $q ) {
			if ( ! is_array( $q ) ) {
				continue;
			}

			/* translators: %d: question number. */
			$where = sprintf( __( 'Question %d:', 'doctor-ak-portal' ), $i + 1 );
			$id    = isset( $q['id'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $q['id'] ) ) : '';

			if ( '' === $id || isset( $ids[ $id ] ) ) {
				$n = $i + 1;

				do {
					$id = 'q' . $n++;
				} while ( isset( $ids[ $id ] ) );
			}

			$id   = substr( $id, 0, 40 );
			$type = isset( $q['type'] ) && in_array( $q['type'], self::QUESTION_TYPES, true ) ? $q['type'] : '';
			$text = trim( sanitize_text_field( isset( $q['text'] ) ? (string) $q['text'] : '' ) );

			if ( '' === $text ) {
				$errors[] = $where . ' ' . __( 'enter the question.', 'doctor-ak-portal' );
			} elseif ( self::len( $text ) > self::MAX_QUESTION ) {
				/* translators: %d: maximum length. */
				$errors[] = $where . ' ' . sprintf( __( 'keep the question to %d characters.', 'doctor-ak-portal' ), self::MAX_QUESTION );
			}

			if ( '' === $type ) {
				$errors[] = $where . ' ' . __( 'choose a question type.', 'doctor-ak-portal' );
				$type     = 'single';
			}

			$affects = isset( $q['affects'] ) && is_array( $q['affects'] ) ? $q['affects'] : array();
			$affects = array(
				'navigation' => ! empty( $affects['navigation'] ),
				'safety'     => ! empty( $affects['safety'] ),
				'matching'   => ! empty( $affects['matching'] ),
			);

			$clean = array(
				'id'          => $id,
				'text'        => self::cut( $text, self::MAX_QUESTION ),
				'type'        => $type,
				'required'    => ! empty( $q['required'] ),
				'explanation' => self::cut( trim( sanitize_textarea_field( isset( $q['explanation'] ) ? (string) $q['explanation'] : '' ) ), self::MAX_EXPLANATION ),
				'affects'     => $affects,
				'options'     => array(),
				'number'      => null,
				'show_if'     => null,
			);

			if ( 'number' === $type ) {
				$n   = isset( $q['number'] ) && is_array( $q['number'] ) ? $q['number'] : array();
				$min = isset( $n['min'] ) && '' !== $n['min'] && null !== $n['min'] ? $n['min'] : null;
				$max = isset( $n['max'] ) && '' !== $n['max'] && null !== $n['max'] ? $n['max'] : null;

				if ( ( null !== $min && ! is_numeric( $min ) ) || ( null !== $max && ! is_numeric( $max ) ) ) {
					$errors[] = $where . ' ' . __( 'the smallest and largest allowed answers must be numbers.', 'doctor-ak-portal' );
				} elseif ( null !== $min && null !== $max && (float) $min > (float) $max ) {
					$errors[] = $where . ' ' . __( 'the smallest allowed answer is larger than the largest.', 'doctor-ak-portal' );
				}

				$clean['number'] = array(
					'min'  => is_numeric( $min ) ? 0 + $min : null,
					'max'  => is_numeric( $max ) ? 0 + $max : null,
					'unit' => self::cut( trim( sanitize_text_field( isset( $n['unit'] ) ? (string) $n['unit'] : '' ) ), 20 ),
				);

				if ( $affects['matching'] ) {
					$errors[] = $where . ' ' . __( 'number questions can’t change specialty matching — untick “Specialty matching”.', 'doctor-ak-portal' );
				}
			} else {
				$raw = isset( $q['options'] ) && is_array( $q['options'] ) ? array_values( $q['options'] ) : array();

				if ( 'yesno' === $type ) {
					$by_id = array();

					foreach ( $raw as $option ) {
						if ( is_array( $option ) && isset( $option['id'] ) ) {
							$by_id[ (string) $option['id'] ] = $option;
						}
					}

					$raw = array(
						array_merge( isset( $by_id['yes'] ) ? $by_id['yes'] : array(), array( 'id' => 'yes', 'label' => __( 'Yes', 'doctor-ak-portal' ) ) ),
						array_merge( isset( $by_id['no'] ) ? $by_id['no'] : array(), array( 'id' => 'no', 'label' => __( 'No', 'doctor-ak-portal' ) ) ),
					);
				}

				if ( count( $raw ) < 2 ) {
					$errors[] = $where . ' ' . __( 'add at least two answers.', 'doctor-ak-portal' );
				} elseif ( count( $raw ) > self::MAX_OPTIONS ) {
					/* translators: %d: maximum number. */
					$errors[] = $where . ' ' . sprintf( __( 'use at most %d answers.', 'doctor-ak-portal' ), self::MAX_OPTIONS );
				}

				$option_ids = array();
				$adds_specs = false;

				foreach ( array_slice( $raw, 0, self::MAX_OPTIONS ) as $o => $option ) {
					$option = is_array( $option ) ? $option : array();
					$oid    = isset( $option['id'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $option['id'] ) ) : '';

					if ( '' === $oid || isset( $option_ids[ $oid ] ) ) {
						$n = $o + 1;

						do {
							$oid = 'a' . $n++;
						} while ( isset( $option_ids[ $oid ] ) );
					}

					$oid   = substr( $oid, 0, 40 );
					$label = trim( sanitize_text_field( isset( $option['label'] ) ? (string) $option['label'] : '' ) );

					if ( '' === $label ) {
						/* translators: %d: answer number. */
						$errors[] = $where . ' ' . sprintf( __( 'answer %d is empty.', 'doctor-ak-portal' ), $o + 1 );
					}

					$invalid = array();
					$specs   = self::clean_specialties( isset( $option['specialties'] ) ? $option['specialties'] : array(), $invalid );

					if ( $invalid ) {
						/* translators: %s: specialty names. */
						$errors[] = $where . ' ' . sprintf( __( 'not a specialty on this site: %s.', 'doctor-ak-portal' ), implode( ', ', $invalid ) );
					}

					$adds_specs              = $adds_specs || ! empty( $specs );
					$option_ids[ $oid ]      = true;
					$clean['options'][]      = array(
						'id'          => $oid,
						'label'       => self::cut( $label, self::MAX_OPTION ),
						'specialties' => $specs,
					);
				}

				if ( $adds_specs && ! $affects['matching'] ) {
					$errors[] = $where . ' ' . __( 'some answers add specialties, so tick “Specialty matching”.', 'doctor-ak-portal' );
				}
			}

			// Conditional display: only after an earlier choice question.
			if ( isset( $q['show_if'] ) && is_array( $q['show_if'] ) && ! empty( $q['show_if']['question'] ) ) {
				$parent_id = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $q['show_if']['question'] ) );
				$parent    = isset( $ids[ $parent_id ] ) ? $questions[ $ids[ $parent_id ] ] : null;
				$answers   = array_values( array_unique( array_map( 'strval', isset( $q['show_if']['answers'] ) ? (array) $q['show_if']['answers'] : array() ) ) );

				if ( ! $parent ) {
					$errors[] = $where . ' ' . __( '“Only show when” must refer to an earlier question.', 'doctor-ak-portal' );
				} elseif ( 'number' === $parent['type'] ) {
					$errors[] = $where . ' ' . __( '“Only show when” can only depend on a choice or yes/no question.', 'doctor-ak-portal' );
				} else {
					$valid   = wp_list_pluck( $parent['options'], 'id' );
					$answers = array_values( array_intersect( $answers, $valid ) );

					if ( ! $answers ) {
						$errors[] = $where . ' ' . __( 'choose which answers make it appear.', 'doctor-ak-portal' );
					}

					$clean['show_if'] = array(
						'question' => $parent_id,
						'answers'  => $answers,
					);
				}
			}

			$ids[ $id ]  = count( $questions );
			$questions[] = $clean;
		}

		return array(
			'questions' => $questions,
			'errors'    => $errors,
		);
	}

	/**
	 * Cleans and checks a symptom's fields.
	 *
	 * @param array $input     { name, slug, description, synonyms, phrases { code: list }, specialties, questions, notes }.
	 * @param int   $ignore_id ID being edited (excluded from duplicate checks).
	 * @return array { fields: array, errors: array field => message, duplicate: bool }
	 */
	public static function validate( array $input, $ignore_id = 0 ) {
		$errors = array();
		$name   = trim( sanitize_text_field( isset( $input['name'] ) ? (string) $input['name'] : '' ) );

		if ( '' === $name ) {
			$errors['name'] = __( 'Enter the symptom name.', 'doctor-ak-portal' );
		} elseif ( self::len( $name ) > self::MAX_TEXT ) {
			/* translators: %d: maximum length. */
			$errors['name'] = sprintf( __( 'Keep the symptom name to %d characters or fewer.', 'doctor-ak-portal' ), self::MAX_TEXT );
		}

		$slug = sanitize_title( isset( $input['slug'] ) && '' !== trim( (string) $input['slug'] ) ? (string) $input['slug'] : $name );
		$slug = substr( $slug, 0, 80 );

		if ( '' === $slug && '' !== $name ) {
			$errors['slug'] = __( 'Enter an identifier using letters, numbers and hyphens.', 'doctor-ak-portal' );
		}

		$description = trim( sanitize_textarea_field( isset( $input['description'] ) ? (string) $input['description'] : '' ) );

		if ( self::len( $description ) > self::MAX_DESCRIPTION ) {
			/* translators: %d: maximum length. */
			$errors['description'] = sprintf( __( 'Keep the patient guidance to %d characters or fewer.', 'doctor-ak-portal' ), self::MAX_DESCRIPTION );
		}

		$seen     = array( self::normalize_phrase( $name ) => true );
		$synonyms = self::clean_phrases( isset( $input['synonyms'] ) ? $input['synonyms'] : array(), $seen, $errors, 'synonyms' );
		$phrases  = array();
		$given    = isset( $input['phrases'] ) && is_array( $input['phrases'] ) ? $input['phrases'] : array();

		foreach ( self::languages() as $code => $label ) {
			$list = self::clean_phrases( isset( $given[ $code ] ) ? $given[ $code ] : array(), $seen, $errors, 'phrases_' . $code );

			if ( $list ) {
				$phrases[ $code ] = $list;
			}
		}

		ksort( $phrases );

		$invalid     = array();
		$specialties = self::clean_specialties( isset( $input['specialties'] ) ? $input['specialties'] : array(), $invalid );

		if ( ! empty( $invalid ) ) {
			/* translators: %s: specialty names. */
			$errors['specialties'] = sprintf( __( 'Not a specialty on this site: %s.', 'doctor-ak-portal' ), implode( ', ', $invalid ) );
		} elseif ( empty( $specialties ) ) {
			$errors['specialties'] = __( 'Choose at least one related specialty.', 'doctor-ak-portal' );
		}

		$checked = self::validate_questions( isset( $input['questions'] ) ? $input['questions'] : array() );

		if ( $checked['errors'] ) {
			$errors['questions'] = implode( ' ', $checked['errors'] );
		}

		// A name, identifier or phrase may belong to one symptom only.
		$duplicate = false;

		if ( '' !== $name ) {
			$mine = array_merge( array( $name ), $synonyms );

			foreach ( $phrases as $list ) {
				$mine = array_merge( $mine, $list );
			}

			$index = self::phrase_index();
			$other = function ( array $ids ) use ( $ignore_id ) {
				foreach ( $ids as $id ) {
					if ( (int) $id !== (int) $ignore_id ) {
						return self::find( $id );
					}
				}

				return null;
			};

			$clash = '' !== $slug && isset( $index['slugs'][ $slug ] ) ? $other( $index['slugs'][ $slug ] ) : null;

			if ( $clash ) {
				/* translators: 1: identifier, 2: other symptom's name. */
				$errors['slug'] = sprintf( __( 'The identifier “%1$s” is already used by “%2$s”.', 'doctor-ak-portal' ), $slug, $clash['name'] );
				$duplicate      = true;
			}

			foreach ( $mine as $i => $phrase ) {
				$key   = self::normalize_phrase( $phrase );
				$clash = isset( $index['phrases'][ $key ] ) ? $other( $index['phrases'][ $key ] ) : null;

				if ( $clash ) {
					$field = 0 === $i ? 'name' : 'synonyms';
					/* translators: 1: phrase, 2: other symptom's name. */
					$errors[ $field ] = sprintf( __( '“%1$s” is already used by the symptom “%2$s”.', 'doctor-ak-portal' ), $phrase, $clash['name'] );
					$duplicate        = true;
					break;
				}
			}
		}

		return array(
			'fields'    => array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'synonyms'    => $synonyms,
				'phrases'     => $phrases,
				'specialties' => $specialties,
				'questions'   => $checked['questions'],
				'notes'       => isset( $input['notes'] ) ? sanitize_textarea_field( (string) $input['notes'] ) : '',
			),
			'errors'    => $errors,
			'duplicate' => $duplicate,
		);
	}

	/**
	 * Every phrase a row answers to (working and published copies, so a
	 * phrase can't be reused while it's still live elsewhere).
	 *
	 * @param array $row Row.
	 * @return string[]
	 */
	private static function phrases_of( array $row ) {
		$out = array();

		foreach ( array_filter( array( $row, $row['published'] ) ) as $copy ) {
			$copy = self::normalize_content( $copy );
			$out  = array_merge( $out, array( $copy['name'] ), self::all_phrases( $copy ) );
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Who already uses each normalized phrase and identifier, built once
	 * per request so checking every row stays linear.
	 *
	 * @return array { phrases: key => int[] ids, slugs: slug => int[] ids }
	 */
	private static function phrase_index() {
		if ( null !== self::$index ) {
			return self::$index;
		}

		$index = array(
			'phrases' => array(),
			'slugs'   => array(),
		);

		foreach ( self::all() as $row ) {
			foreach ( self::phrases_of( $row ) as $phrase ) {
				$index['phrases'][ self::normalize_phrase( $phrase ) ][] = (int) $row['id'];
			}

			foreach ( array_unique( array_filter( array( $row['slug'], isset( $row['published']['slug'] ) ? (string) $row['published']['slug'] : '' ) ) ) as $slug ) {
				$index['slugs'][ $slug ][] = (int) $row['id'];
			}
		}

		self::$index = $index;

		return $index;
	}

	/**
	 * English and other-language phrases of one copy, in one list.
	 *
	 * @param array $content Content fields.
	 * @return string[]
	 */
	private static function all_phrases( array $content ) {
		$out = (array) $content['synonyms'];

		foreach ( (array) $content['phrases'] as $list ) {
			$out = array_merge( $out, (array) $list );
		}

		return array_values( array_unique( $out ) );
	}

	/* ---------------------------------------------------------------------
	 * Changes
	 * ------------------------------------------------------------------- */

	/**
	 * Adds or updates a symptom. A content change returns the working copy
	 * to Draft; a live symptom keeps its published version meanwhile.
	 *
	 * @param array  $input   Fields (see validate()).
	 * @param int    $id      Symptom ID to update, or 0 to add.
	 * @param int    $user_id Who is saving.
	 * @param string $source  'manual', 'import', 'example' or 'legacy' (new rows only).
	 * @return array|\WP_Error Saved row, or WP_Error with field errors as data.
	 */
	public static function save( array $input, $id, $user_id, $source = 'manual' ) {
		$existing = $id ? self::find( $id ) : null;

		if ( $id && ! $existing ) {
			return new \WP_Error( 'doctor_ak_sm_missing', __( 'That symptom no longer exists.', 'doctor-ak-portal' ) );
		}

		// Fields not sent keep their current values (e.g. the paste import
		// has no questions).
		if ( $existing ) {
			foreach ( array( 'slug', 'description', 'phrases', 'questions' ) as $key ) {
				if ( ! array_key_exists( $key, $input ) ) {
					$input[ $key ] = $existing[ $key ];
				}
			}
		}

		$check = self::validate( $input, $id );

		if ( ! empty( $check['errors'] ) ) {
			return new \WP_Error( 'doctor_ak_sm_invalid', __( 'Please fix the highlighted fields.', 'doctor-ak-portal' ), $check['errors'] );
		}

		$rows = self::all();

		if ( $existing ) {
			foreach ( $rows as &$row ) {
				if ( (int) $row['id'] !== (int) $id ) {
					continue;
				}

				$result = Clinical_Workflow::apply_edit( $row, $check['fields'], self::CONTENT_KEYS, $user_id );
				$row    = $result['row'];
				$saved  = $row;

				Symptom_Audit::log( Symptom_Audit::TYPE_SYMPTOM, $id, $result['changed'] ? 'edit' : 'notes', $user_id, $result['changed'] && $row['is_live'] ? __( 'Revision started; the approved version stays live until this is approved and activated.', 'doctor-ak-portal' ) : '' );
			}
			unset( $row );
		} else {
			if ( count( $rows ) >= self::MAX_MAPPINGS ) {
				return new \WP_Error( 'doctor_ak_sm_full', __( 'The maximum number of symptoms has been reached.', 'doctor-ak-portal' ) );
			}

			$now    = current_time( 'mysql' );
			$saved  = self::normalize_row(
				array_merge(
					$check['fields'],
					array(
						'id'         => self::next_id(),
						'published'  => null,
						'status'     => self::STATUS_DRAFT,
						'source'     => in_array( $source, array( 'manual', 'import', 'example', 'legacy' ), true ) ? $source : 'manual',
						'created_at' => $now,
						'created_by' => (int) $user_id,
						'updated_at' => $now,
						'updated_by' => (int) $user_id,
					)
				)
			);
			$rows[] = $saved;

			Symptom_Audit::log( Symptom_Audit::TYPE_SYMPTOM, $saved['id'], 'create', $user_id, 'manual' !== $saved['source'] ? $saved['source'] : '' );
		}

		self::store( $rows );

		return $saved;
	}

	/**
	 * Deletes a symptom — administrators only (checked by the caller), and
	 * never while it's live or a safety rule refers to it.
	 *
	 * @param int $id      Symptom ID.
	 * @param int $user_id Acting user.
	 * @return true|\WP_Error
	 */
	public static function delete( $id, $user_id = 0 ) {
		$row = self::find( $id );

		if ( ! $row ) {
			return new \WP_Error( 'doctor_ak_sm_missing', __( 'That symptom no longer exists.', 'doctor-ak-portal' ) );
		}

		if ( $row['is_live'] ) {
			return new \WP_Error( 'doctor_ak_sm_live', __( 'It’s live on the page. A clinical reviewer needs to deactivate it before it can be deleted.', 'doctor-ak-portal' ) );
		}

		$rules = Symptom_Safety_Rules::referencing_symptom( $id );

		if ( $rules ) {
			/* translators: %s: safety rule titles. */
			return new \WP_Error( 'doctor_ak_sm_in_use', sprintf( __( 'Safety rules refer to this symptom: %s. Change those rules first.', 'doctor-ak-portal' ), implode( ', ', $rules ) ) );
		}

		self::store(
			array_filter(
				self::all(),
				function ( $other ) use ( $id ) {
					return (int) $other['id'] !== (int) $id;
				}
			)
		);

		Symptom_Audit::log( Symptom_Audit::TYPE_SYMPTOM, $id, 'delete', $user_id ? $user_id : get_current_user_id(), $row['name'], Clinical_Workflow::content( $row, self::CONTENT_KEYS ) );

		return true;
	}

	/**
	 * Moves a symptom through the lifecycle (see Clinical_Workflow).
	 *
	 * @param int    $id      Symptom ID.
	 * @param string $action  submit | approve | review (= approve) | return | activate | deactivate | archive | restore.
	 * @param int    $user_id Acting user.
	 * @param string $reason  Reason (for 'return').
	 * @return array|\WP_Error Updated row.
	 */
	public static function transition( $id, $action, $user_id, $reason = '' ) {
		$action = 'review' === $action ? 'approve' : $action;
		$rows   = self::all();

		foreach ( $rows as &$row ) {
			if ( (int) $row['id'] !== (int) $id ) {
				continue;
			}

			$errors = array();

			if ( in_array( $action, array( 'submit', 'approve', 'activate' ), true ) ) {
				$errors = array_values( self::validate( $row, $row['id'] )['errors'] );
			}

			// Activating must not break a live safety rule that depends on
			// one of this symptom's questions or answers.
			if ( 'activate' === $action ) {
				$errors = array_merge( $errors, Symptom_Safety_Rules::broken_by_symptom( $row['id'], Clinical_Workflow::content( $row, self::CONTENT_KEYS ) ) );
			}

			if ( 'deactivate' === $action ) {
				$rules = Symptom_Safety_Rules::referencing_symptom( $row['id'], true );

				if ( $rules ) {
					/* translators: %s: rule titles. */
					return new \WP_Error( 'doctor_ak_sm_in_use', sprintf( __( 'Live safety rules depend on this symptom: %s. Deactivate or change them first, so their instructions aren’t lost.', 'doctor-ak-portal' ), implode( ', ', $rules ) ) );
				}
			}

			$result = Clinical_Workflow::transition(
				$row,
				$action,
				$user_id,
				array(
					'can_manage' => self::can_manage( $user_id ),
					'can_review' => self::can_review( $user_id ),
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
			return new \WP_Error( 'doctor_ak_sm_missing', __( 'That symptom no longer exists.', 'doctor-ak-portal' ) );
		}

		self::store( $rows );

		Symptom_Audit::log(
			Symptom_Audit::TYPE_SYMPTOM,
			$id,
			$action,
			$user_id,
			Clinical_Workflow::audit_summary( $action, $updated ),
			'activate' === $action ? $updated['published'] : null
		);

		return $updated;
	}

	/* ---------------------------------------------------------------------
	 * Import / export, examples and the legacy ruleset
	 * ------------------------------------------------------------------- */

	/**
	 * CSV columns, in export order. Lists inside a cell are separated by "|".
	 *
	 * @return string[]
	 */
	public static function csv_columns() {
		$columns = array( 'name', 'slug', 'description', 'english_phrases' );

		foreach ( array_keys( self::languages() ) as $code ) {
			$columns[] = str_replace( '-', '_', $code ) . '_phrases';
		}

		return array_merge( $columns, array( 'specialties', 'notes', 'status' ) );
	}

	/**
	 * Parses an import: CSV with a header row (see csv_columns()), a JSON
	 * array of objects, or one per line "Symptom | phrase, phrase |
	 * Specialty, Specialty".
	 *
	 * @param string $text Uploaded or pasted text.
	 * @return array[] Raw rows { name, slug, description, synonyms[], phrases{}, specialties[], notes, line }.
	 */
	public static function parse_import( $text ) {
		$text = trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text ) );
		$rows = array();

		if ( '' === $text ) {
			return $rows;
		}

		$json = json_decode( $text, true );

		if ( is_array( $json ) ) {
			foreach ( array_values( $json ) as $i => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$rows[] = array(
					'name'        => isset( $item['name'] ) ? (string) $item['name'] : ( isset( $item['label'] ) ? (string) $item['label'] : '' ),
					'slug'        => isset( $item['slug'] ) ? (string) $item['slug'] : '',
					'description' => isset( $item['description'] ) ? (string) $item['description'] : '',
					'synonyms'    => isset( $item['synonyms'] ) ? (array) $item['synonyms'] : array(),
					'phrases'     => isset( $item['phrases'] ) && is_array( $item['phrases'] ) ? $item['phrases'] : array(),
					'specialties' => isset( $item['specialties'] ) ? (array) $item['specialties'] : array(),
					'notes'       => isset( $item['notes'] ) ? (string) $item['notes'] : '',
					'line'        => $i + 1,
				);
			}

			return $rows;
		}

		$first = strtolower( strtok( $text, "\r\n" ) );

		if ( false !== strpos( $first, ',' ) && preg_match( '/(^|,)\s*"?name"?\s*(,|$)/', $first ) ) {
			return self::parse_csv( $text );
		}

		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $i => $line ) {
			if ( '' === trim( $line ) || 0 === strpos( ltrim( $line ), '#' ) ) {
				continue;
			}

			$parts  = array_map( 'trim', explode( '|', $line ) );
			$split  = function ( $value ) {
				return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) );
			};
			$rows[] = array(
				'name'        => $parts[0],
				'synonyms'    => isset( $parts[1] ) ? $split( $parts[1] ) : array(),
				'specialties' => isset( $parts[2] ) ? $split( $parts[2] ) : array(),
				'line'        => $i + 1,
			);
		}

		return $rows;
	}

	/**
	 * Parses CSV text with a header row. Unknown columns are ignored;
	 * `status` is ignored on import (everything arrives as Draft).
	 *
	 * @param string $text CSV.
	 * @return array[]
	 */
	private static function parse_csv( $text ) {
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream for fgetcsv().
		fwrite( $handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $handle );

		$header = null;
		$rows   = array();
		$line   = 0;
		$langs  = array();

		foreach ( array_keys( self::languages() ) as $code ) {
			$langs[ str_replace( '-', '_', $code ) . '_phrases' ] = $code;
		}

		$split = function ( $value ) {
			return array_values( array_filter( array_map( 'trim', explode( '|', (string) $value ) ), 'strlen' ) );
		};

		while ( false !== ( $cells = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			++$line;

			if ( null === $header ) {
				$header = array_map(
					function ( $cell ) {
						return strtolower( trim( (string) $cell ) );
					},
					$cells
				);
				continue;
			}

			if ( 1 === count( $cells ) && ( null === $cells[0] || '' === trim( (string) $cells[0] ) ) ) {
				continue;
			}

			$cell = function ( $name ) use ( $header, $cells ) {
				$index = array_search( $name, $header, true );
				return false === $index || ! isset( $cells[ $index ] ) ? '' : self::from_csv_cell( (string) $cells[ $index ] );
			};

			$phrases = array();

			foreach ( $langs as $column => $code ) {
				$phrases[ $code ] = $split( $cell( $column ) );
			}

			$english = $cell( 'english_phrases' );

			$rows[] = array(
				'name'        => $cell( 'name' ),
				'slug'        => $cell( 'slug' ),
				'description' => $cell( 'description' ),
				'synonyms'    => $split( '' !== $english ? $english : $cell( 'synonyms' ) ),
				'phrases'     => $phrases,
				'specialties' => $split( $cell( 'specialties' ) ),
				'notes'       => $cell( 'notes' ),
				'line'        => $line,
			);
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $rows;
	}

	/**
	 * Undoes the spreadsheet-formula guard added on export.
	 *
	 * @param string $value Cell.
	 * @return string
	 */
	private static function from_csv_cell( $value ) {
		return preg_match( "/^'[=+\-@\t\r]/", $value ) ? substr( $value, 1 ) : $value;
	}

	/**
	 * A cell safe to open in a spreadsheet: values that start like a
	 * formula are prefixed with an apostrophe (CSV injection).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function to_csv_cell( $value ) {
		$value = (string) $value;

		return preg_match( "/^[=+\-@\t\r]/", $value ) ? "'" . $value : $value;
	}

	/**
	 * All symptoms as CSV (working copies; questions aren't included —
	 * they're edited on screen).
	 *
	 * @return string
	 */
	public static function export_csv() {
		$handle  = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream for fputcsv().
		$columns = self::csv_columns();
		$labels  = Specializations::get_all();

		fputcsv( $handle, $columns, ',', '"', '\\' );

		foreach ( self::all() as $row ) {
			$line = array(
				$row['name'],
				$row['slug'],
				$row['description'],
				implode( ' | ', $row['synonyms'] ),
			);

			foreach ( array_keys( self::languages() ) as $code ) {
				$line[] = implode( ' | ', isset( $row['phrases'][ $code ] ) ? $row['phrases'][ $code ] : array() );
			}

			$line[] = implode(
				' | ',
				array_map(
					function ( $slug ) use ( $labels ) {
						return isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
					},
					$row['specialties']
				)
			);
			$line[] = $row['notes'];
			$line[] = $row['status'] . ( $row['is_live'] ? ' (live)' : '' );

			fputcsv( $handle, array_map( array( __CLASS__, 'to_csv_cell' ), $line ), ',', '"', '\\' );
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return (string) $csv;
	}

	/**
	 * What importing these rows would do, without saving anything.
	 *
	 * @param array[] $rows Rows from parse_import().
	 * @return array[] Each { line, name, synonyms, specialties (labels), result: 'ready'|'duplicate'|'invalid', messages[] }.
	 */
	public static function preview_import( array $rows ) {
		$labels  = Specializations::get_all();
		$out     = array();
		$in_file = array();

		foreach ( $rows as $row ) {
			$check    = self::validate( $row, 0 );
			$messages = array_values( $check['errors'] );
			$result   = 'ready';
			$keys     = array( self::normalize_phrase( $check['fields']['name'] ) );

			foreach ( $check['fields']['synonyms'] as $phrase ) {
				$keys[] = self::normalize_phrase( $phrase );
			}

			foreach ( $check['fields']['phrases'] as $list ) {
				foreach ( $list as $phrase ) {
					$keys[] = self::normalize_phrase( $phrase );
				}
			}

			$keys[] = 'slug:' . $check['fields']['slug'];
			$keys   = array_filter( $keys, 'strlen' );
			$clash  = array_intersect( $keys, array_keys( $in_file ) );

			if ( $clash && '' !== $check['fields']['name'] ) {
				$result = 'duplicate';
				/* translators: %d: line number. */
				$messages[] = sprintf( __( 'Repeats a name or phrase from line %d of this import.', 'doctor-ak-portal' ), $in_file[ reset( $clash ) ] );
			} elseif ( $check['duplicate'] ) {
				$result = 'duplicate';
			} elseif ( ! empty( $check['errors'] ) ) {
				$result = 'invalid';
			}

			foreach ( $keys as $key ) {
				if ( ! isset( $in_file[ $key ] ) ) {
					$in_file[ $key ] = (int) $row['line'];
				}
			}

			$out[] = array(
				'line'        => (int) $row['line'],
				'name'        => $check['fields']['name'] ? $check['fields']['name'] : (string) $row['name'],
				'synonyms'    => self::all_phrases( $check['fields'] ),
				'specialties' => array_map(
					function ( $slug ) use ( $labels ) {
						return isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
					},
					$check['fields']['specialties']
				),
				'result'      => $result,
				'messages'    => $messages,
			);
		}

		return $out;
	}

	/**
	 * Imports the rows that are ready, as Drafts. Rows that clash with an
	 * existing symptom, or with an earlier row of the same import, are
	 * skipped — nothing existing is overwritten.
	 *
	 * @param array[] $rows    Rows from parse_import().
	 * @param int     $user_id Importing user.
	 * @param string  $source  'import', 'example' or 'legacy'.
	 * @return array { added: int, skipped: int }
	 */
	public static function import( array $rows, $user_id, $source = 'import' ) {
		$added   = 0;
		$skipped = 0;

		foreach ( self::preview_import( $rows ) as $i => $preview ) {
			if ( 'ready' !== $preview['result'] ) {
				++$skipped;
				continue;
			}

			$saved = self::save( $rows[ $i ], 0, $user_id, $source );

			if ( is_wp_error( $saved ) ) {
				++$skipped;
			} else {
				++$added;
			}
		}

		return array(
			'added'   => $added,
			'skipped' => $skipped,
		);
	}

	/**
	 * The bundled example symptoms (illustrative drafts, not clinically
	 * reviewed).
	 *
	 * @return array[] Rows for import().
	 */
	public static function example_rows() {
		$path = DOCTOR_AK_PORTAL_PATH . 'includes/data/symptom-mapping-examples.json';
		$data = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled file.

		return self::parse_import( wp_json_encode( isset( $data['mappings'] ) ? $data['mappings'] : array() ) );
	}

	/**
	 * Symptoms from the previous Symptom Checker's ruleset (draft or
	 * published), shaped for import(). The ruleset itself is not changed.
	 *
	 * @return array[]
	 */
	public static function legacy_rows() {
		$rules = null;

		if ( class_exists( __NAMESPACE__ . '\\Symptom_Rules' ) ) {
			$published = Symptom_Rules::get_published();
			$draft     = Symptom_Rules::get_draft();
			$parsed    = '' !== trim( $draft['json'] ) ? json_decode( $draft['json'], true ) : null;
			$rules     = $published ? $published['rules'] : $parsed;
		}

		$items = array();

		foreach ( isset( $rules['symptoms'] ) && is_array( $rules['symptoms'] ) ? $rules['symptoms'] : array() as $symptom ) {
			$items[] = array(
				'name'        => isset( $symptom['label'] ) ? $symptom['label'] : '',
				'synonyms'    => isset( $symptom['synonyms'] ) ? $symptom['synonyms'] : array(),
				'specialties' => isset( $symptom['specialties'] ) ? $symptom['specialties'] : array(),
			);
		}

		return self::parse_import( wp_json_encode( $items ) );
	}

	/* ---------------------------------------------------------------------
	 * Emergency notice + publication
	 * ------------------------------------------------------------------- */

	/**
	 * The general emergency notice shown at the top of the page, whatever
	 * the patient enters. `approved_text` is what patients see; `text` is
	 * the working copy (they differ while an edit awaits approval).
	 *
	 * @return array { text, approved_text, status 'draft'|'approved', updated_at, updated_by, approved_at, approved_by }
	 */
	public static function notice() {
		$stored = get_option( self::OPTION_NOTICE, array() );
		$stored = is_array( $stored ) ? $stored : array();

		// Before schema 2 an approved notice had no separate approved copy.
		if ( ! array_key_exists( 'approved_text', $stored ) ) {
			$stored['approved_text'] = isset( $stored['status'], $stored['text'] ) && 'approved' === $stored['status'] ? $stored['text'] : '';
		}

		$notice = wp_parse_args(
			$stored,
			array(
				// A suggested starting point only — it stays a draft until a
				// clinical reviewer approves it.
				'text'          => __( 'If you think you may need urgent medical help, go to the nearest emergency department or contact your local emergency services now. This page helps you find doctors; it cannot tell whether your symptoms are urgent.', 'doctor-ak-portal' ),
				'approved_text' => '',
				'updated_at'    => '',
				'updated_by'    => 0,
				'approved_at'   => '',
				'approved_by'   => 0,
			)
		);

		$notice['status'] = '' !== $notice['approved_text'] && $notice['approved_text'] === $notice['text'] ? 'approved' : 'draft';

		return $notice;
	}

	/**
	 * Saves the notice text. Patients keep seeing the approved wording
	 * until the new text is approved.
	 *
	 * @param string $text    Notice text.
	 * @param int    $user_id Saving user.
	 * @return array|\WP_Error
	 */
	public static function save_notice( $text, $user_id ) {
		$text = trim( sanitize_textarea_field( (string) $text ) );

		if ( '' === $text ) {
			return new \WP_Error( 'doctor_ak_sm_notice_empty', __( 'Enter the emergency notice text.', 'doctor-ak-portal' ) );
		}

		$notice = self::notice();

		if ( $notice['text'] !== $text || '' === $notice['updated_at'] ) {
			$notice = array_merge(
				$notice,
				array(
					'text'       => $text,
					'updated_at' => current_time( 'mysql' ),
					'updated_by' => (int) $user_id,
				)
			);
			unset( $notice['status'] );
			update_option( self::OPTION_NOTICE, $notice, false );
			Symptom_Audit::log( Symptom_Audit::TYPE_NOTICE, 0, 'edit', $user_id );
		}

		return self::notice();
	}

	/**
	 * Approves the notice (clinical reviewers only).
	 *
	 * @param int $user_id Approving user.
	 * @return array|\WP_Error
	 */
	public static function approve_notice( $user_id ) {
		if ( ! self::can_review( $user_id ) ) {
			return new \WP_Error( 'doctor_ak_sm_forbidden', __( 'Only a clinical reviewer can approve the notice.', 'doctor-ak-portal' ) );
		}

		$notice = self::notice();

		if ( '' === $notice['updated_at'] ) {
			return new \WP_Error( 'doctor_ak_sm_notice_unsaved', __( 'Save the notice text first, then ask a reviewer to approve it.', 'doctor-ak-portal' ) );
		}

		if ( 'approved' === $notice['status'] ) {
			return new \WP_Error( 'doctor_ak_sm_notice_done', __( 'This wording is already approved.', 'doctor-ak-portal' ) );
		}

		$notice['approved_text'] = $notice['text'];
		$notice['approved_at']   = current_time( 'mysql' );
		$notice['approved_by']   = (int) $user_id;
		unset( $notice['status'] );
		update_option( self::OPTION_NOTICE, $notice, false );

		Symptom_Audit::log( Symptom_Audit::TYPE_NOTICE, 0, 'approve', $user_id, '', array( 'text' => $notice['text'] ) );

		return self::notice();
	}

	/**
	 * What's still needed before patients can see the page.
	 *
	 * @return string[] Human-readable missing requirements (empty when ready).
	 */
	public static function missing_requirements() {
		$missing = array();

		if ( empty( self::active() ) ) {
			$missing[] = __( 'At least one symptom must be reviewed and activated.', 'doctor-ak-portal' );
		}

		if ( '' === self::notice()['approved_text'] ) {
			$missing[] = __( 'The emergency notice must be approved by a clinical reviewer.', 'doctor-ak-portal' );
		}

		return $missing;
	}

	/**
	 * Whether the switch is on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' );
	}

	/**
	 * Turns the public page on/off (administrators — checked by the caller).
	 *
	 * @param bool $enabled On/off.
	 * @return true|\WP_Error
	 */
	public static function set_enabled( $enabled ) {
		if ( $enabled && self::missing_requirements() ) {
			return new \WP_Error( 'doctor_ak_sm_not_ready', implode( ' ', self::missing_requirements() ) );
		}

		update_option( self::OPTION_ENABLED, $enabled ? 'yes' : 'no', true );
		Symptom_Audit::log( Symptom_Audit::TYPE_PAGE, 0, $enabled ? 'enable' : 'disable', get_current_user_id() );

		return true;
	}

	/**
	 * Whether patients can use the page right now.
	 *
	 * @return bool
	 */
	public static function is_public() {
		return self::is_enabled() && ! self::missing_requirements();
	}

	/**
	 * Rows shaped for the dashboard screen (names resolved for display).
	 *
	 * @return array[]
	 */
	public static function for_admin() {
		$labels = Specializations::get_all();

		return array_map(
			function ( $row ) use ( $labels ) {
				$row                        = array_merge( $row, Clinical_Workflow::admin_fields( $row, self::CONTENT_KEYS ) );
				$row['specialty_labels']    = array_map(
					function ( $slug ) use ( $labels ) {
						return isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
					},
					$row['specialties']
				);
				$row['invalid_specialties'] = array_values(
					array_filter(
						$row['specialties'],
						function ( $slug ) use ( $labels ) {
							return ! isset( $labels[ $slug ] );
						}
					)
				);
				$row['problems']            = array_values( self::validate( $row, $row['id'] )['errors'] );
				return $row;
			},
			self::all()
		);
	}
}
