<?php
/**
 * Stores "can't find your specialization?" requests submitted by doctors.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Specialization_Request
 *
 * Version 1 simply records the request as a post so it is visible in
 * wp-admin for manual follow-up. A later phase will turn this into a full
 * admin approval workflow that promotes approved requests into the
 * Specializations list.
 */
class Specialization_Request {

	/**
	 * Custom post type slug the requests are stored as.
	 *
	 * @var string
	 */
	const POST_TYPE = 'dak_spec_request';

	/**
	 * Registers the (intentionally non-public) post type used for storage.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Specialization Requests', 'doctor-ak-portal' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'capability_type' => 'post',
				'supports'        => array( 'title' ),
			)
		);
	}

	/**
	 * Saves a new specialization request.
	 *
	 * @param string $specialization_name Free-text specialization name requested.
	 * @param string $requester_email     Optional email address to follow up with.
	 * @return int|\WP_Error New request post ID, or WP_Error on failure.
	 */
	public static function create( $specialization_name, $requester_email = '' ) {
		$specialization_name = sanitize_text_field( $specialization_name );

		if ( '' === $specialization_name ) {
			return new \WP_Error( 'doctor_ak_empty_specialization_request', __( 'Please describe the specialization you are looking for.', 'doctor-ak-portal' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $specialization_name,
				'post_status' => 'pending',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( '' !== $requester_email && is_email( $requester_email ) ) {
			update_post_meta( $post_id, 'requester_email', sanitize_email( $requester_email ) );
		}

		return $post_id;
	}
}
