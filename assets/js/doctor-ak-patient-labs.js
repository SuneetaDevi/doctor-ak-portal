/**
 * Doctor AK Portal — the patient's "Lab tests" tab
 * (templates/dashboard/partials/patient-lab-tests.php).
 *
 * Booking form: the total follows the chosen lab and collection type; home
 * collection is only offered by labs that do it (with their fee); the
 * address box shows only for home collection; today's windows that have
 * already ended can't be picked. "Book tests" and "Cancel booking" post to
 * Patient_Lab_Handler and reload the tab to show the new state.
 */
( function () {
	'use strict';

	var settings = window.dakPatientLabs || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '.dak-labs' );

		if ( ! root ) {
			return;
		}

		var hour = parseInt( root.getAttribute( 'data-lab-hour' ), 10 ) || 0;

		Array.prototype.forEach.call( root.querySelectorAll( '[data-lab-book]' ), function ( form ) {
			wireBookingForm( form, hour );
		} );

		root.addEventListener( 'click', function ( event ) {
			var cancel = event.target.closest( '[data-lab-cancel]' );

			if ( ! cancel || ! window.confirm( settings.confirmCancel || 'Cancel this booking?' ) ) {
				return;
			}

			cancel.disabled = true;

			send( 'doctor_ak_patient_lab_cancel', { order_id: cancel.getAttribute( 'data-lab-cancel' ) }, cancel.closest( '[data-lab-order]' ), function () {
				cancel.disabled = false;
			} );
		} );
	} );

	function money( amount ) {
		return 'PKR ' + Math.round( amount ).toLocaleString( 'en-US' );
	}

	function checked( form, name ) {
		return form.querySelector( 'input[name="' + name + '"]:checked' );
	}

	function wireBookingForm( form, hour ) {
		var homeInput = form.querySelector( 'input[name="collection"][value="home"]' );
		var labInput = form.querySelector( 'input[name="collection"][value="lab"]' );
		var address = form.querySelector( '[data-lab-address]' );
		var total = form.querySelector( '[data-lab-total]' );

		function update() {
			var lab = checked( form, 'lab_id' );
			var offersHome = lab && '1' === lab.getAttribute( 'data-home' );

			// Home collection only where the chosen lab does it.
			if ( homeInput ) {
				homeInput.disabled = ! offersHome;

				if ( ! offersHome && homeInput.checked && labInput ) {
					labInput.checked = true;
				}
			}

			var isHome = homeInput && homeInput.checked;

			if ( address ) {
				address.classList.toggle( 'dak-hidden', ! isHome );
			}

			if ( total && lab ) {
				var amount = parseFloat( lab.getAttribute( 'data-subtotal' ) ) || 0;

				if ( isHome ) {
					amount += parseFloat( lab.getAttribute( 'data-home-fee' ) ) || 0;
				}

				total.textContent = money( amount );
			}

			// Today: only windows that haven't ended yet.
			var day = checked( form, 'date' );
			var isToday = day && '1' === day.getAttribute( 'data-today' );
			var firstOpen = null;

			Array.prototype.forEach.call( form.querySelectorAll( 'input[name="time_window"]' ), function ( input ) {
				var closed = isToday && hour >= ( parseInt( input.getAttribute( 'data-ends' ), 10 ) || 24 );

				input.disabled = closed;

				if ( ! closed && ! firstOpen ) {
					firstOpen = input;
				}
			} );

			var chosenWindow = checked( form, 'time_window' );

			if ( ( ! chosenWindow || chosenWindow.disabled ) && firstOpen ) {
				firstOpen.checked = true;
			}
		}

		form.addEventListener( 'change', update );
		update();

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var submit = form.querySelector( '[data-lab-submit]' );
			var data = {};

			Array.prototype.forEach.call( form.elements, function ( field ) {
				if ( ! field.name || field.disabled ) {
					return;
				}

				if ( ( 'radio' === field.type || 'checkbox' === field.type ) && ! field.checked ) {
					return;
				}

				data[ field.name ] = field.value;
			} );

			if ( submit ) {
				submit.disabled = true;
				submit.setAttribute( 'data-label', submit.textContent );
				submit.textContent = settings.booking || 'Booking…';
			}

			send( 'doctor_ak_patient_lab_book', data, form, function () {
				if ( submit ) {
					submit.disabled = false;
					submit.textContent = submit.getAttribute( 'data-label' );
				}
			} );
		} );
	}

	/**
	 * Posts an action; reloads the tab on success, shows the error in the
	 * order's card otherwise.
	 */
	function send( action, fields, card, onFail ) {
		var body = new FormData();

		body.append( 'action', action );
		body.append( 'nonce', settings.nonce || '' );

		Object.keys( fields ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );

		var error = card ? card.querySelector( '[data-lab-error]' ) : null;

		if ( error ) {
			error.classList.add( 'dak-hidden' );
		}

		fetch( settings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result && result.success ) {
					window.location.reload();
					return;
				}

				fail( ( result && result.data && result.data.message ) || settings.genericError );
			} )
			.catch( function () {
				fail( settings.genericError );
			} );

		function fail( message ) {
			if ( error ) {
				error.textContent = message || 'Something went wrong. Please try again.';
				error.classList.remove( 'dak-hidden' );
			}

			if ( onFail ) {
				onFail();
			}
		}
	}
} )();
