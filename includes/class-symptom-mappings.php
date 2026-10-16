<?php
/**
 * Symptom → specialty mappings for "Find a doctor by symptoms".
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
 * Each mapping links one symptom (plus the other words patients use for it)
 * to the specialties that commonly see it. They only help patients find
 * doctors — nothing here diagnoses, assesses urgency or advises treatment.
 *
 * Lifecycle: Draft → Reviewed → Active.
 * - Anyone who can manage mappings (administrators, clinical reviewers) can
 *   add or edit them; saving a change always returns it to Draft.
 * - A clinical reviewer (capability REVIEW_CAP, granted per user — it does
 *   not require administrator access) marks a Draft as Reviewed, but never
 *   one they edited last, then activates it. Only Active mappings are used
 *   on the public page.
 *
 * The page is only shown to patients when it's switched on, at least one
 * mapping is Active and the general emergency notice has been approved by a
 * reviewer (is_public()). Example and imported mappings always start as
 * Drafts and are never reviewed or activated automatically.
 *
 * The previous Symptom Checker's clinical ruleset (Symptom_Rules options) is
 * left untouched; its symptoms can be copied in as drafts (import_legacy()).
 */
class Symptom_Mappings {

	/** Capability that lets a user review/activate mappings and approve the notice. */
	const REVIEW_CAP = 'doctor_ak_review_symptom_mappings';

	const OPTION_MAPPINGS = 'doctor_ak_symptom_mappings';
	const OPTION_NEXT_ID  = 'doctor_ak_symptom_mappings_next_id';
	const OPTION_NOTICE   = 'doctor_ak_symptom_finder_notice';
	const OPTION_ENABLED  = 'doctor_ak_symptom_finder_enabled';

	const STATUS_DRAFT    = 'draft';
	const STATUS_REVIEWED = 'reviewed';
	const STATUS_ACTIVE   = 'active';

	const MAX_MAPPINGS = 1000;
	const MAX_SYNONYMS = 40;
	const MAX_TEXT     = 80;

	/* ---------------------------------------------------------------------
	 * Permissions
	 * ------------------------------------------------------------------- */

	/**
	 * Whether a user may add/edit mappings and the notice.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function can_manage( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();

		return $user_id > 0 && ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, self::REVIEW_CAP ) );
	}

	/**
	 * Whether a user may review/activate mappings and approve the notice.
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
		$users = get_users(
			array(
				'role__in' => array( 'administrator', Roles::DOCTOR_ROLE ),
				'orderby'  => 'display_name',
				'number'   => 500,
			)
		);

		return array_values(
			array_filter(
				$users,
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

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------- */

