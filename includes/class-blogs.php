<?php
/**
 * Blog posts — admin-authored articles shown on the public [blogs_directory]/
 * [blog_single] pages.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blogs
 *
 * A simple admin-authored content type — title, rich-text body, one
 * featured image, Draft/Published status — storage mirrors Services/Clinics:
 * a plain DB table, no custom post type. Only Published posts (with a
 * `published_at` in the past) are ever visible on the public site; Draft
 * posts only ever show in the admin dashboard's own list.
 */
class Blogs {

	/**
	 * Base table name (without the WordPress table prefix).
	 *
	 * @var string
	 */
	const TABLE = 'dak_blogs';

	const STATUS_DRAFT     = 'draft';
	const STATUS_PUBLISHED = 'published';

	/**
	 * How many words a public listing's auto-generated excerpt is trimmed
	 * to — mirrors service-card.php's own excerpt length.
	 *
	 * @var int
	 */
	const EXCERPT_WORD_COUNT = 30;

	/**
	 * Returns the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Every recognised status => its human label, for the admin form's
	 * status picker.
	 *
	 * @return array
	 */
	public static function status_options() {
		return array(
			self::STATUS_DRAFT     => __( 'Draft', 'doctor-ak-portal' ),
			self::STATUS_PUBLISHED => __( 'Published', 'doctor-ak-portal' ),
		);
	}

	/**
	 * Validates and sanitizes a blog post's fields from a request.
	 *
	 * @param array $posted Raw request array (e.g. $_POST, already a plain array).
	 * @return array|\WP_Error Sanitized fields, or WP_Error on invalid input.
	 */
	public static function sanitize_fields_from_request( array $posted ) {
		$title = isset( $posted['title'] ) ? sanitize_text_field( wp_unslash( $posted['title'] ) ) : '';

		if ( '' === $title ) {
			return new \WP_Error( 'doctor_ak_blog_title_required', __( 'Please provide a title for this post.', 'doctor-ak-portal' ) );
		}

		// wp_kses_post() (not sanitize_textarea_field()) since this is a
		// rich-text editor field (see Rich_Text::toolbar_html()) — keeps
		// safe formatting tags and strips anything else.
		$content = isset( $posted['content'] ) ? wp_kses_post( wp_unslash( $posted['content'] ) ) : '';

		$status = isset( $posted['status'] ) ? sanitize_key( wp_unslash( $posted['status'] ) ) : self::STATUS_DRAFT;

		if ( ! array_key_exists( $status, self::status_options() ) ) {
			return new \WP_Error( 'doctor_ak_blog_status_invalid', __( 'Please choose a valid status.', 'doctor-ak-portal' ) );
		}

		return array(
			'title'   => $title,
			'content' => $content,
			'status'  => $status,
		);
	}

	/**
	 * Creates a new blog post.
	 *
	 * @param int   $author_id Author's user ID (the admin/receptionist who wrote it).
	 * @param array $fields    Sanitized fields, see sanitize_fields_from_request().
	 * @param int   $image_id  Featured image attachment ID, or 0 for none.
	 * @return int|false New post ID, or false on failure.
	 */
	public static function create( $author_id, array $fields, $image_id = 0 ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'author_id'    => (int) $author_id,
				'title'        => $fields['title'],
				'content'      => $fields['content'],
				'image_id'     => (int) $image_id,
				'status'       => $fields['status'],
				'published_at' => self::STATUS_PUBLISHED === $fields['status'] ? $now : null,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates an existing blog post.
	 *
	 * @param int      $blog_id  Blog post ID.
	 * @param array    $fields   Sanitized fields, see sanitize_fields_from_request().
	 * @param int|null $image_id Featured image attachment ID, or null to leave the existing one untouched.
	 * @return bool
	 */
	public static function update( $blog_id, array $fields, $image_id = null ) {
		global $wpdb;

		$existing = self::find( $blog_id );

		$data  = array(
			'title'      => $fields['title'],
			'content'    => $fields['content'],
			'status'     => $fields['status'],
			'updated_at' => current_time( 'mysql' ),
		);
		$types = array( '%s', '%s', '%s', '%s' );

		// Only stamp published_at the first time a post becomes Published —
		// re-saving an already-published post (or editing a still-draft one)
		// never touches it, so the displayed "published on" date stays the
		// date it actually first went live.
		if ( self::STATUS_PUBLISHED === $fields['status'] && $existing && empty( $existing['published_at'] ) ) {
			$data['published_at'] = current_time( 'mysql' );
			$types[]              = '%s';
		}

		if ( null !== $image_id ) {
			$data['image_id'] = (int) $image_id;
			$types[]           = '%d';
		}

		$updated = $wpdb->update( self::table_name(), $data, array( 'id' => (int) $blog_id ), $types, array( '%d' ) );

		return false !== $updated;
	}

	/**
	 * Deletes a blog post.
	 *
	 * @param int $blog_id Blog post ID.
	 * @return bool
	 */
	public static function delete( $blog_id ) {
		global $wpdb;

		return false !== $wpdb->delete( self::table_name(), array( 'id' => (int) $blog_id ), array( '%d' ) );
	}

	/**
	 * Finds a single blog post by ID, regardless of status — for the admin
	 * edit screen.
	 *
	 * @param int $blog_id Blog post ID.
	 * @return array|null Decoded row, or null if not found.
	 */
	public static function find( $blog_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $blog_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
			ARRAY_A
		);

		return $row ? self::decode_row( $row ) : null;
	}

