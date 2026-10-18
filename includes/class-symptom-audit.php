<?php
/**
 * Audit trail for "Find a doctor by symptoms" clinical content.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Symptom_Audit
 *
 * One row per change to a symptom, safety rule or the emergency notice:
 * who did what, when, and — for activations — the exact content that went
 * live, so every published version can be reconstructed later.
 *
 * Only staff content is recorded. Nothing a patient types or answers is
 * ever stored here (the patient page sends nothing to the server).
 */
class Symptom_Audit {

	/** Table name, without the WordPress prefix. */
	const TABLE = 'dak_symptom_audit';

	const TYPE_SYMPTOM = 'symptom';
	const TYPE_RULE    = 'rule';
	const TYPE_NOTICE  = 'notice';
	const TYPE_PAGE    = 'page';

	/** Longest summary kept. */
	const MAX_SUMMARY = 500;

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Records a change. Never throws: a missing table (before the upgrade
	 * has run) must not stop the change itself.
	 *
	 * @param string     $type     One of the TYPE_* constants.
	 * @param int        $id       Item ID (0 for the notice / page).
	 * @param string     $action   e.g. 'create', 'edit', 'submit', 'approve', 'return', 'activate'.
	 * @param int        $user_id  Acting user.
	 * @param string     $summary  Short human-readable detail (e.g. a return reason).
	 * @param array|null $snapshot Content at this moment (stored for activations).
	 * @return void
	 */
	public static function log( $type, $id, $action, $user_id, $summary = '', $snapshot = null ) {
		global $wpdb;

		$summary = (string) $summary;

		if ( function_exists( 'mb_substr' ) ) {
			$summary = mb_substr( $summary, 0, self::MAX_SUMMARY );
		} else {
			$summary = substr( $summary, 0, self::MAX_SUMMARY );
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom audit table.
			self::table_name(),
			array(
				'object_type' => sanitize_key( $type ),
				'object_id'   => (int) $id,
				'action'      => sanitize_key( $action ),
				'user_id'     => (int) $user_id,
				'summary'     => $summary,
				'snapshot'    => null === $snapshot ? null : wp_json_encode( $snapshot ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * History of one item, newest first, with user names resolved.
	 *
	 * @param string $type  Object type.
	 * @param int    $id    Item ID.
	 * @param int    $limit Rows.
	 * @return array[] Each { id, action, user_id, user_name, summary, created_at, version, has_snapshot }.
	 */
	public static function for_object( $type, $id, $limit = 100 ) {
		global $wpdb;

		$table = self::table_name();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom audit table, read on demand.
			$wpdb->prepare(
				"SELECT id, action, user_id, summary, created_at, snapshot IS NOT NULL AS has_snapshot FROM {$table} WHERE object_type = %s AND object_id = %d ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				sanitize_key( $type ),
				(int) $id,
				max( 1, min( 500, (int) $limit ) )
			),
			ARRAY_A
		);

		$names = array();

		return array_map(
			function ( $row ) use ( &$names ) {
				$user_id = (int) $row['user_id'];

				if ( ! isset( $names[ $user_id ] ) ) {
					$user               = $user_id ? get_userdata( $user_id ) : false;
					$names[ $user_id ] = $user ? $user->display_name : '';
				}

				return array(
					'id'           => (int) $row['id'],
					'action'       => (string) $row['action'],
					'user_id'      => $user_id,
					'user_name'    => $names[ $user_id ],
					'summary'      => (string) $row['summary'],
					'created_at'   => (string) $row['created_at'],
					'has_snapshot' => (bool) $row['has_snapshot'],
				);
			},
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * The content stored with one audit row (an activation), or null.
	 *
	 * @param int $entry_id Audit row ID.
	 * @return array|null { object_type, object_id, created_at, snapshot }
	 */
	public static function snapshot( $entry_id ) {
		global $wpdb;

		$table = self::table_name();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom audit table, read on demand.
			$wpdb->prepare( "SELECT object_type, object_id, created_at, snapshot FROM {$table} WHERE id = %d", (int) $entry_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			ARRAY_A
		);

		if ( ! $row || null === $row['snapshot'] ) {
			return null;
		}

		$data = json_decode( (string) $row['snapshot'], true );

		return array(
			'object_type' => (string) $row['object_type'],
			'object_id'   => (int) $row['object_id'],
			'created_at'  => (string) $row['created_at'],
			'snapshot'    => is_array( $data ) ? $data : array(),
		);
	}
}