	/**
	 * Every mapping, in name order.
	 *
	 * @return array[]
	 */
	public static function all() {
		$rows = get_option( self::OPTION_MAPPINGS, array() );
		$rows = is_array( $rows ) ? array_values( array_filter( array_map( array( __CLASS__, 'normalize_row' ), $rows ) ) ) : array();

		usort(
			$rows,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $rows;
	}

	/**
	 * Active mappings only — what the public page uses.
	 *
	 * @return array[]
	 */
	public static function active() {
		return array_values(
			array_filter(
				self::all(),
				function ( $row ) {
					return self::STATUS_ACTIVE === $row['status'];
				}
			)
		);
	}

	/**
	 * One mapping by ID.
	 *
	 * @param int $id Mapping ID.
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
	 * Fills defaults on a stored row; drops malformed ones.
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
				'synonyms'     => array(),
				'specialties'  => array(),
				'status'       => self::STATUS_DRAFT,
				'source'       => 'manual',
				'notes'        => '',
				'updated_at'   => '',
				'updated_by'   => 0,
				'reviewed_at'  => '',
				'reviewed_by'  => 0,
				'activated_at' => '',
				'activated_by' => 0,
			)
		);

		$row['id']          = (int) $row['id'];
		$row['synonyms']    = array_values( array_map( 'strval', (array) $row['synonyms'] ) );
		$row['specialties'] = array_values( array_map( 'strval', (array) $row['specialties'] ) );

		if ( ! in_array( $row['status'], array( self::STATUS_DRAFT, self::STATUS_REVIEWED, self::STATUS_ACTIVE ), true ) ) {
			$row['status'] = self::STATUS_DRAFT;
		}

		return $row;
	}

	/**
	 * Writes the whole list.
	 *
	 * @param array[] $rows Rows.
	 * @return void
	 */
	private static function store( array $rows ) {
		update_option( self::OPTION_MAPPINGS, array_values( $rows ), false );
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
	 * Cleans and checks a mapping's fields.
	 *
	 * @param array $input     { name, synonyms (array|string), specialties (array), notes }.
	 * @param int   $ignore_id ID being edited (excluded from duplicate checks).
	 * @return array { fields: array, errors: array field => message }
	 */
	public static function validate( array $input, $ignore_id = 0 ) {
		$errors = array();
		$name   = trim( sanitize_text_field( isset( $input['name'] ) ? (string) $input['name'] : '' ) );

		$synonyms = isset( $input['synonyms'] ) ? $input['synonyms'] : array();

		if ( is_string( $synonyms ) ) {
			$synonyms = preg_split( '/[\r\n,;]+/', $synonyms );
		}

		$clean_synonyms = array();
		$seen           = array( self::normalize_phrase( $name ) => true );

		foreach ( (array) $synonyms as $synonym ) {
			$synonym = trim( sanitize_text_field( (string) $synonym ) );
			$key     = self::normalize_phrase( $synonym );

			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}

			if ( strlen( $synonym ) > self::MAX_TEXT ) {
				/* translators: %d: maximum length. */
				$errors['synonyms'] = sprintf( __( 'Each alternative phrase must be %d characters or fewer.', 'doctor-ak-portal' ), self::MAX_TEXT );
			}

			$seen[ $key ]     = true;
			$clean_synonyms[] = $synonym;
		}

		if ( count( $clean_synonyms ) > self::MAX_SYNONYMS ) {
			/* translators: %d: maximum number. */
			$errors['synonyms'] = sprintf( __( 'Use at most %d alternative phrases.', 'doctor-ak-portal' ), self::MAX_SYNONYMS );
		}

		if ( '' === $name ) {
			$errors['name'] = __( 'Enter the symptom name.', 'doctor-ak-portal' );
		} elseif ( strlen( $name ) > self::MAX_TEXT ) {
			/* translators: %d: maximum length. */
			$errors['name'] = sprintf( __( 'Keep the symptom name to %d characters or fewer.', 'doctor-ak-portal' ), self::MAX_TEXT );
		}

		$specialties = array();
		$invalid     = array();

		foreach ( (array) ( isset( $input['specialties'] ) ? $input['specialties'] : array() ) as $value ) {
			$slug = self::resolve_specialty( $value );

			if ( '' === $slug ) {
				if ( '' !== trim( (string) $value ) ) {
					$invalid[] = sanitize_text_field( (string) $value );
				}
			} elseif ( ! in_array( $slug, $specialties, true ) ) {
				$specialties[] = $slug;
			}
		}

		if ( ! empty( $invalid ) ) {
			/* translators: %s: specialty names. */
			$errors['specialties'] = sprintf( __( 'Not a specialty on this site: %s.', 'doctor-ak-portal' ), implode( ', ', $invalid ) );
		} elseif ( empty( $specialties ) ) {
			$errors['specialties'] = __( 'Choose at least one related specialty.', 'doctor-ak-portal' );
		}

		// A name or phrase may belong to one mapping only.
		$duplicate = false;

		if ( '' !== $name ) {
			$phrases = array_merge( array( $name ), $clean_synonyms );

			foreach ( self::all() as $other ) {
				if ( (int) $other['id'] === (int) $ignore_id ) {
					continue;
				}

				$taken = array();

				foreach ( array_merge( array( $other['name'] ), $other['synonyms'] ) as $phrase ) {
					$taken[ self::normalize_phrase( $phrase ) ] = true;
				}

				foreach ( $phrases as $i => $phrase ) {
					if ( isset( $taken[ self::normalize_phrase( $phrase ) ] ) ) {
						$field = 0 === $i ? 'name' : 'synonyms';
						/* translators: 1: phrase, 2: other symptom's name. */
						$errors[ $field ] = sprintf( __( '“%1$s” is already used by the symptom “%2$s”.', 'doctor-ak-portal' ), $phrase, $other['name'] );
						$duplicate        = true;
						break 2;
					}
				}
			}
		}

		return array(
			'fields' => array(
				'name'        => $name,
				'synonyms'    => $clean_synonyms,
				'specialties' => $specialties,
				'notes'       => isset( $input['notes'] ) ? sanitize_textarea_field( (string) $input['notes'] ) : '',
			),
			'errors'    => $errors,
			'duplicate' => $duplicate,
		);
	}

	/* ---------------------------------------------------------------------
	 * Changes
	 * ------------------------------------------------------------------- */

	/**
	 * Adds or updates a mapping. Any saved change returns it to Draft, so
	 * it needs reviewing again before it's used.
	 *
	 * @param array $input  Fields (see validate()).
	 * @param int   $id     Mapping ID to update, or 0 to add.
	 * @param int   $user_id Who is saving.
	 * @param string $source 'manual', 'import', 'example' or 'legacy' (new rows only).
	 * @return array|\WP_Error Saved row, or WP_Error with field errors as data.
	 */
	public static function save( array $input, $id, $user_id, $source = 'manual' ) {
		$check = self::validate( $input, $id );

		if ( ! empty( $check['errors'] ) ) {
			return new \WP_Error( 'doctor_ak_sm_invalid', __( 'Please fix the highlighted fields.', 'doctor-ak-portal' ), $check['errors'] );
		}

		$rows = self::all();
		$now  = current_time( 'mysql' );

		if ( $id ) {
			foreach ( $rows as &$row ) {
				if ( (int) $row['id'] !== (int) $id ) {
					continue;
				}

				$changed = $row['name'] !== $check['fields']['name']
					|| $row['synonyms'] !== $check['fields']['synonyms']
					|| $row['specialties'] !== $check['fields']['specialties'];

				$row = array_merge( $row, $check['fields'] );

				if ( $changed ) {
					$row = array_merge(
						$row,
						array(
							'status'       => self::STATUS_DRAFT,
							'updated_at'   => $now,
							'updated_by'   => (int) $user_id,
							'reviewed_at'  => '',
							'reviewed_by'  => 0,
							'activated_at' => '',
							'activated_by' => 0,
						)
					);
				}

				$saved = $row;
			}
			unset( $row );

			if ( empty( $saved ) ) {
				return new \WP_Error( 'doctor_ak_sm_missing', __( 'That symptom mapping no longer exists.', 'doctor-ak-portal' ) );
			}
		} else {
			if ( count( $rows ) >= self::MAX_MAPPINGS ) {
				return new \WP_Error( 'doctor_ak_sm_full', __( 'The maximum number of symptom mappings has been reached.', 'doctor-ak-portal' ) );
			}

			$saved  = self::normalize_row(
				array_merge(
					$check['fields'],
					array(
						'id'         => self::next_id(),
						'status'     => self::STATUS_DRAFT,
						'source'     => in_array( $source, array( 'manual', 'import', 'example', 'legacy' ), true ) ? $source : 'manual',
						'updated_at' => $now,
						'updated_by' => (int) $user_id,
					)
				)
			);
			$rows[] = $saved;
		}

		self::store( $rows );

		return $saved;
	}

	/**
	 * Deletes a mapping.
	 *
	 * @param int $id Mapping ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		$rows   = self::all();
		$before = count( $rows );
		$rows   = array_filter(
			$rows,
			function ( $row ) use ( $id ) {
				return (int) $row['id'] !== (int) $id;
			}
		);

		self::store( $rows );

		return count( $rows ) < $before;
	}

	/**
	 * Moves a mapping to a new status, enforcing who may do it.
	 *
	 * @param int    $id      Mapping ID.
	 * @param string $action  'review', 'activate' or 'deactivate'.
	 * @param int    $user_id Acting user.
	 * @return array|\WP_Error Updated row.
	 */
	public static function transition( $id, $action, $user_id ) {
		if ( ! self::can_review( $user_id ) ) {
			return new \WP_Error( 'doctor_ak_sm_forbidden', __( 'Only a clinical reviewer can do that.', 'doctor-ak-portal' ) );
		}

		$rows = self::all();
		$now  = current_time( 'mysql' );

		foreach ( $rows as &$row ) {
			if ( (int) $row['id'] !== (int) $id ) {
				continue;
			}

			if ( 'review' === $action ) {
				if ( self::STATUS_DRAFT !== $row['status'] ) {
					return new \WP_Error( 'doctor_ak_sm_state', __( 'Only drafts can be marked as reviewed.', 'doctor-ak-portal' ) );
				}

				if ( (int) $row['updated_by'] === (int) $user_id ) {
					return new \WP_Error( 'doctor_ak_sm_self', __( 'You made the latest change to this mapping, so another reviewer needs to review it.', 'doctor-ak-portal' ) );
				}

				$check = self::validate( $row, $row['id'] );

				if ( ! empty( $check['errors'] ) ) {
					return new \WP_Error( 'doctor_ak_sm_invalid', implode( ' ', $check['errors'] ) );
				}

				$row['status']      = self::STATUS_REVIEWED;
				$row['reviewed_at'] = $now;
				$row['reviewed_by'] = (int) $user_id;
			} elseif ( 'activate' === $action ) {
				if ( self::STATUS_REVIEWED !== $row['status'] ) {
					return new \WP_Error( 'doctor_ak_sm_state', __( 'A mapping must be reviewed before it can be activated.', 'doctor-ak-portal' ) );
				}

				$row['status']       = self::STATUS_ACTIVE;
				$row['activated_at'] = $now;
				$row['activated_by'] = (int) $user_id;
			} elseif ( 'deactivate' === $action ) {
				if ( self::STATUS_ACTIVE !== $row['status'] ) {
					return new \WP_Error( 'doctor_ak_sm_state', __( 'This mapping is not active.', 'doctor-ak-portal' ) );
				}

				$row['status'] = self::STATUS_REVIEWED;
			} else {
				return new \WP_Error( 'doctor_ak_sm_action', __( 'Unknown action.', 'doctor-ak-portal' ) );
			}

			$updated = $row;
		}
		unset( $row );

		if ( empty( $updated ) ) {
			return new \WP_Error( 'doctor_ak_sm_missing', __( 'That symptom mapping no longer exists.', 'doctor-ak-portal' ) );
		}

		self::store( $rows );

		return $updated;
	}

	/* ---------------------------------------------------------------------
	 * Import (pasted list), examples and the legacy ruleset
	 * ------------------------------------------------------------------- */

	/**
	 * Parses pasted mappings — a JSON array of { name, synonyms, specialties }
	 * or one per line: "Symptom | phrase, phrase | Specialty, Specialty".
	 *
	 * @param string $text Pasted text.
	 * @return array[] Raw rows { name, synonyms[], specialties[], line }.
	 */
	public static function parse_import( $text ) {
		$text = trim( (string) $text );
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
					'synonyms'    => isset( $item['synonyms'] ) ? (array) $item['synonyms'] : array(),
					'specialties' => isset( $item['specialties'] ) ? (array) $item['specialties'] : array(),
					'line'        => $i + 1,
				);
			}

