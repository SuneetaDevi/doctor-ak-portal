<?php
/**
 * Static list of doctor specializations for Version 1.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Specializations
 *
 * Provides the fixed specialization list used by the doctor registration
 * form's multi-select. A future phase will replace this static list with
 * an admin-managed, approval-driven taxonomy.
 */
class Specializations {

	/**
	 * Returns the full specialization list as slug => label.
	 *
	 * @return array
	 */
	public static function get_all() {
		$labels = array(
			__( 'General Physician', 'doctor-ak-portal' ),
			__( 'Cardiologist', 'doctor-ak-portal' ),
			__( 'Dermatologist', 'doctor-ak-portal' ),
			__( 'Neurologist', 'doctor-ak-portal' ),
			__( 'Orthopedic Surgeon', 'doctor-ak-portal' ),
			__( 'ENT Specialist', 'doctor-ak-portal' ),
			__( 'Gynecologist', 'doctor-ak-portal' ),
			__( 'Obstetrician', 'doctor-ak-portal' ),
			__( 'Pediatrician', 'doctor-ak-portal' ),
			__( 'Psychiatrist', 'doctor-ak-portal' ),
			__( 'Psychologist', 'doctor-ak-portal' ),
			__( 'Dentist', 'doctor-ak-portal' ),
			__( 'Ophthalmologist', 'doctor-ak-portal' ),
			__( 'Urologist', 'doctor-ak-portal' ),
			__( 'Gastroenterologist', 'doctor-ak-portal' ),
			__( 'Pulmonologist', 'doctor-ak-portal' ),
			__( 'Endocrinologist', 'doctor-ak-portal' ),
			__( 'Nephrologist', 'doctor-ak-portal' ),
			__( 'Rheumatologist', 'doctor-ak-portal' ),
			__( 'Oncologist', 'doctor-ak-portal' ),
			__( 'General Surgeon', 'doctor-ak-portal' ),
			__( 'Plastic Surgeon', 'doctor-ak-portal' ),
			__( 'Neurosurgeon', 'doctor-ak-portal' ),
			__( 'Pediatric Surgeon', 'doctor-ak-portal' ),
			__( 'Physiotherapist', 'doctor-ak-portal' ),
			__( 'Nutritionist', 'doctor-ak-portal' ),
			__( 'Diabetologist', 'doctor-ak-portal' ),
			__( 'Family Medicine', 'doctor-ak-portal' ),
			__( 'Internal Medicine', 'doctor-ak-portal' ),
		);

		$specializations = array();

		foreach ( $labels as $label ) {
			$specializations[ sanitize_title( $label ) ] = $label;
		}

		return $specializations;
	}

	/**
	 * Checks whether the given slug is a recognised specialization.
	 *
	 * @param string $slug Specialization slug.
	 * @return bool
	 */
	public static function is_valid( $slug ) {
		return array_key_exists( $slug, self::get_all() );
	}
}
