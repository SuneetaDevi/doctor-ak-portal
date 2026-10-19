/**
 * Doctor AK Portal — "Find a doctor by symptoms" page.
 *
 * 1. Enter symptoms (confirm anything unclear).
 * 2. Answer the clinician-approved follow-up questions, one at a time, with
 *    a chance to review and change answers. Questions only appear when a
 *    symptom has approved ones.
 * 3. Doctors in related specialties, nearest first → the normal profile /
 *    booking pages.
 *
 * Safety: approved safety rules are checked after every change. A matching
 * rule's instructions appear at once, above everything else, and can't be
 * dismissed while it applies.
 * - Emergency rules stop everything: no further questions, doctors or
 *   booking links while they apply — there is no way past them except
 *   correcting the symptoms or answers, after which every rule is checked
 *   again.
 * - Urgent rules set to interrupt stop the questions; the patient can then
 *   continue to doctors.
 * Nothing here scores, diagnoses or says that anything has been ruled out.
 * Symptoms the patient says they don't have are never added, and are
 * never taken as a sign that anything is fine.
 *
 * Privacy: everything stays in this page's memory — nothing is sent to the
 * site, written to the URL, cookies or storage, or logged. The location is
 * only requested on the results step, only used to sort by straight-line
 * distance, and forgotten when the page is left. Booking links carry the
 * doctor and clinic only, never symptoms or answers.
 */
