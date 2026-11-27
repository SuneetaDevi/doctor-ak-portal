<?php
/**
 * Shared engine for the plugin's automatic SEO pages: one real WordPress
 * post per doctor (Doctor_Pages), service (Service_Pages) and clinic
 * (Clinic_Pages), so each has its own URL, title, meta description and
 * canonical that Yoast SEO manages — with no pages created by hand.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seo_Pages
 *
 * Each subclass is one kind of page and defines:
 * - constants: POST_TYPE, NAME (used in option/hook names), REWRITE_SLUG,
 *   META_KEY (post meta holding the item's key), SHORTCODE (the profile
 *   shortcode that renders the page), DIRECTORY_SHORTCODE, QUERY_ARG (the
 *   old ?arg= the profile page reads), VERSION, MENU_POSITION, MENU_ICON;
 * - labels(), public_keys(), build(), request_args(), key_from_old_request(),
 *   yoast_variables(), yoast_defaults(), source_label().
 *
 * An item is identified by a string "key" (a doctor's user ID, a clinic
 * location ID, a service name). The sync (sync_all()/sync_key()) creates
 * a post for every public item, rewrites its title/text when the item
 * changes, and unpublishes it when the item stops being public. Yoast's
 * own fields and the URL slug are never touched by the sync.
 *
 * On the page's URL, the item's normal profile is shown (render_content())
 * inside the plugin's page template; old ?arg= links 301-redirect to the
 * page, and a removed item's URL redirects to its directory.
 */
abstract class Seo_Pages {

	/**
	 * Per-subclass runtime state, keyed by class name: queue, queue_all,
	 * posts (key => { id, status, url }).
	 *
	 * @var array
	 */
	private static $state = array();

	/**
	 * Every kind of SEO page.
	 *
	 * @return string[] Class names.
	 */
	public static function classes() {
		return array(
			Doctor_Pages::class,
			Service_Pages::class,
			Clinic_Pages::class,
		);
	}

	/**
	 * Whether a post type is one of these SEO page types.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_managed_post_type( $post_type ) {
		foreach ( self::classes() as $class ) {
			if ( $class::POST_TYPE === $post_type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The SEO page class for this request, or null.
	 *
	 * @return string|null
	 */
	public static function current_class() {
		foreach ( self::classes() as $class ) {
			if ( $class::is_entity_page() ) {
				return $class;
			}
		}

		return null;
	}

	/* ---------------------------------------------------------------------
	 * What each kind of page defines
	 * ------------------------------------------------------------------- */

	/**
	 * Post type labels: { name, singular, menu }.
	 *
	 * @return array
	 */
	abstract protected static function labels();

	/**
	 * Keys of every item that should have a published page right now.
	 *
	 * @return string[]
	 */
	abstract protected static function public_keys();

	/**
	 * Title, slug, text, excerpt and photo for an item's page.
	 *
	 * @param string $key Item key.
	 * @return array { title, slug, content, excerpt, thumbnail_id }
	 */
	abstract protected static function build( $key );

	/**
	 * Query args the profile renderer reads, for an item ($_GET is set to
	 * these on the item's page — e.g. array( 'doctor_id' => 12 )).
	 *
	 * @param string $key Item key.
	 * @return array
	 */
	abstract protected static function request_args( $key );

	/**
	 * The item an old ?QUERY_ARG= profile link points at, or ''.
	 *
	 * @return string
	 */
	abstract protected static function key_from_old_request();

	/**
	 * Yoast variables: name (without %%) => array( help text, callable( $key ) ).
	 *
	 * @return array
	 */
	abstract protected static function yoast_variables();

	/**
	 * Yoast default templates: array( title, metadesc ).
	 *
	 * @return array
	 */
	abstract protected static function yoast_defaults();

	/**
	 * Where the page's data comes from, for the edit-screen note
	 * (e.g. "the doctor's profile").
	 *
	 * @return string
	 */
	abstract protected static function source_label();

	/**
	 * Whether one item should have a published page. Subclasses may
	 * override with something cheaper than building the whole list.
	 *
	 * @param string $key Item key.
	 * @return bool
	 */
	protected static function is_public( $key ) {
		return in_array( (string) $key, array_map( 'strval', static::public_keys() ), true );
	}

	/* ---------------------------------------------------------------------
	 * Names derived from NAME (kept identical to the first, doctor-only
	 * release so its settings carry over)
	 * ------------------------------------------------------------------- */

