/**
 * Doctor AK Portal — Booking page ([book_appointment] shortcode).
 *
 * Doctor/service cards, an appointment-type segmented control, a month
 * calendar with availability dots, a slot-card grid grouped into
 * Morning/Afternoon/Evening, a running "Your Booking" summary sidebar, and
 * a Continue-to-confirm step revealing identity + notes + submit.
 */
( function () {
	'use strict';

	var form;
	var calendarMonth; // Date, first of the currently displayed month.
	var todayStr;
	var monthCache = {}; // 'doctorId:type:YYYY-MM' => { 'YYYY-MM-DD': { total, available } }

	document.addEventListener( 'DOMContentLoaded', function () {
		form = document.getElementById( 'dak-booking-form' );

		if ( ! form || ! window.dakBookingPage ) {
			return;
		}

		todayStr = formatDate( new Date() );
		calendarMonth = startOfMonth( new Date() );

		wireDoctorCards();
		wireSegmentedControl();
		wireCalendarNav();
		wireQuickDates();
		wireContinueButton();
		wireGuestToggle();
		wirePaymentChoice();
		wireSubmit();

		renderCalendar();
		updateServiceCards( getDoctorId(), getType() );
		applyIdentityState();
		updateSteps();
		updateSummary();

		if ( getDoctorId() ) {
			fetchMonthAvailability();
		}
	} );

	function getDoctorId() {
		return document.getElementById( 'dak-booking-doctor-id' ).value;
	}

	function getType() {
		return document.getElementById( 'dak-booking-type' ).value;
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
		fetchMonthAvailability();
		updateSteps();
		updateSummary();
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
					fetchMonthAvailability();
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

		show( document.getElementById( 'dak-booking-clinic-hint' ) );
		document.getElementById( 'dak-booking-clinic-hint' ).classList.toggle( 'dak-hidden', 'clinic' !== type );

		var serviceSection = document.getElementById( 'dak-booking-service-section' );

		if ( serviceSection ) {
			serviceSection.classList.toggle( 'dak-hidden', 'video' === type );
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
	 * Calendar
	 * ------------------------------------------------------------------- */

	function wireCalendarNav() {
		var prev = document.getElementById( 'dak-booking-cal-prev' );
		var next = document.getElementById( 'dak-booking-cal-next' );

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				calendarMonth = new Date( calendarMonth.getFullYear(), calendarMonth.getMonth() - 1, 1 );
				renderCalendar();

				if ( getDoctorId() ) {
					fetchMonthAvailability();
				}
			} );
		}

		if ( next ) {
			next.addEventListener( 'click', function () {
				calendarMonth = new Date( calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 1 );
				renderCalendar();

				if ( getDoctorId() ) {
					fetchMonthAvailability();
				}
			} );
		}
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
				var tomorrow = new Date();
				tomorrow.setDate( tomorrow.getDate() + 1 );
				jumpToDate( tomorrow );
			} );
		}
	}

	function jumpToDate( date ) {
		calendarMonth = startOfMonth( date );
		renderCalendar();

		if ( getDoctorId() ) {
			fetchMonthAvailability();
		}

		selectDate( formatDate( date ) );
	}

	function startOfMonth( date ) {
		return new Date( date.getFullYear(), date.getMonth(), 1 );
	}

	function formatDate( date ) {
		var year = date.getFullYear();
		var month = String( date.getMonth() + 1 ).padStart( 2, '0' );
		var day = String( date.getDate() ).padStart( 2, '0' );

		return year + '-' + month + '-' + day;
	}

	var monthLabels = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];

	function monthCacheKey() {
		return getDoctorId() + ':' + getType() + ':' + calendarMonth.getFullYear() + '-' + ( calendarMonth.getMonth() + 1 );
	}

	function fetchMonthAvailability() {
		var doctorId = getDoctorId();

		if ( ! doctorId ) {
			return;
		}

		var key = monthCacheKey();

		if ( monthCache[ key ] ) {
			renderCalendar();
			return;
		}

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_month_availability' );
		formData.append( 'nonce', window.dakBookingPage.nonce );
		formData.append( 'doctor_id', doctorId );
		formData.append( 'type', getType() );
		formData.append( 'year', calendarMonth.getFullYear() );
		formData.append( 'month', calendarMonth.getMonth() + 1 );

		fetch( window.dakBookingPage.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				monthCache[ key ] = ( result.success && result.data && result.data.days ) ? result.data.days : {};
				renderCalendar();
			} )
			.catch( function () {
				monthCache[ key ] = {};
				renderCalendar();
			} );
	}

	function renderCalendar() {
		var grid = document.getElementById( 'dak-booking-calendar-grid' );
		var title = document.getElementById( 'dak-booking-cal-title' );
		var prev = document.getElementById( 'dak-booking-cal-prev' );

		if ( ! grid ) {
			return;
		}

		if ( title ) {
			title.textContent = monthLabels[ calendarMonth.getMonth() ] + ' ' + calendarMonth.getFullYear();
		}

		var currentMonthStart = startOfMonth( new Date() );

		if ( prev ) {
			prev.disabled = calendarMonth.getTime() <= currentMonthStart.getTime();
		}

		grid.innerHTML = '';

		var firstWeekday = calendarMonth.getDay();
		var daysInMonth = new Date( calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 0 ).getDate();
		var selectedDate = document.getElementById( 'dak-booking-date' ).value;
		var days = monthCache[ monthCacheKey() ] || null;

		for ( var i = 0; i < firstWeekday; i++ ) {
			var empty = document.createElement( 'span' );
			empty.className = 'dak-booking-calendar-day dak-booking-calendar-day-empty';
			grid.appendChild( empty );
		}

		for ( var day = 1; day <= daysInMonth; day++ ) {
			var date = new Date( calendarMonth.getFullYear(), calendarMonth.getMonth(), day );
			var dateStr = formatDate( date );
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'dak-booking-calendar-day';

			var numberEl = document.createElement( 'span' );
			numberEl.textContent = String( day );
			button.appendChild( numberEl );

			if ( dateStr < todayStr ) {
				button.disabled = true;
			} else {
				button.addEventListener( 'click', function ( clickedDate ) {
					return function () {
						selectDate( clickedDate );
					};
				}( dateStr ) );

				if ( days && days[ dateStr ] ) {
					var dot = document.createElement( 'span' );
					dot.className = 'dak-booking-dot ' + availabilityDotClass( days[ dateStr ] );
					button.appendChild( dot );
				}
			}

			if ( dateStr === selectedDate ) {
				button.classList.add( 'is-selected' );
			}

			grid.appendChild( button );
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

	/* ---------------------------------------------------------------------
	 * Slot cards
	 * ------------------------------------------------------------------- */

	function resetDateSelection() {
		document.getElementById( 'dak-booking-date' ).value = '';
		document.getElementById( 'dak-booking-time' ).value = '';
		selectedSlotSurcharge = 0;
		hide( document.getElementById( 'dak-booking-slots-section' ) );
		hide( document.getElementById( 'dak-booking-details' ) );
		renderCalendar();
	}

	function selectDate( dateStr ) {
		var doctorId = getDoctorId();

		if ( ! doctorId ) {
			showFieldError( 'doctor_id', 'Please choose a doctor first.' );
			return;
		}

		clearFieldError( 'doctor_id' );

		document.getElementById( 'dak-booking-date' ).value = dateStr;
		document.getElementById( 'dak-booking-time' ).value = '';
		selectedSlotSurcharge = 0;
		hide( document.getElementById( 'dak-booking-details' ) );
		renderCalendar();
		updateSteps();
		updateSummary();

		var section = document.getElementById( 'dak-booking-slots-section' );
		var groups = document.getElementById( 'dak-booking-slots-groups' );
		var noSlots = document.getElementById( 'dak-booking-no-slots' );
		var dateLabel = document.getElementById( 'dak-booking-slots-date-label' );

		show( section );
		hide( noSlots );

		if ( dateLabel ) {
			dateLabel.textContent = formatDateLabel( dateStr );
		}

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
		var dateLabel = document.getElementById( 'dak-booking-slots-date-label' );

		groupsEl.innerHTML = '';

		if ( ! slots.length ) {
			show( noSlots );

			if ( dateLabel ) {
				dateLabel.textContent += ' · 0 slots';
			}

			return;
		}

		var availableCount = slots.filter( function ( slot ) { return 'available' === slot.status; } ).length;

		if ( dateLabel ) {
			dateLabel.textContent += ' · ' + availableCount + ' slot' + ( 1 === availableCount ? '' : 's' );
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

	var selectedSlotSurcharge = 0;

	function selectSlot( time, card, surcharge ) {
		document.getElementById( 'dak-booking-time' ).value = time;
		clearFieldError( 'time' );

		document.querySelectorAll( '.dak-booking-slot-card' ).forEach( function ( el ) {
			el.classList.remove( 'is-selected' );
		} );
		card.classList.add( 'is-selected' );

		selectedSlotSurcharge = surcharge || 0;

		updateSteps();
		updateSummary();
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
	 * Step indicator + summary sidebar
	 * ------------------------------------------------------------------- */

	function updateSteps() {
		var steps = document.querySelectorAll( '.dak-booking-step' );

		if ( ! steps.length ) {
			return;
		}

		var doctorDone = !! getDoctorId();
		var dateDone = !! document.getElementById( 'dak-booking-date' ).value;
		var timeDone = !! document.getElementById( 'dak-booking-time' ).value;

		setStepState( steps[ 0 ], doctorDone, ! doctorDone );
		setStepState( steps[ 1 ], dateDone, doctorDone && ! dateDone );
		setStepState( steps[ 2 ], timeDone, dateDone && ! timeDone );
	}

	function setStepState( stepEl, isComplete, isActive ) {
		if ( ! stepEl ) {
			return;
		}

		stepEl.classList.toggle( 'is-complete', isComplete );
		stepEl.classList.toggle( 'is-active', isActive );
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

		var anyVisible = false;

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
			anyVisible = true;
		} );

		hide( document.getElementById( 'dak-booking-summary-empty' ) );

		if ( ! anyVisible ) {
			show( document.getElementById( 'dak-booking-summary-empty' ) );
		}

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

		// "Pay Now" / "Pay Later" is a choice only for clinic (onsite) visits.
		// Video consultations always require payment upfront — a single
		// "Pay Now" button, no "Pay Later" option.
		var showChoice = 'clinic' === type && charge > 0;

		singleWrap.classList.toggle( 'dak-hidden', showChoice );
		choiceWrap.classList.toggle( 'dak-hidden', ! showChoice );

		if ( showChoice ) {
			return;
		}

		var isPaidVideo = 'video' === type && charge > 0;
		var paymentChoiceInput = document.getElementById( 'dak-booking-payment-choice' );
		var singleLabel = singleWrap.querySelector( '.dak-button-label' );

		if ( paymentChoiceInput ) {
			paymentChoiceInput.value = isPaidVideo ? 'now' : 'later';
		}

		if ( singleLabel ) {
			singleLabel.textContent = isPaidVideo ? 'Pay Now' : 'Book Consultation';
		}
	}

	/* ---------------------------------------------------------------------
	 * Continue-to-confirm + identity
	 * ------------------------------------------------------------------- */

	function wireContinueButton() {
		var button = document.getElementById( 'dak-booking-continue' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( ! getDoctorId() ) {
				showFieldError( 'doctor_id', 'Please choose a doctor.' );
				document.getElementById( 'dak-booking-doctor-cards' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
				return;
			}

			if ( ! document.getElementById( 'dak-booking-date' ).value || ! document.getElementById( 'dak-booking-time' ).value ) {
				showFieldError( 'time', 'Please choose a date and time.' );
				document.getElementById( 'dak-booking-slots-section' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
				return;
			}

			var details = document.getElementById( 'dak-booking-details' );
			show( details );
			applyIdentityState();
			details.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} );
	}

	function applyIdentityState() {
		var loggedInBlock = document.getElementById( 'dak-booking-identity-loggedin' );
		var choiceBlock = document.getElementById( 'dak-booking-identity-choice' );
		var guestBlock = document.getElementById( 'dak-booking-identity-guest' );

		if ( window.dakBookingPage.isLoggedIn ) {
			show( loggedInBlock );
			hide( choiceBlock );
			hide( guestBlock );

			document.getElementById( 'dak-booking-loggedin-name' ).value = window.dakBookingPage.user.name || '';
			document.getElementById( 'dak-booking-loggedin-email' ).value = window.dakBookingPage.user.email || '';
			document.getElementById( 'dak-booking-loggedin-phone' ).value = window.dakBookingPage.user.phone || '';
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
			hide( document.getElementById( 'dak-booking-success' ) );

			if ( ! getDoctorId() ) {
				showFieldError( 'doctor_id', 'Please choose a doctor.' );
				return;
			}

			if ( ! document.getElementById( 'dak-booking-date' ).value || ! document.getElementById( 'dak-booking-time' ).value ) {
				showFieldError( 'time', 'Please choose a date and time.' );
				return;
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
			formData.append( 'notes', document.getElementById( 'dak-booking-notes' ).value );
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

						var successAlert = document.getElementById( 'dak-booking-success' );

						if ( result.data.payment_url ) {
							successAlert.textContent = 'Redirecting you to complete payment…';
							show( successAlert );
							window.location.href = result.data.payment_url;
							return;
						}

						successAlert.textContent = result.data.message;
						show( successAlert );
						successAlert.scrollIntoView( { behavior: 'smooth', block: 'start' } );
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
