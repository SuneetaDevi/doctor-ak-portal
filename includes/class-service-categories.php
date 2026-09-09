<?php
/**
 * Admin-managed list of Service categories, used to classify Services rows
 * for the public site's Services mega-menu and the [services_directory]/
 * [service_profile_view] pages.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service_Categories
 *
 * Started life as a hardcoded 6-entry list; now an admin-managed one, stored
 * as a single wp_option (a small, ordered list — a full DB table would be
 * overkill for what's just a slug + label pair). Mirrors Specializations'
 * get_all()/is_valid() shape so Services::sanitize_fields_from_request()/
 * decode_row() can keep validating/labelling a service's category the same
 * way. The last entry the option ever ships with ("Miscellaneous/Other
 * Services", see FALLBACK_SLUG) is the header mega-menu's catch-all bucket
 * (see Services::grouped_by_category_for_public_directory()) and can never
 * be deleted, so grouping always has somewhere to land.
 */
class Service_Categories {

	/**
	 * wp_options key holding the full ordered list of { slug, label, protected }.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'doctor_ak_service_categories';

	/**
	 * The permanent catch-all category slug — see the class docblock.
	 *
	 * @var string
	 */
	const FALLBACK_SLUG = 'miscellaneous-other-services';

	/**
	 * Returns the full category list as slug => label, in display order.
	 *
	 * @return array
	 */
	public static function get_all() {
		$categories = array();

		foreach ( self::get_rows() as $row ) {
			$categories[ $row['slug'] ] = $row['label'];
		}

		return $categories;
	}

	/**
	 * Returns the full category list with its admin-only metadata (whether
	 * each entry can be deleted), for the admin "Categories" screen.
	 *
	 * @return array List of { slug, label, protected }.
	 */
	public static function get_rows() {
		$rows = get_option( self::OPTION_NAME, null );

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			$rows = self::default_rows();
			update_option( self::OPTION_NAME, $rows );
		}

		return $rows;
	}

	/**
	 * Checks whether the given slug is a recognised category.
	 *
	 * @param string $slug Category slug.
	 * @return bool
	 */
	public static function is_valid( $slug ) {
		return array_key_exists( $slug, self::get_all() );
	}

	/**
	 * The catch-all category slug a service with no (or an unrecognised —
	 * e.g. left over from a since-deleted category) category falls into for
	 * grouping purposes.
	 *
	 * @return string
	 */
	public static function fallback_slug() {
		return self::FALLBACK_SLUG;
	}

	/**
	 * Adds a new category, for the admin "Categories" screen.
	 *
	 * @param string $label Category name, as typed by the admin.
	 * @return string|\WP_Error The new category's slug, or WP_Error on invalid/duplicate input.
	 */
	public static function add( $label ) {
		$label = sanitize_text_field( $label );

		if ( '' === $label ) {
			return new \WP_Error( 'doctor_ak_service_category_label_required', __( 'Please provide a name for this category.', 'doctor-ak-portal' ) );
		}

		$slug = sanitize_title( $label );

		if ( '' === $slug ) {
			return new \WP_Error( 'doctor_ak_service_category_label_invalid', __( 'Please provide a valid category name.', 'doctor-ak-portal' ) );
		}

		$rows = self::get_rows();

		foreach ( $rows as $row ) {
			if ( $row['slug'] === $slug ) {
				return new \WP_Error( 'doctor_ak_service_category_exists', __( 'A category with this name already exists.', 'doctor-ak-portal' ) );
			}
		}

		// New categories join ahead of the "Miscellaneous/Other Services"
		// catch-all, which always stays last (see FALLBACK_SLUG) — the
		// invariant add()/get_rows() rely on to keep the fallback at the
		// end without re-sorting the whole list on every save.
		$fallback_row = array_pop( $rows );
		$rows[]       = array(
			'slug'      => $slug,
			'label'     => $label,
			'protected' => false,
		);
		$rows[]       = $fallback_row;

		update_option( self::OPTION_NAME, $rows );

		return $slug;
	}

	/**
	 * Deletes a category, for the admin "Categories" screen. Services
	 * already tagged with it keep their (now unrecognised) category value —
	 * they simply fall into the fallback bucket for grouping purposes, same
	 * as any other "deleted the thing a foreign key pointed at" case in this
	 * codebase (see Clinic_Locations::delete()).
	 *
	 * @param string $slug Category slug.
	 * @return true|\WP_Error
	 */
	public static function delete( $slug ) {
		$rows  = self::get_rows();
		$kept  = array();
		$found = false;

		foreach ( $rows as $row ) {
			if ( $row['slug'] === $slug ) {
				if ( ! empty( $row['protected'] ) ) {
					return new \WP_Error( 'doctor_ak_service_category_protected', __( "This category can't be deleted.", 'doctor-ak-portal' ) );
				}

				$found = true;
				continue;
			}

			$kept[] = $row;
		}

		if ( ! $found ) {
			return new \WP_Error( 'doctor_ak_service_category_not_found', __( 'That category no longer exists.', 'doctor-ak-portal' ) );
		}

		update_option( self::OPTION_NAME, $kept );

		return true;
	}

	/**
	 * The list this option seeds itself with the first time it's read —
	 * the plugin's original hardcoded 6 categories. Slugs are spelled out
	 * explicitly (rather than computed from the translated label, as the
	 * labels below are) so they stay stable across locales/installs and
	 * keep matching whatever's already stored on existing Services rows.
	 *
	 * @return array List of { slug, label, protected }.
	 */
	private static function default_rows() {
		return array(
			array(
				'slug'      => 'surgeries-and-procedures',
				'label'     => __( 'Surgeries and Procedures', 'doctor-ak-portal' ),
				'protected' => false,
			),
			array(
				'slug'      => 'pharmacy',
				'label'     => __( 'Pharmacy', 'doctor-ak-portal' ),
				'protected' => false,
			),
			array(
				'slug'      => 'labs',
				'label'     => __( 'Labs', 'doctor-ak-portal' ),
				'protected' => false,
			),
			array(
				'slug'      => 'nursing',
				'label'     => __( 'Nursing', 'doctor-ak-portal' ),
				'protected' => false,
			),
			array(
				'slug'      => 'second-opinion-services',
				'label'     => __( 'Second Opinion Services', 'doctor-ak-portal' ),
				'protected' => false,
			),
			array(
				'slug'      => self::FALLBACK_SLUG,
				'label'     => __( 'Miscellaneous/Other Services', 'doctor-ak-portal' ),
				'protected' => true,
			),
		);
	}
}
