/**
 * Doctor AK Portal — Global booking modal.
 *
 * Opened via event delegation from any element carrying
 * `data-dak-book-appointment` (header links, directory cards, the
 * profile-view page, or a generic "Book Appointment" dashboard button),
 * optionally with `data-doctor-id`/`data-doctor-name` to pre-select a
 * doctor and `data-booking-type` to pre-select the Clinic/Video tab.
 */
( function () {
	'use strict';

	var modal;
	var form;

	document.addEventListener( 'DOMContentLoaded', function () {
		modal = document.getElementById( 'dak-booking-modal' );
		form = document.getElementById( 'dak-booking-form' );

		if ( ! modal || ! form ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-dak-book-appointment]' );

			if ( trigger ) {
				event.preventDefault();
				openModal( trigger );
				return;
			}

			if ( event.target.closest( '[data-dak-modal-close]' ) ) {
				closeModal();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal.classList.contains( 'is-open' ) ) {
				closeModal();
			}
		} );

		initDoctorSelect();
		initTabs();
		initGuestToggle();
		initSubmit();
	} );

	/**
	 * Opens the modal, applying any pre-selection from the trigger element.
	 *
	 * @param {HTMLElement} trigger The clicked element carrying data-dak-book-appointment.
	 */
	function openModal( trigger ) {
		resetForm();

		var doctorId = trigger.getAttribute( 'data-doctor-id' );
		var doctorName = trigger.getAttribute( 'data-doctor-name' );
		var videoDisabled = trigger.hasAttribute( 'data-video-disabled' );
		var bookingType = trigger.getAttribute( 'data-booking-type' );

		if ( doctorId ) {
			selectDoctor( doctorId, doctorName, videoDisabled );
		}

		if ( bookingType ) {
			setActiveTab( bookingType );
		}

		applyIdentityState();

		modal.classList.add( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'dak-modal-open' );
	}

	function closeModal() {
		modal.classList.remove( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'dak-modal-open' );
	}

	/**
	 * Resets the form back to its default state (clinic tab, no doctor,
	 * guest fields hidden, alerts cleared) so re-opening the modal from a
	 * different trigger doesn't carry over stale state.
	 */
	function resetForm() {
		form.reset();
		clearFieldErrors();
		hide( document.getElementById( 'dak-booking-error' ) );
		hide( document.getElementById( 'dak-booking-success' ) );
		hide( document.getElementById( 'dak-booking-identity-guest' ) );

		document.getElementById( 'dak-booking-doctor-id' ).value = '';
		document.getElementById( 'dak-booking-doctor-select' ).value = '';
		hide( document.getElementById( 'dak-booking-doctor-name' ) );
		show( document.getElementById( 'dak-booking-doctor-field' ) );

		setActiveTab( 'clinic' );
		hide( document.getElementById( 'dak-booking-video-unavailable' ) );

		var submitBtn = document.getElementById( 'dak-booking-submit' );
		submitBtn.disabled = false;
		submitBtn.classList.remove( 'is-loading' );
	}

	/**
	 * Pre-selects a doctor (from a directory card / profile page), hiding
	 * the doctor <select> and showing their name as fixed text instead.
	 *
	 * @param {string}  doctorId      Doctor user ID.
	 * @param {string}  doctorName    Doctor display name, e.g. "Dr. Jane Doe".
	 * @param {boolean} videoDisabled Whether this doctor doesn't offer video consultations.
	 */
	function selectDoctor( doctorId, doctorName, videoDisabled ) {
		document.getElementById( 'dak-booking-doctor-id' ).value = doctorId;

		var nameEl = document.getElementById( 'dak-booking-doctor-name' );
		nameEl.textContent = doctorName || '';
		show( nameEl );
		hide( document.getElementById( 'dak-booking-doctor-field' ) );

		updateVideoAvailability( videoDisabled );
	}

	/**
	 * Wires the doctor <select> (shown when no doctor was pre-selected) so
	 * choosing an option updates the hidden doctor_id field and re-checks
	 * video availability.
	 */
	function initDoctorSelect() {
		var select = document.getElementById( 'dak-booking-doctor-select' );

		if ( ! select ) {
			return;
		}

		select.addEventListener( 'change', function () {
			var option = select.options[ select.selectedIndex ];

			document.getElementById( 'dak-booking-doctor-id' ).value = select.value;
			updateVideoAvailability( option ? option.hasAttribute( 'data-video-disabled' ) : false );
		} );
	}

	/**
	 * Disables the Video tab (and falls back to Clinic if it was active)
	 * when the selected doctor doesn't offer video consultations.
	 *
	 * @param {boolean} videoDisabled Whether video should be unavailable.
	 */
	function updateVideoAvailability( videoDisabled ) {
		var videoTab = document.querySelector( '.dak-booking-tab[data-type="video"]' );
		var hint = document.getElementById( 'dak-booking-video-unavailable' );

		if ( ! videoTab ) {
			return;
		}

		videoTab.disabled = !! videoDisabled;
		videoTab.classList.toggle( 'is-disabled', !! videoDisabled );

		if ( videoDisabled ) {
			show( hint );

			if ( videoTab.classList.contains( 'is-active' ) ) {
				setActiveTab( 'clinic' );
			}
		} else {
			hide( hint );
		}
	}

	/**
	 * Wires the Clinic/Video tab buttons.
	 */
	function initTabs() {
		document.querySelectorAll( '.dak-booking-tab' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				if ( tab.disabled ) {
					return;
				}

				setActiveTab( tab.getAttribute( 'data-type' ) );
			} );
		} );
	}

	/**
	 * @param {string} type 'clinic' or 'video'.
	 */
	function setActiveTab( type ) {
		document.getElementById( 'dak-booking-type' ).value = type;

		document.querySelectorAll( '.dak-booking-tab' ).forEach( function ( tab ) {
			var isActive = tab.getAttribute( 'data-type' ) === type;
			tab.classList.toggle( 'is-active', isActive );
			tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		} );
	}

	/**
	 * Shows the logged-in patient's prefilled (read-only) identity block, or
	 * the login/register/guest choice block for logged-out visitors.
	 */
	function applyIdentityState() {
		var loggedInBlock = document.getElementById( 'dak-booking-identity-loggedin' );
		var choiceBlock = document.getElementById( 'dak-booking-identity-choice' );
		var guestBlock = document.getElementById( 'dak-booking-identity-guest' );

		if ( window.dakBooking && window.dakBooking.isLoggedIn ) {
			show( loggedInBlock );
			hide( choiceBlock );
			hide( guestBlock );

			document.getElementById( 'dak-booking-loggedin-name' ).value = window.dakBooking.user.name || '';
			document.getElementById( 'dak-booking-loggedin-email' ).value = window.dakBooking.user.email || '';
			document.getElementById( 'dak-booking-loggedin-phone' ).value = window.dakBooking.user.phone || '';
			return;
		}

		hide( loggedInBlock );
		show( choiceBlock );
		hide( guestBlock );

		if ( window.dakBooking ) {
			var loginLink = document.getElementById( 'dak-booking-login-link' );
			var registerLink = document.getElementById( 'dak-booking-register-link' );

			if ( loginLink && window.dakBooking.loginUrl ) {
				loginLink.href = window.dakBooking.loginUrl;
			}

			if ( registerLink && window.dakBooking.registerUrl ) {
				registerLink.href = window.dakBooking.registerUrl;
			}
		}
	}

	/**
	 * "Continue as Guest" reveals the manual name/email/phone fields.
	 */
	function initGuestToggle() {
		var button = document.getElementById( 'dak-booking-guest-toggle' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			hide( document.getElementById( 'dak-booking-identity-choice' ) );
			show( document.getElementById( 'dak-booking-identity-guest' ) );
		} );
	}

	/**
	 * Wires the booking form's AJAX submission.
	 */
	function initSubmit() {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( ! window.dakBooking ) {
				return;
			}

			clearFieldErrors();
			hide( document.getElementById( 'dak-booking-error' ) );
			hide( document.getElementById( 'dak-booking-success' ) );

			var submitBtn = document.getElementById( 'dak-booking-submit' );
			submitBtn.disabled = true;
			submitBtn.classList.add( 'is-loading' );

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_book_appointment' );
			formData.append( 'nonce', window.dakBooking.nonce );
			formData.append( 'doctor_id', document.getElementById( 'dak-booking-doctor-id' ).value );
			formData.append( 'type', document.getElementById( 'dak-booking-type' ).value );
			formData.append( 'date', document.getElementById( 'dak-booking-date' ).value );
			formData.append( 'time', document.getElementById( 'dak-booking-time' ).value );
			formData.append( 'notes', document.getElementById( 'dak-booking-notes' ).value );

			var guestBlockVisible = ! document.getElementById( 'dak-booking-identity-guest' ).classList.contains( 'dak-hidden' );

			if ( guestBlockVisible ) {
				formData.append( 'guest_name', document.getElementById( 'dak-booking-guest-name' ).value );
				formData.append( 'guest_email', document.getElementById( 'dak-booking-guest-email' ).value );
				formData.append( 'guest_phone', document.getElementById( 'dak-booking-guest-phone' ).value );
			}

			fetch( window.dakBooking.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitBtn.disabled = false;
					submitBtn.classList.remove( 'is-loading' );

					if ( result.success ) {
						form.querySelectorAll( 'input, select, textarea, button' ).forEach( function ( field ) {
							field.disabled = true;
						} );

						var successAlert = document.getElementById( 'dak-booking-success' );

						if ( result.data.payment_url ) {
							successAlert.textContent = 'Redirecting you to complete payment…';
							show( successAlert );
							window.location.href = result.data.payment_url;
							return;
						}

						successAlert.textContent = result.data.message;
						show( successAlert );
					} else if ( result.data && result.data.errors ) {
						Object.keys( result.data.errors ).forEach( function ( field ) {
							showFieldError( field, result.data.errors[ field ] );
						} );
					} else {
						var errorAlert = document.getElementById( 'dak-booking-error' );
						errorAlert.textContent = ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
						show( errorAlert );
					}
				} )
				.catch( function () {
					submitBtn.disabled = false;
					submitBtn.classList.remove( 'is-loading' );

					var errorAlert = document.getElementById( 'dak-booking-error' );
					errorAlert.textContent = 'Something went wrong. Please try again.';
					show( errorAlert );
				} );
		} );
	}

	function showFieldError( field, message ) {
		var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = message;
			el.classList.add( 'is-visible' );
		}
	}

	function clearFieldErrors() {
		form.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
			el.classList.remove( 'is-visible' );
		} );
	}

	function show( el ) {
		if ( el ) {
			el.classList.remove( 'dak-hidden' );
		}
	}

	function hide( el ) {
		if ( el ) {
			el.classList.add( 'dak-hidden' );
		}
	}
} )();
