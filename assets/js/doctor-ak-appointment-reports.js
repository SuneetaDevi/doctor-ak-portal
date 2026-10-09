/**
 * Doctor AK Portal — "Reports" for an appointment (patient, doctor and admin
 * dashboards). Any element with `data-appointment-reports` and
 * `data-appointment-id` opens one shared dialog that lists the files shared
 * for that appointment and, while it is still open for changes, lets the
 * user add files (button or drag and drop) or remove their own.
 *
 * Files are opened through a permission-checked link
 * (Appointment_Reports_Handler::handle_view()); nothing is public.
 * Elements with `data-appointment-reports-count="<id>"` show the count and
 * are kept up to date. Settings come from window.dakAppointmentReports.
 */
( function () {
	'use strict';

	var cfg = window.dakAppointmentReports;

	if ( ! cfg ) {
		return;
	}

	var s = cfg.strings || {};
	var dialog = null;
	var current = null; // { id, trigger }
	var busy = false;

	var icons = {
		pdf: '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11.5 2.5H5.5a1.5 1.5 0 0 0-1.5 1.5v12a1.5 1.5 0 0 0 1.5 1.5h9a1.5 1.5 0 0 0 1.5-1.5V7z"/><path d="M11.5 2.5V7H16"/><path d="M7 11h6M7 14h4"/></svg>',
		image: '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><circle cx="7" cy="8" r="1.5"/><path d="M17.5 13.5l-4-4-3 3-2.5-2.5-5 5"/></svg>',
		upload: '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10 13V3.5M6 7.5l4-4 4 4"/><path d="M3.5 13v2a1.5 1.5 0 0 0 1.5 1.5h10a1.5 1.5 0 0 0 1.5-1.5v-2"/></svg>'
	};

	function fill( template, value ) {
		return String( template || '' ).replace( '%s', value );
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( undefined !== text ) {
			node.textContent = text;
		}

		return node;
	}

	function build() {
		dialog = el( 'div', 'dak-portal dak-modal dak-appt-reports-modal' );
		dialog.setAttribute( 'aria-hidden', 'true' );
		dialog.innerHTML =
			'<div class="dak-modal-overlay" data-reports-close></div>' +
			'<div class="dak-modal-dialog dak-modal-dialog-form" role="dialog" aria-modal="true" aria-labelledby="dak-appt-reports-title">' +
				'<div class="dak-modal-header">' +
					'<h2 id="dak-appt-reports-title"></h2>' +
					'<p class="dak-appt-reports-subtitle" data-reports-subtitle></p>' +
					'<button type="button" class="dak-modal-close" data-reports-close>&times;</button>' +
				'</div>' +
				'<div class="dak-modal-body">' +
					'<p class="dak-appt-reports-intro" data-reports-intro></p>' +
					'<div class="dak-appt-reports-drop" data-reports-drop>' +
						'<input type="file" multiple class="dak-visually-hidden" id="dak-appt-reports-input" data-reports-input>' +
						'<label for="dak-appt-reports-input" class="dak-button dak-button-primary dak-appt-reports-add">' + icons.upload + '<span data-reports-add-label></span></label>' +
						'<span class="dak-appt-reports-drop-hint" data-reports-drop-hint></span>' +
						'<span class="dak-appt-reports-limits" data-reports-limits></span>' +
					'</div>' +
					'<p class="dak-appt-reports-closed" data-reports-closed hidden></p>' +
					'<div class="dak-appt-reports-status" data-reports-status role="status" aria-live="polite"></div>' +
					'<ul class="dak-appt-reports-list" data-reports-list></ul>' +
					'<p class="dak-appt-reports-empty" data-reports-empty hidden></p>' +
				'</div>' +
				'<div class="dak-modal-footer"><button type="button" class="dak-button dak-button-secondary" data-reports-close></button></div>' +
			'</div>';

		document.body.appendChild( dialog );

		dialog.querySelector( '#dak-appt-reports-title' ).textContent = s.title || 'Reports';
		dialog.querySelector( '.dak-modal-close' ).setAttribute( 'aria-label', s.close || 'Close' );
		dialog.querySelector( '.dak-modal-footer [data-reports-close]' ).textContent = s.close || 'Close';
		dialog.querySelector( '[data-reports-add-label]' ).textContent = s.add || 'Add reports';
		dialog.querySelector( '[data-reports-drop-hint]' ).textContent = s.drop || '';
		dialog.querySelector( '[data-reports-limits]' ).textContent = s.limits || '';
		dialog.querySelector( '[data-reports-closed]' ).textContent = s.closed || '';

		var input = dialog.querySelector( '[data-reports-input]' );

		input.setAttribute( 'accept', cfg.accept || '' );
		input.addEventListener( 'change', function () {
			upload( Array.prototype.slice.call( input.files || [] ) );
			input.value = '';
		} );

		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-reports-close]' ) ) {
				close();
				return;
			}

			var remove = event.target.closest( '[data-reports-remove]' );

			if ( remove ) {
				removeReport( remove );
			}
		} );

		var drop = dialog.querySelector( '[data-reports-drop]' );

		[ 'dragenter', 'dragover' ].forEach( function ( type ) {
			drop.addEventListener( type, function ( event ) {
				event.preventDefault();
				drop.classList.add( 'is-dragging' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( type ) {
			drop.addEventListener( type, function ( event ) {
				event.preventDefault();
				drop.classList.remove( 'is-dragging' );
			} );
		} );

		drop.addEventListener( 'drop', function ( event ) {
			if ( event.dataTransfer && event.dataTransfer.files ) {
				upload( Array.prototype.slice.call( event.dataTransfer.files ) );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! dialog.classList.contains( 'is-open' ) ) {
				return;
			}

			if ( 'Escape' === event.key ) {
				close();
			} else if ( 'Tab' === event.key ) {
				trapFocus( event );
			}
		} );
	}

	function trapFocus( event ) {
		var items = Array.prototype.filter.call(
			dialog.querySelectorAll( 'button, a[href], label[for], input:not(.dak-visually-hidden)' ),
			function ( node ) {
				return node.offsetParent !== null;
			}
		);

		if ( ! items.length ) {
			return;
		}

		var first = items[0];
		var last = items[ items.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	function open( trigger ) {
		if ( ! dialog ) {
			build();
		}

		current = { id: trigger.getAttribute( 'data-appointment-id' ), trigger: trigger };

		dialog.querySelector( '[data-reports-subtitle]' ).textContent = trigger.getAttribute( 'data-appointment-label' ) || '';
		dialog.querySelector( '[data-reports-status]' ).textContent = '';
		dialog.querySelector( '[data-reports-list]' ).textContent = '';
		dialog.querySelector( '[data-reports-empty]' ).hidden = false;
		dialog.querySelector( '[data-reports-empty]' ).textContent = s.loading || '';
		dialog.querySelector( '[data-reports-drop]' ).hidden = true;
		dialog.querySelector( '[data-reports-closed]' ).hidden = true;

		dialog.classList.add( 'is-open' );
		dialog.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'dak-modal-open' );
		dialog.querySelector( '.dak-modal-close' ).focus();

		request( 'doctor_ak_appointment_reports_list', {} ).then( render ).catch( showError );
	}

	function close() {
		if ( ! dialog ) {
			return;
		}

		dialog.classList.remove( 'is-open' );
		dialog.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'dak-modal-open' );

		if ( current && current.trigger && document.contains( current.trigger ) ) {
			// A trigger inside a closed "More" menu can't take focus; use its menu toggle.
			var menu = current.trigger.closest( 'details' );
			var target = menu && ! menu.open ? menu.querySelector( 'summary' ) : current.trigger;

			if ( target ) {
				target.focus();
			}
		}

		current = null;
	}

	function request( action, fields, files ) {
		var body = new FormData();

		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		body.append( 'appointment_id', current ? current.id : '' );

		Object.keys( fields ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );

		( files || [] ).forEach( function ( file ) {
			body.append( 'reports[]', file, file.name );
		} );

		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( result && result.data && result.data.message ? result.data.message : ( s.genericError || '' ) );
				}

				return result.data;
			} );
	}

	function render( data ) {
		var list = dialog.querySelector( '[data-reports-list]' );
		var empty = dialog.querySelector( '[data-reports-empty]' );
		var drop = dialog.querySelector( '[data-reports-drop]' );
		var closedNote = dialog.querySelector( '[data-reports-closed]' );

		dialog.querySelector( '[data-reports-intro]' ).textContent = 'patient' === data.access ? s.intro : s.introStaff;

		drop.hidden = ! data.canAdd || data.remaining <= 0;
		closedNote.hidden = data.canAdd;
		list.textContent = '';

		data.reports.forEach( function ( report ) {
			var item = el( 'li', 'dak-appt-reports-item' );
			var icon = el( 'span', 'dak-appt-reports-icon is-' + report.type );
			var info = el( 'div', 'dak-appt-reports-info' );
			var link = el( 'a', 'dak-appt-reports-name', report.name );
			var meta = el( 'span', 'dak-appt-reports-meta', [ report.size, report.by, report.when ].filter( Boolean ).join( ' · ' ) );
			var actions = el( 'div', 'dak-appt-reports-actions' );
			var openLink = el( 'a', 'dak-button dak-button-secondary dak-button-sm', s.open || 'Open' );

			icon.innerHTML = icons[ report.type ] || icons.pdf;
			link.href = report.url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			openLink.href = report.url;
			openLink.target = '_blank';
			openLink.rel = 'noopener noreferrer';
			openLink.setAttribute( 'aria-label', ( s.open || 'Open' ) + ' ' + report.name );

			info.appendChild( link );
			info.appendChild( meta );
			actions.appendChild( openLink );

			if ( report.canRemove ) {
				var remove = el( 'button', 'dak-button dak-button-ghost dak-button-sm dak-appt-reports-remove', s.remove || 'Remove' );

				remove.type = 'button';
				remove.setAttribute( 'data-reports-remove', report.id );
				remove.setAttribute( 'data-name', report.name );
				remove.setAttribute( 'aria-label', ( s.remove || 'Remove' ) + ' ' + report.name );
				actions.appendChild( remove );
			}

			item.appendChild( icon );
			item.appendChild( info );
			item.appendChild( actions );
			list.appendChild( item );
		} );

		empty.hidden = data.reports.length > 0;
		empty.textContent = data.canAdd ? ( s.empty || '' ) : ( s.emptyClosed || '' );

		updateCounts( data.appointmentId, data.count );
	}

	function updateCounts( appointmentId, count ) {
		document.querySelectorAll( '[data-appointment-reports-count="' + appointmentId + '"]' ).forEach( function ( badge ) {
			badge.textContent = count > 0 ? String( count ) : '';
			badge.hidden = count <= 0;
		} );
	}

	function setStatus( lines, isError ) {
		var status = dialog.querySelector( '[data-reports-status]' );

		status.textContent = '';
		status.classList.toggle( 'is-error', !! isError );

		lines.filter( Boolean ).forEach( function ( line ) {
			status.appendChild( el( 'p', '', line ) );
		} );
	}

	function showError( error ) {
		dialog.querySelector( '[data-reports-empty]' ).hidden = true;
		setStatus( [ error && error.message ? error.message : s.genericError ], true );
	}

	var allowed = /\.(pdf|jpe?g|png|webp)$/i;

	function upload( files ) {
		if ( busy || ! files.length || ! current ) {
			return;
		}

		var problems = [];
		var ok = files.filter( function ( file ) {
			if ( ! allowed.test( file.name ) ) {
				problems.push( fill( s.badType, file.name ) );
				return false;
			}

			if ( file.size > cfg.maxBytes ) {
				problems.push( fill( s.tooLarge, file.name ) );
				return false;
			}

			return true;
		} );

		if ( ! ok.length ) {
			setStatus( problems, true );
			return;
		}

		busy = true;
		dialog.querySelector( '[data-reports-drop]' ).classList.add( 'is-busy' );
		setStatus( [ s.uploading ], false );

		request( 'doctor_ak_appointment_reports_upload', {}, ok ).then( function ( data ) {
			render( data );

			var lines = ( data.added || [] ).map( function ( name ) {
				return fill( s.uploaded, name );
			} ).concat( problems, data.errors || [] );

			setStatus( lines, ( data.errors || [] ).length + problems.length > 0 );
		} ).catch( showError ).then( function () {
			busy = false;
			dialog.querySelector( '[data-reports-drop]' ).classList.remove( 'is-busy' );
		} );
	}

	function removeReport( button ) {
		var name = button.getAttribute( 'data-name' ) || '';

		if ( busy || ! window.confirm( fill( s.confirmRemove, name ) ) ) {
			return;
		}

		busy = true;
		button.disabled = true;

		request( 'doctor_ak_appointment_reports_delete', { report_id: button.getAttribute( 'data-reports-remove' ) } ).then( function ( data ) {
			render( data );
			setStatus( [ fill( s.removed, name ) ], false );
			dialog.querySelector( '.dak-modal-close' ).focus();
		} ).catch( function ( error ) {
			button.disabled = false;
			showError( error );
		} ).then( function () {
			busy = false;
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-appointment-reports]' );

		if ( ! trigger ) {
			return;
		}

		event.preventDefault();

		// Close the row's "More" menu it came from, if any.
		var menu = trigger.closest( 'details[open]' );

		if ( menu ) {
			menu.removeAttribute( 'open' );
		}

		open( trigger );
	} );
}() );
