<?php
/**
 * Dashboard → Reviews: every doctor review, moderation, and the shareable
 * review links.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Doctor_Reviews;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Review_Links;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Reviews
 *
 * Administrators, and receptionists given "Reviews" in Roles & Permissions.
 * Moderation never edits what a
 * reviewer wrote — it only publishes, hides or re-shows a review.
 */
class Admin_Reviews {

	const SECTION         = 'reviews';
	const MODERATE_ACTION = 'doctor_ak_review_moderate';
	const RESET_ACTION    = 'doctor_ak_review_link_reset';
	const NONCE_ACTION    = 'doctor_ak_admin_reviews';
	const PER_PAGE        = 20;

	/**
	 * Whether the current user may use this section.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		if ( current_user_can( 'manage_options' ) ) {
			return Role_Permissions::is_tab_allowed( Role_Permissions::ADMIN_KEY, self::SECTION );
		}

		// A receptionist an admin has given "Reviews" in Roles & Permissions.
		return Admin_Dashboard::staff_can( self::SECTION );
	}

	/**
	 * The section's own URL, optionally with extra query args.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function section_url( array $args = array() ) {
		$dashboard_url = Page_Finder::url_for_shortcode( Admin_Dashboard::SHORTCODE_TAG );
		$base          = $dashboard_url ? $dashboard_url : home_url( '/' );

		return add_query_arg( array_merge( array( 'section' => self::SECTION ), $args ), $base );
	}

	/**
	 * Display name for a user: first + last, else display_name.
	 *
	 * @param \WP_User|false $user User.
	 * @return string
	 */
	private static function user_name( $user ) {
		if ( ! $user ) {
			return '';
		}

		$name = trim( $user->first_name . ' ' . $user->last_name );

		return '' !== $name ? $name : $user->display_name;
	}

