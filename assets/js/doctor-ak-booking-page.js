/**
 * Doctor AK Portal — Booking page ([book_appointment] shortcode).
 *
 * A 6-step wizard: Doctor -> Service -> Personal Details -> Date & Time ->
 * Payment -> Confirmation. Only one step's card is visible at once (see
 * goToStep()); navigation between steps is explicit via "Next"/"Back"
 * buttons (data-wizard-next/data-wizard-back), never automatic. A vertical
 * sidebar step list mirrors the active/complete state of each step.
 */
( function () {
	'use strict';

	var form;
	var dateStripStart; // Date, first day of the visible 7-day date-strip window.
	var todayStr;
	var monthCache = {}; // 'doctorId:type:YYYY-M' => { 'YYYY-MM-DD': { total, available } }
	var selectedSlotSurcharge = 0;

	var STEP_KEYS = [ 'doctor', 'service', 'identity', 'datetime', 'payment', 'confirmation' ];

	var STEP_SECTION_IDS = {
		doctor: 'dak-booking-step-doctor',
		service: 'dak-booking-step-service',
		identity: 'dak-booking-step-identity',
		datetime: 'dak-booking-step-datetime',
		payment: 'dak-booking-step-payment',
		confirmation: 'dak-booking-step-confirmation',
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		form = document.getElementById( 'dak-booking-form' );

		if ( ! form || ! window.dakBookingPage ) {
			return;
		}

		todayStr = formatDate( new Date() );
		dateStripStart = new Date();

		wireDoctorCards();
		wireSegmentedControl();
		wireDateStripNav();
		wireQuickDates();
		wireWizardNav();
		wireGuestToggle();
		wirePaymentChoice();
		wireSubmit();

		renderDateStrip();
		updateServiceCards( getDoctorId(), getType() );
		applyIdentityState();
		updateSummary();
		updateServiceStepDoctorSummary();

		// A doctor is already preselected (booking triggered from that
		// doctor's card/profile), so skip straight to the Service step
		// instead of making the patient pick a doctor again.
		goToStep( getDoctorId() ? 'service' : 'doctor' );

		if ( getDoctorId() ) {
			fetchAndRenderDateStrip();
		}
	} );

	/**
	 * Mirrors the currently selected doctor's avatar/name into the small
	 * "Booking with Dr. X" summary shown atop the Service step — the only
	 * place a patient sees who they're booking once the Doctor step (1) is
	 * skipped.
	 */
	function updateServiceStepDoctorSummary() {
		var summary = document.getElementById( 'dak-booking-service-doctor-summary' );
		var doctorCard = document.querySelector( '[data-doctor-card].is-selected' );

		if ( ! summary ) {
			return;
		}

		if ( ! doctorCard ) {
			summary.classList.add( 'dak-hidden' );
			return;
		}

		document.getElementById( 'dak-booking-service-doctor-avatar' ).innerHTML = doctorCard.querySelector( '.dak-booking-doctor-avatar' ).innerHTML;
		document.getElementById( 'dak-booking-service-doctor-name' ).textContent = 'Dr. ' + doctorCard.getAttribute( 'data-doctor-name' );
		summary.classList.remove( 'dak-hidden' );
	}

	function getDoctorId() {
		return document.getElementById( 'dak-booking-doctor-id' ).value;
	}

	function getType() {
		return document.getElementById( 'dak-booking-type' ).value;
	}

	/* ---------------------------------------------------------------------
	 * Wizard navigation: only one step section is visible at a time, moved
	 * between explicitly via Back/Next buttons.
	 * ------------------------------------------------------------------- */

	function goToStep( key ) {
		STEP_KEYS.forEach( function ( stepKey ) {
			var section = document.getElementById( STEP_SECTION_IDS[ stepKey ] );

			if ( section ) {
				section.classList.toggle( 'dak-hidden', stepKey !== key );
			}
		} );

		updateSteps( key );
	}

	function keyForSectionId( sectionId ) {
		return STEP_KEYS.filter( function ( key ) {
			return STEP_SECTION_IDS[ key ] === sectionId;
		} )[ 0 ];
	}

	function wireWizardNav() {
		document.querySelectorAll( '[data-wizard-next]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var card = button.closest( '.dak-booking-card' );
				var currentKey = card ? keyForSectionId( card.id ) : null;

				if ( currentKey && ! validateStepBeforeNext( currentKey ) ) {
					return;
				}

				goToStep( button.getAttribute( 'data-wizard-next' ) );
			} );
		} );

		document.querySelectorAll( '[data-wizard-back]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				goToStep( button.getAttribute( 'data-wizard-back' ) );
			} );
		} );
	}

	function validateStepBeforeNext( key ) {
		clearFieldErrors();

		if ( 'doctor' === key ) {
			if ( ! getDoctorId() ) {
				showFieldError( 'doctor_id', 'Please choose a doctor.' );
				return false;
			}

			return true;
		}

		if ( 'identity' === key ) {
			var guestVisible = ! document.getElementById( 'dak-booking-identity-guest' ).classList.contains( 'dak-hidden' );

			if ( guestVisible ) {
				var name = document.getElementById( 'dak-booking-guest-name' ).value.trim();
				var email = document.getElementById( 'dak-booking-guest-email' ).value.trim();
				var phone = document.getElementById( 'dak-booking-guest-phone' ).value.trim();
				var ok = true;

				if ( ! name ) {
					showFieldError( 'guest_name', 'Please enter your name.' );
					ok = false;
				}

				if ( ! email ) {
					showFieldError( 'guest_email', 'Please enter a valid email address.' );
					ok = false;
				}

				if ( 'video' === getType() && ! phone ) {
					showFieldError( 'guest_phone', 'A phone number is required to book a video consultation.' );
					ok = false;
				}

				return ok;
			}

			if ( ! window.dakBookingPage.isLoggedIn ) {
				return false;
			}

			if ( 'video' === getType() && ! ( window.dakBookingPage.user && window.dakBookingPage.user.phone ) ) {
				updatePhoneRequirement();
				document.getElementById( 'dak-booking-loggedin-phone-missing' ).scrollIntoView( { behavior: 'smooth', block: 'center' } );
				return false;
			}

			return true;
		}

		if ( 'datetime' === key ) {
			var dateOk = !! document.getElementById( 'dak-booking-date' ).value;
			var timeOk = !! document.getElementById( 'dak-booking-time' ).value;

			if ( ! dateOk ) {
				showFieldError( 'date', 'Please choose an appointment date.' );
			}

			if ( ! timeOk ) {
				showFieldError( 'time', 'Please choose an appointment time.' );
			}

			return dateOk && timeOk;
		}

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Doctor cards
	 * ------------------------------------------------------------------- */

	function wireDoctorCards() {
		document.querySelectorAll( '[data-doctor-card]' ).forEach( function ( card ) {
			card.addEventListener( 'click', function () {
				selectDoctor( card );
			} );
		} );
	}

	function selectDoctor( card ) {
		document.querySelectorAll( '[data-doctor-card]' ).forEach( function ( el ) {
			el.classList.remove( 'is-selected' );
		} );
		card.classList.add( 'is-selected' );

		document.getElementById( 'dak-booking-doctor-id' ).value = card.getAttribute( 'data-doctor-id' );
		clearFieldError( 'doctor_id' );

		updateVideoAvailability( card.hasAttribute( 'data-video-disabled' ) );
		updateServiceCards( getDoctorId(), getType() );
		resetDateSelection();
		monthCache = {};
		fetchAndRenderDateStrip();
		updateSteps( 'doctor' );
		updateSummary();
		updateServiceStepDoctorSummary();
	}

	/* ---------------------------------------------------------------------
	 * Appointment type + service cards
	 * ------------------------------------------------------------------- */

	function wireSegmentedControl() {
		document.querySelectorAll( '.dak-booking-segment' ).forEach( function ( segment ) {
			segment.addEventListener( 'click', function () {
				if ( segment.disabled ) {
					return;
				}

				setType( segment.getAttribute( 'data-type' ) );
				updateServiceCards( getDoctorId(), getType() );
				resetDateSelection();
				monthCache = {};

				if ( getDoctorId() ) {
					fetchAndRenderDateStrip();
				}

				updateSummary();
			} );
		} );
	}

	function setType( type ) {
		document.getElementById( 'dak-booking-type' ).value = type;

		document.querySelectorAll( '.dak-booking-segment' ).forEach( function ( segment ) {
			var isActive = segment.getAttribute( 'data-type' ) === type;
			segment.classList.toggle( 'is-active', isActive );
			segment.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		} );

		document.getElementById( 'dak-booking-clinic-hint' ).classList.toggle( 'dak-hidden', 'clinic' !== type );

		var serviceSection = document.getElementById( 'dak-booking-service-section' );

		if ( serviceSection ) {
			serviceSection.classList.toggle( 'dak-hidden', 'video' === type );
		}

		updatePhoneRequirement();
	}

	/**
	 * Video consultations always require a phone number (they're always paid
	 * online). Toggles the guest phone field's required marker, and warns a
	 * logged-in patient with no phone on file before they hit submit.
	 */
	function updatePhoneRequirement() {
		var isVideo = 'video' === getType();
		var requiredMarker = document.getElementById( 'dak-booking-guest-phone-required' );

		if ( requiredMarker ) {
			requiredMarker.classList.toggle( 'dak-hidden', ! isVideo );
		}

		var missingPhoneNotice = document.getElementById( 'dak-booking-loggedin-phone-missing' );

		if ( missingPhoneNotice ) {
			var hasPhone = !! ( window.dakBookingPage.user && window.dakBookingPage.user.phone );
			missingPhoneNotice.classList.toggle( 'dak-hidden', ! isVideo || hasPhone );
		}
	}

	function updateVideoAvailability( videoDisabled ) {
		var videoSegment = document.querySelector( '.dak-booking-segment[data-type="video"]' );
		var hint = document.getElementById( 'dak-booking-video-unavailable' );

		if ( ! videoSegment ) {
			return;
		}

		videoSegment.disabled = !! videoDisabled;

		if ( videoDisabled ) {
			show( hint );

			if ( videoSegment.classList.contains( 'is-active' ) ) {
				setType( 'clinic' );
			}
		} else {
			hide( hint );
		}
	}

	function updateServiceCards( doctorId, type ) {
		var container = document.getElementById( 'dak-booking-service-cards' );

		if ( ! container ) {
			return;
		}

		document.getElementById( 'dak-booking-service-id' ).value = '';
		container.innerHTML = '';

		if ( ! doctorId ) {
			container.innerHTML = '<p class="dak-field-hint">Select a doctor to see their services.</p>';
			return;
		}

		if ( 'video' === type ) {
			updateVideoPriceCard( container, doctorId );
			return;
		}

		var services = window.dakBookingPage.services && window.dakBookingPage.services[ doctorId ]
			? window.dakBookingPage.services[ doctorId ][ type ]
			: null;

		if ( ! services || ! services.length ) {
			container.innerHTML = '<p class="dak-field-hint">No specific services configured — you can still book a general appointment.</p>';
			document.getElementById( 'dak-booking-service-id' ).value = '0';
			clearFieldError( 'service_id' );
			return;
		}

		services.forEach( function ( service, index ) {
			var card = document.createElement( 'button' );
			card.type = 'button';
			card.className = 'dak-booking-service-card';
			card.setAttribute( 'data-service-id', service.id );
			card.setAttribute( 'data-service-name', service.name );
			card.setAttribute( 'data-service-charge', service.charge );
			card.setAttribute( 'data-service-duration', service.duration_minutes || 0 );

			var nameEl = document.createElement( 'strong' );
			nameEl.textContent = service.name;

			var metaEl = document.createElement( 'span' );
			var metaParts = [];

			if ( service.duration_minutes > 0 ) {
				metaParts.push( service.duration_minutes + ' min' );
			}

			metaParts.push( service.charge > 0 ? 'PKR' + service.charge : 'Free' );
			metaEl.textContent = metaParts.join( ' · ' );

			card.appendChild( nameEl );
			card.appendChild( metaEl );

			card.addEventListener( 'click', function () {
				selectService( card );
			} );

			container.appendChild( card );

			if ( 0 === index ) {
				selectService( card );
			}
		} );
	}

	function updateVideoPriceCard( container, doctorId ) {
		var pricing = window.dakBookingPage.videoPricing ? window.dakBookingPage.videoPricing[ doctorId ] : null;

		if ( ! pricing || ! ( pricing.base_price > 0 ) ) {
			container.innerHTML = '<p class="dak-field-hint">No fixed price configured yet — you can still book a free video consultation.</p>';
			document.getElementById( 'dak-booking-service-id' ).value = '0';
			clearFieldError( 'service_id' );
			return;
		}

		var card = document.createElement( 'div' );
		card.className = 'dak-booking-service-card is-selected is-fixed-price';
		card.setAttribute( 'data-service-id', '0' );
		card.setAttribute( 'data-service-name', 'Video Consultation' );
		card.setAttribute( 'data-service-charge', pricing.final_price );
		card.setAttribute( 'data-service-duration', '0' );

		var nameEl = document.createElement( 'strong' );
		nameEl.textContent = 'Video Consultation';

		var metaEl = document.createElement( 'span' );

		if ( pricing.discount_active ) {
			var strike = document.createElement( 's' );
			strike.textContent = 'PKR' + pricing.base_price;

			metaEl.appendChild( strike );
			metaEl.appendChild( document.createTextNode(
				' PKR' + pricing.final_price + ' · ' + pricing.discount_percent + '% off, ends ' + pricing.discount_ends_at
			) );
		} else {
			metaEl.textContent = pricing.final_price > 0 ? 'PKR' + pricing.final_price : 'Free';
		}

		card.appendChild( nameEl );
		card.appendChild( metaEl );
		container.appendChild( card );

		document.getElementById( 'dak-booking-service-id' ).value = '0';
		clearFieldError( 'service_id' );
	}

	function selectService( card ) {
		document.querySelectorAll( '.dak-booking-service-card' ).forEach( function ( el ) {
			el.classList.remove( 'is-selected' );
		} );
		card.classList.add( 'is-selected' );

		document.getElementById( 'dak-booking-service-id' ).value = card.getAttribute( 'data-service-id' );
		clearFieldError( 'service_id' );
		updateSummary();
	}

	/* ---------------------------------------------------------------------
	 * Date & time: horizontal 7-day date-strip + slot cards.
	 * ------------------------------------------------------------------- */

	function wireDateStripNav() {
		var prevButtons = [ document.getElementById( 'dak-booking-cal-prev' ), document.getElementById( 'dak-booking-strip-prev' ) ];
		var nextButtons = [ document.getElementById( 'dak-booking-cal-next' ), document.getElementById( 'dak-booking-strip-next' ) ];

		prevButtons.forEach( function ( button ) {
			if ( button ) {
				button.addEventListener( 'click', function () {
					dateStripStart = addDays( dateStripStart, -7 );
					fetchAndRenderDateStrip();
				} );
			}
		} );

		nextButtons.forEach( function ( button ) {
			if ( button ) {
				button.addEventListener( 'click', function () {
					dateStripStart = addDays( dateStripStart, 7 );
					fetchAndRenderDateStrip();
				} );
			}
		} );
	}

	function wireQuickDates() {
		var todayBtn = document.getElementById( 'dak-booking-today-btn' );
		var tomorrowBtn = document.getElementById( 'dak-booking-tomorrow-btn' );

		if ( todayBtn ) {
			todayBtn.addEventListener( 'click', function () {
				jumpToDate( new Date() );
			} );
		}

		if ( tomorrowBtn ) {
			tomorrowBtn.addEventListener( 'click', function () {
				jumpToDate( addDays( new Date(), 1 ) );
			} );
		}
	}

	function jumpToDate( date ) {
		dateStripStart = new Date( date.getFullYear(), date.getMonth(), date.getDate() );
		fetchAndRenderDateStrip();

		if ( getDoctorId() ) {
			selectDate( formatDate( date ) );
		}
	}

	function addDays( date, days ) {
		return new Date( date.getFullYear(), date.getMonth(), date.getDate() + days );
	}

	function formatDate( date ) {
		var year = date.getFullYear();
		var month = String( date.getMonth() + 1 ).padStart( 2, '0' );
		var day = String( date.getDate() ).padStart( 2, '0' );

		return year + '-' + month + '-' + day;
	}

	var monthLabels = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
	var weekdayShort = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];

	function ensureMonthCached( year, month ) {
		var doctorId = getDoctorId();

		if ( ! doctorId ) {
			return Promise.resolve( {} );
		}

		var key = doctorId + ':' + getType() + ':' + year + '-' + month;

		if ( monthCache[ key ] ) {
			return Promise.resolve( monthCache[ key ] );
		}

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_month_availability' );
		formData.append( 'nonce', window.dakBookingPage.nonce );
		formData.append( 'doctor_id', doctorId );
		formData.append( 'type', getType() );
		formData.append( 'year', year );
		formData.append( 'month', month );

		return fetch( window.dakBookingPage.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				monthCache[ key ] = ( result.success && result.data && result.data.days ) ? result.data.days : {};
				return monthCache[ key ];
			} )
			.catch( function () {
				monthCache[ key ] = {};
				return monthCache[ key ];
			} );
	}

	function dayInfoFor( dateStr ) {
		var parts = dateStr.split( '-' );
		var key = getDoctorId() + ':' + getType() + ':' + parseInt( parts[ 0 ], 10 ) + '-' + parseInt( parts[ 1 ], 10 );

		return monthCache[ key ] ? monthCache[ key ][ dateStr ] : null;
	}

	function fetchAndRenderDateStrip() {
		if ( ! getDoctorId() ) {
			renderDateStrip();
			return;
		}

		var seen = {};
		var months = [];

		for ( var i = 0; i < 7; i++ ) {
			var d = addDays( dateStripStart, i );
			var mKey = d.getFullYear() + '-' + ( d.getMonth() + 1 );

			if ( ! seen[ mKey ] ) {
				seen[ mKey ] = true;
				months.push( { year: d.getFullYear(), month: d.getMonth() + 1 } );
			}
		}

		Promise.all( months.map( function ( m ) { return ensureMonthCached( m.year, m.month ); } ) )
			.then( renderDateStrip );
	}

	function renderDateStrip() {
		var strip = document.getElementById( 'dak-booking-date-strip' );
		var title = document.getElementById( 'dak-booking-cal-title' );
		var prevButtons = [ document.getElementById( 'dak-booking-cal-prev' ), document.getElementById( 'dak-booking-strip-prev' ) ];

		if ( ! strip ) {
			return;
		}

		if ( title ) {
			title.textContent = monthLabels[ dateStripStart.getMonth() ] + ' ' + dateStripStart.getFullYear();
		}

		var atStart = formatDate( dateStripStart ) <= todayStr;

		prevButtons.forEach( function ( button ) {
			if ( button ) {
				button.disabled = atStart;
			}
		} );

		strip.innerHTML = '';

		var selectedDate = document.getElementById( 'dak-booking-date' ).value;

		for ( var i = 0; i < 7; i++ ) {
			var date = addDays( dateStripStart, i );
			var dateStr = formatDate( date );

			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'dak-booking-date-strip-day';

			var dow = document.createElement( 'span' );
			dow.className = 'dak-booking-date-strip-dow';
			dow.textContent = weekdayShort[ date.getDay() ];
			button.appendChild( dow );

			var num = document.createElement( 'span' );
			num.className = 'dak-booking-date-strip-num';
			num.textContent = String( date.getDate() );
			button.appendChild( num );

			if ( dateStr < todayStr ) {
				button.disabled = true;
			} else {
				button.addEventListener( 'click', function ( clickedDate ) {
					return function () {
						selectDate( clickedDate );
					};
				}( dateStr ) );

				var info = dayInfoFor( dateStr );

				if ( info ) {
					var dot = document.createElement( 'span' );
					dot.className = 'dak-booking-dot ' + availabilityDotClass( info );
					button.appendChild( dot );
				}
			}

			if ( dateStr === selectedDate ) {
				button.classList.add( 'is-selected' );
			}

			strip.appendChild( button );
		}
	}

	function availabilityDotClass( dayInfo ) {
		if ( ! dayInfo.available ) {
			return 'is-full';
		}

		if ( dayInfo.available <= 2 ) {
			return 'is-few';
		}

		return 'is-many';
	}

	function resetDateSelection() {
		document.getElementById( 'dak-booking-date' ).value = '';
		document.getElementById( 'dak-booking-time' ).value = '';
		selectedSlotSurcharge = 0;
		document.getElementById( 'dak-booking-slots-groups' ).innerHTML = '';
		hide( document.getElementById( 'dak-booking-no-slots' ) );
		updateCurrentlySelected();
		renderDateStrip();
	}

	function selectDate( dateStr ) {
		var doctorId = getDoctorId();

		if ( ! doctorId ) {
			showFieldError( 'doctor_id', 'Please choose a doctor first.' );
			return;
		}

		clearFieldError( 'doctor_id' );
		clearFieldError( 'date' );

		document.getElementById( 'dak-booking-date' ).value = dateStr;
		document.getElementById( 'dak-booking-time' ).value = '';
		selectedSlotSurcharge = 0;
		renderDateStrip();
		updateCurrentlySelected();
		updateSummary();

		var groups = document.getElementById( 'dak-booking-slots-groups' );
		var noSlots = document.getElementById( 'dak-booking-no-slots' );

		hide( noSlots );
		groups.innerHTML = '<p>Loading times…</p>';

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_available_slots' );
		formData.append( 'nonce', window.dakBookingPage.nonce );
		formData.append( 'doctor_id', doctorId );
		formData.append( 'type', getType() );
		formData.append( 'date', dateStr );

		fetch( window.dakBookingPage.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				var slots = ( result.success && result.data && result.data.slots ) ? result.data.slots : [];
				renderSlotGroups( slots );
			} )
			.catch( function () {
				groups.innerHTML = '';
				show( noSlots );
			} );
	}

	var weekdayLabels = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];

	function formatDateLabel( dateStr ) {
		var parts = dateStr.split( '-' );
		var date = new Date( parseInt( parts[ 0 ], 10 ), parseInt( parts[ 1 ], 10 ) - 1, parseInt( parts[ 2 ], 10 ) );

		return weekdayLabels[ date.getDay() ] + ', ' + monthLabels[ date.getMonth() ] + ' ' + date.getDate();
	}

	function renderSlotGroups( slots ) {
		var groupsEl = document.getElementById( 'dak-booking-slots-groups' );
		var noSlots = document.getElementById( 'dak-booking-no-slots' );

		groupsEl.innerHTML = '';

		if ( ! slots.length ) {
			show( noSlots );
			return;
		}

		var groups = {
			morning: { label: 'Morning', slots: [] },
			afternoon: { label: 'Afternoon', slots: [] },
			evening: { label: 'Evening', slots: [] },
		};

		slots.forEach( function ( slot ) {
			var hour = parseInt( slot.time.split( ':' )[ 0 ], 10 );
			var bucket = hour < 12 ? 'morning' : ( hour < 17 ? 'afternoon' : 'evening' );
			groups[ bucket ].slots.push( slot );
		} );

		var renderedAny = false;

		[ 'morning', 'afternoon', 'evening' ].forEach( function ( bucketKey ) {
			var bucket = groups[ bucketKey ];
			var visibleSlots = bucket.slots;

			if ( ! visibleSlots.length ) {
				return;
			}

			renderedAny = true;

			var groupEl = document.createElement( 'div' );
			groupEl.className = 'dak-booking-slot-group';

			var labelEl = document.createElement( 'div' );
			labelEl.className = 'dak-booking-slot-group-label';
			labelEl.textContent = bucket.label;
			groupEl.appendChild( labelEl );

			var gridEl = document.createElement( 'div' );
			gridEl.className = 'dak-booking-slots-grid';

			visibleSlots.forEach( function ( slot ) {
				var card = document.createElement( 'button' );
				card.type = 'button';
				card.className = 'dak-booking-slot-card is-' + slot.status + ( slot.is_instant ? ' is-instant' : '' );
				card.textContent = formatTimeLabel( slot.time );

				if ( slot.is_instant ) {
					var badge = document.createElement( 'span' );
					badge.className = 'dak-booking-slot-instant-badge';
					badge.textContent = '⚡';
					card.appendChild( badge );
					card.title = 'Instant booking — +PKR' + slot.surcharge + ' surcharge applies';
				}

				if ( 'available' === slot.status ) {
					card.addEventListener( 'click', function () {
						selectSlot( slot.time, card, slot.is_instant ? slot.surcharge : 0 );
					} );
				} else {
					card.disabled = true;
				}

				gridEl.appendChild( card );
			} );

			groupEl.appendChild( gridEl );
			groupsEl.appendChild( groupEl );
		} );

		if ( ! renderedAny ) {
			show( noSlots );
		} else {
			hide( noSlots );
		}
	}

	function selectSlot( time, card, surcharge ) {
		document.getElementById( 'dak-booking-time' ).value = time;
		clearFieldError( 'time' );

		document.querySelectorAll( '.dak-booking-slot-card' ).forEach( function ( el ) {
			el.classList.remove( 'is-selected' );
		} );
		card.classList.add( 'is-selected' );

		selectedSlotSurcharge = surcharge || 0;

		updateCurrentlySelected();
		updateSummary();
	}

	function updateCurrentlySelected() {
		var bar = document.getElementById( 'dak-booking-currently-selected' );
		var text = document.getElementById( 'dak-booking-currently-selected-text' );
		var date = document.getElementById( 'dak-booking-date' ).value;
		var time = document.getElementById( 'dak-booking-time' ).value;

		if ( ! bar || ! text ) {
			return;
		}

		if ( date && time ) {
			text.textContent = formatDateLabel( date ) + ', ' + formatTimeLabel( time );
			show( bar );
		} else {
			hide( bar );
		}
	}

	function formatTimeLabel( time ) {
		var parts = time.split( ':' );
		var hours = parseInt( parts[ 0 ], 10 );
		var minutes = parts[ 1 ];
		var period = hours >= 12 ? 'PM' : 'AM';
		var hours12 = hours % 12;

		if ( 0 === hours12 ) {
			hours12 = 12;
		}

		return hours12 + ':' + minutes + ' ' + period;
	}

	/* ---------------------------------------------------------------------
	 * Step sidebar + payment-step summary
	 * ------------------------------------------------------------------- */

	function updateSteps( activeKey ) {
		var completeness = {
			doctor: !! getDoctorId(),
			service: !! document.getElementById( 'dak-booking-service-id' ).value,
			identity: window.dakBookingPage.isLoggedIn || ( !! document.getElementById( 'dak-booking-guest-name' ).value.trim() && !! document.getElementById( 'dak-booking-guest-email' ).value.trim() ),
			datetime: !! document.getElementById( 'dak-booking-date' ).value && !! document.getElementById( 'dak-booking-time' ).value,
			payment: false,
			confirmation: false,
		};

		STEP_KEYS.forEach( function ( key ) {
			var li = document.querySelector( '.dak-booking-wizard-step[data-step="' + key + '"]' );

			if ( ! li ) {
				return;
			}

			var isActive = key === activeKey;

			li.classList.toggle( 'is-active', isActive );
			li.classList.toggle( 'is-complete', ! isActive && completeness[ key ] );
		} );
	}

	function updateSummary() {
		var doctorCard = document.querySelector( '[data-doctor-card].is-selected' );
		var serviceCard = document.querySelector( '.dak-booking-service-card.is-selected' );
		var date = document.getElementById( 'dak-booking-date' ).value;
		var time = document.getElementById( 'dak-booking-time' ).value;
		var type = getType();
		var hasDoctor = !! doctorCard;

		var rows = {
			doctor: hasDoctor ? 'Dr. ' + doctorCard.getAttribute( 'data-doctor-name' ) : '',
			type: hasDoctor ? ( 'video' === type ? 'Online Video' : 'Clinic Visit' ) : '',
			service: ( hasDoctor && serviceCard ) ? serviceCard.getAttribute( 'data-service-name' ) + ( serviceCard.getAttribute( 'data-service-duration' ) > 0 ? ' · ' + serviceCard.getAttribute( 'data-service-duration' ) + ' min' : '' ) : '',
			date: date ? formatDateLabel( date ) : '',
			time: time ? formatTimeLabel( time ) : '',
			instant: ( time && selectedSlotSurcharge > 0 ) ? ( 'Instant booking fee: +PKR' + selectedSlotSurcharge ) : '',
		};

		Object.keys( rows ).forEach( function ( key ) {
			var row = document.querySelector( '[data-summary-row="' + key + '"]' );

			if ( ! row ) {
				return;
			}

			if ( ! rows[ key ] ) {
				hide( row );
				return;
			}

			row.querySelector( '[data-summary-value]' ).textContent = rows[ key ];
			show( row );
		} );

		var totalEl = document.getElementById( 'dak-booking-summary-total-amount' );
		var baseCharge = serviceCard ? parseFloat( serviceCard.getAttribute( 'data-service-charge' ) ) : 0;
		var charge = baseCharge + ( time ? selectedSlotSurcharge : 0 );
		totalEl.textContent = hasDoctor ? ( charge > 0 ? 'PKR' + charge : 'Free' ) : '—';

		updateCancellationNote( hasDoctor ? doctorCard.getAttribute( 'data-doctor-id' ) : '' );
		updatePaymentButtons( type, charge );
	}

	function updateCancellationNote( doctorId ) {
		var noteEl = document.getElementById( 'dak-booking-summary-cancellation-note' );

		if ( ! noteEl ) {
			return;
		}

		var rules = doctorId && window.dakBookingPage.bookingRules ? window.dakBookingPage.bookingRules[ doctorId ] : null;

		if ( ! rules ) {
			noteEl.textContent = 'Choose a doctor to see their cancellation policy.';
			return;
		}

		var hours = parseFloat( rules.cancel_refund_hours );

		noteEl.textContent = hours > 0
			? ( 'Free cancellation up to ' + hours + ' hour' + ( 1 === hours ? '' : 's' ) + ' before your appointment.' )
			: 'Free cancellation any time before your appointment starts.';
	}

	function updatePaymentButtons( type, charge ) {
		var singleWrap = document.getElementById( 'dak-booking-submit-single' );
		var choiceWrap = document.getElementById( 'dak-booking-submit-choice' );

		if ( ! singleWrap || ! choiceWrap ) {
			return;
		}

		// Both clinic (onsite) and video visits offer the same Pay Now / Pay
		// Later choice whenever there's a charge — a video consultation can
		// be booked unpaid too, staying "Pending Payment" (no Join Call link)
		// until the patient pays from their own dashboard.
		var showChoice = charge > 0;

		singleWrap.classList.toggle( 'dak-hidden', showChoice );
		choiceWrap.classList.toggle( 'dak-hidden', ! showChoice );

		if ( showChoice ) {
			var hint = document.getElementById( 'dak-booking-submit-choice-hint' );

			if ( hint ) {
				hint.textContent = 'video' === type
					? 'This appointment has a charge. Pay now online, or book now and pay later from your dashboard.'
					: 'This appointment has a charge. Pay now online, or book now and pay at the clinic.';
			}

			return;
		}

		var paymentChoiceInput = document.getElementById( 'dak-booking-payment-choice' );
		var singleLabel = singleWrap.querySelector( '.dak-button-label' );

		if ( paymentChoiceInput ) {
			paymentChoiceInput.value = 'later';
		}

		if ( singleLabel ) {
			singleLabel.textContent = 'Book Consultation';
		}
	}

	/* ---------------------------------------------------------------------
	 * Identity
	 * ------------------------------------------------------------------- */

	function applyIdentityState() {
		var loggedInBlock = document.getElementById( 'dak-booking-identity-loggedin' );
		var choiceBlock = document.getElementById( 'dak-booking-identity-choice' );
		var guestBlock = document.getElementById( 'dak-booking-identity-guest' );

		updatePhoneRequirement();

		if ( window.dakBookingPage.isLoggedIn ) {
			show( loggedInBlock );
			hide( choiceBlock );
			hide( guestBlock );

			document.getElementById( 'dak-booking-loggedin-name' ).value = window.dakBookingPage.user.name || '';
			document.getElementById( 'dak-booking-loggedin-email' ).value = window.dakBookingPage.user.email || '';
			document.getElementById( 'dak-booking-loggedin-phone' ).value = window.dakBookingPage.user.phone || '';

			var phoneLink = document.getElementById( 'dak-booking-loggedin-phone-missing-link' );

			if ( phoneLink && window.dakBookingPage.profileUrl ) {
				phoneLink.href = window.dakBookingPage.profileUrl;
			}

			return;
		}

		hide( loggedInBlock );
		show( choiceBlock );
		hide( guestBlock );

		var loginLink = document.getElementById( 'dak-booking-login-link' );
		var registerLink = document.getElementById( 'dak-booking-register-link' );

		if ( loginLink && window.dakBookingPage.loginUrl ) {
			loginLink.href = window.dakBookingPage.loginUrl;
		}

		if ( registerLink && window.dakBookingPage.registerUrl ) {
			registerLink.href = window.dakBookingPage.registerUrl;
		}
	}

	function wireGuestToggle() {
		var button = document.getElementById( 'dak-booking-guest-toggle' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			hide( document.getElementById( 'dak-booking-identity-choice' ) );
			show( document.getElementById( 'dak-booking-identity-guest' ) );
		} );
	}

	function wirePaymentChoice() {
		document.querySelectorAll( '#dak-booking-submit-choice [data-payment-choice]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				document.getElementById( 'dak-booking-payment-choice' ).value = button.getAttribute( 'data-payment-choice' );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Submit
	 * ------------------------------------------------------------------- */

	function wireSubmit() {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			clearFieldErrors();
			hide( document.getElementById( 'dak-booking-error' ) );

			if ( ! getDoctorId() ) {
				showFieldError( 'doctor_id', 'Please choose a doctor.' );
				goToStep( 'doctor' );
				return;
			}

			if ( ! document.getElementById( 'dak-booking-date' ).value || ! document.getElementById( 'dak-booking-time' ).value ) {
				showFieldError( 'time', 'Please choose a date and time.' );
				goToStep( 'datetime' );
				return;
			}

			var guestBlockVisibleForValidation = ! document.getElementById( 'dak-booking-identity-guest' ).classList.contains( 'dak-hidden' );

			if ( 'video' === getType() ) {
				if ( guestBlockVisibleForValidation ) {
					if ( ! document.getElementById( 'dak-booking-guest-phone' ).value.trim() ) {
						showFieldError( 'guest_phone', 'A phone number is required to book a video consultation.' );
						goToStep( 'identity' );
						return;
					}
				} else if ( window.dakBookingPage.isLoggedIn && ! ( window.dakBookingPage.user && window.dakBookingPage.user.phone ) ) {
					updatePhoneRequirement();
					goToStep( 'identity' );
					document.getElementById( 'dak-booking-loggedin-phone-missing' ).scrollIntoView( { behavior: 'smooth', block: 'center' } );
					return;
				}
			}

			var submitButtons = document.querySelectorAll( '#dak-booking-submit, #dak-booking-pay-later, #dak-booking-pay-now' );
			submitButtons.forEach( function ( button ) {
				button.disabled = true;
				button.classList.add( 'is-loading' );
			} );

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_book_appointment' );
			formData.append( 'nonce', window.dakBookingPage.nonce );
			formData.append( 'doctor_id', getDoctorId() );
			formData.append( 'type', getType() );
			formData.append( 'date', document.getElementById( 'dak-booking-date' ).value );
			formData.append( 'time', document.getElementById( 'dak-booking-time' ).value );
			formData.append( 'service_id', document.getElementById( 'dak-booking-service-id' ).value );
			formData.append( 'payment_choice', document.getElementById( 'dak-booking-payment-choice' ).value );

			var guestBlockVisible = ! document.getElementById( 'dak-booking-identity-guest' ).classList.contains( 'dak-hidden' );

			if ( guestBlockVisible ) {
				formData.append( 'guest_name', document.getElementById( 'dak-booking-guest-name' ).value );
				formData.append( 'guest_email', document.getElementById( 'dak-booking-guest-email' ).value );
				formData.append( 'guest_phone', document.getElementById( 'dak-booking-guest-phone' ).value );
			}

			fetch( window.dakBookingPage.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitButtons.forEach( function ( button ) {
						button.disabled = false;
						button.classList.remove( 'is-loading' );
					} );

					if ( result.success ) {
						form.querySelectorAll( 'input, select, textarea, button' ).forEach( function ( field ) {
							field.disabled = true;
						} );

						goToStep( 'confirmation' );

						var successAlert = document.getElementById( 'dak-booking-success' );

						if ( result.data.payment_url ) {
							successAlert.textContent = 'Redirecting you to complete payment…';
							show( successAlert );
							window.location.href = result.data.payment_url;
							return;
						}

						successAlert.textContent = result.data.message;
						show( successAlert );

						if ( result.data.redirect_url ) {
							window.setTimeout( function () {
								window.location.href = result.data.redirect_url;
							}, 2500 );
						}
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
					submitButtons.forEach( function ( button ) {
						button.disabled = false;
						button.classList.remove( 'is-loading' );
					} );

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

	function clearFieldError( field ) {
		var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = '';
			el.classList.remove( 'is-visible' );
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
