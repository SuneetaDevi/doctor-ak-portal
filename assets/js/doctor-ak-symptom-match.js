/**
 * Doctor AK Portal — "Find a doctor by symptoms" matcher (pure logic).
 *
 * Runs in the patient's browser (window.DAKSymptomMatch) and in the
 * automated tests (module.exports). Nothing it handles leaves the page.
 *
 * Matching rules — deliberately conservative:
 * - A phrase that equals a mapping's name or one of its alternative phrases
 *   (ignoring case, punctuation, plurals and filler words such as "I have",
 *   "my", "a bit") is recognised.
 * - Anything less certain — part of a phrase, a typo, extra words — is
 *   never accepted silently: it comes back as "ambiguous" with candidates
 *   for the patient to confirm, or as "unknown".
 * - Specialties come only from the symptoms the patient confirmed.
 *
 * It suggests which kinds of doctor see a symptom; it does not diagnose,
 * assess urgency or advise treatment.
 */
( function ( root, factory ) {
	if ( 'object' === typeof module && module.exports ) {
		module.exports = factory();
	} else {
		root.DAKSymptomMatch = factory();
	}
}( 'undefined' !== typeof self ? self : this, function () {
	'use strict';

	var FILLERS = {};
	[
		'i ive im me my mine a an the some have has had having got get getting feel feeling feels felt am is are was been very really quite bit little lot lots of bad severe mild slight terrible constant also just',
		// How long / when — "since 2 days", "for a week", "3 din se".
		'since for from past last few couple several about around ago now today yesterday tonight morning night evening hour hours day days week weeks month months year years one two three four five six seven',
		// Common Roman Urdu filler.
		'mujhe mujh mera meri mere hai hain ho hun raha rahi rahe tha thi bohat bahut zyada thoda din hafta hafte mahina mahine saal ghanta ghante se kal aaj subah raat',
	].join( ' ' ).split( ' ' ).forEach( function ( w ) {
		FILLERS[ w ] = true;
	} );

	function stem( word ) {
		return word.length > 3 && /s$/.test( word ) && ! /ss$/.test( word ) ? word.slice( 0, -1 ) : word;
	}

	/** Comparable tokens of a phrase. */
	function tokens( text ) {
		return String( text || '' )
			.toLowerCase()
			.replace( /['’]/g, '' )
			.replace( /[^\p{L}\p{N}]+/gu, ' ' )
			.trim()
			.split( ' ' )
			.filter( function ( w ) {
				return w && ! FILLERS[ w ] && ! /^\d+$/.test( w );
			} )
			.map( stem );
	}

	function key( text ) {
		return tokens( text ).join( ' ' );
	}

	function distance( a, b, cap ) {
		if ( a === b ) {
			return 0;
		}

		if ( Math.abs( a.length - b.length ) > cap ) {
			return cap + 1;
		}

		var prev = [];
		var i;
		var j;

		for ( j = 0; j <= b.length; j++ ) {
			prev[ j ] = j;
		}

		for ( i = 1; i <= a.length; i++ ) {
			var cur = [ i ];
			var best = i;

			for ( j = 1; j <= b.length; j++ ) {
				cur[ j ] = Math.min( prev[ j ] + 1, cur[ j - 1 ] + 1, prev[ j - 1 ] + ( a[ i - 1 ] === b[ j - 1 ] ? 0 : 1 ) );
				best = Math.min( best, cur[ j ] );
			}

			if ( best > cap ) {
				return cap + 1;
			}

			prev = cur;
		}

		return prev[ b.length ];
	}

	/** Close enough to count as the same word (typos on longer words only). */
	function similar( a, b ) {
		if ( a === b ) {
			return true;
		}

		if ( a[ 0 ] !== b[ 0 ] || b.length < 5 ) {
			return false;
		}

		return distance( a, b, b.length >= 8 ? 2 : 1 ) <= ( b.length >= 8 ? 2 : 1 );
	}

	function covers( have, need ) {
		return need.length > 0 && need.every( function ( n ) {
			return have.some( function ( h ) {
				return similar( h, n );
			} );
		} );
	}

	function haversineKm( a, b ) {
		var r = Math.PI / 180;
		var dLat = ( b.lat - a.lat ) * r;
		var dLng = ( b.lng - a.lng ) * r;
		var h = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) + Math.cos( a.lat * r ) * Math.cos( b.lat * r ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );
		return 6371 * 2 * Math.atan2( Math.sqrt( h ), Math.sqrt( 1 - h ) );
	}

	function isNum( v ) {
		return 'number' === typeof v && isFinite( v );
	}

	/**
	 * @param {Array} mappings [{ id, name, synonyms[], specialties[] }]
	 */
	function create( mappings ) {
		mappings = mappings || [];

		var byId = {};
		var phrases = []; // { id, text, tokens, key }
		var exact = {};   // key → [ids]

		mappings.forEach( function ( m ) {
			byId[ m.id ] = m;

			[ m.name ].concat( m.synonyms || [] ).forEach( function ( text ) {
				var t = tokens( text );

				if ( ! t.length ) {
					return;
				}

				var k = t.join( ' ' );
				phrases.push( { id: m.id, text: text, tokens: t, key: k } );
				exact[ k ] = exact[ k ] || [];

				if ( exact[ k ].indexOf( m.id ) === -1 ) {
					exact[ k ].push( m.id );
				}
			} );
		} );

		/**
		 * Suggestions while typing: names/phrases starting with (or
		 * containing) what's typed. One entry per symptom.
		 *
		 * @return {Array} [{ id, name, via }] — via is the alternative phrase that matched, or ''.
		 */
		function suggest( query, limit ) {
			var q = key( query );
			var best = {};

			if ( ! q ) {
				return [];
			}

			var qTokens = q.split( ' ' );

			phrases.forEach( function ( p ) {
				var score = -1;

				if ( 0 === p.key.indexOf( q ) ) {
					score = 0;
				} else if ( p.key.indexOf( ' ' + q ) !== -1 ) {
					score = 1;
				} else if ( qTokens.every( function ( t ) {
					return p.tokens.some( function ( pt ) {
						return 0 === pt.indexOf( t ) || ( t.length >= 4 && similar( t, pt ) );
					} );
				} ) ) {
					score = 2;
				}

				if ( score < 0 ) {
					return;
				}

				var isName = key( byId[ p.id ].name ) === p.key;

				if ( ! best[ p.id ] || score < best[ p.id ].score || ( score === best[ p.id ].score && isName ) ) {
					best[ p.id ] = { id: p.id, name: byId[ p.id ].name, via: isName ? '' : p.text, score: score };
				}
			} );

			return Object.keys( best ).map( function ( id ) {
				return best[ id ];
			} ).sort( function ( a, b ) {
				return a.score - b.score || a.name.localeCompare( b.name );
			} ).slice( 0, limit || 8 );
		}

		/** Splits free text into phrases ("pain in stomach and fever, cough"). */
		function split( text ) {
			return String( text || '' )
				.split( /[,;.\n/]+|\s+(?:and|&|plus|with|aur)\s+/i )
				.map( function ( s ) {
					return s.trim();
				} )
				.filter( Boolean );
		}

		/**
		 * Reads free text.
		 *
		 * @return {Object} {
		 *   recognized: [ids]                      — exact matches only,
		 *   ambiguous:  [{ phrase, candidates: [ids] }] — needs the patient to choose,
		 *   unknown:    [phrase]                   — no reliable match
		 * }
		 */
		function analyse( text ) {
			var result = { recognized: [], ambiguous: [], unknown: [] };

			split( text ).forEach( function ( phrase ) {
				var t = tokens( phrase );

				if ( ! t.length ) {
					return;
				}

				var hit = exact[ t.join( ' ' ) ];

				if ( hit && 1 === hit.length ) {
					if ( result.recognized.indexOf( hit[ 0 ] ) === -1 ) {
						result.recognized.push( hit[ 0 ] );
					}

					return;
				}

				var scores = {};

				phrases.forEach( function ( p ) {
					var score = null;

					if ( hit && hit.indexOf( p.id ) !== -1 ) {
						score = 0;
					} else if ( covers( t, p.tokens ) ) {
						// Everything in the phrase is in what they wrote (plus extra words or a typo).
						score = 1 + ( t.length - p.tokens.length ) * 0.1;
					} else if ( covers( p.tokens, t ) ) {
						// What they wrote is part of a longer phrase ("stomach" → "stomach pain").
						score = 2 + ( p.tokens.length - t.length ) * 0.1;
					}

					if ( null !== score && ( undefined === scores[ p.id ] || score < scores[ p.id ] ) ) {
						scores[ p.id ] = score;
					}
				} );

				var candidates = Object.keys( scores ).sort( function ( a, b ) {
					return scores[ a ] - scores[ b ] || byId[ a ].name.localeCompare( byId[ b ].name );
				} ).slice( 0, 5 ).map( function ( id ) {
					return byId[ id ].id;
				} );

				if ( candidates.length ) {
					result.ambiguous.push( { phrase: phrase, candidates: candidates } );
				} else {
					result.unknown.push( phrase );
				}
			} );

			return result;
		}

		/**
		 * Specialties linked to the confirmed symptoms, most-linked first.
		 *
		 * @return {Array} [{ slug, count, symptoms: [names] }]
		 */
		function specialtiesFor( ids ) {
			var map = {};
			var order = [];

			( ids || [] ).forEach( function ( id ) {
				var m = byId[ id ];

				( m && m.specialties || [] ).forEach( function ( slug ) {
					if ( ! map[ slug ] ) {
						map[ slug ] = { slug: slug, count: 0, symptoms: [] };
						order.push( slug );
					}

					map[ slug ].count += 1;
					map[ slug ].symptoms.push( m.name );
				} );
			} );

			return order.map( function ( slug ) {
				return map[ slug ];
			} ).sort( function ( a, b ) {
				return b.count - a.count || order.indexOf( a.slug ) - order.indexOf( b.slug );
			} );
		}

		return {
			byId: byId,
			suggest: suggest,
			analyse: analyse,
			split: split,
			specialtiesFor: specialtiesFor,
		};
	}

	/**
	 * Doctors in the given specialties, one card each, at their nearest
	 * matching clinic.
	 *
	 * @param {Array}  doctors     [{ id, name, specialties[], next_at, clinics[{ id, lat, lng, city_slug, area_slug, … }], video{ offered } }]
	 * @param {Array}  specialties specialtiesFor() result.
	 * @param {Object} opts        { origin: {lat,lng}|null, city: slug, area: slug }
	 * @return {Object} { clinic: [entry], online: [entry], mode: 'distance'|'place'|'none' }
	 *   entry = { doctor, matched: [slugs], relevance, primary, others, distanceKm }
	 */
	function rankDoctors( doctors, specialties, opts ) {
		opts = opts || {};

		var weight = {};
		specialties.forEach( function ( s ) {
			weight[ s.slug ] = s.count;
		} );

		var mode = opts.origin ? 'distance' : ( opts.city ? 'place' : 'none' );
		var clinicList = [];
		var online = [];

		( doctors || [] ).forEach( function ( doc ) {
			var matched = ( doc.specialties || [] ).filter( function ( slug ) {
				return weight[ slug ];
			} ).sort( function ( a, b ) {
				return weight[ b ] - weight[ a ];
			} );

			if ( ! matched.length ) {
				return;
			}

			var relevance = matched.reduce( function ( sum, slug ) {
				return sum + weight[ slug ];
			}, 0 );

			var clinics = ( doc.clinics || [] ).filter( function ( c ) {
				if ( 'place' !== mode ) {
					return true;
				}

				return c.city_slug === opts.city && ( ! opts.area || c.area_slug === opts.area );
			} ).map( function ( c ) {
				var copy = {};
				Object.keys( c ).forEach( function ( k ) {
					copy[ k ] = c[ k ];
				} );
				copy.distanceKm = opts.origin && isNum( c.lat ) && isNum( c.lng ) ? haversineKm( opts.origin, c ) : null;
				return copy;
			} ).sort( function ( a, b ) {
				if ( null === a.distanceKm ) {
					return null === b.distanceKm ? 0 : 1;
				}

				return null === b.distanceKm ? -1 : a.distanceKm - b.distanceKm;
			} );

			var entry = {
				doctor: doc,
				matched: matched,
				relevance: relevance,
				primary: clinics[ 0 ] || null,
				others: clinics.slice( 1 ),
				distanceKm: clinics[ 0 ] ? clinics[ 0 ].distanceKm : null,
			};

			if ( clinics.length ) {
				clinicList.push( entry );
			}

			if ( doc.video && doc.video.offered ) {
				online.push( entry );
			}
		} );

		function byRelevance( a, b ) {
			if ( a.relevance !== b.relevance ) {
				return b.relevance - a.relevance;
			}

			var na = a.doctor.next_at || '9999';
			var nb = b.doctor.next_at || '9999';

			return na < nb ? -1 : ( na > nb ? 1 : String( a.doctor.name ).localeCompare( String( b.doctor.name ) ) );
		}

		clinicList.sort( function ( a, b ) {
			if ( 'distance' === mode ) {
				if ( null === a.distanceKm && null !== b.distanceKm ) {
					return 1;
				}

				if ( null !== a.distanceKm && null === b.distanceKm ) {
					return -1;
				}

				if ( null !== a.distanceKm && a.distanceKm !== b.distanceKm ) {
					return a.distanceKm - b.distanceKm;
				}
			}

			return byRelevance( a, b );
		} );

		online.sort( byRelevance );

		return { clinic: clinicList, online: online, mode: mode };
	}

	/** Name of the clinic area within 10 km of a point, or ''. */
	function nearbyPlace( origin, places ) {
		var best = null;

		( places || [] ).forEach( function ( p ) {
			if ( ! isNum( p.lat ) || ! isNum( p.lng ) ) {
				return;
			}

			var d = haversineKm( origin, p );

			if ( d <= 10 && ( ! best || d < best.d ) ) {
				best = { d: d, label: p.label };
			}
		} );

		return best ? best.label : '';
	}

	return {
		create: create,
		rankDoctors: rankDoctors,
		nearbyPlace: nearbyPlace,
		haversineKm: haversineKm,
		tokens: tokens,
	};
} ) );
