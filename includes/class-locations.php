<?php
/**
 * Admin-managed Country -> City -> Area list used for doctor location
 * fields (profile-level and per-clinic) and the doctors directory's
 * location filter.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Locations
 *
 * Stored as a single wp_option (small, admin-curated list — not worth a
 * dedicated DB table): a list of countries, each with cities, each with
 * areas — `array( 'slug', 'name', 'cities' => array( array( 'slug', 'name',
 * 'areas' => array( array( 'slug', 'name' ) ) ) ) )`. Countries/cities/areas
 * are matched/stored by slug everywhere else in the plugin; labels are
 * resolved through this class. City slugs only need to be unique within
 * their own country (so e.g. two different countries could each have a
 * "springfield"), which is why every city/area lookup here always takes the
 * country (and, for areas, the city) as part of the key.
 */
class Locations {

	/**
	 * Option name the location list is stored under.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'dak_locations';

	/**
	 * Every country, each with its cities and their areas. Falls back to
	 * default_seed_data() (Pakistan's major cities/areas) the very first
	 * time this is called — before an admin has ever saved Settings →
	 * Locations — so the location fields aren't empty out of the box. Once
	 * an admin saves that page (even to an empty list, if they clear
	 * everything), their saved value is used from then on and this fallback
	 * no longer applies.
	 *
	 * @return array See class docblock for shape.
	 */
	public static function get_all() {
		$countries = get_option( self::OPTION_KEY, false );

		if ( false === $countries ) {
			return self::default_seed_data();
		}

		return is_array( $countries ) ? $countries : array();
	}

	/**
	 * Country slug => name, for building a Country <select>.
	 *
	 * @return array
	 */
	public static function country_options() {
		$options = array();

		foreach ( self::get_all() as $country ) {
			$options[ $country['slug'] ] = $country['name'];
		}

		return $options;
	}

	/**
	 * A single country's cities (slug => name), for building a City <select>.
	 *
	 * @param string $country_slug Country slug.
	 * @return array
	 */
	public static function city_options( $country_slug ) {
		$country = self::find_country( $country_slug );

		if ( ! $country ) {
			return array();
		}

		$options = array();

		foreach ( $country['cities'] as $city ) {
			$options[ $city['slug'] ] = $city['name'];
		}

		return $options;
	}

	/**
	 * A single city's areas (slug => name), for building an Area <select>.
	 *
	 * @param string $country_slug Country slug.
	 * @param string $city_slug    City slug.
	 * @return array
	 */
	public static function area_options( $country_slug, $city_slug ) {
		$city = self::find_city( $country_slug, $city_slug );

		if ( ! $city ) {
			return array();
		}

		$options = array();

		foreach ( $city['areas'] as $area ) {
			$options[ $area['slug'] ] = $area['name'];
		}

		return $options;
	}

	/**
	 * Whether a country slug exists in the list.
	 *
	 * @param string $country_slug Country slug.
	 * @return bool
	 */
	public static function is_valid_country( $country_slug ) {
		return false !== self::find_country( $country_slug );
	}

	/**
	 * Whether a city slug exists under a given country.
	 *
	 * @param string $country_slug Country slug.
	 * @param string $city_slug    City slug.
	 * @return bool
	 */
	public static function is_valid_city( $country_slug, $city_slug ) {
		return false !== self::find_city( $country_slug, $city_slug );
	}

