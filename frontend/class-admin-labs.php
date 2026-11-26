<?php
/**
 * Dashboard → Lab Tests: lab orders, partner labs and their prices, and the
 * test catalog.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Encounter_Report_Uploader;
use DoctorAKPortal\Includes\Lab_Orders;
use DoctorAKPortal\Includes\Lab_Tests;
use DoctorAKPortal\Includes\Labs;
use DoctorAKPortal\Includes\Notification_Center;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Labs
 *
 * Administrators and receptionists (on by default for receptionists, who
 * run the front desk; switchable in Roles & Permissions). Orders: mark the
 * sample collected, record payment, upload the results (the patient and
 * doctor are told), cancel. Labs: name, place, home collection and fee, and
 * the lab's price for each test. Tests: the catalog doctors pick from.
 *
 * Every change is a plain form POST to admin-post.php that redirects back
 * with a notice.
 */
class Admin_Labs {

	const SECTION       = 'lab-tests';
	const NONCE_ACTION  = 'doctor_ak_admin_labs';
	const ORDER_ACTION  = 'doctor_ak_lab_order_action';
	const UPLOAD_ACTION = 'doctor_ak_lab_results_upload';
	const LAB_ACTION    = 'doctor_ak_lab_save';
	const TEST_ACTION   = 'doctor_ak_lab_test_save';
	const PER_PAGE      = 20;

	/**
	 * Whether the current user may use this section.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		if ( current_user_can( 'manage_options' ) ) {
			return Role_Permissions::is_tab_allowed( Role_Permissions::ADMIN_KEY, self::SECTION );
		}

		return Admin_Dashboard::staff_can( self::SECTION );
	}

	/**
	 * The section's URL with extra query args.
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
	 * How many bookings are waiting for their sample (menu badge).
	 *
	 * @return int
	 */
	public static function badge_count() {
		$counts = Lab_Orders::status_counts();

		return $counts[ Lab_Orders::STATUS_BOOKED ];
	}

