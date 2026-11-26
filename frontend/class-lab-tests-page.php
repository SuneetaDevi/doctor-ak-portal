<?php
/**
 * The public "Book a lab test" page ([lab_tests]) and the lab-test
 * promotions elsewhere on the site.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Lab_Orders;
use DoctorAKPortal\Includes\Lab_Tests;
use DoctorAKPortal\Includes\Labs;
use DoctorAKPortal\Includes\Notification_Center;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lab_Tests_Page
 *
 * Anyone can browse the tests partner labs offer, with prices, and pick
 * some; the page then compares the labs for exactly those tests and books
 * one (lab visit or home sample collection, a day and a time). Anyone
 * logged in can book (patients, doctors, staff — like services); a visitor
 * is sent to log in or register and brought back with the same tests still
 * picked. A booking made here is a normal lab order (no doctor or visit
 * behind it), handled by staff in Dashboard → Lab Tests and followed by a
 * patient in their own Lab Tests tab. The page is always linked; until labs
 * have priced tests it says booking is coming soon.
 */
class Lab_Tests_Page {

	const SHORTCODE_TAG = 'lab_tests';
	const NONCE_ACTION  = 'doctor_ak_public_lab_book';

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Sets up collaborators.
	 *
	 * @param Template_Loader $template_loader Template loader.
	 */
	public function __construct( Template_Loader $template_loader ) {
		$this->template_loader = $template_loader;
	}

	/**
	 * Registers the shortcode (on init).
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render' ) );
	}

	/**
	 * The page's URL ('' only if the page itself doesn't exist). Always
	 * linked, like Services — before labs are set up the page says booking
	 * is coming soon and gives the phone number.
	 *
	 * @return string
	 */
	public static function public_url() {
		return Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
	}

	/**
	 * Headline figures for promotions (home page, menu): how many tests and
	 * labs, the lowest price, whether any lab collects at home, and a few
	 * popular tests with their lowest price. Null when nothing is bookable.
	 *
	 * @return array|null { tests, labs, from, home, popular: [ { id, name, from, sample, report } ] }
	 */
	public static function promo() {
		static $promo = false;

		if ( false !== $promo ) {
			return $promo;
		}

		$catalog = self::catalog();

		if ( empty( $catalog['tests'] ) ) {
			$promo = null;

			return $promo;
		}

		$popular = array_values(
			array_filter(
				$catalog['tests'],
				function ( $test ) {
					return $test['popular'];
				}
			)
		);

		if ( count( $popular ) < 4 ) {
			foreach ( $catalog['tests'] as $test ) {
				if ( count( $popular ) < 4 && ! $test['popular'] ) {
					$popular[] = $test;
				}
			}
		}

		$promo = array(
			'tests'   => count( $catalog['tests'] ),
			'labs'    => count( $catalog['labs'] ),
			'from'    => min( wp_list_pluck( $catalog['tests'], 'from' ) ),
			'home'    => in_array( true, wp_list_pluck( $catalog['labs'], 'home' ), true ),
			'popular' => array_map(
				function ( $test ) {
					return array(
						'id'     => $test['id'],
						'name'   => $test['name'],
						'from'   => $test['from'],
						'sample' => $test['sample'],
						'report' => $test['report'],
					);
				},
				array_slice( $popular, 0, 4 )
			),
		);

		return $promo;
	}

	/**
	 * Bookable tests (active, priced by at least one active lab) and the
	 * labs, shaped for the page's script.
	 *
	 * @return array { tests: [ { id, name, sample, prep, report, keywords, prices: { lab_id: price }, from, labs, popular } ], labs: [ { id, name, place, home, homeFee } ] }
	 */
	public static function catalog() {
		static $catalog = null;

		if ( null !== $catalog ) {
			return $catalog;
		}

		$matrix  = Labs::price_matrix();
		$popular = array_slice( Lab_Orders::popular_test_ids( 6 ), 0, 6 );
		$labs    = array();
		$tests   = array();

		foreach ( Labs::all( true ) as $lab ) {
			if ( empty( $matrix[ $lab['id'] ] ) ) {
				continue;
			}

			$labs[] = array(
				'id'      => $lab['id'],
				'name'    => $lab['name'],
				'place'   => implode( ', ', array_filter( array( $lab['address'], $lab['city'] ) ) ),
				'home'    => $lab['home_collection'],
				'homeFee' => $lab['home_collection_fee'],
			);
		}

		foreach ( Lab_Tests::all( true ) as $test ) {
			$prices = array();

			foreach ( $matrix as $lab_id => $lab_prices ) {
				if ( isset( $lab_prices[ $test['id'] ] ) ) {
					$prices[ $lab_id ] = $lab_prices[ $test['id'] ];
				}
			}

			if ( empty( $prices ) ) {
				continue; // No lab offers it yet.
			}

			$tests[] = array(
				'id'       => $test['id'],
				'name'     => $test['name'],
				'sample'   => $test['sample_type_label'],
				'prep'     => $test['preparation'],
				'report'   => $test['report_time'],
				'keywords' => $test['keywords'],
				'prices'   => $prices,
				'from'     => min( $prices ),
				'labs'     => count( $prices ),
				'popular'  => in_array( $test['id'], $popular, true ),
			);
		}

		$catalog = array(
			'tests' => $tests,
			'labs'  => $labs,
		);

		return $catalog;
	}

