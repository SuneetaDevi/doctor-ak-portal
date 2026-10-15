/**
 * Doctor AK Portal — Symptom Checker engine (pure logic, no DOM).
 *
 * Runs entirely in the patient's browser: nothing it handles — symptom
 * text, answers, location — is sent anywhere. Loaded by the Symptom
 * Checker page (window.DAKSymptomEngine) and by the automated tests
 * (module.exports).
 *
 * The clinical content lives in the ruleset (see
 * includes/data/symptom-checker-demo-rules.json and Symptom_Rules), never in
 * this file: symptoms + synonyms, questions + branching, warning signs,
 * explanations, next steps, conflicts and specialties are all data.
 *
 * Condition language (used by show_if / when):
 *   { "all": [ … ] }  { "any": [ … ] }  { "not": cond }
 *   { "symptom": "id" }                       — symptom selected
 *   { "answer": "q", "equals": "v" }           — single answer is v (multi: includes v)
 *   { "answer": "q", "in": [ … ] }             — single in list / multi overlaps list
 *   { "answer": "q", "includes": "v" }         — multi contains v
 *   { "answer": "q", "gte": n } / "lte": n     — chosen option's "score"
 *   { "answered": "q" }                        — any answer given
 * A "Not sure" answer is the value "unsure" (single) or [ "unsure" ] (multi).
 */
