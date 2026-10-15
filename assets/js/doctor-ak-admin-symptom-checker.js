/**
 * Doctor AK Portal — dashboard Symptom Checker section (administrators).
 *
 * Draft editing (check / save / load demonstration rules), reviewers,
 * clinical approval and the on/off switch. Every action posts to
 * Symptom_Checker_Admin_Handler; status-changing ones reload the section so
 * the status panel is always what the server says.
 */
( function () {
	'use strict';

	var config = window.dakAdminSymptomChecker;
	var root = document.querySelector( '[data-admin-sc]' );

	if ( ! config || ! root ) {
		return;
	}

	var message = root.querySelector( '[data-admin-sc-message]' );
	var json = root.querySelector( '[data-admin-sc-json]' );
	var errorsBox = root.querySelector( '[data-admin-sc-errors]' );
	var errorList = root.querySelector( '[data-admin-sc-error-list]' );
	var dirty = false;

	function say( text, isError ) {
		message.textContent = text;
		message.hidden = ! text;
		message.classList.toggle( 'is-error', !! isError );
	}

	function showErrors( errors ) {
		errorList.textContent = '';
		( errors || [] ).forEach( function ( e ) {
			var li = document.createElement( 'li' );
			li.textContent = e;
			errorList.appendChild( li );
		} );
		errorsBox.hidden = ! ( errors && errors.length );
	}

	function post( action, fields, button ) {
		var body = new FormData();

		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		Object.keys( fields || {} ).forEach( function ( key ) {
			var value = fields[ key ];

			if ( Array.isArray( value ) ) {
				value.forEach( function ( v ) {
					body.append( key + '[]', v );
				} );
			} else {
				body.append( key, value );
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

				return res || { success: false, data: {} };
			} );
	}

	function reloadSoon() {
		window.setTimeout( function () {
			window.location.reload();
		}, 900 );
	}

	// The form editor (doctor-ak-admin-symptom-editor.js) when present,
	// otherwise the raw JSON box.
	function rulesJson() {
		return window.DAKSymptomEditor ? window.DAKSymptomEditor.toJSON() : json.value;
	}

	root.addEventListener( 'dak-sc-editor-change', function () {
		dirty = true;
	} );

	if ( json ) {
		json.addEventListener( 'input', function () {
			dirty = true;
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	}

	root.addEventListener( 'click', function ( event ) {
		var target = event.target.closest( 'button' );

		if ( ! target || ! root.contains( target ) ) {
			return;
		}

		if ( target.hasAttribute( 'data-admin-sc-validate' ) ) {
			post( 'doctor_ak_admin_symptom_rules_validate', { json: rulesJson() }, target ).then( function ( res ) {
				var data = res.data || {};
				showErrors( data.errors );
				say( ( data.message || '' ) + ( data.is_demo ? ' (This is the demonstration ruleset — it can be previewed but never approved.)' : '' ), ! res.success || ( data.errors && data.errors.length ) );
			} );
		}

		if ( target.hasAttribute( 'data-admin-sc-save' ) ) {
			post( 'doctor_ak_admin_symptom_rules_save', { json: rulesJson() }, target ).then( function ( res ) {
				var data = res.data || {};
				say( data.message || '', ! res.success );

				if ( res.success ) {
					dirty = false;
					showErrors( data.errors );
					reloadSoon();
				}
			} );
		}

		if ( target.hasAttribute( 'data-admin-sc-load-demo' ) ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( 'Replace the draft with the demonstration rules? Any unsaved or unapproved draft changes will be lost. Demonstration rules are for testing only and can never be shown to patients.' ) ) {
				return;
			}

			post( 'doctor_ak_admin_symptom_rules_load_demo', {}, target ).then( function ( res ) {
				say( ( res.data && res.data.message ) || '', ! res.success );

				if ( res.success ) {
					dirty = false;
					reloadSoon();
				}
			} );
		}

		if ( target.hasAttribute( 'data-admin-sc-save-reviewers' ) ) {
			var ids = Array.prototype.filter.call( root.querySelectorAll( '[data-admin-sc-reviewer]' ), function ( box ) {
				return box.checked;
			} ).map( function ( box ) {
				return box.value;
			} );

			post( 'doctor_ak_admin_symptom_rules_reviewers', { reviewers: ids }, target ).then( function ( res ) {
				say( ( res.data && res.data.message ) || '', ! res.success );

				if ( res.success ) {
					reloadSoon();
				}
			} );
		}

		if ( target.hasAttribute( 'data-admin-sc-approve' ) ) {
			var confirmBox = root.querySelector( '[data-admin-sc-approve-confirm]' );
			var note = root.querySelector( '[data-admin-sc-note]' );

			if ( ! confirmBox.checked ) {
				say( 'Please confirm that you have clinically reviewed the whole draft.', true );
				confirmBox.focus();
				return;
			}

			if ( ! note.value.trim() ) {
				say( 'Please add a short review note.', true );
				note.focus();
				return;
			}

			if ( dirty ) {
				say( 'There are unsaved changes. Save the draft first — approval applies to the saved draft.', true );
				return;
			}

			post( 'doctor_ak_admin_symptom_rules_approve', { confirm: '1', note: note.value }, target ).then( function ( res ) {
				var data = res.data || {};
				say( data.message || '', ! res.success );

				if ( data.errors ) {
					showErrors( data.errors );
				}

				if ( res.success ) {
					reloadSoon();
				}
			} );
		}
	} );

	var toggle = root.querySelector( '[data-admin-sc-toggle]' );

	if ( toggle ) {
		toggle.addEventListener( 'change', function () {
			var on = toggle.checked;

			// eslint-disable-next-line no-alert
			if ( on && ! window.confirm( 'Make the Symptom Checker available to patients, using the approved version?' ) ) {
				toggle.checked = false;
				return;
			}

			post( 'doctor_ak_admin_symptom_checker_toggle', { enabled: on ? '1' : '0' }, null ).then( function ( res ) {
				say( ( res.data && res.data.message ) || '', ! res.success );

				if ( res.success ) {
					reloadSoon();
				} else {
					toggle.checked = ! on;
				}
			} );
		} );
	}
}() );
