World countries and cities for the Country / City location dropdowns
=====================================================================

Source: GeoNames (https://www.geonames.org/), licensed under
Creative Commons Attribution 4.0 (https://creativecommons.org/licenses/by/4.0/).
Built on 2026-11-10 from the GeoNames export dump files countryInfo.txt,
admin1CodesASCII.txt and cities1000.zip (https://download.geonames.org/export/dump/).

What's here
- countries.json  : every country, [{ "code": "PK", "name": "Pakistan" }, ...]
- cities/XX.json  : one file per ISO country code — the names of every
                    populated place of 1,000+ people (sections of places,
                    historical, abandoned and destroyed places excluded).
                    A name used more than once in a country carries its
                    province/state, e.g. "Springfield (Illinois)".

The plugin merges these with the administrator's own list (Settings →
Locations), which keeps its spelling, extra cities and areas — see
includes/class-locations.php. Areas are not part of this data.

To refresh: download the three files above and regenerate the JSON with the
same rules (only populated places, feature class P, excluding feature codes
PPLX, PPLH, PPLQ, PPLW, PPLCH).