( function ( root, factory ) {
	if ( 'object' === typeof module && module.exports ) {
		module.exports = factory();
	} else {
		root.DAKSymptomEngine = factory();
	}
}( 'undefined' !== typeof self ? self : this, function () {
	'use strict';

	var UNSURE = 'unsure';

	var NEGATION_CUES = [ 'no', 'not', 'never', 'without', 'denies', 'deny', 'dont', 'didnt', 'doesnt', 'isnt', 'arent', 'havent', 'hasnt', 'hadnt', 'wasnt', 'werent', 'none', 'neither', 'nor' ];
	var NEGATION_PHRASES = [ 'free of', 'no longer', 'do not', 'does not', 'did not', 'have not', 'has not', 'is not', 'are not', 'was not' ];
	var POST_NEGATION_CUES = [ 'nahi', 'nahin', 'nai', 'nahee' ];
	var UNCERTAIN_CUES = [ 'maybe', 'might', 'possibly', 'perhaps', 'probably', 'unsure', 'think', 'shayad' ];
	var UNCERTAIN_PHRASES = [ 'not sure', 'not certain', 'kind of', 'sort of', 'i think' ];

	var STOPWORDS = toSet( 'i im ive id me my mine myself you your we our us he she his her they them their it its this that these those a an the and or but so if then than also too just only very really quite bit little some lot lots much many more most is am are was were be been being have has had having do does did get got getting feel feels feeling felt seem seems seemed of for from to in on at by with since about after before during into over under again still now today tonight yesterday morning evening night day days week weeks month months year years hour hours ago last past few couple started start starting began begin begun keep keeps kept going come comes came when while because there here what which who how sometimes often always usually bad worse worst terrible awful severe mild slight slightly moderate pain like please help doctor any one two three four five six seven eight nine ten first second around almost about can cant could would should will lately recently new constant all whole both each every hai hain tha thi aur mein mujhe mera meri se ko ka ki ke bhi nahi nahin nai nahee'.split( ' ' ) );

	var NUMBER_WORDS = { a: 1, an: 1, one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9, ten: 10, few: 3, couple: 2, several: 4 };

	// Approximate city centres — only to name the area a visitor is in
	// ("Near Karachi") when no clinic is close by. Never used for distances.
	var CITY_CENTRES = [
		{ name: 'Karachi', lat: 24.8607, lng: 67.0011 },
		{ name: 'Lahore', lat: 31.5497, lng: 74.3436 },
		{ name: 'Islamabad', lat: 33.6844, lng: 73.0479 },
		{ name: 'Rawalpindi', lat: 33.5651, lng: 73.0169 },
		{ name: 'Faisalabad', lat: 31.4504, lng: 73.135 },
		{ name: 'Multan', lat: 30.1575, lng: 71.5249 },
		{ name: 'Peshawar', lat: 34.0151, lng: 71.5249 },
		{ name: 'Quetta', lat: 30.1798, lng: 66.975 },
		{ name: 'Hyderabad', lat: 25.396, lng: 68.3578 },
		{ name: 'Sialkot', lat: 32.4945, lng: 74.5229 },
		{ name: 'Gujranwala', lat: 32.1877, lng: 74.1945 },
		{ name: 'Sukkur', lat: 27.7052, lng: 68.8574 },
		{ name: 'Larkana', lat: 27.5598, lng: 68.2264 },
		{ name: 'Mirpur Khas', lat: 25.5269, lng: 69.0111 },
		{ name: 'Nawabshah', lat: 26.2442, lng: 68.41 }
	];

	function toSet( list ) {
		var set = {};
		list.forEach( function ( w ) {
			set[ w ] = true;
		} );
		return set;
	}

	function normalise( text ) {
		return String( text || '' )
			.toLowerCase()
			.replace( /[‘’ʼ`]/g, '\'' )
			.replace( /n't\b/g, 'nt' )
			.replace( /'/g, '' )
			.replace( /[^a-z0-9؀-ۿ\s.,;!?\n-]/g, ' ' )
			.replace( /-/g, ' ' );
	}

	function tokens( text ) {
		return normalise( text ).split( /[\s.,;!?\n]+/ ).filter( Boolean );
	}

	function stem( word ) {
		if ( word.length > 4 && /ies$/.test( word ) ) {
			return word.slice( 0, -3 ) + 'y';
		}
		if ( word.length > 3 && /s$/.test( word ) && ! /ss$/.test( word ) ) {
			return word.slice( 0, -1 );
		}
		return word;
	}

	/** Damerau–Levenshtein distance (optimal string alignment), capped. */
	function editDistance( a, b, cap ) {
		if ( a === b ) {
			return 0;
		}
		if ( Math.abs( a.length - b.length ) > cap ) {
			return cap + 1;
		}
		var d = [];
		var i;
		var j;
		for ( i = 0; i <= a.length; i++ ) {
			d[ i ] = [ i ];
		}
		for ( j = 0; j <= b.length; j++ ) {
			d[ 0 ][ j ] = j;
		}
		for ( i = 1; i <= a.length; i++ ) {
			var rowMin = Infinity;
			for ( j = 1; j <= b.length; j++ ) {
				var cost = a[ i - 1 ] === b[ j - 1 ] ? 0 : 1;
				d[ i ][ j ] = Math.min( d[ i - 1 ][ j ] + 1, d[ i ][ j - 1 ] + 1, d[ i - 1 ][ j - 1 ] + cost );
				if ( i > 1 && j > 1 && a[ i - 1 ] === b[ j - 2 ] && a[ i - 2 ] === b[ j - 1 ] ) {
					d[ i ][ j ] = Math.min( d[ i ][ j ], d[ i - 2 ][ j - 2 ] + 1 );
				}
				rowMin = Math.min( rowMin, d[ i ][ j ] );
			}
			if ( rowMin > cap ) {
				return cap + 1;
			}
		}
		return d[ a.length ][ b.length ];
	}

	/**
	 * Whether a typed word is close enough to a dictionary word. Typos are
	 * tolerated only on longer words, with the same first letter, and never
	 * for everyday words ("never" must not become "fever").
	 */
	function wordMatches( typed, wanted ) {
		if ( typed === wanted || stem( typed ) === stem( wanted ) ) {
			return true;
		}
		if ( typed[ 0 ] !== wanted[ 0 ] || STOPWORDS[ typed ] || NEGATION_CUES.indexOf( typed ) !== -1 || UNCERTAIN_CUES.indexOf( typed ) !== -1 ) {
			return false;
		}
		var len = wanted.length;
		var cap = len >= 8 ? 2 : ( len >= 5 ? 1 : 0 );
		return cap > 0 && editDistance( stem( typed ), stem( wanted ), cap ) <= cap;
	}

	function haversineKm( a, b ) {
		var toRad = Math.PI / 180;
		var dLat = ( b.lat - a.lat ) * toRad;
		var dLng = ( b.lng - a.lng ) * toRad;
		var h = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) + Math.cos( a.lat * toRad ) * Math.cos( b.lat * toRad ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );
		return 6371 * 2 * Math.atan2( Math.sqrt( h ), Math.sqrt( 1 - h ) );
	}

	function isNum( v ) {
		return 'number' === typeof v && isFinite( v );
	}

	/**
	 * @param {Object} rules Ruleset (see the file docblock).
	 */
	function create( rules ) {
		rules = rules || {};

		var symptoms = rules.symptoms || [];
		var questions = rules.questions || [];
		var symptomById = {};
		var questionById = {};
		var phraseIndex = [];

		symptoms.forEach( function ( s ) {
			symptomById[ s.id ] = s;
			var phrases = [ s.label ].concat( s.synonyms || [] );
			phrases.forEach( function ( phrase ) {
				var t = tokens( String( phrase ).replace( /\([^)]*\)/g, ' ' ) );
				if ( t.length ) {
					phraseIndex.push( { symptom: s.id, tokens: t, phrase: phrase } );
				}
			} );
		} );

		// Longest phrases first, so "blood in stool" wins over "stool".
		phraseIndex.sort( function ( a, b ) {
			return b.tokens.length - a.tokens.length;
		} );

		questions.forEach( function ( q, index ) {
			q._index = index;
			questionById[ q.id ] = q;
		} );

		/* ------------------------------------------------------------------
		 * Free-text understanding
		 * ---------------------------------------------------------------- */

		/**
		 * Splits text into clauses — negation and uncertainty only reach as
		 * far as the end of their own clause ("pain but no fever").
		 */
		function clauses( text ) {
			return normalise( text )
				.split( /[.,;!?\n]+|\s+(?:but|however|though|although|and|also|plus)\s+/ )
				.map( function ( c ) {
					return c.trim();
				} )
				.filter( Boolean );
		}

		function hasCueBefore( words, end, cues, phrases ) {
			var before = words.slice( 0, end );
			var joined = ' ' + before.join( ' ' ) + ' ';
			for ( var i = 0; i < before.length; i++ ) {
				if ( cues.indexOf( before[ i ] ) !== -1 ) {
					return true;
				}
			}
			return phrases.some( function ( p ) {
				return joined.indexOf( ' ' + p + ' ' ) !== -1;
			} );
		}

		function hasCueAnywhere( words, cues, phrases ) {
			return hasCueBefore( words, words.length, cues, phrases );
		}

		/**
		 * Finds known symptoms in free text.
		 *
		 * @param {string} text
		 * @return {Object} {
		 *   matches:   [ { id, label, phrase, negated, uncertain } ] (one per symptom),
		 *   unmatched: [ "clause we couldn't understand" ],
		 *   alerts:    [ questionId ] — warning phrases that need their question asked next,
		 *   durationDays: number|null — "for 3 days" etc., a hint only
		 * }
		 */
		function matchText( text ) {
			var found = {};
			var unmatched = [];
			var alertQuestions = [];
			var parts = clauses( text );

			parts.forEach( function ( clause ) {
				var words = clause.split( /\s+/ ).filter( Boolean );
				// "not sure" / "not certain" is uncertainty, not a denial.
				var negWords = words.map( function ( w, i ) {
					return 'not' === w && /^(sure|certain)$/.test( words[ i + 1 ] || '' ) ? '' : w;
				} );
				var used = [];
				var clauseMatched = false;

				phraseIndex.forEach( function ( entry ) {
					var n = entry.tokens.length;
					for ( var start = 0; start + n <= words.length; start++ ) {
						var ok = true;
						for ( var k = 0; k < n; k++ ) {
							if ( used[ start + k ] || ! wordMatches( words[ start + k ], entry.tokens[ k ] ) ) {
								ok = false;
								break;
							}
						}
						if ( ! ok ) {
							continue;
						}
						for ( k = 0; k < n; k++ ) {
							used[ start + k ] = true;
						}
						clauseMatched = true;

						// Urdu puts the negation after the word ("bukhar nahi hai").
						var after = words.slice( start + n, start + n + 2 );
						var negated = hasCueBefore( negWords, start, NEGATION_CUES, NEGATION_PHRASES ) || after.some( function ( w ) {
							return POST_NEGATION_CUES.indexOf( w ) !== -1;
						} );
						var uncertain = ! negated && hasCueAnywhere( words, UNCERTAIN_CUES, UNCERTAIN_PHRASES );
						var existing = found[ entry.symptom ];

						// A positive mention anywhere beats a negated one elsewhere.
						if ( ! existing || ( existing.negated && ! negated ) || ( existing.uncertain && ! uncertain && ! negated ) ) {
							found[ entry.symptom ] = {
								id: entry.symptom,
								label: symptomById[ entry.symptom ].label,
								phrase: words.slice( start, start + n ).join( ' ' ),
								negated: negated,
								uncertain: uncertain
							};
						}
						break;
					}
				} );

				// Words left over that carry meaning — reported, never ignored.
				var leftovers = words.filter( function ( w, i ) {
					return ! used[ i ] && ! STOPWORDS[ w ] && NEGATION_CUES.indexOf( w ) === -1 && UNCERTAIN_CUES.indexOf( w ) === -1 && ! /^\d+$/.test( w ) && ! NUMBER_WORDS[ w ] && w.length > 2;
				} );

				if ( ! clauseMatched && leftovers.length ) {
					unmatched.push( clause );
				}
			} );

			// Warning phrases queue their screening question. Even a denied
			// one ("no chest pain") does — negation detection isn't perfect,
			// so the question is asked rather than assumed.
			( rules.text_alerts || [] ).forEach( function ( alert ) {
				var hit = ( alert.phrases || [] ).some( function ( phrase ) {
					var want = tokens( phrase );
					return want.length && parts.some( function ( clause ) {
						var words = clause.split( /\s+/ ).filter( Boolean );
						for ( var start = 0; start + want.length <= words.length; start++ ) {
							var all = true;
							for ( var k = 0; k < want.length && all; k++ ) {
								all = wordMatches( words[ start + k ], want[ k ] );
							}
							if ( all ) {
								return true;
							}
						}
						return false;
					} );
				} );
				if ( hit && alertQuestions.indexOf( alert.question ) === -1 ) {
					alertQuestions.push( alert.question );
				}
			} );

			return {
				matches: Object.keys( found ).map( function ( id ) {
					return found[ id ];
				} ),
				unmatched: unmatched,
				alerts: alertQuestions,
				durationDays: durationDays( text )
			};
		}

		/** "for 3 days", "since yesterday", "2 weeks ago" → days, or null. */
		function durationDays( text ) {
			var t = normalise( text );
			var m = t.match( /\b(\d+|an?|one|two|three|four|five|six|seven|eight|nine|ten|few|couple|several)\s+(hour|hr|day|week|wk|month|year)s?\b/ );
			if ( m ) {
				var n = /^\d+$/.test( m[ 1 ] ) ? parseInt( m[ 1 ], 10 ) : NUMBER_WORDS[ m[ 1 ] ];
				var unit = m[ 2 ];
				if ( 'hour' === unit || 'hr' === unit ) {
					return n >= 24 ? n / 24 : 0.5;
				}
				return n * ( { day: 1, week: 7, wk: 7, month: 30, year: 365 }[ unit ] );
			}
			if ( /\b(this morning|today|tonight|few hours|an hour)\b/.test( t ) ) {
				return 0.5;
			}
			if ( /\byesterday\b/.test( t ) ) {
				return 1;
			}
			if ( /\blast week\b/.test( t ) ) {
				return 7;
			}
			if ( /\blast month\b/.test( t ) ) {
				return 30;
			}
			return null;
		}

		/** The duration option a day count falls into (options carry max_days). */
		function durationOptionFor( question, days ) {
			if ( ! question || ! isNum( days ) ) {
				return null;
			}
			var options = question.options || [];
			for ( var i = 0; i < options.length; i++ ) {
				if ( ! isNum( options[ i ].max_days ) || days <= options[ i ].max_days ) {
					return options[ i ].value;
				}
			}
			return null;
		}

		/** Symptom list search (labels + synonyms, typo tolerant). */
		function searchSymptoms( query ) {
			var q = tokens( query );
			if ( ! q.length ) {
				return symptoms.slice();
			}
			return symptoms.filter( function ( s ) {
				var hay = tokens( [ s.label ].concat( s.synonyms || [] ).join( ' ' ) );
				return q.every( function ( word ) {
					return hay.some( function ( h ) {
						return 0 === h.indexOf( word ) || ( word.length >= 4 && wordMatches( word, h ) );
					} );
				} );
			} );
		}

		/* ------------------------------------------------------------------
		 * Conditions, branching, results
		 * ---------------------------------------------------------------- */

		function optionScore( qid, value ) {
			var q = questionById[ qid ];
			if ( ! q ) {
				return null;
			}
			var opt = ( q.options || [] ).filter( function ( o ) {
				return o.value === value;
			} )[ 0 ];
			return opt && isNum( opt.score ) ? opt.score : null;
		}

		function asList( value ) {
			if ( Array.isArray( value ) ) {
				return value;
			}
			return undefined === value || null === value || '' === value ? [] : [ value ];
		}

		/**
		 * @param {Object} cond
		 * @param {Object} state { symptoms: { id: { uncertain } }, answers: { qid: value } }
		 */
		function evaluate( cond, state ) {
			if ( ! cond || 'object' !== typeof cond ) {
				return true;
			}
			if ( Array.isArray( cond.all ) ) {
				return cond.all.every( function ( c ) {
					return evaluate( c, state );
				} );
			}
			if ( Array.isArray( cond.any ) ) {
				return cond.any.some( function ( c ) {
					return evaluate( c, state );
				} );
			}
			if ( cond.not ) {
				return ! evaluate( cond.not, state );
			}
			if ( cond.symptom ) {
				return !! ( state.symptoms && state.symptoms[ cond.symptom ] );
			}
			if ( cond.answered ) {
				return asList( ( state.answers || {} )[ cond.answered ] ).length > 0;
			}
			if ( cond.answer ) {
				var value = ( state.answers || {} )[ cond.answer ];
				var list = asList( value );
				if ( ! list.length ) {
					return false;
				}
				if ( undefined !== cond.equals ) {
					return list.indexOf( cond.equals ) !== -1;
				}
				if ( Array.isArray( cond[ 'in' ] ) ) {
					return list.some( function ( v ) {
						return cond[ 'in' ].indexOf( v ) !== -1;
					} );
				}
				if ( undefined !== cond.includes ) {
					return list.indexOf( cond.includes ) !== -1;
				}
				if ( isNum( cond.gte ) || isNum( cond.lte ) ) {
					return list.some( function ( v ) {
						var score = optionScore( cond.answer, v );
						if ( null === score ) {
							return false;
						}
						return ( ! isNum( cond.gte ) || score >= cond.gte ) && ( ! isNum( cond.lte ) || score <= cond.lte );
					} );
				}
			}
			return false;
		}

		function appliesToSelection( q, state ) {
			if ( '*' === q.applies_to || undefined === q.applies_to ) {
				return true;
			}
			return asList( q.applies_to ).some( function ( id ) {
				return !! ( state.symptoms && state.symptoms[ id ] );
			} );
		}

		/** Questions that apply now, in the order they're asked. */
		function applicableQuestions( state ) {
			var forced = state.forced || [];
			return questions
				.filter( function ( q ) {
					return ( appliesToSelection( q, state ) || forced.indexOf( q.id ) !== -1 ) && evaluate( q.show_if, state );
				} )
				.sort( function ( a, b ) {
					var pa = forced.indexOf( a.id ) !== -1 ? -1 : ( isNum( a.priority ) ? a.priority : 50 );
					var pb = forced.indexOf( b.id ) !== -1 ? -1 : ( isNum( b.priority ) ? b.priority : 50 );
					return pa - pb || a._index - b._index;
				} );
		}

		/**
		 * Drops answers to questions that no longer apply (an earlier answer
		 * changed the path) — repeated until stable, since one removal can
		 * hide another question.
		 *
		 * @return {string[]} Removed question ids.
		 */
		function prune( state ) {
			var removed = [];
			var changed = true;
			while ( changed ) {
				changed = false;
				var applicable = applicableQuestions( state ).map( function ( q ) {
					return q.id;
				} );
				Object.keys( state.answers || {} ).forEach( function ( qid ) {
					if ( applicable.indexOf( qid ) === -1 ) {
						delete state.answers[ qid ];
						removed.push( qid );
						changed = true;
					}
				} );
			}
			return removed;
		}

		function isAnswered( q, state ) {
			return Object.prototype.hasOwnProperty.call( state.answers || {}, q.id );
		}

		/** The next unanswered question, or null when the path is complete. */
		function nextQuestion( state ) {
			var list = applicableQuestions( state );
			for ( var i = 0; i < list.length; i++ ) {
				if ( ! isAnswered( list[ i ], state ) ) {
					return list[ i ];
				}
			}
			return null;
		}

		/** { answered, remaining } — remaining can change as answers change. */
		function progress( state ) {
			var list = applicableQuestions( state );
			var answered = list.filter( function ( q ) {
				return isAnswered( q, state );
			} ).length;
			return { answered: answered, remaining: list.length - answered };
		}

		function redFlags( state ) {
			return ( rules.red_flags || [] ).filter( function ( rf ) {
				return evaluate( rf.when, state );
			} );
		}

		var URGENCY_ORDER = { emergency: 0, urgent: 1, soon: 2, routine: 3, self_care: 4 };

		function results( state ) {
			var steps = ( rules.next_steps || [] ).filter( function ( s ) {
				return ! s.fallback && evaluate( s.when, state );
			} );
			if ( ! steps.length ) {
				steps = ( rules.next_steps || [] ).filter( function ( s ) {
					return s.fallback && evaluate( s.when, state );
				} );
			}
			steps.sort( function ( a, b ) {
				return ( URGENCY_ORDER[ a.urgency ] || 9 ) - ( URGENCY_ORDER[ b.urgency ] || 9 );
			} );

			var explanations = ( rules.explanations || [] ).filter( function ( e ) {
				return evaluate( e.when, state );
			} );

			var conflicts = ( rules.conflicts || [] ).filter( function ( c ) {
				return evaluate( c.when, state );
			} );

			var incomplete = applicableQuestions( state ).filter( function ( q ) {
				return false !== q.required && 'text' !== q.type && ! isAnswered( q, state );
			} ).map( function ( q ) {
				return q.id;
			} );

			var unsureCount = Object.keys( state.answers || {} ).filter( function ( qid ) {
				return asList( state.answers[ qid ] ).indexOf( UNSURE ) !== -1;
			} ).length;

			return {
				urgency: steps.length ? steps[ 0 ].urgency : null,
				nextSteps: steps,
				explanations: explanations,
				conflicts: conflicts,
				incomplete: incomplete,
				unsureCount: unsureCount,
				specialties: specialtiesFor( state, explanations )
			};
		}

		/**
		 * Specialties to search, in order: from matched explanations, then
		 * from the selected symptoms; the general-care fallback only when
		 * nothing matched. Each carries the reason it was suggested.
		 */
		function specialtiesFor( state, explanations ) {
			var order = [];
			var reasons = {};

			function add( slug, reason ) {
				if ( ! slug ) {
					return;
				}
				if ( order.indexOf( slug ) === -1 ) {
					order.push( slug );
					reasons[ slug ] = [];
				}
				if ( reason && reasons[ slug ].indexOf( reason ) === -1 ) {
					reasons[ slug ].push( reason );
				}
			}

			( explanations || [] ).forEach( function ( e ) {
				( e.specialties || [] ).forEach( function ( slug ) {
					add( slug, e.title );
				} );
			} );

			Object.keys( state.symptoms || {} ).forEach( function ( id ) {
				var s = symptomById[ id ];
				( s && s.specialties || [] ).forEach( function ( slug ) {
					add( slug, s.label );
				} );
			} );

			var fallback = false;
			if ( ! order.length && rules.fallback_specialty ) {
				add( rules.fallback_specialty, null );
				fallback = true;
			}

			return order.map( function ( slug ) {
				return { slug: slug, reasons: reasons[ slug ], fallback: fallback };
			} );
		}

		/* ------------------------------------------------------------------
		 * Location naming and doctor ranking
		 * ---------------------------------------------------------------- */

		/**
		 * A human name for where the visitor is: the area of a clinic within
		 * 10 km, else the nearest known city within 60 km, else null.
		 */
		function nameArea( origin, clinicPlaces ) {
			if ( ! origin ) {
				return null;
			}
			var best = null;
			( clinicPlaces || [] ).forEach( function ( p ) {
				if ( ! isNum( p.lat ) || ! isNum( p.lng ) ) {
					return;
				}
				var d = haversineKm( origin, p );
				if ( d <= 10 && ( ! best || d < best.d ) ) {
					best = { d: d, name: p.label };
				}
			} );
			if ( best ) {
				return best.name;
			}
			CITY_CENTRES.forEach( function ( c ) {
				var d = haversineKm( origin, c );
				if ( d <= 60 && ( ! best || d < best.d ) ) {
					best = { d: d, name: c.name };
				}
			} );
			return best ? best.name : null;
		}

		/**
		 * Ranks doctors for the suggested specialties.
		 *
		 * @param {Array}  doctors   Directory data (see Symptom_Checker::doctors_payload()).
		 * @param {Array}  specialties specialtiesFor() result (ordered).
		 * @param {Object} opts { origin: {lat,lng}|null, city: slug|null, radiusKm: number }
		 * @return {Object} { nearby, unknownDistance, beyond, online, mode }
		 */
		function rankDoctors( doctors, specialties, opts ) {
			opts = opts || {};
			var wanted = specialties.map( function ( s ) {
				return s.slug;
			} );
			var mode = opts.origin ? 'distance' : ( opts.city ? 'city' : 'none' );
			var radius = isNum( opts.radiusKm ) ? opts.radiusKm : 25;
			var nearby = [];
			var unknown = [];
			var beyond = [];
			var online = [];

			( doctors || [] ).forEach( function ( doc ) {
				var matched = wanted.filter( function ( slug ) {
					return ( doc.specialties || [] ).indexOf( slug ) !== -1;
				} );
				if ( ! matched.length ) {
					return;
				}
				var rank = wanted.indexOf( matched[ 0 ] );
				var clinics = ( doc.clinics || [] ).map( function ( c ) {
					var copy = {};
					Object.keys( c ).forEach( function ( k ) {
						copy[ k ] = c[ k ];
					} );
					copy.distanceKm = opts.origin && isNum( c.lat ) && isNum( c.lng ) ? haversineKm( opts.origin, c ) : null;
					return copy;
				} );

				if ( 'city' === mode ) {
					clinics = clinics.filter( function ( c ) {
						return c.city_slug === opts.city;
					} );
				}

				clinics.sort( function ( a, b ) {
					if ( null === a.distanceKm && null === b.distanceKm ) {
						return 0;
					}
					if ( null === a.distanceKm ) {
						return 1;
					}
					if ( null === b.distanceKm ) {
						return -1;
					}
					return a.distanceKm - b.distanceKm;
				} );

				var entry = {
					doctor: doc,
					matchedSpecialty: matched[ 0 ],
					rank: rank,
					primary: clinics[ 0 ] || null,
					others: clinics.slice( 1 ),
					distanceKm: clinics[ 0 ] ? clinics[ 0 ].distanceKm : null
				};

				if ( doc.video && doc.video.offered ) {
					online.push( entry );
				}

				if ( ! clinics.length ) {
					return; // No (matching-city) clinic: online list only.
				}

				if ( 'distance' === mode ) {
					if ( null === entry.distanceKm ) {
						unknown.push( entry );
					} else if ( entry.distanceKm <= radius ) {
						nearby.push( entry );
					} else {
						beyond.push( entry );
					}
				} else {
					nearby.push( entry );
				}
			} );

			function byRankThenDistance( a, b ) {
				if ( a.rank !== b.rank ) {
					return a.rank - b.rank;
				}
				if ( null !== a.distanceKm && null !== b.distanceKm && a.distanceKm !== b.distanceKm ) {
					return a.distanceKm - b.distanceKm;
				}
				var na = a.doctor.next_at || '9999';
				var nb = b.doctor.next_at || '9999';
				return na < nb ? -1 : ( na > nb ? 1 : String( a.doctor.name ).localeCompare( String( b.doctor.name ) ) );
			}

			nearby.sort( byRankThenDistance );
			unknown.sort( byRankThenDistance );
			beyond.sort( function ( a, b ) {
				return a.distanceKm - b.distanceKm;
			} );
			online.sort( byRankThenDistance );

			return { nearby: nearby, unknownDistance: unknown, beyond: beyond, online: online, mode: mode, radiusKm: radius };
		}

		return {
			UNSURE: UNSURE,
			rules: rules,
			symptoms: symptoms,
			symptomById: symptomById,
			questionById: questionById,
			matchText: matchText,
			durationDays: durationDays,
			durationOptionFor: durationOptionFor,
			searchSymptoms: searchSymptoms,
			evaluate: evaluate,
			applicableQuestions: applicableQuestions,
			nextQuestion: nextQuestion,
			prune: prune,
			progress: progress,
			redFlags: redFlags,
			results: results,
			nameArea: nameArea,
			rankDoctors: rankDoctors
		};
	}

	return {
		create: create,
		haversineKm: haversineKm,
		editDistance: editDistance
	};
} ) );
