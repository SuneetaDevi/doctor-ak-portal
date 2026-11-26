<?php
/**
 * One WordPress post per public doctor, so each doctor has their own URL,
 * title, meta description and canonical that an SEO plugin (Yoast) can
 * manage — without anyone creating pages by hand.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

use DoctorAKPortal\Frontend\Doctor_Profile_View;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Doctor_Pages
 *
 * How it works:
 * - A public post type (POST_TYPE) at /doctor/{slug}/. Each post is linked
 *   to one doctor (post meta META_DOCTOR_ID; the doctor's user meta
 *   USER_META_POST points back).
 * - The plugin creates, updates and unpublishes these posts itself
 *   (sync_doctor()): when a doctor's data changes (user meta / clinic
 *   hooks, run once at the end of the request), daily by cron, on first
 *   install, and from the "Sync now" button. Nobody creates pages by hand.
 * - The post's title, text and excerpt are written from the doctor's own
 *   data, so Yoast's keyword/readability analysis has real content. Yoast's
 *   own fields (SEO title, meta description, focus keyphrase, URL slug) are
 *   never touched by the sync.
 * - Visitors see the normal doctor profile: on these URLs the page body is
 *   the [doctor_profile_view] output for the linked doctor (render_content()),
 *   inside the plugin's own page template.
 * - Old links (/doctor-profile-view/?doctor_id=N) 301-redirect here, and an
 *   unpublished doctor's URL redirects to the doctors list.
 */
class Doctor_Pages {

	const POST_TYPE      = 'dak_doctor';
	const REWRITE_SLUG   = 'doctor';
	const META_DOCTOR_ID = '_dak_doctor_id';
	const META_HASH      = '_dak_sync_hash';
	const USER_META_POST = 'doctor_ak_seo_page_id';

	/**
	 * Bump to re-run the one-time setup (rewrite flush, Yoast defaults,
	 * full sync) after an update.
	 */
	const VERSION        = '1.0.0';
	const VERSION_OPTION = 'dak_doctor_pages_version';

	const CRON_HOOK   = 'doctor_ak_doctor_pages_sync';
	const SYNC_ACTION = 'doctor_ak_sync_doctor_pages';

	/**
	 * Doctor user IDs whose page should be re-synced at the end of this request.
	 *
	 * @var int[]
	 */
	private static $queue = array();

	/**
	 * doctor ID => published page URL, built once per request by url().
	 *
	 * @var array|null
	 */
	private static $url_map = null;

	/**
	 * Per-request cache for facts().
	 *
	 * @var array
	 */
	private static $facts = array();

	/* ---------------------------------------------------------------------
	 * Setup
	 * ------------------------------------------------------------------- */

