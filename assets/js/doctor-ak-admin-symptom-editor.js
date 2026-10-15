/**
 * Doctor AK Portal — Symptom Checker rules form editor (dashboard).
 *
 * Lets clinicians and administrators edit the ruleset with ordinary form
 * fields instead of JSON: symptoms, questions, warning phrases, warning
 * signs, next steps, possible explanations and answer checks, each with a
 * plain-language "Show when…" builder. Internal ids and option values are
 * generated and kept stable behind the scenes; deleting something also
 * removes it from the rules that use it.
 *
 * The form reads the saved draft from the (hidden) JSON box and exposes
 * window.DAKSymptomEditor.toJSON() for doctor-ak-admin-symptom-checker.js,
 * which saves/validates exactly as before. Technical staff can still open
 * "Advanced: edit as JSON".
 */
( function () {
	'use strict';

	var cfg = window.dakAdminSymptomChecker || {};
	var mount = document.querySelector( '[data-admin-sc-editor]' );
	var jsonBox = document.querySelector( '[data-admin-sc-json]' );
	var advanced = document.querySelector( '[data-admin-sc-advanced]' );
	var advancedNote = document.querySelector( '[data-admin-sc-advanced-note]' );

	if ( ! mount || ! jsonBox ) {
		return;
	}

	var SPECIALTIES = cfg.specialties || {};
	var TYPES = [
		[ 'single', 'One answer' ],
		[ 'multi', 'Several answers can be chosen' ],
		[ 'duration', 'How long (time)' ],
		[ 'severity', 'How severe' ],
		[ 'text', 'Free text (patient types)' ]
	];
	var URGENCIES = [
		[ 'urgent', 'Urgent — get seen today' ],
		[ 'soon', 'Soon — within a few days' ],
		[ 'routine', 'Routine appointment' ],
		[ 'self_care', 'Self-care' ]
	];
	var LISTS = [ 'symptoms', 'text_alerts', 'questions', 'red_flags', 'next_steps', 'explanations', 'conflicts' ];

	var model = null;
	var openState = {};
	var uid = 0;

	/* ------------------------------------------------------------------
	 * Helpers
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
			} else if ( 'value' === key ) {
				node.value = value;
			} else if ( 'checked' === key ) {
				node.checked = !! value;
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

	function nextId() {
		uid += 1;
		return 'dak-sce-' + uid;
	}

	function changed() {
		mount.dispatchEvent( new CustomEvent( 'dak-sc-editor-change', { bubbles: true } ) );
	}

	function makeId( text, taken, prefix ) {
		var base = String( text || '' ).toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' ).slice( 0, 40 ) || prefix || 'item';

		if ( /^[0-9]/.test( base ) ) {
			base = ( prefix || 'x' ) + '_' + base;
		}

		if ( 'unsure' === base ) {
			base = 'unsure_answer';
		}

		var id = base;
		var n = 2;

		while ( taken.indexOf( id ) !== -1 ) {
			id = base + '_' + n;
			n += 1;
		}

		return id;
	}

	function ids( list ) {
		return ( list || [] ).map( function ( x ) {
			return x.id;
		} );
	}

	function short( text, max ) {
		text = String( text || '' );
		return text.length > ( max || 60 ) ? text.slice( 0, ( max || 60 ) - 1 ) + '…' : text;
	}

	function symptomLabel( id ) {
		var s = model.symptoms.filter( function ( x ) {
			return x.id === id;
		} )[ 0 ];

		return s ? s.label || id : id;
	}

	function questionById( id ) {
		return model.questions.filter( function ( q ) {
			return q.id === id;
		} )[ 0 ];
	}

	function normalise( rules ) {
		rules = rules && 'object' === typeof rules ? rules : {};
		rules.meta = rules.meta || { name: '', is_demo: false };
		rules.emergency = rules.emergency || { title: '', message: '', contact_text: '' };
		LISTS.forEach( function ( key ) {
			if ( ! Array.isArray( rules[ key ] ) ) {
				rules[ key ] = [];
			}
		} );
		rules.questions.forEach( function ( q ) {
			if ( 'text' !== q.type && ! Array.isArray( q.options ) ) {
				q.options = [];
			}
		} );

		return rules;
	}

	function blankRules() {
		return normalise( {
			meta: { name: 'Clinic rules', is_demo: false },
			emergency: {
				title: 'Get urgent medical help now',
				message: '',
				contact_text: ''
			},
			fallback_specialty: 'general-physician',
			next_steps: [ { id: 'default_step', fallback: true, urgency: 'routine', title: '', text: '', when: { all: [] } } ]
		} );
	}

	/* ------------------------------------------------------------------
	 * Conditions: plain-language rows <-> rule language
	 * ---------------------------------------------------------------- */

	/** One rule-language condition → an editable row, or null if it can't be shown as a row. */
	function parseRow( c ) {
		if ( ! c || 'object' !== typeof c ) {
			return null;
		}

		if ( c.not ) {
			var inner = parseRow( c.not );

			if ( inner && ! inner.not ) {
				inner.not = true;
				return inner;
			}

			return null;
		}

		if ( c.symptom ) {
			return { kind: 'symptoms', ids: [ c.symptom ], not: false };
		}

		if ( Array.isArray( c.any ) && c.any.length && c.any.every( function ( x ) {
			return x && x.symptom && 1 === Object.keys( x ).length;
		} ) ) {
			return { kind: 'symptoms', ids: c.any.map( function ( x ) {
				return x.symptom;
			} ), not: false };
		}

		if ( c.answered ) {
			return { kind: 'answered', q: c.answered, not: false };
		}

		if ( c.answer ) {
			if ( undefined !== c.equals ) {
				return { kind: 'answer', q: c.answer, op: 'in', values: [ c.equals ], not: false };
			}

			if ( undefined !== c.includes ) {
				return { kind: 'answer', q: c.answer, op: 'in', values: [ c.includes ], not: false };
			}

			if ( Array.isArray( c[ 'in' ] ) ) {
				return { kind: 'answer', q: c.answer, op: 'in', values: c[ 'in' ].slice(), not: false };
			}

			if ( undefined !== c.gte && undefined === c.lte ) {
				return { kind: 'answer', q: c.answer, op: 'gte', score: c.gte, not: false };
			}

			if ( undefined !== c.lte && undefined === c.gte ) {
				return { kind: 'answer', q: c.answer, op: 'lte', score: c.lte, not: false };
			}
		}

		return null;
	}

	/** Whole condition → { mode, rows } or null (shown as "advanced"). */
	function parseCondition( c ) {
		if ( ! c || 'object' !== typeof c || ! Object.keys( c ).length ) {
			return { mode: 'all', rows: [] };
		}

		var mode = Array.isArray( c.any ) ? 'any' : ( Array.isArray( c.all ) ? 'all' : null );
		var list = mode ? c[ mode ] : [ c ];

		// A lone "any of these symptoms" reads better as one row.
		if ( 'any' === mode ) {
			var asRow = parseRow( c );

			if ( asRow ) {
				return { mode: 'all', rows: [ asRow ] };
			}
		}

		var rows = list.map( parseRow );

		if ( rows.some( function ( r ) {
			return ! r;
		} ) ) {
			return null;
		}

		return { mode: mode || 'all', rows: rows };
	}

	function rowToCondition( row ) {
		var c = null;

		if ( 'symptoms' === row.kind ) {
			if ( ! row.ids.length ) {
				return null;
			}

			c = 1 === row.ids.length ? { symptom: row.ids[ 0 ] } : { any: row.ids.map( function ( id ) {
				return { symptom: id };
			} ) };
		} else if ( 'answered' === row.kind ) {
			c = row.q ? { answered: row.q } : null;
		} else if ( 'answer' === row.kind ) {
			if ( ! row.q ) {
				return null;
			}

			if ( 'in' === row.op ) {
				if ( ! row.values || ! row.values.length ) {
					return null;
				}

				c = { answer: row.q, 'in': row.values.slice() };
			} else if ( null !== row.score && undefined !== row.score && '' !== row.score ) {
				c = { answer: row.q };
				c[ row.op ] = Number( row.score );
			}
		}

		return c && row.not ? { not: c } : c;
	}

	function buildCondition( parsed ) {
		var out = {};
		out[ parsed.mode ] = parsed.rows.map( rowToCondition ).filter( Boolean );
		return out;
	}

	/** Walks every condition in the ruleset. */
	function eachCondition( fn ) {
		model.questions.forEach( function ( q ) {
			if ( q.show_if ) {
				q.show_if = fn( q.show_if );
			}
		} );
		[ 'red_flags', 'next_steps', 'explanations', 'conflicts' ].forEach( function ( key ) {
			model[ key ].forEach( function ( item ) {
				if ( item.when ) {
					item.when = fn( item.when );
				}
			} );
		} );
	}

	/**
	 * Removes references from a raw condition. test(c) returns 'drop' to
	 * remove a leaf, or a replacement leaf. Returns null when nothing is left.
	 */
	function strip( c, test ) {
		if ( ! c || 'object' !== typeof c ) {
			return c;
		}

		if ( Array.isArray( c.all ) || Array.isArray( c.any ) ) {
			var key = Array.isArray( c.all ) ? 'all' : 'any';
			var out = {};
			out[ key ] = c[ key ].map( function ( x ) {
				return strip( x, test );
			} ).filter( Boolean );

			// "any" of nothing would never be true — drop it.
			return 'any' === key && ! out.any.length ? null : out;
		}

		if ( c.not ) {
			var inner = strip( c.not, test );
			return inner ? { not: inner } : null;
		}

		var result = test( c );
		return 'drop' === result ? null : ( result || c );
	}

	function stripAll( test ) {
		eachCondition( function ( c ) {
			var out = strip( c, test );
			return out || { all: [] };
		} );
	}

	function countRefs( test ) {
		var n = 0;

		eachCondition( function ( c ) {
			( function walk( x ) {
				if ( ! x || 'object' !== typeof x ) {
					return;
				}

				( x.all || x.any || [] ).forEach( walk );

				if ( x.not ) {
					walk( x.not );
				}

				if ( test( x ) ) {
					n += 1;
				}
			}( c ) );

			return c;
		} );

		return n;
	}

	/* ------------------------------------------------------------------
	 * Field builders
	 * ---------------------------------------------------------------- */

	function textField( label, obj, key, opts ) {
		opts = opts || {};
		var id = nextId();
		var input = h( opts.multiline ? 'textarea' : 'input', {
			id: id,
			class: 'dak-sce-input',
			type: opts.multiline ? null : ( opts.type || 'text' ),
			rows: opts.multiline ? String( opts.rows || 3 ) : null,
			placeholder: opts.placeholder || null,
			value: undefined === obj[ key ] || null === obj[ key ] ? '' : String( obj[ key ] )
		} );

		input.addEventListener( 'input', function () {
			var v = input.value;

			if ( 'number' === opts.type ) {
				obj[ key ] = '' === v ? undefined : Number( v );

				if ( undefined === obj[ key ] ) {
					delete obj[ key ];
				}
			} else {
				obj[ key ] = v;
			}

			if ( opts.onInput ) {
				opts.onInput( v );
			}

			changed();
		} );

		return h( 'div', { class: 'dak-sce-field' + ( opts.narrow ? ' is-narrow' : '' ) }, [
			h( 'label', { for: id, text: label } ),
			opts.hint ? h( 'p', { class: 'dak-sce-hint', text: opts.hint } ) : null,
			input
		] );
	}

	function linesField( label, obj, key, hint ) {
		var id = nextId();
		var input = h( 'textarea', { id: id, class: 'dak-sce-input', rows: '3', value: ( obj[ key ] || [] ).join( '\n' ) } );

		input.addEventListener( 'input', function () {
			obj[ key ] = input.value.split( /\n|,/ ).map( function ( s ) {
				return s.trim();
			} ).filter( Boolean );
			changed();
		} );

		return h( 'div', { class: 'dak-sce-field' }, [
			h( 'label', { for: id, text: label } ),
			hint ? h( 'p', { class: 'dak-sce-hint', text: hint } ) : null,
			input
		] );
	}

	function checkField( label, obj, key, onChange, keepFalse ) {
		var id = nextId();
		var box = h( 'input', { type: 'checkbox', id: id, checked: !! obj[ key ] } );

		box.addEventListener( 'change', function () {
			if ( box.checked ) {
				obj[ key ] = true;
			} else if ( keepFalse ) {
				obj[ key ] = false;
			} else {
				delete obj[ key ];
			}

			changed();

			if ( onChange ) {
				onChange( box.checked );
			}
		} );

		return h( 'label', { class: 'dak-sce-check', for: id }, [ box, h( 'span', { text: label } ) ] );
	}

	function selectField( label, options, current, onChange, hint ) {
		var id = nextId();
		var select = h( 'select', { id: id, class: 'dak-sce-input' }, options.map( function ( o ) {
			return h( 'option', { value: o[ 0 ], text: o[ 1 ], selected: o[ 0 ] === current } );
		} ) );

		select.value = current || '';
		select.addEventListener( 'change', function () {
			onChange( select.value );
			changed();
		} );

		return h( 'div', { class: 'dak-sce-field' }, [
			h( 'label', { for: id, text: label } ),
			hint ? h( 'p', { class: 'dak-sce-hint', text: hint } ) : null,
			select
		] );
	}

	/** A group of checkboxes over [value, label] pairs, writing a list. */
	function checkGroup( legend, pairs, selected, onChange, hint ) {
		var set = ( selected || [] ).slice();
		var wrap = h( 'fieldset', { class: 'dak-sce-checkgroup' }, [ h( 'legend', { text: legend } ), hint ? h( 'p', { class: 'dak-sce-hint', text: hint } ) : null ] );
		var grid = h( 'div', { class: 'dak-sce-checkgrid' } );

		if ( ! pairs.length ) {
			grid.appendChild( h( 'p', { class: 'dak-sce-hint', text: 'Nothing to choose from yet.' } ) );
		}

		pairs.forEach( function ( p ) {
			var id = nextId();
			var box = h( 'input', { type: 'checkbox', id: id, value: p[ 0 ], checked: set.indexOf( p[ 0 ] ) !== -1 } );

			box.addEventListener( 'change', function () {
				var i = set.indexOf( p[ 0 ] );

				if ( box.checked && i === -1 ) {
					set.push( p[ 0 ] );
				} else if ( ! box.checked && i !== -1 ) {
					set.splice( i, 1 );
				}

				onChange( set.slice() );
				changed();
			} );

			grid.appendChild( h( 'label', { class: 'dak-sce-check is-chip', for: id }, [ box, h( 'span', { text: p[ 1 ] } ) ] ) );
		} );

		wrap.appendChild( grid );
		return wrap;
	}

	function specialtyPairs() {
		return Object.keys( SPECIALTIES ).map( function ( slug ) {
			return [ slug, SPECIALTIES[ slug ] ];
		} );
	}

	function symptomPairs() {
		return model.symptoms.map( function ( s ) {
			return [ s.id, s.label || '(unnamed symptom)' ];
		} );
	}

	function questionPairs( filter ) {
		return model.questions.filter( filter || function () {
			return true;
		} ).map( function ( q ) {
			return [ q.id, short( q.text || '(question without text)', 70 ) ];
		} );
	}

	/* ------------------------------------------------------------------
	 * "Show when…" builder
	 * ---------------------------------------------------------------- */

	function conditionBuilder( title, getCond, setCond, opts ) {
		opts = opts || {};
		var box = h( 'div', { class: 'dak-sce-cond' } );
		var parsed = parseCondition( getCond() );

		function save() {
			setCond( buildCondition( parsed ) );
			changed();
		}

		function draw() {
			box.textContent = '';
			box.appendChild( h( 'p', { class: 'dak-sce-cond-title', text: title } ) );

			if ( ! parsed ) {
				// Too complex for rows — editable as text for technical staff.
				var id = nextId();
				var raw = h( 'textarea', { id: id, class: 'dak-sce-input dak-sce-mono', rows: '4', value: JSON.stringify( getCond(), null, 1 ) } );
				var err = h( 'p', { class: 'dak-sce-error', hidden: true } );

				raw.addEventListener( 'change', function () {
					try {
						setCond( JSON.parse( raw.value ) );
						err.hidden = true;
						changed();
					} catch ( e ) {
						err.textContent = 'This isn\'t valid — the previous version is kept.';
						err.hidden = false;
					}
				} );

				box.appendChild( h( 'p', { class: 'dak-sce-hint', text: 'This condition is too complex for the simple editor. A technical person can change it here.' } ) );
				box.appendChild( h( 'label', { class: 'dak-sr-only', for: id, text: title } ) );
				box.appendChild( raw );
				box.appendChild( err );
				return;
			}

			if ( parsed.rows.length > 1 ) {
				box.appendChild( selectField( 'Combine', [ [ 'all', 'All of these must be true' ], [ 'any', 'Any one of these is enough' ] ], parsed.mode, function ( v ) {
					parsed.mode = v;
					save();
				} ) );
			}

			if ( ! parsed.rows.length ) {
				box.appendChild( h( 'p', { class: 'dak-sce-hint', text: opts.emptyText || 'No conditions yet — add one below.' } ) );
			}

			parsed.rows.forEach( function ( row, index ) {
				box.appendChild( rowEditor( row, index ) );
			} );

			box.appendChild( h( 'div', { class: 'dak-sce-row-actions' }, [
				h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sce-small', text: '+ Symptom condition', onclick: function () {
					parsed.rows.push( { kind: 'symptoms', ids: [], not: false } );
					save();
					draw();
				} } ),
				h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sce-small', text: '+ Answer condition', onclick: function () {
					parsed.rows.push( { kind: 'answer', q: '', op: 'in', values: [], not: false } );
					save();
					draw();
				} } )
			] ) );
		}

		function rowEditor( row, index ) {
			var el = h( 'div', { class: 'dak-sce-row' } );

			el.appendChild( h( 'div', { class: 'dak-sce-row-head' }, [
				h( 'strong', { text: ( 0 === index ? '' : ( 'any' === parsed.mode ? 'OR ' : 'AND ' ) ) + ( 'symptoms' === row.kind ? 'Patient has…' : 'Answer…' ) } ),
				h( 'button', { type: 'button', class: 'dak-sce-link is-danger', text: 'Remove condition', onclick: function () {
					parsed.rows.splice( index, 1 );
					save();
					draw();
				} } )
			] ) );

			if ( 'symptoms' === row.kind ) {
				el.appendChild( checkGroup( 'Any of these symptoms', symptomPairs(), row.ids, function ( list ) {
					row.ids = list;
					save();
				} ) );
			} else {
				var answerable = questionPairs( function ( q ) {
					return 'text' !== q.type;
				} );

				el.appendChild( selectField( 'Question', [ [ '', 'Choose a question…' ] ].concat( answerable ), row.q, function ( v ) {
					row.q = v;
					row.values = [];
					row.score = null;
					save();
					draw();
				} ) );

				var q = questionById( row.q );

				if ( q ) {
					var ranked = ( q.options || [] ).filter( function ( o ) {
						return 'number' === typeof o.score;
					} );
					var ops = [ [ 'in', 'is one of' ] ];

					if ( ranked.length ) {
						ops.push( [ 'gte', 'is at least' ], [ 'lte', 'is at most' ] );
					}

					if ( 'answered' === row.kind ) {
						row.kind = 'answer';
						row.op = 'in';
						row.values = [];
					}

					el.appendChild( selectField( 'Answer', ops, row.op, function ( v ) {
						row.op = v;
						row.values = [];
						row.score = null;
						save();
						draw();
					} ) );

					if ( 'in' === row.op ) {
						var pairs = ( q.options || [] ).map( function ( o ) {
							return [ o.value, o.label || o.value ];
						} );

						if ( q.allow_unsure ) {
							pairs.push( [ 'unsure', 'Not sure' ] );
						}

						el.appendChild( checkGroup( 'Answers', pairs, row.values, function ( list ) {
							row.values = list;
							save();
						} ) );
					} else {
						var current = ranked.filter( function ( o ) {
							return o.score === Number( row.score );
						} )[ 0 ];

						el.appendChild( selectField( 'Than', [ [ '', 'Choose an answer…' ] ].concat( ranked.map( function ( o ) {
							return [ String( o.score ), o.label ];
						} ) ), current ? String( current.score ) : '', function ( v ) {
							row.score = '' === v ? null : Number( v );
							save();
						}, 'Compares the answers\' rank: "at least" includes this answer and every higher-ranked one.' ) );
					}
				}
			}

			el.appendChild( checkField( 'Reverse: true when this is NOT the case', row, 'not', function () {
				save();
			} ) );

			return el;
		}

		draw();
		return box;
	}

	/* ------------------------------------------------------------------
	 * Lists of items
	 * ---------------------------------------------------------------- */

	function itemCard( listKey, index, summary, body ) {
		var list = model[ listKey ];
		var item = list[ index ];
		var key = listKey + ':' + ( item.id || index );
		var details = h( 'details', { class: 'dak-sce-item', open: !! openState[ key ] } );
		var sum = h( 'summary', {}, [ h( 'span', { class: 'dak-sce-item-title', text: summary() || '(not filled in yet)' } ) ] );

		function build() {
			if ( ! details.getAttribute( 'data-built' ) ) {
				details.setAttribute( 'data-built', '1' );
				details.appendChild( body( function () {
					sum.firstChild.textContent = summary() || '(not filled in yet)';
				} ) );
				details.appendChild( h( 'div', { class: 'dak-sce-item-actions' }, [
					h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sce-small', text: 'Move up', disabled: 0 === index, onclick: function () {
						move( listKey, index, -1 );
					} } ),
					h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sce-small', text: 'Move down', disabled: index === list.length - 1, onclick: function () {
						move( listKey, index, 1 );
					} } ),
					h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sce-small is-danger', text: 'Delete', onclick: function () {
						remove( listKey, index );
					} } )
				] ) );
			}
		}

		details.addEventListener( 'toggle', function () {
			openState[ key ] = details.open;

			if ( details.open ) {
				build();
			}
		} );

		details.appendChild( sum );

		// Items that were open before a redraw are built straight away.
		if ( openState[ key ] ) {
			build();
		}

		return details;
	}

	function move( listKey, index, delta ) {
		var list = model[ listKey ];
		var target = index + delta;

		if ( target < 0 || target >= list.length ) {
			return;
		}

		var tmp = list[ index ];
		list[ index ] = list[ target ];
		list[ target ] = tmp;
		changed();
		render();
	}

	function remove( listKey, index ) {
		var item = model[ listKey ][ index ];
		var refs = 0;
		var label = item.label || item.text || item.title || item.message || 'this item';

		if ( 'symptoms' === listKey ) {
			refs = countRefs( function ( c ) {
				return c.symptom === item.id;
			} ) + model.questions.filter( function ( q ) {
				return Array.isArray( q.applies_to ) && q.applies_to.indexOf( item.id ) !== -1;
			} ).length;
		} else if ( 'questions' === listKey ) {
			refs = countRefs( function ( c ) {
				return c.answer === item.id || c.answered === item.id;
			} ) + model.text_alerts.filter( function ( a ) {
				return a.question === item.id;
			} ).length;
		}

		// eslint-disable-next-line no-alert
		if ( ! window.confirm( 'Delete “' + short( label, 80 ) + '”?' + ( refs ? '\n\nIt is used in ' + refs + ' other rule' + ( 1 === refs ? '' : 's' ) + '. It will be removed from those too — please check them afterwards.' : '' ) ) ) {
			return;
		}

		model[ listKey ].splice( index, 1 );

		if ( 'symptoms' === listKey ) {
			stripAll( function ( c ) {
				return c.symptom === item.id ? 'drop' : null;
			} );
			model.questions.forEach( function ( q ) {
				if ( Array.isArray( q.applies_to ) ) {
					q.applies_to = q.applies_to.filter( function ( id ) {
						return id !== item.id;
					} );
				}
			} );
		} else if ( 'questions' === listKey ) {
			stripAll( function ( c ) {
				return c.answer === item.id || c.answered === item.id ? 'drop' : null;
			} );
			model.text_alerts = model.text_alerts.filter( function ( a ) {
				return a.question !== item.id;
			} );
		}

		changed();
		render();
	}

	function addItem( listKey, item ) {
		model[ listKey ].push( item );
		openState[ listKey + ':' + ( item.id || model[ listKey ].length - 1 ) ] = true;
		changed();
		render();
	}

	/* ------------------------------------------------------------------
	 * Sections
	 * ---------------------------------------------------------------- */

	function section( key, title, intro, count, build ) {
		var details = h( 'details', { class: 'dak-sce-section', open: !! openState[ 'section:' + key ] } );

		details.addEventListener( 'toggle', function () {
			openState[ 'section:' + key ] = details.open;
		} );

		details.appendChild( h( 'summary', {}, [
			h( 'span', { text: title } ),
			null === count ? null : h( 'span', { class: 'dak-sce-count', text: String( count ) } )
		] ) );
		details.appendChild( h( 'p', { class: 'dak-sce-hint', text: intro } ) );
		build( details );
		return details;
	}

	function addButton( text, onclick ) {
		return h( 'button', { type: 'button', class: 'dak-button dak-button-secondary dak-sce-add', text: text, onclick: onclick } );
	}

	function basicsSection() {
		return section( 'basics', 'Basics and emergency message', 'The name of this rule set, the urgent-help message and who to suggest when nothing else matches.', null, function ( el ) {
			el.appendChild( textField( 'Name of this rule set', model.meta, 'name', { placeholder: 'e.g. Gastro clinic rules — October 2026' } ) );

			var demo = h( 'div', { class: 'dak-sce-demo' + ( model.meta.is_demo ? ' is-on' : '' ) } );
			demo.appendChild( checkField( 'This is demonstration / test content (it can never be approved or shown to patients)', model.meta, 'is_demo', function ( on ) {
				demo.className = 'dak-sce-demo' + ( on ? ' is-on' : '' );
			} ) );
			demo.appendChild( h( 'p', { class: 'dak-sce-hint', text: 'Untick this only once a qualified clinician has reviewed every part of the rules.' } ) );
			el.appendChild( demo );

			el.appendChild( textField( 'Emergency message — heading', model.emergency, 'title' ) );
			el.appendChild( textField( 'Emergency message — text', model.emergency, 'message', { multiline: true, rows: 4 } ) );
			el.appendChild( textField( 'Emergency contact to show (optional)', model.emergency, 'contact_text', {
				hint: 'Only enter a phone number your clinical lead has verified. Leave empty to show no number.'
			} ) );
			el.appendChild( selectField( 'When nothing else matches, suggest', [ [ '', '— nobody —' ] ].concat( specialtyPairs() ), model.fallback_specialty || '', function ( v ) {
				if ( v ) {
					model.fallback_specialty = v;
				} else {
					delete model.fallback_specialty;
				}
			} ) );
		} );
	}

	function symptomsSection() {
		return section( 'symptoms', 'Symptoms', 'The symptoms patients can choose, the other words they might type for each one, and which kind of doctor each points to.', model.symptoms.length, function ( el ) {
			model.symptoms.forEach( function ( s, i ) {
				el.appendChild( itemCard( 'symptoms', i, function () {
					return s.label;
				}, function ( refresh ) {
					return h( 'div', { class: 'dak-sce-body' }, [
						textField( 'Symptom name (shown to patients)', s, 'label', { onInput: refresh } ),
						linesField( 'Other words patients might type', s, 'synonyms', 'One per line (or separated by commas). Include common misspellings and Urdu / Roman Urdu words, e.g. "pet dard".' ),
						checkGroup( 'Suggest these specialists', specialtyPairs(), s.specialties, function ( list ) {
							s.specialties = list;
						} )
					] );
				} ) );
			} );

			el.appendChild( addButton( '+ Add symptom', function () {
				addItem( 'symptoms', { id: makeId( 'new_symptom', ids( model.symptoms ), 'symptom' ), label: '', synonyms: [], specialties: [] } );
			} ) );
		} );
	}

	function optionsEditor( q ) {
		var wrap = h( 'div', { class: 'dak-sce-options' } );

		function draw() {
			wrap.textContent = '';
			wrap.appendChild( h( 'p', { class: 'dak-sce-subtitle', text: 'Answers patients can choose' } ) );
			wrap.appendChild( h( 'p', { class: 'dak-sce-hint', text: 'Rank is optional: give answers a number (1 = lowest) to use "at least / at most" in rules. For "How long" questions, "Up to (days)" lets the checker pre-select the answer when a patient writes e.g. "for 3 days".' } ) );

			q.options.forEach( function ( o, i ) {
				var row = h( 'div', { class: 'dak-sce-option' } );

				row.appendChild( textField( 'Answer ' + ( i + 1 ), o, 'label' ) );
				row.appendChild( textField( 'Rank', o, 'score', { type: 'number', narrow: true } ) );

				if ( 'duration' === q.type ) {
					row.appendChild( textField( 'Up to (days)', o, 'max_days', { type: 'number', narrow: true } ) );
				}

				var controls = h( 'div', { class: 'dak-sce-option-controls' } );

				if ( 'multi' === q.type ) {
					controls.appendChild( checkField( 'Clears the other choices (e.g. "None of these")', o, 'exclusive' ) );
				}

				controls.appendChild( h( 'button', { type: 'button', class: 'dak-sce-link is-danger', text: 'Remove answer', onclick: function () {
					var refs = countRefs( function ( c ) {
						return c.answer === q.id && ( c.equals === o.value || c.includes === o.value || ( Array.isArray( c[ 'in' ] ) && c[ 'in' ].indexOf( o.value ) !== -1 ) );
					} );

					// eslint-disable-next-line no-alert
					if ( refs && ! window.confirm( 'This answer is used in ' + refs + ' rule' + ( 1 === refs ? '' : 's' ) + '. Remove it from those too?' ) ) {
						return;
					}

					q.options.splice( i, 1 );
					stripAll( function ( c ) {
						if ( c.answer !== q.id ) {
							return null;
						}

						if ( c.equals === o.value || c.includes === o.value ) {
							return 'drop';
						}

						if ( Array.isArray( c[ 'in' ] ) && c[ 'in' ].indexOf( o.value ) !== -1 ) {
							var left = c[ 'in' ].filter( function ( v ) {
								return v !== o.value;
							} );

							return left.length ? { answer: c.answer, 'in': left } : 'drop';
						}

						return null;
					} );
					changed();
					render();
				} } ) );

				row.appendChild( controls );
				wrap.appendChild( row );
			} );

			wrap.appendChild( addButton( '+ Add answer', function () {
				q.options.push( { value: makeId( 'answer', q.options.map( function ( o ) {
					return o.value;
				} ), 'answer' ), label: '' } );
				changed();
				draw();
			} ) );
		}

		draw();
		return wrap;
	}

	function questionsSection() {
		return section( 'questions', 'Follow-up questions', 'The questions patients are asked after choosing symptoms. Questions with a lower order number are asked first.', model.questions.length, function ( el ) {
			model.questions.forEach( function ( q, i ) {
				el.appendChild( itemCard( 'questions', i, function () {
					return q.text;
				}, function ( refresh ) {
					var body = h( 'div', { class: 'dak-sce-body' } );
					var applies = Array.isArray( q.applies_to ) ? 'some' : 'all';

					body.appendChild( textField( 'Question', q, 'text', { onInput: refresh } ) );
					body.appendChild( textField( 'Extra explanation under the question (optional)', q, 'help' ) );
					body.appendChild( selectField( 'Type of answer', TYPES, q.type || 'single', function ( v ) {
						q.type = v;

						if ( 'text' === v ) {
							delete q.options;
						} else if ( ! Array.isArray( q.options ) ) {
							q.options = [];
						}

						render();
					} ) );
					body.appendChild( textField( 'Order', q, 'priority', { type: 'number', narrow: true, hint: 'Lower numbers are asked first (e.g. 0 for urgent screening questions).' } ) );

					var appliesBox = h( 'div' );

					function drawApplies() {
						appliesBox.textContent = '';
						appliesBox.appendChild( selectField( 'Ask this question for', [ [ 'all', 'Every patient' ], [ 'some', 'Only patients with certain symptoms' ] ], applies, function ( v ) {
							applies = v;
							q.applies_to = 'all' === v ? '*' : [];
							drawApplies();
						} ) );

						if ( 'some' === applies ) {
							appliesBox.appendChild( checkGroup( 'Symptoms', symptomPairs(), q.applies_to, function ( list ) {
								q.applies_to = list;
							} ) );
						}
					}

					drawApplies();
					body.appendChild( appliesBox );

					if ( 'text' === q.type ) {
						// Free-text questions are optional unless ticked.
						if ( undefined === q.required ) {
							q.required = false;
						}

						body.appendChild( checkField( 'Patients must answer (otherwise optional)', q, 'required', null, true ) );
					} else {
						body.appendChild( checkField( 'Offer a “Not sure” answer', q, 'allow_unsure' ) );
						body.appendChild( optionsEditor( q ) );
					}

					var condBox = h( 'div' );
					var limited = !! q.show_if;

					function drawCond() {
						condBox.textContent = '';
						var holder = { on: limited };

						condBox.appendChild( checkField( 'Only ask this in some cases (based on earlier answers)', holder, 'on', function ( on ) {
							limited = on;

							if ( on ) {
								q.show_if = { all: [] };
							} else {
								delete q.show_if;
							}

							drawCond();
						} ) );

						if ( limited ) {
							condBox.appendChild( conditionBuilder( 'Only ask when…', function () {
								return q.show_if;
							}, function ( c ) {
								q.show_if = c;
							} ) );
						}
					}

					drawCond();
					body.appendChild( condBox );
					return body;
				} ) );
			} );

			el.appendChild( addButton( '+ Add question', function () {
				addItem( 'questions', { id: makeId( 'question', ids( model.questions ), 'question' ), priority: 50, applies_to: '*', type: 'single', text: '', allow_unsure: true, options: [] } );
			} ) );
		} );
	}

	function alertsSection() {
		return section( 'alerts', 'Warning phrases in what patients type', 'If a patient\'s own description contains one of these phrases, the chosen question is asked straight away (usually the urgent-symptoms question).', model.text_alerts.length, function ( el ) {
			model.text_alerts.forEach( function ( a, i ) {
				el.appendChild( itemCard( 'text_alerts', i, function () {
					return ( a.phrases || [] ).slice( 0, 4 ).join( ', ' );
				}, function () {
					return h( 'div', { class: 'dak-sce-body' }, [
						linesField( 'Phrases', a, 'phrases', 'One per line, e.g. "vomited blood", "can\'t breathe".' ),
						selectField( 'Then ask', [ [ '', 'Choose a question…' ] ].concat( questionPairs() ), a.question || '', function ( v ) {
							a.question = v;
						} )
					] );
				} ) );
			} );

			el.appendChild( addButton( '+ Add warning phrases', function () {
				var first = model.questions[ 0 ];
				addItem( 'text_alerts', { phrases: [], question: first ? first.id : '' } );
			} ) );
		} );
	}

	function ruleSection( key, listKey, title, intro, fields, newItem ) {
		return section( key, title, intro, model[ listKey ].length, function ( el ) {
			model[ listKey ].forEach( function ( item, i ) {
				el.appendChild( itemCard( listKey, i, function () {
					return item.title || item.message;
				}, function ( refresh ) {
					return fields( item, refresh );
				} ) );
			} );

			el.appendChild( addButton( newItem.label, function () {
				var item = newItem.make();
				item.id = makeId( newItem.prefix, ids( model[ listKey ] ), newItem.prefix );
				addItem( listKey, item );
			} ) );
		} );
	}

	function whenBuilder( item, title, emptyText ) {
		return conditionBuilder( title, function () {
			return item.when;
		}, function ( c ) {
			item.when = c;
		}, { emptyText: emptyText } );
	}

	function render() {
		mount.textContent = '';

		if ( ! model ) {
			return;
		}

		mount.appendChild( basicsSection() );
		mount.appendChild( symptomsSection() );
		mount.appendChild( questionsSection() );
		mount.appendChild( alertsSection() );

		mount.appendChild( ruleSection( 'flags', 'red_flags', 'Warning signs (show the emergency message)', 'When any of these is true, the patient immediately sees the emergency message instead of more questions.', function ( item, refresh ) {
			return h( 'div', { class: 'dak-sce-body' }, [
				textField( 'Warning sign (shown to the patient as the reason)', item, 'title', { onInput: refresh } ),
				whenBuilder( item, 'Show the emergency message when…', 'Add at least one condition — a warning sign without conditions is not allowed.' )
			] );
		}, { label: '+ Add warning sign', prefix: 'warning', make: function () {
			return { title: '', when: { all: [] } };
		} } ) );

		mount.appendChild( ruleSection( 'steps', 'next_steps', 'Next steps (what to do)', 'Advice shown on the results page. The most urgent ones that apply are shown first. One must be the default, shown when nothing else applies.', function ( item, refresh ) {
			var body = h( 'div', { class: 'dak-sce-body' } );
			var condBox = h( 'div' );

			body.appendChild( textField( 'Heading', item, 'title', { onInput: refresh } ) );
			body.appendChild( textField( 'Advice', item, 'text', { multiline: true, rows: 3 } ) );
			body.appendChild( selectField( 'How urgent', URGENCIES, item.urgency || 'routine', function ( v ) {
				item.urgency = v;
			} ) );

			function drawCond() {
				condBox.textContent = '';

				if ( ! item.fallback ) {
					condBox.appendChild( whenBuilder( item, 'Show this advice when…', 'Add at least one condition (or tick "default" above).' ) );
				}
			}

			body.appendChild( checkField( 'Default — show this when no other next step applies', item, 'fallback', function ( on ) {
				if ( on ) {
					item.when = { all: [] };
				}

				drawCond();
			} ) );
			drawCond();
			body.appendChild( condBox );
			return body;
		}, { label: '+ Add next step', prefix: 'step', make: function () {
			return { urgency: 'routine', title: '', text: '', when: { all: [] } };
		} } ) );

		mount.appendChild( ruleSection( 'explanations', 'explanations', 'Possible explanations', 'Possible causes mentioned on the results page — always presented as possibilities, never as a diagnosis.', function ( item, refresh ) {
			return h( 'div', { class: 'dak-sce-body' }, [
				textField( 'Possible cause (heading)', item, 'title', { onInput: refresh } ),
				textField( 'Short explanation', item, 'summary', { multiline: true, rows: 3 } ),
				checkGroup( 'Suggest these specialists', specialtyPairs(), item.specialties, function ( list ) {
					item.specialties = list;
				} ),
				whenBuilder( item, 'Mention this when…', 'Add at least one condition.' )
			] );
		}, { label: '+ Add possible explanation', prefix: 'explanation', make: function () {
			return { title: '', summary: '', specialties: [], when: { all: [] } };
		} } ) );

		mount.appendChild( ruleSection( 'conflicts', 'conflicts', 'Answer checks', 'Answers that don\'t fit together. The patient is asked to check them before seeing results.', function ( item, refresh ) {
			return h( 'div', { class: 'dak-sce-body' }, [
				textField( 'Message to the patient', item, 'message', { multiline: true, rows: 3, onInput: refresh } ),
				whenBuilder( item, 'Show this message when…', 'Add at least one condition.' )
			] );
		}, { label: '+ Add answer check', prefix: 'check', make: function () {
			return { message: '', when: { all: [] } };
		} } ) );
	}

	/* ------------------------------------------------------------------
	 * Start, JSON sync, public API
	 * ---------------------------------------------------------------- */

	function loadFromJson( text, quiet ) {
		if ( '' === String( text ).trim() ) {
			model = blankRules();
			return true;
		}

		try {
			model = normalise( JSON.parse( text ) );
			return true;
		} catch ( e ) {
			if ( ! quiet ) {
				model = null;
			}

			return false;
		}
	}

	if ( loadFromJson( jsonBox.value ) ) {
		render();
	} else {
		mount.appendChild( h( 'p', { class: 'dak-sce-error', text: 'The saved draft can\'t be shown as a form because it isn\'t valid. A technical person can fix it under "Advanced: edit as JSON" below, or you can load the demonstration rules to start again.' } ) );

		if ( advanced ) {
			advanced.open = true;
		}
	}

	if ( advanced ) {
		advanced.addEventListener( 'toggle', function () {
			if ( advanced.open && model ) {
				jsonBox.value = JSON.stringify( model, null, '\t' );
			}
		} );
	}

	jsonBox.addEventListener( 'change', function () {
		if ( loadFromJson( jsonBox.value, true ) ) {
			if ( advancedNote ) {
				advancedNote.textContent = 'The form above has been updated from this JSON.';
			}

			render();
		} else if ( advancedNote ) {
			advancedNote.textContent = 'This JSON isn\'t valid, so the form wasn\'t updated. Saving will save this text as it is.';
		}
	} );

	window.DAKSymptomEditor = {
		toJSON: function () {
			// Invalid hand-edited JSON is saved as typed (the server reports the problem).
			if ( ! model || ( advanced && advanced.open && ! loadFromJson( jsonBox.value, true ) ) ) {
				return jsonBox.value;
			}

			return JSON.stringify( model, null, '\t' );
		}
	};
}() );