	/**
	 * Renders the section.
	 *
	 * @param Template_Loader $loader Template loader.
	 * @return string
	 */
	public static function section_html( Template_Loader $loader ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters and notices.
		$doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0;
		$rating    = isset( $_GET['rating'] ) ? absint( $_GET['rating'] ) : 0;
		$status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$page      = isset( $_GET['rpage'] ) ? max( 1, absint( $_GET['rpage'] ) ) : 1;
		$notice    = isset( $_GET['reviews_notice'] ) ? sanitize_key( wp_unslash( $_GET['reviews_notice'] ) ) : '';
		$share_id  = isset( $_GET['share_doctor'] ) ? absint( $_GET['share_doctor'] ) : 0;
		// phpcs:enable

		$statuses = Doctor_Reviews::statuses();
		$status   = array_key_exists( $status, $statuses ) ? $status : '';
		$rating   = $rating >= 1 && $rating <= 5 ? $rating : 0;

		$doctor_users = get_users(
			array(
				'role'    => Roles::DOCTOR_ROLE,
				'orderby' => 'display_name',
			)
		);

		$doctors = array();

		foreach ( $doctor_users as $user ) {
			$is_public = Appointments::is_public_doctor( $user->ID );

			$doctors[ $user->ID ] = array(
				'id'        => $user->ID,
				'name'      => self::user_name( $user ),
				'is_public' => $is_public,
				// A link only for a doctor whose profile people can open.
				'link'      => $is_public ? Review_Links::url( $user->ID ) : '',
			);
		}

		uasort(
			$doctors,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		$doctor_id = isset( $doctors[ $doctor_id ] ) ? $doctor_id : 0;

		if ( ! isset( $doctors[ $share_id ] ) ) {
			$share_id = $doctor_id;
		}

		$result = Doctor_Reviews::admin_query(
			array(
				'doctor_id' => $doctor_id,
				'rating'    => $rating,
				'status'    => $status,
				'page'      => $page,
				'per_page'  => self::PER_PAGE,
			)
		);

		$rows = array();

		foreach ( $result['rows'] as $row ) {
			$from_link = Doctor_Reviews::SOURCE_LINK === $row['source'];
			$patient   = $from_link ? false : get_userdata( (int) $row['patient_id'] );

			$rows[] = array(
				'id'          => (int) $row['id'],
				'doctor_name' => isset( $doctors[ (int) $row['doctor_id'] ] ) ? $doctors[ (int) $row['doctor_id'] ]['name'] : __( 'Removed doctor', 'doctor-ak-portal' ),
				'doctor_id'   => (int) $row['doctor_id'],
				'rating'      => (int) $row['rating'],
				'comment'     => (string) $row['comment'],
				'status'      => $row['status'],
				'from_link'   => $from_link,
				'reviewer'    => $from_link ? Doctor_Reviews::public_name( $row ) : ( $patient ? self::user_name( $patient ) : __( 'Deleted patient account', 'doctor-ak-portal' ) ),
				'contact'     => $from_link ? (string) $row['reviewer_contact'] : ( $patient ? $patient->user_email : '' ),
				'created_at'  => $row['created_at'],
				'edited'      => $row['updated_at'] !== $row['created_at'],
			);
		}

		$filters = array_filter(
			array(
				'doctor_id' => $doctor_id,
				'rating'    => $rating,
				'status'    => $status,
			)
		);

		return $loader->get_template(
			'dashboard/partials/admin-reviews.php',
			array(
				'rows'          => $rows,
				'total'         => $result['total'],
				'page'          => $page,
				'pages'         => max( 1, (int) ceil( $result['total'] / self::PER_PAGE ) ),
				'counts'        => Doctor_Reviews::status_counts(),
				'statuses'      => $statuses,
				'doctors'       => $doctors,
				'filters'       => array(
					'doctor_id' => $doctor_id,
					'rating'    => $rating,
					'status'    => $status,
				),
				'share_id'      => $share_id,
				'notice'        => $notice,
				'section_url'   => self::section_url(),
				// Where a moderation/reset form sends the admin back to.
				'return_url'    => self::section_url( array_merge( $filters, $page > 1 ? array( 'rpage' => $page ) : array() ) ),
				'filter_args'   => $filters,
				'action_url'    => admin_url( 'admin-post.php' ),
				'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Redirects back to the section (the posted return URL, if it's ours).
	 *
	 * @param array $args Extra query args.
	 * @return void
	 */
	private static function back( array $args ) {
		$fallback = self::section_url();
		$return   = isset( $_POST['return'] ) ? esc_url_raw( wp_unslash( $_POST['return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller.
		$return   = '' !== $return ? wp_validate_redirect( $return, $fallback ) : $fallback;

		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'reviews_notice', 'share_doctor' ), $return ) ), 303 );
		exit;
	}

	/**
	 * admin-post: publish, hide or show one review.
	 *
	 * @return void
	 */
	public static function handle_moderate() {
		if ( ! self::can_manage() || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'doctor-ak-portal' ), '', array( 'response' => 403 ) );
		}

		$review_id = isset( $_POST['review_id'] ) ? absint( $_POST['review_id'] ) : 0;
		$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! in_array( $status, array( Doctor_Reviews::STATUS_PUBLISHED, Doctor_Reviews::STATUS_HIDDEN ), true ) ) {
			self::back( array( 'reviews_notice' => 'error' ) );
		}

		$ok = Doctor_Reviews::set_status( $review_id, $status );

		self::back( array( 'reviews_notice' => $ok ? $status : 'error' ) );
	}

	/**
	 * admin-post: give a doctor a new review link (the old one stops working).
	 *
	 * @return void
	 */
	public static function handle_reset_link() {
		if ( ! self::can_manage() || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'doctor-ak-portal' ), '', array( 'response' => 403 ) );
		}

		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		$doctor    = $doctor_id > 0 ? get_userdata( $doctor_id ) : false;

		if ( ! $doctor || ! in_array( Roles::DOCTOR_ROLE, (array) $doctor->roles, true ) ) {
			self::back( array( 'reviews_notice' => 'error' ) );
		}

		Review_Links::reset( $doctor_id );

		self::back(
			array(
				'reviews_notice' => 'link_reset',
				'share_doctor'   => $doctor_id,
			)
		);
	}
}
