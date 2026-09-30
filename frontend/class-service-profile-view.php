<?php
/**
 * Backs the [service_profile_view] shortcode.
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Clinics;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Services;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Profile_View
 *
 * A public, read-only detail page for one service NAME — reached via
 * `?service_id=` (any one Services row with that name) on whichever page
 * contains [service_profile_view] (found dynamically by Page_Finder, same
 * pattern as Doctor_Profile_View). A service added for several doctors at
 * once (see Service_Handler's bulk-create) is really one portfolio entry
 * with several doctor-owned rows — this page looks up every one of them
 * (Services::active_rows_by_name()) and shows a "Doctors & Pricing"
 * breakdown, each with its own price, clinics, and a "Book with Dr. X"
 * link into the normal booking flow.
 */
class Service_Profile_View {

	/**
	 * Shortcode tag this controller backs.
	 *
	 * @var string
	 */
	const SHORTCODE_TAG = 'service_profile_view';

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
	 * Enqueues assets only on pages containing [service_profile_view].
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_profile_view_page() ) {
			return;
		}

		wp_enqueue_style(
			'doctor-ak-portal-auth',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-auth.css',
			array(),
			Assets::version( 'assets/css/doctor-ak-auth.css' )
		);

		wp_enqueue_style(
			'doctor-ak-portal-directory',
			DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-directory.css',
			array( 'doctor-ak-portal-auth' ),
			Assets::version( 'assets/css/doctor-ak-directory.css' )
		);

		wp_enqueue_script(
			'doctor-ak-portal-service-profile',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-service-profile.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-service-profile.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-service-profile',
			'dakServiceProfile',
			array(
				/* translators: %s: doctor's display name, e.g. "Dr. Jane Smith". Keep the literal %s — it's swapped for the name in JS. */
				'bookingWithLabel'     => __( 'Booking with %s.', 'doctor-ak-portal' ),
				'bookAppointmentLabel' => __( 'Book Appointment', 'doctor-ak-portal' ),
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( Service_Request_Handler::NONCE_ACTION ),
			)
		);

		// The "Request This Service" form (see service-profile-view.php),
		// shown instead of the doctor-picker/booking flow when the service
		// has requires_doctor = 0 — reads window.dakServiceProfile above.
		wp_enqueue_script(
			'doctor-ak-portal-service-request',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-service-request.js',
			array( 'doctor-ak-portal-service-profile' ),
			Assets::version( 'assets/js/doctor-ak-service-request.js' ),
			true
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$service_id = isset( $_GET['service_id'] ) ? absint( $_GET['service_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public lookup.
		$service    = $service_id > 0 ? Services::find_for_public_profile( $service_id ) : null;
		$group      = null;

		if ( $service ) {
			$rows                     = Services::active_rows_by_name( $service['name'] );
			$group                    = $this->build_group( $service['name'], $rows );
			$group['requires_doctor'] = $service['requires_doctor'];
			$group['service_id']      = $service['id'];
			$group['clinic_groups']   = $this->build_clinic_groups( $rows );
		}

		return $this->template_loader->get_template(
			'directory/service-profile-view.php',
			array(
				'group'         => $group,
				'directory_url' => Page_Finder::url_for_shortcode( 'services_directory' ),
			)
		);
	}

	/**
	 * Gathers every doctor-owned row for a service name into one page's
	 * worth of data: the shared name/description/image (from whichever row
	 * has them), an overall price range, and a "Book with Dr. X" offer per
	 * doctor.
	 *
	 * @param string $name Service name (from an already-verified active row).
	 * @param array  $rows Services::active_rows_by_name( $name ) — passed in rather than re-queried since build_clinic_groups() below needs the exact same rows.
	 * @return array
	 */
	private function build_group( $name, array $rows ) {
		$doctor_offers = array();
		$description   = '';
		$image_url     = '';
		$prices        = array();

		$base_booking_url = Page_Finder::url_for_shortcode( 'book_appointment' );

		foreach ( $rows as $row ) {
			$doctor = get_userdata( $row['doctor_id'] );

			if ( ! $doctor ) {
				continue;
			}

			if ( '' === $description && '' !== $row['description'] ) {
				$description = $row['description'];
			}

			if ( '' === $image_url && '' !== $row['image_url'] ) {
				$image_url = $row['image_url'];
			}

			$prices[] = $row['effective_price'];

			// A clinic id only gets added to the booking link below when this
			// doctor offers the service at exactly one clinic — with more
			// than one, there's genuinely no unambiguous choice to make for
			// the patient, so Selection still needs to show the clinic
			// picker in that case (see Booking_Page::resolved_selection()).
			//
			// $row['clinic_locations'] holds Clinic_Locations rows (the admin
			// master clinic list), but Booking_Page::resolved_selection()
			// validates a booking link's clinic_id against this doctor's own
			// Clinics rows instead — so the Clinic_Locations id has to be
			// translated to this doctor's matching Clinics row id via its
			// clinic_location_id foreign key before it's usable here.
			$dak_unambiguous_clinic_location_id = 1 === count( $row['clinic_locations'] ) ? (int) $row['clinic_locations'][0]['id'] : 0;
			$dak_unambiguous_clinic_id          = 0;

			if ( $dak_unambiguous_clinic_location_id > 0 ) {
				foreach ( Clinics::get_for_doctor( $doctor->ID ) as $doctor_clinic ) {
					if ( $dak_unambiguous_clinic_location_id === (int) $doctor_clinic['clinic_location_id'] ) {
						$dak_unambiguous_clinic_id = $doctor_clinic['id'];
						break;
					}
				}
			}

			$dak_booking_query_args = array(
				'doctor_id'  => $doctor->ID,
				'service_id' => $row['id'],
			);

			if ( $dak_unambiguous_clinic_id > 0 ) {
				$dak_booking_query_args['clinic_id'] = $dak_unambiguous_clinic_id;
			}

			$doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$doctor_name = '' !== $doctor_name ? $doctor_name : $doctor->display_name;

			// Location labels across this doctor's clinics for this
			// service, for the "Location" filter — a lightweight "near me"
			// stand-in that doesn't need geolocation.
			$location_labels = array_values(
				array_unique(
					array_filter(
						array_map(
							function ( $clinic_location ) {
								return isset( $clinic_location['area_label'] ) ? $clinic_location['area_label'] : '';
							},
							$row['clinic_locations']
						)
					)
				)
			);

			$doctor_offers[] = array(
				'doctor_id'          => $doctor->ID,
				'doctor_name'        => $doctor_name,
				'doctor_avatar_url'  => self::doctor_avatar_url( $doctor->ID ),
				'doctor_profile_url' => add_query_arg( 'doctor_id', $doctor->ID, Page_Finder::url_for_shortcode( 'doctor_profile_view' ) ),
				'price'              => $row['effective_price'],
				'price_label'        => $row['price_label'],
				'category'           => $row['category'],
				'category_label'     => $row['category_label'],
				'location_labels'    => $location_labels,
				'clinic_locations'   => $row['clinic_locations'],
				// Carries the exact service (and, when unambiguous, clinic)
				// the patient just picked here straight into the booking
				// wizard, so Selection can be skipped entirely (see
				// Booking_Page::resolved_selection()'s selection_fully_known)
				// straight to Identity/Payment.
				'booking_url'        => $base_booking_url ? add_query_arg( $dak_booking_query_args, $base_booking_url ) : '',
			);
		}

		// Sort doctors cheapest-first by default — matches
		// active_rows_by_name()'s own ORDER BY, kept explicit here since
		// the "Sort" control's default option relies on it.
		usort(
			$doctor_offers,
			function ( $a, $b ) {
				return $a['price'] <=> $b['price'];
			}
		);

		return array(
			'name'          => $name,
			'description'   => $description,
			'image_url'     => $image_url,
			'price_label'   => Services::price_range_label( $prices ),
			'doctor_offers' => $doctor_offers,
		);
	}

	/**
	 * The same rows build_group() uses, regrouped clinic-first instead of
	 * doctor-first: one entry per physical clinic offering this service,
	 * each with the doctors who offer it there (and that doctor's own price
	 * at that specific clinic) nested underneath — "Doctors & Pricing"
	 * organised the way a patient picking a convenient clinic actually
	 * thinks about it, rather than by doctor.
	 *
	 * A service row with no clinic-specific pricing set (Services::decode_row()'s
	 * 'clinic_locations' empty — the common case: offered at every clinic
	 * that doctor practises at, same as Doctor_Profile_View::clinic_fee_label())
	 * falls back to that doctor's own full physical-clinic list, at the
	 * service's flat price. A doctor with no physical clinic at all for
	 * this service (video-only, or a clinic row not linked to a shared
	 * Clinic_Locations entry) lands in a catch-all "Other Locations" group
	 * instead of being silently dropped.
	 *
	 * @param array $rows Services::active_rows_by_name() rows.
	 * @return array List of { label, meta, doctors: [ same shape as build_group()'s doctor_offers, minus location_labels/clinic_locations ] }, alphabetical by clinic label ("Other Locations" last).
	 */
	private function build_clinic_groups( array $rows ) {
		$groups             = array();
		$clinics_by_doctor  = array();
		$base_booking_url   = Page_Finder::url_for_shortcode( 'book_appointment' );
		$doctor_profile_url = Page_Finder::url_for_shortcode( 'doctor_profile_view' );

		foreach ( $rows as $row ) {
			$doctor = get_userdata( $row['doctor_id'] );

			if ( ! $doctor ) {
				continue;
			}

			if ( ! isset( $clinics_by_doctor[ $doctor->ID ] ) ) {
				$clinics_by_doctor[ $doctor->ID ] = Clinics::get_for_doctor( $doctor->ID );
			}

			$doctor_name = trim( $doctor->first_name . ' ' . $doctor->last_name );
			$doctor_name = '' !== $doctor_name ? $doctor_name : $doctor->display_name;

			// Every clinic this row should appear under, as
			// { group_key, label, meta, clinic_location_id (0 for the
			// catch-all), price, price_label }.
			$entries = array();

			if ( ! empty( $row['clinic_locations'] ) ) {
				foreach ( $row['clinic_locations'] as $dak_clinic_location ) {
					$entries[] = array(
						'group_key'          => 'loc:' . $dak_clinic_location['id'],
						'label'              => $dak_clinic_location['name'],
						'meta'               => implode( ', ', array_filter( array( $dak_clinic_location['address'], $dak_clinic_location['area_label'], $dak_clinic_location['city_label'] ) ) ),
						'clinic_location_id' => (int) $dak_clinic_location['id'],
						'price'              => $dak_clinic_location['price'],
						'price_label'        => $dak_clinic_location['price_label'],
					);
				}
			} else {
				foreach ( $clinics_by_doctor[ $doctor->ID ] as $dak_doctor_clinic ) {
					if ( Clinics::TYPE_PHYSICAL !== $dak_doctor_clinic['type'] ) {
						continue;
					}

					$dak_clinic_location_id = (int) $dak_doctor_clinic['clinic_location_id'];

					$entries[] = array(
						'group_key'          => $dak_clinic_location_id > 0 ? 'loc:' . $dak_clinic_location_id : 'clinic:' . $dak_doctor_clinic['id'],
						'label'              => $dak_doctor_clinic['name'],
						'meta'               => implode( ', ', array_filter( array( $dak_doctor_clinic['address'], $dak_doctor_clinic['area_label'], $dak_doctor_clinic['city_label'] ) ) ),
						'clinic_location_id' => $dak_clinic_location_id,
						'price'              => $row['effective_price'],
						'price_label'        => $row['price_label'],
					);
				}
			}

			if ( empty( $entries ) ) {
				$entries[] = array(
					'group_key'          => 'other',
					'label'              => __( 'Other Locations', 'doctor-ak-portal' ),
					'meta'               => '',
					'clinic_location_id' => 0,
					'price'              => $row['effective_price'],
					'price_label'        => $row['price_label'],
				);
			}

			foreach ( $entries as $dak_entry ) {
				if ( ! isset( $groups[ $dak_entry['group_key'] ] ) ) {
					$groups[ $dak_entry['group_key'] ] = array(
						'label'   => $dak_entry['label'],
						'meta'    => $dak_entry['meta'],
						'doctors' => array(),
					);
				}

				// A clinic id only gets added to the booking link when this
				// clinic has an unambiguous Clinics row for this doctor —
				// same "translate the shared Clinic_Locations id to this
				// doctor's own Clinics row id" need as build_group() above.
				$dak_clinic_id = 0;

				if ( $dak_entry['clinic_location_id'] > 0 ) {
					foreach ( $clinics_by_doctor[ $doctor->ID ] as $dak_doctor_clinic ) {
						if ( $dak_entry['clinic_location_id'] === (int) $dak_doctor_clinic['clinic_location_id'] ) {
							$dak_clinic_id = $dak_doctor_clinic['id'];
							break;
						}
					}
				}

				$dak_booking_query_args = array(
					'doctor_id'  => $doctor->ID,
					'service_id' => $row['id'],
				);

				if ( $dak_clinic_id > 0 ) {
					$dak_booking_query_args['clinic_id'] = $dak_clinic_id;
				}

				$groups[ $dak_entry['group_key'] ]['doctors'][] = array(
					'doctor_id'          => $doctor->ID,
					'doctor_name'        => $doctor_name,
					'doctor_avatar_url'  => self::doctor_avatar_url( $doctor->ID ),
					'doctor_profile_url' => $doctor_profile_url ? add_query_arg( 'doctor_id', $doctor->ID, $doctor_profile_url ) : '',
					'price'              => $dak_entry['price'],
					'price_label'        => $dak_entry['price_label'],
					'category'           => $row['category'],
					'category_label'     => $row['category_label'],
					'booking_url'        => $base_booking_url ? add_query_arg( $dak_booking_query_args, $base_booking_url ) : '',
				);
			}
		}

		foreach ( $groups as &$dak_group ) {
			usort(
				$dak_group['doctors'],
				function ( $a, $b ) {
					return $a['price'] <=> $b['price'];
				}
			);
		}
		unset( $dak_group );

		// Alphabetical by clinic name, with the catch-all "Other Locations"
		// bucket always last regardless of where its label would otherwise sort.
		$dak_other_label = __( 'Other Locations', 'doctor-ak-portal' );

		uasort(
			$groups,
			function ( $a, $b ) use ( $dak_other_label ) {
				$a_is_other = $dak_other_label === $a['label'];
				$b_is_other = $dak_other_label === $b['label'];

				if ( $a_is_other !== $b_is_other ) {
					return $a_is_other ? 1 : -1;
				}

				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		return array_values( $groups );
	}

	/**
	 * Resolves a doctor's uploaded profile picture, falling back to a
	 * generic avatar if they haven't uploaded one — same as
	 * Doctors_Directory/Doctor_Profile_View, for the "Provided by" card.
	 *
	 * @param int $doctor_id Doctor's user ID.
	 * @return string
	 */
	private static function doctor_avatar_url( $doctor_id ) {
		$picture_id = (int) get_user_meta( $doctor_id, 'doctor_ak_profile_picture_id', true );

		if ( $picture_id > 0 ) {
			$url = wp_get_attachment_image_url( $picture_id, 'medium' );

			if ( $url ) {
				return $url;
			}
		}

		return get_avatar_url( $doctor_id, array( 'size' => 200 ) );
	}

	/**
	 * Checks whether the current request is for a page containing the
	 * profile-view shortcode.
	 *
	 * @return bool
	 */
	private function is_profile_view_page() {
		global $post;

		return ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE_TAG );
	}
}
