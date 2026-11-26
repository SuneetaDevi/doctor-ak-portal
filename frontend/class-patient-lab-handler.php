<?php
/**
 * The patient's "Lab tests" tab: what the doctor suggested, booking it, and
 * the results.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Lab_Orders;
use DoctorAKPortal\Includes\Lab_Tests;
use DoctorAKPortal\Includes\Labs;
use DoctorAKPortal\Includes\Notification_Center;
use DoctorAKPortal\Includes\Roles;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Patient_Lab_Handler
 *
 * Builds the tab's view model (Patient_Dashboard renders it) and handles
 * the two things a patient does there: book suggested tests (one request,
 * with sensible choices already made) and cancel a booking before the
 * sample is collected. A patient only ever sees and changes their own
 * orders.
 */
class Patient_Lab_Handler {

	const NONCE_ACTION = 'doctor_ak_patient_labs';

	/**
	 * Everything the tab shows for a patient.
	 *
	 * @param int $patient_id Patient user ID.
	 * @return array { orders: [...], days: [ { value, label } ], windows: slug => label, hour: int, saved_address: string }
	 */
	public static function view_model( $patient_id ) {
		$orders   = Lab_Orders::for_patient( $patient_id );
		$test_ids = array();

		foreach ( $orders as $order ) {
			foreach ( $order['items'] as $item ) {
				$test_ids[] = $item['test_id'];
			}
		}

		$tests = Lab_Tests::find_many( $test_ids );

		foreach ( $orders as &$order ) {
			$doctor              = get_userdata( $order['doctor_id'] );
			$order['doctor_name'] = $doctor ? ( trim( $doctor->first_name . ' ' . $doctor->last_name ) ?: $doctor->display_name ) : '';
			$order['date_label']  = date_i18n( 'j M Y', strtotime( $order['created_at'] ) );

			// Test details for the patient (sample, how to prepare, report time).
			foreach ( $order['items'] as &$item ) {
				$test                = isset( $tests[ $item['test_id'] ] ) ? $tests[ $item['test_id'] ] : null;
				$item['sample']      = $test ? $test['sample_type_label'] : '';
				$item['preparation'] = $test ? $test['preparation'] : '';
				$item['report_time'] = $test ? $test['report_time'] : '';
			}
			unset( $item );

			$order['quotes'] = array();

			if ( Lab_Orders::STATUS_SUGGESTED === $order['status'] ) {
				foreach ( Labs::quotes_for_tests( wp_list_pluck( $order['items'], 'test_id' ) ) as $quote ) {
					$missing = array();

					foreach ( $order['items'] as $item ) {
						if ( in_array( $item['test_id'], $quote['missing'], true ) ) {
							$missing[] = $item['test_name'];
						}
					}

					$order['quotes'][] = array(
						'lab_id'     => $quote['lab']['id'],
						'name'       => $quote['lab']['name'],
						'place'      => implode( ', ', array_filter( array( $quote['lab']['address'], $quote['lab']['city'] ) ) ),
						'covers_all' => $quote['covers_all'],
						'missing'    => $missing,
						'subtotal'   => $quote['subtotal'],
						'home'       => $quote['lab']['home_collection'],
						'home_fee'   => $quote['lab']['home_collection_fee'],
					);
				}
			}

			$lab                  = $order['lab_id'] > 0 ? Labs::find( $order['lab_id'] ) : null;
			$order['lab_name']    = $lab ? $lab['name'] : '';
			$order['lab_place']   = $lab ? implode( ', ', array_filter( array( $lab['address'], $lab['city'] ) ) ) : '';
			$order['visit_label'] = $order['preferred_date'] ? date_i18n( 'l, j M', strtotime( $order['preferred_date'] ) ) : '';
		}
		unset( $order );

		return array(
			'orders'        => $orders,
			'days'          => self::booking_days(),
			'windows'       => Lab_Orders::time_windows(),
			'hour'          => (int) current_time( 'G' ),
			'saved_address' => (string) get_user_meta( $patient_id, Lab_Orders::ADDRESS_META_KEY, true ),
		);
	}

	/**
	 * The next 7 bookable days; today only while a window is still open
	 * (before 8 pm). Shared with the public "Book a lab test" page.
	 *
	 * @return array List of { value 'Y-m-d', label, isToday }.
	 */
	public static function booking_days() {
		$today = current_time( 'Y-m-d' );
		$hour  = (int) current_time( 'G' );
		$days  = array();

		for ( $offset = $hour >= 20 ? 1 : 0; count( $days ) < 7; $offset++ ) {
			$date = gmdate( 'Y-m-d', strtotime( $today . ' +' . $offset . ' days' ) );

			if ( 0 === $offset ) {
				$label = __( 'Today', 'doctor-ak-portal' );
			} elseif ( 1 === $offset ) {
				$label = __( 'Tomorrow', 'doctor-ak-portal' );
			} else {
				$label = date_i18n( 'D j M', strtotime( $date ) );
			}

			$days[] = array(
				'value'   => $date,
				'label'   => $label,
				'isToday' => 0 === $offset,
			);
		}

		return $days;
	}

	/**
	 * The logged-in patient, or a JSON error.
	 *
	 * @return \WP_User
	 */
	private static function patient_from_request() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$user = wp_get_current_user();

		if ( ! $user->ID || ! in_array( Roles::PATIENT_ROLE, (array) $user->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'doctor-ak-portal' ) ), 403 );
		}

		return $user;
	}

	/**
	 * AJAX: books suggested tests.
	 *
	 * @return void
	 */
	public function handle_book() {
		$user     = self::patient_from_request();
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in patient_from_request().

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in patient_from_request().
		$result = Lab_Orders::book(
			$order_id,
			$user->ID,
			array(
				'lab_id'      => isset( $_POST['lab_id'] ) ? absint( wp_unslash( $_POST['lab_id'] ) ) : 0,
				'collection'  => isset( $_POST['collection'] ) ? sanitize_key( wp_unslash( $_POST['collection'] ) ) : 'lab',
				'date'        => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
				'time_window' => isset( $_POST['time_window'] ) ? sanitize_key( wp_unslash( $_POST['time_window'] ) ) : '',
				'address'     => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
				'notes'       => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			)
		);
		// phpcs:enable

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		Notification_Center::notify_lab_event( Lab_Orders::find( $order_id ), 'booked' );

		wp_send_json_success( array( 'message' => __( 'Booked! We’ll see you then — pay at collection.', 'doctor-ak-portal' ) ) );
	}

	/**
	 * AJAX: cancels a booking (back to "to book").
	 *
	 * @return void
	 */
	public function handle_cancel() {
		$user     = self::patient_from_request();
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in patient_from_request().
		$result   = Lab_Orders::cancel_booking( $order_id, $user->ID );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Booking cancelled. You can book again any time.', 'doctor-ak-portal' ) ) );
	}
}