	/**
	 * Whether an area slug exists under a given country/city.
	 *
	 * @param string $country_slug Country slug.
	 * @param string $city_slug    City slug.
	 * @param string $area_slug    Area slug.
	 * @return bool
	 */
	public static function is_valid_area( $country_slug, $city_slug, $area_slug ) {
		$city = self::find_city( $country_slug, $city_slug );

		if ( ! $city ) {
			return false;
		}

		foreach ( $city['areas'] as $area ) {
			if ( $area['slug'] === $area_slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A country's display name.
	 *
	 * @param string $country_slug Country slug.
	 * @return string Empty string if not found.
	 */
	public static function country_label( $country_slug ) {
		$country = self::find_country( $country_slug );

		return $country ? $country['name'] : '';
	}

	/**
	 * A city's display name.
	 *
	 * @param string $country_slug Country slug.
	 * @param string $city_slug    City slug.
	 * @return string Empty string if not found.
	 */
	public static function city_label( $country_slug, $city_slug ) {
		$city = self::find_city( $country_slug, $city_slug );

		return $city ? $city['name'] : '';
	}

	/**
	 * An area's display name.
	 *
	 * @param string $country_slug Country slug.
	 * @param string $city_slug    City slug.
	 * @param string $area_slug    Area slug.
	 * @return string Empty string if not found.
	 */
	public static function area_label( $country_slug, $city_slug, $area_slug ) {
		$city = self::find_city( $country_slug, $city_slug );

		if ( ! $city ) {
			return '';
		}

		foreach ( $city['areas'] as $area ) {
			if ( $area['slug'] === $area_slug ) {
				return $area['name'];
			}
		}

		return '';
	}

	/**
	 * Finds a country entry by slug.
	 *
	 * @param string $country_slug Country slug.
	 * @return array|false
	 */
	private static function find_country( $country_slug ) {
		if ( '' === $country_slug ) {
			return false;
		}

		foreach ( self::get_all() as $country ) {
			if ( $country['slug'] === $country_slug ) {
				return $country;
			}
		}

		return false;
	}

	/**
	 * Finds a city entry (within a specific country) by slug.
	 *
	 * @param string $country_slug Country slug.
	 * @param string $city_slug    City slug.
	 * @return array|false
	 */
	private static function find_city( $country_slug, $city_slug ) {
		if ( '' === $city_slug ) {
			return false;
		}

		$country = self::find_country( $country_slug );

		if ( ! $country ) {
			return false;
		}

		foreach ( $country['cities'] as $city ) {
			if ( $city['slug'] === $city_slug ) {
				return $city;
			}
		}

		return false;
	}

	/**
	 * A starting-point list of Pakistan's major cities and their well-known
	 * areas/localities, for the Settings → Locations "Load Default
	 * Countries" action. General-knowledge based, not an authoritative/
	 * exhaustive source — admins are expected to review, correct, and
	 * extend it (adding missing cities/areas or other countries) for their
	 * own coverage rather than treat it as final.
	 *
	 * @return array List of `array( 'name', 'cities' => array( array( 'name', 'areas' => string[] ) ) )` (pre-slug shape).
	 */
	public static function default_seed_countries() {
		return array(
			array(
				'name'   => 'Pakistan',
				'cities' => array(
					array(
						'name'  => 'Karachi',
						'areas' => array( 'DHA', 'Clifton', 'Gulshan-e-Iqbal', 'North Nazimabad', 'Gulistan-e-Johar', 'PECHS', 'Nazimabad', 'Malir', 'Korangi', 'Saddar', 'Bahadurabad', 'Federal B Area', 'North Karachi', 'Shah Faisal Colony', 'Lyari', 'Landhi', 'Orangi Town', 'Gulberg' ),
					),
					array(
						'name'  => 'Lahore',
						'areas' => array( 'DHA', 'Gulberg', 'Model Town', 'Johar Town', 'Garden Town', 'Cantt', 'Bahria Town', 'Township', 'Wapda Town', 'Iqbal Town', 'Faisal Town', 'Allama Iqbal Town', 'Shadman', 'Samanabad', 'Gulshan-e-Ravi', 'Askari', 'Valencia Town', 'Walled City' ),
					),
					array(
						'name'  => 'Islamabad',
						'areas' => array( 'F-6', 'F-7', 'F-8', 'F-10', 'F-11', 'G-6', 'G-7', 'G-8', 'G-9', 'G-10', 'G-11', 'E-7', 'E-11', 'I-8', 'I-9', 'I-10', 'Bahria Town', 'DHA' ),
					),
					array(
						'name'  => 'Rawalpindi',
						'areas' => array( 'Saddar', 'Cantt', 'Satellite Town', 'Bahria Town', 'DHA', 'Chaklala', 'Westridge', 'Committee Chowk', 'Adiala Road', 'Pindora', 'Gulraiz', 'Peshawar Road' ),
					),
					array(
						'name'  => 'Faisalabad',
						'areas' => array( 'Madina Town', 'Peoples Colony', 'Gulberg', 'Jinnah Colony', 'D Ground', 'Susan Road', 'Samanabad', 'Millat Town' ),
					),
					array(
						'name'  => 'Multan',
						'areas' => array( 'Cantt', 'Gulgasht Colony', 'Model Town', 'Shah Rukn-e-Alam Colony', 'Bosan Road', 'New Multan', 'Wapda Town' ),
					),
					array(
						'name'  => 'Peshawar',
						'areas' => array( 'University Town', 'Hayatabad', 'Cantt', 'Gulbahar', 'Saddar', 'Warsak Road', 'Ring Road' ),
					),
					array(
						'name'  => 'Quetta',
						'areas' => array( 'Cantt', 'Jinnah Town', 'Satellite Town', 'Samungli Road', 'Airport Road' ),
					),
					array(
						'name'  => 'Hyderabad',
						'areas' => array( 'Latifabad', 'Qasimabad', 'City', 'Cantt', 'Hyderabad Bypass' ),
					),
					array(
						'name'  => 'Sialkot',
						'areas' => array( 'Cantt', 'Model Town', 'Paris Road', 'Kutchery Road' ),
					),
					array(
						'name'  => 'Gujranwala',
						'areas' => array( 'Model Town', 'Satellite Town', 'Civil Lines', 'GT Road' ),
					),
					array(
						'name'  => 'Sargodha',
						'areas' => array( 'Satellite Town', 'University Road', 'Civil Lines' ),
					),
					array(
						'name'  => 'Bahawalpur',
						'areas' => array( 'Model Town', 'Satellite Town', 'Shahra-e-Bahawalpur' ),
					),
					array(
						'name'  => 'Sukkur',
						'areas' => array( 'Military Road', 'Barrage Colony', 'New Sukkur' ),
					),
					array(
						'name'  => 'Larkana',
						'areas' => array( 'Model Colony', 'Old Larkana', 'Sindhi Muslim Housing Society' ),
					),
					array(
						'name'  => 'Abbottabad',
						'areas' => array( 'Mandian', 'Jinnahabad', 'Supply Bazaar', 'Cantt' ),
					),
					array(
						'name'  => 'Mardan',
						'areas' => array( 'Bank Road', 'Cantt', 'University Road' ),
					),
					array(
						'name'  => 'Sahiwal',
						'areas' => array( 'Farid Town', 'High Street', 'Model Town' ),
					),
					array(
						'name'  => 'Gujrat',
						'areas' => array( 'Satellite Town', 'GT Road', 'Civil Lines' ),
					),
					array(
						'name'  => 'Sheikhupura',
						'areas' => array( 'Model Town', 'GT Road' ),
					),
					array(
						'name'  => 'Dera Ghazi Khan',
						'areas' => array( 'Model Town', 'DG Khan Cantt' ),
					),
					array(
						'name'  => 'Nawabshah',
						'areas' => array( 'City Area', 'Housing Society' ),
					),
					array(
						'name'  => 'Mingora (Swat)',
						'areas' => array( 'Green Chowk', 'Kanju', 'Faizabad' ),
					),
					array(
						'name'  => 'Muzaffarabad',
						'areas' => array( 'Upper Chattar', 'Domel', 'Sector D' ),
					),
					array(
						'name'  => 'Gilgit',
						'areas' => array( 'Jutial', 'Konodas', 'Amphari' ),
					),
				),
			),
		);
	}

	/**
	 * Converts default_seed_countries() into get_all()'s stored shape
	 * (slugs added). Used by the Settings → Locations "Load Default
	 * Countries" action.
	 *
	 * @return array See get_all().
	 */
	public static function default_seed_data() {
		return array_map(
			function ( $country ) {
				return array(
					'slug'   => self::slugify( $country['name'] ),
					'name'   => $country['name'],
					'cities' => array_map(
						function ( $city ) {
							return array(
								'slug'  => self::slugify( $city['name'] ),
								'name'  => $city['name'],
								'areas' => array_map(
									function ( $area_name ) {
										return array(
											'slug' => self::slugify( $area_name ),
											'name' => $area_name,
										);
									},
									$city['areas']
								),
							);
						},
						$country['cities']
					),
				);
			},
			self::default_seed_countries()
		);
	}

	/**
	 * Builds a URL/DB-safe slug from a free-text name, matching the pattern
	 * Doctor_Awards/Specializations-adjacent free-text-to-slug conversions
	 * elsewhere use.
	 *
	 * @param string $name Free-text country/city/area name.
	 * @return string
	 */
	public static function slugify( $name ) {
		$slug = sanitize_title( $name );

		return '' !== $slug ? $slug : md5( $name );
	}

	/**
	 * Validates and sanitizes the Settings → Locations admin form's raw
	 * `countries[name][]` / `countries[cities][]` posted arrays into the
	 * shape get_all() returns. Each country's cities textarea holds one city
	 * per line, formatted `City Name: Area 1, Area 2, Area 3` (the `: areas`
	 * part is optional — a bare city name is a city with no areas yet).
	 * Blank country-name rows are dropped; duplicate country/city/area slugs
	 * are de-duplicated (last one wins).
	 *
	 * @param array $raw_names        Raw `countries[name][]` values.
	 * @param array $raw_cities_blocks Raw `countries[cities][]` values (one per country, newline-separated `City: Area, Area` lines).
	 * @return array Sanitized list, see get_all().
	 */
	public static function sanitize_from_request( array $raw_names, array $raw_cities_blocks ) {
		$countries = array();

		foreach ( $raw_names as $index => $raw_name ) {
			$name = sanitize_text_field( $raw_name );

			if ( '' === $name ) {
				continue;
			}

			$country_slug = self::slugify( $name );
			$cities       = array();

			$cities_block = isset( $raw_cities_blocks[ $index ] ) ? (string) $raw_cities_blocks[ $index ] : '';

			foreach ( preg_split( '/[\r\n]+/', $cities_block ) as $city_line ) {
				$city_line = trim( $city_line );

				if ( '' === $city_line ) {
					continue;
				}

				$parts     = explode( ':', $city_line, 2 );
				$city_name = sanitize_text_field( $parts[0] );

				if ( '' === $city_name ) {
					continue;
				}

				$areas = array();

				if ( isset( $parts[1] ) ) {
					foreach ( explode( ',', $parts[1] ) as $area_name ) {
						$area_name = sanitize_text_field( $area_name );

						if ( '' === $area_name ) {
							continue;
						}

						$areas[ self::slugify( $area_name ) ] = array(
							'slug' => self::slugify( $area_name ),
							'name' => $area_name,
						);
					}
				}

				$city_slug            = self::slugify( $city_name );
				$cities[ $city_slug ] = array(
					'slug'  => $city_slug,
					'name'  => $city_name,
					'areas' => array_values( $areas ),
				);
			}

			$countries[ $country_slug ] = array(
				'slug'   => $country_slug,
				'name'   => $name,
				'cities' => array_values( $cities ),
			);
		}

		return array_values( $countries );
	}
}