	/**
	 * Renders the section.
	 *
	 * @param Template_Loader $loader Template loader.
	 * @return string
	 */
	public static function section_html( Template_Loader $loader ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only navigation, filters and notices.
		$view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'orders';
		$notice = isset( $_GET['labs_notice'] ) ? sanitize_key( wp_unslash( $_GET['labs_notice'] ) ) : '';
		$error  = isset( $_GET['labs_error'] ) ? sanitize_text_field( wp_unslash( $_GET['labs_error'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : Lab_Orders::STATUS_BOOKED;
		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$page   = isset( $_GET['lpage'] ) ? max( 1, absint( $_GET['lpage'] ) ) : 1;
		// phpcs:enable

		$view = in_array( $view, array( 'orders', 'labs', 'tests', 'lab', 'test' ), true ) ? $view : 'orders';
		$data = array(
			'view'         => $view,
			'notice'       => $notice,
			'error'        => $error,
			'nonce_action' => self::NONCE_ACTION,
			'post_url'     => admin_url( 'admin-post.php' ),
			'urls'         => array(
				'orders' => self::section_url(),
				'labs'   => self::section_url( array( 'view' => 'labs' ) ),
				'tests'  => self::section_url( array( 'view' => 'tests' ) ),
			),
			'actions'      => array(
				'order'  => self::ORDER_ACTION,
				'upload' => self::UPLOAD_ACTION,
				'lab'    => self::LAB_ACTION,
				'test'   => self::TEST_ACTION,
			),
			'counts'       => Lab_Orders::status_counts(),
		);

		if ( 'orders' === $view ) {
			$status = array_key_exists( $status, Lab_Orders::statuses() ) || 'all' === $status ? $status : Lab_Orders::STATUS_BOOKED;
			$result = Lab_Orders::admin_query(
				array(
					'status'   => 'all' === $status ? '' : $status,
					'search'   => $search,
					'page'     => $page,
					'per_page' => self::PER_PAGE,
				)
			);

			$labs = array();

			foreach ( Labs::all() as $lab ) {
				$labs[ $lab['id'] ] = $lab;
			}

			$data['status']   = $status;
			$data['search']   = $search;
			$data['page']     = $page;
			$data['pages']    = max( 1, (int) ceil( $result['total'] / self::PER_PAGE ) );
			$data['total']    = $result['total'];
			$data['orders']   = array_map(
				function ( $order ) use ( $labs ) {
					$patient = get_userdata( $order['patient_id'] );
					$doctor  = get_userdata( $order['doctor_id'] );

					$order['patient_name']  = $patient ? ( trim( $patient->first_name . ' ' . $patient->last_name ) ?: $patient->display_name ) : __( 'Unknown patient', 'doctor-ak-portal' );
					$order['patient_phone'] = $patient ? (string) get_user_meta( $patient->ID, 'doctor_ak_phone_number', true ) : '';
					$order['doctor_name']   = $doctor ? ( trim( $doctor->first_name . ' ' . $doctor->last_name ) ?: $doctor->display_name ) : '';
					$order['lab_name']      = isset( $labs[ $order['lab_id'] ] ) ? $labs[ $order['lab_id'] ]['name'] : '';
					$order['visit_label']   = $order['preferred_date'] ? date_i18n( 'D, j M', strtotime( $order['preferred_date'] ) ) : '';

					return $order;
				},
				$result['rows']
			);
		} elseif ( 'labs' === $view ) {
			$data['labs']    = Labs::all();
			$data['offered'] = Labs::offered_counts();
		} elseif ( 'tests' === $view ) {
			$data['tests'] = Lab_Tests::all();
		} elseif ( 'lab' === $view ) {
			$data['lab']    = $id > 0 ? Labs::find( $id ) : null;
			$data['prices'] = $data['lab'] ? Labs::prices_for_lab( $id ) : array();
			$data['tests']  = Lab_Tests::all();
		} elseif ( 'test' === $view ) {
			$data['test']         = $id > 0 ? Lab_Tests::find( $id ) : null;
			$data['sample_types'] = Lab_Tests::sample_types();
		}

		return $loader->get_template( 'dashboard/partials/admin-lab-tests.php', $data );
	}

	/**
	 * Common checks for every POST: nonce and permission.
	 *
	 * @return void
	 */
	private static function guard() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Your session has expired. Please go back and try again.', 'doctor-ak-portal' ) );
		}

		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'doctor-ak-portal' ) );
		}
	}

	/**
	 * Redirects back to the section.
	 *
	 * @param array           $args   Query args (view, status, …).
	 * @param string          $notice Notice slug.
	 * @param \WP_Error|null  $error  Error to show instead.
	 * @return void
	 */
	private static function back( array $args, $notice, $error = null ) {
		if ( $error instanceof \WP_Error ) {
			$args['labs_error'] = rawurlencode( $error->get_error_message() );
		} else {
			$args['labs_notice'] = $notice;
		}

		wp_safe_redirect( self::section_url( $args ) );
		exit;
	}

	/**
	 * Where to return after an order action: the list the staff member was on.
	 *
	 * @return array
	 */
	private static function return_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$args = array();

		if ( ! empty( $_POST['return_status'] ) ) {
			$args['status'] = sanitize_key( wp_unslash( $_POST['return_status'] ) );
		}

		if ( ! empty( $_POST['return_search'] ) ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_POST['return_search'] ) );
		}
		// phpcs:enable

		return $args;
	}

	/**
	 * POST: collected / paid / cancel on an order.
	 *
	 * @return void
	 */
	public static function handle_order_action() {
		self::guard();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		// phpcs:enable

		if ( 'paid' === $do ) {
			Lab_Orders::mark_paid( $order_id );
			self::back( self::return_args(), 'paid' );
		}

		$result = Lab_Orders::set_status( $order_id, 'collected' === $do ? Lab_Orders::STATUS_COLLECTED : Lab_Orders::STATUS_CANCELLED );

		self::back( self::return_args(), 'collected' === $do ? 'collected' : 'cancelled', is_wp_error( $result ) ? $result : null );
	}

	/**
	 * POST (multipart): attaches the results file and tells the patient and
	 * doctor.
	 *
	 * @return void
	 */
	public static function handle_upload_results() {
		self::guard();

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$order    = Lab_Orders::find( $order_id );

		if ( ! $order ) {
			self::back( self::return_args(), '', new \WP_Error( 'doctor_ak_lab_order_not_found', __( 'That lab order could not be found.', 'doctor-ak-portal' ) ) );
		}

		$file = isset( $_FILES['results'] ) && is_array( $_FILES['results'] ) ? $_FILES['results'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by the uploader.
		$id   = ( new Encounter_Report_Uploader() )->upload( $file, $order['patient_id'] );

		if ( is_wp_error( $id ) ) {
			self::back( self::return_args(), '', $id );
		}

		$result = Lab_Orders::attach_results( $order_id, $id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			self::back( self::return_args(), '', $result );
		}

		Notification_Center::notify_lab_event( Lab_Orders::find( $order_id ), 'ready' );

		self::back( self::return_args(), 'uploaded' );
	}

	/**
	 * POST: adds or updates a lab and its price list.
	 *
	 * @return void
	 */
	public static function handle_save_lab() {
		self::guard();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$fields = Labs::sanitize_from_request( $_POST );
		$prices = Labs::sanitize_prices( isset( $_POST['prices'] ) ? $_POST['prices'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by sanitize_prices().
		// phpcs:enable

		if ( is_wp_error( $fields ) ) {
			self::back( array( 'view' => 'lab', 'id' => $id ), '', $fields );
		}

		if ( $id > 0 && Labs::find( $id ) ) {
			Labs::update( $id, $fields );
		} else {
			$id = Labs::create( $fields );
		}

		Labs::set_prices( $id, $prices );

		self::back( array( 'view' => 'labs' ), 'lab_saved' );
	}

	/**
	 * POST: adds or updates a test.
	 *
	 * @return void
	 */
	public static function handle_save_test() {
		self::guard();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$fields = Lab_Tests::sanitize_from_request( $_POST );
		// phpcs:enable

		if ( is_wp_error( $fields ) ) {
			self::back( array( 'view' => 'test', 'id' => $id ), '', $fields );
		}

		$result = $id > 0 && Lab_Tests::find( $id ) ? Lab_Tests::update( $id, $fields ) : Lab_Tests::create( $fields );

		if ( is_wp_error( $result ) ) {
			self::back( array( 'view' => 'test', 'id' => $id ), '', $result );
		}

		self::back( array( 'view' => 'tests' ), 'test_saved' );
	}
}
