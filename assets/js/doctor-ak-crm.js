/**
 * Doctor AK Portal — CRM screen (admin dashboard section and the doctor
 * dashboard's CRM tab).
 *
 * One table for leads and patients (saved views as tabs, filters, saved
 * segments, bulk actions, inline stage/owner), a side panel per person
 * (summary, details, possible duplicates, email, tasks, notes, timeline), a
 * Tasks list and — for clinic staff — an Emails tab (group emails and
 * templates) with the email composer shown in the side panel.
 *
 * Everything shown comes from Crm_Handler, which re-checks every action:
 * doctors only ever receive their own patients, and the buttons they
 * can't use simply aren't drawn.
 */
( function () {
	'use strict';

	var config = window.dakCrm;
	var root = document.querySelector( '[data-dak-crm]' );

	if ( ! config || ! root ) {
		return;
	}

	var staff = !! config.isStaff;
	var drawer = document.querySelector( '[data-crm-drawer]' );
	var panelBody = drawer.querySelector( '[data-crm-panel-body]' );
	var dialog = document.getElementById( 'dak-crm-dialog' );

	var state = {
		view: 'all',
		filters: { q: '', stage: '', source: '', doctor: '', owner: '', tag: '', date_from: '', date_to: '', last_visit_from: '', last_visit_to: '', sort: '' },
		page: 1,
		pages: 1,
		total: 0,
		rows: [],
		counts: {},
		selected: {},
		contact: null,
		taskWho: 'mine',
		taskUpcoming: false,
		returnFocus: null,
		segment: '',
		segments: ( config.email && config.email.segments ) || [],
		emailsTab: 'campaigns',
		templates: null,
		sending: {},
	};

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

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
			} else if ( 'value' === k ) {
				el.value = v;
			} else if ( 'checked' === k ) {
				el.checked = !! v;
			} else if ( 'selected' === k ) {
				el.selected = !! v;
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

	function btn( text, cls, onclick, extra ) {
		var attrs = { type: 'button', class: 'dak-button ' + ( cls || 'dak-button-secondary' ) + ' dak-button-sm', text: text, onclick: onclick };

		Object.keys( extra || {} ).forEach( function ( k ) {
			attrs[ k ] = extra[ k ];
		} );

		return h( 'button', attrs );
	}

	function select( options, value, attrs ) {
		var el = h( 'select', attrs || {} );

		options.forEach( function ( o ) {
			el.appendChild( h( 'option', { value: String( o.id ), text: o.label, selected: String( o.id ) === String( value ) } ) );
		} );

		return el;
	}

	function mapToOptions( map ) {
		return Object.keys( map ).map( function ( k ) {
			return { id: k, label: map[ k ] };
		} );
	}

	var MONTHS = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];

	function parse( value ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec( String( value || '' ) );

		return m ? new Date( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ], m[ 4 ] ? +m[ 4 ] : 0, m[ 5 ] ? +m[ 5 ] : 0 ) : null;
	}

	function fmtDate( value ) {
		var d = parse( value );

		return d ? d.getDate() + ' ' + MONTHS[ d.getMonth() ] + ' ' + d.getFullYear() : '';
	}

	function fmtDateTime( value ) {
		var d = parse( value );

		if ( ! d ) {
			return '';
		}

		var hr = d.getHours() % 12 || 12;

		return fmtDate( value ) + ', ' + hr + ':' + ( '0' + d.getMinutes() ).slice( -2 ) + ' ' + ( d.getHours() < 12 ? 'am' : 'pm' );
	}

	function relative( value ) {
		var d = parse( value );
		var today = parse( config.today );

		if ( ! d || ! today ) {
			return '';
		}

		var days = Math.round( ( new Date( today.getFullYear(), today.getMonth(), today.getDate() ) - new Date( d.getFullYear(), d.getMonth(), d.getDate() ) ) / 86400000 );

		if ( 0 === days ) {
			return 'Today';
		}

		if ( 1 === days ) {
			return 'Yesterday';
		}

		if ( days > 1 && days < 7 ) {
			return days + ' days ago';
		}

		return fmtDate( value );
	}

	function money( n ) {
		return 'PKR ' + Number( n || 0 ).toLocaleString( 'en-PK', { maximumFractionDigits: 0 } );
	}

	function addDays( ymd, n ) {
		var d = parse( ymd );
		d.setDate( d.getDate() + n );
		return d.getFullYear() + '-' + ( '0' + ( d.getMonth() + 1 ) ).slice( -2 ) + '-' + ( '0' + d.getDate() ).slice( -2 );
	}

	function staffName( id ) {
		var s = ( config.staff || [] ).filter( function ( o ) {
			return o.id === Number( id );
		} )[ 0 ];

		return s ? s.label : '';
	}

	var flashTimer = null;

	function flash( text, isError ) {
		var el = root.querySelector( '[data-crm-flash]' );

		clearTimeout( flashTimer );
		el.textContent = text || '';
		el.hidden = ! text;
		el.classList.toggle( 'is-error', !! isError );
		el.setAttribute( 'role', isError ? 'alert' : 'status' );

		if ( text && ! isError ) {
			flashTimer = setTimeout( function () {
				flash( '' );
			}, 6000 );
		}
	}

	function post( op, fields, button ) {
		var body = new FormData();
		body.append( 'action', 'doctor_ak_crm' );
		body.append( 'nonce', config.nonce );
		body.append( 'op', op );

		Object.keys( fields || {} ).forEach( function ( k ) {
			var v = fields[ k ];

			if ( Array.isArray( v ) ) {
				if ( ! v.length ) {
					body.append( k + '[]', '' );
				}

				v.forEach( function ( item ) {
					body.append( k + '[]', item );
				} );
			} else if ( null !== v && undefined !== v ) {
				body.append( k, v );
			}
		} );

		if ( button ) {
			button.disabled = true;
		}

		return fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.catch( function () {
				return { success: false, data: { message: 'Something went wrong. Please check your connection and try again.' } };
			} )
			.then( function ( res ) {
				if ( button ) {
					button.disabled = false;
				}

				res = res || { success: false, data: {} };

				if ( ! res.success ) {
					flash( ( res.data && res.data.message ) || 'Something went wrong.', true );
				}

				return res;
			} );
	}

	function stagePill( stage ) {
		return h( 'span', { class: 'dak-crm-stage is-' + stage, text: config.stages[ stage ] || stage } );
	}

	function tagChips( tags ) {
		return h( 'span', { class: 'dak-crm-tags' }, ( tags || [] ).map( function ( t ) {
			return h( 'span', { class: 'dak-crm-tag', text: t } );
		} ) );
	}

	/* ------------------------------------------------------------------
	 * First run: import existing patients, bookings and requests
	 * ---------------------------------------------------------------- */

	function importExisting( done ) {
		var box = root.querySelector( '[data-crm-import]' );

		box.hidden = false;
		box.textContent = 'Setting up the CRM: bringing in existing patients, bookings and service requests…';

		( function step() {
			post( 'sync', {} ).then( function ( res ) {
				if ( ! res.success ) {
					box.textContent = 'Couldn’t finish bringing in existing records. Reload the page to continue where it stopped.';
					return;
				}

				var labels = { patients: 'patients', appointments: 'bookings', requests: 'service requests' };

				if ( res.data.done ) {
					box.hidden = true;
					config.imported = true;
					done();
				} else {
					box.textContent = 'Setting up the CRM: ' + res.data.imported + ' records brought in so far (now: ' + ( labels[ res.data.phase ] || res.data.phase ) + ')…';
					step();
				}
			} );
		}() );
	}

	/* ------------------------------------------------------------------
	 * Head actions, views, filters
	 * ---------------------------------------------------------------- */

	function renderHeadActions() {
		var box = root.querySelector( '[data-crm-head-actions]' );

		box.textContent = '';

		if ( ! staff ) {
			return;
		}

		box.appendChild( btn( 'Export CSV', 'dak-button-secondary', exportCsv ) );
		box.appendChild( btn( '+ Add contact', 'dak-button-primary', openAddDialog ) );
	}

	function renderViews() {
		var box = root.querySelector( '[data-crm-views]' );

		box.textContent = '';

		Object.keys( config.views ).forEach( function ( key ) {
			var on = key === state.view;
			var n = state.counts[ key ];

			// Payment / no-show views use booking money, which doctors don't see.
			if ( ! staff && ( 'pending_payment' === key || 'no_show' === key ) ) {
				return;
			}

			box.appendChild( h( 'button', {
				type: 'button',
				role: 'tab',
				class: 'dak-crm-view' + ( on ? ' is-active' : '' ),
				'aria-selected': on ? 'true' : 'false',
				onclick: function () {
					state.view = key;
					state.page = 1;
					state.selected = {};

					if ( state.segment ) {
						state.segment = '';
						renderFilters();
					}

					load();
				},
			}, [ config.views[ key ], undefined === n ? null : h( 'span', { class: 'dak-crm-count', text: String( n ) } ) ] ) );
		} );
	}

	function renderFilters() {
		var form = root.querySelector( '[data-crm-filters]' );
		var timer = null;

		form.textContent = '';
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
		} );

		function field( label, control ) {
			return h( 'label', { class: 'dak-crm-filter' }, [ h( 'span', { text: label } ), control ] );
		}

		var segmentSelect = null;

		function changed( key, value ) {
			state.filters[ key ] = value;
			state.page = 1;
			state.selected = {};

			// Edited filters are no longer the saved segment.
			if ( segmentSelect ) {
				state.segment = '';
				segmentSelect.value = '';
			}

			load();
		}

		if ( staff ) {
			segmentSelect = select( [ { id: '', label: state.segments.length ? 'None' : 'None saved yet' } ].concat( state.segments.map( function ( s ) {
				return { id: s.id, label: s.name };
			} ) ), state.segment, { class: 'dak-crm-input' } );
			segmentSelect.addEventListener( 'change', function () {
				var seg = state.segments.filter( function ( s ) {
					return s.id === segmentSelect.value;
				} )[ 0 ];

				Object.keys( state.filters ).forEach( function ( k ) {
					state.filters[ k ] = seg && seg.filters[ k ] ? seg.filters[ k ] : '';
				} );
				state.segment = seg ? seg.id : '';
				state.view = seg ? seg.view : 'all';
				state.page = 1;
				state.selected = {};
				renderFilters();
				load();
			} );
			form.appendChild( field( 'Saved segment', segmentSelect ) );
		}

		var search = h( 'input', { type: 'search', class: 'dak-crm-input', placeholder: 'Name, phone or email', value: state.filters.q } );
		search.addEventListener( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( function () {
				changed( 'q', search.value.trim() );
			}, 300 );
		} );
		form.appendChild( field( 'Search', search ) );

		var add = function ( key, label, options ) {
			var s = select( [ { id: '', label: 'Any' } ].concat( options ), state.filters[ key ], { class: 'dak-crm-input' } );
			s.addEventListener( 'change', function () {
				changed( key, s.value );
			} );
			form.appendChild( field( label, s ) );
		};

		add( 'stage', 'Stage', mapToOptions( config.stages ) );
		add( 'source', 'Source', mapToOptions( config.sources ) );

		if ( staff ) {
			add( 'doctor', 'Doctor', config.doctors );
			add( 'owner', 'Owner', [ { id: '0', label: 'Unassigned' } ].concat( config.staff ) );
		}

		if ( ( config.tags || [] ).length ) {
			add( 'tag', 'Tag', config.tags.map( function ( t ) {
				return { id: t, label: t };
			} ) );
		}

		[ [ 'date_from', 'Added from' ], [ 'date_to', 'Added to' ], [ 'last_visit_from', 'Last visit from' ], [ 'last_visit_to', 'Last visit to' ] ].forEach( function ( pair ) {
			var d = h( 'input', { type: 'date', class: 'dak-crm-input', value: state.filters[ pair[ 0 ] ] } );
			d.addEventListener( 'change', function () {
				changed( pair[ 0 ], d.value );
			} );
			form.appendChild( field( pair[ 1 ], d ) );
		} );

		var sort = select( [
			{ id: '', label: 'Default' },
			{ id: 'activity', label: 'Latest activity' },
			{ id: 'follow_up', label: 'Follow-up date' },
			{ id: 'name', label: 'Name' },
			{ id: 'created', label: 'Newest added' },
		], state.filters.sort, { class: 'dak-crm-input' } );
		sort.addEventListener( 'change', function () {
			changed( 'sort', sort.value );
		} );
		form.appendChild( field( 'Sort by', sort ) );

		var actions = h( 'div', { class: 'dak-crm-filter is-action' }, [ btn( 'Clear filters', 'dak-button-secondary', function () {
			Object.keys( state.filters ).forEach( function ( k ) {
				state.filters[ k ] = '';
			} );
			state.segment = '';
			state.page = 1;
			renderFilters();
			load();
		} ) ] );

		if ( staff ) {
			actions.appendChild( btn( 'Save as segment', 'dak-button-secondary', saveSegment ) );

			if ( state.segment ) {
				actions.appendChild( btn( 'Delete segment', 'dak-button-secondary', deleteSegment ) );
			}
		}

		form.appendChild( actions );
	}

	/** Saves the current view + filters under a name (staff). */
	function saveSegment( e ) {
		var name = window.prompt( 'Name this segment — the current view and filters are saved together (e.g. “Diabetic patients, no visit in 6 months”):', '' ); // eslint-disable-line no-alert

		if ( null === name || ! name.trim() ) {
			return;
		}

		var fields = { name: name.trim(), view: state.view };

		Object.keys( state.filters ).forEach( function ( k ) {
			fields[ k ] = state.filters[ k ];
		} );

		post( 'segment_save', fields, e.currentTarget ).then( function ( res ) {
			if ( res.success ) {
				state.segments = res.data.segments;
				state.segment = state.segments[ state.segments.length - 1 ].id;
				flash( res.data.message );
				renderFilters();
			}
		} );
	}

	function deleteSegment( e ) {
		var seg = state.segments.filter( function ( s ) {
			return s.id === state.segment;
		} )[ 0 ];

		if ( ! seg || ! window.confirm( 'Delete the saved segment “' + seg.name + '”? The people in it aren’t affected.' ) ) { // eslint-disable-line no-alert
			return;
		}

		post( 'segment_delete', { segment_id: seg.id }, e.currentTarget ).then( function ( res ) {
			if ( res.success ) {
				state.segments = res.data.segments;
				state.segment = '';
				flash( res.data.message );
				renderFilters();
			}
		} );
	}

	/** "everyone in “Inactive patients” · Tag: Diabetic" — the current list, in words. */
	function audienceLabel() {
		var parts = [ '“' + config.views[ state.view ] + '”' ];
		var f = state.filters;
		var seg = state.segments.filter( function ( s ) {
			return s.id === state.segment;
		} )[ 0 ];

		if ( seg ) {
			return 'Segment “' + seg.name + '”';
		}

		if ( f.q ) {
			parts.push( 'search “' + f.q + '”' );
		}

		if ( f.stage ) {
			parts.push( 'stage ' + config.stages[ f.stage ] );
		}

		if ( f.source ) {
			parts.push( 'source ' + config.sources[ f.source ] );
		}

		if ( f.doctor ) {
			var d = ( config.doctors || [] ).filter( function ( o ) {
				return String( o.id ) === String( f.doctor );
			} )[ 0 ];
			parts.push( d ? d.label : 'a doctor' );
		}

		if ( '' !== f.owner ) {
			parts.push( 'owner ' + ( staffName( f.owner ) || 'unassigned' ) );
		}

		if ( f.tag ) {
			parts.push( 'tag ' + f.tag );
		}

		if ( f.date_from || f.date_to ) {
			parts.push( 'added ' + [ f.date_from ? 'from ' + fmtDate( f.date_from ) : '', f.date_to ? 'to ' + fmtDate( f.date_to ) : '' ].filter( Boolean ).join( ' ' ) );
		}

		if ( f.last_visit_from || f.last_visit_to ) {
			parts.push( 'last visit ' + [ f.last_visit_from ? 'from ' + fmtDate( f.last_visit_from ) : '', f.last_visit_to ? 'to ' + fmtDate( f.last_visit_to ) : '' ].filter( Boolean ).join( ' ' ) );
		}

		return parts.join( ' · ' );
	}

	/* ------------------------------------------------------------------
	 * Table
	 * ---------------------------------------------------------------- */

	function load() {
		var table = root.querySelector( '[data-crm-table]' );

		table.setAttribute( 'aria-busy', 'true' );

		var fields = { view: state.view, page: state.page };

		Object.keys( state.filters ).forEach( function ( k ) {
			fields[ k ] = state.filters[ k ];
		} );

		return post( 'list', fields ).then( function ( res ) {
			table.removeAttribute( 'aria-busy' );

			if ( ! res.success ) {
				return;
			}

			state.rows = res.data.rows;
			state.counts = res.data.counts;
			state.pages = res.data.pages;
			state.page = res.data.page;
			state.total = res.data.total;
			renderViews();
			renderTable();
			renderBulk();
			renderPager();

			root.querySelector( '[data-crm-summary]' ).textContent = res.data.total + ' ' + ( 1 === res.data.total ? 'person' : 'people' ) + ' in “' + config.views[ state.view ] + '”.';

			var actions = root.querySelector( '[data-crm-summary-actions]' );
			actions.textContent = '';

			if ( staff && res.data.total ) {
				actions.appendChild( btn( 1 === res.data.total ? 'Email this person' : 'Email these ' + res.data.total + ' people', 'dak-button-secondary', function ( e ) {
					openComposer( { mode: 'group', audience: { type: 'filter', label: audienceLabel(), count: state.total } }, e.currentTarget );
				} ) );
			}
		} );
	}

	/** The current view and filters, as request fields. */
	function listFields() {
		var fields = { view: state.view };

		Object.keys( state.filters ).forEach( function ( k ) {
			fields[ k ] = state.filters[ k ];
		} );

		return fields;
	}

	function emptyText() {
		var texts = {
			all: 'No one here yet. People appear automatically when they book, request a service or register.',
			new: 'No new leads waiting.',
			followups: 'No follow-ups due today. Nice work.',
			pending_payment: 'No unpaid online bookings.',
			no_show: 'No possible no-shows.',
			inactive: 'No inactive patients.',
		};

		return texts[ state.view ] || texts.all;
	}

	function renderTable() {
		var box = root.querySelector( '[data-crm-table]' );

		box.textContent = '';

		if ( ! state.rows.length ) {
			box.appendChild( h( 'div', { class: 'dak-crm-empty', text: emptyText() } ) );
			return;
		}

		var allOn = state.rows.every( function ( r ) {
			return state.selected[ r.id ];
		} );
		var head = [];

		if ( staff ) {
			var all = h( 'input', { type: 'checkbox', checked: allOn, 'aria-label': 'Select everyone on this page' } );
			all.addEventListener( 'change', function () {
				state.rows.forEach( function ( r ) {
					if ( all.checked ) {
						state.selected[ r.id ] = true;
					} else {
						delete state.selected[ r.id ];
					}
				} );
				renderTable();
				renderBulk();
			} );
			head.push( h( 'th', { scope: 'col', class: 'dak-crm-col-check' }, [ all ] ) );
		}

		[ 'Name', 'Source', 'Stage', 'Doctor / service', 'Last activity', 'Next follow-up', 'Owner', 'Tags' ].forEach( function ( t ) {
			head.push( h( 'th', { scope: 'col', text: t } ) );
		} );

		var tbody = h( 'tbody' );

		state.rows.forEach( function ( r ) {
			var cells = [];

			if ( staff ) {
				var check = h( 'input', { type: 'checkbox', checked: !! state.selected[ r.id ], 'aria-label': 'Select ' + r.name } );
				check.addEventListener( 'change', function () {
					if ( check.checked ) {
						state.selected[ r.id ] = true;
					} else {
						delete state.selected[ r.id ];
					}
					renderBulk();
				} );
				cells.push( h( 'td', { class: 'dak-crm-col-check dak-col-select', 'data-label': 'Select' }, [ check ] ) );
			}

			cells.push( h( 'td', { 'data-label': 'Name', class: 'dak-crm-col-name dak-col-primary' }, [
				h( 'button', { type: 'button', class: 'dak-crm-name', text: r.name, 'aria-label': 'Open ' + r.name, onclick: function ( e ) {
					openPanel( r.id, e.currentTarget );
				} } ),
				h( 'span', { class: 'dak-cell-sub', text: [ r.phone, r.email ].filter( Boolean ).join( ' · ' ) } ),
				r.patientNumber ? h( 'span', { class: 'dak-crm-badge', text: r.patientNumber } ) : null,
			] ) );

			cells.push( h( 'td', { 'data-label': 'Source', text: config.sources[ r.source ] || r.source } ) );

			if ( staff ) {
				var st = select( mapToOptions( config.stages ), r.stage, { class: 'dak-crm-inline is-' + r.stage, 'aria-label': 'Stage for ' + r.name } );
				st.addEventListener( 'change', function () {
					setField( r.id, { stage: st.value }, st, r.stage );
				} );
				cells.push( h( 'td', { 'data-label': 'Stage' }, [ st ] ) );
			} else {
				cells.push( h( 'td', { 'data-label': 'Stage' }, [ stagePill( r.stage ) ] ) );
			}

			cells.push( h( 'td', { 'data-label': 'Doctor / service' }, [
				h( 'span', { text: r.doctors.join( ', ' ) || '—' } ),
				r.services.length ? h( 'span', { class: 'dak-cell-sub', text: r.services.join( ', ' ) } ) : null,
				r.flags.indexOf( 'pending_payment' ) !== -1 ? h( 'span', { class: 'dak-crm-flag is-amber', text: 'Unpaid online booking' } ) : null,
				r.flags.indexOf( 'no_show' ) !== -1 ? h( 'span', { class: 'dak-crm-flag', text: 'Possible no-show' } ) : null,
			] ) );

			cells.push( h( 'td', { 'data-label': 'Last activity' }, [
				h( 'span', { text: r.lastActivity } ),
				h( 'span', { class: 'dak-cell-sub', text: relative( r.lastActivityAt ) } ),
			] ) );

			var overdue = r.followUp && r.followUp <= config.today;
			cells.push( h( 'td', { 'data-label': 'Next follow-up' }, [
				r.followUp ? h( 'span', { class: 'dak-crm-due' + ( overdue ? ' is-due' : '' ), text: ( r.followUp < config.today ? 'Overdue · ' : ( r.followUp === config.today ? 'Today · ' : '' ) ) + fmtDate( r.followUp ) } ) : h( 'span', { class: 'dak-cell-sub', text: '—' } ),
			] ) );

			if ( staff ) {
				var ow = select( [ { id: 0, label: 'Unassigned' } ].concat( config.staff ), r.ownerId, { class: 'dak-crm-inline', 'aria-label': 'Owner for ' + r.name } );
				ow.addEventListener( 'change', function () {
					setField( r.id, { owner_id: ow.value }, ow, r.ownerId );
				} );
				cells.push( h( 'td', { 'data-label': 'Owner' }, [ ow ] ) );
			} else {
				cells.push( h( 'td', { 'data-label': 'Owner', text: r.ownerName || '—' } ) );
			}

			cells.push( h( 'td', { 'data-label': 'Tags' }, [ r.tags.length ? tagChips( r.tags ) : h( 'span', { class: 'dak-cell-sub', text: '—' } ) ] ) );

			tbody.appendChild( h( 'tr', { 'data-row': '', id: 'dak-crm-row-' + r.id }, cells ) );
		} );

		box.appendChild( h( 'table', { class: 'dak-data-table dak-crm-table' }, [ h( 'thead', {}, [ h( 'tr', {}, head ) ] ), tbody ] ) );
	}

	/** Inline change from the table (stage / owner). */
	function setField( id, fields, control, previous ) {
		if ( 'lost' === fields.stage ) {
			var reason = window.prompt( 'Why was this lead lost? (optional, e.g. “Went to another clinic”)', '' ); // eslint-disable-line no-alert

			if ( null === reason ) {
				control.value = previous;
				return;
			}

			fields.lost_reason = reason;
		}

		fields.id = id;
		post( 'update', fields, control ).then( function ( res ) {
			if ( res.success ) {
				flash( 'Saved.' );
				load();
			} else {
				control.value = previous;
			}
		} );
	}

	function renderPager() {
		var nav = root.querySelector( '[data-crm-pager]' );

		nav.textContent = '';

		if ( state.pages <= 1 ) {
			return;
		}

		nav.appendChild( btn( 'Previous', 'dak-button-secondary', function () {
			state.page -= 1;
			load();
		}, { disabled: state.page <= 1 } ) );
		nav.appendChild( h( 'span', { text: 'Page ' + state.page + ' of ' + state.pages } ) );
		nav.appendChild( btn( 'Next', 'dak-button-secondary', function () {
			state.page += 1;
			load();
		}, { disabled: state.page >= state.pages } ) );
	}

	/* ------------------------------------------------------------------
	 * Bulk actions (staff)
	 * ---------------------------------------------------------------- */

	function renderBulk() {
		var box = root.querySelector( '[data-crm-bulk]' );
		var ids = Object.keys( state.selected ).map( Number );

		box.textContent = '';
		box.hidden = ! staff || ! ids.length;

		if ( box.hidden ) {
			return;
		}

		function run( action, value, button ) {
			post( 'bulk', { bulk_action: action, value: value, ids: ids }, button ).then( function ( res ) {
				if ( res.success ) {
					flash( res.data.message + ( res.data.failed.length ? ' Not changed: ' + res.data.failed.join( ' ' ) : '' ), !! res.data.failed.length );
					state.selected = {};
					load();
				}
			} );
		}

		var stage = select( [ { id: '', label: 'Change stage…' } ].concat( mapToOptions( config.stages ) ), '', { class: 'dak-crm-input', 'aria-label': 'Change stage of selected' } );
		stage.addEventListener( 'change', function () {
			if ( stage.value ) {
				run( 'stage', stage.value, stage );
			}
		} );

		var owner = select( [ { id: '', label: 'Assign owner…' }, { id: 0, label: 'Unassigned' } ].concat( config.staff ), '', { class: 'dak-crm-input', 'aria-label': 'Assign owner to selected' } );
		owner.addEventListener( 'change', function () {
			if ( '' !== owner.value ) {
				run( 'owner', owner.value, owner );
			}
		} );

		var tag = h( 'input', { type: 'text', class: 'dak-crm-input', placeholder: 'Tag', list: 'dak-crm-tag-list', 'aria-label': 'Tag for selected' } );
		var date = h( 'input', { type: 'date', class: 'dak-crm-input', 'aria-label': 'Follow-up date for selected' } );

		box.appendChild( h( 'strong', { text: ids.length + ' selected' } ) );
		box.appendChild( stage );
		box.appendChild( owner );
		box.appendChild( h( 'span', { class: 'dak-crm-bulk-group' }, [
			tag,
			btn( 'Add tag', 'dak-button-secondary', function ( e ) {
				if ( tag.value.trim() ) {
					run( 'tag_add', tag.value.trim(), e.currentTarget );
				} else {
					tag.focus();
				}
			} ),
			btn( 'Remove tag', 'dak-button-secondary', function ( e ) {
				if ( tag.value.trim() ) {
					run( 'tag_remove', tag.value.trim(), e.currentTarget );
				} else {
					tag.focus();
				}
			} ),
		] ) );
		box.appendChild( h( 'span', { class: 'dak-crm-bulk-group' }, [
			date,
			btn( 'Set follow-up', 'dak-button-secondary', function ( e ) {
				if ( date.value ) {
					run( 'follow_up', date.value, e.currentTarget );
				} else {
					date.focus();
				}
			} ),
		] ) );
		box.appendChild( btn( 'Email selected', 'dak-button-secondary', function ( e ) {
			openComposer( { mode: 'group', audience: { type: 'ids', ids: ids, label: ids.length + ' selected ' + ( 1 === ids.length ? 'person' : 'people' ), count: ids.length } }, e.currentTarget );
		} ) );
		box.appendChild( btn( 'Clear selection', 'dak-button-secondary', function () {
			state.selected = {};
			renderTable();
			renderBulk();
		} ) );
		box.appendChild( tagDatalist() );
	}

	function tagDatalist() {
		return h( 'datalist', { id: 'dak-crm-tag-list' }, ( config.tags || [] ).map( function ( t ) {
			return h( 'option', { value: t } );
		} ) );
	}

	/* ------------------------------------------------------------------
	 * Side panel
	 * ---------------------------------------------------------------- */

	/**
	 * Opens the side panel empty (or clears it when it's already open —
	 * focus then still returns to what opened it first).
	 */
	function showDrawer( trigger ) {
		if ( drawer.hidden ) {
			state.returnFocus = trigger || document.activeElement;
		}

		drawer.hidden = false;
		drawer.querySelector( '.dak-crm-panel' ).classList.remove( 'is-wide' );
		document.body.classList.add( 'dak-modal-open' );
		state.contact = null;
		panelBody.textContent = '';
	}

	/** Moves focus to the panel's heading (after drawing a view). */
	function focusTitle() {
		var title = document.getElementById( 'dak-crm-panel-title' );

		if ( title ) {
			title.setAttribute( 'tabindex', '-1' );
			title.focus();
		}
	}

	function openPanel( id, trigger ) {
		showDrawer( trigger );
		panelBody.appendChild( h( 'p', { class: 'dak-crm-loading', role: 'status', text: 'Loading…' } ) );
		drawer.querySelector( '.dak-crm-panel' ).focus();

		post( 'contact', { id: id } ).then( function ( res ) {
			if ( res.success ) {
				renderPanel( res.data );
			} else {
				closePanel();
			}
		} );
	}

	function closePanel() {
		drawer.hidden = true;
		document.body.classList.remove( 'dak-modal-open' );
		state.contact = null;

		if ( state.returnFocus && document.body.contains( state.returnFocus ) ) {
			state.returnFocus.focus();
		}
	}

	drawer.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-crm-close]' ) ) {
			closePanel();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! dialog.classList.contains( 'is-open' ) && ! drawer.hidden ) {
			closePanel();
		}

		// Keep Tab inside whichever overlay is open.
		if ( 'Tab' === e.key ) {
			var box = dialog.classList.contains( 'is-open' ) ? dialog.querySelector( '[role="dialog"]' ) : ( drawer.hidden ? null : drawer.querySelector( '.dak-crm-panel' ) );

			if ( ! box ) {
				return;
			}

			var focusable = Array.prototype.filter.call( box.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' ), function ( el ) {
				return el.offsetParent !== null;
			} );

			if ( ! focusable.length ) {
				return;
			}

			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];

			if ( e.shiftKey && ( document.activeElement === first || ! box.contains( document.activeElement ) ) ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	} );

	function section( title, kids ) {
		return h( 'section', { class: 'dak-crm-section' }, [ h( 'h3', { text: title } ) ].concat( kids ) );
	}

	/** After a change in the panel: redraw it and the table behind it. */
	function updated( res ) {
		if ( res.success ) {
			flash( res.data.message || 'Saved.' );
			renderPanel( res.data.contact );
			load();
		}
	}

	function renderPanel( c ) {
		state.contact = c;
		panelBody.textContent = '';

		// Header.
		var contactLine = [];

		if ( c.phone ) {
			contactLine.push( h( 'a', { href: 'tel:' + c.phone.replace( /[^0-9+]/g, '' ), text: c.phone } ) );
		}

		if ( c.email ) {
			contactLine.push( h( 'a', { href: 'mailto:' + c.email, text: c.email } ) );
		}

		panelBody.appendChild( h( 'header', { class: 'dak-crm-panel-head' }, [
			h( 'h2', { id: 'dak-crm-panel-title', text: c.name } ),
			h( 'p', { class: 'dak-crm-panel-meta' }, [
				stagePill( c.stage ),
				h( 'span', { class: 'dak-crm-badge', text: c.hasAccount ? 'Patient account ' + c.patientNumber : 'No account' } ),
				h( 'span', { class: 'dak-cell-sub', text: 'Added ' + fmtDate( c.createdAt ) + ' · ' + ( config.sources[ c.source ] || c.source ) } ),
			] ),
			contactLine.length ? h( 'p', { class: 'dak-crm-panel-contact' }, contactLine ) : null,
			c.lostReason && 'lost' === c.stage ? h( 'p', { class: 'dak-cell-sub', text: 'Lost: ' + c.lostReason } ) : null,
		] ) );

		// At a glance (staff).
		if ( staff && c.summary ) {
			var s = c.summary;
			var stats = [
				[ 'Visits', String( s.visits || 0 ) ],
				[ 'Last visit', s.last_visit ? fmtDate( s.last_visit ) : '—' ],
				[ 'Next appointment', s.next_appointment ? fmtDateTime( s.next_appointment ) : '—' ],
				[ 'Paid', money( s.total_paid ) ],
				[ 'Unpaid bookings', s.unpaid ? money( s.unpaid ) : '—' ],
				[ 'Possible no-shows', String( s.possible_no_shows || 0 ) ],
			];

			panelBody.appendChild( h( 'dl', { class: 'dak-crm-stats' }, stats.map( function ( pair ) {
				return h( 'div', {}, [ h( 'dt', { text: pair[ 0 ] } ), h( 'dd', { text: pair[ 1 ] } ) ] );
			} ) ) );
		}

		panelBody.appendChild( detailsForm( c ) );

		if ( staff && c.duplicates && c.duplicates.length ) {
			panelBody.appendChild( section( 'Possible duplicates', [
				h( 'p', { class: 'dak-crm-help', text: 'Same phone or email. Families often share a phone, so only merge when it’s the same person.' } ),
				h( 'ul', { class: 'dak-crm-list' }, c.duplicates.map( function ( d ) {
					return h( 'li', {}, [
						h( 'span', {}, [ h( 'strong', { text: d.name } ), ' ', h( 'span', { class: 'dak-cell-sub', text: [ d.phone, d.email, config.stages[ d.stage ], d.hasAccount ? 'patient account' : '' ].filter( Boolean ).join( ' · ' ) } ) ] ),
						btn( 'Merge into this record', 'dak-button-secondary', function ( e ) {
							if ( window.confirm( 'Merge “' + d.name + '” into “' + c.name + '”? Their bookings, notes and tasks move here and the other record is removed. This can’t be undone.' ) ) { // eslint-disable-line no-alert
								post( 'merge', { id: c.id, source_id: d.id }, e.currentTarget ).then( updated );
							}
						}, { 'aria-label': 'Merge ' + d.name + ' into this record' } ),
					] );
				} ) ),
			] ) );
		}

		if ( staff ) {
			panelBody.appendChild( emailSection( c ) );
		}

		panelBody.appendChild( tasksSection( c ) );
		panelBody.appendChild( notesSection( c ) );
		panelBody.appendChild( timelineSection( c ) );
		focusTitle();
	}

	/** Email: offers & news status, and writing one email (staff). */
	function emailSection( c ) {
		var kids = [];
		var m = c.marketing || {};

		if ( ! c.hasEmail ) {
			kids.push( h( 'p', { class: 'dak-crm-help', text: c.hasAccount ? 'No email address on their account.' : 'No email address yet — add one under Details to email them.' } ) );
			return section( 'Email', kids );
		}

		var status;

		if ( m.allowed ) {
			status = 'Gets offers & news emails.';
		} else if ( m.optedOutAt ) {
			status = 'Unsubscribed from offers & news on ' + fmtDate( m.optedOutAt ) + ( 'link' === m.via ? ' (using the link in an email).' : ' (marked by staff).' );
		} else if ( m.accountOff ) {
			status = 'Turned off announcement emails in their account settings, so they don’t get offers & news.';
		} else {
			status = 'Doesn’t get offers & news: someone else on file with the same email address unsubscribed.';
		}

		var toggle = null;

		if ( m.allowed ) {
			toggle = btn( 'Stop offers & news', 'dak-button-secondary', function ( e ) {
				if ( window.confirm( 'Stop sending offers & news emails to ' + c.name + '? Care messages about their own visits still go to them.' ) ) { // eslint-disable-line no-alert
					post( 'opt_out', { id: c.id, out: '1' }, e.currentTarget ).then( updated );
				}
			} );
		} else if ( m.optedOutAt ) {
			toggle = btn( 'Allow offers & news again', 'dak-button-secondary', function ( e ) {
				if ( window.confirm( 'Only do this if ' + c.name + ' asked to get offers & news again. Allow them?' ) ) { // eslint-disable-line no-alert
					post( 'opt_out', { id: c.id, out: '0' }, e.currentTarget ).then( updated );
				}
			} );
		}

		kids.push( h( 'p', { class: 'dak-crm-help' }, [ status, m.accountOff && m.optedOutAt ? ' Their account’s announcement setting is off too — only they can turn it back on.' : '' ] ) );
		kids.push( h( 'div', { class: 'dak-crm-actions' }, [
			btn( 'Write an email', 'dak-button-primary', function () {
				openComposer( { mode: 'single', contact: c } );
			} ),
			toggle,
		] ) );

		return section( 'Email', kids );
	}

	function labelled( id, label, control, help ) {
		control.id = id;

		return h( 'div', { class: 'dak-crm-field' }, [
			h( 'label', { for: id, text: label } ),
			control,
			help ? h( 'p', { class: 'dak-crm-help', text: help } ) : null,
		] );
	}

	function detailsForm( c ) {
		var form = h( 'form', { class: 'dak-crm-form', novalidate: true } );
		var fields = {};

		if ( staff ) {
			var editable = ! c.hasAccount;

			fields.name = h( 'input', { type: 'text', class: 'dak-crm-input', value: c.name, readonly: ! editable, maxlength: '191' } );
			fields.phone = h( 'input', { type: 'tel', class: 'dak-crm-input', value: c.phone, readonly: ! editable, maxlength: '40', inputmode: 'tel' } );
			fields.email = h( 'input', { type: 'email', class: 'dak-crm-input', value: c.email, readonly: ! editable, maxlength: '191' } );
			fields.stage = select( mapToOptions( config.stages ), c.stage, { class: 'dak-crm-input' } );
			fields.lost_reason = h( 'input', { type: 'text', class: 'dak-crm-input', value: c.lostReason, maxlength: '191', placeholder: 'e.g. Went to another clinic' } );
			fields.owner_id = select( [ { id: 0, label: 'Unassigned' } ].concat( config.staff ), c.ownerId, { class: 'dak-crm-input' } );
			fields.source = select( mapToOptions( config.sources ), c.source, { class: 'dak-crm-input' } );
			fields.tags = h( 'input', { type: 'text', class: 'dak-crm-input', value: c.tags.join( ', ' ), list: 'dak-crm-tag-list', placeholder: 'e.g. VIP, Diabetic follow-up' } );
		}

		fields.follow_up_date = h( 'input', { type: 'date', class: 'dak-crm-input', value: c.followUp } );

		var grid = h( 'div', { class: 'dak-crm-form-grid' } );

		if ( staff ) {
			grid.appendChild( labelled( 'dak-crm-f-name', 'Name', fields.name ) );
			grid.appendChild( labelled( 'dak-crm-f-phone', 'Phone', fields.phone ) );
			grid.appendChild( labelled( 'dak-crm-f-email', 'Email', fields.email, c.hasAccount ? 'From their patient account — change it there.' : '' ) );
			grid.appendChild( labelled( 'dak-crm-f-stage', 'Stage', fields.stage ) );

			var lost = labelled( 'dak-crm-f-lost', 'Why lost (optional)', fields.lost_reason );
			lost.hidden = 'lost' !== c.stage;
			fields.stage.addEventListener( 'change', function () {
				lost.hidden = 'lost' !== fields.stage.value;
			} );
			grid.appendChild( lost );
			grid.appendChild( labelled( 'dak-crm-f-owner', 'Owner', fields.owner_id ) );
			grid.appendChild( labelled( 'dak-crm-f-source', 'Source', fields.source ) );
			grid.appendChild( labelled( 'dak-crm-f-tags', 'Tags', fields.tags, 'Separate with commas.' ) );
		}

		grid.appendChild( labelled( 'dak-crm-f-follow', 'Next follow-up', fields.follow_up_date, staff ? '' : 'Reception sees this in “Follow-ups due”.' ) );
		form.appendChild( grid );
		form.appendChild( tagDatalist() );

		var save = h( 'button', { type: 'submit', class: 'dak-button dak-button-primary dak-button-sm', text: 'Save details' } );
		form.appendChild( h( 'div', { class: 'dak-crm-actions' }, [ save ] ) );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var data = { id: c.id, follow_up_date: fields.follow_up_date.value };

			if ( staff ) {
				data.stage = fields.stage.value;
				data.lost_reason = fields.lost_reason.value;
				data.owner_id = fields.owner_id.value;
				data.source = fields.source.value;
				data.tags = fields.tags.value.split( ',' ).map( function ( t ) {
					return t.trim();
				} ).filter( Boolean );

				if ( ! c.hasAccount ) {
					data.name = fields.name.value;
					data.phone = fields.phone.value;
					data.email = fields.email.value;
				}
			}

			post( 'update', data, save ).then( updated );
		} );

		return section( 'Details', [ form ] );
	}

	function tasksSection( c ) {
		var list = h( 'ul', { class: 'dak-crm-list dak-crm-tasks' } );

		c.tasks.forEach( function ( t ) {
			var open = 'open' === t.status;
			var actions = h( 'span', { class: 'dak-crm-row-actions' } );

			if ( open ) {
				actions.appendChild( btn( 'Done', 'dak-button-secondary', function ( e ) {
					taskStatus( t, 'done', e.currentTarget );
				}, { 'aria-label': 'Mark done: ' + t.title } ) );
				actions.appendChild( btn( 'Not reached', 'dak-button-secondary', function ( e ) {
					taskStatus( t, 'not_reached', e.currentTarget );
				}, { 'aria-label': 'Could not reach: ' + t.title } ) );
			} else {
				actions.appendChild( btn( 'Reopen', 'dak-button-secondary', function ( e ) {
					taskStatus( t, 'open', e.currentTarget );
				}, { 'aria-label': 'Reopen: ' + t.title } ) );
			}

			list.appendChild( h( 'li', { class: open ? ( t.overdue ? 'is-overdue' : '' ) : 'is-closed' }, [
				h( 'span', {}, [
					h( 'strong', { text: t.title } ),
					h( 'span', { class: 'dak-cell-sub', text: [ ( open ? ( t.overdue ? 'Overdue · due ' : 'Due ' ) : config.taskStatus[ t.status ] + ' · was due ' ) + fmtDate( t.due_date ), t.assignee_name ? 'for ' + t.assignee_name : 'unassigned', t.outcome ? '“' + t.outcome + '”' : '' ].filter( Boolean ).join( ' · ' ) } ),
				] ),
				actions,
			] ) );
		} );

		var title = h( 'input', { type: 'text', class: 'dak-crm-input', maxlength: '191', placeholder: 'e.g. Call to book a review visit' } );
		var due = h( 'input', { type: 'date', class: 'dak-crm-input', value: addDays( config.today, 1 ) } );
		var assignee = select( [ { id: 0, label: 'Unassigned' } ].concat( config.staff ), staff ? config.userId : 0, { class: 'dak-crm-input' } );
		var addBtn = h( 'button', { type: 'submit', class: 'dak-button dak-button-secondary dak-button-sm', text: 'Add task' } );
		var form = h( 'form', { class: 'dak-crm-form dak-crm-inline-form', novalidate: true }, [
			labelled( 'dak-crm-t-title', 'New task', title ),
			labelled( 'dak-crm-t-due', 'Due', due ),
			labelled( 'dak-crm-t-who', 'For', assignee ),
			h( 'div', { class: 'dak-crm-actions' }, [ addBtn ] ),
		] );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			if ( ! title.value.trim() ) {
				title.focus();
				return;
			}

			post( 'task_add', { id: c.id, title: title.value, due_date: due.value, assignee_id: assignee.value }, addBtn ).then( updated );
		} );

		return section( 'Tasks', [ c.tasks.length ? list : h( 'p', { class: 'dak-crm-help', text: 'No tasks yet.' } ), form ] );
	}

	function taskStatus( t, status, button ) {
		var outcome = '';

		if ( 'open' !== status ) {
			outcome = window.prompt( 'Anything to note? (optional, e.g. “Booked for Monday”)', '' ); // eslint-disable-line no-alert

			if ( null === outcome ) {
				return null;
			}
		}

		return post( 'task_update', { task_id: t.id, status: status, outcome: outcome }, button ).then( function ( res ) {
			if ( state.contact && res.success ) {
				updated( res );
			}

			if ( ! root.querySelector( '[data-crm-tasks]' ).hidden ) {
				loadTasks();
			}

			return res;
		} );
	}

	function notesSection( c ) {
		var body = h( 'textarea', { class: 'dak-crm-input', rows: '3', maxlength: '4000', placeholder: 'e.g. Prefers evening appointments. Asked about the diabetes package.' } );
		var addBtn = h( 'button', { type: 'submit', class: 'dak-button dak-button-secondary dak-button-sm', text: 'Add note' } );
		var form = h( 'form', { class: 'dak-crm-form', novalidate: true }, [
			labelled( 'dak-crm-note', 'New note', body, 'Staff notes about contact and bookings — keep clinical notes in the encounter.' ),
			h( 'div', { class: 'dak-crm-actions' }, [ addBtn ] ),
		] );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			if ( ! body.value.trim() ) {
				body.focus();
				return;
			}

			post( 'note', { id: c.id, body: body.value }, addBtn ).then( updated );
		} );

		return section( 'Notes', [ form ] );
	}

	function timelineSection( c ) {
		var icons = { appointment: 'Booking', visit: 'Visit', request: 'Request', note: 'Note', activity: 'Update', email: 'Email', hidden: '' };
		var list = h( 'ol', { class: 'dak-crm-timeline' } );

		c.timeline.forEach( function ( item ) {
			var link = null;

			if ( item.meta && item.meta.appointment_id && config.urls.appointments ) {
				link = h( 'a', { href: config.urls.appointments + '#dak-appointment-' + item.meta.appointment_id, text: 'Open in Appointments' } );
			} else if ( item.meta && item.meta.encounter_id && config.urls.encounter ) {
				link = h( 'a', { href: config.urls.encounter + '&encounter_id=' + item.meta.encounter_id, text: 'Open encounter' } );
			} else if ( item.meta && item.meta.campaign_id && staff ) {
				link = h( 'button', { type: 'button', class: 'dak-crm-linkish', text: 'Open group email', onclick: function () {
					openCampaign( item.meta.campaign_id );
				} } );
			}

			var body = item.meta && item.meta.body ? h( 'details', { class: 'dak-crm-tl-message' }, [
				h( 'summary', { text: 'Show message' } ),
				h( 'div', { class: 'dak-crm-prewrap', text: item.meta.body } ),
			] ) : null;

			list.appendChild( h( 'li', { class: 'is-' + item.type }, [
				icons[ item.type ] ? h( 'span', { class: 'dak-crm-tl-type', text: icons[ item.type ] } ) : null,
				h( 'span', { class: 'dak-crm-tl-body' }, [
					h( 'strong', { text: item.title } ),
					item.detail ? h( 'span', { class: 'dak-crm-tl-detail', text: item.detail } ) : null,
					body,
					link,
				] ),
				item.at ? h( 'time', { datetime: item.at, text: fmtDateTime( item.at ) } ) : null,
			] ) );
		} );

		return section( 'History', [ c.timeline.length ? list : h( 'p', { class: 'dak-crm-help', text: 'Nothing yet.' } ) ] );
	}

	/* ------------------------------------------------------------------
	 * Tasks tab
	 * ---------------------------------------------------------------- */

	function loadTasks() {
		var box = root.querySelector( '[data-crm-tasks]' );

		return post( 'tasks', { who: state.taskWho, upcoming: state.taskUpcoming ? '1' : '' } ).then( function ( res ) {
			if ( ! res.success ) {
				return;
			}

			box.textContent = '';

			var controls = h( 'div', { class: 'dak-crm-task-controls' } );

			if ( staff ) {
				var who = select( [ { id: 'mine', label: 'My tasks' }, { id: 'all', label: 'Everyone’s tasks' } ], state.taskWho, { class: 'dak-crm-input', 'aria-label': 'Whose tasks' } );
				who.addEventListener( 'change', function () {
					state.taskWho = who.value;
					loadTasks();
				} );
				controls.appendChild( who );
			}

			var upcoming = h( 'input', { type: 'checkbox', id: 'dak-crm-upcoming', checked: state.taskUpcoming } );
			upcoming.addEventListener( 'change', function () {
				state.taskUpcoming = upcoming.checked;
				loadTasks();
			} );
			controls.appendChild( h( 'label', { class: 'dak-crm-check', for: 'dak-crm-upcoming' }, [ upcoming, ' Include tasks due later' ] ) );
			box.appendChild( controls );

			var tasks = res.data.tasks;
			var groups = [
				[ 'Overdue', tasks.filter( function ( t ) {
					return t.due_date < config.today;
				} ) ],
				[ 'Today', tasks.filter( function ( t ) {
					return t.due_date === config.today;
				} ) ],
				[ 'Later', tasks.filter( function ( t ) {
					return t.due_date > config.today;
				} ) ],
			];

			if ( ! tasks.length ) {
				box.appendChild( h( 'div', { class: 'dak-crm-empty', text: state.taskUpcoming ? 'No open tasks.' : 'Nothing due today. Open tasks due later appear when you tick “Include tasks due later”.' } ) );
			}

			groups.forEach( function ( g ) {
				if ( ! g[ 1 ].length ) {
					return;
				}

				box.appendChild( h( 'h3', { class: 'dak-crm-group-title', text: g[ 0 ] + ' (' + g[ 1 ].length + ')' } ) );
				box.appendChild( h( 'ul', { class: 'dak-crm-list dak-crm-tasks' }, g[ 1 ].map( function ( t ) {
					return h( 'li', { class: t.overdue ? 'is-overdue' : '' }, [
						h( 'span', {}, [
							h( 'button', { type: 'button', class: 'dak-crm-name', text: t.contact_name, onclick: function ( e ) {
								openPanel( t.contact_id, e.currentTarget );
							} } ),
							h( 'strong', { class: 'dak-crm-task-title', text: t.title } ),
							h( 'span', { class: 'dak-cell-sub', text: [ 'Due ' + fmtDate( t.due_date ), t.contact_phone, t.assignee_name ? 'for ' + t.assignee_name : 'unassigned' ].filter( Boolean ).join( ' · ' ) } ),
						] ),
						h( 'span', { class: 'dak-crm-row-actions' }, [
							btn( 'Done', 'dak-button-secondary', function ( e ) {
								taskStatus( t, 'done', e.currentTarget );
							}, { 'aria-label': 'Mark done: ' + t.title + ' for ' + t.contact_name } ),
							btn( 'Not reached', 'dak-button-secondary', function ( e ) {
								taskStatus( t, 'not_reached', e.currentTarget );
							}, { 'aria-label': 'Could not reach ' + t.contact_name } ),
						] ),
					] );
				} ) ) );
			} );

			updateTaskCount( tasks );
		} );
	}

	function updateTaskCount( tasks ) {
		var badge = root.querySelector( '[data-crm-task-count]' );
		var due = tasks.filter( function ( t ) {
			return t.due_date <= config.today;
		} ).length;

		badge.textContent = String( due );
		badge.hidden = ! due;
	}

	/* ------------------------------------------------------------------
	 * People / Tasks switch
	 * ---------------------------------------------------------------- */

	var tabs = Array.prototype.slice.call( root.querySelectorAll( '[data-crm-switch]' ) );

	function switchTo( name, focus ) {
		tabs.forEach( function ( t ) {
			var on = t.getAttribute( 'data-crm-switch' ) === name;
			t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			t.setAttribute( 'tabindex', on ? '0' : '-1' );
			t.classList.toggle( 'is-active', on );

			if ( on && focus ) {
				t.focus();
			}
		} );

		root.querySelector( '[data-crm-people]' ).hidden = 'people' !== name;
		root.querySelector( '[data-crm-tasks]' ).hidden = 'tasks' !== name;

		var emails = root.querySelector( '[data-crm-emails]' );

		if ( emails ) {
			emails.hidden = 'emails' !== name;
		}

		if ( 'tasks' === name ) {
			loadTasks();
		} else if ( 'emails' === name ) {
			loadEmails();
		}
	}

	tabs.forEach( function ( t, i ) {
		t.addEventListener( 'click', function () {
			switchTo( t.getAttribute( 'data-crm-switch' ), false );
		} );
		t.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowRight' === e.key || 'ArrowLeft' === e.key ) {
				e.preventDefault();
				var next = tabs[ ( i + ( 'ArrowRight' === e.key ? 1 : tabs.length - 1 ) ) % tabs.length ];
				switchTo( next.getAttribute( 'data-crm-switch' ), true );
			}
		} );
	} );

	/* ------------------------------------------------------------------
	 * Add contact dialog, export (staff)
	 * ---------------------------------------------------------------- */

	function openDialog( title, content ) {
		state.returnFocus = document.activeElement;
		dialog.querySelector( '[data-crm-dialog-title]' ).textContent = title;
		var body = dialog.querySelector( '[data-crm-dialog-body]' );
		body.textContent = '';
		body.appendChild( content );
		dialog.classList.add( 'is-open' );
		dialog.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'dak-modal-open' );
		( body.querySelector( 'input, select' ) || dialog.querySelector( '[role="dialog"]' ) ).focus();
	}

	function closeDialog() {
		dialog.classList.remove( 'is-open' );
		dialog.setAttribute( 'aria-hidden', 'true' );

		if ( drawer.hidden ) {
			document.body.classList.remove( 'dak-modal-open' );
		}

		if ( state.returnFocus && document.body.contains( state.returnFocus ) ) {
			state.returnFocus.focus();
		}
	}

	dialog.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-crm-dialog-close]' ) ) {
			closeDialog();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && dialog.classList.contains( 'is-open' ) ) {
			closeDialog();
		}
	} );

	function openAddDialog() {
		var name = h( 'input', { type: 'text', class: 'dak-crm-input', maxlength: '191', autocomplete: 'off' } );
		var phone = h( 'input', { type: 'tel', class: 'dak-crm-input', maxlength: '40', inputmode: 'tel', placeholder: 'e.g. 0300 1234567' } );
		var email = h( 'input', { type: 'email', class: 'dak-crm-input', maxlength: '191' } );
		var source = select( mapToOptions( config.sources ), 'phone', { class: 'dak-crm-input' } );
		var owner = select( [ { id: 0, label: 'Unassigned' } ].concat( config.staff ), config.userId, { class: 'dak-crm-input' } );
		var error = h( 'p', { class: 'dak-crm-error', role: 'alert', hidden: true } );
		var save = h( 'button', { type: 'submit', class: 'dak-button dak-button-primary', text: 'Add contact' } );
		var form = h( 'form', { class: 'dak-crm-form', novalidate: true }, [
			labelled( 'dak-crm-a-name', 'Name', name ),
			labelled( 'dak-crm-a-phone', 'Phone', phone, 'A phone number or an email is needed.' ),
			labelled( 'dak-crm-a-email', 'Email', email ),
			labelled( 'dak-crm-a-source', 'Where did they come from?', source ),
			labelled( 'dak-crm-a-owner', 'Owner', owner ),
			error,
			h( 'div', { class: 'dak-crm-actions' }, [ save, btn( 'Cancel', 'dak-button-secondary', closeDialog ) ] ),
		] );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			error.hidden = true;

			post( 'create', { name: name.value, phone: phone.value, email: email.value, source: source.value, owner_id: owner.value }, save ).then( function ( res ) {
				if ( ! res.success ) {
					flash( '' );
					error.textContent = res.data.message;
					error.hidden = false;
					var field = { name: name, phone: phone, email: email }[ res.data.field ];
					( field || name ).focus();
					return;
				}

				closeDialog();
				flash( res.data.message );
				load();
				openPanel( res.data.id, root.querySelector( '[data-crm-head-actions] .dak-button-primary' ) );
			} );
		} );

		openDialog( 'Add contact', form );
	}

	function exportCsv( e ) {
		var fields = { view: state.view };

		Object.keys( state.filters ).forEach( function ( k ) {
			fields[ k ] = state.filters[ k ];
		} );

		post( 'export', fields, e.currentTarget ).then( function ( res ) {
			if ( ! res.success ) {
				return;
			}

			var blob = new Blob( [ '﻿' + res.data.csv ], { type: 'text/csv;charset=utf-8' } );
			var a = h( 'a', { href: URL.createObjectURL( blob ), download: res.data.filename } );
			document.body.appendChild( a );
			a.click();
			setTimeout( function () {
				URL.revokeObjectURL( a.href );
				a.remove();
			}, 1000 );
			flash( res.data.count + ' contacts exported.' );
		} );
	}

	/* ------------------------------------------------------------------
	 * Email (staff): composer, group emails, templates
	 * ---------------------------------------------------------------- */

	function purposePill( purpose ) {
		var p = config.email.purposes[ purpose ];

		return h( 'span', { class: 'dak-crm-purpose is-' + purpose, text: p ? p.label : purpose } );
	}

	function loadTemplates( force ) {
		if ( state.templates && ! force ) {
			return Promise.resolve( state.templates );
		}

		return post( 'templates', {} ).then( function ( res ) {
			if ( res.success ) {
				state.templates = res.data.templates;
			}

			return state.templates || [];
		} );
	}

	/**
	 * The email composer, in the side panel.
	 *
	 * @param {Object} opts { mode: 'group'|'single'|'template', audience: { type: 'filter'|'ids', ids, label, count }, contact, template }
	 */
	function openComposer( opts, trigger ) {
		var mode = opts.mode;
		var c = opts.contact;
		var tpl = opts.template || null;
		var list = 'group' === mode && 'filter' === opts.audience.type ? listFields() : null;
		var sampleId = 'single' === mode ? c.id : 0;
		var lastField = null;

		showDrawer( trigger );
		drawer.querySelector( '.dak-crm-panel' ).classList.add( 'is-wide' );

		var title;

		if ( 'group' === mode ) {
			title = 'Email ' + ( 1 === opts.audience.count ? '1 person' : opts.audience.count + ' people' );
		} else if ( 'single' === mode ) {
			title = 'Email ' + c.name;
		} else {
			title = tpl ? 'Edit template' : 'New template';
		}

		panelBody.appendChild( h( 'header', { class: 'dak-crm-panel-head' }, [
			'single' === mode ? h( 'button', { type: 'button', class: 'dak-crm-linkish dak-crm-back', text: '← Back to ' + c.name, onclick: function () {
				openPanel( c.id );
			} } ) : null,
			h( 'h2', { id: 'dak-crm-panel-title', text: title } ),
			'group' === mode ? h( 'p', { class: 'dak-crm-panel-meta', text: 'To: ' + opts.audience.label } ) : null,
			'single' === mode ? h( 'p', { class: 'dak-crm-panel-meta', text: 'To: ' + c.email } ) : null,
			'template' === mode ? h( 'p', { class: 'dak-crm-help', text: 'Templates are starting points: whoever uses one can still change the wording before sending.' } ) : null,
		] ) );

		var check = 'group' === mode ? h( 'p', { class: 'dak-crm-recipients', role: 'status', 'aria-live': 'polite', text: 'Checking who can receive it…' } ) : null;
		var status = h( 'p', { class: 'dak-crm-panel-status', role: 'status', 'aria-live': 'polite', hidden: true } );
		var error = h( 'p', { class: 'dak-crm-error', role: 'alert', hidden: true } );

		function say( text, isError ) {
			error.hidden = ! isError;
			status.hidden = !! isError || ! text;
			( isError ? error : status ).textContent = text || '';
		}

		// Fields.
		var f = {};
		var radios = {};
		var purposeBox = h( 'fieldset', { class: 'dak-crm-purposes' }, [ h( 'legend', { text: 'Type of email' } ) ] );

		Object.keys( config.email.purposes ).forEach( function ( key ) {
			var p = config.email.purposes[ key ];
			var blocked = 'single' === mode && 'marketing' === key && c.marketing && ! c.marketing.allowed;
			var id = 'dak-crm-p-' + key;

			radios[ key ] = h( 'input', { type: 'radio', name: 'dak-crm-purpose', id: id, value: key, disabled: blocked } );
			purposeBox.appendChild( h( 'label', { class: 'dak-crm-purpose-option', for: id }, [
				radios[ key ],
				h( 'span', {}, [ h( 'strong', { text: p.label } ), h( 'span', { class: 'dak-crm-help', text: blocked ? 'Not available — they unsubscribed from offers & news.' : p.help } ) ] ),
			] ) );
			radios[ key ].addEventListener( 'change', function () {
				if ( check ) {
					recheck();
				}
			} );
		} );

		function purpose() {
			var on = Object.keys( radios ).filter( function ( k ) {
				return radios[ k ].checked;
			} )[ 0 ];

			return on || '';
		}

		function setPurpose( key ) {
			Object.keys( radios ).forEach( function ( k ) {
				radios[ k ].checked = k === key && ! radios[ k ].disabled;
			} );
		}

		f.name = h( 'input', { type: 'text', class: 'dak-crm-input', maxlength: '191', placeholder: 'template' === mode ? 'e.g. Review visit reminder' : 'e.g. Eid closure notice' } );
		f.subject = h( 'input', { type: 'text', class: 'dak-crm-input', maxlength: '191' } );
		f.heading = h( 'input', { type: 'text', class: 'dak-crm-input', maxlength: '191' } );
		f.body = h( 'textarea', { class: 'dak-crm-input', rows: '9', maxlength: '5000' } );
		f.button_label = h( 'input', { type: 'text', class: 'dak-crm-input', maxlength: '60', placeholder: 'e.g. Book an appointment' } );
		f.button_url = h( 'input', { type: 'url', class: 'dak-crm-input', maxlength: '500', placeholder: 'https://' } );

		[ f.subject, f.heading, f.body ].forEach( function ( el ) {
			el.addEventListener( 'focus', function () {
				lastField = el;
			} );
		} );

		function fill( t ) {
			[ 'subject', 'heading', 'body', 'button_label', 'button_url' ].forEach( function ( k ) {
				f[ k ].value = t[ k ] || '';
			} );
			setPurpose( t.purpose );

			if ( 'template' === mode ) {
				f.name.value = t.name || '';
			}

			if ( check ) {
				recheck();
			}
		}

		var templateSelect = null;

		if ( 'template' !== mode ) {
			templateSelect = select( [ { id: '', label: 'Loading templates…' } ], '', { class: 'dak-crm-input' } );
			loadTemplates().then( function ( list ) {
				templateSelect.textContent = '';
				templateSelect.appendChild( h( 'option', { value: '', text: 'Start from scratch' } ) );
				list.filter( function ( t ) {
					return 'active' === t.status;
				} ).forEach( function ( t ) {
					templateSelect.appendChild( h( 'option', { value: String( t.id ), text: t.name + ' (' + config.email.purposes[ t.purpose ].label + ')' } ) );
				} );
			} );
			templateSelect.addEventListener( 'change', function () {
				var t = ( state.templates || [] ).filter( function ( x ) {
					return String( x.id ) === templateSelect.value;
				} )[ 0 ];
				var dirty = f.subject.value.trim() || f.body.value.trim();

				if ( ! t ) {
					return;
				}

				if ( dirty && ! window.confirm( 'Replace what you’ve written with the template “' + t.name + '”?' ) ) { // eslint-disable-line no-alert
					return;
				}

				fill( t );
			} );
		}

		// Merge-field buttons insert at the cursor of the last field used.
		var chips = h( 'div', { class: 'dak-crm-chips' }, [ h( 'span', { class: 'dak-crm-help', text: 'Insert:' } ) ] );

		Object.keys( config.email.mergeFields ).forEach( function ( token ) {
			chips.appendChild( h( 'button', { type: 'button', class: 'dak-crm-chip', text: config.email.mergeFields[ token ], 'aria-label': 'Insert ' + config.email.mergeFields[ token ], onclick: function () {
				var el = lastField || f.body;
				var start = 'number' === typeof el.selectionStart ? el.selectionStart : el.value.length;
				var end = 'number' === typeof el.selectionEnd ? el.selectionEnd : start;
				var text = '{' + token + '}';

				el.value = el.value.slice( 0, start ) + text + el.value.slice( end );
				el.focus();
				el.setSelectionRange( start + text.length, start + text.length );
			} } ) );
		} );

		var form = h( 'form', { class: 'dak-crm-form', novalidate: true } );

		if ( 'group' === mode ) {
			form.appendChild( check );
		}

		form.appendChild( purposeBox );

		if ( templateSelect ) {
			form.appendChild( labelled( 'dak-crm-e-template', 'Start from a template', templateSelect ) );
		}

		if ( 'single' !== mode ) {
			form.appendChild( labelled( 'dak-crm-e-name', 'template' === mode ? 'Template name' : 'Name for your records (optional)', f.name, 'group' === mode ? 'Shown in the Emails tab. Recipients don’t see it.' : '' ) );
		}

		form.appendChild( labelled( 'dak-crm-e-subject', 'Subject', f.subject ) );
		form.appendChild( labelled( 'dak-crm-e-heading', 'Heading (optional)', f.heading, 'Large text at the top of the email. Leave empty to use the subject.' ) );
		form.appendChild( labelled( 'dak-crm-e-body', 'Message', f.body, 'A greeting (“Hi Sara,”) is added for you. Leave a blank line between paragraphs. Merge fields are filled in for each person; an empty one (e.g. no next appointment) is left out.' ) );
		form.appendChild( chips );
		form.appendChild( h( 'div', { class: 'dak-crm-form-grid' }, [
			labelled( 'dak-crm-e-blabel', 'Button text (optional)', f.button_label ),
			labelled( 'dak-crm-e-burl', 'Button link', f.button_url ),
		] ) );
		form.appendChild( error );
		form.appendChild( status );

		var primaryText = { group: 'Send', single: 'Send email', template: 'Save template' }[ mode ];
		var primary = h( 'button', { type: 'submit', class: 'dak-button dak-button-primary dak-button-sm', text: primaryText } );
		var previewBtn = btn( 'Preview', 'dak-button-secondary', function ( e ) {
			showPreview( e.currentTarget );
		} );
		var testBtn = btn( 'Send a test to me', 'dak-button-secondary', function ( e ) {
			var data = content();
			data.contact_id = sampleId || '';
			say( '' );
			post( 'email_test', data, e.currentTarget ).then( function ( res ) {
				if ( res.success ) {
					say( res.data.message );
				} else {
					fieldError( res );
				}
			} );
		}, { title: config.email.myEmail ? 'Sends to ' + config.email.myEmail : '' } );

		form.appendChild( h( 'div', { class: 'dak-crm-actions' }, [ primary, previewBtn, testBtn ] ) );

		var previewBox = h( 'div', { class: 'dak-crm-preview', hidden: true } );
		form.appendChild( previewBox );
		panelBody.appendChild( form );

		if ( tpl ) {
			fill( tpl );
		} else {
			setPurpose( 'care' );
		}

		function content() {
			var data = { purpose: purpose(), template_id: templateSelect ? templateSelect.value : ( tpl ? tpl.id : '' ) };

			Object.keys( f ).forEach( function ( k ) {
				data[ k ] = f[ k ].value;
			} );

			return data;
		}

		function audienceFields( data ) {
			if ( 'ids' === opts.audience.type ) {
				data.audience = 'ids';
				data.ids = opts.audience.ids;
			} else {
				data.audience = 'filter';
				Object.keys( list ).forEach( function ( k ) {
					data[ k ] = list[ k ];
				} );
			}

			return data;
		}

		function fieldError( res ) {
			var message = ( res.data && res.data.message ) || 'Something went wrong.';
			var map = { name: f.name, subject: f.subject, heading: f.heading, body: f.body, button_label: f.button_label, button_url: f.button_url, purpose: radios.care };

			flash( '' );
			say( message, true );

			if ( res.data && map[ res.data.field ] ) {
				map[ res.data.field ].focus();
			}
		}

		var willSend = 0;

		function recheck() {
			post( 'email_check', audienceFields( { purpose: purpose() } ) ).then( function ( res ) {
				if ( ! res.success ) {
					check.textContent = res.data.message;
					primary.disabled = true;
					return;
				}

				var d = res.data;
				var skipped = [];

				if ( d.counts.no_email ) {
					skipped.push( d.counts.no_email + ' without an email address' );
				}

				if ( d.counts.unsubscribed ) {
					skipped.push( d.counts.unsubscribed + ' unsubscribed from offers & news' );
				}

				if ( d.counts.duplicate ) {
					skipped.push( d.counts.duplicate + ' sharing an email with someone else on the list (they get one copy)' );
				}

				willSend = d.send;
				sampleId = d.sample ? d.sample.id : 0;
				check.textContent = ( 1 === d.send ? '1 person will get it.' : d.send + ' people will get it.' ) + ( skipped.length ? ' Skipped: ' + skipped.join( '; ' ) + '.' : '' );
				primary.textContent = d.send ? 'Send to ' + ( 1 === d.send ? '1 person' : d.send + ' people' ) : 'No one to send to';
				primary.disabled = ! d.send;
			} );
		}

		if ( check ) {
			primary.disabled = true;
			recheck();
		}

		function showPreview( button ) {
			var data = content();
			data.contact_id = sampleId || '';
			say( '' );

			post( 'email_preview', data, button ).then( function ( res ) {
				if ( ! res.success ) {
					fieldError( res );
					return;
				}

				previewBox.textContent = '';
				previewBox.hidden = false;
				previewBox.appendChild( h( 'p', { class: 'dak-crm-help' }, [
					h( 'strong', { text: 'Subject: ' + res.data.subject } ),
					' — ' + ( res.data.example ? 'shown with example details.' : 'shown as it will look for one recipient.' ),
				] ) );

				var frame = h( 'iframe', { class: 'dak-crm-preview-frame', title: 'Email preview', sandbox: '' } );
				frame.srcdoc = res.data.html;
				previewBox.appendChild( frame );
				previewBox.scrollIntoView( { block: 'nearest' } );
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			say( '' );

			var data = content();

			if ( 'template' === mode ) {
				data.template_id = tpl ? tpl.id : '';
				post( 'template_save', data, primary ).then( function ( res ) {
					if ( ! res.success ) {
						fieldError( res );
						return;
					}

					state.templates = res.data.templates;
					closePanel();
					flash( res.data.message );

					if ( ! root.querySelector( '[data-crm-emails]' ).hidden ) {
						loadEmails();
					}
				} );
				return;
			}

			if ( 'single' === mode ) {
				data.id = c.id;
				post( 'email_send', data, primary ).then( function ( res ) {
					if ( ! res.success ) {
						fieldError( res );
						return;
					}

					flash( res.data.message );
					renderPanel( res.data.contact );
					load();
				} );
				return;
			}

			if ( ! window.confirm( 'Send “' + f.subject.value.trim() + '” to ' + ( 1 === willSend ? '1 person' : willSend + ' people' ) + ' now? Emails can’t be called back once sent.' ) ) { // eslint-disable-line no-alert
				return;
			}

			data = audienceFields( data );
			data.audience_label = opts.audience.label;

			post( 'campaign_create', data, primary ).then( function ( res ) {
				if ( ! res.success ) {
					fieldError( res );
					return;
				}

				showProgress( res.data.id, f.subject.value.trim() );
			} );
		} );

		focusTitle();
	}

	/** "30 sent · 1 failed · 3 skipped". */
	function resultText( counts ) {
		var parts = [];
		var labels = { sent: 'sent', failed: 'failed', skipped: 'skipped', cancelled: 'cancelled' };
		var waiting = ( counts.queued || 0 ) + ( counts.sending || 0 );

		Object.keys( labels ).forEach( function ( k ) {
			if ( counts[ k ] ) {
				parts.push( counts[ k ] + ' ' + labels[ k ] );
			}
		} );

		if ( waiting ) {
			parts.push( waiting + ' waiting' );
		}

		return parts.join( ' · ' ) || '—';
	}

	/**
	 * Sends a group email batch by batch while the page is open (the
	 * server's scheduled sender does the same, more slowly, if it's closed).
	 */
	function sendLoop( id, onUpdate ) {
		if ( state.sending[ id ] ) {
			return;
		}

		state.sending[ id ] = true;

		( function step() {
			post( 'campaign_process', { campaign_id: id } ).then( function ( res ) {
				var d = res.success ? res.data : null;
				var more = d && 'sending' === d.status && d.counts.queued > 0;

				if ( onUpdate ) {
					onUpdate( d, ! more );
				}

				if ( more ) {
					step();
				} else {
					delete state.sending[ id ];
				}
			} );
		}() );
	}

	function showProgress( id, subject ) {
		showDrawer();

		var text = h( 'p', { class: 'dak-crm-panel-status', role: 'status', 'aria-live': 'polite', text: 'Starting…' } );
		var bar = h( 'progress', { class: 'dak-crm-progress', max: '1', value: '0', 'aria-label': 'Sending progress' } );

		panelBody.appendChild( h( 'header', { class: 'dak-crm-panel-head' }, [
			h( 'h2', { id: 'dak-crm-panel-title', text: 'Sending “' + subject + '”' } ),
		] ) );
		panelBody.appendChild( h( 'section', { class: 'dak-crm-section' }, [
			bar,
			text,
			h( 'p', { class: 'dak-crm-help', text: 'You can close this panel. Sending carries on in the background, a little more slowly.' } ),
			h( 'div', { class: 'dak-crm-actions' }, [
				btn( 'View details', 'dak-button-secondary', function () {
					openCampaign( id );
				} ),
				btn( 'Close', 'dak-button-secondary', closePanel ),
			] ),
		] ) );
		focusTitle();

		sendLoop( id, function ( d, finished ) {
			if ( ! d ) {
				text.textContent = 'Sending paused. It continues in the background — open the email in the Emails tab to check on it.';
				return;
			}

			var c = d.counts;
			var total = c.queued + c.sending + c.sent + c.failed + c.cancelled;
			var done = c.sent + c.failed + c.cancelled;

			bar.max = String( total || 1 );
			bar.value = String( done );
			text.textContent = finished
				? 'Done: ' + resultText( c ) + '.' + ( c.failed ? ' Open the details to see who and try again.' : '' )
				: 'Sending… ' + done + ' of ' + total + ' done.';

			if ( finished ) {
				flash( 'Group email sent: ' + resultText( c ) + '.' );
			}
		} );
	}

	/** One group email: what was sent, to whom, and how it went. */
	function openCampaign( id, trigger, filter, page ) {
		filter = filter || '';
		page = page || 1;
		showDrawer( trigger );
		drawer.querySelector( '.dak-crm-panel' ).classList.add( 'is-wide' );
		panelBody.appendChild( h( 'p', { class: 'dak-crm-loading', role: 'status', text: 'Loading…' } ) );

		post( 'campaign', { campaign_id: id, status: filter, page: page } ).then( function ( res ) {
			if ( ! res.success ) {
				closePanel();
				return;
			}

			var c = res.data;
			var counts = c.counts;
			var reopen = function () {
				openCampaign( id, null, filter, page );
			};

			panelBody.textContent = '';
			panelBody.appendChild( h( 'header', { class: 'dak-crm-panel-head' }, [
				h( 'h2', { id: 'dak-crm-panel-title', text: c.name } ),
				h( 'p', { class: 'dak-crm-panel-meta' }, [
					purposePill( c.purpose ),
					h( 'span', { class: 'dak-crm-badge', text: { sending: 'Sending', sent: 'Finished', cancelled: 'Stopped' }[ c.status ] || c.status } ),
					h( 'span', { class: 'dak-cell-sub', text: 'Started ' + fmtDateTime( c.createdAt ) + ( c.createdBy ? ' by ' + c.createdBy : '' ) } ),
				] ),
				h( 'p', { class: 'dak-cell-sub', text: 'To: ' + c.audience } ),
			] ) );

			var actions = h( 'div', { class: 'dak-crm-actions' } );

			if ( 'sending' === c.status ) {
				actions.appendChild( btn( state.sending[ id ] ? 'Sending…' : 'Send the rest now', 'dak-button-primary', function ( e ) {
					e.currentTarget.disabled = true;
					e.currentTarget.textContent = 'Sending…';
					sendLoop( id, function ( d, finished ) {
						if ( finished ) {
							reopen();
						}
					} );
				}, { disabled: !! state.sending[ id ] } ) );
				actions.appendChild( btn( 'Stop sending', 'dak-button-secondary', function ( e ) {
					if ( window.confirm( 'Stop sending? People who haven’t been sent it yet won’t get it. Emails already sent can’t be called back.' ) ) { // eslint-disable-line no-alert
						post( 'campaign_cancel', { campaign_id: id }, e.currentTarget ).then( function ( r ) {
							if ( r.success ) {
								flash( r.data.message );
								reopen();
							}
						} );
					}
				} ) );
			} else if ( counts.failed ) {
				actions.appendChild( btn( 'Try the failed ones again', 'dak-button-primary', function ( e ) {
					post( 'campaign_retry', { campaign_id: id }, e.currentTarget ).then( function ( r ) {
						if ( r.success ) {
							flash( r.data.message );
							sendLoop( id, function ( d, finished ) {
								if ( finished ) {
									reopen();
								}
							} );
							reopen();
						}
					} );
				} ) );
			}

			var filters = h( 'div', { class: 'dak-crm-views', role: 'group', 'aria-label': 'Show recipients' } );
			[ [ '', 'Everyone' ], [ 'sent', 'Sent' ], [ 'failed', 'Failed' ], [ 'skipped', 'Skipped' ], [ 'queued', 'Waiting' ], [ 'cancelled', 'Cancelled' ] ].forEach( function ( pair ) {
				var n = '' === pair[ 0 ] ? Object.keys( counts ).reduce( function ( s, k ) {
					return s + counts[ k ];
				}, 0 ) : counts[ pair[ 0 ] ];

				if ( pair[ 0 ] && ! n ) {
					return;
				}

				filters.appendChild( h( 'button', { type: 'button', class: 'dak-crm-view' + ( filter === pair[ 0 ] ? ' is-active' : '' ), 'aria-pressed': filter === pair[ 0 ] ? 'true' : 'false', onclick: function () {
					openCampaign( id, null, pair[ 0 ], 1 );
				} }, [ pair[ 1 ], h( 'span', { class: 'dak-crm-count', text: String( n ) } ) ] ) );
			} );

			panelBody.appendChild( h( 'section', { class: 'dak-crm-section' }, [
				h( 'h3', { text: 'Result' } ),
				h( 'p', { text: resultText( counts ) } ),
				actions.childNodes.length ? actions : null,
			] ) );

			panelBody.appendChild( section( 'Message', [
				h( 'dl', { class: 'dak-crm-message' }, [
					h( 'dt', { text: 'Subject' } ), h( 'dd', { text: c.subject } ),
					c.heading ? h( 'dt', { text: 'Heading' } ) : null, c.heading ? h( 'dd', { text: c.heading } ) : null,
					h( 'dt', { text: 'Message' } ), h( 'dd', { class: 'dak-crm-prewrap', text: c.body } ),
					c.buttonLabel ? h( 'dt', { text: 'Button' } ) : null, c.buttonLabel ? h( 'dd', { text: c.buttonLabel + ' → ' + c.buttonUrl } ) : null,
				] ),
				h( 'p', { class: 'dak-crm-help', text: 'Merge fields like {first_name} were filled in for each person.' } ),
			] ) );

			var rows = h( 'tbody' );

			c.recipients.forEach( function ( r ) {
				rows.appendChild( h( 'tr', {}, [
					h( 'td', { 'data-label': 'Name', class: 'dak-col-primary' }, [ r.contactId ? h( 'button', { type: 'button', class: 'dak-crm-name', text: r.name, onclick: function () {
						openPanel( r.contactId );
					} } ) : h( 'span', { text: r.name } ) ] ),
					h( 'td', { 'data-label': 'Email', text: r.email || '—' } ),
					h( 'td', { 'data-label': 'Status' }, [ h( 'span', { class: 'dak-crm-msg is-' + r.status, text: config.email.statuses[ r.status ] || r.status } ) ] ),
					h( 'td', { 'data-label': 'Note', text: r.error || ( r.sentAt && 'sent' === r.status ? fmtDateTime( r.sentAt ) : '' ) } ),
				] ) );
			} );

			var pager = h( 'nav', { class: 'dak-crm-pager', 'aria-label': 'Recipient pages' } );

			if ( c.pages > 1 ) {
				pager.appendChild( btn( 'Previous', 'dak-button-secondary', function () {
					openCampaign( id, null, filter, page - 1 );
				}, { disabled: page <= 1 } ) );
				pager.appendChild( h( 'span', { text: 'Page ' + page + ' of ' + c.pages } ) );
				pager.appendChild( btn( 'Next', 'dak-button-secondary', function () {
					openCampaign( id, null, filter, page + 1 );
				}, { disabled: page >= c.pages } ) );
			}

			panelBody.appendChild( section( 'Recipients', [
				filters,
				c.recipients.length ? h( 'table', { class: 'dak-data-table dak-crm-table' }, [
					h( 'thead', {}, [ h( 'tr', {}, [ 'Name', 'Email', 'Status', 'Note' ].map( function ( t ) {
						return h( 'th', { scope: 'col', text: t } );
					} ) ) ] ),
					rows,
				] ) : h( 'p', { class: 'dak-crm-help', text: 'No one here.' } ),
				pager,
			] ) );
			focusTitle();
		} );
	}

	/* Emails tab ------------------------------------------------------- */

	function loadEmails( page ) {
		var box = root.querySelector( '[data-crm-emails]' );
		var listBox = h( 'div', { class: 'dak-crm-table-wrap' } );
		var tabsBar = h( 'div', { class: 'dak-crm-views', role: 'group', 'aria-label': 'Emails' } );

		[ [ 'campaigns', 'Group emails' ], [ 'templates', 'Templates' ] ].forEach( function ( pair ) {
			var on = state.emailsTab === pair[ 0 ];

			tabsBar.appendChild( h( 'button', { type: 'button', class: 'dak-crm-view' + ( on ? ' is-active' : '' ), 'aria-pressed': on ? 'true' : 'false', text: pair[ 1 ], onclick: function () {
				state.emailsTab = pair[ 0 ];
				loadEmails();
			} } ) );
		} );

		box.textContent = '';
		box.appendChild( h( 'div', { class: 'dak-crm-emails-bar' }, [
			tabsBar,
			'templates' === state.emailsTab ? btn( '+ New template', 'dak-button-primary', function ( e ) {
				openComposer( { mode: 'template' }, e.currentTarget );
			} ) : null,
		] ) );
		box.appendChild( listBox );
		listBox.appendChild( h( 'p', { class: 'dak-crm-loading', role: 'status', text: 'Loading…' } ) );

		if ( 'templates' === state.emailsTab ) {
			loadTemplates( true ).then( function ( list ) {
				renderTemplateList( listBox, list );
			} );
			return;
		}

		post( 'campaigns', { page: page || 1 } ).then( function ( res ) {
			if ( res.success ) {
				renderCampaignList( listBox, res.data );
			}
		} );
	}

	function renderCampaignList( box, data ) {
		box.textContent = '';

		if ( ! data.rows.length ) {
			box.appendChild( h( 'div', { class: 'dak-crm-empty', text: 'No group emails yet. On the People tab, filter the list (or tick people), then use “Email these people”.' } ) );
			return;
		}

		var tbody = h( 'tbody' );

		data.rows.forEach( function ( c ) {
			var sending = 'sending' === c.status;

			// Keep sending while someone has the CRM open.
			if ( sending ) {
				sendLoop( c.id, function ( d, finished ) {
					if ( finished && ! root.querySelector( '[data-crm-emails]' ).hidden && drawer.hidden ) {
						loadEmails( data.page );
					}
				} );
			}

			tbody.appendChild( h( 'tr', {}, [
				h( 'td', { 'data-label': 'Email', class: 'dak-col-primary' }, [
					h( 'button', { type: 'button', class: 'dak-crm-name', text: c.name, onclick: function ( e ) {
						openCampaign( c.id, e.currentTarget );
					} } ),
					c.name !== c.subject ? h( 'span', { class: 'dak-cell-sub', text: c.subject } ) : null,
				] ),
				h( 'td', { 'data-label': 'Type' }, [ purposePill( c.purpose ) ] ),
				h( 'td', { 'data-label': 'Sent to', text: c.audience || '—' } ),
				h( 'td', { 'data-label': 'Started' }, [ h( 'span', { text: fmtDateTime( c.createdAt ) } ), c.createdBy ? h( 'span', { class: 'dak-cell-sub', text: 'by ' + c.createdBy } ) : null ] ),
				h( 'td', { 'data-label': 'Result' }, [
					h( 'span', { text: resultText( c.counts ) } ),
					sending ? h( 'span', { class: 'dak-crm-flag is-amber', text: 'Sending' } ) : null,
					'cancelled' === c.status ? h( 'span', { class: 'dak-crm-flag', text: 'Stopped' } ) : null,
				] ),
			] ) );
		} );

		box.appendChild( h( 'table', { class: 'dak-data-table dak-crm-table' }, [
			h( 'thead', {}, [ h( 'tr', {}, [ 'Email', 'Type', 'Sent to', 'Started', 'Result' ].map( function ( t ) {
				return h( 'th', { scope: 'col', text: t } );
			} ) ) ] ),
			tbody,
		] ) );

		if ( data.pages > 1 ) {
			box.appendChild( h( 'nav', { class: 'dak-crm-pager', 'aria-label': 'Pages' }, [
				btn( 'Previous', 'dak-button-secondary', function () {
					loadEmails( data.page - 1 );
				}, { disabled: data.page <= 1 } ),
				h( 'span', { text: 'Page ' + data.page + ' of ' + data.pages } ),
				btn( 'Next', 'dak-button-secondary', function () {
					loadEmails( data.page + 1 );
				}, { disabled: data.page >= data.pages } ),
			] ) );
		}
	}

	function renderTemplateList( box, list ) {
		box.textContent = '';

		if ( ! list.length ) {
			box.appendChild( h( 'div', { class: 'dak-crm-empty', text: 'No templates yet. Templates save wording you send often, like a review-visit reminder.' } ) );
			return;
		}

		var tbody = h( 'tbody' );

		list.forEach( function ( t ) {
			var active = 'active' === t.status;

			tbody.appendChild( h( 'tr', { class: active ? '' : 'is-archived' }, [
				h( 'td', { 'data-label': 'Template', class: 'dak-col-primary' }, [
					h( 'button', { type: 'button', class: 'dak-crm-name', text: t.name, onclick: function ( e ) {
						openComposer( { mode: 'template', template: t }, e.currentTarget );
					} } ),
					h( 'span', { class: 'dak-cell-sub', text: t.subject } ),
					active ? null : h( 'span', { class: 'dak-crm-badge', text: 'Archived' } ),
				] ),
				h( 'td', { 'data-label': 'Type' }, [ purposePill( t.purpose ) ] ),
				h( 'td', { 'data-label': 'Last changed' }, [ h( 'span', { text: fmtDate( t.updated_at ) } ), t.updated_by ? h( 'span', { class: 'dak-cell-sub', text: 'by ' + t.updated_by } ) : null ] ),
				h( 'td', { 'data-label': 'Actions' }, [ h( 'span', { class: 'dak-crm-row-actions' }, [
					btn( 'Edit', 'dak-button-secondary', function ( e ) {
						openComposer( { mode: 'template', template: t }, e.currentTarget );
					}, { 'aria-label': 'Edit ' + t.name } ),
					btn( active ? 'Archive' : 'Restore', 'dak-button-secondary', function ( e ) {
						post( 'template_status', { template_id: t.id, active: active ? '0' : '1' }, e.currentTarget ).then( function ( res ) {
							if ( res.success ) {
								state.templates = res.data.templates;
								flash( res.data.message );
								renderTemplateList( box, state.templates );
							}
						} );
					}, { 'aria-label': ( active ? 'Archive ' : 'Restore ' ) + t.name } ),
				] ) ] ),
			] ) );
		} );

		box.appendChild( h( 'table', { class: 'dak-data-table dak-crm-table' }, [
			h( 'thead', {}, [ h( 'tr', {}, [ 'Template', 'Type', 'Last changed', 'Actions' ].map( function ( th ) {
				return h( 'th', { scope: 'col', text: th } );
			} ) ) ] ),
			tbody,
		] ) );
	}

	/* ------------------------------------------------------------------ */

	function start() {
		renderHeadActions();
		renderFilters();
		load();
		post( 'tasks', { who: 'mine' } ).then( function ( res ) {
			if ( res.success ) {
				updateTaskCount( res.data.tasks );
			}
		} );
	}

	if ( config.imported ) {
		start();
	} else {
		importExisting( start );
	}
}() );