	/**
	 * Registers the post type. Hooked to 'init'.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Doctor Pages', 'doctor-ak-portal' ),
					'singular_name'      => __( 'Doctor Page', 'doctor-ak-portal' ),
					'menu_name'          => __( 'Doctor Pages (SEO)', 'doctor-ak-portal' ),
					'all_items'          => __( 'All Doctor Pages', 'doctor-ak-portal' ),
					'edit_item'          => __( 'Edit Doctor Page', 'doctor-ak-portal' ),
					'view_item'          => __( 'View Doctor Page', 'doctor-ak-portal' ),
					'search_items'       => __( 'Search Doctor Pages', 'doctor-ak-portal' ),
					'not_found'          => __( 'No doctor pages yet. Click "Sync doctor pages now" above.', 'doctor-ak-portal' ),
					'not_found_in_trash' => __( 'No doctor pages in the trash.', 'doctor-ak-portal' ),
				),
				'description'         => __( 'One page per public doctor, created and updated automatically from the doctor profiles. Edit the SEO title and description in the Yoast box.', 'doctor-ak-portal' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 21,
				'menu_icon'           => 'dashicons-id-alt',
				'show_in_nav_menus'   => false,
				// The front-end admin bar's "Edit Doctor Page" link needs this. ("+ New"
				// still won't offer it — create_posts is do_not_allow below.)
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'rewrite'             => array(
					'slug'       => self::REWRITE_SLUG,
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
	 * One-time setup after install/update: register the URLs, set Yoast's
	 * default title/description templates for doctor pages, and schedule
	 * the first full sync. Also keeps the daily sync scheduled. Hooked to
	 * 'init' after register_post_type().
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}

		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		self::set_yoast_defaults();
		update_option( self::VERSION_OPTION, self::VERSION );

		// First full sync in the background (next WP-Cron run), so this
		// request isn't slowed down. "Sync now" runs it straight away.
		wp_schedule_single_event( time(), self::CRON_HOOK );
	}

	/**
	 * Yoast's default SEO title / meta description templates for doctor
	 * pages — only filled in when still empty or Yoast's own generic
	 * default, so an SEO manager's settings are never overwritten. Uses the
	 * %%dak_*%% variables registered in register_yoast_variables().
	 *
	 * @return void
	 */
	private static function set_yoast_defaults() {
		$titles = get_option( 'wpseo_titles' );

		if ( ! is_array( $titles ) ) {
			return;
		}

		$title_key = 'title-' . self::POST_TYPE;
		$desc_key  = 'metadesc-' . self::POST_TYPE;

		if ( empty( $titles[ $title_key ] ) || '%%title%% %%page%% %%sep%% %%sitename%%' === $titles[ $title_key ] ) {
			$titles[ $title_key ] = '%%title%% – %%dak_specialty%% in %%dak_city%% %%sep%% Book Appointment %%sep%% %%sitename%%';
		}

		if ( empty( $titles[ $desc_key ] ) ) {
			$titles[ $desc_key ] = 'Book an appointment with %%title%%, %%dak_specialty%% at %%dak_clinics%%, %%dak_city%%. See fees, timings and patient reviews, and book online.';
		}

		update_option( 'wpseo_titles', $titles );
	}

	/* ---------------------------------------------------------------------
	 * Sync
	 * ------------------------------------------------------------------- */

	/**
	 * Re-syncs every doctor's page, and unpublishes pages whose doctor is
	 * gone. Hooked to the daily cron and the "Sync now" button.
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

		$doctor_ids = get_users(
			array(
				'role'   => Roles::DOCTOR_ROLE,
				'fields' => 'ID',
				'number' => -1,
			)
		);

		foreach ( $doctor_ids as $doctor_id ) {
			$result = self::sync_doctor( (int) $doctor_id );

			if ( isset( $counts[ $result ] ) ) {
				++$counts[ $result ];
			}
		}

		// Pages whose doctor no longer exists or is no longer a doctor.
		$doctor_ids = array_map( 'intval', $doctor_ids );
		$orphans    = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_DOCTOR_ID,
						'value'   => $doctor_ids ? $doctor_ids : array( 0 ),
						'compare' => 'NOT IN',
					),
				),
			)
		);

		foreach ( $orphans as $post_id ) {
			wp_update_post(
				array(
					'ID'          => (int) $post_id,
					'post_status' => 'draft',
				)
			);
			++$counts['unpublished'];
		}

		self::$url_map = null;

		update_option( 'dak_doctor_pages_last_sync', current_time( 'mysql' ), false );

		return $counts;
	}

	/**
	 * Creates, updates or unpublishes one doctor's page.
	 *
	 * Only public doctors (approved, not deactivated — the same rule the
	 * profile page itself uses) get a published page; a page is never
	 * created for a doctor who isn't public yet. The URL slug is set once,
	 * at creation, and never changed by the sync (so rankings survive a
	 * name change; WordPress redirects old slugs if someone edits it).
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return string 'created' | 'updated' | 'unpublished' | 'unchanged' | 'skipped' | 'error'.
	 */
	public static function sync_doctor( $doctor_id ) {
		$doctor_id = (int) $doctor_id;
		$user      = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;
		$post_id   = self::post_id_for( $doctor_id );
		$is_doctor = $user && in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true );
		$is_public = $is_doctor && Appointments::is_public_doctor( $doctor_id );

		if ( ! $is_public ) {
			if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
				self::$url_map = null;

				return 'unpublished';
			}

