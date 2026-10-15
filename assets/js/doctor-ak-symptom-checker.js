/**
 * Doctor AK Portal — Symptom Checker page.
 *
 * Flow: describe / choose symptoms → confirm what we understood → follow-up
 * questions (branching, "Not sure", Back) → check your answers → results
 * (next steps, possible explanations, who can help, doctors near you).
 * Warning signs interrupt the flow with the emergency message as soon as
 * they're reported.
 *
 * Privacy: everything here stays in this page's memory. Nothing is sent to
 * the site or anyone else, nothing is written to the URL, cookies or
 * storage, and nothing is logged. The visitor's location is used only to
 * work out approximate straight-line distances and is forgotten when they
 * leave the page.
 *
 * All the clinical content comes from the ruleset (dakSymptomChecker.rules)
 * via doctor-ak-symptom-engine.js. All text is inserted with textContent.
 */
( function () {
	'use strict';

	var data = window.dakSymptomChecker;
	var Engine = window.DAKSymptomEngine;
	var root = document.querySelector( '[data-dak-sc]' );

	if ( ! data || ! Engine || ! root ) {
		return;
	}

	var app = root.querySelector( '[data-sc-app]' );
	var intro = root.querySelector( '[data-sc-stage="intro"]' );
	var live = root.querySelector( '[data-sc-live]' );

	if ( ! app || ! intro ) {
		return;
	}

	var rules = data.rules || {};
	// wp_localize_script() turns top-level numbers into strings.
	var RADIUS_KM = Number( data.radiusKm ) || 25;
	var EXPANDED_KM = Number( data.expandedKm ) || 100;
	var eng = Engine.create( rules );
	var UNSURE = eng.UNSURE;

	var URGENCY = {
		urgent: { label: 'Get seen today', cls: 'is-urgent' },
		soon: { label: 'See a doctor soon', cls: 'is-soon' },
		routine: { label: 'Routine', cls: 'is-routine' },
		self_care: { label: 'Look after yourself', cls: 'is-routine' }
	};

	var state;
	var loc = {
		status: 'idle', // idle | locating | granted | denied | unavailable | timeout
		origin: null,
		area: null,
		mode: 'auto', // auto (follow status) | city | none
		city: '',
		radius: RADIUS_KM,
		panelOpen: false
	};

	function freshState() {
		return {
			symptoms: {},
			answers: {},
			forced: [],
			parsed: null,
			durationHint: null,
			history: [],
			stage: 'intro',
			current: null,
			returnToReview: false,
			introSelected: {}
		};
	}

	state = freshState();

	/* ------------------------------------------------------------------
	 * DOM helpers
	 * ---------------------------------------------------------------- */

	function h( tag, attrs, kids ) {
		var node = document.createElement( tag );

		Object.keys( attrs || {} ).forEach( function ( key ) {
			var value = attrs[ key ];

			if ( null === value || undefined === value || false === value ) {
				return;
			}

			if ( 'class' === key ) {
				node.className = value;
			} else if ( 'text' === key ) {
				node.textContent = value;
			} else if ( 'on' === key.slice( 0, 2 ) ) {
				node.addEventListener( key.slice( 2 ), value );
			} else {
				node.setAttribute( key, true === value ? '' : value );
			}
		} );

		( kids || [] ).forEach( function ( kid ) {
			if ( null === kid || undefined === kid || false === kid ) {
				return;
			}

			node.appendChild( 'string' === typeof kid ? document.createTextNode( kid ) : kid );
		} );

		return node;
	}

	function safeUrl( url ) {
		return /^https?:\/\//i.test( String( url || '' ) ) ? url : null;
	}

	function button( text, cls, onclick, extra ) {
		var attrs = { type: 'button', class: 'dak-button dak-sc-btn ' + ( cls || 'dak-button-secondary' ), text: text, onclick: onclick };

		Object.keys( extra || {} ).forEach( function ( k ) {
			attrs[ k ] = extra[ k ];
		} );

		return h( 'button', attrs );
	}

	function announce( message ) {
		if ( ! live ) {
			return;
		}

		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = message;
		}, 60 );
	}

	function focusHeading( container ) {
		var heading = container.querySelector( '[data-sc-focus]' ) || container.querySelector( 'h1, h2' );

		if ( heading ) {
			heading.setAttribute( 'tabindex', '-1' );
			heading.focus( { preventScroll: true } );
		}

		var top = root.getBoundingClientRect().top + window.pageYOffset - 90;

		if ( window.pageYOffset > top ) {
			window.scrollTo( 0, Math.max( 0, top ) );
		}
	}

	function show( stage, node, message ) {
		state.stage = stage;
		intro.hidden = 'intro' === stage ? false : true;
		app.textContent = '';

		if ( node ) {
			app.appendChild( node );
		}

		focusHeading( 'intro' === stage ? intro : app );

		if ( message ) {
			announce( message );
		}
	}

	function symptomLabel( id ) {
		var s = eng.symptomById[ id ];
		return s ? s.label : id;
	}

	function optionLabel( question, value ) {
		if ( UNSURE === value ) {
			return 'Not sure';
		}

		var match = ( question.options || [] ).filter( function ( o ) {
			return o.value === value;
		} )[ 0 ];

		return match ? match.label : String( value );
	}

	function answerText( question, value ) {
		if ( 'text' === question.type ) {
			return '' === String( value || '' ).trim() ? 'Nothing added' : String( value );
		}

		var list = Array.isArray( value ) ? value : [ value ];

		return list.map( function ( v ) {
			return optionLabel( question, v );
		} ).join( '; ' );
	}

	function specialtyLabel( slug ) {
		return ( data.specialtyLabels && data.specialtyLabels[ slug ] ) || slug.replace( /-/g, ' ' );
	}

	/* ------------------------------------------------------------------
	 * Symptom chips (intro + confirm)
	 * ---------------------------------------------------------------- */

	function renderChips( container, query, selected, onToggle ) {
		var list = eng.searchSymptoms( query );

		container.textContent = '';

		if ( ! list.length ) {
			container.appendChild( h( 'p', { class: 'dak-sc-small', text: 'No symptoms match that search. Try another word, or describe it in your own words above.' } ) );
			return;
		}

		list.forEach( function ( symptom ) {
			var pressed = !! selected[ symptom.id ];

			container.appendChild(
				h( 'button', {
					type: 'button',
					class: 'dak-sc-chip' + ( pressed ? ' is-selected' : '' ),
					'aria-pressed': pressed ? 'true' : 'false',
					text: symptom.label,
					onclick: function () {
						onToggle( symptom.id );
					}
				} )
			);
		} );
	}

	var introChips = intro.querySelector( '[data-sc-chips]' );
	var introSearch = intro.querySelector( '[data-sc-search]' );
	var introText = intro.querySelector( '[data-sc-text]' );
	var introError = intro.querySelector( '[data-sc-intro-error]' );

	function refreshIntroChips() {
		renderChips( introChips, introSearch.value, state.introSelected, function ( id ) {
			if ( state.introSelected[ id ] ) {
				delete state.introSelected[ id ];
				announce( symptomLabel( id ) + ' removed' );
			} else {
				state.introSelected[ id ] = true;
				announce( symptomLabel( id ) + ' added' );
			}

			var focused = id;
			refreshIntroChips();
			// Keep focus on the chip that was pressed.
			Array.prototype.forEach.call( introChips.querySelectorAll( '.dak-sc-chip' ), function ( chip ) {
				if ( chip.textContent === symptomLabel( focused ) ) {
					chip.focus();
				}
			} );
		} );
	}

	introSearch.addEventListener( 'input', refreshIntroChips );
	refreshIntroChips();

	intro.querySelector( '[data-sc-start]' ).addEventListener( 'click', function () {
		var text = introText.value.trim();
		var picked = Object.keys( state.introSelected );

		if ( ! text && ! picked.length ) {
			introError.textContent = 'Please describe how you feel, or choose at least one symptom from the list.';
			introError.hidden = false;
			introText.focus();
			return;
		}

		introError.hidden = true;
		startLocation();

		state.parsed = eng.matchText( text );
		state.durationHint = state.parsed.durationDays;
		state.symptoms = {};

		state.parsed.matches.forEach( function ( m ) {
			if ( ! m.negated ) {
				state.symptoms[ m.id ] = { uncertain: m.uncertain, source: 'text' };
			}
		} );

		picked.forEach( function ( id ) {
			state.symptoms[ id ] = { uncertain: false, source: 'list' };
		} );

		state.forced = state.parsed.alerts.slice();
		renderConfirm();
	} );

	/* ------------------------------------------------------------------
	 * Confirm what we understood
	 * ---------------------------------------------------------------- */

	function renderConfirm( message ) {
		var ids = Object.keys( state.symptoms );
		var parsed = state.parsed || { matches: [], unmatched: [] };
		var negated = parsed.matches.filter( function ( m ) {
			return m.negated && ! state.symptoms[ m.id ];
		} );
		var wrap = h( 'section', { class: 'dak-sc-card dak-sc-stage', 'aria-labelledby': 'dak-sc-confirm-title' } );
		var error = h( 'p', { class: 'dak-sc-error', role: 'alert', hidden: true } );

		wrap.appendChild( h( 'h2', { id: 'dak-sc-confirm-title', 'data-sc-focus': true, text: ids.length ? 'Is this right?' : 'Which symptoms do you have?' } ) );
		wrap.appendChild( h( 'p', { class: 'dak-sc-lead', text: ids.length ? 'These are the symptoms we\'ll ask about. Remove any that aren\'t right, or add others.' : 'We couldn\'t match what you wrote to a symptom in our list. Please choose the closest ones below.' } ) );

		if ( ids.length ) {
			var selected = h( 'ul', { class: 'dak-sc-selected', 'aria-label': 'Your symptoms' } );

			ids.forEach( function ( id ) {
				var info = state.symptoms[ id ];

				selected.appendChild(
					h( 'li', { class: 'dak-sc-selected-item' }, [
						h( 'span', { text: symptomLabel( id ) } ),
						info.uncertain ? h( 'span', { class: 'dak-sc-tag', text: 'You weren\'t sure' } ) : null,
						h( 'button', {
							type: 'button',
							class: 'dak-sc-remove',
							'aria-label': 'Remove ' + symptomLabel( id ),
							text: 'Remove',
							onclick: function () {
								delete state.symptoms[ id ];
								renderConfirm( symptomLabel( id ) + ' removed' );
							}
						} )
					] )
				);
			} );

			wrap.appendChild( selected );
		}

		if ( negated.length ) {
			var neg = h( 'div', { class: 'dak-sc-info' } );

			neg.appendChild( h( 'p', { text: 'You said you don\'t have:' } ) );
			negated.forEach( function ( m ) {
				neg.appendChild(
					h( 'p', { class: 'dak-sc-inline' }, [
						h( 'span', { text: m.label } ),
						h( 'button', {
							type: 'button',
							class: 'dak-sc-link',
							text: 'Actually, I do — add it',
							'aria-label': 'Add ' + m.label,
							onclick: function () {
								state.symptoms[ m.id ] = { uncertain: false, source: 'list' };
								renderConfirm( m.label + ' added' );
							}
						} )
					] )
				);
			} );
			wrap.appendChild( neg );
		}

		if ( parsed.unmatched.length ) {
			var un = h( 'div', { class: 'dak-sc-info is-warning' } );

			un.appendChild( h( 'p', { text: 'We couldn\'t match these words to a symptom in our list:' } ) );
			un.appendChild(
				h( 'ul', {}, parsed.unmatched.map( function ( clause ) {
					return h( 'li', { text: '“' + clause + '”' } );
				} ) )
			);
			un.appendChild( h( 'p', { class: 'dak-sc-small', text: 'Choose the closest symptom below if there is one. If not, you can continue — a doctor can ask you about it.' } ) );
			wrap.appendChild( un );
		}

		var search = h( 'input', { type: 'search', class: 'dak-sc-input', id: 'dak-sc-confirm-search', autocomplete: 'off', placeholder: 'Search symptoms' } );
		var chips = h( 'div', { class: 'dak-sc-chips', role: 'group', 'aria-label': 'Add symptoms' } );

		function refresh() {
			renderChips( chips, search.value, state.symptoms, function ( id ) {
				if ( state.symptoms[ id ] ) {
					delete state.symptoms[ id ];
					renderConfirm( symptomLabel( id ) + ' removed' );
				} else {
					state.symptoms[ id ] = { uncertain: false, source: 'list' };
					renderConfirm( symptomLabel( id ) + ' added' );
				}
			} );
		}

		search.addEventListener( 'input', refresh );
		refresh();

		wrap.appendChild( h( 'label', { class: 'dak-sc-label', for: 'dak-sc-confirm-search', text: 'Add another symptom' } ) );
		wrap.appendChild( search );
		wrap.appendChild( chips );
		wrap.appendChild( error );

		wrap.appendChild(
			h( 'div', { class: 'dak-sc-actions' }, [
				button( 'Back', 'dak-button-secondary', function () {
					show( 'intro', null );
				} ),
				button( 'Continue', 'dak-button-primary', function () {
					if ( ! Object.keys( state.symptoms ).length ) {
						error.textContent = 'Please choose at least one symptom to continue.';
						error.hidden = false;
						search.focus();
						return;
					}

					var removed = eng.prune( state );
					state.history = state.history.filter( function ( qid ) {
						return removed.indexOf( qid ) === -1;
					} );

					advance( removed.length ? 'Answers that no longer apply to your symptoms were cleared.' : '' );
				} )
			] )
		);

		if ( data.directoryUrl && ! ids.length ) {
			wrap.appendChild( h( 'p', { class: 'dak-sc-small' }, [ 'Can\'t find your symptom? ', h( 'a', { href: safeUrl( data.directoryUrl ), text: 'Browse all doctors' } ) ] ) );
		}

		var focusSearch = 'confirm' === state.stage && message;

		show( 'confirm', wrap, message );

		if ( focusSearch ) {
			search.focus();
		}
	}

	/* ------------------------------------------------------------------
	 * Questions
	 * ---------------------------------------------------------------- */

	/** Next step after an answer: warning signs → question → review. */
	function advance( message ) {
		var flags = eng.redFlags( state );

		if ( flags.length ) {
			renderEmergency( flags );
			return;
		}

		var next = eng.nextQuestion( state );

		if ( next ) {
			renderQuestion( next, message );
			return;
		}

		state.returnToReview = false;
		renderReview( message );
	}

	function renderQuestion( question, message ) {
		var current = state.answers[ question.id ];
		var hasAnswer = Object.prototype.hasOwnProperty.call( state.answers, question.id );
		var progress = eng.progress( state );
		var number = progress.answered + ( hasAnswer ? 0 : 1 );
		var wrap = h( 'section', { class: 'dak-sc-card dak-sc-stage dak-sc-question', 'aria-labelledby': 'dak-sc-q-title' } );
		var error = h( 'p', { class: 'dak-sc-error', role: 'alert', id: 'dak-sc-q-error', hidden: true } );
		var hint = null;
		var field;

		state.current = question.id;

		var total = progress.answered + progress.remaining;
		var fill = h( 'span', { class: 'dak-sc-progress-fill' } );
		fill.style.width = Math.max( 8, Math.round( ( ( number - 1 ) / Math.max( total, 1 ) ) * 100 ) ) + '%';

		wrap.appendChild(
			h( 'div', { class: 'dak-sc-progress' }, [
				h( 'span', { class: 'dak-sc-progress-text', text: 'Question ' + number } ),
				h( 'span', { class: 'dak-sc-progress-bar', 'aria-hidden': 'true' }, [ fill ] )
			] )
		);

		wrap.appendChild( h( 'h2', { id: 'dak-sc-q-title', 'data-sc-focus': true, text: question.text } ) );

		if ( question.help ) {
			wrap.appendChild( h( 'p', { class: 'dak-sc-lead', id: 'dak-sc-q-help', text: question.help } ) );
		}

		// "How long" — suggest the option matching what they wrote.
		if ( 'duration' === question.type && ! hasAnswer && null !== state.durationHint ) {
			var suggested = eng.durationOptionFor( question, state.durationHint );

			if ( suggested ) {
				current = suggested;
				hint = h( 'p', { class: 'dak-sc-info', text: 'We\'ve selected an answer based on what you wrote. Please check it.' } );
			}
		}

		if ( 'text' === question.type ) {
			field = h( 'textarea', { id: 'dak-sc-q-text', class: 'dak-sc-textarea', rows: '4', maxlength: '600', autocomplete: 'off', 'aria-labelledby': 'dak-sc-q-title', 'aria-describedby': question.help ? 'dak-sc-q-help' : null } );
			field.value = hasAnswer ? String( current || '' ) : '';
			wrap.appendChild( field );
		} else {
			var multi = 'multi' === question.type;
			var options = ( question.options || [] ).slice();

			if ( question.allow_unsure ) {
				options.push( { value: UNSURE, label: 'Not sure', exclusive: true } );
			}

			var selected = Array.isArray( current ) ? current : ( undefined === current || null === current ? [] : [ current ] );

			field = h( 'fieldset', { class: 'dak-sc-options', 'aria-describedby': 'dak-sc-q-error' }, [ h( 'legend', { class: 'dak-sc-sr', text: question.text } ) ] );

			options.forEach( function ( option, index ) {
				var id = 'dak-sc-opt-' + index;
				var input = h( 'input', {
					type: multi ? 'checkbox' : 'radio',
					name: 'dak-sc-q',
					id: id,
					value: option.value,
					checked: selected.indexOf( option.value ) !== -1,
					'data-exclusive': option.exclusive ? '1' : null
				} );

				if ( multi ) {
					input.addEventListener( 'change', function () {
						if ( ! input.checked ) {
							return;
						}

						Array.prototype.forEach.call( field.querySelectorAll( 'input' ), function ( other ) {
							if ( other !== input && ( option.exclusive || '1' === other.getAttribute( 'data-exclusive' ) ) ) {
								other.checked = false;
							}
						} );
					} );
				}

				field.appendChild(
					h( 'label', { class: 'dak-sc-option' + ( UNSURE === option.value ? ' is-unsure' : '' ), for: id }, [ input, h( 'span', { text: option.label } ) ] )
				);
			} );

			if ( hint ) {
				wrap.appendChild( hint );
			}

			wrap.appendChild( field );
		}

		wrap.appendChild( error );

		function readValue() {
			if ( 'text' === question.type ) {
				return field.value.trim();
			}

			var checked = Array.prototype.filter.call( field.querySelectorAll( 'input' ), function ( input ) {
				return input.checked;
			} ).map( function ( input ) {
				return input.value;
			} );

			if ( ! checked.length ) {
				return null;
			}

			return 'multi' === question.type ? checked : checked[ 0 ];
		}

		var actions = h( 'div', { class: 'dak-sc-actions' } );

		actions.appendChild(
			button( 'Back', 'dak-button-secondary', function () {
				goBack( question.id );
			} )
		);

		actions.appendChild(
			button( 'Continue', 'dak-button-primary', function () {
				var value = readValue();

				if ( null === value && 'text' !== question.type ) {
					error.textContent = question.allow_unsure ? 'Please choose an answer, or choose “Not sure”.' : 'Please choose an answer.';
					error.hidden = false;
					var first = field.querySelector( 'input' );

					if ( first ) {
						first.focus();
					}

					return;
				}

				if ( 'text' === question.type && '' === value && false !== question.required ) {
					error.textContent = 'Please type an answer.';
					error.hidden = false;
					field.focus();
					return;
				}

				answer( question, value );
			} )
		);

		wrap.appendChild( actions );
		show( 'question', wrap, message );
	}

	function sameValue( a, b ) {
		return JSON.stringify( a ) === JSON.stringify( b );
	}

	function answer( question, value ) {
		var had = Object.prototype.hasOwnProperty.call( state.answers, question.id );
		var changed = had && ! sameValue( state.answers[ question.id ], value );
		var message = '';

		state.answers[ question.id ] = value;

		if ( state.history.indexOf( question.id ) === -1 ) {
			state.history.push( question.id );
		}

		if ( changed ) {
			var removed = eng.prune( state );

			state.history = state.history.filter( function ( qid ) {
				return removed.indexOf( qid ) === -1;
			} );

			if ( removed.length ) {
				message = 'Your change means ' + ( 1 === removed.length ? 'an earlier answer no longer applies, so it was cleared.' : 'some answers no longer apply, so they were cleared.' );
			}
		}

		// Free text: offer anything it mentions that isn't selected yet.
		if ( 'text' === question.type && value ) {
			var parsed = eng.matchText( value );

			state.extraMentions = parsed.matches.filter( function ( m ) {
				return ! m.negated && ! state.symptoms[ m.id ];
			} );

			parsed.alerts.forEach( function ( qid ) {
				if ( state.forced.indexOf( qid ) === -1 ) {
					state.forced.push( qid );
				}
			} );
		}

		if ( state.returnToReview && ! eng.nextQuestion( state ) && ! eng.redFlags( state ).length ) {
			state.returnToReview = false;
			renderReview( message || 'Answer updated.' );
			return;
		}

		advance( message );
	}

	function goBack( qid ) {
		if ( state.returnToReview ) {
			state.returnToReview = false;
			renderReview();
			return;
		}

		var index = state.history.indexOf( qid );
		var previous = index === -1 ? state.history[ state.history.length - 1 ] : state.history[ index - 1 ];

		if ( previous && eng.questionById[ previous ] ) {
			renderQuestion( eng.questionById[ previous ] );
		} else {
			renderConfirm();
		}
	}

	/* ------------------------------------------------------------------
	 * Emergency
	 * ---------------------------------------------------------------- */

	function renderEmergency( flags ) {
		var em = rules.emergency || {};
		var wrap = h( 'section', { class: 'dak-sc-card dak-sc-stage dak-sc-emergency', role: 'alert', 'aria-labelledby': 'dak-sc-em-title' } );

		wrap.appendChild( h( 'h2', { id: 'dak-sc-em-title', 'data-sc-focus': true, text: em.title || 'Get urgent medical help now' } ) );
		wrap.appendChild( h( 'p', { class: 'dak-sc-em-message', text: em.message || 'Please go to the nearest emergency department or contact your local emergency services now.' } ) );

		if ( em.contact_text ) {
			wrap.appendChild( h( 'p', { class: 'dak-sc-em-contact', text: em.contact_text } ) );
		}

		wrap.appendChild( h( 'p', { class: 'dak-sc-em-because', text: 'Because you told us about:' } ) );
		wrap.appendChild(
			h( 'ul', { class: 'dak-sc-em-list' }, flags.map( function ( f ) {
				return h( 'li', { text: f.title } );
			} ) )
		);
		wrap.appendChild( h( 'p', { class: 'dak-sc-small', text: 'This tool can\'t detect every emergency. If you feel very unwell or are worried, get help now even if your symptoms aren\'t listed here.' } ) );

		wrap.appendChild(
			h( 'div', { class: 'dak-sc-actions' }, [
				button( 'Change my answers', 'dak-button-secondary', function () {
					renderReview();
				} ),
				button( 'Start again', 'dak-button-secondary', startAgain )
			] )
		);

		show( 'emergency', wrap, '' );
	}

	/* ------------------------------------------------------------------
	 * Review
	 * ---------------------------------------------------------------- */

	function summaryList( editable ) {
		var list = h( 'dl', { class: 'dak-sc-summary' } );
		var ids = Object.keys( state.symptoms );

		list.appendChild(
			h( 'div', { class: 'dak-sc-summary-row' }, [
				h( 'dt', { text: 'Symptoms' } ),
				h( 'dd', { text: ids.map( function ( id ) {
					return symptomLabel( id ) + ( state.symptoms[ id ].uncertain ? ' (not sure)' : '' );
				} ).join( ', ' ) } ),
				editable ? h( 'dd', { class: 'dak-sc-summary-edit' }, [
					h( 'button', { type: 'button', class: 'dak-sc-link', text: 'Edit', 'aria-label': 'Edit symptoms', onclick: function () {
						renderConfirm();
					} } )
				] ) : null
			] )
		);

		eng.applicableQuestions( state ).forEach( function ( q ) {
			if ( ! Object.prototype.hasOwnProperty.call( state.answers, q.id ) ) {
				return;
			}

			list.appendChild(
				h( 'div', { class: 'dak-sc-summary-row' }, [
					h( 'dt', { text: q.text } ),
					h( 'dd', { text: answerText( q, state.answers[ q.id ] ) } ),
					editable ? h( 'dd', { class: 'dak-sc-summary-edit' }, [
						h( 'button', { type: 'button', class: 'dak-sc-link', text: 'Edit', 'aria-label': 'Edit answer: ' + q.text, onclick: function () {
							state.returnToReview = true;
							renderQuestion( q );
						} } )
					] ) : null
				] )
			);
		} );

		return list;
	}

	function renderReview( message ) {
		var res = eng.results( state );
		var wrap = h( 'section', { class: 'dak-sc-card dak-sc-stage', 'aria-labelledby': 'dak-sc-review-title' } );
		var flags = eng.redFlags( state );

		wrap.appendChild( h( 'h2', { id: 'dak-sc-review-title', 'data-sc-focus': true, text: 'Check your answers' } ) );
		wrap.appendChild( h( 'p', { class: 'dak-sc-lead', text: 'Make sure these are right before you see your results. You can change any answer.' } ) );

		if ( flags.length ) {
			wrap.appendChild( h( 'div', { class: 'dak-sc-info is-danger', role: 'alert' }, [
				h( 'p', { text: 'Your answers include an urgent warning sign. Please change it if it was a mistake, or get urgent help now.' } )
			] ) );
		}

		res.conflicts.forEach( function ( c ) {
			wrap.appendChild( h( 'div', { class: 'dak-sc-info is-warning', role: 'note' }, [ h( 'p', { text: c.message } ) ] ) );
		} );

		( state.extraMentions || [] ).forEach( function ( m ) {
			wrap.appendChild(
				h( 'div', { class: 'dak-sc-info' }, [
					h( 'p', { text: 'You also mentioned: ' + m.label + '.' } ),
					h( 'button', { type: 'button', class: 'dak-sc-link', text: 'Add it to my symptoms', onclick: function () {
						state.symptoms[ m.id ] = { uncertain: m.uncertain, source: 'text' };
						state.extraMentions = ( state.extraMentions || [] ).filter( function ( x ) {
							return x.id !== m.id;
						} );
						advance( m.label + ' added. We have a few more questions.' );
					} } )
				] )
			);
		} );

		wrap.appendChild( summaryList( true ) );

		if ( res.unsureCount ) {
			wrap.appendChild( h( 'p', { class: 'dak-sc-small', text: 'You answered “Not sure” to ' + ( 1 === res.unsureCount ? 'one question' : res.unsureCount + ' questions' ) + '. That\'s fine — your results will be more general.' } ) );
		}

		wrap.appendChild(
			h( 'div', { class: 'dak-sc-actions' }, [
				button( 'Back', 'dak-button-secondary', function () {
					var last = state.history[ state.history.length - 1 ];

					if ( last && eng.questionById[ last ] ) {
						renderQuestion( eng.questionById[ last ] );
					} else {
						renderConfirm();
					}
				} ),
				button( 'See results', 'dak-button-primary', function () {
					if ( eng.redFlags( state ).length ) {
						renderEmergency( eng.redFlags( state ) );
						return;
					}

					if ( eng.nextQuestion( state ) ) {
						advance( 'We have a few more questions first.' );
						return;
					}

					renderResults();
				} )
			] )
		);

		show( 'review', wrap, message );
	}

	/* ------------------------------------------------------------------
	 * Results
	 * ---------------------------------------------------------------- */

	var resultsDoctors = null;
	var lastResults = null;

	function renderResults() {
		var res = eng.results( state );
		var wrap = h( 'div', { class: 'dak-sc-results' } );

		lastResults = res;

		// 1. Summary.
		var summary = h( 'section', { class: 'dak-sc-card', 'aria-labelledby': 'dak-sc-res-title' } );
		summary.appendChild( h( 'h2', { id: 'dak-sc-res-title', 'data-sc-focus': true, text: 'Your results' } ) );
		summary.appendChild( h( 'p', { class: 'dak-sc-note', text: 'This is general guidance to help you find care. It is not a diagnosis.' } ) );

		var details = h( 'details', { class: 'dak-sc-details' }, [ h( 'summary', { text: 'What you told us' } ), summaryList( false ) ] );
		summary.appendChild( details );
		summary.appendChild( h( 'p', {}, [ h( 'button', { type: 'button', class: 'dak-sc-link', text: 'Edit my answers', onclick: function () {
			renderReview();
		} } ) ] ) );
		wrap.appendChild( summary );

		// 2. Next steps.
		var steps = h( 'section', { class: 'dak-sc-card', 'aria-labelledby': 'dak-sc-steps-title' } );
		steps.appendChild( h( 'h2', { id: 'dak-sc-steps-title', text: 'What to do next' } ) );

		res.nextSteps.forEach( function ( step ) {
			var u = URGENCY[ step.urgency ] || URGENCY.routine;

			steps.appendChild(
				h( 'div', { class: 'dak-sc-step ' + u.cls }, [
					h( 'span', { class: 'dak-sc-badge ' + u.cls, text: u.label } ),
					h( 'h3', { text: step.title } ),
					h( 'p', { text: step.text } )
				] )
			);
		} );

		if ( res.unsureCount || res.conflicts.length ) {
			steps.appendChild( h( 'p', { class: 'dak-sc-small', text: 'Some of your answers were uncertain or didn\'t quite fit together, so this guidance is more general. A doctor can go through it with you.' } ) );
		}

		steps.appendChild( h( 'p', { class: 'dak-sc-small', text: 'If your symptoms get worse, or you develop new symptoms such as severe pain, difficulty breathing, fainting or confusion, get urgent help straight away. This tool can\'t detect every emergency.' } ) );
		wrap.appendChild( steps );

		// 3. Possible explanations.
		var ex = h( 'section', { class: 'dak-sc-card', 'aria-labelledby': 'dak-sc-ex-title' } );
		ex.appendChild( h( 'h2', { id: 'dak-sc-ex-title', text: 'Possible explanations' } ) );
		ex.appendChild( h( 'p', { class: 'dak-sc-lead', text: 'These are some things that can cause symptoms like yours. They are possibilities, not a diagnosis — only a doctor who assesses you can tell what\'s causing your symptoms, and it may be something not listed here.' } ) );

		if ( res.explanations.length ) {
			ex.appendChild(
				h( 'ul', { class: 'dak-sc-ex-list' }, res.explanations.map( function ( e ) {
					return h( 'li', {}, [ h( 'h3', { text: e.title } ), h( 'p', { text: e.summary } ) ] );
				} ) )
			);
		} else {
			ex.appendChild( h( 'p', { text: 'We can\'t suggest likely explanations from your answers. A doctor can help work out what\'s going on.' } ) );
		}

		wrap.appendChild( ex );

		// 4. Who can help.
		var sp = h( 'section', { class: 'dak-sc-card', 'aria-labelledby': 'dak-sc-sp-title' } );
		sp.appendChild( h( 'h2', { id: 'dak-sc-sp-title', text: 'Who can help' } ) );

		sp.appendChild(
			h( 'ul', { class: 'dak-sc-sp-list' }, res.specialties.map( function ( s ) {
				return h( 'li', {}, [
					h( 'strong', { text: specialtyLabel( s.slug ) } ),
					s.fallback
						? h( 'span', { text: 'Can assess your symptoms and refer you to a specialist if needed.' } )
						: h( 'span', { text: 'Suggested for: ' + s.reasons.join( ', ' ) } )
				] );
			} ) )
		);
		wrap.appendChild( sp );

		// 5. Doctors.
		resultsDoctors = h( 'section', { class: 'dak-sc-card dak-sc-doctors', 'aria-labelledby': 'dak-sc-docs-title' } );
		wrap.appendChild( resultsDoctors );

		wrap.appendChild(
			h( 'div', { class: 'dak-sc-actions dak-sc-actions-end' }, [ button( 'Start again', 'dak-button-secondary', startAgain ) ] )
		);

		show( 'results', wrap, 'Results ready.' );
		renderDoctors();
	}

	/* ------------------------------------------------------------------
	 * Location
	 * ---------------------------------------------------------------- */

	function locate() {
		if ( ! navigator.geolocation ) {
			loc.status = 'unavailable';
			renderDoctors();
			return;
		}

		loc.status = 'locating';
		renderDoctors();

		navigator.geolocation.getCurrentPosition(
			function ( position ) {
				loc.origin = { lat: position.coords.latitude, lng: position.coords.longitude };
				loc.area = eng.nameArea( loc.origin, data.places || [] );
				loc.status = 'granted';
				loc.mode = 'auto';
				renderDoctors( 'Showing doctors near ' + ( loc.area || 'your location' ) + '.' );
			},
			function ( error ) {
				loc.status = 1 === error.code ? 'denied' : ( 3 === error.code ? 'timeout' : 'unavailable' );
				renderDoctors();
			},
			{ enableHighAccuracy: false, timeout: 15000, maximumAge: 600000 }
		);
	}

	/**
	 * Starts finding the visitor's location when they press Start, so it's
	 * ready by the results. If they've already allowed it, no prompt shows;
	 * if they've blocked it, we don't ask again.
	 */
	function startLocation() {
		if ( 'idle' !== loc.status ) {
			return;
		}

		if ( ! navigator.geolocation ) {
			loc.status = 'unavailable';
			return;
		}

		if ( navigator.permissions && navigator.permissions.query ) {
			navigator.permissions.query( { name: 'geolocation' } ).then( function ( status ) {
				if ( 'denied' === status.state ) {
					loc.status = 'denied';
					renderDoctors();
				} else {
					locate();
				}
			}, locate );
			loc.status = 'locating';
			return;
		}

		locate();
	}

	function effectiveMode() {
		if ( 'city' === loc.mode && loc.city ) {
			return 'city';
		}

		if ( 'none' === loc.mode ) {
			return 'none';
		}

		return 'granted' === loc.status ? 'distance' : 'none';
	}

	function cityLabel( slug ) {
		var c = ( data.cities || [] ).filter( function ( x ) {
			return x.slug === slug;
		} )[ 0 ];

		return c ? c.label : slug;
	}

	function locationBar() {
		var mode = effectiveMode();
		var bar = h( 'div', { class: 'dak-sc-locbar' } );
		var text;

		if ( 'locating' === loc.status && 'auto' === loc.mode ) {
			text = 'Finding doctors near you…';
		} else if ( 'distance' === mode ) {
			text = 'Near ' + ( loc.area || 'your current location' );
		} else if ( 'city' === mode ) {
			text = 'In ' + cityLabel( loc.city );
		} else {
			text = 'All locations';
		}

		bar.appendChild( h( 'span', { class: 'dak-sc-locbar-text' }, [ h( 'span', { class: 'dak-sc-pin', 'aria-hidden': 'true' } ), text ] ) );
		bar.appendChild( h( 'button', {
			type: 'button',
			class: 'dak-sc-link',
			'aria-expanded': loc.panelOpen ? 'true' : 'false',
			text: 'Change',
			'aria-label': 'Change location',
			onclick: function () {
				loc.panelOpen = ! loc.panelOpen;
				renderDoctors();
				var first = resultsDoctors && resultsDoctors.querySelector( '.dak-sc-locpanel button, .dak-sc-locpanel select' );

				if ( first && loc.panelOpen ) {
					first.focus();
				}
			}
		} ) );

		return bar;
	}

	function locationPanel( reason ) {
		var panel = h( 'div', { class: 'dak-sc-locpanel' } );

		if ( reason ) {
			panel.appendChild( h( 'p', { text: reason } ) );
		}

		var useMine = button( 'granted' === loc.status ? 'Use my current location' : 'Try my location again', 'dak-button-secondary', function () {
			loc.mode = 'auto';
			loc.panelOpen = false;

			if ( 'granted' === loc.status ) {
				renderDoctors( 'Showing doctors near ' + ( loc.area || 'your location' ) + '.', true );
			} else {
				locate();
				renderDoctors( 'Finding your location…', true );
			}
		} );

		panel.appendChild( useMine );

		if ( 'denied' === loc.status ) {
			panel.appendChild( h( 'p', { class: 'dak-sc-small', text: 'Location is blocked for this site. To use it, allow location in your browser\'s settings, then try again.' } ) );
		}

		if ( ( data.cities || [] ).length ) {
			var select = h( 'select', { id: 'dak-sc-city', class: 'dak-sc-input' }, [ h( 'option', { value: '', text: 'Choose a city' } ) ].concat(
				data.cities.map( function ( c ) {
					return h( 'option', { value: c.slug, text: c.label, selected: c.slug === loc.city } );
				} )
			) );

			panel.appendChild( h( 'label', { class: 'dak-sc-label', for: 'dak-sc-city', text: 'Or choose a city' } ) );
			panel.appendChild(
				h( 'div', { class: 'dak-sc-inline' }, [
					select,
					button( 'Show doctors', 'dak-button-secondary', function () {
						if ( ! select.value ) {
							select.focus();
							return;
						}

						loc.mode = 'city';
						loc.city = select.value;
						loc.panelOpen = false;
						renderDoctors( 'Showing doctors in ' + cityLabel( loc.city ) + '.', true );
					} )
				] )
			);
		}

		panel.appendChild(
			h( 'p', {}, [ h( 'button', { type: 'button', class: 'dak-sc-link', text: 'Show doctors in all locations, without distances', onclick: function () {
				loc.mode = 'none';
				loc.panelOpen = false;
				renderDoctors( 'Showing doctors in all locations.', true );
			} } ) ] )
		);

		return panel;
	}

	/* ------------------------------------------------------------------
	 * Doctors
	 * ---------------------------------------------------------------- */

	function formatKm( km ) {
		if ( km < 1 ) {
			return 'Less than 1 km away';
		}

		return 'About ' + ( km < 10 ? km.toFixed( 1 ) : Math.round( km ) ) + ' km away';
	}

	function reasonFor( slug ) {
		var s = ( lastResults.specialties || [] ).filter( function ( x ) {
			return x.slug === slug;
		} )[ 0 ];

		if ( ! s || s.fallback ) {
			return 'Can assess your symptoms and refer you if needed';
		}

		return 'Suggested for: ' + s.reasons.join( ', ' );
	}

	function avatar( doc ) {
		var src = safeUrl( doc.avatar );

		return src
			? h( 'img', { class: 'dak-sc-doc-photo', src: src, alt: '', loading: 'lazy', width: '64', height: '64' } )
			: h( 'span', { class: 'dak-sc-doc-photo is-initials', 'aria-hidden': 'true', text: doc.name.replace( /^(dr|doctor)\.?\s+/i, '' ).charAt( 0 ) } );
	}

	function clinicLine( clinic, showDistance ) {
		return h( 'div', { class: 'dak-sc-doc-clinic' }, [
			h( 'p', { class: 'dak-sc-doc-clinic-name', text: clinic.label + ( clinic.place ? ' · ' + clinic.place : '' ) } ),
			showDistance && null !== clinic.distanceKm ? h( 'p', { class: 'dak-sc-doc-distance', text: formatKm( clinic.distanceKm ) + ' (approximate, straight-line)' } ) : null
		] );
	}

	function doctorCard( entry, showDistance ) {
		var doc = entry.doctor;
		var clinic = entry.primary;
		var card = h( 'article', { class: 'dak-sc-doc' } );
		var body = h( 'div', { class: 'dak-sc-doc-body' } );
		var profile = safeUrl( doc.profile_url );
		var book = clinic ? safeUrl( clinic.booking_url ) : null;

		body.appendChild( h( 'h3', { class: 'dak-sc-doc-name', text: doc.name } ) );
		body.appendChild( h( 'p', { class: 'dak-sc-doc-spec', text: specialtyLabel( entry.matchedSpecialty ) } ) );
		body.appendChild( h( 'p', { class: 'dak-sc-doc-reason', text: reasonFor( entry.matchedSpecialty ) } ) );
		body.appendChild( clinicLine( clinic, showDistance ) );

		var facts = h( 'dl', { class: 'dak-sc-doc-facts' } );

		if ( clinic.fee ) {
			facts.appendChild( h( 'div', {}, [ h( 'dt', { text: 'Clinic fee' } ), h( 'dd', { text: clinic.fee } ) ] ) );
		}

		facts.appendChild( h( 'div', {}, [ h( 'dt', { text: 'Next available' } ), h( 'dd', { text: clinic.next_available || 'No open times in the next 7 days' } ) ] ) );
		body.appendChild( facts );

		if ( entry.others.length ) {
			var others = h( 'details', { class: 'dak-sc-doc-others' }, [
				h( 'summary', { text: entry.others.length + ' other ' + ( 1 === entry.others.length ? 'location' : 'locations' ) } )
			] );

			entry.others.forEach( function ( other ) {
				var url = safeUrl( other.booking_url );

				others.appendChild(
					h( 'div', { class: 'dak-sc-doc-other' }, [
						clinicLine( other, showDistance ),
						other.fee ? h( 'p', { class: 'dak-sc-small', text: 'Fee: ' + other.fee } ) : null,
						h( 'p', { class: 'dak-sc-small', text: 'Next available: ' + ( other.next_available || 'No open times in the next 7 days' ) } ),
						url ? h( 'a', { class: 'dak-sc-link', href: url, text: 'Book at this clinic', 'aria-label': 'Book ' + doc.name + ' at ' + other.label } ) : null
					] )
				);
			} );

			body.appendChild( others );
		}

		body.appendChild(
			h( 'div', { class: 'dak-sc-doc-actions' }, [
				profile ? h( 'a', { class: 'dak-button dak-button-secondary dak-sc-btn', href: profile, text: 'View profile', 'aria-label': 'View profile of ' + doc.name } ) : null,
				book ? h( 'a', { class: 'dak-button dak-button-primary dak-sc-btn', href: book, text: 'Book', 'aria-label': 'Book ' + doc.name + ' at ' + clinic.label } ) : null
			] )
		);

		card.appendChild( avatar( doc ) );
		card.appendChild( body );

		return card;
	}

	function onlineCard( entry ) {
		var doc = entry.doctor;
		var card = h( 'article', { class: 'dak-sc-doc' } );
		var body = h( 'div', { class: 'dak-sc-doc-body' } );
		var profile = safeUrl( doc.profile_url );
		var book = safeUrl( doc.video.booking_url );

		body.appendChild( h( 'h3', { class: 'dak-sc-doc-name', text: doc.name } ) );
		body.appendChild( h( 'p', { class: 'dak-sc-doc-spec', text: specialtyLabel( entry.matchedSpecialty ) + ' · Video consultation' } ) );
		body.appendChild( h( 'p', { class: 'dak-sc-doc-reason', text: reasonFor( entry.matchedSpecialty ) } ) );

		var facts = h( 'dl', { class: 'dak-sc-doc-facts' } );

		if ( doc.video.fee ) {
			facts.appendChild( h( 'div', {}, [ h( 'dt', { text: 'Video fee' } ), h( 'dd', { text: doc.video.fee } ) ] ) );
		}

		facts.appendChild( h( 'div', {}, [ h( 'dt', { text: 'Next available' } ), h( 'dd', { text: doc.video.next_available || 'No open times in the next 7 days' } ) ] ) );
		body.appendChild( facts );

		body.appendChild(
			h( 'div', { class: 'dak-sc-doc-actions' }, [
				profile ? h( 'a', { class: 'dak-button dak-button-secondary dak-sc-btn', href: profile, text: 'View profile', 'aria-label': 'View profile of ' + doc.name } ) : null,
				book ? h( 'a', { class: 'dak-button dak-button-primary dak-sc-btn', href: book, text: 'Book video consultation', 'aria-label': 'Book a video consultation with ' + doc.name } ) : null
			] )
		);

		card.appendChild( avatar( doc ) );
		card.appendChild( body );

		return card;
	}

	function doctorGroup( title, note, entries, cardFn, limit ) {
		var group = h( 'div', { class: 'dak-sc-doc-group' } );
		var list = h( 'div', { class: 'dak-sc-doc-list' } );
		var shown = 0;

		group.appendChild( h( 'h3', { class: 'dak-sc-group-title', text: title } ) );

		if ( note ) {
			group.appendChild( h( 'p', { class: 'dak-sc-small', text: note } ) );
		}

		function more() {
			entries.slice( shown, shown + limit ).forEach( function ( entry ) {
				list.appendChild( cardFn( entry ) );
			} );
			shown = Math.min( entries.length, shown + limit );
		}

		more();
		group.appendChild( list );

		if ( entries.length > shown ) {
			var moreBtn = button( 'Show more doctors', 'dak-button-secondary', function () {
				var before = shown;
				more();
				var next = list.children[ before ];

				if ( next ) {
					var heading = next.querySelector( 'h3' );
					heading.setAttribute( 'tabindex', '-1' );
					heading.focus();
				}

				if ( shown >= entries.length ) {
					moreBtn.remove();
				}
			} );
			group.appendChild( moreBtn );
		}

		return group;
	}

	/**
	 * @param {string}  message   Announced to screen readers.
	 * @param {boolean} moveFocus The visitor changed something here — move
	 *                            focus to the heading (never for updates
	 *                            that arrive on their own, like the location).
	 */
	function renderDoctors( message, moveFocus ) {
		// Location can resolve while the visitor is still answering
		// questions — nothing to redraw until the results are on screen.
		if ( ! resultsDoctors || ! resultsDoctors.isConnected ) {
			return;
		}

		var box = resultsDoctors;
		var mode = effectiveMode();
		var specs = lastResults ? lastResults.specialties : [];
		var labels = specs.map( function ( s ) {
			return specialtyLabel( s.slug );
		} );

		box.textContent = '';
		box.appendChild( h( 'h2', { id: 'dak-sc-docs-title', text: 'Doctors who can help' } ) );
		box.appendChild( locationBar() );

		var waiting = 'locating' === loc.status && 'auto' === loc.mode;
		var failed = 'auto' === loc.mode && ( 'denied' === loc.status || 'unavailable' === loc.status || 'timeout' === loc.status );

		if ( loc.panelOpen || failed ) {
			var reason = null;

			if ( 'denied' === loc.status && 'auto' === loc.mode ) {
				reason = 'We couldn\'t use your location, so we can\'t show distances. You can choose a city instead, or see doctors in all locations below.';
			} else if ( 'timeout' === loc.status && 'auto' === loc.mode ) {
				reason = 'Finding your location took too long. You can try again, choose a city, or see doctors in all locations below.';
			} else if ( 'unavailable' === loc.status && 'auto' === loc.mode ) {
				reason = 'Your location isn\'t available on this device. You can choose a city, or see doctors in all locations below.';
			}

			box.appendChild( locationPanel( reason ) );
		}

		if ( waiting ) {
			box.appendChild( h( 'p', { class: 'dak-sc-loading', role: 'status', text: 'Finding doctors near you…' } ) );

			if ( message ) {
				announce( message );
			}

			return;
		}

		var opts = { radiusKm: loc.radius };

		if ( 'distance' === mode ) {
			opts.origin = loc.origin;
		} else if ( 'city' === mode ) {
			opts.city = loc.city;
		}

		var ranked = eng.rankDoctors( data.doctors || [], specs, opts );
		var any = ranked.nearby.length + ranked.unknownDistance.length + ranked.beyond.length + ranked.online.length;
		var showDistance = 'distance' === mode;

		if ( ! any ) {
			box.appendChild(
				h( 'div', { class: 'dak-sc-info' }, [
					h( 'p', { text: 'We couldn\'t find a ' + labels.join( ' or ' ) + ' on Doctor AK ' + ( 'city' === mode ? 'in ' + cityLabel( loc.city ) + ' ' : '' ) + 'at the moment.' } ),
					'city' === mode ? h( 'p', {}, [ h( 'button', { type: 'button', class: 'dak-sc-link', text: 'Show all locations', onclick: function () {
						loc.mode = 'none';
						renderDoctors( 'Showing doctors in all locations.', true );
					} } ) ] ) : null,
					safeUrl( data.directoryUrl ) ? h( 'p', {}, [ h( 'a', { href: safeUrl( data.directoryUrl ), text: 'Browse all doctors' } ) ] ) : null
				] )
			);
		} else {
			if ( ranked.nearby.length ) {
				box.appendChild( doctorGroup(
					'distance' === mode ? 'Nearest to you' : ( 'city' === mode ? 'In ' + cityLabel( loc.city ) : 'Clinic visits' ),
					'distance' === mode ? 'Distances are approximate straight-line distances from your location, not travel distances.' : null,
					ranked.nearby,
					function ( e ) {
						return doctorCard( e, showDistance );
					},
					6
				) );
			} else if ( 'distance' === mode ) {
				box.appendChild( h( 'div', { class: 'dak-sc-info' }, [
					h( 'p', { text: 'No suitable doctors have clinics within ' + loc.radius + ' km of ' + ( loc.area || 'your location' ) + '.' } )
				] ) );
			}

			if ( 'distance' === mode && ranked.beyond.length ) {
				var wider = EXPANDED_KM > loc.radius ? EXPANDED_KM : null;

				box.appendChild( h( 'p', {}, [
					button( wider ? 'Search a wider area (up to ' + wider + ' km)' : 'Show doctors further away', 'dak-button-secondary', function () {
						loc.radius = wider || 100000;
						renderDoctors( 'Search area widened.', true );
					} )
				] ) );
			}

			if ( ranked.unknownDistance.length ) {
				box.appendChild( doctorGroup(
					'distance' === mode ? 'Other clinics (distance not available)' : 'More clinics',
					'distance' === mode ? 'These clinics haven\'t added their map location yet, so we can\'t show how far away they are.' : null,
					ranked.unknownDistance,
					function ( e ) {
						return doctorCard( e, false );
					},
					4
				) );
			}

			if ( ranked.online.length ) {
				box.appendChild( doctorGroup(
					'Online video consultations',
					'Talk to a doctor by video from wherever you are. A video consultation isn\'t suitable for every problem — the doctor will tell you if you need to be seen in person.',
					ranked.online,
					onlineCard,
					4
				) );
			}

			if ( safeUrl( data.directoryUrl ) ) {
				box.appendChild( h( 'p', { class: 'dak-sc-small' }, [ 'Prefer to look yourself? ', h( 'a', { href: safeUrl( data.directoryUrl ), text: 'Browse all doctors' } ) ] ) );
			}
		}

		if ( message ) {
			announce( message );
		}

		if ( moveFocus ) {
			var heading = box.querySelector( '#dak-sc-docs-title' );
			heading.setAttribute( 'tabindex', '-1' );
			heading.focus();
		}
	}

	/* ------------------------------------------------------------------
	 * Start again
	 * ---------------------------------------------------------------- */

	function startAgain() {
		state = freshState();
		introText.value = '';
		introSearch.value = '';
		refreshIntroChips();
		resultsDoctors = null;
		lastResults = null;
		loc.panelOpen = false;
		loc.radius = RADIUS_KM;
		show( 'intro', null, 'Started again. Your previous answers have been cleared.' );
	}
}() );
