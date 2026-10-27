<?php
/**
 * CRM: importing people from a CSV file.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Crm_Import
 *
 * The CRM screen reads the file in the browser, lets staff match its
 * columns to the fields below, and sends the rows here — first all at once
 * to check them (nothing is saved), then in batches to import. Each row is
 * cleaned with the same rules as adding a contact by hand.
 *
 * Someone already in the CRM (same name AND same phone or email — the
 * matching rule used everywhere in the CRM, so family members sharing a
 * phone stay separate) is either skipped or, if staff choose, has their
 * source, stage, owner, tags and follow-up updated. Their name, phone and
 * email are never changed by an import.
 */
class Crm_Import {

	/** Most rows in one file. */
	const MAX_ROWS = 5000;

	/** Most rows per import request. */
	const BATCH = 100;

	/** Problems listed back (the counts always cover every row). */
	const MAX_PROBLEMS = 200;

	/**
	 * Fields a column can be matched to.
	 *
	 * @return array key => label
	 */
	public static function fields() {
		return array(
			'name'      => __( 'Name', 'doctor-ak-portal' ),
			'phone'     => __( 'Phone', 'doctor-ak-portal' ),
			'email'     => __( 'Email', 'doctor-ak-portal' ),
			'source'    => __( 'Source', 'doctor-ak-portal' ),
			'stage'     => __( 'Stage', 'doctor-ak-portal' ),
			'owner'     => __( 'Owner', 'doctor-ak-portal' ),
			'tags'      => __( 'Tags', 'doctor-ak-portal' ),
			'follow_up' => __( 'Next follow-up', 'doctor-ak-portal' ),
			'note'      => __( 'Note', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Slug from a slug or label, case-insensitive ("Phone call", "phone").
	 *
	 * @param string $value Value from the file.
	 * @param array  $map   slug => label.
	 * @return string Slug, or '' if not recognised.
	 */
	private static function lookup( $value, array $map ) {
		$value = strtolower( trim( (string) $value ) );

		foreach ( $map as $slug => $label ) {
			if ( strtolower( $slug ) === $value || strtolower( $label ) === $value ) {
				return (string) $slug;
			}
		}

		return '';
	}

	/**
	 * Staff member by display name or email.
	 *
	 * @return int User ID, or 0.
	 */
	private static function owner_id( $value ) {
		static $by = null;

		if ( null === $by ) {
			$by = array();

			foreach ( Crm::staff() as $id => $name ) {
				$user                        = get_userdata( $id );
				$by[ strtolower( $name ) ]   = $id;

				if ( $user && ! empty( $user->user_email ) ) {
					$by[ strtolower( $user->user_email ) ] = $id;
				}
			}
		}

		$value = strtolower( trim( (string) $value ) );

		return isset( $by[ $value ] ) ? (int) $by[ $value ] : 0;
	}

	/**
	 * 'Y-m-d' from 2026-11-05, 05/11/2026, 5-11-2026, 5.11.2026 (day first,
	 * as in Pakistan) or "5 Nov 2026".
	 *
	 * @return string '' if not a date.
	 */
	public static function parse_date( $value ) {
		$value = trim( (string) $value );

		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m ) ) {
			list( , $y, $mo, $d ) = $m;
		} elseif ( preg_match( '#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $value, $m ) ) {
			list( , $d, $mo, $y ) = $m;
		} elseif ( preg_match( '/^\d{1,2}\s+[A-Za-z]{3,}\s+\d{4}$/', $value ) && false !== strtotime( $value ) ) {
			return gmdate( 'Y-m-d', strtotime( $value ) );
		} else {
			return '';
		}

		return checkdate( (int) $mo, (int) $d, (int) $y ) ? sprintf( '%04d-%02d-%02d', $y, $mo, $d ) : '';
	}

	/**
	 * Cleans one row.
	 *
	 * @param array $raw  field => text from the file.
	 * @param array $opts default_source, tag.
	 * @return array { ok: bool, data: array, errors: string[], warnings: string[] }
	 */
	public static function parse_row( array $raw, array $opts ) {
		$get      = function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
		};
		$errors   = array();
		$warnings = array();
		$identity = Crm::clean_identity(
			array(
				'name'  => $get( 'name' ),
				'phone' => $get( 'phone' ),
				'email' => $get( 'email' ),
			)
		);

		if ( is_wp_error( $identity ) ) {
			$messages = array(
				'doctor_ak_crm_name'    => __( 'No name.', 'doctor-ak-portal' ),
				'doctor_ak_crm_contact' => __( 'No phone number or email address.', 'doctor-ak-portal' ),
				/* translators: %s: email address. */
				'doctor_ak_crm_email'   => sprintf( __( '“%s” isn’t a valid email address.', 'doctor-ak-portal' ), $get( 'email' ) ),
			);
			$code = $identity->get_error_code();

			// An unusable email is the real problem, even with no phone either.
			if ( 'doctor_ak_crm_contact' === $code && '' !== $get( 'email' ) ) {
				$code = 'doctor_ak_crm_email';
			}

			$errors[] = isset( $messages[ $code ] ) ? $messages[ $code ] : $identity->get_error_message();
			$identity = array(
				'name'  => $get( 'name' ),
				'phone' => $get( 'phone' ),
				'email' => $get( 'email' ),
			);
		} elseif ( '' !== $identity['phone'] && '' === Crm::norm_phone( $identity['phone'] ) ) {
			/* translators: %s: phone number. */
			$warnings[] = sprintf( __( 'Phone “%s” looks too short — kept as written, but it won’t be used to recognise them.', 'doctor-ak-portal' ), $identity['phone'] );
		}

		$default = isset( $opts['default_source'] ) && isset( Crm::sources()[ $opts['default_source'] ] ) ? $opts['default_source'] : 'other';
		$source  = '' !== $get( 'source' ) ? self::lookup( $get( 'source' ), Crm::sources() ) : $default;

		if ( '' === $source ) {
			/* translators: 1: value from the file, 2: source used instead. */
			$warnings[] = sprintf( __( 'Source “%1$s” isn’t one of the CRM’s sources — set to %2$s.', 'doctor-ak-portal' ), $get( 'source' ), Crm::sources()[ $default ] );
			$source     = $default;
		}

		$stage = '' !== $get( 'stage' ) ? self::lookup( $get( 'stage' ), Crm::stages() ) : '';

		if ( '' !== $get( 'stage' ) && '' === $stage ) {
			/* translators: %s: value from the file. */
			$warnings[] = sprintf( __( 'Stage “%s” isn’t one of the CRM’s stages — left as New lead.', 'doctor-ak-portal' ), $get( 'stage' ) );
		}

		$owner = '' !== $get( 'owner' ) ? self::owner_id( $get( 'owner' ) ) : 0;

		if ( '' !== $get( 'owner' ) && ! $owner ) {
			/* translators: %s: value from the file. */
			$warnings[] = sprintf( __( 'Owner “%s” isn’t a member of staff — left unassigned.', 'doctor-ak-portal' ), $get( 'owner' ) );
		}

		$follow = '' !== $get( 'follow_up' ) ? self::parse_date( $get( 'follow_up' ) ) : '';

		if ( '' !== $get( 'follow_up' ) && '' === $follow ) {
			/* translators: %s: value from the file. */
			$warnings[] = sprintf( __( 'Follow-up date “%s” wasn’t understood (use 2026-11-05 or 05/11/2026) — left empty.', 'doctor-ak-portal' ), $get( 'follow_up' ) );
		}

		$tags = preg_split( '/[;,]/', $get( 'tags' ) );

		if ( ! empty( $opts['tag'] ) ) {
			$tags[] = (string) $opts['tag'];
		}

		return array(
			'ok'       => ! $errors,
			'data'     => array(
				'name'      => $identity['name'],
				'phone'     => $identity['phone'],
				'email'     => $identity['email'],
				'source'    => $source,
				// Updating someone: only a source actually in the file replaces theirs.
				'source_in_file' => '' !== $get( 'source' ) && '' !== self::lookup( $get( 'source' ), Crm::sources() ),
				'stage'     => $stage,
				'owner_id'  => $owner,
				'tags'      => Crm::clean_tags( $tags ),
				'follow_up' => $follow,
				'note'      => function_exists( 'mb_substr' ) ? mb_substr( sanitize_textarea_field( $get( 'note' ) ), 0, 4000 ) : substr( sanitize_textarea_field( $get( 'note' ) ), 0, 4000 ),
			),
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Checks or imports rows.
	 *
	 * @param array[] $rows    Each: field => text, plus '_row' (line number in the file).
	 * @param array   $opts    existing ('skip'|'update'), default_source, tag, file.
	 * @param int     $user_id Acting user.
	 * @param bool    $dry     Only check; save nothing.
	 * @return array { added, updated, skipped, failed, warnings, problems: [ { row, name, level, message } ], more }
	 */
	public static function run( array $rows, array $opts, $user_id, $dry ) {
		$update  = isset( $opts['existing'] ) && 'update' === $opts['existing'];
		$file    = isset( $opts['file'] ) ? sanitize_file_name( (string) $opts['file'] ) : '';
		$out     = array(
			'added'    => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'warnings' => 0,
			'problems' => array(),
			'more'     => 0,
		);
		$seen    = array(); // Rows earlier in this file, for the check.
		$problem = function ( $row, $name, $level, $message ) use ( &$out ) {
			if ( count( $out['problems'] ) < self::MAX_PROBLEMS ) {
				$out['problems'][] = array(
					'row'     => (int) $row,
					'name'    => (string) $name,
					'level'   => $level,
					'message' => $message,
				);
			} else {
				++$out['more'];
			}
		};

		foreach ( $rows as $i => $raw ) {
			$raw    = is_array( $raw ) ? $raw : array();
			$line   = isset( $raw['_row'] ) ? (int) $raw['_row'] : $i + 2;
			$parsed = self::parse_row( $raw, $opts );
			$d      = $parsed['data'];

			if ( ! $parsed['ok'] ) {
				++$out['failed'];
				$problem( $line, $d['name'], 'error', implode( ' ', $parsed['errors'] ) );
				continue;
			}

			if ( $parsed['warnings'] ) {
				++$out['warnings'];
				$problem( $line, $d['name'], 'warning', implode( ' ', $parsed['warnings'] ) );
			}

			$keys = array();

			foreach ( array( Crm::norm_phone( $d['phone'] ), Crm::norm_email( $d['email'] ) ) as $k ) {
				if ( '' !== $k ) {
					$keys[] = Crm::norm_name( $d['name'] ) . '|' . $k;
				}
			}

			$match = Crm::find_match( $d['name'], $d['phone'], $d['email'] );

			if ( ! $match && $dry && array_intersect_key( $seen, array_flip( $keys ) ) ) {
				$match = -1; // Same person earlier in this file.
			}

			$seen = array_merge( $seen, array_fill_keys( $keys, true ) );

			if ( $match && ! $update ) {
				++$out['skipped'];
				continue;
			}

			if ( $match ) {
				++$out['updated'];

				if ( ! $dry && $match > 0 ) {
					self::update_existing( $match, $d, $user_id );
				}

				continue;
			}

			++$out['added'];

			if ( ! $dry ) {
				self::add_new( $d, $user_id, $file );
			}
		}

		return $out;
	}

	/**
	 * A new contact from a cleaned row.
	 */
	private static function add_new( array $d, $user_id, $file ) {
		$id = Crm::insert(
			array(
				'name'       => $d['name'],
				'phone'      => $d['phone'],
				'email'      => $d['email'],
				'source'     => $d['source'],
				'stage'      => '' !== $d['stage'] ? $d['stage'] : Crm::STAGE_NEW,
				'owner_id'   => $d['owner_id'],
				'created_by' => (int) $user_id,
			)
		);

		if ( ! $id ) {
			return 0;
		}

		$extra = array();

		if ( $d['tags'] ) {
			$extra['tags'] = '|' . implode( '|', $d['tags'] ) . '|';
		}

		if ( '' !== $d['follow_up'] ) {
			$extra['follow_up_date'] = $d['follow_up'];
		}

		if ( $extra ) {
			Crm::update_row( $id, $extra );
		}

		/* translators: %s: file name. */
		Crm::log( $id, $user_id, 'import', '' !== $file ? sprintf( __( 'Imported from %s.', 'doctor-ak-portal' ), $file ) : __( 'Imported from a CSV file.', 'doctor-ak-portal' ) );

		if ( '' !== $d['note'] ) {
			Crm::add_note( $id, $d['note'], $user_id );
		}

		return $id;
	}

	/**
	 * Updates someone already in the CRM: source, stage, owner, follow-up
	 * when the file has them; tags are added (never removed).
	 */
	private static function update_existing( $id, array $d, $user_id ) {
		$contact = Crm::find( $id );

		if ( ! $contact ) {
			return;
		}

		$fields = array();

		if ( $d['source_in_file'] ) {
			$fields['source'] = $d['source'];
		}

		if ( '' !== $d['stage'] ) {
			$fields['stage'] = $d['stage'];
		}

		if ( $d['owner_id'] ) {
			$fields['owner_id'] = $d['owner_id'];
		}

		if ( $d['tags'] ) {
			$fields['tags'] = array_merge( $contact['tags'], $d['tags'] );
		}

		if ( '' !== $d['follow_up'] ) {
			$fields['follow_up_date'] = $d['follow_up'];
		}

		if ( $fields ) {
			Crm::update_fields( $id, $fields, $user_id );
		}

		if ( '' !== $d['note'] ) {
			Crm::add_note( $id, $d['note'], $user_id );
		}
	}
}