	/**
	 * A single Published post for the public [blog_single] page — null if it
	 * doesn't exist or isn't Published yet.
	 *
	 * @param int $blog_id Blog post ID.
	 * @return array|null
	 */
	public static function find_for_public_view( $blog_id ) {
		$blog = self::find( $blog_id );

		return ( $blog && self::STATUS_PUBLISHED === $blog['status'] ) ? $blog : null;
	}

	/**
	 * Total number of blog posts, optionally filtered by status — for the
	 * admin dashboard's stat cards.
	 *
	 * @param string|null $status Only this status, or null for every post.
	 * @return int
	 */
	public static function total_count( $status = null ) {
		global $wpdb;

		if ( null === $status ) {
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE status = %s', $status ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.
		);
	}

	/**
	 * Every blog post regardless of status, newest-first, for the admin
	 * "Blogs" list — joined with the author's display name.
	 *
	 * @param int $number Max rows to return. Default 200.
	 * @return array List of decoded rows, each with an added 'author' sub-array (id/name).
	 */
	public static function all_flat_for_admin( $number = 200 ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, u.display_name AS author_display_name
				FROM " . self::table_name() . " b
				LEFT JOIN {$wpdb->users} u ON u.ID = b.author_id
				ORDER BY b.id DESC
				LIMIT %d",
				(int) $number
			), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names, not user input.
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				$blog = self::decode_row( $row );

				$blog['author'] = array(
					'id'   => $blog['author_id'],
					'name' => isset( $row['author_display_name'] ) ? $row['author_display_name'] : '',
				);

				return $blog;
			},
			$rows
		);
	}

	/**
	 * Every Published post whose publish date has already passed, newest-
	 * first, for the public [blogs_directory] grid.
	 *
	 * @param int $limit Max rows to return, or 0 for every post.
	 * @return array List of decoded rows.
	 */
	public static function published_for_public_directory( $limit = 0 ) {
		global $wpdb;

		$sql = "SELECT * FROM " . self::table_name() . " WHERE status = %s AND published_at IS NOT NULL AND published_at <= %s ORDER BY published_at DESC";
		$params = array( self::STATUS_PUBLISHED, current_time( 'mysql' ) );

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d';
			$params[] = $limit;
		}

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, not user input.

		return array_map( array( __CLASS__, 'decode_row' ), $rows );
	}

	/**
	 * Decodes a raw DB row into the shape the rest of the codebase works
	 * with: casts IDs, resolves the featured image URL, the author's
	 * display name, and a plain-text excerpt for card/listing use.
	 *
	 * @param array $row Raw associative row from $wpdb.
	 * @return array
	 */
	private static function decode_row( array $row ) {
		$image_id  = isset( $row['image_id'] ) ? (int) $row['image_id'] : 0;
		$image_url = '';

		if ( $image_id > 0 ) {
			$found     = wp_get_attachment_image_url( $image_id, 'large' );
			$image_url = $found ? $found : '';
		}

		$author       = get_userdata( (int) $row['author_id'] );
		$author_name  = '';

		if ( $author ) {
			$full_name   = trim( $author->first_name . ' ' . $author->last_name );
			$author_name = '' !== $full_name ? $full_name : $author->display_name;
		}

		$content = isset( $row['content'] ) ? (string) $row['content'] : '';

		return array(
			'id'           => (int) $row['id'],
			'author_id'    => (int) $row['author_id'],
			'author_name'  => $author_name,
			'title'        => $row['title'],
			'content'      => $content,
			'excerpt'      => wp_trim_words( wp_strip_all_tags( $content ), self::EXCERPT_WORD_COUNT ),
			'image_id'     => $image_id,
			'image_url'    => $image_url,
			'status'       => $row['status'],
			'status_label' => isset( self::status_options()[ $row['status'] ] ) ? self::status_options()[ $row['status'] ] : $row['status'],
			'published_at' => isset( $row['published_at'] ) ? $row['published_at'] : null,
			'created_at'   => $row['created_at'],
		);
	}
}