	/** @return string */
	public static function version_option() {
		return 'dak_' . static::NAME . '_pages_version';
	}

	/** @return string */
	public static function cron_hook() {
		return 'doctor_ak_' . static::NAME . '_pages_sync';
	}

	/** @return string */
	public static function sync_action() {
		return 'doctor_ak_sync_' . static::NAME . '_pages';
	}

	/** @return string */
	protected static function last_sync_option() {
		return 'dak_' . static::NAME . '_pages_last_sync';
	}

	/**
	 * This subclass's runtime state.
	 *
	 * @return array Reference.
	 */
	protected static function &state() {
		$class = static::class;

		if ( ! isset( self::$state[ $class ] ) ) {
			self::$state[ $class ] = array(
				'queue'     => array(),
				'queue_all' => false,
				'posts'     => null,
			);
		}

		return self::$state[ $class ];
	}

	/* ---------------------------------------------------------------------
	 * Setup
	 * ------------------------------------------------------------------- */

	/**
	 * Registers the post type. Hooked to 'init'.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$l = static::labels();

		register_post_type(
			static::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => $l['name'],
					'singular_name'      => $l['singular'],
					'menu_name'          => $l['menu'],
					/* translators: %s: plural label, e.g. "Doctor Pages". */
					'all_items'          => sprintf( __( 'All %s', 'doctor-ak-portal' ), $l['name'] ),
					/* translators: %s: singular label, e.g. "Doctor Page". */
					'edit_item'          => sprintf( __( 'Edit %s', 'doctor-ak-portal' ), $l['singular'] ),
					/* translators: %s: singular label. */
					'view_item'          => sprintf( __( 'View %s', 'doctor-ak-portal' ), $l['singular'] ),
					/* translators: %s: plural label. */
					'search_items'       => sprintf( __( 'Search %s', 'doctor-ak-portal' ), $l['name'] ),
					'not_found'          => __( 'Nothing here yet. Click the "Sync now" button above.', 'doctor-ak-portal' ),
					'not_found_in_trash' => __( 'Nothing in the trash.', 'doctor-ak-portal' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => static::MENU_POSITION,
				'menu_icon'           => static::MENU_ICON,
				'show_in_nav_menus'   => false,
				// The front-end admin bar's "Edit … Page" link needs this. ("+ New"
				// still won't offer it — create_posts is do_not_allow below.)
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'rewrite'             => array(
					'slug'       => static::REWRITE_SLUG,
					'with_front' => false,
				),
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				// Pages only ever come from the sync, never "Add New".
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}

	/**
	 * One-time setup after install/update (register URLs, Yoast defaults,
	 * first full sync in the background) and keeps the daily sync
	 * scheduled. Hooked to 'init' after register_post_type().
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( ! wp_next_scheduled( static::cron_hook() ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', static::cron_hook() );
		}

		if ( static::VERSION === get_option( static::version_option() ) ) {
			return;
		}

		flush_rewrite_rules( false );
		static::set_yoast_defaults();
		update_option( static::version_option(), static::VERSION );

		wp_schedule_single_event( time(), static::cron_hook() );
	}

	/**
	 * Fills Yoast's default SEO title / meta description for this page type,
	 * only where still empty or Yoast's own generic default — an SEO
	 * manager's settings are never overwritten.
	 *
	 * @return void
	 */
	protected static function set_yoast_defaults() {
		$titles = get_option( 'wpseo_titles' );

		if ( ! is_array( $titles ) ) {
			return;
		}

		list( $title, $desc ) = static::yoast_defaults();

		$title_key = 'title-' . static::POST_TYPE;
		$desc_key  = 'metadesc-' . static::POST_TYPE;

		if ( empty( $titles[ $title_key ] ) || '%%title%% %%page%% %%sep%% %%sitename%%' === $titles[ $title_key ] ) {
			$titles[ $title_key ] = $title;
		}

		if ( empty( $titles[ $desc_key ] ) ) {
			$titles[ $desc_key ] = $desc;
		}

		update_option( 'wpseo_titles', $titles );
	}

	/* ---------------------------------------------------------------------
	 * Sync
	 * ------------------------------------------------------------------- */

