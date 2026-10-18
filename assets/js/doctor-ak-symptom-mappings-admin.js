/**
 * Doctor AK Portal — "Symptom mappings" screen (admin dashboard section and
 * the clinical reviewers' doctor-dashboard tab).
 *
 * Symptoms (with their follow-up questions) and safety rules share one
 * review lifecycle: Draft → Pending review → Reviewed → Active, or
 * Inactive. A live item keeps its last approved version on the patient
 * page while a revision is being reviewed.
 *
 * Draws everything from the state the server sends (dakSymptomMappings.state)
 * and redraws from the fresh state each action returns. Buttons are only
 * shown to people allowed to use them, but every permission is enforced on
 * the server (Symptom_Mappings_Handler / Clinical_Workflow).
 */
( function () {
	'use strict';

	var config = window.dakSymptomMappings;
	var root = document.querySelector( '[data-dak-sm]' );

	if ( ! config || ! root ) {
		return;
	}

	var state = config.state;
	var filters = { q: '', status: '', specialty: '' };
	var picked = {};
	var dialog = document.getElementById( 'dak-sm-dialog' );
	var lastFocus = null;

	var STATUS = {
		draft: { label: 'Draft', cls: 'dak-status-pill-is-neutral' },
		pending: { label: 'Pending review', cls: 'dak-status-pill-is-pending' },
		reviewed: { label: 'Reviewed', cls: 'dak-status-pill-is-checked-in' },
		active: { label: 'Active', cls: 'dak-status-pill-is-active' },
		inactive: { label: 'Inactive', cls: 'dak-status-pill-is-disabled' },
	};
	var SOURCE = { example: 'Example — not reviewed', legacy: 'From previous rules', import: 'Imported' };
	var TYPES = { single: 'One answer', multi: 'Several answers', yesno: 'Yes / No', number: 'Number' };
	var LEVELS = { emergency: 'Emergency', urgent: 'Urgent' };
	var ACTIONS = {
		create: 'Created',
		edit: 'Edited',
		notes: 'Notes changed',
		submit: 'Submitted for review',
		approve: 'Approved',
		'return': 'Returned for revision',
		activate: 'Activated',
		deactivate: 'Deactivated',
		archive: 'Moved to Inactive',
		restore: 'Restored to draft',
		'delete': 'Deleted',
		enable: 'Page switched on',
		disable: 'Page switched off',
		reviewer_add: 'Reviewer added',
		reviewer_remove: 'Reviewer removed',
	};

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

	function date( value ) {
		if ( ! value ) {
			return '';
		}

		var d = new Date( String( value ).replace( ' ', 'T' ) );

		return isNaN( d.getTime() ) ? value : d.toLocaleDateString( undefined, { day: 'numeric', month: 'short', year: 'numeric' } );
	}

	function dateTime( value ) {
		var d = new Date( String( value || '' ).replace( ' ', 'T' ) );

		return isNaN( d.getTime() ) ? String( value || '' ) : d.toLocaleString( undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' } );
	}

	var flashTimer = null;

	/**
	 * Shows the outcome of an action in a toast fixed to the bottom of the
	 * window, so it's seen wherever the button was. Errors stay until
	 * dismissed; confirmations fade after a few seconds.
	 */
	function flash( text, isError ) {
		var el = root.querySelector( '[data-sm-flash]' );

		clearTimeout( flashTimer );
		el.textContent = '';
		el.hidden = ! text;
		el.classList.toggle( 'is-error', !! isError );
		el.setAttribute( 'role', isError ? 'alert' : 'status' );

		if ( ! text ) {
			return;
		}

		el.appendChild( h( 'span', { text: text } ) );
		el.appendChild( h( 'button', { type: 'button', class: 'dak-sm-flash-close', 'aria-label': 'Dismiss message', text: '×', onclick: function () {
			flash( '' );
		} } ) );

		if ( ! isError ) {
			flashTimer = setTimeout( function () {
				flash( '' );
			}, 7000 );
		}
	}

	function post( op, fields, button ) {
		var body = new FormData();
		body.append( 'action', 'doctor_ak_symptom_mappings' );
		body.append( 'nonce', config.nonce );
		body.append( 'op', op );

		Object.keys( fields || {} ).forEach( function ( k ) {
			var v = fields[ k ];

			if ( Array.isArray( v ) ) {
				v.forEach( function ( item ) {
					body.append( k + '[]', item );
				} );
			} else {
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

				if ( res.data && res.data.state ) {
					state = res.data.state;
					render();
				}

				if ( res.data && res.data.message ) {
					flash( res.data.message + ( res.data.failed && res.data.failed.length ? ' Not changed: ' + res.data.failed.join( ' ' ) : '' ), ! res.success );
				}

				return res;
			} );
	}

	function specLabel( slug ) {
		return ( state.specialties && state.specialties[ slug ] ) || slug;
	}

	function symptomById( id ) {
		return state.mappings.filter( function ( m ) {
			return m.id === Number( id );
		} )[ 0 ] || null;
	}

	function pill( status ) {
		var s = STATUS[ status ] || STATUS.draft;
		return h( 'span', { class: 'dak-status-pill ' + s.cls, text: s.label } );
	}

	/** "Live v2" / "Live · revision in Draft" badges. */
	function liveBadge( item ) {
		if ( ! item.is_live ) {
			return null;
		}

		return h( 'span', { class: 'dak-sm-live' + ( item.pending_changes ? ' has-revision' : '' ), text: item.pending_changes ? 'Live v' + item.published_version + ' · revision not live yet' : 'Live v' + item.published_version } );
	}

	/* ------------------------------------------------------------------
	 * Status
	 * ---------------------------------------------------------------- */

	function counts( items ) {
		var c = { draft: 0, pending: 0, reviewed: 0, active: 0, inactive: 0, live: 0 };

		items.forEach( function ( m ) {
			c[ m.status ] = ( c[ m.status ] || 0 ) + 1;
			c.live += m.is_live ? 1 : 0;
		} );

		return c;
	}

	function renderStatus() {
		var box = root.querySelector( '[data-sm-status]' );
		var c = counts( state.mappings );
		var r = counts( state.rules || [] );

		box.textContent = '';
		box.appendChild( h( 'p', {}, [
			h( 'span', { class: 'dak-status-pill ' + ( state.isPublic ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral' ), text: state.isPublic ? 'Available to patients' : 'Hidden from patients' } ),
			' Symptoms: ' + c.live + ' live · ' + c.pending + ' pending review · ' + c.reviewed + ' reviewed · ' + c.draft + ' draft · ' + c.inactive + ' inactive.',
			' Safety rules: ' + r.live + ' live · ' + ( r.pending + r.reviewed + r.draft ) + ' in review or draft.',
		] ) );

		if ( c.pending + r.pending && state.can.review ) {
			box.appendChild( h( 'p', { class: 'dak-sm-alert is-info', text: ( c.pending + r.pending ) + ' item(s) are waiting for clinical review.' } ) );
		}

		if ( ! state.reviewerCount ) {
			box.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'There are no clinical reviewers yet, so nothing can be approved or activated. An administrator can add them under Clinical reviewers.' } ) );
		}

		if ( state.missing.length ) {
			box.appendChild( h( 'p', { class: 'dak-sm-help', text: 'Before patients can see the page:' } ) );
			box.appendChild( h( 'ul', { class: 'dak-sm-missing' }, state.missing.map( function ( m ) {
				return h( 'li', { text: m } );
			} ) ) );
		}

		if ( state.can.admin ) {
			var id = 'dak-sm-toggle';
			var toggle = h( 'input', { type: 'checkbox', id: id, checked: state.enabled, disabled: ! state.enabled && state.missing.length > 0 } );

			toggle.addEventListener( 'change', function () {
				if ( toggle.checked && ! window.confirm( 'Show “Find a doctor by symptoms” to patients, with the live symptoms and safety rules?' ) ) { // eslint-disable-line no-alert
					toggle.checked = false;
					return;
				}

				post( 'toggle', { enabled: toggle.checked ? '1' : '0' } );
			} );

			box.appendChild( h( 'label', { class: 'dak-sm-check', for: id }, [ toggle, h( 'span', { text: 'Show “Find a doctor by symptoms” to patients' } ) ] ) );
			box.appendChild( h( 'p', { class: 'dak-sm-help', text: 'When on, the page and its links (home page, header menu) appear for everyone. Turning it off hides them straight away.' } ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Emergency notice
	 * ---------------------------------------------------------------- */

	function renderNotice() {
		var box = root.querySelector( '[data-sm-notice]' );
		var n = state.notice;
		var approved = 'approved' === n.status;
		var area = h( 'textarea', { id: 'dak-sm-notice-text', class: 'dak-sm-input', rows: '3', value: n.text, readonly: ! state.can.manage } );

		box.textContent = '';
		box.appendChild( h( 'label', { class: 'dak-sm-label', for: 'dak-sm-notice-text', text: 'Notice text' } ) );
		box.appendChild( area );
		box.appendChild( h( 'p', { class: 'dak-sm-meta' }, [
			h( 'span', { class: 'dak-status-pill ' + ( approved ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral' ), text: approved ? 'Approved' : ( n.approved_text ? 'Edited — awaiting approval' : 'Draft' ) } ),
			n.updated_at ? ' Last edited by ' + ( n.updated_by_name || 'unknown' ) + ', ' + date( n.updated_at ) + '.' : ' Suggested wording — not saved or approved yet.',
			n.approved_at ? ' Approved by ' + ( n.approved_by_name || 'unknown' ) + ', ' + date( n.approved_at ) + '.' : '',
		] ) );

		if ( ! approved && n.approved_text ) {
			box.appendChild( h( 'div', { class: 'dak-sm-live-copy' }, [
				h( 'strong', { text: 'Patients currently see: ' } ),
				n.approved_text,
			] ) );
		}

		var actions = h( 'div', { class: 'dak-sm-actions' } );

		if ( state.can.manage ) {
			actions.appendChild( btn( 'Save notice', 'dak-button-secondary', function ( e ) {
				post( 'notice_save', { text: area.value }, e.currentTarget );
			} ) );
		}

		if ( state.can.review && ! approved ) {
			actions.appendChild( btn( 'Approve notice', 'dak-button-primary', function ( e ) {
				post( 'notice_approve', {}, e.currentTarget );
			}, { disabled: ! n.updated_at } ) );

			if ( ! n.updated_at ) {
				actions.appendChild( h( 'span', { class: 'dak-sm-help', text: 'Save the notice first, then approve it.' } ) );
			}
		}

		actions.appendChild( btn( 'History', 'dak-button-secondary', function () {
			openHistory( 'notice', 0, 'Emergency notice' );
		} ) );

		box.appendChild( actions );
	}

	/* ------------------------------------------------------------------
	 * Lifecycle buttons (shared by symptoms and rules)
	 * ---------------------------------------------------------------- */

	/**
	 * @param {Object} item   Symptom or rule row.
	 * @param {string} prefix '' for symptoms, 'rule_' for rules.
	 * @param {string} name   Name for labels.
	 * @return {Array} Buttons.
	 */
	function lifecycleButtons( item, prefix, name ) {
		var out = [];
		var invalid = item.problems && item.problems.length > 0;
		var go = function ( action ) {
			return function ( e ) {
				post( prefix + action, { id: item.id }, e.currentTarget );
			};
		};

		if ( state.can.manage && 'draft' === item.status ) {
			out.push( btn( 'Submit for review', 'dak-button-primary', go( 'submit' ), {
				disabled: invalid,
				'aria-label': 'Submit ' + name + ' for review',
			} ) );

			if ( invalid ) {
				out.push( h( 'span', { class: 'dak-sm-blocked', text: 'Fix the problems shown before submitting (Edit).' } ) );
			}
		}

		if ( state.can.review ) {
			if ( 'pending' === item.status ) {
				out.push( btn( 'Approve', 'dak-button-primary', go( 'approve' ), {
					disabled: invalid,
					'aria-label': 'Approve ' + name,
				} ) );

				if ( invalid ) {
					out.push( h( 'span', { class: 'dak-sm-blocked', text: 'Fix the problems shown before approving (Edit).' } ) );
				}
			}

			if ( 'pending' === item.status || 'reviewed' === item.status ) {
				out.push( btn( 'Return for revision', 'dak-button-secondary', function () {
					openReturn( item, prefix, name );
				}, { 'aria-label': 'Return ' + name + ' for revision' } ) );
			}

			if ( 'reviewed' === item.status ) {
				out.push( btn( item.is_live ? 'Activate this version' : 'Activate', 'dak-button-primary', go( 'activate' ), { 'aria-label': 'Activate ' + name } ) );
			}

			if ( 'inactive' === item.status && item.published && ! item.pending_changes ) {
				out.push( btn( 'Reactivate', 'dak-button-secondary', go( 'activate' ), { 'aria-label': 'Reactivate ' + name } ) );
			}

			if ( item.is_live ) {
				out.push( btn( 'Deactivate', 'dak-button-secondary', function ( e ) {
					if ( window.confirm( 'Take “' + name + '” off the patient page now?' ) ) { // eslint-disable-line no-alert
						go( 'deactivate' )( e );
					}
				}, { 'aria-label': 'Deactivate ' + name } ) );
			}
		}

		if ( state.can.manage && ! item.is_live && 'inactive' !== item.status ) {
			out.push( btn( 'Archive', 'dak-button-secondary', go( 'archive' ), { 'aria-label': 'Archive ' + name } ) );
		}

		if ( state.can.manage && 'inactive' === item.status && ! item.is_live ) {
			out.push( btn( 'Restore to draft', 'dak-button-secondary', go( 'restore' ), { 'aria-label': 'Restore ' + name + ' to draft' } ) );
		}

		return out;
	}

	function openReturn( item, prefix, name ) {
		var form = h( 'form', { class: 'dak-sm-form', novalidate: true } );
		var reason = h( 'textarea', { id: 'dak-sm-reason', class: 'dak-sm-input', rows: '4', maxlength: '500', 'aria-describedby': 'dak-sm-reason-help' } );
		var save = h( 'button', { type: 'submit', class: 'dak-button dak-button-primary', text: 'Return for revision' } );

		form.appendChild( field( 'What needs to change?', reason, 'The author sees this on the item. It is kept in the history.', 'reason' ) );
		form.appendChild( h( 'div', { class: 'dak-sm-actions' }, [ save, btn( 'Cancel', 'dak-button-secondary', closeDialog ) ] ) );
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( ! reason.value.trim() ) {
				showFieldError( form, 'reason', 'Say what needs to change.' );
				reason.focus();
				return;
			}

			post( prefix + 'return', { id: item.id, reason: reason.value }, save ).then( function ( res ) {
				if ( res.success ) {
					closeDialog();
				}
			} );
		} );

		openDialog( 'Return “' + name + '” for revision', form );
	}

	/* ------------------------------------------------------------------
	 * Symptom list
	 * ---------------------------------------------------------------- */

	function matchesFilter( m ) {
		if ( 'live' === filters.status ) {
			if ( ! m.is_live ) {
				return false;
			}
		} else if ( filters.status && m.status !== filters.status ) {
			return false;
		}

		if ( filters.specialty && m.specialties.indexOf( filters.specialty ) === -1 ) {
			return false;
		}

		if ( ! filters.q ) {
			return true;
		}

		var phrases = m.synonyms.slice();

		Object.keys( m.phrases || {} ).forEach( function ( code ) {
			phrases = phrases.concat( m.phrases[ code ] );
		} );

		var hay = [ m.name, m.slug ].concat( phrases, m.specialty_labels ).join( ' ' ).toLowerCase();

		return hay.indexOf( filters.q.toLowerCase() ) !== -1;
	}

	function renderToolbar() {
		var bar = root.querySelector( '[data-sm-toolbar]' );

		if ( bar.childNodes.length ) {
			return; // Keep focus/typing in the search box across redraws.
		}

		var search = h( 'input', { type: 'search', class: 'dak-sm-input dak-sm-search', placeholder: 'Search symptoms, phrases, specialties', 'aria-label': 'Search symptoms' } );
		search.addEventListener( 'input', function () {
			filters.q = search.value.trim();
			renderList();
		} );

		var status = h( 'select', { class: 'dak-sm-input', 'aria-label': 'Filter by status' }, [
			h( 'option', { value: '', text: 'All statuses' } ),
			h( 'option', { value: 'live', text: 'Live on the page' } ),
		].concat( Object.keys( STATUS ).map( function ( k ) {
			return h( 'option', { value: k, text: STATUS[ k ].label } );
		} ) ) );
		status.addEventListener( 'change', function () {
			filters.status = status.value;
			renderList();
		} );

		var specialty = h( 'select', { class: 'dak-sm-input', 'aria-label': 'Filter by specialty' }, [ h( 'option', { value: '', text: 'All specialties' } ) ].concat( Object.keys( state.specialties ).map( function ( slug ) {
			return h( 'option', { value: slug, text: state.specialties[ slug ] } );
		} ) ) );
		specialty.addEventListener( 'change', function () {
			filters.specialty = specialty.value;
			renderList();
		} );

		bar.appendChild( search );
		bar.appendChild( status );
		bar.appendChild( specialty );

		if ( state.can.manage ) {
			bar.appendChild( btn( '+ Add symptom', 'dak-button-primary', function () {
				openEditor( null );
			} ) );
			bar.appendChild( btn( 'Import CSV', 'dak-button-secondary', openImport ) );
			bar.appendChild( btn( 'Export CSV', 'dak-button-secondary', function ( e ) {
				post( 'export', {}, e.currentTarget ).then( function ( res ) {
					if ( res.success && res.data && 'string' === typeof res.data.csv ) {
						download( res.data.filename || 'symptom-mappings.csv', res.data.csv );
					}
				} );
			} ) );
			bar.appendChild( btn( 'Load example drafts', 'dak-button-secondary', function ( e ) {
				if ( window.confirm( 'Add the bundled EXAMPLE symptoms as drafts? They are not clinically reviewed; each must be checked by a reviewer before use. Existing symptoms are skipped.' ) ) { // eslint-disable-line no-alert
					post( 'examples', {}, e.currentTarget );
				}
			} ) );
		}
	}

	function download( name, text ) {
		var blob = new Blob( [ '﻿' + text ], { type: 'text/csv;charset=utf-8' } );
		var url = URL.createObjectURL( blob );
		var a = h( 'a', { href: url, download: name } );
		document.body.appendChild( a );
		a.click();
		a.remove();
		setTimeout( function () {
			URL.revokeObjectURL( url );
		}, 1000 );
	}

	/** Where a symptom came from; examples are flagged until reviewed. */
	function sourceBadge( m ) {
		if ( 'example' === m.source && 'draft' !== m.status ) {
			return h( 'span', { class: 'dak-sm-source', text: 'From examples' } );
		}

		return SOURCE[ m.source ] ? h( 'span', { class: 'dak-sm-source is-' + m.source, text: SOURCE[ m.source ] } ) : null;
	}

	function previewUrl( id ) {
		if ( ! config.pageUrl ) {
			return '';
		}

		var url = config.pageUrl + ( config.pageUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'sc_preview=1';

		return id ? url + '&sc_symptom=' + encodeURIComponent( id ) : url;
	}

	function renderList() {
		var box = root.querySelector( '[data-sm-list]' );
		var rows = state.mappings.filter( matchesFilter );
		var ids = state.mappings.map( function ( m ) {
			return m.id;
		} );

		Object.keys( picked ).forEach( function ( id ) {
			if ( ids.indexOf( Number( id ) ) === -1 ) {
				delete picked[ id ];
			}
		} );

		box.textContent = '';

		if ( ! state.mappings.length ) {
			box.appendChild( h( 'div', { class: 'dak-sm-empty', text: 'No symptoms yet. Add one, import a reviewed CSV, or load the example drafts to start from.' } ) );
			return;
		}

		var pickedIds = Object.keys( picked ).map( Number );

		if ( pickedIds.length ) {
			var bulk = h( 'div', { class: 'dak-sm-bulk' }, [ h( 'strong', { text: pickedIds.length + ' selected' } ) ] );
			var bulkPost = function ( action, label ) {
				return btn( label, 'dak-button-secondary', function ( e ) {
					post( 'bulk', { bulk_action: action, ids: pickedIds }, e.currentTarget );
				} );
			};

			bulk.appendChild( bulkPost( 'submit', 'Submit for review' ) );

			if ( state.can.review ) {
				bulk.appendChild( bulkPost( 'approve', 'Approve' ) );
				bulk.appendChild( bulkPost( 'activate', 'Activate' ) );
			}

			bulk.appendChild( bulkPost( 'archive', 'Archive' ) );

			if ( state.can.admin ) {
				bulk.appendChild( btn( 'Delete', 'dak-button-danger-outline', function ( e ) {
					if ( window.confirm( 'Delete ' + pickedIds.length + ' symptom(s)? Live symptoms are skipped. Their history stays in the audit log.' ) ) { // eslint-disable-line no-alert
						post( 'bulk', { bulk_action: 'delete', ids: pickedIds }, e.currentTarget ).then( function () {
							picked = {};
							renderList();
						} );
					}
				} ) );
			}

			bulk.appendChild( btn( 'Clear selection', 'dak-button-secondary', function () {
				picked = {};
				renderList();
			} ) );
			box.appendChild( bulk );
		}

		if ( ! rows.length ) {
			box.appendChild( h( 'div', { class: 'dak-sm-empty', text: 'No symptoms match these filters.' } ) );
			return;
		}

		var table = h( 'table', { class: 'dak-sm-table' } );
		table.appendChild( h( 'thead', {}, [ h( 'tr', {}, [
			h( 'th', { scope: 'col' }, [ h( 'span', { class: 'dak-visually-hidden', text: 'Select' } ) ] ),
			h( 'th', { scope: 'col', text: 'Symptom' } ),
			h( 'th', { scope: 'col', text: 'Phrases' } ),
			h( 'th', { scope: 'col', text: 'Specialties' } ),
			h( 'th', { scope: 'col', text: 'Questions' } ),
			h( 'th', { scope: 'col', text: 'Status' } ),
			h( 'th', { scope: 'col', text: 'Updated / reviewed' } ),
			h( 'th', { scope: 'col' }, [ h( 'span', { class: 'dak-visually-hidden', text: 'Actions' } ) ] ),
		] ) ] ) );

		var body = h( 'tbody' );

		rows.forEach( function ( m ) {
			var check = h( 'input', { type: 'checkbox', checked: !! picked[ m.id ], 'aria-label': 'Select ' + m.name } );
			check.addEventListener( 'change', function () {
				if ( check.checked ) {
					picked[ m.id ] = true;
				} else {
					delete picked[ m.id ];
				}

				renderList();
			} );

			var actions = h( 'div', { class: 'dak-sm-row-actions' } );

			if ( state.can.manage ) {
				actions.appendChild( btn( 'Edit', 'dak-button-secondary', function () {
					openEditor( m );
				}, { 'aria-label': 'Edit ' + m.name } ) );
			}

			lifecycleButtons( m, '', m.name ).forEach( function ( b ) {
				actions.appendChild( b );
			} );

			var preview = previewUrl( m.id );

			if ( preview ) {
				actions.appendChild( h( 'a', { class: 'dak-button dak-button-secondary dak-button-sm', href: preview, target: '_blank', rel: 'noopener', text: 'Preview', 'aria-label': 'Preview ' + m.name + ' on the patient page (opens in a new tab)' } ) );
			}

			actions.appendChild( btn( 'History', 'dak-button-secondary', function () {
				openHistory( 'symptom', m.id, m.name );
			}, { 'aria-label': 'History of ' + m.name } ) );

			if ( state.can.admin && ! m.is_live ) {
				actions.appendChild( btn( 'Delete', 'dak-button-danger-outline', function ( e ) {
					if ( window.confirm( 'Delete “' + m.name + '”? Its history stays in the audit log.' ) ) { // eslint-disable-line no-alert
						post( 'delete', { id: m.id }, e.currentTarget );
					}
				}, { 'aria-label': 'Delete ' + m.name } ) );
			}

			var phrases = m.synonyms.slice( 0, 3 ).join( ', ' ) + ( m.synonyms.length > 3 ? ' +' + ( m.synonyms.length - 3 ) + ' more' : '' );
			var other = Object.keys( state.languages || {} ).map( function ( code ) {
				var n = ( m.phrases && m.phrases[ code ] || [] ).length;
				return n ? state.languages[ code ] + ': ' + n : '';
			} ).filter( Boolean ).join( ' · ' );

			body.appendChild( h( 'tr', {}, [
				h( 'td', { 'data-label': 'Select' }, [ check ] ),
				h( 'td', { class: 'dak-sm-name', 'data-label': 'Symptom' }, [
					h( 'strong', { text: m.name } ),
					h( 'span', { class: 'dak-sm-slug', text: m.slug } ),
					sourceBadge( m ),
				] ),
				h( 'td', { 'data-label': 'Phrases' }, [
					phrases || '—',
					other ? h( 'span', { class: 'dak-sm-help', text: other } ) : null,
				] ),
				h( 'td', { 'data-label': 'Specialties' }, [
					m.specialty_labels.join( ', ' ),
					m.invalid_specialties.length ? h( 'span', { class: 'dak-sm-warn', text: 'Unknown specialty: ' + m.invalid_specialties.join( ', ' ) } ) : null,
				] ),
				h( 'td', { 'data-label': 'Questions', text: m.questions.length ? String( m.questions.length ) : '—' } ),
				h( 'td', { 'data-label': 'Status' }, [
					pill( m.status ),
					liveBadge( m ),
					m.return_reason ? h( 'span', { class: 'dak-sm-warn', text: 'Returned: ' + m.return_reason } ) : null,
					m.problems.length ? h( 'span', { class: 'dak-sm-warn', text: m.problems.join( ' ' ) } ) : null,
				] ),
				h( 'td', { class: 'dak-sm-when', 'data-label': 'Updated / reviewed' }, [
					h( 'span', { text: date( m.updated_at ) + ( m.updated_by_name ? ' · ' + m.updated_by_name : '' ) } ),
					m.reviewed_by_name ? h( 'span', { text: 'Reviewed ' + date( m.reviewed_at ) + ' · ' + m.reviewed_by_name } ) : null,
					m.is_live && m.activated_by_name ? h( 'span', { text: 'Activated ' + date( m.activated_at ) + ' · ' + m.activated_by_name } ) : null,
				] ),
				h( 'td', { 'data-label': 'Actions' }, [ actions ] ),
			] ) );
		} );

		table.appendChild( body );
		box.appendChild( h( 'div', { class: 'dak-sm-table-wrap' }, [ table ] ) );
	}

	/* ------------------------------------------------------------------
	 * Safety rules
	 * ---------------------------------------------------------------- */

	function triggerText( t ) {
		var s = symptomById( t.symptom );
		var name = s ? s.name : 'Symptom #' + t.symptom;

		if ( ! t.question ) {
			return name + ' is selected';
		}

		var q = s ? findQuestion( s, t.question ) : null;

		if ( ! q ) {
			return name + ': question “' + t.question + '” (missing)';
		}

		if ( 'number' === q.type ) {
			var range = [];

			if ( null !== t.min && undefined !== t.min ) {
				range.push( 'at least ' + t.min );
			}

			if ( null !== t.max && undefined !== t.max ) {
				range.push( 'at most ' + t.max );
			}

			return name + ': “' + q.text + '” is ' + range.join( ' and ' ) + ( q.number && q.number.unit ? ' ' + q.number.unit : '' );
		}

		var labels = ( q.options || [] ).filter( function ( o ) {
			return ( t.answers || [] ).indexOf( o.id ) !== -1;
		} ).map( function ( o ) {
			return '“' + o.label + '”';
		} );

		return name + ': “' + q.text + '” answered ' + labels.join( ' or ' );
	}

	/** A symptom's question in its working copy, else its live copy. */
	function findQuestion( s, qid ) {
		var lists = [ s.questions || [], s.published && s.published.questions || [] ];

		for ( var i = 0; i < lists.length; i++ ) {
			for ( var j = 0; j < lists[ i ].length; j++ ) {
				if ( lists[ i ][ j ].id === qid ) {
					return lists[ i ][ j ];
				}
			}
		}

		return null;
	}

	function renderRules() {
		var box = root.querySelector( '[data-sm-rules]' );

		if ( ! box ) {
			return;
		}

		box.textContent = '';

		var head = h( 'div', { class: 'dak-sm-inline' } );

		if ( state.can.manage ) {
			head.appendChild( btn( '+ Add safety rule', 'dak-button-primary', function () {
				openRuleEditor( null );
			} ) );
		}

		box.appendChild( head );

		var items = state.rules || [];

		if ( ! items.length ) {
			box.appendChild( h( 'div', { class: 'dak-sm-empty', text: 'No safety rules yet. Without approved rules, the page shows only the general emergency notice — it doesn’t react to any answer.' } ) );
			return;
		}

		var listEl = h( 'div', { class: 'dak-sm-rules' } );

		items.forEach( function ( r ) {
			var actions = h( 'div', { class: 'dak-sm-row-actions' } );

			if ( state.can.manage ) {
				actions.appendChild( btn( 'Edit', 'dak-button-secondary', function () {
					openRuleEditor( r );
				}, { 'aria-label': 'Edit ' + r.title } ) );
			}

			lifecycleButtons( r, 'rule_', r.title ).forEach( function ( b ) {
				actions.appendChild( b );
			} );

			actions.appendChild( btn( 'History', 'dak-button-secondary', function () {
				openHistory( 'rule', r.id, r.title );
			}, { 'aria-label': 'History of ' + r.title } ) );

			if ( state.can.admin && ! r.is_live ) {
				actions.appendChild( btn( 'Delete', 'dak-button-danger-outline', function ( e ) {
					if ( window.confirm( 'Delete the safety rule “' + r.title + '”? Its history stays in the audit log.' ) ) { // eslint-disable-line no-alert
						post( 'rule_delete', { id: r.id }, e.currentTarget );
					}
				}, { 'aria-label': 'Delete ' + r.title } ) );
			}

			listEl.appendChild( h( 'article', { class: 'dak-sm-rule is-' + r.level }, [
				h( 'div', { class: 'dak-sm-rule-head' }, [
					h( 'span', { class: 'dak-sm-level is-' + r.level, text: LEVELS[ r.level ] || r.level } ),
					h( 'h3', { text: r.title } ),
					pill( r.status ),
					liveBadge( r ),
				] ),
				h( 'p', { class: 'dak-sm-rule-msg', text: r.message } ),
				h( 'p', { class: 'dak-sm-help', text: r.interrupt ? 'Stops the remaining questions when shown.' : 'Shown alongside the questions (doesn’t stop them).' } ),
				h( 'ul', { class: 'dak-sm-triggers', 'aria-label': 'Shown when' }, r.triggers.map( function ( t ) {
					return h( 'li', { text: 'When ' + triggerText( t ) } );
				} ) ),
				r.return_reason ? h( 'p', { class: 'dak-sm-warn', text: 'Returned: ' + r.return_reason } ) : null,
				r.problems.length ? h( 'p', { class: 'dak-sm-warn', text: r.problems.join( ' ' ) } ) : null,
				r.warnings.length ? h( 'p', { class: 'dak-sm-note', text: r.warnings.join( ' ' ) } ) : null,
				h( 'p', { class: 'dak-sm-meta', text: 'Edited ' + date( r.updated_at ) + ( r.updated_by_name ? ' by ' + r.updated_by_name : '' ) + ( r.reviewed_by_name ? ' · reviewed by ' + r.reviewed_by_name : '' ) } ),
				actions,
			] ) );
		} );

		box.appendChild( listEl );
	}

	/* ------------------------------------------------------------------
	 * Dialog helpers
	 * ---------------------------------------------------------------- */

	// The button that opened a dialog gets focus back when it closes (not
	// every browser focuses a button when it's clicked).
	var lastTrigger = null;

	root.addEventListener( 'click', function ( event ) {
		lastTrigger = event.target.closest( 'button' );
	}, true );

	function openDialog( title, content ) {
		lastFocus = lastTrigger || document.activeElement;
		dialog.querySelector( '[data-sm-dialog-title]' ).textContent = title;
		var body = dialog.querySelector( '[data-sm-dialog-body]' );
		body.textContent = '';
		body.appendChild( content );
		dialog.classList.add( 'is-open' );
		dialog.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'dak-modal-open' );

		var first = body.querySelector( 'input, textarea, select' );
		( first || dialog.querySelector( '[role="dialog"]' ) ).focus();
	}

	function closeDialog() {
		dialog.classList.remove( 'is-open' );
		dialog.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'dak-modal-open' );

		if ( lastFocus && document.body.contains( lastFocus ) ) {
			lastFocus.focus();
		}
	}

	dialog.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '[data-sm-close]' ) ) {
			closeDialog();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && dialog.classList.contains( 'is-open' ) ) {
			closeDialog();
		}
	} );

	function field( label, control, help, errorKey ) {
		return h( 'div', { class: 'dak-sm-field' }, [
			h( 'label', { class: 'dak-sm-label', for: control.id, text: label } ),
			help ? h( 'p', { class: 'dak-sm-help', id: control.id + '-help', text: help } ) : null,
			control,
			h( 'p', { class: 'dak-sm-error', 'data-error': errorKey || '', hidden: true } ),
		] );
	}

	function showFieldError( form, key, text ) {
		var el = form.querySelector( '[data-error="' + key + '"]' );

		if ( el ) {
			el.textContent = text;
			el.hidden = false;
		}

		return el;
	}

	function showErrors( form, errors, fallback ) {
		var first = null;

		form.querySelectorAll( '[data-error]' ).forEach( function ( el ) {
			el.hidden = true;
		} );

		Object.keys( errors || {} ).forEach( function ( k ) {
			var el = showFieldError( form, k, errors[ k ] ) || showFieldError( form, fallback, errors[ k ] );

			if ( el ) {
				first = first || el.parentNode.querySelector( 'input, textarea, select' ) || el;
			}
		} );

		if ( first ) {
			if ( ! first.matches( 'input, textarea, select' ) ) {
				first.setAttribute( 'tabindex', '-1' );
			}

			first.focus();
		}
	}

	/** Checkbox list of the site's specialties, with a filter. */
	function specialtyPicker( idPrefix, chosen, label ) {
		var wrap = h( 'div', { class: 'dak-sm-spec-wrap' } );
		var filter = h( 'input', { type: 'search', id: idPrefix + '-filter', class: 'dak-sm-input', placeholder: 'Filter specialties', 'aria-controls': idPrefix + '-list', 'aria-label': 'Filter ' + label } );
		var listEl = h( 'div', { class: 'dak-sm-spec-list', id: idPrefix + '-list', role: 'group', 'aria-label': label } );

		Object.keys( state.specialties ).forEach( function ( slug ) {
			var id = idPrefix + '-' + slug;
			listEl.appendChild( h( 'label', { class: 'dak-sm-spec', for: id, 'data-name': state.specialties[ slug ].toLowerCase() }, [
				h( 'input', { type: 'checkbox', id: id, value: slug, checked: chosen.indexOf( slug ) !== -1 } ),
				h( 'span', { text: state.specialties[ slug ] } ),
			] ) );
		} );

		filter.addEventListener( 'input', function () {
			var q = filter.value.toLowerCase();
			Array.prototype.forEach.call( listEl.children, function ( l ) {
				l.hidden = q && l.getAttribute( 'data-name' ).indexOf( q ) === -1;
			} );
		} );

		wrap.appendChild( filter );
		wrap.appendChild( listEl );
		wrap.value = function () {
			return Array.prototype.filter.call( listEl.querySelectorAll( 'input' ), function ( b ) {
				return b.checked;
			} ).map( function ( b ) {
				return b.value;
			} );
		};

		return wrap;
	}

	/* ------------------------------------------------------------------
	 * Symptom editor (with follow-up questions)
	 * ---------------------------------------------------------------- */

	function clone( v ) {
		return JSON.parse( JSON.stringify( v ) );
	}

	function nextId( prefix, used ) {
		var n = 1;

		while ( used.indexOf( prefix + n ) !== -1 ) {
			n += 1;
		}

		return prefix + n;
	}

	function openEditor( m ) {
		var form = h( 'form', { class: 'dak-sm-form', novalidate: true } );
		var name = h( 'input', { type: 'text', id: 'dak-sm-name', class: 'dak-sm-input', value: m ? m.name : '', maxlength: '80', 'aria-describedby': 'dak-sm-name-help' } );
		var slug = h( 'input', { type: 'text', id: 'dak-sm-slug', class: 'dak-sm-input dak-sm-mono', value: m ? m.slug : '', maxlength: '80', 'aria-describedby': 'dak-sm-slug-help' } );
		var desc = h( 'textarea', { id: 'dak-sm-description', class: 'dak-sm-input', rows: '2', maxlength: '500', value: m ? m.description : '', 'aria-describedby': 'dak-sm-description-help' } );
		var syn = h( 'textarea', { id: 'dak-sm-synonyms', class: 'dak-sm-input', rows: '4', value: m ? m.synonyms.join( '\n' ) : '', 'aria-describedby': 'dak-sm-synonyms-help' } );
		var notes = h( 'textarea', { id: 'dak-sm-notes', class: 'dak-sm-input', rows: '2', value: m ? m.notes : '' } );
		var langs = {};
		var questions = m ? clone( m.questions ) : [];

		if ( m && m.return_reason ) {
			form.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'Returned for revision: ' + m.return_reason } ) );
		}

		if ( m && m.is_live ) {
			form.appendChild( h( 'p', { class: 'dak-sm-alert is-info', text: 'Live version ' + m.published_version + ' stays on the patient page while you edit. Saving a change creates a draft revision; it goes live only after it’s submitted, approved and activated.' } ) );
		} else if ( m && 'draft' !== m.status ) {
			form.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'This symptom is ' + STATUS[ m.status ].label.toLowerCase() + '. Saving a change to its clinical content returns it to Draft for a new review.' } ) );
		}

		if ( m && 'example' === m.source ) {
			form.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'This is an example written by the software team, not clinically reviewed.' } ) );
		}

		form.appendChild( field( 'Symptom name', name, 'How it appears to patients, e.g. “Headache”.', 'name' ) );
		form.appendChild( field( 'Identifier', slug, 'Unique, letters, numbers and hyphens. Left blank, it’s made from the name.', 'slug' ) );
		form.appendChild( field( 'Patient guidance (optional)', desc, 'A short, clinician-approved line shown when the patient picks this symptom. Don’t diagnose or reassure.', 'description' ) );
		form.appendChild( field( 'English alternative phrases', syn, 'Other ways patients describe it — one per line. Include common misspellings.', 'synonyms' ) );

		Object.keys( state.languages || {} ).forEach( function ( code ) {
			var area = h( 'textarea', { id: 'dak-sm-phrases-' + code, class: 'dak-sm-input', rows: '3', value: m && m.phrases && m.phrases[ code ] ? m.phrases[ code ].join( '\n' ) : '', 'aria-describedby': 'dak-sm-phrases-' + code + '-help' } );
			langs[ code ] = area;
			form.appendChild( field( state.languages[ code ] + ' phrases', area, 'One per line, e.g. “sar dard” for headache.', 'phrases_' + code ) );
		} );

		var specs = specialtyPicker( 'dak-sm-spec', m ? m.specialties : [], 'Related specialties' );

		form.appendChild( h( 'div', { class: 'dak-sm-field' }, [
			h( 'label', { class: 'dak-sm-label', for: 'dak-sm-spec-filter', text: 'Related specialties' } ),
			h( 'p', { class: 'dak-sm-help', text: 'Which kinds of doctor patients with this symptom are shown. Choose at least one.' } ),
			specs,
			h( 'p', { class: 'dak-sm-error', 'data-error': 'specialties', hidden: true } ),
		] ) );

		var qBox = h( 'div', { class: 'dak-sm-questions' } );

		form.appendChild( h( 'div', { class: 'dak-sm-field' }, [
			h( 'h3', { class: 'dak-sm-label', text: 'Follow-up questions (optional)' } ),
			h( 'p', { class: 'dak-sm-help', text: 'Asked one at a time, in this order, after the patient picks this symptom. Write them with a clinician. They never produce a diagnosis or score; an answer can only show a reviewed safety rule or add specialties you choose.' } ),
			qBox,
			h( 'p', { class: 'dak-sm-error', 'data-error': 'questions', hidden: true } ),
		] ) );

		function renderQuestionsEditor() {
			qBox.textContent = '';

			questions.forEach( function ( q, i ) {
				qBox.appendChild( questionCard( q, i ) );
			} );

			if ( questions.length < ( state.limits && state.limits.questions || 10 ) ) {
				qBox.appendChild( btn( '+ Add question', 'dak-button-secondary', function () {
					var used = questions.map( function ( x ) {
						return x.id;
					} );
					questions.push( {
						id: nextId( 'q', used ),
						text: '',
						type: 'single',
						required: true,
						explanation: '',
						affects: { navigation: true, safety: false, matching: false },
						options: [ { id: 'a1', label: '', specialties: [] }, { id: 'a2', label: '', specialties: [] } ],
						number: null,
						show_if: null,
					} );
					renderQuestionsEditor();
					var inputs = qBox.querySelectorAll( '.dak-sm-qcard' );
					var last = inputs[ inputs.length - 1 ];

					if ( last ) {
						last.querySelector( 'input' ).focus();
					}
				} ) );
			}
		}

		function questionCard( q, i ) {
			var base = 'dak-sm-q' + i;
			var card = h( 'fieldset', { class: 'dak-sm-qcard' } );
			var text = h( 'input', { type: 'text', id: base + '-text', class: 'dak-sm-input', value: q.text, maxlength: '200' } );
			var type = h( 'select', { id: base + '-type', class: 'dak-sm-input' }, Object.keys( TYPES ).map( function ( k ) {
				return h( 'option', { value: k, text: TYPES[ k ], selected: k === q.type } );
			} ) );
			var required = h( 'input', { type: 'checkbox', id: base + '-req', checked: q.required } );
			var expl = h( 'textarea', { id: base + '-expl', class: 'dak-sm-input', rows: '2', maxlength: '400', value: q.explanation || '' } );

			text.addEventListener( 'input', function () {
				q.text = text.value;
			} );
			expl.addEventListener( 'input', function () {
				q.explanation = expl.value;
			} );
			required.addEventListener( 'change', function () {
				q.required = required.checked;
			} );
			type.addEventListener( 'change', function () {
				q.type = type.value;

				if ( 'number' === q.type ) {
					q.options = [];
					q.number = q.number || { min: null, max: null, unit: '' };
					q.affects.matching = false;
				} else if ( 'yesno' === q.type ) {
					var keep = {};
					( q.options || [] ).forEach( function ( o ) {
						keep[ o.id ] = o;
					} );
					q.options = [
						{ id: 'yes', label: 'Yes', specialties: keep.yes ? keep.yes.specialties : [] },
						{ id: 'no', label: 'No', specialties: keep.no ? keep.no.specialties : [] },
					];
					q.number = null;
				} else {
					if ( ! q.options || q.options.length < 2 || 'yes' === ( q.options[ 0 ] || {} ).id ) {
						q.options = [ { id: 'a1', label: '', specialties: [] }, { id: 'a2', label: '', specialties: [] } ];
					}

					q.number = null;
				}

				renderQuestionsEditor();
				focusIn( i, '-type' );
			} );

			card.appendChild( h( 'legend', { class: 'dak-sm-qcard-title' }, [ 'Question ' + ( i + 1 ), h( 'span', { class: 'dak-sm-slug', text: q.id } ) ] ) );

			var tools = h( 'div', { class: 'dak-sm-qcard-tools' }, [
				btn( 'Move up', 'dak-button-secondary', function () {
					move( i, -1 );
				}, { disabled: 0 === i, 'aria-label': 'Move question ' + ( i + 1 ) + ' up' } ),
				btn( 'Move down', 'dak-button-secondary', function () {
					move( i, 1 );
				}, { disabled: i === questions.length - 1, 'aria-label': 'Move question ' + ( i + 1 ) + ' down' } ),
				btn( 'Remove', 'dak-button-danger-outline', function () {
					var dependents = questions.filter( function ( x ) {
						return x.show_if && x.show_if.question === q.id;
					} );

					if ( window.confirm( 'Remove question ' + ( i + 1 ) + '?' + ( dependents.length ? ' Questions that depend on it will always be shown instead.' : '' ) ) ) { // eslint-disable-line no-alert
						dependents.forEach( function ( x ) {
							x.show_if = null;
						} );
						questions.splice( i, 1 );
						renderQuestionsEditor();
					}
				}, { 'aria-label': 'Remove question ' + ( i + 1 ) } ),
			] );

			card.appendChild( tools );
			card.appendChild( field( 'Question', text, '', '' ) );
			card.appendChild( h( 'div', { class: 'dak-sm-grid2' }, [
				field( 'Answer type', type, '', '' ),
				h( 'label', { class: 'dak-sm-check', for: base + '-req' }, [ required, h( 'span', { text: 'Patients must answer' } ) ] ),
			] ) );

			// Purpose — shown to reviewers; "matching" also unlocks answer specialties.
			var affects = h( 'div', { class: 'dak-sm-affects', role: 'group', 'aria-label': 'This question affects' } );
			[ [ 'navigation', 'Navigation (which questions come next)' ], [ 'safety', 'Safety messages' ], [ 'matching', 'Specialty matching' ] ].forEach( function ( pair ) {
				var id = base + '-aff-' + pair[ 0 ];
				var box = h( 'input', { type: 'checkbox', id: id, checked: !! ( q.affects && q.affects[ pair[ 0 ] ] ), disabled: 'matching' === pair[ 0 ] && 'number' === q.type } );
				box.addEventListener( 'change', function () {
					q.affects = q.affects || {};
					q.affects[ pair[ 0 ] ] = box.checked;

					if ( 'matching' === pair[ 0 ] ) {
						renderQuestionsEditor();
						focusIn( i, '-aff-matching' );
					}
				} );
				affects.appendChild( h( 'label', { class: 'dak-sm-check', for: id }, [ box, h( 'span', { text: pair[ 1 ] } ) ] ) );
			} );
			card.appendChild( h( 'div', { class: 'dak-sm-field' }, [ h( 'p', { class: 'dak-sm-label', text: 'This question affects' } ), affects ] ) );

			if ( 'number' === q.type ) {
				q.number = q.number || { min: null, max: null, unit: '' };
				var mk = function ( key, label, attrs ) {
					var el = h( 'input', Object.assign( { id: base + '-' + key, class: 'dak-sm-input', value: null === q.number[ key ] || undefined === q.number[ key ] ? '' : String( q.number[ key ] ) }, attrs ) );
					el.addEventListener( 'input', function () {
						q.number[ key ] = 'unit' === key ? el.value : ( '' === el.value.trim() ? null : Number( el.value ) );
					} );
					return field( label, el, '', '' );
				};

				card.appendChild( h( 'div', { class: 'dak-sm-grid3' }, [
					mk( 'min', 'Smallest allowed answer', { type: 'number', step: 'any' } ),
					mk( 'max', 'Largest allowed answer', { type: 'number', step: 'any' } ),
					mk( 'unit', 'Unit (e.g. days)', { type: 'text', maxlength: '20' } ),
				] ) );
				card.appendChild( h( 'p', { class: 'dak-sm-help', text: 'The range only checks what patients can enter. Any answer that should show a safety message must be set in a reviewed safety rule.' } ) );
			} else {
				card.appendChild( optionsEditor( q, i ) );
			}

			card.appendChild( conditionEditor( q, i ) );
			card.appendChild( field( 'Clinician-reviewed explanation (optional)', expl, 'Shown under the question to help patients answer. Keep it neutral.', '' ) );

			return card;
		}

		function optionsEditor( q, i ) {
			var wrap = h( 'div', { class: 'dak-sm-field' }, [ h( 'p', { class: 'dak-sm-label', text: 'Answers' } ) ] );
			var fixed = 'yesno' === q.type;

			( q.options || [] ).forEach( function ( o, oi ) {
				var id = 'dak-sm-q' + i + '-o' + oi;
				var label = h( 'input', { type: 'text', id: id, class: 'dak-sm-input', value: o.label, maxlength: '100', readonly: fixed, 'aria-label': 'Answer ' + ( oi + 1 ) + ' of question ' + ( i + 1 ) } );
				label.addEventListener( 'input', function () {
					o.label = label.value;
				} );

				var row = h( 'div', { class: 'dak-sm-option' }, [ label ] );

				if ( ! fixed ) {
					row.appendChild( btn( 'Remove', 'dak-button-secondary', function () {
						q.options.splice( oi, 1 );
						questions.forEach( function ( x ) {
							if ( x.show_if && x.show_if.question === q.id ) {
								x.show_if.answers = x.show_if.answers.filter( function ( a ) {
									return a !== o.id;
								} );
							}
						} );
						renderQuestionsEditor();
					}, { disabled: q.options.length <= 2, 'aria-label': 'Remove answer ' + ( oi + 1 ) } ) );
				}

				if ( q.affects && q.affects.matching ) {
					var sel = h( 'select', { multiple: true, class: 'dak-sm-input dak-sm-multi', size: '3', 'aria-label': 'Specialties to add when patients answer “' + ( o.label || 'answer ' + ( oi + 1 ) ) + '”' }, Object.keys( state.specialties ).map( function ( slug ) {
						return h( 'option', { value: slug, text: state.specialties[ slug ], selected: ( o.specialties || [] ).indexOf( slug ) !== -1 } );
					} ) );
					sel.addEventListener( 'change', function () {
						o.specialties = Array.prototype.filter.call( sel.options, function ( op ) {
							return op.selected;
						} ).map( function ( op ) {
							return op.value;
						} );
					} );
					row.appendChild( h( 'div', { class: 'dak-sm-option-specs' }, [ h( 'span', { class: 'dak-sm-help', text: 'Also suggest:' } ), sel ] ) );
				}

				wrap.appendChild( row );
			} );

			if ( ! fixed && q.options.length < ( state.limits && state.limits.options || 8 ) ) {
				wrap.appendChild( btn( '+ Add answer', 'dak-button-secondary', function () {
					q.options.push( { id: nextId( 'a', q.options.map( function ( o ) {
						return o.id;
					} ) ), label: '', specialties: [] } );
					renderQuestionsEditor();
					focusIn( i, '-o' + ( q.options.length - 1 ) );
				} ) );
			}

			return wrap;
		}

		function conditionEditor( q, i ) {
			var earlier = questions.slice( 0, i ).filter( function ( x ) {
				return 'number' !== x.type;
			} );
			var wrap = h( 'div', { class: 'dak-sm-field dak-sm-cond' } );
			var id = 'dak-sm-q' + i + '-cond';
			var sel = h( 'select', { id: id, class: 'dak-sm-input', disabled: ! earlier.length }, [ h( 'option', { value: '', text: earlier.length ? 'Always ask this question' : 'Always ask (no earlier choice questions)' } ) ].concat( earlier.map( function ( x ) {
				return h( 'option', { value: x.id, text: 'Only if “' + ( x.text || x.id ) + '” is answered…', selected: q.show_if && q.show_if.question === x.id } );
			} ) ) );

			sel.addEventListener( 'change', function () {
				q.show_if = sel.value ? { question: sel.value, answers: [] } : null;
				renderQuestionsEditor();
				focusIn( i, '-cond' );
			} );

			wrap.appendChild( h( 'label', { class: 'dak-sm-label', for: id, text: 'When to ask' } ) );
			wrap.appendChild( sel );

			var parent = q.show_if ? earlier.filter( function ( x ) {
				return x.id === q.show_if.question;
			} )[ 0 ] : null;

			if ( parent ) {
				var group = h( 'div', { class: 'dak-sm-affects', role: 'group', 'aria-label': 'Answers that show this question' } );
				( parent.options || [] ).forEach( function ( o, oi ) {
					var cid = 'dak-sm-q' + i + '-ca' + oi;
					var box = h( 'input', { type: 'checkbox', id: cid, checked: q.show_if.answers.indexOf( o.id ) !== -1 } );
					box.addEventListener( 'change', function () {
						q.show_if.answers = q.show_if.answers.filter( function ( a ) {
							return a !== o.id;
						} );

						if ( box.checked ) {
							q.show_if.answers.push( o.id );
						}
					} );
					group.appendChild( h( 'label', { class: 'dak-sm-check', for: cid }, [ box, h( 'span', { text: o.label || o.id } ) ] ) );
				} );
				wrap.appendChild( group );
			} else if ( q.show_if ) {
				q.show_if = null;
			}

			return wrap;
		}

		function move( i, by ) {
			var j = i + by;
			var item = questions.splice( i, 1 )[ 0 ];
			questions.splice( j, 0, item );

			// A condition must still point at an earlier question.
			questions.forEach( function ( x, xi ) {
				if ( x.show_if ) {
					var pi = questions.map( function ( y ) {
						return y.id;
					} ).indexOf( x.show_if.question );

					if ( pi === -1 || pi >= xi ) {
						x.show_if = null;
					}
				}
			} );

			renderQuestionsEditor();
			focusIn( j, '-text' );
		}

		function focusIn( i, suffix ) {
			var el = document.getElementById( 'dak-sm-q' + i + suffix );

			if ( el ) {
				el.focus();
			}
		}

		renderQuestionsEditor();

		form.appendChild( field( 'Internal notes (optional)', notes, 'For reviewers only — never shown to patients. Changing only the notes keeps the review.', 'notes' ) );

		var save = h( 'button', { type: 'submit', class: 'dak-button dak-button-primary', text: m ? 'Save changes' : 'Add as draft' } );
		form.appendChild( h( 'div', { class: 'dak-sm-actions' }, [ save, btn( 'Cancel', 'dak-button-secondary', closeDialog ) ] ) );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var fields = {
				id: m ? m.id : 0,
				name: name.value,
				slug: slug.value,
				description: desc.value,
				synonyms: syn.value,
				specialties: specs.value(),
				questions: JSON.stringify( questions ),
				notes: notes.value,
			};

			Object.keys( langs ).forEach( function ( code ) {
				fields[ 'phrases_' + code ] = langs[ code ].value;
			} );

			post( 'save', fields, save ).then( function ( res ) {
				if ( res.success ) {
					closeDialog();
					return;
				}

				showErrors( form, ( res.data && res.data.errors ) || {}, 'name' );
			} );
		} );

		openDialog( m ? 'Edit symptom' : 'Add symptom', form );
	}

	/* ------------------------------------------------------------------
	 * Safety rule editor
	 * ---------------------------------------------------------------- */

	function openRuleEditor( r ) {
		var form = h( 'form', { class: 'dak-sm-form', novalidate: true } );
		var title = h( 'input', { type: 'text', id: 'dak-sm-rule-title', class: 'dak-sm-input', value: r ? r.title : '', maxlength: '120', 'aria-describedby': 'dak-sm-rule-title-help' } );
		var message = h( 'textarea', { id: 'dak-sm-rule-message', class: 'dak-sm-input', rows: '5', maxlength: '1000', value: r ? r.message : '', 'aria-describedby': 'dak-sm-rule-message-help' } );
		var notes = h( 'textarea', { id: 'dak-sm-rule-notes', class: 'dak-sm-input', rows: '2', value: r ? r.notes : '' } );
		var level = r ? r.level : 'emergency';
		var interrupt = h( 'input', { type: 'checkbox', id: 'dak-sm-rule-interrupt', checked: r ? r.interrupt : true } );
		var triggers = r ? clone( r.triggers ) : [ { symptom: 0, question: '', answers: [], min: null, max: null } ];

		form.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'Write safety messages with a qualified clinician. They show exactly as written, immediately, above any doctor suggestions. They must give clear instructions — never reassure patients or say that something has been ruled out.' } ) );

		if ( r && r.return_reason ) {
			form.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'Returned for revision: ' + r.return_reason } ) );
		}

		if ( r && r.is_live ) {
			form.appendChild( h( 'p', { class: 'dak-sm-alert is-info', text: 'Live version ' + r.published_version + ' keeps showing to patients while you edit. Your changes go live only after they are approved and activated.' } ) );
		}

		form.appendChild( field( 'Heading', title, 'e.g. “Get emergency help now”.', 'title' ) );

		var levelGroup = h( 'div', { class: 'dak-sm-affects', role: 'radiogroup', 'aria-labelledby': 'dak-sm-rule-level-label' } );
		Object.keys( LEVELS ).forEach( function ( k ) {
			var id = 'dak-sm-rule-level-' + k;
			var radio = h( 'input', { type: 'radio', id: id, name: 'dak-sm-rule-level', value: k, checked: k === level } );
			radio.addEventListener( 'change', function () {
				level = k;
				syncInterrupt();
			} );
			levelGroup.appendChild( h( 'label', { class: 'dak-sm-check', for: id }, [ radio, h( 'span', { text: 'emergency' === k ? 'Emergency — red, always stops the questions' : 'Urgent — amber' } ) ] ) );
		} );

		form.appendChild( h( 'div', { class: 'dak-sm-field' }, [
			h( 'p', { class: 'dak-sm-label', id: 'dak-sm-rule-level-label', text: 'How it’s shown' } ),
			levelGroup,
			h( 'label', { class: 'dak-sm-check', for: 'dak-sm-rule-interrupt' }, [ interrupt, h( 'span', { text: 'Stop the remaining questions when this is shown' } ) ] ),
			h( 'p', { class: 'dak-sm-error', 'data-error': 'level', hidden: true } ),
		] ) );

		function syncInterrupt() {
			if ( 'emergency' === level ) {
				interrupt.checked = true;
				interrupt.disabled = true;
			} else {
				interrupt.disabled = false;
			}
		}

		syncInterrupt();

		form.appendChild( field( 'Instructions for patients', message, 'What to do now, e.g. who to call or where to go. One instruction per line is easiest to read.', 'message' ) );

		var tBox = h( 'div', { class: 'dak-sm-triggers-edit' } );

		form.appendChild( h( 'div', { class: 'dak-sm-field' }, [
			h( 'h3', { class: 'dak-sm-label', text: 'Show this when (any of these)' } ),
			h( 'p', { class: 'dak-sm-help', text: 'Choose a symptom, and optionally one of its follow-up questions and the answers that should show this message.' } ),
			tBox,
			h( 'p', { class: 'dak-sm-error', 'data-error': 'triggers', hidden: true } ),
		] ) );

		function questionsOf( s ) {
			var seen = {};
			var out = [];

			[ s.questions || [], s.published && s.published.questions || [] ].forEach( function ( listQ, li ) {
				listQ.forEach( function ( q ) {
					if ( ! seen[ q.id ] ) {
						seen[ q.id ] = true;
						out.push( { q: q, liveOnly: 1 === li } );
					}
				} );
			} );

			return out;
		}

		function renderTriggers() {
			tBox.textContent = '';

			triggers.forEach( function ( t, i ) {
				var base = 'dak-sm-t' + i;
				var row = h( 'div', { class: 'dak-sm-trigger' } );
				var sSel = h( 'select', { id: base + '-s', class: 'dak-sm-input', 'aria-label': 'Symptom for condition ' + ( i + 1 ) }, [ h( 'option', { value: '0', text: 'Choose a symptom' } ) ].concat( state.mappings.map( function ( s ) {
					return h( 'option', { value: String( s.id ), text: s.name + ( s.is_live ? '' : ' (not live)' ), selected: s.id === Number( t.symptom ) } );
				} ) ) );

				sSel.addEventListener( 'change', function () {
					t.symptom = Number( sSel.value );
					t.question = '';
					t.answers = [];
					t.min = null;
					t.max = null;
					renderTriggers();
					document.getElementById( base + '-s' ).focus();
				} );

				row.appendChild( h( 'p', { class: 'dak-sm-label', text: 'When ' + ( i + 1 ) } ) );
				row.appendChild( sSel );

				var s = symptomById( t.symptom );

				if ( s ) {
					var qs = questionsOf( s );
					var qSel = h( 'select', { id: base + '-q', class: 'dak-sm-input', 'aria-label': 'Question for condition ' + ( i + 1 ) }, [ h( 'option', { value: '', text: 'Whenever this symptom is chosen' } ) ].concat( qs.map( function ( x ) {
						return h( 'option', { value: x.q.id, text: '…and “' + ( x.q.text || x.q.id ) + '”' + ( x.liveOnly ? ' (removed in the working copy)' : '' ), selected: x.q.id === t.question } );
					} ) ) );

					qSel.addEventListener( 'change', function () {
						t.question = qSel.value;
						t.answers = [];
						t.min = null;
						t.max = null;
						renderTriggers();
						document.getElementById( base + '-q' ).focus();
					} );

					row.appendChild( qSel );

					var q = t.question ? findQuestion( s, t.question ) : null;

					if ( q && 'number' === q.type ) {
						var mk = function ( key, label ) {
							var el = h( 'input', { type: 'number', step: 'any', id: base + '-' + key, class: 'dak-sm-input', value: null === t[ key ] || undefined === t[ key ] ? '' : String( t[ key ] ) } );
							el.addEventListener( 'input', function () {
								t[ key ] = '' === el.value.trim() ? null : Number( el.value );
							} );
							return field( label + ( q.number && q.number.unit ? ' (' + q.number.unit + ')' : '' ), el, '', '' );
						};
						row.appendChild( h( 'div', { class: 'dak-sm-grid2' }, [ mk( 'min', 'Answer at least' ), mk( 'max', 'Answer at most' ) ] ) );
					} else if ( q ) {
						var group = h( 'div', { class: 'dak-sm-affects', role: 'group', 'aria-label': 'Answers that show this message' } );
						( q.options || [] ).forEach( function ( o, oi ) {
							var cid = base + '-a' + oi;
							var box = h( 'input', { type: 'checkbox', id: cid, checked: ( t.answers || [] ).indexOf( o.id ) !== -1 } );
							box.addEventListener( 'change', function () {
								t.answers = ( t.answers || [] ).filter( function ( a ) {
									return a !== o.id;
								} );

								if ( box.checked ) {
									t.answers.push( o.id );
								}
							} );
							group.appendChild( h( 'label', { class: 'dak-sm-check', for: cid }, [ box, h( 'span', { text: o.label || o.id } ) ] ) );
						} );
						row.appendChild( group );
					}
				}

				row.appendChild( btn( 'Remove condition', 'dak-button-secondary', function () {
					triggers.splice( i, 1 );
					renderTriggers();
				}, { disabled: triggers.length <= 1, 'aria-label': 'Remove condition ' + ( i + 1 ) } ) );

				tBox.appendChild( row );
			} );

			if ( triggers.length < ( state.limits && state.limits.triggers || 20 ) ) {
				tBox.appendChild( btn( '+ Add another condition', 'dak-button-secondary', function () {
					triggers.push( { symptom: 0, question: '', answers: [], min: null, max: null } );
					renderTriggers();
					document.getElementById( 'dak-sm-t' + ( triggers.length - 1 ) + '-s' ).focus();
				} ) );
			}
		}

		renderTriggers();

		form.appendChild( field( 'Internal notes (optional)', notes, 'For reviewers only — e.g. the clinical source for this rule.', 'notes' ) );

		var save = h( 'button', { type: 'submit', class: 'dak-button dak-button-primary', text: r ? 'Save changes' : 'Add as draft' } );
		form.appendChild( h( 'div', { class: 'dak-sm-actions' }, [ save, btn( 'Cancel', 'dak-button-secondary', closeDialog ) ] ) );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			post( 'rule_save', {
				id: r ? r.id : 0,
				title: title.value,
				level: level,
				interrupt: interrupt.checked ? '1' : '0',
				message: message.value,
				triggers: JSON.stringify( triggers.filter( function ( t ) {
					return Number( t.symptom ) > 0 || triggers.length === 1;
				} ) ),
				notes: notes.value,
			}, save ).then( function ( res ) {
				if ( res.success ) {
					closeDialog();
					return;
				}

				showErrors( form, ( res.data && res.data.errors ) || {}, 'title' );
			} );
		} );

		openDialog( r ? 'Edit safety rule' : 'Add safety rule', form );
	}

	/* ------------------------------------------------------------------
	 * Import
	 * ---------------------------------------------------------------- */

	function openImport() {
		var wrap = h( 'div', { class: 'dak-sm-form' } );
		var file = h( 'input', { type: 'file', id: 'dak-sm-import-file', class: 'dak-sm-input', accept: '.csv,text/csv,.txt,.json', 'aria-describedby': 'dak-sm-import-file-help' } );
		var text = h( 'textarea', { id: 'dak-sm-import', class: 'dak-sm-input dak-sm-mono', rows: '7', placeholder: state.csvColumns.join( ',' ), 'aria-describedby': 'dak-sm-import-help' } );
		var out = h( 'div', { class: 'dak-sm-import-out', 'aria-live': 'polite' } );
		var columns = state.csvColumns.filter( function ( c ) {
			return 'status' !== c;
		} );

		wrap.appendChild( h( 'div', { class: 'dak-sm-help-box' }, [
			h( 'p', {}, [ h( 'strong', { text: 'CSV format. ' } ), 'First row: column names. Columns: ' + columns.join( ', ' ) + '. Only “name” and “specialties” are required. Put several phrases or specialties in one cell separated by “|”. Specialties can be names or identifiers from this site. A “status” column (as in an export) is ignored.' ] ),
			h( 'p', { text: 'Every row is imported as a Draft and must be reviewed. Rows that repeat an existing symptom, identifier or phrase — or an earlier row — are skipped. Nothing existing is changed. Follow-up questions are added on screen after import.' } ),
			btn( 'Download a template', 'dak-button-secondary', function () {
				var example = columns.map( function ( c ) {
					return {
						name: 'Example symptom',
						slug: 'example-symptom',
						description: '',
						english_phrases: 'phrase one | phrase two',
						specialties: 'General Physician',
						notes: 'Replace this row',
					}[ c ] || '';
				} );
				download( 'symptom-mappings-template.csv', columns.join( ',' ) + '\n' + example.map( function ( v ) {
					return /[",\n]/.test( v ) ? '"' + v.replace( /"/g, '""' ) + '"' : v;
				} ).join( ',' ) + '\n' );
			} ),
		] ) );

		wrap.appendChild( field( 'CSV file', file, 'Up to 1 MB. Or paste below.', '' ) );
		wrap.appendChild( field( 'Or paste CSV (or “Symptom | phrases | specialties” lines)', text, '', '' ) );

		file.addEventListener( 'change', function () {
			var f = file.files && file.files[ 0 ];

			if ( ! f ) {
				return;
			}

			if ( f.size > 1048576 ) {
				out.textContent = 'That file is larger than 1 MB.';
				return;
			}

			var reader = new FileReader();
			reader.onload = function () {
				text.value = String( reader.result || '' );
			};
			reader.readAsText( f );
		} );

		var check = btn( 'Check import', 'dak-button-primary', function ( e ) {
			post( 'import_preview', { text: text.value }, e.currentTarget ).then( function ( res ) {
				out.textContent = '';
				var rows = ( res.data && res.data.preview ) || [];

				if ( ! rows.length ) {
					out.appendChild( h( 'p', { class: 'dak-sm-help', text: 'Nothing to import — check the format.' } ) );
					return;
				}

				var ready = rows.filter( function ( r ) {
					return 'ready' === r.result;
				} ).length;

				out.appendChild( h( 'p', { class: 'dak-sm-help', text: ready + ' of ' + rows.length + ' rows are ready. The rest will be skipped.' } ) );

				var table = h( 'table', { class: 'dak-sm-table' }, [ h( 'thead', {}, [ h( 'tr', {}, [
					h( 'th', { text: 'Line' } ), h( 'th', { text: 'Symptom' } ), h( 'th', { text: 'Specialties' } ), h( 'th', { text: 'Result' } ),
				] ) ] ) ] );
				var body = h( 'tbody' );

				rows.forEach( function ( r ) {
					body.appendChild( h( 'tr', { class: 'is-' + r.result }, [
						h( 'td', { text: String( r.line ) } ),
						h( 'td', {}, [ h( 'strong', { text: r.name || '—' } ), r.synonyms.length ? h( 'span', { class: 'dak-sm-help', text: r.synonyms.join( ', ' ) } ) : null ] ),
						h( 'td', { text: r.specialties.join( ', ' ) || '—' } ),
						h( 'td', {}, [
							h( 'span', { class: 'dak-status-pill ' + ( 'ready' === r.result ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral' ), text: { ready: 'Ready', duplicate: 'Duplicate — skipped', invalid: 'Needs fixing — skipped' }[ r.result ] } ),
							r.messages.length ? h( 'span', { class: 'dak-sm-warn', text: r.messages.join( ' ' ) } ) : null,
						] ),
					] ) );
				} );

				table.appendChild( body );
				out.appendChild( h( 'div', { class: 'dak-sm-table-wrap' }, [ table ] ) );

				if ( ready ) {
					out.appendChild( btn( 'Import ' + ready + ' as drafts', 'dak-button-primary', function ( ev ) {
						post( 'import', { text: text.value }, ev.currentTarget ).then( function ( r2 ) {
							if ( r2.success ) {
								closeDialog();
							}
						} );
					} ) );
				}
			} );
		} );

		wrap.appendChild( h( 'div', { class: 'dak-sm-actions' }, [ check, btn( 'Cancel', 'dak-button-secondary', closeDialog ) ] ) );
		wrap.appendChild( out );
		openDialog( 'Import symptoms', wrap );
	}

	/* ------------------------------------------------------------------
	 * History
	 * ---------------------------------------------------------------- */

	function describeSnapshot( type, snap ) {
		var dl = h( 'dl', { class: 'dak-sm-try-list' } );
		var add = function ( k, v ) {
			dl.appendChild( h( 'dt', { text: k } ) );
			dl.appendChild( h( 'dd', { text: v || '—' } ) );
		};

		if ( 'rule' === type ) {
			add( 'Heading', snap.title );
			add( 'Shown as', LEVELS[ snap.level ] || snap.level );
			add( 'Instructions', snap.message );
			add( 'Shown when', ( snap.triggers || [] ).map( triggerText ).join( '; ' ) );
		} else if ( 'notice' === type ) {
			add( 'Notice', snap.text );
		} else {
			add( 'Name', snap.name );
			add( 'Patient guidance', snap.description );
			add( 'English phrases', ( snap.synonyms || [] ).join( ', ' ) );
			Object.keys( snap.phrases || {} ).forEach( function ( code ) {
				add( ( state.languages[ code ] || code ) + ' phrases', snap.phrases[ code ].join( ', ' ) );
			} );
			add( 'Specialties', ( snap.specialties || [] ).map( specLabel ).join( ', ' ) );
			add( 'Questions', ( snap.questions || [] ).map( function ( q, i ) {
				return ( i + 1 ) + '. ' + q.text + ' [' + ( TYPES[ q.type ] || q.type ) + ( q.options && q.options.length ? ': ' + q.options.map( function ( o ) {
					return o.label;
				} ).join( ' / ' ) : '' ) + ']';
			} ).join( '  ' ) );
		}

		return dl;
	}

	function openHistory( type, id, name ) {
		var wrap = h( 'div', { class: 'dak-sm-form' }, [ h( 'p', { class: 'dak-sm-help', text: 'Loading…' } ) ] );

		openDialog( 'History — ' + name, wrap );

		post( 'history', { type: type, id: id } ).then( function ( res ) {
			wrap.textContent = '';
			var rows = ( res.data && res.data.history ) || [];

			if ( ! res.success ) {
				wrap.appendChild( h( 'p', { class: 'dak-sm-warn', text: ( res.data && res.data.message ) || 'History could not be loaded.' } ) );
				return;
			}

			if ( ! rows.length ) {
				wrap.appendChild( h( 'p', { class: 'dak-sm-help', text: 'No recorded changes yet. (Changes made before the audit log was added aren’t listed.)' } ) );
				return;
			}

			var ol = h( 'ol', { class: 'dak-sm-history' } );

			rows.forEach( function ( row ) {
				var detail = h( 'div', { class: 'dak-sm-history-detail', hidden: true } );
				var li = h( 'li', {}, [
					h( 'p', {}, [
						h( 'strong', { text: ACTIONS[ row.action ] || row.action } ),
						' · ' + dateTime( row.created_at ) + ( row.user_name ? ' · ' + row.user_name : '' ),
					] ),
					row.summary ? h( 'p', { class: 'dak-sm-help', text: row.summary } ) : null,
					row.has_snapshot ? btn( 'View this version', 'dak-button-secondary', function ( e ) {
						if ( ! detail.hidden ) {
							detail.hidden = true;
							return;
						}

						post( 'history_snapshot', { entry: row.id }, e.currentTarget ).then( function ( r2 ) {
							detail.textContent = '';

							if ( r2.success && r2.data && r2.data.entry ) {
								detail.appendChild( describeSnapshot( type, r2.data.entry.snapshot ) );
							} else {
								detail.appendChild( h( 'p', { class: 'dak-sm-warn', text: 'That version could not be loaded.' } ) );
							}

							detail.hidden = false;
						} );
					} ) : null,
					detail,
				] );

				ol.appendChild( li );
			} );

			wrap.appendChild( ol );
		} );
	}

	/* ------------------------------------------------------------------
	 * Try matching
	 * ---------------------------------------------------------------- */

	function renderPreview() {
		var box = root.querySelector( '[data-sm-preview]' );

		if ( box.childNodes.length ) {
			box.dispatchEvent( new Event( 'dak-refresh' ) );
			return;
		}

		var input = h( 'input', { type: 'text', id: 'dak-sm-try', class: 'dak-sm-input', placeholder: 'e.g. stomache pain and bukhar since 2 days' } );
		var all = h( 'input', { type: 'checkbox', id: 'dak-sm-try-all', checked: true } );
		var out = h( 'div', { class: 'dak-sm-try-out', 'aria-live': 'polite' } );

		function run() {
			out.textContent = '';

			if ( ! input.value.trim() || ! window.DAKSymptomMatch ) {
				return;
			}

			var pool = state.mappings.filter( function ( m ) {
				return all.checked ? 'inactive' !== m.status || m.is_live : m.is_live;
			} ).map( function ( m ) {
				var copy = all.checked || ! m.published ? m : Object.assign( {}, m, m.published );
				var phrases = ( copy.synonyms || [] ).slice();

				Object.keys( copy.phrases || {} ).forEach( function ( code ) {
					phrases = phrases.concat( copy.phrases[ code ] );
				} );

				return { id: m.id, name: copy.name, synonyms: phrases, specialties: copy.specialties, status: m.status, is_live: m.is_live };
			} );
			var byId = {};
			pool.forEach( function ( m ) {
				byId[ m.id ] = m;
			} );

			var matcher = window.DAKSymptomMatch.create( pool );
			var r = matcher.analyse( input.value );
			var tag = function ( id ) {
				return byId[ id ].name + ( byId[ id ].is_live ? '' : ' (' + STATUS[ byId[ id ].status ].label.toLowerCase() + ')' );
			};

			out.appendChild( h( 'dl', { class: 'dak-sm-try-list' }, [
				h( 'dt', { text: 'Recognised' } ), h( 'dd', { text: r.recognized.map( tag ).join( ', ' ) || '—' } ),
				h( 'dt', { text: 'Would ask “did you mean”' } ), h( 'dd', { text: r.ambiguous.map( function ( a ) {
					return '“' + a.phrase + '” → ' + a.candidates.map( tag ).join( ' / ' );
				} ).join( '; ' ) || '—' } ),
				h( 'dt', { text: 'Not matched' } ), h( 'dd', { text: r.unknown.map( function ( p ) {
					return '“' + p + '”';
				} ).join( ', ' ) || '—' } ),
				h( 'dt', { text: 'Specialties shown' } ), h( 'dd', { text: matcher.specialtiesFor( r.recognized ).map( function ( s ) {
					return specLabel( s.slug ) + ' (' + s.symptoms.join( ', ' ) + ')';
				} ).join( '; ' ) || '—' } ),
			] ) );
		}

		input.addEventListener( 'input', run );
		all.addEventListener( 'change', run );
		box.addEventListener( 'dak-refresh', run );

		box.appendChild( field( 'What a patient might type', input, '', '' ) );
		box.appendChild( h( 'label', { class: 'dak-sm-check', for: 'dak-sm-try-all' }, [ all, h( 'span', { text: 'Use working copies, including drafts (otherwise only what patients see now)' } ) ] ) );
		box.appendChild( out );
	}

	/* ------------------------------------------------------------------
	 * Reviewers + legacy
	 * ---------------------------------------------------------------- */

	function renderReviewers() {
		var box = root.querySelector( '[data-sm-reviewers]' );

		if ( ! box || ! state.reviewers ) {
			return;
		}

		box.textContent = '';

		if ( ! state.reviewers.length ) {
			box.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'No clinical reviewers yet. Nothing can be approved or activated until you add at least one.' } ) );
		} else {
			box.appendChild( h( 'ul', { class: 'dak-sm-people' }, state.reviewers.map( function ( r ) {
				return h( 'li', {}, [
					h( 'span', { text: r.name + ' · ' + r.role } ),
					btn( 'Remove', 'dak-button-secondary', function ( e ) {
						post( 'reviewer_remove', { user_id: r.id }, e.currentTarget );
					}, { 'aria-label': 'Remove ' + r.name + ' as reviewer' } ),
				] );
			} ) ) );
		}

		var current = state.reviewers.map( function ( r ) {
			return r.id;
		} );
		var select = h( 'select', { id: 'dak-sm-new-reviewer', class: 'dak-sm-input' }, [ h( 'option', { value: '', text: 'Choose a doctor or administrator' } ) ].concat(
			state.candidates.filter( function ( c ) {
				return current.indexOf( c.id ) === -1;
			} ).map( function ( c ) {
				return h( 'option', { value: String( c.id ), text: c.name } );
			} )
		) );

		box.appendChild( h( 'div', { class: 'dak-sm-inline' }, [
			h( 'label', { class: 'dak-visually-hidden', for: 'dak-sm-new-reviewer', text: 'Add a clinical reviewer' } ),
			select,
			btn( 'Add reviewer', 'dak-button-primary', function ( e ) {
				if ( select.value ) {
					post( 'reviewer_add', { user_id: select.value }, e.currentTarget );
				} else {
					select.focus();
				}
			} ),
			btn( 'History', 'dak-button-secondary', function () {
				openHistory( 'page', 0, 'Page and reviewers' );
			} ),
		] ) );
	}

	function renderLegacy() {
		var box = root.querySelector( '[data-sm-legacy]' );

		box.hidden = ! state.legacyCount;
		box.textContent = '';

		if ( ! state.legacyCount ) {
			return;
		}

		box.appendChild( h( 'p', { text: 'The previous Symptom Checker’s rules are kept but no longer used. They include ' + state.legacyCount + ' symptom' + ( 1 === state.legacyCount ? '' : 's' ) + '.' } ) );

		if ( state.can.manage ) {
			box.appendChild( btn( 'Copy those symptoms in as drafts', 'dak-button-secondary', function ( e ) {
				post( 'legacy', {}, e.currentTarget );
			} ) );
		}
	}

	function render() {
		renderStatus();
		renderNotice();
		renderToolbar();
		renderList();
		renderRules();
		renderPreview();
		renderReviewers();
		renderLegacy();
	}

	render();
}() );