( function () {
	'use strict';

	var data = window.dakSymptomFinder;
	var Match = window.DAKSymptomMatch;
	var root = document.querySelector( '[data-dak-sf]' );

	if ( ! data || ! Match || ! root ) {
		return;
	}

	var m = Match.create( data.mappings || [] );
	var rules = data.rules || [];
	var input = root.querySelector( '[data-sf-input]' );
	var list = root.querySelector( '[data-sf-suggestions]' );
	var clarify = root.querySelector( '[data-sf-clarify]' );
	var chipsWrap = root.querySelector( '[data-sf-selected-wrap]' );
	var chips = root.querySelector( '[data-sf-chips]' );
	var errorEl = root.querySelector( '[data-sf-error]' );
	var results = root.querySelector( '[data-sf-results]' );
	var live = root.querySelector( '[data-sf-live]' );
	var alerts = root.querySelector( '[data-sf-alerts]' );
	var stepSymptoms = root.querySelector( '[data-sf-step="symptoms"]' );
	var stepQuestions = root.querySelector( '[data-sf-step="questions"]' );

	// Keeps a triggered safety message one tap away while the patient
	// scrolls through questions or doctors (it never replaces the message).
	var dock = h( 'div', { class: 'dak-sf-dock', hidden: true } );
	root.appendChild( dock );

	var selected = [];      // mapping ids, in the order added
	var questions = [];     // { phrase, candidates, kind: 'which'|'current', reason } awaiting a choice
	var unmatched = [];     // phrases with no reliable match
	var denied = [];        // { phrase, ids, gone } — said they don't have it (never added)
	var suggestions = [];
	var active = -1;

	var flow = {
		step: 'symptoms',   // 'symptoms' | 'questions' | 'results'
		answers: {},        // 'symptomId:questionId' → answer
		index: 0,           // position among the visible questions
		reviewing: false,   // showing the "check your answers" list
		shownRules: {},     // rule ids already on screen
		editing: false,     // the patient chose to change answers after a stop
		urgentSeen: false,  // the patient read an interrupting urgent message and chose to continue
		skipped: {},        // question keys the patient chose to skip
	};

	var loc = { status: 'idle', origin: null, place: '', mode: 'auto', city: '', area: '', panel: false };

	/* ------------------------------------------------------------------ */

	function h( tag, attrs, kids ) {
		var el = document.createElement( tag );

		Object.keys( attrs || {} ).forEach( function ( k ) {
			var v = attrs[ k ];

			if ( null === v || undefined === v || false === v ) {
				return;
			}

			if ( 'class' === k ) {
				el.className = v;
			} else if ( 'text' === k ) {
				el.textContent = v;
			} else if ( 'checked' === k ) {
				el.checked = !! v;
			} else if ( 'value' === k ) {
				el.value = v;
			} else if ( 'on' === k.slice( 0, 2 ) ) {
				el.addEventListener( k.slice( 2 ), v );
			} else {
				el.setAttribute( k, true === v ? '' : v );
			}
		} );

		( kids || [] ).forEach( function ( kid ) {
			if ( kid ) {
				el.appendChild( 'string' === typeof kid ? document.createTextNode( kid ) : kid );
			}
		} );

		return el;
	}

	function safeUrl( url ) {
		return /^https?:\/\//i.test( String( url || '' ) ) ? url : null;
	}

	function announce( text ) {
		live.textContent = '';
		setTimeout( function () {
			live.textContent = text;
		}, 40 );
	}

	function nameOf( id ) {
		return m.byId[ id ] ? m.byId[ id ].name : '';
	}

	function specialtyLabel( slug ) {
		return ( data.specialtyLabels && data.specialtyLabels[ slug ] ) || slug.replace( /-/g, ' ' );
	}

	function showError( text ) {
		errorEl.textContent = text;
		errorEl.hidden = ! text;
	}

	function focusEl( el ) {
		if ( ! el ) {
			return;
		}

		if ( ! el.hasAttribute( 'tabindex' ) && ! /^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test( el.tagName ) ) {
			el.setAttribute( 'tabindex', '-1' );
		}

		el.focus();
	}

	/** Splits clinician-written text into paragraphs. */
	function paragraphs( text, cls ) {
		return String( text || '' ).split( /\n+/ ).map( function ( line ) {
			return line.trim();
		} ).filter( Boolean ).map( function ( line ) {
			return h( 'p', { class: cls || null, text: line } );
		} );
	}

	/* ------------------------------------------------------------------
	 * Steps
	 * ---------------------------------------------------------------- */

	/** The questions to ask now — identical questions from several symptoms once. */
	function asked() {
		return m.askList( selected, flow.answers );
	}

	function triggered() {
		return m.triggeredRules( rules, selected, flow.answers );
	}

	/**
	 * An emergency rule that applies now. While one does, the page shows no
	 * further questions, doctors or booking links — only the instructions
	 * and ways to correct what was entered.
	 */
	function emergencyNow() {
		return Match.emergencyRule( triggered() );
	}

	/** An urgent rule set to interrupt the questions (not an emergency). */
	function urgentStop() {
		return triggered().filter( function ( r ) {
			return 'emergency' !== r.level && r.interrupt;
		} )[ 0 ] || null;
	}

	function stopRule() {
		return emergencyNow() || urgentStop();
	}

	/** Drops answers that no longer apply, after any change. */
	function prune() {
		flow.answers = m.pruneAnswers( selected, flow.answers );
	}

	function renderSteps() {
		var order = [ 'symptoms', 'questions', 'results' ];
		var current = order.indexOf( flow.step );
		var noQuestions = ! m.questionsFor( selected ).length;

		Array.prototype.forEach.call( root.querySelectorAll( '[data-sf-step-item]' ), function ( li ) {
			var i = order.indexOf( li.getAttribute( 'data-sf-step-item' ) );

			li.classList.toggle( 'is-current', i === current );
			li.classList.toggle( 'is-done', i < current );
			li.classList.toggle( 'is-skipped', 'questions' === order[ i ] && noQuestions && selected.length > 0 );

			if ( i === current ) {
				li.setAttribute( 'aria-current', 'step' );
			} else {
				li.removeAttribute( 'aria-current' );
			}
		} );
	}

	/**
	 * @param {string}  step  'symptoms' | 'questions' | 'results'.
	 * @param {boolean} focus Move focus to the step's heading.
	 */
	function goTo( step, focus ) {
		// Doctors and booking links are never shown while an emergency rule
		// applies — whatever led here (a button, Back, a restored page).
		if ( 'results' === step && emergencyNow() ) {
			flow.editing = false;
			step = 'questions';
		}

		flow.step = step;
		stepSymptoms.hidden = 'symptoms' !== step;
		stepQuestions.hidden = 'questions' !== step;
		results.hidden = 'results' !== step;

		if ( 'results' !== step ) {
			// Nothing stale left behind to reappear later.
			results.textContent = '';
		}

		renderSteps();
		renderAlerts( false );

		if ( 'questions' === step ) {
			renderQuestions( focus );
		} else if ( 'results' === step ) {
			if ( m.specialtiesFor( selected, flow.answers ).length ) {
				startLocation();
			}

			renderResults( 'Results updated.', focus );
		} else if ( focus ) {
			input.focus();
		}

		if ( focus ) {
			var top = alerts.hidden ? root.querySelector( '.dak-sf-steps' ) : alerts;

			if ( top && top.scrollIntoView ) {
				top.scrollIntoView( { block: 'start' } );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Safety messages
	 * ---------------------------------------------------------------- */

	/**
	 * Shows every approved safety rule the current symptoms and answers
	 * meet. New ones are announced; an interrupting one takes focus.
	 *
	 * @param {boolean} takeFocus Whether a newly shown stopping rule may take focus.
	 * @return {boolean} Whether a stopping rule appeared just now.
	 */
	function renderAlerts( takeFocus ) {
		var now = triggered();
		var ids = {};
		var fresh = [];

		now.forEach( function ( rule ) {
			ids[ rule.id ] = true;

			if ( ! flow.shownRules[ rule.id ] ) {
				fresh.push( rule );
			}
		} );

		flow.shownRules = ids;
		alerts.textContent = '';
		alerts.hidden = ! now.length;
		renderDock( now );

		now.forEach( function ( rule ) {
			var emergency = 'emergency' === rule.level;
			var box = h( 'section', {
				class: 'dak-sf-alert ' + ( emergency ? 'is-emergency' : 'is-urgent' ),
				'aria-labelledby': 'dak-sf-alert-' + rule.id,
				'data-rule': String( rule.id ),
			} );

			box.appendChild( h( 'p', { class: 'dak-sf-alert-tag', text: emergency ? 'Emergency' : 'Urgent' } ) );
			box.appendChild( h( 'h2', { id: 'dak-sf-alert-' + rule.id, class: 'dak-sf-alert-title', text: rule.title } ) );
			paragraphs( rule.message ).forEach( function ( p ) {
				box.appendChild( p );
			} );

			if ( data.preview ) {
				box.appendChild( h( 'p', { class: 'dak-sf-alert-meta', text: 'Staff preview: safety rule #' + rule.id + '.' } ) );
			}

			alerts.appendChild( box );
		} );

		if ( ! fresh.length ) {
			return false;
		}

		var stopping = fresh.filter( function ( r ) {
			return r.interrupt || 'emergency' === r.level;
		} )[ 0 ];

		announce( ( 'emergency' === fresh[ 0 ].level ? 'Emergency: ' : 'Important: ' ) + fresh[ 0 ].title + '. ' + fresh[ 0 ].message );

		if ( stopping && takeFocus ) {
			var heading = document.getElementById( 'dak-sf-alert-' + stopping.id );
			alerts.scrollIntoView( { block: 'start' } );
			focusEl( heading );
		}

		return !! stopping;
	}

	function renderDock( now ) {
		dock.textContent = '';
		dock.hidden = ! now.length;
		root.classList.toggle( 'has-dock', !! now.length );

		if ( ! now.length ) {
			return;
		}

		var first = now[ 0 ];
		var emergency = 'emergency' === first.level;

		dock.className = 'dak-sf-dock ' + ( emergency ? 'is-emergency' : 'is-urgent' );
		dock.appendChild( h( 'p', {}, [ h( 'strong', { text: ( emergency ? 'Emergency' : 'Urgent' ) + ': ' } ), first.title ] ) );
		dock.appendChild( h( 'button', { type: 'button', class: 'dak-sf-dock-btn', text: 'See instructions', onclick: function () {
			alerts.scrollIntoView( { block: 'start' } );
			focusEl( document.getElementById( 'dak-sf-alert-' + first.id ) );
		} } ) );
	}

	/* ------------------------------------------------------------------
	 * Symptoms: suggestions, chips, clarification
	 * ---------------------------------------------------------------- */

	function closeList() {
		list.hidden = true;
		list.textContent = '';
		input.setAttribute( 'aria-expanded', 'false' );
		input.removeAttribute( 'aria-activedescendant' );
		active = -1;
	}

	function highlight( index ) {
		var items = list.querySelectorAll( '[role="option"]' );

		Array.prototype.forEach.call( items, function ( li ) {
			li.classList.remove( 'is-active' );
			li.setAttribute( 'aria-selected', 'false' );
		} );

		if ( index < 0 || ! items.length ) {
			active = -1;
			input.removeAttribute( 'aria-activedescendant' );
			return;
		}

		active = Math.max( 0, Math.min( index, items.length - 1 ) );
		items[ active ].classList.add( 'is-active' );
		items[ active ].setAttribute( 'aria-selected', 'true' );
		input.setAttribute( 'aria-activedescendant', items[ active ].id );
	}

	function renderSuggestions() {
		suggestions = m.suggest( input.value, 8 ).filter( function ( s ) {
			return selected.indexOf( s.id ) === -1;
		} );

		list.textContent = '';

		if ( ! suggestions.length ) {
			closeList();
			return;
		}

		suggestions.forEach( function ( s, i ) {
			var li = h( 'li', { role: 'option', id: 'dak-sf-opt-' + i, 'aria-selected': 'false', class: 'dak-sf-option' }, [
				h( 'span', { text: s.name } ),
				s.via ? h( 'small', { text: '“' + s.via + '”' } ) : null,
			] );

			li.addEventListener( 'mousedown', function ( event ) {
				event.preventDefault();
			} );
			li.addEventListener( 'click', function () {
				addSymptom( s.id );
				input.value = '';
				closeList();
				input.focus();
			} );
			list.appendChild( li );
		} );

		list.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
		active = -1;
	}

	function addSymptom( id ) {
		if ( selected.indexOf( id ) === -1 ) {
			selected.push( id );
			announce( nameOf( id ) + ' added' );
		}

		showError( '' );
		prune();
		renderChips();
		renderSteps();
		renderAlerts( false );
	}

	function removeSymptom( id ) {
		selected = selected.filter( function ( x ) {
			return x !== id;
		} );
		announce( nameOf( id ) + ' removed' );
		prune();
		renderChips();
		renderSteps();
		renderAlerts( false );
	}

	function chipLabel( id ) {
		var row = m.byId[ id ];

		return nameOf( id ) + ( data.preview && row && row.status && 'active' !== row.status ? ' (' + row.status + ')' : '' );
	}

	function renderChips() {
		chips.textContent = '';
		chipsWrap.hidden = ! selected.length;

		selected.forEach( function ( id ) {
			var row = m.byId[ id ];

			chips.appendChild(
				h( 'li', { class: 'dak-sf-chip' }, [
					h( 'span', { text: chipLabel( id ) } ),
					h( 'button', {
						type: 'button',
						class: 'dak-sf-chip-remove',
						'aria-label': 'Remove ' + nameOf( id ),
						text: '×',
						onclick: function () {
							removeSymptom( id );
							input.focus();
						},
					} ),
				] )
			);

			if ( row && row.description ) {
				chips.lastChild.setAttribute( 'title', row.description );
			}
		} );

		var guidance = selected.map( function ( id ) {
			return m.byId[ id ] && m.byId[ id ].description ? { name: nameOf( id ), text: m.byId[ id ].description } : null;
		} ).filter( Boolean );

		var old = chipsWrap.querySelector( '.dak-sf-guidance' );

		if ( old ) {
			old.remove();
		}

		if ( guidance.length ) {
			chipsWrap.appendChild( h( 'div', { class: 'dak-sf-guidance' }, guidance.map( function ( g ) {
				return h( 'p', {}, [ h( 'strong', { text: g.name + ': ' } ), g.text ] );
			} ) ) );
		}
	}

	/** Reads typed text: exact matches become chips, the rest is asked about. */
	function readText( text ) {
		if ( ! String( text || '' ).trim() ) {
			return;
		}

		var r = m.analyse( text );
		var notSelected = function ( id ) {
			return selected.indexOf( id ) === -1;
		};

		r.recognized.forEach( addSymptom );

		r.ambiguous.forEach( function ( q ) {
			var left = q.candidates.filter( notSelected );

			if ( left.length ) {
				questions.push( { phrase: q.phrase, candidates: left, kind: 'which' } );
			}
		} );

		// Past or unclear wording ("had a fever last week", "no fever and
		// cough"): ask before adding anything.
		r.confirm.forEach( function ( q ) {
			var left = q.candidates.filter( notSelected );

			if ( left.length ) {
				questions.push( { phrase: q.phrase, candidates: left, kind: 'current', reason: q.reason } );
			}
		} );

		r.negated.concat( r.resolved ).forEach( function ( item ) {
			var gone = r.resolved.indexOf( item ) !== -1;
			var known = denied.some( function ( d ) {
				return d.phrase === item.phrase;
			} );

			if ( ! known ) {
				denied.push( { phrase: item.phrase, ids: item.ids, gone: gone } );
			}
		} );

		r.unknown.forEach( function ( phrase ) {
			if ( unmatched.indexOf( phrase ) === -1 ) {
				unmatched.push( phrase );
			}
		} );

		input.value = '';
		closeList();
		renderClarify();

		if ( questions.length || unmatched.length || r.negated.length || r.resolved.length ) {
			announce( 'Some of what you typed needs checking. See below the box.' );
		}
	}

	/** Ids the patient said they don't have, that are still in their list. */
	function deniedButSelected() {
		var out = [];

		denied.forEach( function ( d ) {
			d.ids.forEach( function ( id ) {
				if ( selected.indexOf( id ) !== -1 && out.indexOf( id ) === -1 && 1 === d.ids.length ) {
					out.push( id );
				}
			} );
		} );

		return out;
	}

	function renderClarify() {
		clarify.textContent = '';
		clarify.hidden = ! questions.length && ! unmatched.length && ! denied.length;

		questions.forEach( function ( q, qi ) {
			if ( 'current' === q.kind ) {
				clarify.appendChild( currentBox( q, qi ) );
				return;
			}

			var box = h( 'div', { class: 'dak-sf-q', role: 'group', 'aria-label': 'Did you mean, for ' + q.phrase } );

			box.appendChild( h( 'p', {}, [ h( 'strong', { text: '“' + q.phrase + '”' } ), ' — did you mean:' ] ) );

			var choices = h( 'div', { class: 'dak-sf-q-choices' } );

			q.candidates.forEach( function ( id ) {
				choices.appendChild( h( 'button', {
					type: 'button',
					class: 'dak-sf-choice',
					text: nameOf( id ),
					onclick: function () {
						questions.splice( qi, 1 );
						addSymptom( id );
						renderClarify();
						input.focus();
					},
				} ) );
			} );

			choices.appendChild( h( 'button', {
				type: 'button',
				class: 'dak-sf-choice is-none',
				text: 'None of these',
				onclick: function () {
					questions.splice( qi, 1 );
					unmatched.push( q.phrase );
					renderClarify();
					input.focus();
				},
			} ) );

			box.appendChild( choices );
			clarify.appendChild( box );
		} );

		if ( denied.length ) {
			clarify.appendChild( deniedBox() );
		}

		if ( unmatched.length ) {
			var note = h( 'div', { class: 'dak-sf-q is-unknown' } );

			note.appendChild( h( 'p', { text: 'We couldn’t match ' + unmatched.map( function ( p ) {
				return '“' + p + '”';
			} ).join( ', ' ) + ' to a symptom in our list. Try other words (English or Roman Urdu), or browse doctors yourself.' } ) );
			note.appendChild( h( 'button', {
				type: 'button',
				class: 'dak-sf-link',
				text: 'Dismiss',
				onclick: function () {
					unmatched = [];
					renderClarify();
					input.focus();
				},
			} ) );
			clarify.appendChild( note );
		}
	}

	/**
	 * "Do you have this now?" — for past ("had a fever last week") or
	 * unclear ("no fever and cough") wording. Nothing is added until the
	 * patient says yes.
	 */
	function currentBox( q, qi ) {
		var one = 1 === q.candidates.length;
		var prompt = 'past' === q.reason ? ' — do you have this now?' : ' — do you have this?';
		var box = h( 'div', { class: 'dak-sf-q', role: 'group', 'aria-label': 'Do you have this now, for ' + q.phrase } );
		var choices = h( 'div', { class: 'dak-sf-q-choices' } );

		box.appendChild( h( 'p', {}, [ h( 'strong', { text: '“' + q.phrase + '”' } ), prompt ] ) );

		q.candidates.forEach( function ( id ) {
			choices.appendChild( h( 'button', {
				type: 'button',
				class: 'dak-sf-choice',
				text: one ? 'Yes, I have ' + nameOf( id ).toLowerCase() + ' now' : 'Yes: ' + nameOf( id ),
				onclick: function () {
					questions.splice( qi, 1 );
					addSymptom( id );
					renderClarify();
					input.focus();
				},
			} ) );
		} );

		choices.appendChild( h( 'button', {
			type: 'button',
			class: 'dak-sf-choice is-none',
			text: one ? 'No, not now' : 'None of these now',
			onclick: function () {
				questions.splice( qi, 1 );
				renderClarify();
				input.focus();
			},
		} ) );

		box.appendChild( choices );

		return box;
	}

	/**
	 * What wasn't added because the patient said they don't have it (or it
	 * has gone). Neutral: it never suggests that anything is fine.
	 */
	function deniedBox() {
		var box = h( 'div', { class: 'dak-sf-q is-unknown' } );
		var not = denied.filter( function ( d ) {
			return ! d.gone;
		} );
		var gone = denied.filter( function ( d ) {
			return d.gone;
		} );
		var quote = function ( list ) {
			return list.map( function ( d ) {
				return '“' + d.phrase + '”';
			} ).join( ', ' );
		};

		if ( not.length ) {
			box.appendChild( h( 'p', { text: 'Not added, because you said you don’t have them: ' + quote( not ) + '.' } ) );
		}

		if ( gone.length ) {
			box.appendChild( h( 'p', { text: 'Not added, because you said they have gone: ' + quote( gone ) + '.' } ) );
		}

		deniedButSelected().forEach( function ( id ) {
			box.appendChild( h( 'p', {}, [
				nameOf( id ) + ' is still in your list. ',
				h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Remove ' + nameOf( id ), onclick: function () {
					removeSymptom( id );
					renderClarify();
					input.focus();
				} } ),
			] ) );
		} );

		box.appendChild( h( 'button', {
			type: 'button',
			class: 'dak-sf-link',
			text: 'Dismiss',
			onclick: function () {
				denied = [];
				renderClarify();
				input.focus();
			},
		} ) );

		return box;
	}

	input.addEventListener( 'input', renderSuggestions );

	input.addEventListener( 'keydown', function ( event ) {
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			if ( list.hidden ) {
				renderSuggestions();
			}

			event.preventDefault();
			highlight( active + ( 'ArrowDown' === event.key ? 1 : -1 ) );
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();

			if ( active > -1 && suggestions[ active ] ) {
				addSymptom( suggestions[ active ].id );
				input.value = '';
				closeList();
			} else {
				readText( input.value );
			}
		} else if ( 'Escape' === event.key ) {
			closeList();
		}
	} );

	input.addEventListener( 'blur', function () {
		setTimeout( closeList, 150 );
	} );

	root.querySelector( '[data-sf-add]' ).addEventListener( 'click', function () {
		readText( input.value );
		input.focus();
	} );

	root.querySelector( '[data-sf-find]' ).addEventListener( 'click', function () {
		// Anything still in the box counts too.
		if ( input.value.trim() ) {
			readText( input.value );
		}

		if ( ! selected.length && questions.length ) {
			showError( 'Please answer the question above first.' );
			clarify.querySelector( 'button' ).focus();
			return;
		}

		if ( ! selected.length && ! unmatched.length ) {
			showError( denied.length ? 'You’ve told us which symptoms you don’t have. Add at least one symptom you have now to find doctors.' : 'Add at least one symptom first.' );
			input.focus();
			return;
		}

		showError( '' );
		flow.index = 0;
		flow.reviewing = false;
		flow.editing = false;
		flow.urgentSeen = false;
		flow.skipped = {};
		flow.answers = m.pruneAnswers( selected, flow.answers );

		goTo( stopRule() || asked().length ? 'questions' : 'results', true );
	} );

	/* ------------------------------------------------------------------
	 * Follow-up questions
	 * ---------------------------------------------------------------- */

	function answerText( q, a ) {
		if ( 'number' === q.type ) {
			return Match.isAnswered( q, a ) ? String( a ) + ( q.number && q.number.unit ? ' ' + q.number.unit : '' ) : 'Not answered';
		}

		var labels = ( q.options || [] ).filter( function ( o ) {
			return Array.isArray( a ) ? a.indexOf( o.id ) !== -1 : a === o.id;
		} ).map( function ( o ) {
			return o.label;
		} );

		return labels.length ? labels.join( ', ' ) : 'Not answered';
	}

	function numberRange( q ) {
		var n = q.number || {};
		var unit = n.unit ? ' ' + n.unit : '';

		if ( 'number' === typeof n.min && 'number' === typeof n.max ) {
			return 'Enter a number from ' + n.min + ' to ' + n.max + unit + '.';
		}

		if ( 'number' === typeof n.min ) {
			return 'Enter a number of at least ' + n.min + unit + '.';
		}

		if ( 'number' === typeof n.max ) {
			return 'Enter a number up to ' + n.max + unit + '.';
		}

		return 'Enter a number.';
	}

	/**
	 * Records an answer — for every chosen symptom that asked the same
	 * question — drops answers that no longer apply, then re-checks the
	 * safety rules straight away.
	 *
	 * @param {Object} item  An asked() entry.
	 * @param {*}      value The answer, or '' to clear it.
	 */
	function setAnswer( item, value ) {
		var empty = undefined === value || null === value || '' === value || ( Array.isArray( value ) && ! value.length );

		item.members.forEach( function ( member ) {
			if ( empty ) {
				delete flow.answers[ member.key ];
			} else {
				flow.answers[ member.key ] = Match.mapAnswer( item.question, member.question, value );
			}
		} );

		prune();

		if ( ! empty ) {
			delete flow.skipped[ item.key ];
		}

		var stale = stepQuestions.querySelector( '#dak-sf-q-error' );

		if ( stale ) {
			stale.hidden = true;
			stale.textContent = '';
		}

		renderSteps();
		refreshProgress();

		if ( renderAlerts( true ) ) {
			// A stopping safety rule just appeared: don't ask anything else.
			flow.editing = false;
			flow.urgentSeen = false;
			renderQuestions( false );
		}
	}

	function isDone( item ) {
		return Match.isAnswered( item.question, flow.answers[ item.key ] );
	}

	/** Position of the first question neither answered nor skipped (or the count). */
	function firstUnanswered( items ) {
		for ( var i = 0; i < items.length; i++ ) {
			if ( undefined === flow.answers[ items[ i ].key ] && ! flow.skipped[ items[ i ].key ] ) {
				return i;
			}
		}

		return items.length;
	}

	/**
	 * An answer can show or hide later questions: keep "Question 2 of 4"
	 * and the Next / Check your answers label true without redrawing (and
	 * so without moving focus away from the answer just chosen).
	 */
	function refreshProgress() {
		var count = stepQuestions.querySelector( '.dak-sf-q-count' );
		var next = stepQuestions.querySelector( '[data-sf-next]' );

		if ( ! count || ! next ) {
			return;
		}

		var total = asked().length;
		var n = Math.min( flow.index + 1, total );

		count.textContent = 'Question ' + n + ' of ' + total;
		stepQuestions.querySelector( '.dak-sf-bar span' ).style.width = Math.round( n / Math.max( total, 1 ) * 100 ) + '%';
		next.textContent = flow.index >= total - 1 ? 'Check your answers' : 'Next';
	}

	function renderQuestions( focus ) {
		var box = stepQuestions;
		var items = asked();
		var emergency = emergencyNow();
		var urgent = emergency ? null : urgentStop();

		box.textContent = '';

		if ( emergency && ! flow.editing ) {
			renderStopped( box, emergency, items, focus );
			return;
		}

		if ( urgent && ! flow.editing && ! flow.urgentSeen ) {
			renderStopped( box, urgent, items, focus );
			return;
		}

		// Correcting answers while an emergency applies: only answers already
		// given can be changed — nothing new is asked.
		if ( emergency ) {
			items = items.filter( isDone );

			if ( ! items.length ) {
				flow.editing = false;
				renderStopped( box, emergency, items, focus );
				return;
			}
		}

		if ( ! items.length ) {
			goTo( 'results', focus );
			return;
		}

		if ( flow.reviewing || flow.index >= items.length ) {
			flow.reviewing = true;
			renderReview( box, items, focus, !! emergency );
			return;
		}

		flow.index = Math.max( 0, Math.min( flow.index, items.length - 1 ) );

		var item = items[ flow.index ];
		var q = item.question;
		var answer = flow.answers[ item.key ];
		var errId = 'dak-sf-q-error';
		var hintId = q.explanation ? 'dak-sf-q-hint' : null;

		if ( ! emergency ) {
			box.appendChild( h( 'div', { class: 'dak-sf-q-progress' }, [
				h( 'p', { class: 'dak-sf-q-count', text: 'Question ' + ( flow.index + 1 ) + ' of ' + items.length } ),
				h( 'div', { class: 'dak-sf-bar', 'aria-hidden': 'true' }, [
					h( 'span', { style: 'width:' + Math.round( ( flow.index + 1 ) / items.length * 100 ) + '%' } ),
				] ),
			] ) );
		}

		var fieldset = h( 'fieldset', { class: 'dak-sf-fieldset', 'aria-describedby': [ hintId, errId ].filter( Boolean ).join( ' ' ) } );
		fieldset.appendChild( h( 'legend', { class: 'dak-sf-legend' }, [
			h( 'span', { class: 'dak-sf-q-about', text: 'About your ' + item.symptomNames.join( ' and ' ).toLowerCase() } ),
			h( 'span', { id: 'dak-sf-q-title', class: 'dak-sf-q-text', tabindex: '-1', text: q.text } ),
			q.required ? null : h( 'span', { class: 'dak-sf-q-optional', text: ' (optional)' } ),
		] ) );

		if ( q.explanation ) {
			fieldset.appendChild( h( 'p', { class: 'dak-sf-hint', id: hintId }, paragraphs( q.explanation ).map( function ( p ) {
				return p.textContent + ' ';
			} ) ) );
		}

		if ( 'number' === q.type ) {
			var numId = 'dak-sf-num';
			var num = h( 'input', {
				type: 'number',
				id: numId,
				class: 'dak-sf-input dak-sf-number',
				inputmode: 'decimal',
				min: q.number && 'number' === typeof q.number.min ? String( q.number.min ) : null,
				max: q.number && 'number' === typeof q.number.max ? String( q.number.max ) : null,
				step: 'any',
				value: 'number' === typeof answer ? String( answer ) : '',
			} );

			num.addEventListener( 'change', function () {
				var v = '' === num.value.trim() ? '' : Number( num.value );
				setAnswer( item, '' === v || isNaN( v ) ? '' : v );
			} );

			fieldset.appendChild( h( 'div', { class: 'dak-sf-num-wrap' }, [
				h( 'label', { class: 'dak-visually-hidden', for: numId, text: q.text } ),
				num,
				q.number && q.number.unit ? h( 'span', { class: 'dak-sf-unit', text: q.number.unit } ) : null,
			] ) );
			fieldset.appendChild( h( 'p', { class: 'dak-sf-hint', text: numberRange( q ) } ) );
		} else {
			var multi = 'multi' === q.type;
			var grid = h( 'div', { class: 'dak-sf-answers' + ( 'yesno' === q.type ? ' is-yesno' : '' ) } );

			if ( multi ) {
				fieldset.appendChild( h( 'p', { class: 'dak-sf-hint', text: 'Choose all that apply.' } ) );
			}

			( q.options || [] ).forEach( function ( o, oi ) {
				var id = 'dak-sf-ans-' + oi;
				var on = multi ? Array.isArray( answer ) && answer.indexOf( o.id ) !== -1 : answer === o.id;
				var control = h( 'input', { type: multi ? 'checkbox' : 'radio', id: id, name: 'dak-sf-answer', value: o.id, checked: on } );

				control.addEventListener( 'change', function () {
					if ( multi ) {
						var picked = Array.prototype.filter.call( grid.querySelectorAll( 'input' ), function ( c ) {
							return c.checked;
						} ).map( function ( c ) {
							return c.value;
						} );
						setAnswer( item, picked );
					} else {
						setAnswer( item, o.id );
					}

					Array.prototype.forEach.call( grid.querySelectorAll( 'label' ), function ( l ) {
						l.classList.toggle( 'is-checked', l.querySelector( 'input' ).checked );
					} );
				} );

				grid.appendChild( h( 'label', { class: 'dak-sf-answer' + ( on ? ' is-checked' : '' ), for: id }, [
					control,
					h( 'span', { class: 'dak-sf-answer-mark', 'aria-hidden': 'true' } ),
					h( 'span', { class: 'dak-sf-answer-label', text: o.label } ),
				] ) );
			} );

			fieldset.appendChild( grid );
		}

		fieldset.appendChild( h( 'p', { class: 'dak-sf-error', id: errId, role: 'alert', hidden: true } ) );
		box.appendChild( fieldset );

		/** After Next or Skip: go on, or back to the list while correcting. */
		function advance() {
			if ( emergencyNow() ) {
				// Still applies: only corrections, never the next question.
				flow.reviewing = true;
				renderQuestions( true );
				return;
			}

			if ( emergency ) {
				// This change means the emergency rule no longer applies —
				// carry on from the question after the one just changed.
				var all = asked();
				var at = all.map( function ( x ) {
					return x.key;
				} ).indexOf( item.key );

				flow.editing = false;
				flow.reviewing = false;
				flow.index = -1 === at ? firstUnanswered( all ) : at + 1;
				renderQuestions( true );
				return;
			}

			flow.index += 1;
			renderQuestions( true );
		}

		var last = flow.index === items.length - 1;

		box.appendChild( h( 'div', { class: 'dak-sf-nav' }, [
			h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Back', onclick: function () {
				if ( emergency ) {
					flow.reviewing = true;
					renderQuestions( true );
				} else if ( flow.index > 0 ) {
					flow.index -= 1;
					renderQuestions( true );
				} else {
					goTo( 'symptoms', true );
				}
			} } ),
			q.required ? null : h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Skip', 'aria-label': 'Skip this question', onclick: function () {
				setAnswer( item, '' );
				flow.skipped[ item.key ] = true;

				// Clearing an answer can itself change which rule applies.
				if ( flow.step === 'questions' && ( ! emergencyNow() || emergency ) ) {
					advance();
				}
			} } ),
			h( 'button', { type: 'button', class: 'dak-button dak-button-primary dak-sf-btn', 'data-sf-next': emergency ? null : '', text: emergency ? 'Save answer' : ( last ? 'Check your answers' : 'Next' ), onclick: function () {
				var a = flow.answers[ item.key ];
				var err = box.querySelector( '#' + errId );

				if ( 'number' === q.type && undefined !== a && ! Match.isAnswered( q, a ) ) {
					err.textContent = numberRange( q );
					err.hidden = false;
					focusEl( box.querySelector( 'input' ) );
					return;
				}

				if ( q.required && ! Match.isAnswered( q, a ) ) {
					err.textContent = 'number' === q.type ? numberRange( q ) : 'Please choose an answer.';
					err.hidden = false;
					focusEl( box.querySelector( 'input' ) );
					return;
				}

				advance();
			} } ),
		] ) );

		if ( focus ) {
			focusEl( document.getElementById( 'dak-sf-q-title' ) );
		}
	}

	/**
	 * The answers so far, each with "Change".
	 *
	 * @param {boolean} correcting An emergency rule applies: the patient is
	 *                             only correcting answers, not continuing.
	 */
	function renderReview( box, items, focus, correcting ) {
		box.appendChild( h( 'h2', { id: 'dak-sf-q-title', tabindex: '-1', text: correcting ? 'Change your answers' : 'Check your answers' } ) );
		box.appendChild( h( 'p', { class: 'dak-sf-hint', text: correcting
			? 'Change anything you chose by mistake. When you’ve finished, everything is checked again.'
			: 'You can change any answer before we show doctors.' } ) );

		var dl = h( 'dl', { class: 'dak-sf-review' } );

		items.forEach( function ( item, i ) {
			dl.appendChild( h( 'div', { class: 'dak-sf-review-row' }, [
				h( 'dt', {}, [ h( 'span', { class: 'dak-sf-q-about', text: item.symptomNames.join( ', ' ) } ), item.question.text ] ),
				h( 'dd', {}, [
					h( 'span', { text: answerText( item.question, flow.answers[ item.key ] ) } ),
					h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Change', 'aria-label': 'Change answer: ' + item.question.text, onclick: function () {
						flow.reviewing = false;
						flow.index = i;
						renderQuestions( true );
					} } ),
				] ),
			] ) );
		} );

		box.appendChild( dl );

		if ( correcting ) {
			box.appendChild( h( 'div', { class: 'dak-sf-nav' }, [
				h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Change my symptoms', onclick: function () {
					flow.editing = false;
					goTo( 'symptoms', true );
				} } ),
				h( 'button', { type: 'button', class: 'dak-button dak-button-primary dak-sf-btn', text: 'Done', onclick: function () {
					flow.editing = false;
					flow.reviewing = false;
					flow.index = firstUnanswered( asked() );
					renderQuestions( true );
				} } ),
			] ) );
		} else {
			box.appendChild( h( 'div', { class: 'dak-sf-nav' }, [
				h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Back', onclick: function () {
					flow.reviewing = false;
					flow.index = items.length - 1;
					renderQuestions( true );
				} } ),
				h( 'button', { type: 'button', class: 'dak-button dak-button-primary dak-sf-btn', text: 'Show doctors', onclick: function () {
					goTo( 'results', true );
				} } ),
			] ) );
		}

		if ( focus ) {
			focusEl( document.getElementById( 'dak-sf-q-title' ) );
		}
	}

	/**
	 * Where the questions stop.
	 *
	 * - Emergency: no further questions, doctors or booking — only the
	 *   instructions (above) and ways to correct what was entered.
	 * - Urgent (set to interrupt): the questions stop; the patient can read
	 *   the message and then continue to doctors.
	 */
	function renderStopped( box, rule, items, focus ) {
		var emergency = 'emergency' === rule.level;
		var answered = items.filter( isDone );
		var changeAnswers = answered.length ? h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Change my answers', onclick: function () {
			flow.editing = true;
			flow.reviewing = true;
			renderQuestions( true );
		} } ) : null;
		var changeSymptoms = h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Change my symptoms', onclick: function () {
			goTo( 'symptoms', true );
		} } );

		if ( emergency ) {
			box.appendChild( h( 'h2', { id: 'dak-sf-q-title', tabindex: '-1', text: 'We’ve stopped here' } ) );
			box.appendChild( h( 'p', { text: 'Please follow the emergency instructions above now: “' + rule.title + '”.' } ) );
			box.appendChild( h( 'p', { class: 'dak-sf-hint', text: 'While this applies, we don’t show doctors or appointments, so that booking doesn’t delay getting help. If you chose a symptom or answer by mistake, change it and everything is checked again.' } ) );
			box.appendChild( h( 'div', { class: 'dak-sf-nav' }, [ changeAnswers, changeSymptoms ] ) );
		} else {
			box.appendChild( h( 'h2', { id: 'dak-sf-q-title', tabindex: '-1', text: 'Please read this first' } ) );
			box.appendChild( h( 'p', { text: 'Because of what you’ve told us, please follow the instructions above: “' + rule.title + '”.' } ) );
			box.appendChild( h( 'p', { class: 'dak-sf-hint', text: 'You can change your answers if something was chosen by mistake, or continue to see doctors.' } ) );
			box.appendChild( h( 'div', { class: 'dak-sf-nav' }, [
				changeAnswers || changeSymptoms,
				h( 'button', { type: 'button', class: 'dak-button dak-button-primary dak-sf-btn', text: 'Continue to doctors', onclick: function () {
					flow.urgentSeen = true;
					goTo( 'results', true );
				} } ),
			] ) );
		}

		if ( focus ) {
			focusEl( document.getElementById( 'dak-sf-alert-' + rule.id ) || document.getElementById( 'dak-sf-q-title' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Location
	 * ---------------------------------------------------------------- */

	function locate() {
		if ( ! navigator.geolocation ) {
			loc.status = 'unavailable';
			renderResults();
			return;
		}

		loc.status = 'locating';
		renderResults();

		navigator.geolocation.getCurrentPosition(
			function ( position ) {
				loc.origin = { lat: position.coords.latitude, lng: position.coords.longitude };
				loc.place = Match.nearbyPlace( loc.origin, data.places );
				loc.status = 'granted';
				loc.mode = 'auto';
				renderResults( 'Doctors sorted by distance from your location.' );
			},
			function ( error ) {
				loc.status = 1 === error.code ? 'denied' : ( 3 === error.code ? 'timeout' : 'unavailable' );
				renderResults();
			},
			{ enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 }
		);
	}

	/** Asks once, on the results step; never re-asks after a refusal. */
	function startLocation() {
		if ( 'idle' !== loc.status ) {
			return;
		}

		if ( navigator.permissions && navigator.permissions.query ) {
			loc.status = 'locating';
			navigator.permissions.query( { name: 'geolocation' } ).then( function ( p ) {
				if ( 'denied' === p.state ) {
					loc.status = 'denied';
					renderResults();
				} else {
					locate();
				}
			}, locate );
			return;
		}

		locate();
	}

	function rankOptions() {
		if ( 'place' === loc.mode && loc.city ) {
			return { city: loc.city, area: loc.area };
		}

		if ( 'auto' === loc.mode && 'granted' === loc.status ) {
			return { origin: loc.origin };
		}

		return {};
	}

	function cityOf( slug ) {
		return ( data.locations || [] ).filter( function ( c ) {
			return c.slug === slug;
		} )[ 0 ];
	}

	/* ------------------------------------------------------------------
	 * Results
	 * ---------------------------------------------------------------- */

	function formatKm( km ) {
		return km < 1 ? 'Less than 1 km away' : 'About ' + ( km < 10 ? km.toFixed( 1 ) : Math.round( km ) ) + ' km away';
	}

	function avatar( doc ) {
		var src = safeUrl( doc.avatar );

		return src
			? h( 'img', { class: 'dak-sf-photo', src: src, alt: '', loading: 'lazy', width: '64', height: '64' } )
			: h( 'span', { class: 'dak-sf-photo is-initials', 'aria-hidden': 'true', text: doc.name.replace( /^(dr|doctor)\.?\s+/i, '' ).charAt( 0 ) } );
	}

	function card( entry, online ) {
		var doc = entry.doctor;
		var clinic = online ? null : entry.primary;
		var body = h( 'div', { class: 'dak-sf-doc-body' } );
		var profile = safeUrl( doc.profile_url );
		var book = online ? safeUrl( doc.video.booking_url ) : safeUrl( clinic && clinic.booking_url );

		body.appendChild( h( 'h3', { class: 'dak-sf-doc-name', text: doc.name } ) );
		body.appendChild( h( 'p', { class: 'dak-sf-doc-spec', text: entry.matched.map( specialtyLabel ).join( ', ' ) + ( online ? ' · Video consultation' : '' ) } ) );

		if ( clinic ) {
			body.appendChild( h( 'p', { class: 'dak-sf-doc-clinic', text: clinic.label + ( clinic.place ? ' · ' + clinic.place : '' ) } ) );

			if ( 'distance' === entry.mode ) {
				body.appendChild( h( 'p', { class: 'dak-sf-doc-distance', text: null !== clinic.distanceKm ? formatKm( clinic.distanceKm ) + ' (straight-line)' : 'Distance not available for this clinic' } ) );
			}
		}

		var fee = online ? doc.video.fee : clinic.fee;
		var next = online ? doc.video.next_available : clinic.next_available;
		var facts = h( 'dl', { class: 'dak-sf-facts' } );

		if ( fee ) {
			facts.appendChild( h( 'div', {}, [ h( 'dt', { text: online ? 'Video fee' : 'Fee' } ), h( 'dd', { text: fee } ) ] ) );
		}

		if ( next ) {
			facts.appendChild( h( 'div', {}, [ h( 'dt', { text: 'Next available' } ), h( 'dd', { text: next } ) ] ) );
		}

		if ( facts.children.length ) {
			body.appendChild( facts );
		}

		if ( ! online && entry.others.length ) {
			var more = h( 'details', { class: 'dak-sf-more' }, [ h( 'summary', { text: 'Also at ' + entry.others.length + ' other clinic' + ( 1 === entry.others.length ? '' : 's' ) } ) ] );

			entry.others.forEach( function ( c ) {
				var url = safeUrl( c.booking_url );
				more.appendChild( h( 'p', {}, [
					c.label + ( c.place ? ' · ' + c.place : '' ) + ( 'distance' === entry.mode && null !== c.distanceKm ? ' · ' + formatKm( c.distanceKm ) : '' ) + ' ',
					url ? h( 'a', { href: url, text: 'Book here', 'aria-label': 'Book ' + doc.name + ' at ' + c.label } ) : null,
				] ) );
			} );

			body.appendChild( more );
		}

		body.appendChild( h( 'div', { class: 'dak-sf-doc-actions' }, [
			profile ? h( 'a', { class: 'dak-button dak-button-secondary dak-sf-btn', href: profile, text: 'View profile', 'aria-label': 'View profile of ' + doc.name } ) : null,
			book ? h( 'a', { class: 'dak-button dak-button-primary dak-sf-btn', href: book, text: online ? 'Book video consultation' : 'Book appointment', 'aria-label': ( online ? 'Book a video consultation with ' : 'Book an appointment with ' ) + doc.name } ) : null,
		] ) );

		return h( 'article', { class: 'dak-sf-doc' }, [ avatar( doc ), body ] );
	}

	function group( title, note, entries, online ) {
		var wrap = h( 'div', { class: 'dak-sf-group' }, [ h( 'h3', { class: 'dak-sf-group-title', text: title } ), note ? h( 'p', { class: 'dak-sf-hint', text: note } ) : null ] );
		var listEl = h( 'div', { class: 'dak-sf-doc-list' } );
		var shown = 0;

		function more() {
			entries.slice( shown, shown + 6 ).forEach( function ( e ) {
				listEl.appendChild( card( e, online ) );
			} );
			shown = Math.min( entries.length, shown + 6 );
		}

		more();
		wrap.appendChild( listEl );

		if ( entries.length > shown ) {
			var btn = h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Show more doctors', onclick: function () {
				var before = shown;
				more();

				var first = listEl.children[ before ] && listEl.children[ before ].querySelector( 'h3' );

				if ( first ) {
					focusEl( first );
				}

				if ( shown >= entries.length ) {
					btn.remove();
				}
			} } );
			wrap.appendChild( btn );
		}

		return wrap;
	}

	/**
	 * Only the neutral directory — never a substitute specialty the
	 * clinician didn't choose.
	 */
	function fallbackLinks() {
		var p = h( 'p', { class: 'dak-sf-fallback' } );

		if ( safeUrl( data.directoryUrl ) ) {
			p.appendChild( h( 'a', { class: 'dak-button dak-button-secondary dak-sf-btn', href: data.directoryUrl, text: 'Search all doctors' } ) );
		}

		return p;
	}

	function locationBar() {
		var bar = h( 'div', { class: 'dak-sf-locbar' } );
		var text;

		if ( 'place' === loc.mode && loc.city ) {
			var city = cityOf( loc.city );
			var area = city && loc.area ? city.areas.filter( function ( a ) {
				return a.slug === loc.area;
			} )[ 0 ] : null;
			text = 'Showing doctors in ' + ( area ? area.label + ', ' : '' ) + ( city ? city.label : loc.city ) + ' — not sorted by distance.';
		} else if ( 'auto' === loc.mode && 'locating' === loc.status ) {
			text = 'Finding your location…';
		} else if ( 'auto' === loc.mode && 'granted' === loc.status ) {
			text = 'Sorted by straight-line distance from your location' + ( loc.place ? ' (near ' + loc.place + ')' : '' ) + '.';
		} else {
			text = 'Your location isn’t being used, so doctors aren’t sorted by distance.';
		}

		bar.appendChild( h( 'p', { text: text } ) );
		bar.appendChild( h( 'button', {
			type: 'button',
			class: 'dak-sf-link',
			'aria-expanded': loc.panel ? 'true' : 'false',
			text: 'Change location',
			onclick: function () {
				loc.panel = ! loc.panel;
				renderResults();
				var first = results.querySelector( '.dak-sf-locpanel button, .dak-sf-locpanel select' );

				if ( first && loc.panel ) {
					first.focus();
				}
			},
		} ) );

		return bar;
	}

	function locationPanel( reason ) {
		var panel = h( 'div', { class: 'dak-sf-locpanel' } );

		if ( reason ) {
			panel.appendChild( h( 'p', { text: reason } ) );
		}

		panel.appendChild( h( 'button', {
			type: 'button',
			class: 'dak-button dak-button-secondary dak-sf-btn',
			text: 'granted' === loc.status ? 'Use my current location' : ( 'idle' === loc.status ? 'Use my location' : 'Try my location again' ),
			onclick: function () {
				loc.mode = 'auto';
				loc.panel = false;

				if ( 'granted' === loc.status ) {
					renderResults( 'Doctors sorted by distance from your location.', true );
				} else {
					locate();
				}
			},
		} ) );

		if ( 'denied' === loc.status ) {
			panel.appendChild( h( 'p', { class: 'dak-sf-hint', text: 'Location is blocked for this site. To use it, allow location in your browser’s settings, then try again.' } ) );
		}

		var cities = data.locations || [];

		if ( cities.length ) {
			var citySel = h( 'select', { id: 'dak-sf-city', class: 'dak-sf-input' }, [ h( 'option', { value: '', text: 'Choose a city' } ) ].concat( cities.map( function ( c ) {
				return h( 'option', { value: c.slug, text: c.label, selected: c.slug === loc.city } );
			} ) ) );
			var areaSel = h( 'select', { id: 'dak-sf-area', class: 'dak-sf-input' } );

			var fillAreas = function () {
				var city = cityOf( citySel.value );
				areaSel.textContent = '';
				areaSel.appendChild( h( 'option', { value: '', text: city ? 'Any area' : 'Choose a city first' } ) );
				( city ? city.areas : [] ).forEach( function ( a ) {
					areaSel.appendChild( h( 'option', { value: a.slug, text: a.label, selected: a.slug === loc.area } ) );
				} );
				areaSel.disabled = ! city || ! city.areas.length;
			};

			citySel.addEventListener( 'change', fillAreas );
			fillAreas();

			panel.appendChild( h( 'div', { class: 'dak-sf-place' }, [
				h( 'div', {}, [ h( 'label', { for: 'dak-sf-city', class: 'dak-sf-label', text: 'City (optional)' } ), citySel ] ),
				h( 'div', {}, [ h( 'label', { for: 'dak-sf-area', class: 'dak-sf-label', text: 'Area (optional)' } ), areaSel ] ),
				h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sf-btn', text: 'Show doctors there', onclick: function () {
					if ( ! citySel.value ) {
						citySel.focus();
						return;
					}

					loc.mode = 'place';
					loc.city = citySel.value;
					loc.area = areaSel.value;
					loc.panel = false;
					renderResults( 'Showing doctors in the place you chose.', true );
				} } ),
			] ) );
		}

		panel.appendChild( h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Browse without location', onclick: function () {
			loc.mode = 'none';
			loc.panel = false;
			renderResults( 'Showing doctors without location.', true );
		} } ) );

		return panel;
	}

	/** "You told us" summary with ways back to change things. */
	function summary() {
		var answered = asked().filter( isDone );
		var box = h( 'div', { class: 'dak-sf-summary' } );

		box.appendChild( h( 'p', {}, [
			h( 'strong', { text: 'Your symptoms: ' } ),
			selected.length ? selected.map( nameOf ).join( ', ' ) : 'none matched',
			unmatched.length ? h( 'span', { class: 'dak-sf-hint', text: ' (not matched: ' + unmatched.join( ', ' ) + ')' } ) : null,
		] ) );

		if ( answered.length ) {
			var details = h( 'details', { class: 'dak-sf-more' }, [ h( 'summary', { text: 'Your answers (' + answered.length + ')' } ) ] );
			var dl = h( 'dl', { class: 'dak-sf-review is-compact' } );

			answered.forEach( function ( item ) {
				dl.appendChild( h( 'div', { class: 'dak-sf-review-row' }, [ h( 'dt', { text: item.question.text } ), h( 'dd', { text: answerText( item.question, flow.answers[ item.key ] ) } ) ] ) );
			} );

			details.appendChild( dl );
			box.appendChild( details );
		}

		box.appendChild( h( 'div', { class: 'dak-sf-summary-actions' }, [
			h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Change symptoms', onclick: function () {
				goTo( 'symptoms', true );
			} } ),
			m.questionsFor( selected ).length ? h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Change answers', onclick: function () {
				flow.reviewing = true;
				flow.editing = true;
				goTo( 'questions', true );
			} } ) : null,
		] ) );

		return box;
	}

	/**
	 * @param {string}  message   Announced to screen readers.
	 * @param {boolean} moveFocus The visitor just acted here — focus the heading
	 *                            (never for the location arriving on its own).
	 */
	function renderResults( message, moveFocus ) {
		if ( 'results' !== flow.step ) {
			return;
		}

		// Never draw doctors while an emergency rule applies (e.g. location
		// arriving late, or a restored page).
		if ( emergencyNow() ) {
			goTo( 'questions', !! moveFocus );
			return;
		}

		// Redrawing removes whatever had focus inside the results; put it
		// back on the heading rather than losing it to the page.
		var focusTitle = !! moveFocus || ( document.activeElement !== document.body && results.contains( document.activeElement ) );
		results.textContent = '';
		results.hidden = false;

		results.appendChild( h( 'h2', { id: 'dak-sf-results-title', tabindex: '-1', text: 'Doctors for your symptoms' } ) );
		results.appendChild( summary() );

		var specs = m.specialtiesFor( selected, flow.answers );

		// Symptoms with no clinician-approved specialty: say so plainly
		// rather than guessing one.
		var linked = {};

		specs.forEach( function ( s ) {
			s.symptoms.forEach( function ( name ) {
				linked[ name ] = true;
			} );
			s.answers.forEach( function ( via ) {
				linked[ via.split( ': ' )[ 0 ] ] = true;
			} );
		} );

		var unmapped = selected.map( nameOf ).filter( function ( name ) {
			return ! linked[ name ];
		} );

		if ( ! specs.length ) {
			results.appendChild( h( 'div', { class: 'dak-sf-empty', 'data-sf-no-guidance': '' }, [
				h( 'p', { text: selected.length
					? 'We don’t have reviewed guidance yet on which kind of doctor to see for: ' + unmapped.join( ', ' ) + '.'
					: 'We couldn’t reliably match what you entered to symptoms in our list, so we can’t suggest a kind of doctor.' } ),
				h( 'p', { text: 'You can search all doctors yourself. This page can’t tell whether symptoms are serious — if you’re worried, follow the advice at the top of this page.' } ),
				fallbackLinks(),
			] ) );
			afterRender( message, focusTitle );
			return;
		}

		if ( unmapped.length ) {
			results.appendChild( h( 'p', { class: 'dak-sf-hint dak-sf-unmapped', text: 'We don’t have reviewed guidance yet on which kind of doctor to see for: ' + unmapped.join( ', ' ) + '. The doctors below are for your other symptoms only.' } ) );
		}

		results.appendChild( h( 'p', { class: 'dak-sf-explain', text: 'Doctors in specialties linked to what you told us. This is not a diagnosis, and it doesn’t mean a particular doctor is right for you — the doctor will advise you at your appointment.' } ) );

		results.appendChild( h( 'ul', { class: 'dak-sf-specs', 'aria-label': 'Related specialties' }, specs.map( function ( s ) {
			var why = s.symptoms.slice();

			if ( s.answers.length ) {
				why = why.concat( s.answers.map( function ( a ) {
					return 'your answer “' + a + '”';
				} ) );
			}

			return h( 'li', {}, [ h( 'strong', { text: specialtyLabel( s.slug ) } ), h( 'span', { text: 'linked to ' + why.join( ', ' ) } ) ] );
		} ) ) );

		results.appendChild( locationBar() );

		var failed = 'auto' === loc.mode && ( 'denied' === loc.status || 'unavailable' === loc.status || 'timeout' === loc.status );

		if ( loc.panel || failed ) {
			var reason = null;

			if ( failed ) {
				reason = 'denied' === loc.status
					? 'You didn’t share your location, so we can’t sort doctors by distance. You can choose a city or area instead, or browse without location.'
					: ( 'timeout' === loc.status ? 'Finding your location took too long.' : 'Your location isn’t available on this device.' ) + ' You can choose a city or area instead, or browse without location.';
			}

			results.appendChild( locationPanel( reason ) );
		}

		if ( 'auto' === loc.mode && 'locating' === loc.status ) {
			results.appendChild( h( 'p', { class: 'dak-sf-loading', role: 'status', text: 'Finding your location…' } ) );
			afterRender( message, focusTitle );
			return;
		}

		var ranked = Match.rankDoctors( data.doctors || [], specs, rankOptions() );

		ranked.clinic.forEach( function ( e ) {
			e.mode = ranked.mode;
		} );

		if ( ranked.clinic.length ) {
			results.appendChild( group(
				'distance' === ranked.mode ? 'Nearest first' : 'Clinic appointments',
				'distance' === ranked.mode ? 'Distances are straight-line estimates from your location, not travel distances.' : null,
				ranked.clinic,
				false
			) );
		} else {
			results.appendChild( h( 'div', { class: 'dak-sf-empty' }, [
				h( 'p', { text: 'place' === ranked.mode ? 'No doctors in these specialties have a clinic in the place you chose.' : 'No doctors in these specialties are listed yet.' } ),
				'place' === ranked.mode ? h( 'button', { type: 'button', class: 'dak-sf-link', text: 'Show all locations', onclick: function () {
					loc.mode = 'none';
					renderResults( 'Showing doctors in all locations.', true );
				} } ) : null,
				fallbackLinks(),
			] ) );
		}

		if ( ranked.online.length ) {
			results.appendChild( group( 'Online video consultations', 'Talk to a doctor by video. They will tell you if you need to be seen in person.', ranked.online, true ) );
		}

		afterRender( message, focusTitle );
	}

	function afterRender( message, focusTitle ) {
		if ( message ) {
			announce( message );
		}

		if ( focusTitle ) {
			// A safety message, if any, is read first.
			var alertTitle = alerts.hidden ? null : alerts.querySelector( '.dak-sf-alert-title' );
			focusEl( alertTitle || document.getElementById( 'dak-sf-results-title' ) );
		}
	}

	/* ------------------------------------------------------------------ */

	// A page restored from the browser's back/forward cache keeps its
	// in-memory state: re-check the rules so an emergency still applying
	// can't leave doctors on screen.
	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			goTo( flow.step, false );
		}
	} );

	// Staff preview opened from one symptom's "Preview" button.
	if ( data.preview && data.previewSymptom && m.byId[ data.previewSymptom ] ) {
		addSymptom( data.previewSymptom );
	}

	renderSteps();
}() );
