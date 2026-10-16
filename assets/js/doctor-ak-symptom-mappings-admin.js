/**
 * Doctor AK Portal — "Symptom mappings" screen (admin dashboard section and
 * the clinical reviewers' doctor-dashboard tab).
 *
 * Draws everything from the state the server sends (dakSymptomMappings.state)
 * and redraws from the fresh state each action returns. Buttons are only
 * shown to people allowed to use them, but every permission is enforced on
 * the server (Symptom_Mappings_Handler).
 */
( function () {
	'use strict';

	var config = window.dakSymptomMappings;
	var root = document.querySelector( '[data-dak-sm]' );

	if ( ! config || ! root ) {
		return;
	}

	var state = config.state;
	var filters = { q: '', status: '' };
	var picked = {};
	var dialog = document.getElementById( 'dak-sm-dialog' );
	var lastFocus = null;

	var STATUS = {
		draft: { label: 'Draft', cls: 'dak-status-pill-is-neutral' },
		reviewed: { label: 'Reviewed', cls: 'dak-status-pill-is-pending' },
		active: { label: 'Active', cls: 'dak-status-pill-is-active' },
	};
	var SOURCE = { example: 'Example — not reviewed', legacy: 'From previous rules', import: 'Imported' };

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

	function flash( text, isError ) {
		var el = root.querySelector( '[data-sm-flash]' );
		el.textContent = text || '';
		el.hidden = ! text;
		el.classList.toggle( 'is-error', !! isError );
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

	/* ------------------------------------------------------------------
	 * Status
	 * ---------------------------------------------------------------- */

	function renderStatus() {
		var box = root.querySelector( '[data-sm-status]' );
		var counts = { draft: 0, reviewed: 0, active: 0 };

		state.mappings.forEach( function ( m ) {
			counts[ m.status ] += 1;
		} );

		box.textContent = '';
		box.appendChild( h( 'p', {}, [
			h( 'span', { class: 'dak-status-pill ' + ( state.isPublic ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral' ), text: state.isPublic ? 'Available to patients' : 'Hidden from patients' } ),
			' ' + counts.active + ' active · ' + counts.reviewed + ' reviewed · ' + counts.draft + ' draft',
		] ) );

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
				if ( toggle.checked && ! window.confirm( 'Show “Find a doctor by symptoms” to patients, with the active mappings?' ) ) { // eslint-disable-line no-alert
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
			h( 'span', { class: 'dak-status-pill ' + ( approved ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral' ), text: approved ? 'Approved' : 'Draft' } ),
			n.updated_at ? ' Last edited by ' + ( n.updated_by_name || 'unknown' ) + ', ' + date( n.updated_at ) + '.' : ' Suggested wording — not saved or approved yet.',
			approved ? ' Approved by ' + ( n.approved_by_name || 'unknown' ) + ', ' + date( n.approved_at ) + '.' : '',
		] ) );

		var actions = h( 'div', { class: 'dak-sm-actions' } );

		if ( state.can.manage ) {
			actions.appendChild( btn( 'Save notice', 'dak-button-secondary', function ( e ) {
				post( 'notice_save', { text: area.value }, e.currentTarget );
			} ) );
		}

		if ( state.can.review && ! approved ) {
			var self = n.updated_by === state.userId;
			actions.appendChild( btn( 'Approve notice', 'dak-button-primary', function ( e ) {
				post( 'notice_approve', {}, e.currentTarget );
			}, { disabled: self || ! n.updated_at, title: self ? 'Another reviewer must approve your own edit.' : null } ) );

			if ( self ) {
				actions.appendChild( h( 'span', { class: 'dak-sm-help', text: 'You edited this last, so another reviewer needs to approve it.' } ) );
			}
		}

		box.appendChild( actions );
	}

	/* ------------------------------------------------------------------
	 * List
	 * ---------------------------------------------------------------- */

	function matchesFilter( m ) {
		if ( filters.status && m.status !== filters.status ) {
			return false;
		}

		if ( ! filters.q ) {
			return true;
		}

		var hay = [ m.name ].concat( m.synonyms, m.specialty_labels ).join( ' ' ).toLowerCase();

		return hay.indexOf( filters.q.toLowerCase() ) !== -1;
	}

	function renderToolbar() {
		var bar = root.querySelector( '[data-sm-toolbar]' );

		if ( bar.childNodes.length ) {
			return; // Keep focus/typing in the search box across redraws.
		}

		var search = h( 'input', { type: 'search', class: 'dak-sm-input dak-sm-search', placeholder: 'Search symptoms, phrases, specialties', 'aria-label': 'Search symptom mappings' } );
		search.addEventListener( 'input', function () {
			filters.q = search.value.trim();
			renderList();
		} );

		var status = h( 'select', { class: 'dak-sm-input', 'aria-label': 'Filter by status' }, [
			h( 'option', { value: '', text: 'All statuses' } ),
			h( 'option', { value: 'draft', text: 'Draft' } ),
			h( 'option', { value: 'reviewed', text: 'Reviewed' } ),
			h( 'option', { value: 'active', text: 'Active' } ),
		] );
		status.addEventListener( 'change', function () {
			filters.status = status.value;
			renderList();
		} );

		bar.appendChild( search );
		bar.appendChild( status );

		if ( state.can.manage ) {
			bar.appendChild( btn( '+ Add symptom', 'dak-button-primary', function () {
				openEditor( null );
			} ) );
			bar.appendChild( btn( 'Import list', 'dak-button-secondary', openImport ) );
			bar.appendChild( btn( 'Load example drafts', 'dak-button-secondary', function ( e ) {
				if ( window.confirm( 'Add the bundled EXAMPLE mappings as drafts? They are not clinically reviewed; each must be checked by a reviewer before use. Existing symptoms are skipped.' ) ) { // eslint-disable-line no-alert
					post( 'examples', {}, e.currentTarget );
				}
			} ) );
		}
	}

	/** Where a mapping came from; examples are flagged until reviewed. */
	function sourceBadge( m ) {
		if ( 'example' === m.source && 'draft' !== m.status ) {
			return h( 'span', { class: 'dak-sm-source', text: 'From examples' } );
		}

		return SOURCE[ m.source ] ? h( 'span', { class: 'dak-sm-source is-' + m.source, text: SOURCE[ m.source ] } ) : null;
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
			box.appendChild( h( 'div', { class: 'dak-sm-empty', text: 'No symptom mappings yet. Add one, import a reviewed list, or load the example drafts to start from.' } ) );
			return;
		}

		var pickedIds = Object.keys( picked ).map( Number );

		if ( pickedIds.length ) {
			var bulk = h( 'div', { class: 'dak-sm-bulk' }, [ h( 'strong', { text: pickedIds.length + ' selected' } ) ] );

			if ( state.can.review ) {
				bulk.appendChild( btn( 'Mark reviewed', 'dak-button-secondary', function ( e ) {
					post( 'bulk', { bulk_action: 'review', ids: pickedIds }, e.currentTarget );
				} ) );
				bulk.appendChild( btn( 'Activate', 'dak-button-secondary', function ( e ) {
					post( 'bulk', { bulk_action: 'activate', ids: pickedIds }, e.currentTarget );
				} ) );
			}

			if ( state.can.admin ) {
				bulk.appendChild( btn( 'Delete', 'dak-button-danger-outline', function ( e ) {
					if ( window.confirm( 'Delete ' + pickedIds.length + ' symptom mapping(s)? This cannot be undone.' ) ) { // eslint-disable-line no-alert
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
			box.appendChild( h( 'div', { class: 'dak-sm-empty', text: 'No mappings match your search.' } ) );
			return;
		}

		var table = h( 'table', { class: 'dak-sm-table' } );
		table.appendChild( h( 'thead', {}, [ h( 'tr', {}, [
			h( 'th', { scope: 'col' }, [ h( 'span', { class: 'dak-visually-hidden', text: 'Select' } ) ] ),
			h( 'th', { scope: 'col', text: 'Symptom' } ),
			h( 'th', { scope: 'col', text: 'Alternative phrases' } ),
			h( 'th', { scope: 'col', text: 'Specialties' } ),
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

			if ( state.can.review ) {
				var own = m.updated_by === state.userId;

				if ( 'draft' === m.status ) {
					actions.appendChild( btn( 'Mark reviewed', 'dak-button-secondary', function ( e ) {
						post( 'review', { id: m.id }, e.currentTarget );
					}, {
						disabled: own || m.invalid_specialties.length > 0,
						title: own ? 'You edited this last — another reviewer must review it.' : ( m.invalid_specialties.length ? 'Fix the unknown specialty first (Edit).' : null ),
						'aria-label': 'Mark ' + m.name + ' reviewed',
					} ) );
				} else if ( 'reviewed' === m.status ) {
					actions.appendChild( btn( 'Activate', 'dak-button-primary', function ( e ) {
						post( 'activate', { id: m.id }, e.currentTarget );
					}, { 'aria-label': 'Activate ' + m.name } ) );
				} else {
					actions.appendChild( btn( 'Deactivate', 'dak-button-secondary', function ( e ) {
						post( 'deactivate', { id: m.id }, e.currentTarget );
					}, { 'aria-label': 'Deactivate ' + m.name } ) );
				}
			}

			if ( state.can.admin ) {
				actions.appendChild( btn( 'Delete', 'dak-button-danger-outline', function ( e ) {
					if ( window.confirm( 'Delete “' + m.name + '”? This cannot be undone.' ) ) { // eslint-disable-line no-alert
						post( 'delete', { id: m.id }, e.currentTarget );
					}
				}, { 'aria-label': 'Delete ' + m.name } ) );
			}

			var phrases = m.synonyms.slice( 0, 3 ).join( ', ' ) + ( m.synonyms.length > 3 ? ' +' + ( m.synonyms.length - 3 ) + ' more' : '' );

			body.appendChild( h( 'tr', {}, [
				h( 'td', { 'data-label': 'Select' }, [ check ] ),
				h( 'td', { class: 'dak-sm-name', 'data-label': 'Symptom' }, [
					h( 'strong', { text: m.name } ),
					sourceBadge( m ),
				] ),
				h( 'td', { 'data-label': 'Alternative phrases', text: phrases || '—' } ),
				h( 'td', { 'data-label': 'Specialties' }, [
					m.specialty_labels.join( ', ' ),
					m.invalid_specialties.length ? h( 'span', { class: 'dak-sm-warn', text: 'Unknown specialty: ' + m.invalid_specialties.join( ', ' ) } ) : null,
				] ),
				h( 'td', { 'data-label': 'Status' }, [ h( 'span', { class: 'dak-status-pill ' + STATUS[ m.status ].cls, text: STATUS[ m.status ].label } ) ] ),
				h( 'td', { class: 'dak-sm-when', 'data-label': 'Updated / reviewed' }, [
					h( 'span', { text: date( m.updated_at ) + ( m.updated_by_name ? ' · ' + m.updated_by_name : '' ) } ),
					m.reviewed_by_name ? h( 'span', { text: 'Reviewed ' + date( m.reviewed_at ) + ' · ' + m.reviewed_by_name } ) : null,
				] ),
				h( 'td', { 'data-label': 'Actions' }, [ actions ] ),
			] ) );
		} );

		table.appendChild( body );
		box.appendChild( h( 'div', { class: 'dak-sm-table-wrap' }, [ table ] ) );
	}

	/* ------------------------------------------------------------------
	 * Dialog (add/edit, import)
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

	function openEditor( m ) {
		var form = h( 'form', { class: 'dak-sm-form', novalidate: true } );
		var name = h( 'input', { type: 'text', id: 'dak-sm-name', class: 'dak-sm-input', value: m ? m.name : '', maxlength: '80', 'aria-describedby': 'dak-sm-name-help' } );
		var syn = h( 'textarea', { id: 'dak-sm-synonyms', class: 'dak-sm-input', rows: '5', value: m ? m.synonyms.join( '\n' ) : '', 'aria-describedby': 'dak-sm-synonyms-help' } );
		var notes = h( 'textarea', { id: 'dak-sm-notes', class: 'dak-sm-input', rows: '2', value: m ? m.notes : '' } );
		var chosen = m ? m.specialties.slice() : [];

		if ( m && 'draft' !== m.status ) {
			form.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'This mapping is ' + m.status + '. Saving a change returns it to Draft — it stops being used until a reviewer checks it again.' } ) );
		}

		if ( m && 'example' === m.source ) {
			form.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'This is an example written by the software team, not clinically reviewed.' } ) );
		}

		form.appendChild( field( 'Symptom name', name, 'How it appears to patients, e.g. “Stomach pain”.', 'name' ) );
		form.appendChild( field( 'Alternative phrases', syn, 'Other ways patients describe it — one per line. Include common misspellings and Urdu / Roman Urdu words, e.g. “pet dard”.', 'synonyms' ) );

		// Specialties: searchable checkbox list.
		var specFilter = h( 'input', { type: 'search', id: 'dak-sm-spec-filter', class: 'dak-sm-input', placeholder: 'Filter specialties', 'aria-controls': 'dak-sm-spec-list' } );
		var specList = h( 'div', { class: 'dak-sm-spec-list', id: 'dak-sm-spec-list', role: 'group', 'aria-label': 'Related specialties' } );

		Object.keys( state.specialties ).forEach( function ( slug ) {
			var id = 'dak-sm-spec-' + slug;
			var box = h( 'input', { type: 'checkbox', id: id, value: slug, checked: chosen.indexOf( slug ) !== -1 } );
			specList.appendChild( h( 'label', { class: 'dak-sm-spec', for: id, 'data-name': state.specialties[ slug ].toLowerCase() }, [ box, h( 'span', { text: state.specialties[ slug ] } ) ] ) );
		} );

		specFilter.addEventListener( 'input', function () {
			var q = specFilter.value.toLowerCase();
			Array.prototype.forEach.call( specList.children, function ( label ) {
				label.hidden = q && label.getAttribute( 'data-name' ).indexOf( q ) === -1;
			} );
		} );

		form.appendChild( h( 'div', { class: 'dak-sm-field' }, [
			h( 'label', { class: 'dak-sm-label', for: 'dak-sm-spec-filter', text: 'Related specialties' } ),
			h( 'p', { class: 'dak-sm-help', text: 'Which kinds of doctor patients with this symptom are shown. Choose at least one.' } ),
			specFilter,
			specList,
			h( 'p', { class: 'dak-sm-error', 'data-error': 'specialties', hidden: true } ),
		] ) );

		form.appendChild( field( 'Internal notes (optional)', notes, 'For reviewers only — never shown to patients.', 'notes' ) );

		var save = h( 'button', { type: 'submit', class: 'dak-button dak-button-primary', text: m ? 'Save changes' : 'Add as draft' } );
		form.appendChild( h( 'div', { class: 'dak-sm-actions' }, [ save, btn( 'Cancel', 'dak-button-secondary', closeDialog ) ] ) );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var specs = Array.prototype.filter.call( specList.querySelectorAll( 'input' ), function ( b ) {
				return b.checked;
			} ).map( function ( b ) {
				return b.value;
			} );

			form.querySelectorAll( '[data-error]' ).forEach( function ( el ) {
				el.hidden = true;
			} );

			post( 'save', { id: m ? m.id : 0, name: name.value, synonyms: syn.value, specialties: specs, notes: notes.value }, save ).then( function ( res ) {
				if ( res.success ) {
					closeDialog();
					return;
				}

				var errors = ( res.data && res.data.errors ) || {};
				var first = null;

				Object.keys( errors ).forEach( function ( k ) {
					var el = form.querySelector( '[data-error="' + k + '"]' );

					if ( el ) {
						el.textContent = errors[ k ];
						el.hidden = false;
						first = first || el.parentNode.querySelector( 'input, textarea' );
					}
				} );

				if ( first ) {
					first.focus();
				}
			} );
		} );

		openDialog( m ? 'Edit symptom' : 'Add symptom', form );
	}

	function openImport() {
		var wrap = h( 'div', { class: 'dak-sm-form' } );
		var text = h( 'textarea', { id: 'dak-sm-import', class: 'dak-sm-input dak-sm-mono', rows: '8', placeholder: 'Stomach pain | tummy pain, pet dard | Gastroenterologist\nFever | high temperature, bukhar | General Physician', 'aria-describedby': 'dak-sm-import-help' } );
		var out = h( 'div', { class: 'dak-sm-import-out', 'aria-live': 'polite' } );

		wrap.appendChild( field( 'Mappings to import', text, 'One per line: Symptom | alternative phrases (comma-separated) | specialties (comma-separated, names or slugs). A JSON list of { name, synonyms, specialties } also works. Everything is imported as Draft and must be reviewed.', '' ) );

		var check = btn( 'Check list', 'dak-button-primary', function ( e ) {
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
		openDialog( 'Import mappings', wrap );
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
				return all.checked || 'active' === m.status;
			} );
			var byId = {};
			pool.forEach( function ( m ) {
				byId[ m.id ] = m;
			} );

			var matcher = window.DAKSymptomMatch.create( pool );
			var r = matcher.analyse( input.value );
			var tag = function ( id ) {
				return byId[ id ].name + ( 'active' !== byId[ id ].status ? ' (' + STATUS[ byId[ id ].status ].label.toLowerCase() + ')' : '' );
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
		box.appendChild( h( 'label', { class: 'dak-sm-check', for: 'dak-sm-try-all' }, [ all, h( 'span', { text: 'Include drafts and reviewed mappings (not live yet)' } ) ] ) );
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
			box.appendChild( h( 'p', { class: 'dak-sm-alert', text: 'No clinical reviewers yet. Nothing can be reviewed or activated until you add at least one (two, if the same person also edits mappings).' } ) );
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
		renderPreview();
		renderReviewers();
		renderLegacy();
	}

	render();
}() );