	/**
	 * Enqueues the page's assets — only where the shortcode is used.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) || ! has_shortcode( $post->post_content, self::SHORTCODE_TAG ) ) {
			return;
		}

		wp_enqueue_style( 'doctor-ak-portal-auth', DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css', array(), Assets::version( 'assets/css/doctor-ak-auth.css' ) );
		wp_enqueue_style( 'doctor-ak-portal-lab-tests', DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-lab-tests.css', array( 'doctor-ak-portal-auth' ), Assets::version( 'assets/css/doctor-ak-lab-tests.css' ) );
		wp_enqueue_script( 'doctor-ak-portal-lab-tests', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-lab-tests-page.js', array(), Assets::version( 'assets/js/doctor-ak-lab-tests-page.js' ), true );

		wp_localize_script(
			'doctor-ak-portal-lab-tests',
			'dakLabsPage',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'genericError' => __( 'Something went wrong. Please try again.', 'doctor-ak-portal' ),
				'labels'       => array(
					'add'          => __( 'Add', 'doctor-ak-portal' ),
					'added'        => __( 'Added', 'doctor-ak-portal' ),
					/* translators: %d: number of tests. */
					'tests'        => __( '%d tests', 'doctor-ak-portal' ),
					'test'         => __( '1 test', 'doctor-ak-portal' ),
					/* translators: %s: price. */
					'from'         => __( 'from %s', 'doctor-ak-portal' ),
					/* translators: %d: number of labs. */
					'labs'         => __( '%d labs', 'doctor-ak-portal' ),
					'lab'          => __( '1 lab', 'doctor-ak-portal' ),
					'lowest'       => __( 'Lowest price', 'doctor-ak-portal' ),
					/* translators: %s: home collection fee. */
					'homeFee'      => __( 'Home collection +%s', 'doctor-ak-portal' ),
					'homeFree'     => __( 'Free home collection', 'doctor-ak-portal' ),
					'labOnly'      => __( 'Lab visit only', 'doctor-ak-portal' ),
					/* translators: %s: comma-separated test names. */
					'missing'      => __( 'Doesn’t offer: %s', 'doctor-ak-portal' ),
					'noLab'        => __( 'No single lab offers all of these tests. Remove one to see labs that do.', 'doctor-ak-portal' ),
					'booking'      => __( 'Booking…', 'doctor-ak-portal' ),
					'noMatch'      => __( 'No tests match your search.', 'doctor-ak-portal' ),
				),
			)
		);
	}

	/**
	 * Renders the page.
	 *
	 * @return string
	 */
	public function render() {
		$user    = wp_get_current_user();
		$picked  = isset( $_GET['tests'] ) ? array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['tests'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preselection.

		// Anyone logged in can book (patients, doctors, staff) — like services.
		$viewer = $user->ID ? 'member' : 'guest';

		$page_url  = Page_Finder::url_for_shortcode( self::SHORTCODE_TAG );
		$login_url = Page_Finder::url_for_shortcode( 'doctor_login' );

		return $this->template_loader->get_template(
			'directory/lab-tests-page.php',
			array(
				'catalog'       => self::catalog(),
				'promo'         => self::promo(),
				'picked'        => array_values( array_filter( $picked ) ),
				'viewer'        => $viewer,
				'page_url'      => $page_url,
				'login_url'     => $login_url,
				'days'          => Patient_Lab_Handler::booking_days(),
				'windows'       => Lab_Orders::time_windows(),
				'hour'          => (int) current_time( 'G' ),
				'saved_address' => $user->ID ? (string) get_user_meta( $user->ID, Lab_Orders::ADDRESS_META_KEY, true ) : '',
				'phone'         => \DoctorAKPortal\Includes\Clinic_Locations::website_contact()['phone'],
			)
		);
	}

	/**
	 * AJAX (logged-in): a patient books the tests they picked.
	 *
	 * @return void
	 */
	public function handle_book() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$user = wp_get_current_user();

		if ( ! $user->ID ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to book.', 'doctor-ak-portal' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$test_ids = isset( $_POST['tests'] ) ? array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['tests'] ) ) ) ) : array();
		$order_id = Lab_Orders::create_for_patient( $user->ID, $test_ids );

		if ( is_wp_error( $order_id ) ) {
			wp_send_json_error( array( 'message' => $order_id->get_error_message() ) );
		}

		$result = Lab_Orders::book(
			$order_id,
			$user->ID,
			array(
				'lab_id'      => isset( $_POST['lab_id'] ) ? absint( wp_unslash( $_POST['lab_id'] ) ) : 0,
				'collection'  => isset( $_POST['collection'] ) ? sanitize_key( wp_unslash( $_POST['collection'] ) ) : 'lab',
				'date'        => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
				'time_window' => isset( $_POST['time_window'] ) ? sanitize_key( wp_unslash( $_POST['time_window'] ) ) : '',
				'address'     => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
			)
		);
		// phpcs:enable

		if ( is_wp_error( $result ) ) {
			Lab_Orders::delete( $order_id );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		Notification_Center::notify_lab_event( Lab_Orders::find( $order_id ), 'booked' );

		// Patients follow it in their portal's Lab Tests tab; staff see it in
		// Dashboard → Lab Tests; anyone else stays here with a confirmation.
		$redirect = '';

		if ( in_array( Roles::PATIENT_ROLE, (array) $user->roles, true ) ) {
			$dashboard = Page_Finder::url_for_shortcode( 'patient_dashboard' );
			$redirect  = $dashboard ? add_query_arg( 'tab', 'lab-tests', $dashboard ) : '';
		} elseif ( Admin_Labs::can_manage() ) {
			$redirect = Admin_Labs::section_url();
		}

		wp_send_json_success(
			array(
				'redirect' => $redirect,
				'message'  => __( 'Booked! We’ll see you then — pay at collection.', 'doctor-ak-portal' ),
			)
		);
	}
}
