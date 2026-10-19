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
 * - Each phrase is read for what the patient says about it: denied ("no
 *   fever", "bukhar nahi hai"), gone ("went away"), past or unclear ("had
 *   a fever last week", "no fever and cough"). Denied and gone symptoms are
 *   never added; past or unclear ones are asked about. A denial is never
 *   treated as evidence that anything is fine.
 * - Specialties come only from the symptoms the patient confirmed, plus any
 *   specialties a clinician linked to an answer they gave.
 *
 * Follow-up questions and safety rules (also clinician-approved data):
 * - questionsFor() / visibleQuestions() decide what to ask; a question with
 *   show_if appears only while its earlier question has one of the listed
 *   answers.
 * - triggeredRules() returns the approved safety messages whose conditions
 *   are met by the answers so far, emergency first. There is no scoring:
 *   a rule either applies or it doesn't, exactly as the clinician wrote it.
 *
 * It suggests which kinds of doctor see a symptom; it does not diagnose,
 * score risk or advise treatment.
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

	/*
	 * What the patient says about each phrase — reported, denied, gone, or
	 * unclear. Cues are looked for in the patient's own words (before filler
	 * words are dropped) and only ever apply to the phrase they're in, never
	 * to the whole message. A denied symptom is simply not added; it is never
	 * taken as a sign that anything is fine.
	 */
	function cueSet( list ) {
		var set = {};
		list.split( ' ' ).forEach( function ( w ) {
			set[ w ] = true;
		} );
		return set;
	}

	// Negation that comes before the symptom ("no fever", "I don't have cough").
	var NEG_BEFORE = cueSet( 'no not without never none neither nor dont doesnt didnt havent hasnt denies deny' );
	// Roman Urdu negation that follows it ("bukhar nahi hai").
	var NEG_AFTER = cueSet( 'nahi nahin nai nhi nahee nahein' );
	// Words that may follow NEG_AFTER and still belong to that phrase.
	var NEG_TAIL = cueSet( 'hai hain he tha thi thay ho hota hoti raha rahi rahe hua hui' );
	// Gone / resolved.
	var RESOLVED = cueSet( 'longer anymore resolved recovered cured gone theek thik khatam' );
	var RESOLVED_PAIRS = { 'went away': true, 'any more': true, 'better now': true, 'ab nahi': true, 'ab nahin': true };
	// Past tense without saying it has stopped — needs asking.
	var PAST = cueSet( 'had used was were previously earlier ago tha thi thay pehle pichle pichhle' );
	// Says it is happening now.
	var PRESENT = cueSet( 'have has having got getting feel feeling still currently now abhi bhi hai hain ho raha rahi rahe hota hoti' );
	// Roman Urdu copulas end a phrase ("khansi hai, bukhar nahi").
	var URDU_END = cueSet( 'hai hain tha thi thay' );
	// Not symptom words, so a cue on its own never becomes a "symptom".
	var NEUTRAL = cueSet( 'do does did any koi but went away better ab it this that yes' );

	function rawWords( text ) {
		return String( text || '' )
			.toLowerCase()
			.replace( /['’]/g, '' )
			.replace( /[^\p{L}\p{N}]+/gu, ' ' )
			.trim()
			.split( ' ' )
			.filter( Boolean );
	}

	function isCue( w ) {
		return NEG_BEFORE[ w ] || NEG_AFTER[ w ] || RESOLVED[ w ] || PAST[ w ] || PRESENT[ w ] || NEUTRAL[ w ];
	}

	/**
	 * Splits one clause into phrases at negation boundaries ("cough no
	 * fever" → "cough" + "no fever"; "bukhar nahi hai khansi hai" →
	 * "bukhar nahi hai" + "khansi hai") and labels each.
	 *
	 * @return {Array} [{ words: [], polarity: 'present'|'absent'|'resolved'|'past'|'neutral', explicit: bool }]
	 */
	function segments( words ) {
		var out = [];
		var cur = { words: [], neg: false };

		function content( seg ) {
			return seg.words.some( function ( w ) {
				return ! isCue( w ) && ! FILLERS[ w ] && ! /^\d+$/.test( w );
			} );
		}

		function close() {
			if ( cur.words.length ) {
				out.push( cur );
			}
			cur = { words: [], neg: false };
		}

		for ( var i = 0; i < words.length; i++ ) {
			var w = words[ i ];

			if ( NEG_BEFORE[ w ] ) {
				if ( content( cur ) && ! cur.neg ) {
					close();
				}

				cur.neg = true;
				cur.words.push( w );
			} else if ( NEG_AFTER[ w ] ) {
				cur.neg = true;
				cur.words.push( w );

				while ( i + 1 < words.length && NEG_TAIL[ words[ i + 1 ] ] ) {
					cur.words.push( words[ ++i ] );
				}

				close();
			} else if ( URDU_END[ w ] && content( cur ) ) {
				cur.words.push( w );
				close();
			} else {
				cur.words.push( w );
			}
		}

		close();

		var labelled = out.map( function ( seg ) {
			var ws = seg.words;
			var joined = ' ' + ws.join( ' ' ) + ' ';
			var resolved = ws.some( function ( w ) {
				return RESOLVED[ w ];
			} ) || Object.keys( RESOLVED_PAIRS ).some( function ( p ) {
				return joined.indexOf( ' ' + p + ' ' ) !== -1;
			} );
			var present = ws.some( function ( w ) {
				return PRESENT[ w ];
			} );
			var past = ws.some( function ( w ) {
				return PAST[ w ];
			} );
			var polarity;

			if ( resolved ) {
				polarity = 'resolved';
			} else if ( seg.neg ) {
				polarity = 'absent';
			} else if ( past && ! ws.some( function ( w ) {
				return 'still' === w || 'now' === w || 'currently' === w || 'abhi' === w;
			} ) ) {
				polarity = 'past';
			} else {
				polarity = present ? 'present' : 'neutral';
			}

			return { words: ws, polarity: polarity, content: content( seg ) };
		} );

		// "bukhar tha, ab nahi" — a bare "not now" after a past phrase means
		// it has gone.
		labelled.forEach( function ( seg, i ) {
			if ( i > 0 && ! seg.content && ( 'absent' === seg.polarity || 'resolved' === seg.polarity ) && 'past' === labelled[ i - 1 ].polarity ) {
				labelled[ i - 1 ].polarity = 'resolved';
			}
		} );

		return labelled;
	}

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

		/**
		 * Splits free text into clauses, remembering what joined each one to
		 * the one before: 'list' (comma, semicolon, slash, new line), 'and',
		 * 'or', 'but', or 'stop' (full stop / start).
		 *
		 * @return {Array} [{ text, connector }]
		 */
		function clauses( text ) {
			var out = [];
			var re = /([.]+|[,;\n/]+)|\s+(and|&|plus|with|aur|or|ya|nor|but|though|although|however|except|lekin|magar|laikin|lekn)\s+/gi;
			var str = String( text || '' );
			var last = 0;
			var connector = 'stop';
			var match;

			function push( part, next ) {
				part = part.trim();

				if ( part ) {
					out.push( { text: part, connector: connector } );
				}

				connector = next;
			}

			while ( null !== ( match = re.exec( str ) ) ) {
				var kind;

				if ( match[ 1 ] ) {
					kind = /\./.test( match[ 1 ] ) ? 'stop' : 'list';
				} else {
					var w = match[ 2 ].toLowerCase();
					kind = /^(or|ya|nor)$/.test( w ) ? 'or' : ( /^(and|&|plus|with|aur)$/.test( w ) ? 'and' : 'but' );
				}

				push( str.slice( last, match.index ), kind );
				last = re.lastIndex;
			}

			push( str.slice( last ), 'stop' );

			return out;
		}

		/** Splits free text into phrases ("pain in stomach and fever, cough"). */
		function split( text ) {
			return clauses( text ).map( function ( c ) {
				return c.text;
			} );
		}

		/**
		 * Symptoms a phrase could mean.
		 *
		 * @param {Array} t tokens().
		 * @return {Object} { exact: id|null, candidates: [ids] }
		 */
		function lookup( t ) {
			var hit = exact[ t.join( ' ' ) ];

			if ( hit && 1 === hit.length ) {
				return { exact: hit[ 0 ], candidates: [ hit[ 0 ] ] };
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

			return {
				exact: null,
				candidates: Object.keys( scores ).sort( function ( a, b ) {
					return scores[ a ] - scores[ b ] || byId[ a ].name.localeCompare( byId[ b ].name );
				} ).slice( 0, 5 ).map( function ( id ) {
					return byId[ id ].id;
				} ),
			};
		}

		/**
		 * Reads free text, keeping what the patient reports apart from what
		 * they deny or say has gone. Only reported, exact matches are added
		 * without asking.
		 *
		 * @return {Object} {
		 *   recognized: [ids]                                — reported, exact matches,
		 *   ambiguous:  [{ phrase, candidates: [ids] }]      — reported, needs "did you mean",
		 *   unknown:    [phrase]                             — no reliable match,
		 *   negated:    [{ phrase, ids: [ids] }]             — the patient says they don't have it,
		 *   resolved:   [{ phrase, ids: [ids] }]             — the patient says it has gone,
		 *   confirm:    [{ phrase, candidates: [ids], reason: 'past'|'unclear' }] — ask whether they have it now
		 * }
		 */
		function analyse( text ) {
			var result = { recognized: [], ambiguous: [], unknown: [], negated: [], resolved: [], confirm: [] };
			var prev = null;

			clauses( text ).forEach( function ( clause ) {
				var words = rawWords( clause.text );

				if ( ! words.length ) {
					return;
				}

				if ( 'stop' === clause.connector || 'but' === clause.connector ) {
					prev = null;
				}

				// A symptom phrase that itself contains a cue word ("not able
				// to sleep", "neend nahi aati") is taken exactly as written.
				var cueKey = words.filter( function ( w ) {
					return ( ! FILLERS[ w ] || isCue( w ) ) && ! /^\d+$/.test( w );
				} ).map( stem );
				var whole = cueKey.some( isCue ) ? exact[ cueKey.join( ' ' ) ] : null;
				var segs = whole && 1 === whole.length
					? [ { words: words, polarity: 'present', explicit: true, id: whole[ 0 ] } ]
					: segments( words );

				segs.forEach( function ( seg, si ) {
					var polarity = seg.polarity;

					if ( 'neutral' === polarity ) {
						if ( 0 === si && 'absent' === prev ) {
							// "no fever or cough" denies both; "no fever and cough" /
							// "no fever, cough" could mean either — ask.
							polarity = 'or' === clause.connector ? 'absent' : 'unclear';
						} else {
							polarity = 'present';
						}
					}

					prev = 'unclear' === polarity ? 'absent' : polarity;

					var contentWords = seg.words.filter( function ( w ) {
						return ! isCue( w );
					} );
					var phrase = seg.words.join( ' ' );
					var found;

					if ( seg.id ) {
						found = { exact: seg.id, candidates: [ seg.id ] };
					} else {
						var t = tokens( contentWords.join( ' ' ) );

						if ( ! t.length ) {
							return;
						}

						found = lookup( t );
					}

					if ( 'present' === polarity ) {
						if ( null !== found.exact ) {
							if ( result.recognized.indexOf( found.exact ) === -1 ) {
								result.recognized.push( found.exact );
							}
						} else if ( found.candidates.length ) {
							result.ambiguous.push( { phrase: phrase, candidates: found.candidates } );
						} else {
							result.unknown.push( phrase );
						}
					} else if ( 'absent' === polarity || 'resolved' === polarity ) {
						result[ 'absent' === polarity ? 'negated' : 'resolved' ].push( {
							phrase: phrase,
							ids: null !== found.exact ? [ found.exact ] : found.candidates,
						} );
					} else if ( found.candidates.length ) {
						result.confirm.push( { phrase: phrase, candidates: found.candidates, reason: polarity } );
					} else {
						result.unknown.push( phrase );
					}
				} );
			} );

			// Reported and denied in the same message ("fever, no fever"): ask.
			result.negated.concat( result.resolved ).forEach( function ( item ) {
				item.ids.forEach( function ( id ) {
					var at = result.recognized.indexOf( id );

					if ( at !== -1 ) {
						result.recognized.splice( at, 1 );
						result.confirm.push( { phrase: byId[ id ].name, candidates: [ id ], reason: 'unclear' } );
					}
				} );
			} );

			return result;
		}


		/**
		 * Every follow-up question of the chosen symptoms, in order.
		 *
		 * @return {Array} [{ key, symptomId, symptomName, question }]
		 */
		function questionsFor( ids ) {
			var out = [];

			( ids || [] ).forEach( function ( id ) {
				var m = byId[ id ];

				( m && m.questions || [] ).forEach( function ( q ) {
					out.push( { key: id + ':' + q.id, symptomId: id, symptomName: m.name, question: q } );
				} );
			} );

			return out;
		}

		/**
		 * The questions to ask given the answers so far: a show_if question
		 * only while its (visible) parent has one of the listed answers.
		 *
		 * @param {Array}  ids     Chosen symptom ids.
		 * @param {Object} answers key -> answer.
		 * @return {Array} Same shape as questionsFor().
		 */
		function visibleQuestions( ids, answers ) {
			var shown = {};

			return questionsFor( ids ).filter( function ( item ) {
				var cond = item.question.show_if;
				var ok = true;

				if ( cond && cond.question ) {
					var parentKey = item.symptomId + ':' + cond.question;
					ok = !! shown[ parentKey ] && chose( ( answers || {} )[ parentKey ], cond.answers || [] );
				}

				if ( ok ) {
					shown[ item.key ] = true;
				}

				return ok;
			} );
		}

		/**
		 * Specialties linked to the confirmed symptoms (and to answers a
		 * clinician linked to specialties), most-linked first.
		 *
		 * @param {Array}  ids     Chosen symptom ids.
		 * @param {Object} answers key -> answer (optional).
		 * @return {Array} [{ slug, count, symptoms: [names], answers: ['Symptom: answer'] }]
		 */
		function specialtiesFor( ids, answers ) {
			var map = {};
			var order = [];

			function add( slug, symptom, via ) {
				if ( ! map[ slug ] ) {
					map[ slug ] = { slug: slug, count: 0, symptoms: [], answers: [] };
					order.push( slug );
				}

				map[ slug ].count += 1;

				if ( via ) {
					map[ slug ].answers.push( via );
				} else if ( map[ slug ].symptoms.indexOf( symptom ) === -1 ) {
					map[ slug ].symptoms.push( symptom );
				}
			}

			( ids || [] ).forEach( function ( id ) {
				var m = byId[ id ];

				( m && m.specialties || [] ).forEach( function ( slug ) {
					add( slug, m.name );
				} );
			} );

			if ( answers ) {
				visibleQuestions( ids, answers ).forEach( function ( item ) {
					var q = item.question;

					if ( ! q.affects || ! q.affects.matching ) {
						return;
					}

					( q.options || [] ).forEach( function ( o ) {
						if ( chose( answers[ item.key ], [ o.id ] ) ) {
							( o.specialties || [] ).forEach( function ( slug ) {
								add( slug, item.symptomName, item.symptomName + ': ' + o.label );
							} );
						}
					} );
				} );
			}

			return order.map( function ( slug ) {
				return map[ slug ];
			} ).sort( function ( a, b ) {
				return b.count - a.count || order.indexOf( a.slug ) - order.indexOf( b.slug );
			} );
		}

		/**
		 * Approved safety rules whose conditions the current selection and
		 * answers meet - emergency before urgent, then in configured order.
		 * Only answers to questions still being asked count.
		 *
		 * @param {Array}  rules   [{ id, level, triggers: [{ symptom, question, answers, min, max }] }]
		 * @param {Array}  ids     Chosen symptom ids.
		 * @param {Object} answers key -> answer.
		 * @return {Array} Matching rules.
		 */
		function triggeredRules( rules, ids, answers ) {
			var chosen = {};
			var visible = {};

			( ids || [] ).forEach( function ( id ) {
				chosen[ id ] = true;
			} );

			visibleQuestions( ids, answers ).forEach( function ( item ) {
				visible[ item.key ] = item.question;
			} );

			return ( rules || [] ).filter( function ( rule ) {
				return ( rule.triggers || [] ).some( function ( t ) {
					if ( ! chosen[ t.symptom ] ) {
						return false;
					}

					if ( ! t.question ) {
						return true;
					}

					var k = t.symptom + ':' + t.question;
					var q = visible[ k ];
					var a = ( answers || {} )[ k ];

					if ( ! q || ! isAnswered( q, a ) ) {
						return false;
					}

					if ( 'number' === q.type ) {
						return ( ! isNum( t.min ) || a >= t.min ) && ( ! isNum( t.max ) || a <= t.max );
					}

					return chose( a, t.answers || [] );
				} );
			} ).map( function ( rule, i ) {
				return { rule: rule, i: i };
			} ).sort( function ( a, b ) {
				return ( 'emergency' === b.rule.level ? 1 : 0 ) - ( 'emergency' === a.rule.level ? 1 : 0 ) || a.i - b.i;
			} ).map( function ( x ) {
				return x.rule;
			} );
		}

		/**
		 * The answers still relevant: drops answers to questions of symptoms
		 * no longer chosen, and to questions hidden by a changed earlier
		 * answer (repeated, since hiding one can hide another).
		 *
		 * @param {Array}  ids     Chosen symptom ids.
		 * @param {Object} answers key -> answer.
		 * @return {Object} A new answers object.
		 */
		function pruneAnswers( ids, answers ) {
			var out = {};

			Object.keys( answers || {} ).forEach( function ( k ) {
				out[ k ] = answers[ k ];
			} );

			for ( var changed = true; changed; ) {
				var keep = {};
				changed = false;

				visibleQuestions( ids, out ).forEach( function ( item ) {
					keep[ item.key ] = true;
				} );

				Object.keys( out ).forEach( function ( k ) {
					if ( ! keep[ k ] ) {
						delete out[ k ];
						changed = true;
					}
				} );
			}

			return out;
		}

		/**
		 * The questions to ask, with identical questions from several chosen
		 * symptoms asked once. Questions count as identical when their type,
		 * wording and answers (or number range) are the same.
		 *
		 * @return {Array} [{ key, question, symptomId, symptomName, symptomNames: [], members: [visibleQuestions() items] }]
		 */
		function askList( ids, answers ) {
			var groups = [];
			var bySig = {};

			visibleQuestions( ids, answers ).forEach( function ( item ) {
				var sig = signature( item.question );
				var g = bySig[ sig ];

				if ( g ) {
					g.members.push( item );

					if ( g.symptomNames.indexOf( item.symptomName ) === -1 ) {
						g.symptomNames.push( item.symptomName );
					}

					return;
				}

				g = {
					key: item.key,
					question: item.question,
					symptomId: item.symptomId,
					symptomName: item.symptomName,
					symptomNames: [ item.symptomName ],
					members: [ item ],
				};
				bySig[ sig ] = g;
				groups.push( g );
			} );

			return groups;
		}

		return {
			byId: byId,
			suggest: suggest,
			analyse: analyse,
			split: split,
			clauses: clauses,
			specialtiesFor: specialtiesFor,
			questionsFor: questionsFor,
			visibleQuestions: visibleQuestions,
			pruneAnswers: pruneAnswers,
			askList: askList,
			triggeredRules: triggeredRules,
		};
	}

	function plain( text ) {
		return String( text || '' ).toLowerCase().replace( /[^\p{L}\p{N}]+/gu, ' ' ).trim();
	}

	/** What makes two questions "the same question" for asking once. */
	function signature( q ) {
		var parts = [ q.type, plain( q.text ) ];

		if ( 'number' === q.type ) {
			var n = q.number || {};
			parts.push( String( n.min ), String( n.max ), plain( n.unit ) );
		} else {
			parts.push( ( q.options || [] ).map( function ( o ) {
				return plain( o.label );
			} ).join( '|' ) );
		}

		return parts.join( '#' );
	}

	/**
	 * Carries an answer from one question to an identical one (same
	 * signature) whose answer ids may differ: matched by position.
	 */
	function mapAnswer( from, to, answer ) {
		if ( 'number' === from.type || from === to ) {
			return answer;
		}

		var fromIds = ( from.options || [] ).map( function ( o ) {
			return o.id;
		} );
		var toIds = ( to.options || [] ).map( function ( o ) {
			return o.id;
		} );
		var one = function ( id ) {
			var at = fromIds.indexOf( id );
			return at === -1 ? undefined : toIds[ at ];
		};

		if ( Array.isArray( answer ) ) {
			return answer.map( one ).filter( function ( id ) {
				return undefined !== id;
			} );
		}

		return one( answer );
	}

	/** The first emergency rule among triggered rules, or null. */
	function emergencyRule( triggered ) {
		return ( triggered || [] ).filter( function ( r ) {
			return 'emergency' === r.level;
		} )[ 0 ] || null;
	}

	/** Whether an answer (one id, or a list of ids) includes any of `ids`. */
	function chose( answer, ids ) {
		var given = Array.isArray( answer ) ? answer : ( undefined === answer || null === answer || '' === answer ? [] : [ answer ] );

		return given.some( function ( a ) {
			return ids.indexOf( String( a ) ) !== -1;
		} );
	}

	/**
	 * Whether a question has a usable answer: a known option, at least one
	 * known option (multi), or a number within the allowed range.
	 */
	function isAnswered( q, answer ) {
		if ( ! q ) {
			return false;
		}

		if ( 'number' === q.type ) {
			var n = q.number || {};

			return isNum( answer ) && ( ! isNum( n.min ) || answer >= n.min ) && ( ! isNum( n.max ) || answer <= n.max );
		}

		var ids = ( q.options || [] ).map( function ( o ) {
			return o.id;
		} );

		if ( 'multi' === q.type ) {
			return Array.isArray( answer ) && answer.length > 0 && answer.every( function ( a ) {
				return ids.indexOf( a ) !== -1;
			} );
		}

		return 'string' === typeof answer && ids.indexOf( answer ) !== -1;
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
		signature: signature,
		mapAnswer: mapAnswer,
		emergencyRule: emergencyRule,
		isAnswered: isAnswered,
		rankDoctors: rankDoctors,
		nearbyPlace: nearbyPlace,
		haversineKm: haversineKm,
		tokens: tokens,
	};
} ) );