			return 'skipped';
		}

		unset( self::$facts[ $doctor_id ] );
		$data = self::build( $doctor_id );
		$hash = md5( wp_json_encode( $data ) );

		if ( ! $post_id ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => self::POST_TYPE,
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

			update_post_meta( $post_id, self::META_DOCTOR_ID, $doctor_id );
			update_user_meta( $doctor_id, self::USER_META_POST, $post_id );
			$result = 'created';
		} else {
			$unchanged = get_post_meta( $post_id, self::META_HASH, true ) === $hash && 'publish' === get_post_status( $post_id );

			if ( $unchanged ) {
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

		update_post_meta( $post_id, self::META_HASH, $hash );
		self::$url_map = null;

		return $result;
	}

	/**
	 * The doctor's page post ID, or 0. Ignores a link to a post that's gone
	 * or in the trash (a fresh page is created instead).
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return int
	 */
	public static function post_id_for( $doctor_id ) {
		$post_id = (int) get_user_meta( $doctor_id, self::USER_META_POST, true );

		if ( $post_id <= 0 ) {
			return 0;
		}

		$post = get_post( $post_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return 0;
		}

		return $post_id;
	}

	/**
	 * Queues a doctor for a re-sync at the end of the request when one of
	 * the fields shown on their page changes. Hooked to
	 * added_/updated_/deleted_user_meta.
	 *
	 * @param int    $meta_id  Unused.
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public static function on_user_meta_change( $meta_id, $user_id, $meta_key ) {
		global $wpdb;

		unset( $meta_id );

		if ( self::USER_META_POST === $meta_key ) {
			return;
		}

		if ( 0 === strpos( $meta_key, 'doctor_ak_' ) || in_array( $meta_key, array( 'first_name', 'last_name', $wpdb->prefix . 'capabilities' ), true ) ) {
			self::queue( $user_id );
		}
	}

	/**
	 * Queues a doctor for a re-sync at the end of the request.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return void
	 */
	public static function queue( $doctor_id ) {
		$doctor_id = (int) $doctor_id;

		if ( $doctor_id > 0 ) {
			self::$queue[ $doctor_id ] = $doctor_id;
		}
	}

	/**
	 * Syncs every queued doctor. Hooked to 'shutdown' — once per request,
	 * however many of a doctor's fields were saved.
	 *
	 * @return void
	 */
	public static function process_queue() {
		$queue       = self::$queue;
		self::$queue = array();

		foreach ( $queue as $doctor_id ) {
			$user = get_userdata( $doctor_id );

			// Patients and staff share the doctor_ak_ meta prefix — only
			// doctors (or a user that has a page from when they were one).
			if ( ! $user || ( ! in_array( Roles::DOCTOR_ROLE, (array) $user->roles, true ) && ! self::post_id_for( $doctor_id ) ) ) {
				continue;
			}

			self::sync_doctor( $doctor_id );
		}
	}

	/**
	 * A deleted doctor's page is deleted with them. Hooked to 'delete_user'.
	 *
	 * @param int $user_id User being deleted.
	 * @return void
	 */
	public static function on_delete_user( $user_id ) {
		$post_id = self::post_id_for( $user_id );

		if ( $post_id ) {
			wp_delete_post( $post_id, true );
		}

		unset( self::$queue[ (int) $user_id ] );
	}

	/**
	 * Title, slug, text, excerpt and photo for a doctor's page.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return array { title, slug, content, excerpt, thumbnail_id }
	 */
	private static function build( $doctor_id ) {
		$f = self::facts( $doctor_id );

		$lines = array();

		if ( '' !== $f['short_description'] ) {
			$lines[] = '<p>' . esc_html( $f['short_description'] ) . '</p>';
		}

		/* translators: %s: doctor's name. */
		$lines[] = '<h2>' . esc_html( sprintf( __( 'About %s', 'doctor-ak-portal' ), $f['title'] ) ) . '</h2>';
		$lines[] = '<p>' . esc_html( self::summary_sentence( $f ) ) . '</p>';

		if ( count( $f['specialties'] ) > 1 ) {
			$lines[] = '<h2>' . esc_html__( 'Specialities', 'doctor-ak-portal' ) . '</h2>';
			$lines[] = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $f['specialties'] ) ) . '</li></ul>';
		}

		if ( '' !== $f['expertise'] ) {
			$lines[] = '<h2>' . esc_html__( 'Areas of expertise', 'doctor-ak-portal' ) . '</h2>';
			$lines[] = wp_kses_post( $f['expertise'] );
		}

		if ( ! empty( $f['clinic_lines'] ) ) {
			$lines[] = '<h2>' . esc_html__( 'Clinics', 'doctor-ak-portal' ) . '</h2>';
			$lines[] = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $f['clinic_lines'] ) ) . '</li></ul>';
		}

		if ( '' !== $f['fee'] || $f['video'] ) {
			$lines[] = '<h2>' . esc_html__( 'Fees and booking', 'doctor-ak-portal' ) . '</h2>';

			if ( '' !== $f['fee'] ) {
				/* translators: %s: starting consultation fee, e.g. "Rs. 2,000". */
				$lines[] = '<p>' . esc_html( sprintf( __( 'Consultation from %s. Book your appointment online.', 'doctor-ak-portal' ), $f['fee'] ) ) . '</p>';
			}

			if ( $f['video'] ) {
				$lines[] = '<p>' . esc_html__( 'Online video consultation is also available.', 'doctor-ak-portal' ) . '</p>';
			}
		}

		$excerpt = '' !== $f['short_description'] ? $f['short_description'] : self::summary_sentence( $f );

		return array(
			'title'        => $f['title'],
			'slug'         => sanitize_title( trim( 'dr ' . $f['name'] . ' ' . $f['specialty'] ) ),
			'content'      => implode( "\n\n", $lines ),
			'excerpt'      => wp_html_excerpt( $excerpt, 300, '…' ),
			'thumbnail_id' => $f['picture_id'],
		);
	}

	/**
	 * "Dr. X is a Gastroenterologist with 12 years of experience, practising
	 * at A and B in Karachi." — whatever parts are known.
	 *
	 * @param array $f facts().
	 * @return string
	 */
	private static function summary_sentence( array $f ) {
		/* translators: 1: doctor's name, 2: speciality. */
		$sentence = sprintf( __( '%1$s is a %2$s', 'doctor-ak-portal' ), $f['title'], '' !== $f['specialty'] ? $f['specialty'] : __( 'specialist doctor', 'doctor-ak-portal' ) );

		if ( '' !== $f['experience'] ) {
			/* translators: %s: e.g. "12 years". */
			$sentence .= sprintf( __( ' with %s of experience', 'doctor-ak-portal' ), $f['experience'] );
		}

		if ( ! empty( $f['clinic_names'] ) ) {
			/* translators: %s: clinic names. */
			$sentence .= sprintf( __( ', practising at %s', 'doctor-ak-portal' ), wp_sprintf( '%l', array_slice( $f['clinic_names'], 0, 3 ) ) );

			if ( '' !== $f['city'] ) {
				/* translators: %s: city. */
				$sentence .= sprintf( __( ' in %s', 'doctor-ak-portal' ), $f['city'] );
			}
		}

		$sentence .= '.';

		if ( '' !== $f['qualification'] ) {
			/* translators: %s: qualifications, e.g. "MBBS, FCPS (Gastroenterology)". */
			$sentence .= ' ' . sprintf( __( 'Qualifications: %s.', 'doctor-ak-portal' ), rtrim( $f['qualification'], '.' ) );
		}

		return $sentence;
	}

	/**
	 * Everything a doctor's page and the Yoast variables say about them.
	 *
	 * @param int $doctor_id Doctor user ID.
	 * @return array
	 */
	public static function facts( $doctor_id ) {
		$doctor_id = (int) $doctor_id;

		if ( isset( self::$facts[ $doctor_id ] ) ) {
			return self::$facts[ $doctor_id ];
		}

		$user = get_userdata( $doctor_id );
		$name = '';

		if ( $user ) {
			$name = trim( $user->first_name . ' ' . $user->last_name );
			$name = '' !== $name ? $name : $user->display_name;
		}

		// "Dr." is added once, whether or not the stored name already has it.
		$name = trim( preg_replace( '/^dr\.?\s+/i', '', (string) $name ) );

		$all_specializations = Specializations::get_all();
		$specialties         = array();

		foreach ( (array) get_user_meta( $doctor_id, 'doctor_ak_specializations', true ) as $slug ) {
			if ( isset( $all_specializations[ $slug ] ) ) {
				$specialties[] = $all_specializations[ $slug ];
			}
		}

		$clinic_names = array();
		$clinic_lines = array();
		$cities       = array();
		$video        = false;

		foreach ( Clinics::get_for_doctor( $doctor_id ) as $clinic ) {
			if ( Clinics::TYPE_PHYSICAL !== $clinic['type'] ) {
				$video = true;
				continue;
			}

			$place          = implode( ', ', array_filter( array( $clinic['area_label'], $clinic['city_label'] ) ) );
			$clinic_names[] = $clinic['name'];
			$clinic_lines[] = '' !== $place ? $clinic['name'] . ' — ' . $place : $clinic['name'];

			if ( '' !== $clinic['city_label'] ) {
				$cities[] = $clinic['city_label'];
			}
		}

		$clinic_names = array_values( array_unique( $clinic_names ) );
		$cities       = array_values( array_unique( $cities ) );
		$years        = (int) get_user_meta( $doctor_id, 'doctor_ak_years_experience', true );
		$fee          = Doctor_Profile_View::starting_fee_summary( $doctor_id );
		$expertise    = get_user_meta( $doctor_id, 'doctor_ak_expertise', true );

		self::$facts[ $doctor_id ] = array(
			'name'              => $name,
			'title'             => '' !== $name ? sprintf( 'Dr. %s', $name ) : __( 'Doctor', 'doctor-ak-portal' ),
			'specialty'         => isset( $specialties[0] ) ? $specialties[0] : '',
			'specialties'       => $specialties,
			'qualification'     => trim( (string) get_user_meta( $doctor_id, 'doctor_ak_qualification', true ) ),
			/* translators: %d: number of years. */
			'experience'        => $years > 0 ? sprintf( _n( '%d year', '%d years', $years, 'doctor-ak-portal' ), $years ) : '',
			'short_description' => trim( wp_strip_all_tags( (string) get_user_meta( $doctor_id, 'doctor_ak_short_description', true ) ) ),
			'expertise'         => is_array( $expertise ) ? implode( ', ', array_map( 'wp_strip_all_tags', $expertise ) ) : trim( (string) $expertise ),
			'clinic_names'      => $clinic_names,
			'clinic_lines'      => $clinic_lines,
			'city'              => isset( $cities[0] ) ? $cities[0] : '',
			'video'             => $video,
			'fee'               => in_array( $fee['state'], array( 'paid', 'range' ), true ) ? $fee['label'] : '',
			'picture_id'        => (int) get_user_meta( $doctor_id, 'doctor_ak_profile_picture_id', true ),
		);

		return self::$facts[ $doctor_id ];
	}

	/* ---------------------------------------------------------------------
	 * Links
	 * ------------------------------------------------------------------- */

	/**
	 * A doctor's public profile URL: their own page when it's published,
	 * otherwise the old /doctor-profile-view/?doctor_id=N address. Every
	 * "View profile" link in the plugin goes through here.
	 *
	 * @param int   $doctor_id Doctor user ID.
	 * @param array $args      Extra query args (e.g. a review token).
	 * @return string '' when neither exists.
	 */
	public static function url( $doctor_id, array $args = array() ) {
		$doctor_id = (int) $doctor_id;
		$map       = self::url_map();

		if ( isset( $map[ $doctor_id ] ) ) {
			unset( $args['doctor_id'] );

			return $args ? add_query_arg( $args, $map[ $doctor_id ] ) : $map[ $doctor_id ];
		}

		$base = Page_Finder::url_for_shortcode( Doctor_Profile_View::SHORTCODE_TAG );

		return '' !== $base ? add_query_arg( array_merge( array( 'doctor_id' => $doctor_id ), $args ), $base ) : '';
	}

	/**
	 * doctor ID => published page URL, in one query per request.
	 *
	 * @return array
	 */
	private static function url_map() {
		if ( null !== self::$url_map ) {
			return self::$url_map;
		}

		self::$url_map = array();

		$posts = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $posts as $post ) {
			$doctor_id = (int) get_post_meta( $post->ID, self::META_DOCTOR_ID, true );

			if ( $doctor_id > 0 ) {
				self::$url_map[ $doctor_id ] = get_permalink( $post );
			}
		}

		return self::$url_map;
	}

	/**
	 * Whether this request is a doctor page.
	 *
	 * @return bool
	 */
	public static function is_doctor_page() {
		return ! is_admin() && is_singular( self::POST_TYPE );
	}

	/**
	 * On a doctor page, makes the rest of the plugin see the linked doctor
	 * exactly as on the old ?doctor_id= URL (the profile renderer, review
	 * request/link handling and page-title code all read it). Hooked to 'wp'.
	 *
	 * @return void
	 */
	public static function setup_request() {
		if ( ! self::is_doctor_page() ) {
			return;
		}

		$doctor_id = (int) get_post_meta( get_queried_object_id(), self::META_DOCTOR_ID, true );

		$_GET['doctor_id']     = $doctor_id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_REQUEST['doctor_id'] = $doctor_id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The page body visitors see: the normal doctor profile, not the post's
	 * own text (that text is there for search engines and Yoast's analysis).
	 * Hooked to 'the_content', last.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function render_content( $content ) {
		if ( self::is_doctor_page() && in_the_loop() && is_main_query() ) {
			return do_shortcode( '[' . Doctor_Profile_View::SHORTCODE_TAG . ']' );
		}

		return $content;
	}

	/**
	 * Redirects: an old /doctor-profile-view/?doctor_id=N link to the
	 * doctor's page (301, other query args kept), and an unpublished or
	 * removed doctor's page to the doctors list. GET requests only. Hooked
	 * to 'template_redirect' after Review_Requests::handle_landing().
	 *
	 * @return void
	 */
	public static function redirects() {
		if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect lookup.
		if ( is_singular() && ! self::is_doctor_page() && isset( $_GET['doctor_id'] ) ) {
			$post = get_queried_object();

			if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, Doctor_Profile_View::SHORTCODE_TAG ) ) {
				$doctor_id = absint( $_GET['doctor_id'] );
				$map       = self::url_map();

				if ( isset( $map[ $doctor_id ] ) ) {
					$args = wp_unslash( $_GET );
					unset( $args['doctor_id'] );

					wp_safe_redirect( $args ? add_query_arg( urlencode_deep( $args ), $map[ $doctor_id ] ) : $map[ $doctor_id ], 301 );
					exit;
				}
			}
		}
		// phpcs:enable

		if ( is_404() ) {
			$path      = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH ), '/' );
			$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

			if ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
				$path = substr( $path, strlen( $home_path ) + 1 );
			}

			if ( 0 === strpos( $path, self::REWRITE_SLUG . '/' ) ) {
				$directory = Page_Finder::url_for_shortcode( 'doctors_directory' );

				if ( '' !== $directory ) {
					wp_safe_redirect( $directory, 301 );
					exit;
				}
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Yoast SEO
	 * ------------------------------------------------------------------- */

	/**
	 * Doctor variables for Yoast titles/descriptions (Yoast → Settings →
	 * Content types → Doctor Pages, or a single page's Yoast box).
	 * Hooked to 'wpseo_register_extra_replacements'.
	 *
	 * @return void
	 */
	public static function register_yoast_variables() {
		if ( ! function_exists( 'wpseo_register_var_replacement' ) ) {
			return;
		}

		$vars = array(
			'dak_specialty'     => __( "The doctor's main speciality, e.g. Gastroenterologist.", 'doctor-ak-portal' ),
			'dak_specialties'   => __( "All of the doctor's specialities.", 'doctor-ak-portal' ),
			'dak_city'          => __( "City of the doctor's first clinic.", 'doctor-ak-portal' ),
			'dak_clinics'       => __( "The doctor's clinics (up to three).", 'doctor-ak-portal' ),
			'dak_experience'    => __( 'Years of experience, e.g. "12 years" (empty if not set).', 'doctor-ak-portal' ),
			'dak_qualification' => __( "The doctor's qualifications.", 'doctor-ak-portal' ),
			'dak_fee'           => __( 'Starting consultation fee, e.g. "Rs. 2,000" (empty if not set).', 'doctor-ak-portal' ),
		);

		foreach ( $vars as $var => $help ) {
			wpseo_register_var_replacement( '%%' . $var . '%%', array( __CLASS__, 'yoast_variable_' . substr( $var, 4 ) ), 'advanced', $help );
		}
	}

	/**
	 * The facts() of the doctor behind the post Yoast is replacing
	 * variables for, or null.
	 *
	 * @param mixed $args Yoast's replacement args (the post object).
	 * @return array|null
	 */
	private static function facts_for_yoast( $args ) {
		$post_id = is_object( $args ) && isset( $args->ID ) ? (int) $args->ID : 0;

		if ( $post_id <= 0 && self::is_doctor_page() ) {
			$post_id = get_queried_object_id();
		}

		$doctor_id = $post_id > 0 ? (int) get_post_meta( $post_id, self::META_DOCTOR_ID, true ) : 0;

		return $doctor_id > 0 ? self::facts( $doctor_id ) : null;
	}

	/** @param string $var Unused. @param mixed $args Post. @return string */
	public static function yoast_variable_specialty( $var, $args ) {
		$f = self::facts_for_yoast( $args );
		return $f ? ( '' !== $f['specialty'] ? $f['specialty'] : __( 'Specialist doctor', 'doctor-ak-portal' ) ) : '';
	}

	/** @param string $var Unused. @param mixed $args Post. @return string */
	public static function yoast_variable_specialties( $var, $args ) {
		$f = self::facts_for_yoast( $args );
		return $f ? implode( ', ', $f['specialties'] ) : '';
	}

	/** @param string $var Unused. @param mixed $args Post. @return string */
	public static function yoast_variable_city( $var, $args ) {
		$f = self::facts_for_yoast( $args );
		return $f ? ( '' !== $f['city'] ? $f['city'] : __( 'Pakistan', 'doctor-ak-portal' ) ) : '';
	}

	/** @param string $var Unused. @param mixed $args Post. @return string */
	public static function yoast_variable_clinics( $var, $args ) {
		$f = self::facts_for_yoast( $args );
		return $f ? ( $f['clinic_names'] ? wp_sprintf( '%l', array_slice( $f['clinic_names'], 0, 3 ) ) : __( 'our clinics', 'doctor-ak-portal' ) ) : '';
	}

	/** @param string $var Unused. @param mixed $args Post. @return string */
	public static function yoast_variable_experience( $var, $args ) {
		$f = self::facts_for_yoast( $args );
		return $f ? $f['experience'] : '';
	}

	/** @param string $var Unused. @param mixed $args Post. @return string */
	public static function yoast_variable_qualification( $var, $args ) {
		$f = self::facts_for_yoast( $args );
		return $f ? $f['qualification'] : '';
	}

	/** @param string $var Unused. @param mixed $args Post. @return string */
	public static function yoast_variable_fee( $var, $args ) {
		$f = self::facts_for_yoast( $args );
		return $f ? $f['fee'] : '';
	}

	/**
	 * Keeps the bare /doctor-profile-view/ page (no doctor chosen — it only
	 * says "Doctor not found") out of Yoast's sitemap. Hooked to
	 * 'wpseo_exclude_from_sitemap_by_post_ids'.
	 *
	 * @param int[] $ids Post IDs Yoast already excludes.
	 * @return int[]
	 */
	public static function exclude_base_page_from_sitemap( $ids ) {
		$base_id = self::base_page_id();

		if ( $base_id ) {
			$ids[] = $base_id;
		}

		return $ids;
	}

	/**
	 * …and tells search engines not to index it. Hooked to 'wpseo_robots'.
	 *
	 * @param string $robots Robots value Yoast was about to print.
	 * @return string
	 */
	public static function noindex_base_page( $robots ) {
		$base_id = self::base_page_id();

		if ( $base_id && is_page( $base_id ) ) {
			return 'noindex, follow';
		}

		return $robots;
	}

	/**
	 * ID of the page holding [doctor_profile_view], or 0.
	 *
	 * @return int
	 */
	private static function base_page_id() {
		$url = Page_Finder::url_for_shortcode( Doctor_Profile_View::SHORTCODE_TAG );

		return '' !== $url ? (int) url_to_postid( $url ) : 0;
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------- */

	/**
	 * Explains the screen to whoever opens a doctor page in wp-admin.
	 * Hooked to 'edit_form_after_title'.
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	public static function edit_screen_notice( $post ) {
		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		$doctor_id = (int) get_post_meta( $post->ID, self::META_DOCTOR_ID, true );
		$user      = $doctor_id ? get_userdata( $doctor_id ) : false;
		?>
		<div class="notice notice-info inline" style="margin:12px 0;">
			<p>
				<strong><?php esc_html_e( 'This page is created and updated automatically from the doctor\'s profile.', 'doctor-ak-portal' ); ?></strong>
				<?php if ( $user ) : ?>
					<?php
					/* translators: %s: doctor's name. */
					echo esc_html( sprintf( __( 'Linked doctor: %s.', 'doctor-ak-portal' ), $user->display_name ) );
					?>
				<?php endif; ?>
			</p>
			<p>
				<?php esc_html_e( 'For SEO, use the Yoast SEO box below: SEO title, meta description, focus keyphrase and URL slug. Those are never changed by the automatic update.', 'doctor-ak-portal' ); ?>
				<?php esc_html_e( 'The title and text above are rewritten whenever the doctor\'s details change — edit the doctor in the Admin Dashboard instead. Visitors see the normal doctor profile, not this text.', 'doctor-ak-portal' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * "Sync doctor pages now" button above the list. Hooked to
	 * 'views_edit-dak_doctor'.
	 *
	 * @param array $views List-table view links.
	 * @return array
	 */
	public static function list_sync_button( $views ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return $views;
		}

		$url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::SYNC_ACTION ), self::SYNC_ACTION );
		$last = get_option( 'dak_doctor_pages_last_sync', '' );

		$views['dak_sync'] = sprintf(
			'<a href="%1$s" class="button button-small" style="vertical-align:middle;">%2$s</a> <span class="description">%3$s</span>',
			esc_url( $url ),
			esc_html__( 'Sync doctor pages now', 'doctor-ak-portal' ),
			'' !== $last
				/* translators: %s: date and time of the last sync. */
				? esc_html( sprintf( __( 'Last synced: %s', 'doctor-ak-portal' ), mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ) ) )
				: esc_html__( 'Not synced yet.', 'doctor-ak-portal' )
		);

		return $views;
	}

	/**
	 * Handles the "Sync now" button. Hooked to 'admin_post_' . SYNC_ACTION.
	 *
	 * @return void
	 */
	public static function handle_sync_now() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'doctor-ak-portal' ), 403 );
		}

		check_admin_referer( self::SYNC_ACTION );

		$counts = self::sync_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'  => self::POST_TYPE,
					'dak_synced' => implode( '-', array( $counts['created'], $counts['updated'], $counts['unpublished'], $counts['unchanged'] ) ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Result of "Sync now". Hooked to 'admin_notices'.
	 *
	 * @return void
	 */
	public static function sync_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$raw = isset( $_GET['dak_synced'] ) ? sanitize_text_field( wp_unslash( $_GET['dak_synced'] ) ) : '';

		if ( '' === $raw || ! preg_match( '/^\d+-\d+-\d+-\d+$/', $raw ) ) {
			return;
		}

		list( $created, $updated, $unpublished, $unchanged ) = array_map( 'intval', explode( '-', $raw ) );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: created, 2: updated, 3: unpublished, 4: unchanged. */
					__( 'Doctor pages synced: %1$d created, %2$d updated, %3$d unpublished, %4$d already up to date.', 'doctor-ak-portal' ),
					$created,
					$updated,
					$unpublished,
					$unchanged
				)
			)
		);
	}

	/**
	 * Doctor pages have no author. WordPress would otherwise record
	 * whoever's action triggered the sync (often an administrator), and
	 * that gives the admin account a public /author/{username}/ archive.
	 * Hooked to 'wp_insert_post_data'.
	 *
	 * @param array $data Post data about to be saved.
	 * @return array
	 */
	public static function no_author( $data ) {
		if ( isset( $data['post_type'] ) && self::POST_TYPE === $data['post_type'] ) {
			$data['post_author'] = 0;
		}

		return $data;
	}

	/**
	 * Doctor pages can't be deleted by hand — the sync manages them
	 * (deleting the doctor deletes the page). Hooked to 'map_meta_cap'.
	 *
	 * @param string[] $caps    Required primitive caps.
	 * @param string   $cap     Meta cap being checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    [0] => post ID.
	 * @return string[]
	 */
	public static function prevent_delete( $caps, $cap, $user_id, $args ) {
		unset( $user_id );

		if ( 'delete_post' === $cap && ! empty( $args[0] ) && self::POST_TYPE === get_post_type( (int) $args[0] ) && ! doing_action( 'delete_user' ) ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}
}