			return $rows;
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
			$key      = self::normalize_phrase( $check['fields']['name'] );

			if ( isset( $in_file[ $key ] ) && '' !== $key ) {
				$result     = 'duplicate';
				$messages[] = __( 'Appears more than once in this import.', 'doctor-ak-portal' );
			} elseif ( $check['duplicate'] ) {
				$result = 'duplicate';
			} elseif ( ! empty( $check['errors'] ) ) {
				$result = 'invalid';
			}

			$in_file[ $key ] = true;

			$out[] = array(
				'line'        => (int) $row['line'],
				'name'        => $check['fields']['name'] ? $check['fields']['name'] : (string) $row['name'],
				'synonyms'    => $check['fields']['synonyms'],
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
	 * Imports the rows that are ready, as Drafts.
	 *
	 * @param array[] $rows    Rows from parse_import().
	 * @param int     $user_id Importing user.
	 * @param string  $source  'import', 'example' or 'legacy'.
	 * @return array { added: int, skipped: int }
	 */
	public static function import( array $rows, $user_id, $source = 'import' ) {
		$added   = 0;
		$skipped = 0;

		foreach ( $rows as $row ) {
			$saved = self::save( $row, 0, $user_id, $source );

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
	 * The bundled example mappings (illustrative drafts, not clinically
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
	 * The general emergency notice shown on the page (independent of any
	 * matching).
	 *
	 * @return array { text, status 'draft'|'approved', updated_at, updated_by, approved_at, approved_by }
	 */
	public static function notice() {
		$notice = get_option( self::OPTION_NOTICE, array() );

		return wp_parse_args(
			is_array( $notice ) ? $notice : array(),
			array(
				// A suggested starting point only — it stays a draft until a
				// clinical reviewer approves it.
				'text'        => __( 'If you think you may need urgent medical help, go to the nearest emergency department or contact your local emergency services now. This page helps you find doctors; it cannot tell whether your symptoms are urgent.', 'doctor-ak-portal' ),
				'status'      => 'draft',
				'updated_at'  => '',
				'updated_by'  => 0,
				'approved_at' => '',
				'approved_by' => 0,
			)
		);
	}

	/**
	 * Saves the notice text (back to draft when it changes).
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
					'text'        => $text,
					'status'      => 'draft',
					'updated_at'  => current_time( 'mysql' ),
					'updated_by'  => (int) $user_id,
					'approved_at' => '',
					'approved_by' => 0,
				)
			);
			update_option( self::OPTION_NOTICE, $notice, false );
		}

		return $notice;
	}

	/**
	 * Approves the notice (clinical reviewers, not the person who last
	 * edited it).
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

		if ( (int) $notice['updated_by'] === (int) $user_id ) {
			return new \WP_Error( 'doctor_ak_sm_self', __( 'You made the latest change to the notice, so another reviewer needs to approve it.', 'doctor-ak-portal' ) );
		}

		$notice['status']      = 'approved';
		$notice['approved_at'] = current_time( 'mysql' );
		$notice['approved_by'] = (int) $user_id;
		update_option( self::OPTION_NOTICE, $notice, false );

		return $notice;
	}

	/**
	 * What's still needed before patients can see the page.
	 *
	 * @return string[] Human-readable missing requirements (empty when ready).
	 */
	public static function missing_requirements() {
		$missing = array();

		if ( empty( self::active() ) ) {
			$missing[] = __( 'At least one symptom mapping must be reviewed and activated.', 'doctor-ak-portal' );
		}

		if ( 'approved' !== self::notice()['status'] ) {
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
		$name   = function ( $user_id ) {
			$user = $user_id ? get_userdata( $user_id ) : false;
			return $user ? $user->display_name : '';
		};

		return array_map(
			function ( $row ) use ( $labels, $name ) {
				$row['specialty_labels'] = array_map(
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
				$row['updated_by_name']  = $name( (int) $row['updated_by'] );
				$row['reviewed_by_name'] = $name( (int) $row['reviewed_by'] );
				return $row;
			},
			self::all()
		);
	}
}