	/**
	 * Syncs every page of this type. Hooked to the daily cron, the
	 * "Sync now" button and queue_all().
	 *
	 * @return array Counts: { created, updated, unpublished, unchanged }.
	 */
	public static function sync_all() {
		$counts = array(
			'created'     => 0,
			'updated'     => 0,
			'unpublished' => 0,
			'unchanged'   => 0,
		);

		$public = array_values( array_unique( array_map( 'strval', static::public_keys() ) ) );

		foreach ( $public as $key ) {
			$result = static::sync_key( $key, true );

			if ( isset( $counts[ $result ] ) ) {
				++$counts[ $result ];
			}
		}

		$public_set = array_flip( $public );

		foreach ( static::posts() as $key => $info ) {
			if ( 'publish' === $info['status'] && ! isset( $public_set[ (string) $key ] ) ) {
				static::unpublish( (string) $key );
				++$counts['unpublished'];
			}
		}

		update_option( static::last_sync_option(), current_time( 'mysql' ), false );

		return $counts;
	}

	/**
	 * Creates, updates or unpublishes one item's page. The URL slug is set
	 * once, at creation, and never changed by the sync.
	 *
	 * @param string    $key       Item key.
	 * @param bool|null $is_public Already known, or null to check.
	 * @return string 'created' | 'updated' | 'unpublished' | 'unchanged' | 'skipped' | 'error'.
	 */
	public static function sync_key( $key, $is_public = null ) {
		$key     = (string) $key;
		$posts   = static::posts();
		$post_id = isset( $posts[ $key ] ) ? $posts[ $key ]['id'] : 0;

		if ( null === $is_public ) {
			$is_public = static::is_public( $key );
		}

		if ( ! $is_public ) {
			if ( $post_id && 'publish' === $posts[ $key ]['status'] ) {
				static::unpublish( $key );

				return 'unpublished';
			}

			return 'skipped';
		}

		$data = static::build( $key );
		$hash = md5( wp_json_encode( $data ) );

		if ( ! $post_id ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => static::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $data['title'],
					'post_name'    => $data['slug'],
					'post_content' => $data['content'],
					'post_excerpt' => $data['excerpt'],
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return 'error';
			}

			update_post_meta( $post_id, static::META_KEY, $key );
			$result = 'created';
		} else {
			if ( 'publish' === $posts[ $key ]['status'] && get_post_meta( $post_id, '_dak_sync_hash', true ) === $hash ) {
				return 'unchanged';
			}

			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_status'  => 'publish',
					'post_title'   => $data['title'],
					'post_content' => $data['content'],
					'post_excerpt' => $data['excerpt'],
				)
			);
			$result = 'updated';
		}

		if ( $data['thumbnail_id'] > 0 ) {
			set_post_thumbnail( $post_id, $data['thumbnail_id'] );
		} else {
			delete_post_thumbnail( $post_id );
		}

		update_post_meta( $post_id, '_dak_sync_hash', $hash );
		static::remember( $key, (int) $post_id, 'publish' );

		return $result;
	}

	/**
	 * Unpublishes an item's page (its URL then redirects to the directory).
	 *
	 * @param string $key Item key.
	 * @return void
	 */
	protected static function unpublish( $key ) {
		$posts = static::posts();

		if ( empty( $posts[ $key ] ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $posts[ $key ]['id'],
				'post_status' => 'draft',
			)
		);
		static::remember( $key, $posts[ $key ]['id'], 'draft' );
	}

	/**
	 * Deletes an item's page outright (e.g. its doctor was deleted).
	 *
	 * @param string $key Item key.
	 * @return void
	 */
	public static function delete_page( $key ) {
		$key   = (string) $key;
		$posts = static::posts();

		if ( ! empty( $posts[ $key ] ) ) {
			wp_delete_post( $posts[ $key ]['id'], true );

			$s = &static::state();
			unset( $s['posts'][ $key ] );
		}
	}

	/**
	 * key => { id, status, url } for every page of this type (not trashed),
	 * in one query per request.
	 *
	 * @return array
	 */
	protected static function posts() {
		$s = &static::state();

		if ( null !== $s['posts'] ) {
			return $s['posts'];
		}

		$s['posts'] = array();

		$posts = get_posts(
			array(
				'post_type'              => static::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);

		foreach ( $posts as $post ) {
			$key = (string) get_post_meta( $post->ID, static::META_KEY, true );

			// The oldest post wins if two ever point at the same item.
			if ( '' !== $key && ! isset( $s['posts'][ $key ] ) ) {
				$s['posts'][ $key ] = array(
					'id'     => (int) $post->ID,
					'status' => $post->post_status,
					'url'    => 'publish' === $post->post_status ? get_permalink( $post ) : '',
				);
			}
		}

		return $s['posts'];
	}

	/**
	 * Updates the in-memory posts() entry after a write.
	 *
	 * @param string $key     Item key.
	 * @param int    $post_id Post ID.
	 * @param string $status  New status.
	 * @return void
	 */
	protected static function remember( $key, $post_id, $status ) {
		$s = &static::state();

		if ( null === $s['posts'] ) {
			return;
		}

		$s['posts'][ $key ] = array(
			'id'     => (int) $post_id,
			'status' => $status,
			'url'    => 'publish' === $status ? get_permalink( $post_id ) : '',
		);
	}

	/**
	 * Re-syncs one item at the end of this request.
	 *
	 * @param string|int $key Item key.
	 * @return void
	 */
	public static function queue( $key ) {
		$key = (string) $key;

		if ( '' !== $key && '0' !== $key ) {
			$s                 = &static::state();
			$s['queue'][ $key ] = $key;
		}
	}

	/**
	 * Re-syncs every page of this type at the end of this request.
	 *
	 * @return void
	 */
	public static function queue_all() {
		$s              = &static::state();
		$s['queue_all'] = true;
	}

	/**
	 * Runs whatever was queued. Hooked to 'shutdown'.
	 *
	 * @return void
	 */
	public static function process_queue() {
		$s     = &static::state();
		$all   = $s['queue_all'];
		$queue = $s['queue'];

		$s['queue_all'] = false;
		$s['queue']     = array();

		if ( $all ) {
			static::sync_all();
			return;
		}

		foreach ( $queue as $key ) {
			static::sync_key( $key );
		}
	}

	/* ---------------------------------------------------------------------
	 * Links and the front end
	 * ------------------------------------------------------------------- */

	/**
	 * An item's public URL: its own page when published, otherwise the old
	 * profile page with ?QUERY_ARG=.
	 *
	 * @param string     $key      Item key.
	 * @param array      $args     Extra query args.
	 * @param string|int $fallback Value for ?QUERY_ARG= on the old URL (default: the key).
	 * @return string '' when neither exists.
	 */
	protected static function link( $key, array $args = array(), $fallback = null ) {
		$posts = static::posts();
		$key   = (string) $key;

		if ( isset( $posts[ $key ] ) && '' !== $posts[ $key ]['url'] ) {
			unset( $args[ static::QUERY_ARG ] );

			return $args ? add_query_arg( $args, $posts[ $key ]['url'] ) : $posts[ $key ]['url'];
		}

		$base = Page_Finder::url_for_shortcode( static::SHORTCODE );

		if ( '' === $base ) {
			return '';
		}

		return add_query_arg( array_merge( array( static::QUERY_ARG => null !== $fallback ? $fallback : $key ), $args ), $base );
	}

	/**
	 * Whether this request is one of this type's pages.
	 *
	 * @return bool
	 */
	public static function is_entity_page() {
		return ! is_admin() && is_singular( static::POST_TYPE );
	}

	/**
	 * Key of the item on the current page, or ''.
	 *
	 * @return string
	 */
	protected static function current_key() {
		return static::is_entity_page() ? (string) get_post_meta( get_queried_object_id(), static::META_KEY, true ) : '';
	}

	/**
	 * On this type's page, makes the rest of the plugin see the item exactly
	 * as on its old ?QUERY_ARG= URL. Hooked to 'wp'.
	 *
	 * @return void
	 */
	public static function setup_request() {
		$key = static::current_key();

		if ( '' === $key ) {
			return;
		}

		foreach ( static::request_args( $key ) as $name => $value ) {
			$_GET[ $name ]     = $value; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_REQUEST[ $name ] = $value; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}

	/**
	 * The page body visitors see: the item's normal profile. (The post's
	 * own text is for search engines and Yoast's analysis.) Hooked to
	 * 'the_content', last.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function render_content( $content ) {
		if ( static::is_entity_page() && in_the_loop() && is_main_query() ) {
			return do_shortcode( '[' . static::SHORTCODE . ']' );
		}

		return $content;
	}

	/**
	 * 301s an old /profile-page/?QUERY_ARG=… link to the item's page, other
	 * query args kept. GET only. Hooked to 'template_redirect' (5, after
	 * the review-link landing handler).
	 *
	 * @return void
	 */
	public static function redirect_old_links() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect lookup.
		if ( ! static::is_get() || ! is_singular() || null !== self::current_class() || ! isset( $_GET[ static::QUERY_ARG ] ) ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post || ! has_shortcode( $post->post_content, static::SHORTCODE ) ) {
			return;
		}

		$key   = static::key_from_old_request();
		$posts = static::posts();

		if ( '' === $key || ! isset( $posts[ $key ] ) || '' === $posts[ $key ]['url'] ) {
			return;
		}

		$args = wp_unslash( $_GET );
		unset( $args[ static::QUERY_ARG ] );
		// phpcs:enable

		wp_safe_redirect( $args ? add_query_arg( urlencode_deep( $args ), $posts[ $key ]['url'] ) : $posts[ $key ]['url'], 301 );
		exit;
	}

	/**
	 * A removed or unpublished item's URL goes to its directory instead of
	 * a "not found" page. Hooked to 'template_redirect' (11) — after
	 * WordPress's own old-slug redirect, so an edited or merged page's old
	 * URL still reaches the right page.
	 *
	 * @return void
	 */
	public static function redirect_missing() {
		if ( ! static::is_get() || ! is_404() ) {
			return;
		}

		$path      = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH ), '/' );
		$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		if ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) + 1 );
		}

		if ( 0 !== strpos( $path, static::REWRITE_SLUG . '/' ) ) {
			return;
		}

		$directory = Page_Finder::url_for_shortcode( static::DIRECTORY_SHORTCODE );

		if ( '' !== $directory ) {
			wp_safe_redirect( $directory, 301 );
			exit;
		}
	}

	/**
	 * Whether this is a GET request.
	 *
	 * @return bool
	 */
	protected static function is_get() {
		return 'GET' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET' );
	}

	/* ---------------------------------------------------------------------
	 * Yoast SEO
	 * ------------------------------------------------------------------- */

	/**
	 * Registers this type's %%dak_*%% variables. Hooked to
	 * 'wpseo_register_extra_replacements'.
	 *
	 * @return void
	 */
	public static function register_yoast_variables() {
		if ( ! function_exists( 'wpseo_register_var_replacement' ) ) {
			return;
		}

		foreach ( static::yoast_variables() as $name => $definition ) {
			list( $help, $callback ) = $definition;

			wpseo_register_var_replacement(
				'%%' . $name . '%%',
				function ( $var, $args ) use ( $callback ) {
					unset( $var );

					$key = static::key_for_yoast( $args );

					return '' !== $key ? (string) call_user_func( $callback, $key ) : '';
				},
				'advanced',
				$help
			);
		}
	}

	/**
	 * The item behind the post Yoast is filling variables for, or ''.
	 *
	 * @param mixed $args Yoast's replacement args (the post).
	 * @return string
	 */
	protected static function key_for_yoast( $args ) {
		$post_id = is_object( $args ) && isset( $args->ID ) ? (int) $args->ID : 0;

		if ( $post_id <= 0 && static::is_entity_page() ) {
			$post_id = get_queried_object_id();
		}

		if ( $post_id <= 0 || static::POST_TYPE !== get_post_type( $post_id ) ) {
			return '';
		}

		return (string) get_post_meta( $post_id, static::META_KEY, true );
	}

	/**
	 * Keeps the bare profile page (no item chosen — it only says "not
	 * found") out of Yoast's sitemap. Hooked to
	 * 'wpseo_exclude_from_sitemap_by_post_ids'.
	 *
	 * @param int[] $ids Post IDs Yoast already excludes.
	 * @return int[]
	 */
	public static function exclude_base_page_from_sitemap( $ids ) {
		$base_id = static::base_page_id();

		if ( $base_id ) {
			$ids[] = $base_id;
		}

		return $ids;
	}

	/**
	 * …and tells search engines not to index it. Hooked to 'wpseo_robots'.
	 *
	 * @param string $robots Robots value.
	 * @return string
	 */
	public static function noindex_base_page( $robots ) {
		$base_id = static::base_page_id();

		return ( $base_id && is_page( $base_id ) ) ? 'noindex, follow' : $robots;
	}

	/**
	 * ID of the page holding SHORTCODE, or 0.
	 *
	 * @return int
	 */
	protected static function base_page_id() {
		$url = Page_Finder::url_for_shortcode( static::SHORTCODE );

		return '' !== $url ? (int) url_to_postid( $url ) : 0;
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------- */

	/**
	 * Explains the edit screen. Hooked to 'edit_form_after_title'.
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	public static function edit_screen_notice( $post ) {
		if ( ! $post instanceof \WP_Post || static::POST_TYPE !== $post->post_type ) {
			return;
		}
		?>
		<div class="notice notice-info inline" style="margin:12px 0;">
			<p>
				<strong>
					<?php
					/* translators: %s: e.g. "the doctor's profile". */
					echo esc_html( sprintf( __( 'This page is created and updated automatically from %s.', 'doctor-ak-portal' ), static::source_label() ) );
					?>
				</strong>
			</p>
			<p>
				<?php esc_html_e( 'For SEO, use the Yoast SEO box below: SEO title, meta description, focus keyphrase and URL slug. Those are never changed by the automatic update.', 'doctor-ak-portal' ); ?>
				<?php esc_html_e( 'The title and text above are rewritten whenever the details change — edit them in the Admin Dashboard instead. Visitors see the normal profile page, not this text.', 'doctor-ak-portal' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * "Sync now" button above the list. Hooked to 'views_edit-{POST_TYPE}'.
	 *
	 * @param array $views List-table view links.
	 * @return array
	 */
	public static function list_sync_button( $views ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return $views;
		}

		$url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . static::sync_action() ), static::sync_action() );
		$last = get_option( static::last_sync_option(), '' );

		$views['dak_sync'] = sprintf(
			'<a href="%1$s" class="button button-small" style="vertical-align:middle;">%2$s</a> <span class="description">%3$s</span>',
			esc_url( $url ),
			esc_html__( 'Sync now', 'doctor-ak-portal' ),
			'' !== $last
				/* translators: %s: date and time of the last sync. */
				? esc_html( sprintf( __( 'Last synced: %s', 'doctor-ak-portal' ), mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ) ) )
				: esc_html__( 'Not synced yet.', 'doctor-ak-portal' )
		);

		return $views;
	}

	/**
	 * Handles "Sync now". Hooked to 'admin_post_{sync_action()}'.
	 *
	 * @return void
	 */
	public static function handle_sync_now() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'doctor-ak-portal' ), 403 );
		}

		check_admin_referer( static::sync_action() );

		$c = static::sync_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'  => static::POST_TYPE,
					'dak_synced' => implode( '-', array( $c['created'], $c['updated'], $c['unpublished'], $c['unchanged'] ) ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Result of "Sync now", on this type's list screen. Hooked to 'admin_notices'.
	 *
	 * @return void
	 */
	public static function sync_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$raw  = isset( $_GET['dak_synced'] ) ? sanitize_text_field( wp_unslash( $_GET['dak_synced'] ) ) : '';
		// phpcs:enable

		if ( static::POST_TYPE !== $type || ! preg_match( '/^\d+-\d+-\d+-\d+$/', $raw ) ) {
			return;
		}

		list( $created, $updated, $unpublished, $unchanged ) = array_map( 'intval', explode( '-', $raw ) );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: created, 2: updated, 3: unpublished, 4: unchanged. */
					__( 'Pages synced: %1$d created, %2$d updated, %3$d unpublished, %4$d already up to date.', 'doctor-ak-portal' ),
					$created,
					$updated,
					$unpublished,
					$unchanged
				)
			)
		);
	}

	/**
	 * SEO pages have no author — otherwise whoever's action triggered the
	 * sync (often an administrator) gets a public /author/{username}/
	 * archive. Hooked to 'wp_insert_post_data'.
	 *
	 * @param array $data Post data about to be saved.
	 * @return array
	 */
	public static function no_author( $data ) {
		if ( isset( $data['post_type'] ) && self::is_managed_post_type( $data['post_type'] ) ) {
			$data['post_author'] = 0;
		}

		return $data;
	}

	/**
	 * SEO pages can't be deleted by hand — the sync manages them. Hooked to
	 * 'map_meta_cap'.
	 *
	 * @param string[] $caps    Required primitive caps.
	 * @param string   $cap     Meta cap being checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    [0] => post ID.
	 * @return string[]
	 */
	public static function prevent_delete( $caps, $cap, $user_id, $args ) {
		unset( $user_id );

		if ( 'delete_post' === $cap && ! empty( $args[0] ) && self::is_managed_post_type( get_post_type( (int) $args[0] ) ) ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}
}
