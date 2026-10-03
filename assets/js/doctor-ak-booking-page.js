/**
 * Doctor AK Portal — Booking page ([book_appointment] shortcode).
 *
 * Four steps — Doctor & visit -> Date & time -> Your details -> Review &
 * payment — then a separate confirmation result. Times come before personal
 * details so a patient can see availability first. One step is visible at a
 * time; Continue validates the current step, Back never does, and completed
 * steps can be reopened from the progress bar, the summary or the review.
 *
 * Every choice lives in one `state` object (see below); the hidden inputs,
 * the visible selection, the booking summary, the review and the submitted
 * payload are all rendered FROM it, so they can't drift apart. Changing an
 * earlier choice clears only what it invalidates (e.g. a new visit type
 * clears the chosen time, but the clinic chosen for each doctor is
 * remembered across clinic -> video -> clinic).
 *
 * Availability requests are tagged with the selection they were made for;
 * a response that arrives after the doctor/type/clinic/services/date changed
 * is discarded instead of replacing newer results.
 *
 * Server contract is unchanged: same AJAX actions, nonce and field names
 * (doctor_id, type, service_id + service_ids[], clinic_id, date, time,
 * payment_choice, patient_id, guest_*). Booking_Handler stays the authority
 * on validation, prices and status — the client only mirrors its rules so
 * patients find problems before submitting.
 */
( function () {
	'use strict';

	var STEP_KEYS = [ 'selection', 'schedule', 'identity', 'review' ];

	var STEP_SECTION_IDS = {
		selection: 'dak-booking-step-selection',
		schedule: 'dak-booking-step-schedule',
		identity: 'dak-booking-step-identity',
		review: 'dak-booking-step-review',
		confirmation: 'dak-booking-step-confirmation',
	};

	// Which step owns each server-side field error, so a rejected submit
	// lands the patient exactly where the problem is.
	var FIELD_STEP = {
		doctor_id: 'selection',
		service_id: 'selection',
		clinic_id: 'selection',
		date: 'schedule',
		time: 'schedule',
		patient_id: 'identity',
		guest_name: 'identity',
		guest_email: 'identity',
		guest_phone: 'identity',
		payment_choice: 'review',
	};

	var SERVICE_PREVIEW_COUNT = 6;

	var cfg;
	var form;
	var todayStr;

	var state = {
		step: 'selection',
		maxStep: 0, // Index into STEP_KEYS of the furthest step reached.
		doctorId: 0,
		type: 'clinic',
		clinicId: '',
		clinicByDoctor: {}, // doctorId => clinic id last chosen for that doctor.
		serviceIds: [], // Strings, in the order they were chosen.
		date: '',
		time: '',
		slotSurcharge: 0,
		slots: null, // Last slot list rendered for state.date, or null.
		weekStart: null, // Date — first day of the visible 7-day window.
		paymentChoice: '', // '' until chosen when a choice is offered.
		showAllServices: false,
		submitting: false,
		restoreTime: '', // Time to reselect once a restored date's slots load (login return).
	};

	var monthCache = {}; // 'doctor:type:YYYY-M' => { 'YYYY-MM-DD': { total, available } }
	var slotsSeq = 0;
	var monthSeq = 0;

	document.addEventListener( 'DOMContentLoaded', function () {
		form = document.getElementById( 'dak-booking-form' );
		cfg = window.dakBookingPage;

		if ( ! form || ! cfg ) {
			return;
		}

		todayStr = cfg.today || formatDate( new Date() );
		state.weekStart = parseDate( todayStr );
		state.type = 'video' === valueOf( 'dak-booking-type' ) ? 'video' : 'clinic';
		state.doctorId = toInt( valueOf( 'dak-booking-doctor-id' ) );

		applyEntryPreselection();

		wireDoctorPicker();
		wireVisitType();
		wireServiceSearch();
		wireCalendar();
		wireNavigation();
		wireIdentity();
		wirePayment();
		wireSubmit();

		renderAll();
		goToStep( initialStep(), { focus: false, scroll: false } );
		wireSummaryPanel();

		if ( state.doctorId ) {
			refreshBookingRules( state.doctorId );
		}
	} );

	/* =====================================================================
	 * Initial state
	 * =================================================================== */

	/**
	 * Applies the validated entry-link preselection (Booking_Page::
	 * resolved_selection()), plus a date/time carried back from the login
	 * page, before anything renders — so the "auto-pick" defaults below
	 * never override something the patient already chose.
	 */
	function applyEntryPreselection() {
		var card = doctorCard( state.doctorId );

		if ( ! card ) {
			state.doctorId = 0;
		} else if ( card.hasAttribute( 'data-video-disabled' ) ) {
			state.type = 'clinic';
		}

		if ( state.doctorId && 'clinic' === state.type ) {
			var clinicId = cfg.selectedClinicId ? String( cfg.selectedClinicId ) : '';

			if ( clinicId && findClinic( state.doctorId, clinicId ) ) {
				state.clinicId = clinicId;
				state.clinicByDoctor[ state.doctorId ] = clinicId;
			}

			( cfg.selectedServiceIds || [] ).forEach( function ( id ) {
				if ( findService( state.doctorId, id ) ) {
					state.serviceIds.push( String( id ) );
				}
			} );
		}

		var params = window.URLSearchParams ? new URLSearchParams( window.location.search ) : null;

		if ( params && state.doctorId ) {
			var date = params.get( 'date' );
			var time = params.get( 'time' );

			if ( date && /^\d{4}-\d{2}-\d{2}$/.test( date ) && date >= todayStr ) {
				state.date = date;
				state.weekStart = parseDate( date );
				state.restoreTime = time && /^\d{2}:\d{2}$/.test( time ) ? time : '';
			}
		}
	}

	/**
	 * Which step to open at load. Skips Doctor & visit only when the entry
	 * link fully decided it (re-checked here against the same localized maps
	 * the step itself uses); goes further only when a date/time was carried
	 * back from the login page.
	 */
	function initialStep() {
		if ( ! stepComplete( 'selection' ) || ( ! cfg.selectionFullyKnown && ! state.date ) ) {
			return 'selection';
		}

		state.maxStep = Math.max( state.maxStep, 1 );

		return 'schedule';
	}

	/* =====================================================================
	 * Data helpers
	 * =================================================================== */

	function doctorCard( id ) {
		return id ? document.querySelector( '[data-doctor-card][data-doctor-id="' + id + '"]' ) : null;
	}

	function clinicsFor( doctorId ) {
		return ( cfg.clinics && cfg.clinics[ doctorId ] ) || [];
	}

	function findClinic( doctorId, clinicId ) {
		return clinicsFor( doctorId ).filter( function ( c ) { return String( c.id ) === String( clinicId ); } )[ 0 ] || null;
	}

	function servicesFor( doctorId ) {
		return ( cfg.services && cfg.services[ doctorId ] && cfg.services[ doctorId ].clinic ) || [];
	}

	function findService( doctorId, serviceId ) {
		return servicesFor( doctorId ).filter( function ( s ) { return String( s.id ) === String( serviceId ); } )[ 0 ] || null;
	}

	function videoPricing( doctorId ) {
		return ( cfg.videoPricing && cfg.videoPricing[ doctorId ] ) || null;
	}

	function videoOffered( doctorId ) {
		var card = doctorCard( doctorId );

		return !! card && ! card.hasAttribute( 'data-video-disabled' );
	}

	function selectedClinic() {
		return state.clinicId ? findClinic( state.doctorId, state.clinicId ) : null;
	}

	function clinicLocationId() {
		var clinic = selectedClinic();

		return clinic && clinic.clinic_location_id ? String( clinic.clinic_location_id ) : '';
	}

	/**
	 * A service is offered at every clinic when its clinic_charges map is
	 * empty, otherwise only at the clinic locations listed in it — the same
	 * rule Appointments::resolve_services() enforces server-side.
	 */
	function serviceOfferedAt( service, locationId ) {
		var charges = service.clinic_charges || {};

		if ( ! locationId || Array.isArray( charges ) || ! Object.keys( charges ).length ) {
			return true;
		}

		return Object.prototype.hasOwnProperty.call( charges, locationId );
	}

	/**
	 * A service's price for the current selection: { known, amount, from }.
	 * Unknown when it varies by clinic and no clinic is chosen yet — shown
	 * as "From PKR x" instead of guessing, and never confused with Free.
	 */
	function servicePrice( service ) {
		var charges = service.clinic_charges || {};
		var hasMap = ! Array.isArray( charges ) && Object.keys( charges ).length > 0;
		var locationId = clinicLocationId();

		if ( locationId && hasMap && Object.prototype.hasOwnProperty.call( charges, locationId ) ) {
			return { known: true, amount: parseFloat( charges[ locationId ] ) || 0 };
		}

		var needsClinic = hasMap && ! locationId && clinicsFor( state.doctorId ).length > 1;

		if ( needsClinic ) {
			var values = Object.keys( charges ).map( function ( k ) { return parseFloat( charges[ k ] ) || 0; } );
			values.push( parseFloat( service.charge ) || 0 );

			return { known: false, amount: Math.min.apply( null, values ), from: true };
		}

		return { known: true, amount: parseFloat( service.charge ) || 0 };
	}

	/**
	 * Visible services for the chosen clinic (or all, before a clinic is
	 * chosen / when the doctor has none).
	 */
	function servicesForSelection() {
		var locationId = clinicLocationId();

		return servicesFor( state.doctorId ).filter( function ( s ) { return serviceOfferedAt( s, locationId ); } );
	}

	/**
	 * Itemized charges for the current selection, mirroring
	 * Appointments::create(): clinic = sum of chosen services at the chosen
	 * clinic; video = the doctor's fixed (possibly discounted) price, plus the
	 * instant-booking surcharge — which the server applies to VIDEO bookings
	 * only, so it's never added to a clinic visit here either.
	 *
	 * @return {{lines: Array, total: number, known: boolean, ready: boolean}}
	 */
	function charges() {
		var lines = [];
		var total = 0;
		var known = true;

		if ( ! state.doctorId ) {
			return { lines: lines, total: 0, known: false, ready: false };
		}

		if ( 'video' === state.type ) {
			var pricing = videoPricing( state.doctorId );
			var base = pricing ? parseFloat( pricing.base_price ) || 0 : 0;
			var final = pricing ? parseFloat( pricing.final_price ) || 0 : 0;

			lines.push( { label: 'Video consultation', amount: base } );

			if ( pricing && pricing.discount_active && base > final ) {
				lines.push( { label: 'Discount (' + pricing.discount_percent + '% off)', amount: final - base, discount: true } );
			}

			total = final;

			if ( state.time && state.slotSurcharge > 0 ) {
				lines.push( { label: 'Instant booking fee', amount: state.slotSurcharge } );
				total += state.slotSurcharge;
			}

			return { lines: lines, total: total, known: true, ready: true };
		}

		if ( ! state.serviceIds.length ) {
			return { lines: lines, total: 0, known: false, ready: false };
		}

		state.serviceIds.forEach( function ( id ) {
			var service = findService( state.doctorId, id );

			if ( ! service ) {
				return;
			}

			var price = servicePrice( service );
			known = known && price.known;
			total += price.amount;
			lines.push( { label: service.name, amount: price.amount, known: price.known } );
		} );

		return { lines: lines, total: total, known: known, ready: true };
	}

	/* =====================================================================
	 * Formatting
	 * =================================================================== */

	function money( amount ) {
		var n = Number( amount ) || 0;
		var formatted = Math.abs( n ).toLocaleString( 'en-US', { maximumFractionDigits: 2 } );

		return ( n < 0 ? '− ' : '' ) + 'PKR ' + formatted;
	}

	function priceLabel( price ) {
		if ( ! price.known ) {
			return price.from ? 'From ' + money( price.amount ) : 'Price on selection';
		}

		return price.amount > 0 ? money( price.amount ) : 'Free';
	}

	var MONTHS = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
	var MONTHS_SHORT = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
	var DAYS = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
	var DAYS_SHORT = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];

	function parseDate( str ) {
		var p = String( str ).split( '-' );

		return new Date( parseInt( p[ 0 ], 10 ), parseInt( p[ 1 ], 10 ) - 1, parseInt( p[ 2 ], 10 ) );
	}

	function formatDate( date ) {
		return date.getFullYear() + '-' + String( date.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( date.getDate() ).padStart( 2, '0' );
	}

	function addDays( date, days ) {
		return new Date( date.getFullYear(), date.getMonth(), date.getDate() + days );
	}

	function dateLong( str ) {
		var d = parseDate( str );

		return DAYS_SHORT[ d.getDay() ] + ', ' + d.getDate() + ' ' + MONTHS_SHORT[ d.getMonth() ] + ' ' + d.getFullYear();
	}

	function timeLabel( time ) {
		var parts = String( time ).split( ':' );
		var h = parseInt( parts[ 0 ], 10 );
		var h12 = h % 12 || 12;

		return h12 + ':' + parts[ 1 ] + ' ' + ( h >= 12 ? 'PM' : 'AM' );
	}

	/** Same normalization as Swich_Payment::normalize_msisdn(). */
	function normalizeMsisdn( phone ) {
		var digits = String( phone || '' ).replace( /\D/g, '' );

		if ( ! digits ) {
			return '';
		}

		if ( 0 === digits.indexOf( '92' ) && 12 === digits.length ) {
			digits = '0' + digits.slice( 2 );
		} else if ( 0 !== digits.indexOf( '0' ) && 10 === digits.length ) {
			digits = '0' + digits;
		}

		return /^03\d{9}$/.test( digits ) ? digits : '';
	}

	function validEmail( email ) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
	}

	/* =====================================================================
	 * State transitions
	 * =================================================================== */

	function clearTime() {
		state.time = '';
		state.slotSurcharge = 0;
		state.restoreTime = '';
	}

	function clearDate() {
		state.date = '';
		state.slots = null;
		clearTime();
		slotsSeq++; // Any in-flight slot request is now outdated.
	}

	function selectDoctor( id ) {
		id = toInt( id );

		if ( id === state.doctorId ) {
			showDoctorPicker( false );
			return;
		}

		if ( state.doctorId && state.clinicId ) {
			state.clinicByDoctor[ state.doctorId ] = state.clinicId;
		}

		state.doctorId = id;
		state.serviceIds = [];
		state.showAllServices = false;
		state.clinicId = state.clinicByDoctor[ id ] || '';
		state.paymentChoice = '';
		clearDate();
		monthCache = {};

		if ( ! videoOffered( id ) ) {
			state.type = 'clinic';
		}

		autoPickClinic();
		autoPickService();
		clearFieldError( 'doctor_id' );
		showDoctorPicker( false );
		refreshBookingRules( id );
		renderAll();

		var heading = document.getElementById( 'dak-bk-visit' );

		if ( heading ) {
			var first = heading.querySelector( 'input:not([disabled])' );

			if ( first ) {
				first.focus( { preventScroll: true } );
			}
		}
	}

	function setType( type ) {
		if ( type === state.type ) {
			return;
		}

		if ( 'video' === type && ! videoOffered( state.doctorId ) ) {
			return;
		}

		if ( state.clinicId ) {
			state.clinicByDoctor[ state.doctorId ] = state.clinicId;
		}

		state.type = type;
		state.paymentChoice = '';
		// Availability differs by visit type — a time picked for one is never
		// silently carried over to the other.
		clearDate();

		if ( 'clinic' === type ) {
			state.clinicId = state.clinicByDoctor[ state.doctorId ] || state.clinicId || '';
			autoPickClinic();
		}

		clearFieldError( 'service_id' );
		clearFieldError( 'clinic_id' );
		renderAll();
	}

	function selectClinic( clinicId ) {
		clinicId = String( clinicId );

		if ( clinicId === state.clinicId ) {
			return;
		}

		state.clinicId = clinicId;
		state.clinicByDoctor[ state.doctorId ] = clinicId;
		clearFieldError( 'clinic_id' );

		// Keep only services this clinic offers; say so if any were dropped.
		var locationId = clinicLocationId();
		var dropped = [];

		state.serviceIds = state.serviceIds.filter( function ( id ) {
			var service = findService( state.doctorId, id );
			var ok = service && serviceOfferedAt( service, locationId );

			if ( service && ! ok ) {
				dropped.push( service.name );
			}

			return ok;
		} );

		autoPickService();
		invalidateAvailability();
		renderAll();

		if ( dropped.length ) {
			setNotice( 'dak-bk-no-services', dropped.join( ', ' ) + ( 1 === dropped.length ? ' isn\'t' : ' aren\'t' ) + ' offered at ' + ( selectedClinic() ? selectedClinic().name : 'this clinic' ) + ', so ' + ( 1 === dropped.length ? 'it was' : 'they were' ) + ' removed from your selection.', 'is-info' );
		}
	}

	function toggleService( id, on ) {
		id = String( id );
		var index = state.serviceIds.indexOf( id );

		if ( on && -1 === index ) {
			state.serviceIds.push( id );
		} else if ( ! on && -1 !== index ) {
			state.serviceIds.splice( index, 1 );
		}

		if ( state.serviceIds.length ) {
			clearFieldError( 'service_id' );
		}

		state.paymentChoice = '';
		// Slots aren't service-specific server-side today, but the chosen time
		// is re-confirmed against fresh availability when services change.
		invalidateAvailability();
		renderSummaryAndReview();
		renderProgress();
	}

	/** Only one clinic? It's the clinic — no need to ask. */
	function autoPickClinic() {
		if ( 'clinic' !== state.type || state.clinicId ) {
			return;
		}

		var clinics = clinicsFor( state.doctorId );

		if ( 1 === clinics.length ) {
			state.clinicId = String( clinics[ 0 ].id );
		}
	}

	/** Only one service on offer? Select it; otherwise the patient chooses. */
	function autoPickService() {
		if ( 'clinic' !== state.type || state.serviceIds.length ) {
			return;
		}

		var available = servicesForSelection();

		if ( 1 === available.length && ( state.clinicId || ! clinicsFor( state.doctorId ).length || 1 === clinicsFor( state.doctorId ).length ) ) {
			state.serviceIds = [ String( available[ 0 ].id ) ];
		}
	}

	/**
	 * Something availability may depend on changed: re-fetch the visible
	 * week and the chosen date's slots, keeping the chosen time only if it's
	 * still available in the fresh response.
	 */
	function invalidateAvailability() {
		monthCache = {};

		if ( state.date ) {
			// Keep the chosen time only if fresh availability still offers it
			// (re-applied in renderSlots()); until then nothing stale is shown.
			if ( state.time ) {
				state.restoreTime = state.time;
			}

			state.time = '';
			state.slotSurcharge = 0;
			state.slots = null;
			slotsSeq++;

			if ( 'schedule' === state.step ) {
				fetchSlots();
			}
		}

		if ( 'schedule' === state.step ) {
			fetchWeek();
		}
	}

	/* =====================================================================
	 * Doctor picker
	 * =================================================================== */

	function wireDoctorPicker() {
		var list = document.getElementById( 'dak-booking-doctor-cards' );
		var search = document.getElementById( 'dak-booking-doctor-search' );
		var spec = document.getElementById( 'dak-booking-doctor-specialization-filter' );
		var change = document.getElementById( 'dak-bk-change-doctor' );
		var clear = document.getElementById( 'dak-bk-clear-doctor-filters' );

		if ( list ) {
			list.addEventListener( 'click', function ( event ) {
				var card = event.target.closest( '[data-doctor-card]' );

				if ( card ) {
					selectDoctor( card.getAttribute( 'data-doctor-id' ) );
				}
			} );
		}

		if ( search ) {
			search.addEventListener( 'input', filterDoctors );
		}

		if ( spec ) {
			spec.addEventListener( 'change', filterDoctors );
		}

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				if ( search ) {
					search.value = '';
				}

				if ( spec ) {
					spec.value = '';
				}

				filterDoctors();

				if ( search ) {
					search.focus();
				}
			} );
		}

		if ( change ) {
			change.addEventListener( 'click', function () {
				var picker = document.getElementById( 'dak-bk-doctor-picker' );
				var changing = picker && picker.classList.contains( 'is-changing' );

				showDoctorPicker( ! changing );

				if ( ! changing && search ) {
					search.focus();
				} else {
					change.focus();
				}
			} );
		}

		applyLandingPrefill( spec );
		filterDoctors();
	}

	/**
	 * Carries over what the visitor typed into the home page's "Schedule your
	 * appointment" form: `specialization` narrows the doctor list, and
	 * name/email/phone prefill the guest fields so they aren't asked twice.
	 */
	function applyLandingPrefill( spec ) {
		if ( ! window.URLSearchParams ) {
			return;
		}

		var params = new URLSearchParams( window.location.search );

		[ [ 'name', 'dak-booking-guest-name' ], [ 'email', 'dak-booking-guest-email' ], [ 'phone', 'dak-booking-guest-phone' ] ].forEach( function ( pair ) {
			var value = params.get( pair[ 0 ] );
			var field = document.getElementById( pair[ 1 ] );

			if ( value && field && ! field.value ) {
				field.value = value;
			}
		} );

		var specialization = params.get( 'specialization' );

		if ( specialization && spec ) {
			spec.value = specialization;
		}
	}

	function filterDoctors() {
		var search = document.getElementById( 'dak-booking-doctor-search' );
		var spec = document.getElementById( 'dak-booking-doctor-specialization-filter' );
		var query = search ? search.value.trim().toLowerCase() : '';
		var specialization = spec ? spec.value : '';
		var shown = 0;
		var total = 0;

		document.querySelectorAll( '#dak-booking-doctor-cards [data-doctor-card]' ).forEach( function ( card ) {
			var item = card.closest( 'li' ) || card;
			var matches = ( '' === query || ( card.getAttribute( 'data-search-name' ) || '' ).indexOf( query ) !== -1 ) &&
				( '' === specialization || ( card.getAttribute( 'data-search-specializations' ) || '' ).split( ',' ).indexOf( specialization ) !== -1 );
			// The selected doctor always stays visible, pinned, even when the
			// current search would hide it.
			var isSelected = toInt( card.getAttribute( 'data-doctor-id' ) ) === state.doctorId;

			total++;
			item.classList.toggle( 'dak-hidden', ! matches && ! isSelected );
			item.classList.toggle( 'is-pinned', isSelected && ! matches );

			if ( matches ) {
				shown++;
			}
		} );

		var count = document.getElementById( 'dak-bk-doctor-count' );

		if ( count ) {
			count.textContent = ( query || specialization )
				? shown + ' of ' + total + ' doctors'
				: total + ' doctors';
		}

		toggle( document.getElementById( 'dak-booking-doctor-no-results' ), 0 === shown );
	}

	function showDoctorPicker( open ) {
		var picker = document.getElementById( 'dak-bk-doctor-picker' );

		if ( picker ) {
			picker.classList.toggle( 'dak-hidden', ! open && !! state.doctorId );
			picker.classList.toggle( 'is-changing', !! open && !! state.doctorId );
		}

		renderSelectedDoctor();
		filterDoctors();
	}

	function renderSelectedDoctor() {
		var wrap = document.getElementById( 'dak-bk-selected-doctor' );
		var card = doctorCard( state.doctorId );
		var picker = document.getElementById( 'dak-bk-doctor-picker' );
		var pickerOpen = picker && ! picker.classList.contains( 'dak-hidden' );

		document.querySelectorAll( '[data-doctor-card]' ).forEach( function ( el ) {
			var on = toInt( el.getAttribute( 'data-doctor-id' ) ) === state.doctorId;
			el.classList.toggle( 'is-selected', on );
			el.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );

		if ( ! wrap ) {
			return;
		}

		if ( ! card ) {
			wrap.classList.add( 'dak-hidden' );
			return;
		}

		wrap.classList.toggle( 'dak-hidden', !! pickerOpen && ! picker.classList.contains( 'is-changing' ) );
		document.getElementById( 'dak-bk-selected-doctor-name' ).textContent = 'Dr. ' + card.getAttribute( 'data-doctor-name' );
		document.getElementById( 'dak-bk-selected-doctor-spec' ).textContent = card.getAttribute( 'data-doctor-spec' ) || '';

		var avatar = document.getElementById( 'dak-bk-selected-doctor-avatar' );
		var avatarUrl = card.getAttribute( 'data-doctor-avatar' );
		avatar.innerHTML = '';

		if ( avatarUrl ) {
			var img = document.createElement( 'img' );
			img.src = avatarUrl;
			img.alt = '';
			avatar.appendChild( img );
		} else {
			avatar.textContent = card.getAttribute( 'data-doctor-initials' ) || '';
		}

		var change = document.getElementById( 'dak-bk-change-doctor' );

		if ( change ) {
			var changing = picker && picker.classList.contains( 'is-changing' );
			change.textContent = changing ? 'Keep this doctor' : 'Change doctor';
			change.setAttribute( 'aria-expanded', changing ? 'true' : 'false' );
		}
	}

	/* =====================================================================
	 * Visit type, clinics, services
	 * =================================================================== */

	function wireVisitType() {
		document.querySelectorAll( '.dak-booking-segment' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( radio.checked ) {
					setType( radio.getAttribute( 'data-type' ) );
				}
			} );
		} );

		var clinicCards = document.getElementById( 'dak-booking-clinic-cards' );

		if ( clinicCards ) {
			clinicCards.addEventListener( 'change', function ( event ) {
				if ( event.target.matches( 'input[type="radio"]' ) ) {
					selectClinic( event.target.value );
				}
			} );
		}

		var serviceCards = document.getElementById( 'dak-booking-service-cards' );

		if ( serviceCards ) {
			serviceCards.addEventListener( 'change', function ( event ) {
				if ( event.target.matches( '.dak-booking-service-checkbox' ) ) {
					toggleService( event.target.value, event.target.checked );
					event.target.closest( '.dak-bk-option' ).classList.toggle( 'is-selected', event.target.checked );
				}
			} );
		}

		var more = document.getElementById( 'dak-bk-service-more' );

		if ( more ) {
			more.addEventListener( 'click', function () {
				state.showAllServices = ! state.showAllServices;
				renderServices();
			} );
		}

		document.addEventListener( 'click', function ( event ) {
			var switcher = event.target.closest( '[data-bk-switch-video]' );

			if ( switcher ) {
				setType( 'video' );
			}
		} );
	}

	function wireServiceSearch() {
		var search = document.getElementById( 'dak-bk-service-search' );

		if ( search ) {
			search.addEventListener( 'input', renderServices );
		}
	}

	function renderVisit() {
		var visit = document.getElementById( 'dak-bk-visit' );

		toggle( visit, !! state.doctorId );

		if ( ! state.doctorId ) {
			return;
		}

		var videoOk = videoOffered( state.doctorId );

		document.querySelectorAll( '.dak-booking-segment' ).forEach( function ( radio ) {
			var type = radio.getAttribute( 'data-type' );

			radio.checked = type === state.type;

			if ( 'video' === type ) {
				radio.disabled = ! videoOk;
				radio.closest( '.dak-bk-segment' ).classList.toggle( 'is-disabled', ! videoOk );
			}

			radio.closest( '.dak-bk-segment' ).classList.toggle( 'is-active', type === state.type );
		} );

		var videoSub = document.getElementById( 'dak-bk-video-sub' );

		if ( videoSub ) {
			videoSub.textContent = videoOk ? 'From home' : 'Not offered';
		}

		toggle( document.getElementById( 'dak-booking-video-unavailable' ), ! videoOk );
		renderClinics();
		renderServices();
	}

	function renderClinics() {
		var section = document.getElementById( 'dak-booking-clinic-section' );
		var container = document.getElementById( 'dak-booking-clinic-cards' );
		var hint = document.getElementById( 'dak-booking-clinic-hint' );
		var clinics = clinicsFor( state.doctorId );
		var isClinic = 'clinic' === state.type && !! state.doctorId;

		toggle( section, isClinic && clinics.length > 0 );
		toggle( hint, isClinic && 0 === clinics.length );

		if ( ! container ) {
			return;
		}

		container.innerHTML = '';

		if ( ! isClinic || ! clinics.length ) {
			return;
		}

		clinics.forEach( function ( clinic ) {
			var id = 'dak-bk-clinic-' + clinic.id;
			var label = document.createElement( 'label' );
			label.className = 'dak-bk-option dak-bk-option-radio dak-booking-service-card' + ( String( clinic.id ) === state.clinicId ? ' is-selected' : '' );
			label.setAttribute( 'for', id );
			label.setAttribute( 'data-clinic-id', clinic.id );

			var input = document.createElement( 'input' );
			input.type = 'radio';
			input.name = 'dak_bk_clinic';
			input.id = id;
			input.value = clinic.id;
			input.checked = String( clinic.id ) === state.clinicId;

			var text = document.createElement( 'span' );
			text.className = 'dak-bk-option-text';

			var name = document.createElement( 'strong' );
			name.textContent = clinic.name;
			text.appendChild( name );

			var address = addressWithoutName( clinic.address, clinic.name );

			if ( address ) {
				var addr = document.createElement( 'span' );
				addr.textContent = address;
				text.appendChild( addr );
			}

			label.appendChild( input );
			label.appendChild( text );
			label.appendChild( checkIcon() );
			container.appendChild( label );
		} );
	}

	/**
	 * A clinic's composed address often starts with the clinic's own name
	 * (it's entered as the first address line) — strip that so the name
	 * isn't shown twice.
	 */
	function addressWithoutName( address, name ) {
		var a = String( address || '' ).trim();
		var n = String( name || '' ).trim();

		if ( n && 0 === a.toLowerCase().indexOf( n.toLowerCase() ) ) {
			a = a.slice( n.length ).replace( /^[\s,\-–—]+/, '' );
		}

		return a;
	}

	function renderServices() {
		var section = document.getElementById( 'dak-booking-service-section' );
		var container = document.getElementById( 'dak-booking-service-cards' );
		var searchWrap = document.getElementById( 'dak-bk-service-search-wrap' );
		var search = document.getElementById( 'dak-bk-service-search' );
		var more = document.getElementById( 'dak-bk-service-more' );
		var label = document.getElementById( 'dak-booking-service-label' );
		var legendHint = document.getElementById( 'dak-bk-service-legend-hint' );
		var notice = document.getElementById( 'dak-bk-no-services' );

		if ( ! container || ! state.doctorId ) {
			return;
		}

		container.innerHTML = '';
		hide( more );
		hide( searchWrap );
		hide( notice );
		toggle( section, true );

		if ( 'video' === state.type ) {
			label.textContent = 'Consultation fee';
			hide( legendHint );
			renderVideoFee( container );
			return;
		}

		label.textContent = 'Services';
		show( legendHint );

		var all = servicesFor( state.doctorId );

		if ( ! all.length ) {
			// Booking_Handler requires at least one service for a clinic
			// visit, so there's nothing bookable here — say so plainly and
			// offer the route that does work, instead of promising a
			// "general appointment" the server would reject.
			hide( legendHint );
			var doctorName = doctorCard( state.doctorId ) ? 'Dr. ' + doctorCard( state.doctorId ).getAttribute( 'data-doctor-name' ) : 'This doctor';
			var html = '<strong>' + escapeHtml( doctorName ) + ' hasn’t listed any clinic services for online booking yet</strong>, so a clinic visit can’t be booked here.';

			if ( videoOffered( state.doctorId ) ) {
				html += ' <button type="button" class="dak-button dak-button-secondary dak-button-sm" data-bk-switch-video>Book an online video consultation instead</button>';
			} else if ( cfg.contactUrl ) {
				html += ' <a href="' + escapeHtml( cfg.contactUrl ) + '">Contact the clinic</a> to arrange a visit.';
			} else {
				html += ' Please contact the clinic to arrange a visit.';
			}

			notice.className = 'dak-bk-notice is-warning';
			notice.innerHTML = html;
			show( notice );
			return;
		}

		if ( clinicsFor( state.doctorId ).length > 1 && ! state.clinicId ) {
			var pick = document.createElement( 'p' );
			pick.className = 'dak-field-hint';
			pick.textContent = 'Choose a clinic above to see its exact prices.';
			container.appendChild( pick );
		}

		var available = servicesForSelection();

		if ( ! available.length ) {
			notice.className = 'dak-bk-notice is-warning';
			notice.textContent = 'This doctor doesn’t offer any services at ' + ( selectedClinic() ? selectedClinic().name : 'this clinic' ) + ' — choose a different clinic.';
			show( notice );
			return;
		}

		var query = search ? search.value.trim().toLowerCase() : '';

		toggle( searchWrap, available.length > SERVICE_PREVIEW_COUNT );

		var matching = available.filter( function ( s ) {
			return '' === query || s.name.toLowerCase().indexOf( query ) !== -1;
		} );

		// Selected services always render, even when collapsed or filtered out.
		var visible = matching.filter( function ( s, i ) {
			return state.showAllServices || '' !== query || i < SERVICE_PREVIEW_COUNT;
		} );

		state.serviceIds.forEach( function ( id ) {
			var s = findService( state.doctorId, id );

			if ( s && visible.indexOf( s ) === -1 && available.indexOf( s ) !== -1 ) {
				visible.push( s );
			}
		} );

		visible.forEach( function ( service ) {
			container.appendChild( serviceOption( service ) );
		} );

		if ( ! matching.length ) {
			var none = document.createElement( 'p' );
			none.className = 'dak-field-hint';
			none.textContent = 'No services match “' + query + '”.';
			container.appendChild( none );
		}

		if ( '' === query && available.length > SERVICE_PREVIEW_COUNT ) {
			more.textContent = state.showAllServices ? 'Show fewer services' : 'Show all ' + available.length + ' services';
			more.setAttribute( 'aria-expanded', state.showAllServices ? 'true' : 'false' );
			show( more );
		}
	}

	function serviceOption( service ) {
		var id = 'dak-bk-service-' + service.id;
		var checked = state.serviceIds.indexOf( String( service.id ) ) !== -1;
		var price = servicePrice( service );

		var label = document.createElement( 'label' );
		label.className = 'dak-bk-option dak-bk-option-check dak-booking-service-card' + ( checked ? ' is-selected' : '' );
		label.setAttribute( 'for', id );
		label.setAttribute( 'data-service-id', service.id );

		var input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.className = 'dak-booking-service-checkbox';
		input.id = id;
		input.value = service.id;
		input.checked = checked;

		var text = document.createElement( 'span' );
		text.className = 'dak-bk-option-text';

		var name = document.createElement( 'strong' );
		name.textContent = service.name;
		text.appendChild( name );

		if ( parseInt( service.duration_minutes, 10 ) > 0 ) {
			var meta = document.createElement( 'span' );
			meta.textContent = service.duration_minutes + ' min';
			text.appendChild( meta );
		}

		var priceEl = document.createElement( 'span' );
		priceEl.className = 'dak-bk-option-price' + ( price.known && 0 === price.amount ? ' is-free' : '' );
		priceEl.textContent = priceLabel( price );

		label.appendChild( input );
		label.appendChild( text );
		label.appendChild( priceEl );

		return label;
	}

	function renderVideoFee( container ) {
		var pricing = videoPricing( state.doctorId );
		var base = pricing ? parseFloat( pricing.base_price ) || 0 : 0;
		var final = pricing ? parseFloat( pricing.final_price ) || 0 : 0;

		var box = document.createElement( 'div' );
		box.className = 'dak-bk-option is-selected is-static';

		var text = document.createElement( 'span' );
		text.className = 'dak-bk-option-text';
		text.innerHTML = '<strong>Video consultation</strong><span>A secure video call with the doctor</span>';

		var priceEl = document.createElement( 'span' );
		priceEl.className = 'dak-bk-option-price';

		if ( pricing && pricing.discount_active && base > final ) {
			priceEl.innerHTML = '<s>' + escapeHtml( money( base ) ) + '</s> ' + escapeHtml( final > 0 ? money( final ) : 'Free' ) +
				' <span class="dak-bk-badge">' + escapeHtml( pricing.discount_percent + '% off' ) + '</span>';
		} else {
			priceEl.textContent = final > 0 ? money( final ) : 'Free';
			priceEl.classList.toggle( 'is-free', ! ( final > 0 ) );
		}

		box.appendChild( text );
		box.appendChild( priceEl );
		container.appendChild( box );

		if ( pricing && pricing.discount_active && pricing.discount_ends_at ) {
			var ends = document.createElement( 'p' );
			ends.className = 'dak-field-hint';
			ends.textContent = 'Discount ends ' + pricing.discount_ends_at + '.';
			container.appendChild( ends );
		}
	}

	/* =====================================================================
	 * Calendar + slots
	 * =================================================================== */

	function wireCalendar() {
		var prev = document.getElementById( 'dak-booking-strip-prev' );
		var next = document.getElementById( 'dak-booking-strip-next' );
		var days = document.getElementById( 'dak-booking-date-strip' );
		var slotsEl = document.getElementById( 'dak-booking-slots-groups' );
		var statusEl = document.getElementById( 'dak-bk-slots-status' );

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				var target = addDays( state.weekStart, -7 );
				state.weekStart = formatDate( target ) < todayStr ? parseDate( todayStr ) : target;
				fetchWeek();
			} );
		}

		if ( next ) {
			next.addEventListener( 'click', function () {
				state.weekStart = addDays( state.weekStart, 7 );
				fetchWeek();
			} );
		}

		if ( days ) {
			days.addEventListener( 'click', function ( event ) {
				var day = event.target.closest( '[data-date]' );

				if ( day && ! day.disabled ) {
					selectDate( day.getAttribute( 'data-date' ) );
				}
			} );

			// Arrow keys move between days (roving within the strip).
			days.addEventListener( 'keydown', function ( event ) {
				if ( 'ArrowRight' !== event.key && 'ArrowLeft' !== event.key ) {
					return;
				}

				var buttons = Array.prototype.slice.call( days.querySelectorAll( 'button' ) );
				var index = buttons.indexOf( document.activeElement );

				if ( -1 === index ) {
					return;
				}

				event.preventDefault();
				var nextIndex = index + ( 'ArrowRight' === event.key ? 1 : -1 );

				if ( buttons[ nextIndex ] ) {
					buttons[ nextIndex ].focus();
				}
			} );
		}

		if ( slotsEl ) {
			slotsEl.addEventListener( 'click', function ( event ) {
				var slot = event.target.closest( '[data-slot-time]' );

				if ( slot && ! slot.disabled ) {
					selectSlot( slot.getAttribute( 'data-slot-time' ), parseFloat( slot.getAttribute( 'data-slot-surcharge' ) ) || 0 );
				}
			} );
		}

		if ( statusEl ) {
			statusEl.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '[data-bk-retry]' ) ) {
					fetchSlots();
				}

				var jump = event.target.closest( '[data-bk-jump]' );

				if ( jump ) {
					var date = jump.getAttribute( 'data-bk-jump' );
					state.weekStart = parseDate( date );
					fetchWeek();
					selectDate( date );
				}
			} );
		}
	}

	function monthKey( year, month ) {
		return state.doctorId + ':' + state.type + ':' + year + '-' + month;
	}

	function ensureMonth( year, month ) {
		var key = monthKey( year, month );

		if ( monthCache[ key ] ) {
			return Promise.resolve( monthCache[ key ] );
		}

		var body = new FormData();
		body.append( 'action', 'doctor_ak_month_availability' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'doctor_id', state.doctorId );
		body.append( 'type', state.type );
		body.append( 'year', year );
		body.append( 'month', month );

		return fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				var days = ( result && result.success && result.data && result.data.days ) ? result.data.days : null;

				if ( days ) {
					monthCache[ key ] = days;
				}

				return days;
			} )
			.catch( function () { return null; } );
	}

	/** Loads availability for the visible week, then renders it (latest request wins). */
	function fetchWeek() {
		renderWeek();

		if ( ! state.doctorId ) {
			return;
		}

		var seq = ++monthSeq;
		var months = {};

		for ( var i = 0; i < 7; i++ ) {
			var d = addDays( state.weekStart, i );
			months[ d.getFullYear() + '-' + ( d.getMonth() + 1 ) ] = [ d.getFullYear(), d.getMonth() + 1 ];
		}

		Promise.all( Object.keys( months ).map( function ( k ) { return ensureMonth( months[ k ][ 0 ], months[ k ][ 1 ] ); } ) )
			.then( function () {
				if ( seq === monthSeq ) {
					renderWeek();
					maybeAutoSelectDate();
				}
			} );
	}

	function dayInfo( dateStr ) {
		var d = parseDate( dateStr );
		var month = monthCache[ monthKey( d.getFullYear(), d.getMonth() + 1 ) ];

		if ( ! month ) {
			return { loaded: false };
		}

		var info = month[ dateStr ];

		return { loaded: true, total: info ? info.total : 0, available: info ? info.available : 0 };
	}

	function dayState( dateStr ) {
		if ( dateStr < todayStr ) {
			return 'past';
		}

		var info = dayInfo( dateStr );

		if ( ! info.loaded ) {
			return 'loading';
		}

		if ( ! info.total ) {
			return 'none';
		}

		if ( ! info.available ) {
			return 'full';
		}

		return info.available <= 2 ? 'few' : 'available';
	}

	var DAY_STATE_TEXT = {
		past: 'Past date',
		loading: 'Checking availability',
		none: 'Unavailable',
		full: 'Fully booked',
		few: 'Few times left',
		available: 'Available',
	};

	function renderWeek() {
		var strip = document.getElementById( 'dak-booking-date-strip' );
		var title = document.getElementById( 'dak-booking-cal-title' );
		var prev = document.getElementById( 'dak-booking-strip-prev' );

		if ( ! strip ) {
			return;
		}

		var end = addDays( state.weekStart, 6 );

		if ( title ) {
			title.textContent = state.weekStart.getMonth() === end.getMonth()
				? MONTHS[ state.weekStart.getMonth() ] + ' ' + state.weekStart.getFullYear()
				: MONTHS_SHORT[ state.weekStart.getMonth() ] + ' – ' + MONTHS_SHORT[ end.getMonth() ] + ' ' + end.getFullYear();
		}

		if ( prev ) {
			prev.disabled = formatDate( state.weekStart ) <= todayStr;
		}

		strip.innerHTML = '';

		for ( var i = 0; i < 7; i++ ) {
			var date = addDays( state.weekStart, i );
			var dateStr = formatDate( date );
			var dState = dayState( dateStr );
			var selected = dateStr === state.date;
			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'dak-bk-day is-' + dState + ( selected ? ' is-selected' : '' ) + ( dateStr === todayStr ? ' is-today' : '' );
			button.setAttribute( 'data-date', dateStr );
			button.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
			button.setAttribute( 'aria-label', DAYS[ date.getDay() ] + ' ' + date.getDate() + ' ' + MONTHS[ date.getMonth() ] + ', ' + DAY_STATE_TEXT[ dState ] + ( selected ? ', selected' : '' ) );
			// Past, unavailable and fully booked days can't be chosen; while
			// still loading a day stays clickable (its slots load on demand).
			button.disabled = 'past' === dState || 'none' === dState || 'full' === dState;

			button.innerHTML = '<span class="dak-bk-day-dow">' + ( dateStr === todayStr ? 'Today' : DAYS_SHORT[ date.getDay() ] ) + '</span>' +
				'<span class="dak-bk-day-num">' + date.getDate() + '</span>' +
				'<span class="dak-bk-day-mark" aria-hidden="true"></span>';

			strip.appendChild( button );
		}
	}

	/** On arriving at Date & time with no date yet, open the first day that has times. */
	function maybeAutoSelectDate() {
		if ( state.date || 'schedule' !== state.step ) {
			return;
		}

		for ( var i = 0; i < 7; i++ ) {
			var dateStr = formatDate( addDays( state.weekStart, i ) );
			var s = dayState( dateStr );

			if ( 'available' === s || 'few' === s ) {
				selectDate( dateStr, { quiet: true } );
				return;
			}
		}

		setSlotsStatus( 'empty-week' );
	}

	function selectDate( dateStr, opts ) {
		if ( ! state.doctorId ) {
			return;
		}

		if ( dateStr !== state.date ) {
			state.date = dateStr;
			clearTime();
		}

		clearFieldError( 'date' );
		renderWeek();
		renderPicked();
		renderSummaryAndReview();
		fetchSlots();

		if ( ! ( opts && opts.quiet ) ) {
			var title = document.getElementById( 'dak-bk-slots-title' );

			if ( title && window.matchMedia && window.matchMedia( '(max-width: 782px)' ).matches ) {
				scrollToEl( title );
			}
		}
	}

	function fetchSlots() {
		if ( ! state.doctorId || ! state.date ) {
			return;
		}

		var seq = ++slotsSeq;
		var request = { doctorId: state.doctorId, type: state.type, date: state.date };

		state.slots = null;
		document.getElementById( 'dak-booking-slots-groups' ).innerHTML = '';
		setSlotsStatus( 'loading' );

		var body = new FormData();
		body.append( 'action', 'doctor_ak_available_slots' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'doctor_id', request.doctorId );
		body.append( 'type', request.type );
		body.append( 'date', request.date );

		fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				// Discard anything answered for a selection that's since changed.
				if ( seq !== slotsSeq || request.doctorId !== state.doctorId || request.type !== state.type || request.date !== state.date ) {
					return;
				}

				if ( ! result || ! result.success ) {
					setSlotsStatus( 'error', result && result.data && result.data.message );
					return;
				}

				state.slots = ( result.data && result.data.slots ) || [];
				renderSlots();
			} )
			.catch( function () {
				if ( seq === slotsSeq ) {
					setSlotsStatus( 'error' );
				}
			} );
	}

	function setSlotsStatus( kind, message ) {
		var el = document.getElementById( 'dak-bk-slots-status' );

		if ( ! el ) {
			return;
		}

		el.className = 'dak-bk-slots-status' + ( kind ? ' is-' + kind : '' );

		if ( 'loading' === kind ) {
			el.innerHTML = '<span class="dak-bk-spinner" aria-hidden="true"></span> Loading available times…';
		} else if ( 'error' === kind ) {
			el.innerHTML = escapeHtml( message || 'We couldn’t load times for this date.' ) + ' <button type="button" class="dak-button dak-button-secondary dak-button-sm" data-bk-retry>Try again</button>';
		} else if ( 'empty' === kind ) {
			var next = nextAvailableDate( state.date );
			el.innerHTML = 'No open times on ' + escapeHtml( dateLong( state.date ) ) + '.' +
				( next ? ' <button type="button" class="dak-button dak-button-secondary dak-button-sm" data-bk-jump="' + next + '">Next available: ' + escapeHtml( dateLong( next ) ) + '</button>' : ' Try another date.' );
		} else if ( 'empty-week' === kind ) {
			el.innerHTML = 'No open times this week. Use the arrows to see later dates.';
		} else if ( 'choose' === kind ) {
			el.textContent = 'Choose a date to see available times.';
		} else {
			el.innerHTML = '';
		}
	}

	function nextAvailableDate( fromStr ) {
		var dates = [];

		Object.keys( monthCache ).forEach( function ( key ) {
			if ( 0 !== key.indexOf( state.doctorId + ':' + state.type + ':' ) ) {
				return;
			}

			Object.keys( monthCache[ key ] ).forEach( function ( d ) {
				if ( d > fromStr && monthCache[ key ][ d ].available > 0 ) {
					dates.push( d );
				}
			} );
		} );

		dates.sort();

		return dates[ 0 ] || '';
	}

	function renderSlots() {
		var groupsEl = document.getElementById( 'dak-booking-slots-groups' );
		var slots = state.slots || [];

		groupsEl.innerHTML = '';

		var bookable = slots.filter( function ( s ) { return 'available' === s.status; } );

		// Re-apply a time carried over (login return / earlier choice) only
		// if it's still genuinely available in this fresh response.
		if ( state.restoreTime ) {
			var keep = bookable.filter( function ( s ) { return s.time === state.restoreTime; } )[ 0 ];

			if ( keep ) {
				state.time = keep.time;
				state.slotSurcharge = keep.is_instant ? parseFloat( keep.surcharge ) || 0 : 0;
			} else {
				showStepAlert( 'schedule', 'The time you picked earlier (' + timeLabel( state.restoreTime ) + ') is no longer available. Please choose another time.' );
			}

			state.restoreTime = '';
		} else if ( state.time && ! bookable.some( function ( s ) { return s.time === state.time; } ) ) {
			clearTime();
		}

		if ( ! bookable.length ) {
			setSlotsStatus( 'empty' );
			renderPicked();
			renderSummaryAndReview();
			return;
		}

		setSlotsStatus( '' );

		var buckets = { Morning: [], Afternoon: [], Evening: [] };

		slots.forEach( function ( slot ) {
			var hour = parseInt( slot.time.split( ':' )[ 0 ], 10 );
			buckets[ hour < 12 ? 'Morning' : ( hour < 17 ? 'Afternoon' : 'Evening' ) ].push( slot );
		} );

		Object.keys( buckets ).forEach( function ( name ) {
			if ( ! buckets[ name ].length ) {
				return;
			}

			var group = document.createElement( 'div' );
			group.className = 'dak-bk-slot-group';
			group.setAttribute( 'role', 'group' );
			group.setAttribute( 'aria-label', name );

			var label = document.createElement( 'div' );
			label.className = 'dak-bk-slot-group-label';
			label.textContent = name;
			group.appendChild( label );

			var grid = document.createElement( 'div' );
			grid.className = 'dak-bk-slot-grid';

			buckets[ name ].forEach( function ( slot ) {
				// The instant surcharge only applies to video bookings
				// (Appointments::create()), so it's only shown for those.
				var surcharge = 'video' === state.type && slot.is_instant ? parseFloat( slot.surcharge ) || 0 : 0;
				var selected = slot.time === state.time;
				var btn = document.createElement( 'button' );

				btn.type = 'button';
				btn.className = 'dak-bk-slot is-' + slot.status + ( selected ? ' is-selected' : '' );
				btn.setAttribute( 'data-slot-time', slot.time );
				btn.setAttribute( 'data-slot-surcharge', surcharge );
				btn.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
				btn.disabled = 'available' !== slot.status;

				var statusText = 'available' === slot.status ? '' : ( 'booked' === slot.status ? 'Booked' : 'Past' );
				btn.setAttribute( 'aria-label', timeLabel( slot.time ) + ( statusText ? ', ' + statusText : '' ) + ( surcharge ? ', instant booking fee ' + money( surcharge ) : '' ) );
				btn.innerHTML = '<span class="dak-bk-slot-time">' + timeLabel( slot.time ) + '</span>' +
					( statusText ? '<span class="dak-bk-slot-note">' + statusText + '</span>' : '' ) +
					( surcharge ? '<span class="dak-bk-slot-note">+' + escapeHtml( money( surcharge ) ) + '</span>' : '' );

				grid.appendChild( btn );
			} );

			group.appendChild( grid );
			groupsEl.appendChild( group );
		} );

		renderPicked();
		renderSummaryAndReview();
		renderProgress();
	}

	function selectSlot( time, surcharge ) {
		// Only accept a time from the CURRENT slot list (never a stale card).
		var slot = ( state.slots || [] ).filter( function ( s ) { return s.time === time && 'available' === s.status; } )[ 0 ];

		if ( ! slot ) {
			return;
		}

		state.time = time;
		state.slotSurcharge = 'video' === state.type ? surcharge : 0;
		clearFieldError( 'time' );
		hideStepAlert();

		document.querySelectorAll( '#dak-booking-slots-groups [data-slot-time]' ).forEach( function ( btn ) {
			var on = btn.getAttribute( 'data-slot-time' ) === time;
			btn.classList.toggle( 'is-selected', on );
			btn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );

		renderPicked();
		renderSummaryAndReview();
		renderProgress();
	}

	function renderPicked() {
		var bar = document.getElementById( 'dak-booking-currently-selected' );
		var text = document.getElementById( 'dak-booking-currently-selected-text' );

		if ( ! bar || ! text ) {
			return;
		}

		if ( state.date && state.time ) {
			text.textContent = dateLong( state.date ) + ' at ' + timeLabel( state.time );
			show( bar );
		} else {
			text.textContent = '';
			hide( bar );
		}
	}

	/* =====================================================================
	 * Identity
	 * =================================================================== */

	function wireIdentity() {
		[ 'guest_name', 'guest_email', 'guest_phone' ].forEach( function ( field ) {
			var input = document.getElementById( 'dak-booking-' + field.replace( '_', '-' ) );

			if ( ! input ) {
				return;
			}

			// Typing, pasting and browser autofill all fire input/change —
			// each re-checks the field and clears its error once it's valid.
			[ 'input', 'change', 'blur' ].forEach( function ( eventName ) {
				input.addEventListener( eventName, function () {
					if ( input.getAttribute( 'aria-invalid' ) === 'true' || 'blur' === eventName && input.value.trim() ) {
						validateGuestField( field, 'blur' === eventName );
					}

					renderSummaryAndReview();
					renderProgress();
				} );
			} );
		} );

		wireStaffPatientPicker();
	}

	function guestMode() {
		return ! cfg.isLoggedIn && ! cfg.isStaff;
	}

	/** The chosen registered patient's ID (0 = "Add new patient"; the field starts as "0"). */
	function staffPatientId() {
		return toInt( valueOf( 'dak-booking-patient-id' ) );
	}

	function staffNewPatient() {
		return cfg.isStaff && ! staffPatientId();
	}

	/**
	 * Mirrors Booking_Handler: a phone is required for video, and for a clinic
	 * visit only when paying online. `ignorePayment` checks just the visit-type
	 * rule — the pay-now rule is reported on Review, where "Pay later" is the
	 * other way out.
	 */
	function phoneRequiredNow( ignorePayment ) {
		return 'video' === state.type || ( ! ignorePayment && 'now' === state.paymentChoice );
	}

	/**
	 * Validates one guest/new-patient field against the same rules
	 * Booking_Handler applies; returns the error message or ''.
	 */
	function guestFieldError( field, ignorePayment ) {
		var value = valueOf( 'dak-booking-' + field.replace( '_', '-' ) ).trim();

		if ( 'guest_name' === field ) {
			return value ? '' : ( cfg.isStaff ? 'Enter the patient’s full name.' : 'Enter your full name.' );
		}

		if ( 'guest_email' === field ) {
			if ( ! value ) {
				return 'Enter an email address.';
			}

			return validEmail( value ) ? '' : 'Enter a valid email address, like name@example.com.';
		}

		if ( 'guest_phone' === field ) {
			if ( ! phoneRequiredNow( ignorePayment ) ) {
				return value && ! normalizeMsisdn( value ) && ! ignorePayment && 'now' === state.paymentChoice ? 'Enter a valid Pakistani mobile number, e.g. 03001234567.' : '';
			}

			if ( ! value ) {
				return 'video' === state.type ? 'A mobile number is required for video consultations.' : 'A mobile number is required to pay online.';
			}

			return normalizeMsisdn( value ) ? '' : 'Enter a valid Pakistani mobile number, e.g. 03001234567.';
		}

		return '';
	}

	function validateGuestField( field, soft ) {
		var message = guestFieldError( field );

		if ( message && ! soft ) {
			showFieldError( field, message );
		} else if ( message && soft ) {
			// On blur, only flag fields that have content but are wrong.
			if ( 'guest_email' === field || 'guest_phone' === field ) {
				showFieldError( field, message );
			}
		} else {
			clearFieldError( field );
		}

		return ! message;
	}

	function renderIdentity() {
		var staffBlock = document.getElementById( 'dak-booking-identity-staff' );
		var loggedInBlock = document.getElementById( 'dak-booking-identity-loggedin' );
		var choiceBlock = document.getElementById( 'dak-booking-identity-choice' );
		var guestBlock = document.getElementById( 'dak-booking-identity-guest' );
		var intro = document.getElementById( 'dak-bk-guest-intro' );

		toggle( staffBlock, !! cfg.isStaff );
		toggle( loggedInBlock, ! cfg.isStaff && !! cfg.isLoggedIn );
		toggle( choiceBlock, guestMode() );
		toggle( guestBlock, guestMode() || staffNewPatient() );
		toggle( intro, guestMode() );

		if ( cfg.isLoggedIn && ! cfg.isStaff ) {
			var user = cfg.user || {};
			setText( 'dak-booking-loggedin-name', user.name || '—' );
			setText( 'dak-booking-loggedin-email', user.email || '—' );
			setText( 'dak-booking-loggedin-phone', user.phone || 'Not added' );

			document.querySelectorAll( '.dak-bk-profile-link' ).forEach( function ( a ) {
				a.href = cfg.profileUrl || '#';
			} );

			toggle( document.getElementById( 'dak-booking-loggedin-phone-missing' ), 'video' === state.type && ! normalizeMsisdn( user.phone ) );
		}

		var phoneRequired = 'video' === state.type;
		toggle( document.getElementById( 'dak-booking-guest-phone-required' ), phoneRequired );
		toggle( document.getElementById( 'dak-bk-phone-optional' ), ! phoneRequired );

		var phone = document.getElementById( 'dak-booking-guest-phone' );

		if ( phone ) {
			phone.required = phoneRequired;
		}

		setText( 'dak-bk-hint-phone', phoneRequired
			? 'Required for video consultations, so the clinic can reach you.'
			: 'Only needed if you choose to pay online.' );

		updateAccountLinks();
	}

	/** Log in / register links carry the current selections back here. */
	function updateAccountLinks() {
		var returnUrl = currentStateUrl();
		var login = document.getElementById( 'dak-booking-login-link' );
		var register = document.getElementById( 'dak-booking-register-link' );

		if ( login && cfg.loginBaseUrl ) {
			login.href = withParam( cfg.loginBaseUrl, 'redirect_to', returnUrl );
		} else if ( login && cfg.loginUrl ) {
			login.href = cfg.loginUrl;
		}

		if ( register && cfg.registerBaseUrl ) {
			register.href = withParam( cfg.registerBaseUrl, 'redirect_to', returnUrl );
		} else if ( register && cfg.registerUrl ) {
			register.href = cfg.registerUrl;
		}
	}

	function currentStateUrl() {
		var base = window.location.origin + window.location.pathname;
		var parts = [];

		if ( state.doctorId ) {
			parts.push( 'doctor_id=' + state.doctorId );
			parts.push( 'type=' + state.type );
		}

		if ( 'clinic' === state.type ) {
			if ( state.clinicId ) {
				parts.push( 'clinic_id=' + encodeURIComponent( state.clinicId ) );
			}

			state.serviceIds.forEach( function ( id ) {
				parts.push( 'service_ids%5B%5D=' + encodeURIComponent( id ) );
			} );
		}

		if ( state.date ) {
			parts.push( 'date=' + state.date );
		}

		if ( state.time ) {
			parts.push( 'time=' + encodeURIComponent( state.time ) );
		}

		return base + ( parts.length ? '?' + parts.join( '&' ) : '' );
	}

	function withParam( url, key, value ) {
		return url + ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + key + '=' + encodeURIComponent( value );
	}

	/**
	 * Staff identity: pick a registered patient, or "Add new patient" (which
	 * reuses the guest fields and creates an account server-side).
	 */
	function wireStaffPatientPicker() {
		var hidden = document.getElementById( 'dak-booking-patient-id' );
		var container = document.getElementById( 'dak-booking-patient-cards' );

		if ( ! hidden || ! container ) {
			return;
		}

		var cards = Array.prototype.slice.call( container.querySelectorAll( '[data-patient-card]' ) );

		container.addEventListener( 'click', function ( event ) {
			var card = event.target.closest( '[data-patient-card]' );

			if ( ! card ) {
				return;
			}

			cards.forEach( function ( el ) {
				el.classList.toggle( 'is-selected', el === card );
				el.setAttribute( 'aria-pressed', el === card ? 'true' : 'false' );
			} );

			hidden.value = card.getAttribute( 'data-patient-id' ) || '';
			clearFieldError( 'patient_id' );
			renderIdentity();
			renderSummaryAndReview();
		} );

		var search = document.getElementById( 'dak-booking-patient-search' );
		var empty = document.getElementById( 'dak-booking-patient-cards-empty' );

		if ( search ) {
			search.addEventListener( 'input', function () {
				var query = search.value.trim().toLowerCase();
				var count = 0;

				cards.forEach( function ( card ) {
					if ( '' === card.getAttribute( 'data-patient-id' ) ) {
						return;
					}

					var match = '' === query || ( card.getAttribute( 'data-patient-name' ) || '' ).indexOf( query ) !== -1 || card.classList.contains( 'is-selected' );
					card.classList.toggle( 'dak-hidden', ! match );
					count += match ? 1 : 0;
				} );

				toggle( empty, 0 === count );
			} );
		}
	}

	/* =====================================================================
	 * Review + payment
	 * =================================================================== */

	function wirePayment() {
		document.querySelectorAll( 'input[name="dak_bk_payment"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( radio.checked ) {
					state.paymentChoice = radio.value;
					clearFieldError( 'payment_choice' );
					hideStepAlert();
					// Choosing "Pay now" can make the phone required — re-check.
					if ( guestMode() || staffNewPatient() ) {
						clearFieldError( 'guest_phone' );
					}

					renderReview();
				}
			} );
		} );
	}

	/**
	 * Whether a Pay now / Pay later choice is offered: only when there's
	 * something to pay AND online payment can actually be started (see
	 * Swich_Payment::requires_payment()).
	 */
	function paymentChoiceOffered( total ) {
		return total > 0 && false !== cfg.onlinePaymentAvailable;
	}

	function effectivePaymentChoice( total ) {
		return paymentChoiceOffered( total ) ? state.paymentChoice : 'later';
	}

	function renderReview() {
		var c = charges();
		var offered = paymentChoiceOffered( c.total );
		var isVideo = 'video' === state.type;

		setText( 'dak-bk-pay-now-desc', 'Pay ' + money( c.total ) + ' by card or mobile wallet on the secure payment page.' +
			( isVideo ? ' Your video call link is issued once payment is confirmed.' : '' ) );

		var laterText;

		if ( cfg.isStaff ) {
			laterText = 'Book now with payment pending.';
		} else if ( ! isVideo ) {
			laterText = 'Book now and pay at the clinic.';
		} else if ( cfg.isLoggedIn ) {
			laterText = 'Book now and pay from your dashboard before the call. The call link appears after payment.';
		} else {
			laterText = 'Book now; the clinic will contact you to arrange payment. The call link is issued after payment.';
		}

		setText( 'dak-bk-pay-later-desc', laterText );
		toggle( document.getElementById( 'dak-booking-submit-choice' ), offered );

		document.querySelectorAll( 'input[name="dak_bk_payment"]' ).forEach( function ( radio ) {
			radio.checked = radio.value === state.paymentChoice;
			radio.closest( '.dak-bk-option' ).classList.toggle( 'is-selected', radio.checked );
		} );

		var offline = document.getElementById( 'dak-bk-payment-offline-note' );

		if ( offline ) {
			if ( c.total > 0 && ! offered ) {
				offline.textContent = isVideo
					? 'Payment of ' + money( c.total ) + ' will be pending after booking; the clinic will arrange it with you. The call link is issued after payment.'
					: 'Payment of ' + money( c.total ) + ' is due at the clinic.';
				show( offline );
			} else {
				hide( offline );
			}
		}

		// Hidden field mirrors the effective choice ('later' when no choice is offered).
		document.getElementById( 'dak-booking-payment-choice' ).value = effectivePaymentChoice( c.total ) || '';

		renderSubmitLabel( c );
		renderReviewFacts( c );
		updateCancellationNote();
	}

	function renderSubmitLabel( c ) {
		var label = document.querySelector( '#dak-booking-submit .dak-button-label' );

		if ( ! label ) {
			return;
		}

		var choice = effectivePaymentChoice( c.total );
		var text;

		if ( state.submitting ) {
			text = 'Booking…';
		} else if ( 'now' === choice ) {
			text = 'Pay ' + money( c.total ) + ' and book';
		} else if ( c.total > 0 ) {
			text = cfg.isStaff ? 'Book for patient (pay later)' : 'Book appointment (pay later)';
		} else {
			text = cfg.isStaff ? 'Book for patient' : 'Book appointment';
		}

		label.textContent = text;
	}

	function renderReviewFacts( c ) {
		var card = doctorCard( state.doctorId );
		var clinic = selectedClinic();
		var appointment = [];

		if ( card ) {
			appointment.push( [ 'Doctor', 'Dr. ' + card.getAttribute( 'data-doctor-name' ) ] );
		}

		appointment.push( [ 'Visit', 'video' === state.type ? 'Online video consultation' : 'Clinic visit' ] );

		if ( 'clinic' === state.type ) {
			if ( clinic ) {
				var address = addressWithoutName( clinic.address, clinic.name );
				appointment.push( [ 'Clinic', clinic.name + ( address ? '\n' + address : '' ) ] );
			}

			appointment.push( [ 'Services', serviceNames().join( ', ' ) || '—' ] );
		}

		fillFacts( 'dak-bk-review-appointment', appointment );
		fillFacts( 'dak-bk-review-when', [ [ 'Date', state.date ? dateLong( state.date ) : '—' ], [ 'Time', state.time ? timeLabel( state.time ) + ( cfg.timezoneLabel ? ' · ' + cfg.timezoneLabel : '' ) : '—' ] ] );

		var patient = [];

		if ( cfg.isStaff && ! staffNewPatient() ) {
			var picked = document.querySelector( '#dak-booking-patient-cards [data-patient-card].is-selected' );
			patient.push( [ 'Patient', picked ? picked.getAttribute( 'data-patient-label' ) || '' : '—' ] );
		} else if ( cfg.isLoggedIn && ! cfg.isStaff ) {
			var user = cfg.user || {};
			patient.push( [ 'Name', user.name || '—' ], [ 'Email', user.email || '—' ], [ 'Mobile', user.phone || 'Not added' ] );
		} else {
			patient.push( [ 'Name', valueOf( 'dak-booking-guest-name' ).trim() || '—' ], [ 'Email', valueOf( 'dak-booking-guest-email' ).trim() || '—' ], [ 'Mobile', valueOf( 'dak-booking-guest-phone' ).trim() || 'Not given' ] );
		}

		fillFacts( 'dak-bk-review-patient', patient );

		// "Your details" is skipped for a logged-in patient with a phone on
		// file — their details are edited on the profile page instead.
		var editDetails = document.getElementById( 'dak-bk-review-edit-details' );
		toggle( editDetails, ! identitySkipped() );

		var tbody = document.querySelector( '#dak-bk-review-charges tbody' );

		if ( tbody ) {
			tbody.innerHTML = '';

			c.lines.forEach( function ( line ) {
				var tr = document.createElement( 'tr' );
				var th = document.createElement( 'th' );
				var td = document.createElement( 'td' );
				th.scope = 'row';
				th.textContent = line.label;
				td.textContent = line.discount ? money( line.amount ) : ( false === line.known ? 'From ' + money( line.amount ) : ( line.amount > 0 ? money( line.amount ) : 'Free' ) );

				if ( line.discount ) {
					tr.className = 'is-discount';
				}

				tr.appendChild( th );
				tr.appendChild( td );
				tbody.appendChild( tr );
			} );
		}

		setText( 'dak-bk-review-total', totalLabel( c ) );
	}

	function totalLabel( c ) {
		if ( ! c.ready ) {
			return '—';
		}

		if ( ! c.known ) {
			return 'From ' + money( c.total );
		}

		return c.total > 0 ? money( c.total ) : 'Free';
	}

	function serviceNames() {
		return state.serviceIds.map( function ( id ) {
			var s = findService( state.doctorId, id );

			return s ? s.name : '';
		} ).filter( Boolean );
	}

	function fillFacts( id, rows ) {
		var dl = document.getElementById( id );

		if ( ! dl ) {
			return;
		}

		dl.innerHTML = '';

		rows.forEach( function ( row ) {
			var wrap = document.createElement( 'div' );
			var dt = document.createElement( 'dt' );
			var dd = document.createElement( 'dd' );
			dt.textContent = row[ 0 ];

			String( row[ 1 ] ).split( '\n' ).forEach( function ( line, i ) {
				if ( i ) {
					var small = document.createElement( 'span' );
					small.className = 'dak-bk-facts-sub';
					small.textContent = line;
					dd.appendChild( small );
				} else {
					dd.appendChild( document.createTextNode( line ) );
				}
			} );

			wrap.appendChild( dt );
			wrap.appendChild( dd );
			dl.appendChild( wrap );
		} );
	}

	/** Cancellation terms, from the doctor's own refund window (same wording as before). */
	function updateCancellationNote() {
		var note = document.getElementById( 'dak-booking-summary-cancellation-note' );

		if ( ! note ) {
			return;
		}

		var rules = state.doctorId && cfg.bookingRules ? cfg.bookingRules[ state.doctorId ] : null;

		if ( ! rules ) {
			note.textContent = '';
			hide( note );
			return;
		}

		var hours = parseFloat( rules.cancel_refund_hours );
		note.innerHTML = '<strong>Cancellation:</strong> ' + escapeHtml( hours > 0
			? 'Free cancellation up to ' + hours + ' hour' + ( 1 === hours ? '' : 's' ) + ' before your appointment.'
			: 'Free cancellation any time before your appointment starts.' );
		show( note );
	}

	function refreshBookingRules( doctorId ) {
		if ( ! doctorId ) {
			return;
		}

		var body = new FormData();
		body.append( 'action', 'doctor_ak_booking_rules' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'doctor_id', doctorId );

		fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( result && result.success && result.data ) {
					cfg.bookingRules = cfg.bookingRules || {};
					cfg.bookingRules[ doctorId ] = result.data;

					if ( doctorId === state.doctorId ) {
						updateCancellationNote();
					}
				}
			} )
			.catch( function () {} );
	}

	/* =====================================================================
	 * Summary
	 * =================================================================== */

	function renderSummary() {
		var card = doctorCard( state.doctorId );
		var clinic = selectedClinic();
		var c = charges();
		var rows = {
			doctor: card ? 'Dr. ' + card.getAttribute( 'data-doctor-name' ) : '',
			type: card ? ( 'video' === state.type ? 'Online video' : 'Clinic visit' ) : '',
			clinic: card && 'clinic' === state.type && clinic ? clinic.name : '',
			service: card && 'clinic' === state.type ? serviceNames().join( ', ' ) : '',
			when: state.date && state.time ? dateLong( state.date ) + ', ' + timeLabel( state.time ) : ( state.date ? dateLong( state.date ) + ' — choose a time' : '' ),
		};

		Object.keys( rows ).forEach( function ( key ) {
			var row = document.querySelector( '[data-summary-row="' + key + '"]' );

			if ( ! row ) {
				return;
			}

			var value = row.querySelector( '[data-summary-value]' );
			var empty = ! rows[ key ];

			value.textContent = empty ? placeholderFor( key ) : rows[ key ];
			row.classList.toggle( 'is-empty', empty );
			row.classList.toggle( 'dak-hidden', ( 'clinic' === key || 'service' === key ) && 'video' === state.type );

			var edit = row.querySelector( '[data-goto-step]' );

			if ( edit ) {
				toggle( edit, ! empty && canGoTo( edit.getAttribute( 'data-goto-step' ) ) && 'confirmation' !== state.step );
			}
		} );

		var total = document.getElementById( 'dak-booking-summary-total-amount' );
		var mini = document.getElementById( 'dak-bk-summary-total-mini' );
		var note = document.getElementById( 'dak-bk-summary-note' );
		var label = totalLabel( c );

		if ( total ) {
			total.textContent = label;
		}

		if ( mini ) {
			mini.textContent = c.ready ? label : '';
		}

		if ( note ) {
			if ( ! state.doctorId ) {
				note.textContent = 'Choose a doctor to begin.';
			} else if ( ! c.ready ) {
				note.textContent = 'Choose at least one service to see the price.';
			} else if ( ! c.known ) {
				note.textContent = 'Final price depends on the clinic you choose.';
			} else if ( 'video' === state.type && state.time && state.slotSurcharge > 0 ) {
				note.textContent = 'Includes an instant booking fee of ' + money( state.slotSurcharge ) + '.';
			} else {
				note.textContent = '';
			}

			toggle( note, !! note.textContent );
		}
	}

	function placeholderFor( key ) {
		return {
			doctor: 'Not chosen yet',
			type: '—',
			clinic: 'Not chosen yet',
			service: 'Not chosen yet',
			when: 'Not chosen yet',
		}[ key ] || '—';
	}

	/**
	 * The summary is a <details>: always open beside the form on desktop,
	 * collapsed (total still visible in its header) above the form on
	 * smaller screens, where the patient can expand it.
	 */
	function wireSummaryPanel() {
		var summary = document.getElementById( 'dak-bk-summary' );

		if ( ! summary || ! window.matchMedia ) {
			return;
		}

		var mobile = window.matchMedia( '(max-width: 1023px)' );

		function apply() {
			summary.open = ! mobile.matches;
		}

		apply();

		if ( mobile.addEventListener ) {
			mobile.addEventListener( 'change', apply );
		}

		summary.querySelector( 'summary' ).addEventListener( 'click', function ( event ) {
			if ( ! mobile.matches ) {
				event.preventDefault();
			}
		} );
	}

	function renderSummaryAndReview() {
		syncHiddenInputs();
		renderSummary();
		renderReview();
		updateAccountLinks();
	}

	function syncHiddenInputs() {
		setValue( 'dak-booking-doctor-id', state.doctorId || '' );
		setValue( 'dak-booking-type', state.type );
		setValue( 'dak-booking-clinic-id', 'clinic' === state.type ? state.clinicId : '' );
		setValue( 'dak-booking-service-id', 'clinic' === state.type && state.serviceIds.length ? state.serviceIds[ 0 ] : '' );
		setValue( 'dak-booking-date', state.date );
		setValue( 'dak-booking-time', state.time );
	}

	/* =====================================================================
	 * Steps + navigation
	 * =================================================================== */

	function identitySkipped() {
		// A logged-in patient with a phone on file has nothing to add.
		return !! cfg.identityFullyKnown && !! cfg.isLoggedIn && ! cfg.isStaff;
	}

	function stepComplete( key ) {
		if ( 'selection' === key ) {
			if ( ! state.doctorId ) {
				return false;
			}

			if ( 'video' === state.type ) {
				return videoOffered( state.doctorId );
			}

			var clinics = clinicsFor( state.doctorId );

			return state.serviceIds.length > 0 && ( ! clinics.length || !! state.clinicId );
		}

		if ( 'schedule' === key ) {
			return !! state.date && !! state.time;
		}

		if ( 'identity' === key ) {
			return identitySkipped() || ( state.maxStep > STEP_KEYS.indexOf( 'identity' ) );
		}

		return false;
	}

	function canGoTo( key ) {
		var index = STEP_KEYS.indexOf( key );

		return index !== -1 && index <= state.maxStep && ! state.submitting && 'confirmation' !== state.step;
	}

	function renderProgress() {
		var activeIndex = STEP_KEYS.indexOf( state.step );

		STEP_KEYS.forEach( function ( key, index ) {
			var li = document.querySelector( '.dak-bk-progress-step[data-step="' + key + '"]' );

			if ( ! li ) {
				return;
			}

			var btn = li.querySelector( 'button' );
			var isActive = key === state.step;
			var done = 'confirmation' === state.step || ( index < activeIndex ) || ( ! isActive && index <= state.maxStep && stepComplete( key ) );
			var skipped = 'identity' === key && identitySkipped();

			li.classList.toggle( 'is-active', isActive );
			li.classList.toggle( 'is-complete', done && ! isActive );
			li.classList.toggle( 'is-skipped', skipped && ! isActive );

			if ( btn ) {
				btn.disabled = isActive || ! canGoTo( key ) || 'confirmation' === state.step || ( skipped && ! isActive );

				if ( isActive ) {
					btn.setAttribute( 'aria-current', 'step' );
				} else {
					btn.removeAttribute( 'aria-current' );
				}

				var sr = btn.querySelector( '.dak-bk-progress-state' );

				if ( sr ) {
					sr.textContent = isActive ? ' (current step)' : ( done ? ' (completed)' : '' );
				}
			}
		} );
	}

	function wireNavigation() {
		document.addEventListener( 'click', function ( event ) {
			var next = event.target.closest( '[data-wizard-next]' );
			var back = event.target.closest( '[data-wizard-back]' );
			var go = event.target.closest( '[data-goto-step]' );

			if ( next && form.contains( next ) ) {
				event.preventDefault();
				continueFrom( state.step );
			} else if ( back && form.contains( back ) ) {
				event.preventDefault();
				var target = back.getAttribute( 'data-wizard-back' );

				if ( 'identity' === target && identitySkipped() ) {
					target = 'schedule';
				}

				goToStep( target );
			} else if ( go ) {
				event.preventDefault();
				var key = go.getAttribute( 'data-goto-step' );

				if ( canGoTo( key ) ) {
					goToStep( key );
				}
			}
		} );
	}

	function continueFrom( key ) {
		hideStepAlert();

		if ( ! validateStep( key ) ) {
			return;
		}

		var index = STEP_KEYS.indexOf( key );
		var target = STEP_KEYS[ index + 1 ];

		if ( 'identity' === target && identitySkipped() ) {
			target = 'review';
		}

		goToStep( target );
	}

	function goToStep( key, opts ) {
		opts = opts || {};
		state.step = key;

		var index = STEP_KEYS.indexOf( key );

		if ( index > state.maxStep ) {
			state.maxStep = index;
		}

		Object.keys( STEP_SECTION_IDS ).forEach( function ( k ) {
			toggle( document.getElementById( STEP_SECTION_IDS[ k ] ), k === key );
		} );

		document.querySelector( '.dak-booking-page' ).classList.toggle( 'is-done', 'confirmation' === key );

		if ( 'selection' === key ) {
			showDoctorPicker( ! state.doctorId );
		}

		if ( 'schedule' === key ) {
			fetchWeek();

			if ( state.date && ! state.slots ) {
				fetchSlots();
			} else if ( ! state.date ) {
				setSlotsStatus( 'choose' );
			}
		}

		if ( 'identity' === key ) {
			renderIdentity();
		}

		renderSummaryAndReview();
		renderProgress();

		if ( false !== opts.scroll ) {
			scrollToEl( document.querySelector( '.dak-bk-progress-wrap' ) || form );
		}

		if ( false !== opts.focus ) {
			var heading = document.querySelector( '#' + STEP_SECTION_IDS[ key ] + ' h2' );

			if ( heading ) {
				heading.focus( { preventScroll: true } );
			}
		}
	}

	/** Validates a step; on failure shows errors and focuses the first invalid field. */
	function validateStep( key ) {
		var errors = [];

		if ( 'selection' === key ) {
			if ( ! state.doctorId ) {
				errors.push( [ 'doctor_id', 'Choose a doctor to continue.', document.getElementById( 'dak-booking-doctor-search' ) || document.querySelector( '[data-doctor-card]' ) ] );
			} else if ( 'clinic' === state.type ) {
				var clinics = clinicsFor( state.doctorId );

				if ( ! servicesFor( state.doctorId ).length ) {
					errors.push( [ 'service_id', videoOffered( state.doctorId ) ? 'Clinic visits with this doctor can’t be booked online yet. Choose Online video, or another doctor.' : 'Clinic visits with this doctor can’t be booked online yet. Please choose another doctor.', document.querySelector( '[data-bk-switch-video]' ) || document.querySelector( '.dak-booking-segment' ) ] );
				} else {
					if ( clinics.length && ! state.clinicId ) {
						errors.push( [ 'clinic_id', 'Choose which clinic you’d like to visit.', document.querySelector( 'input[name="dak_bk_clinic"]' ) ] );
					}

					if ( ! state.serviceIds.length ) {
						errors.push( [ 'service_id', 'Choose at least one service.', document.querySelector( '.dak-booking-service-checkbox' ) ] );
					}
				}
			}
		}

		if ( 'schedule' === key ) {
			if ( ! state.date ) {
				errors.push( [ 'date', 'Choose a date.', document.querySelector( '.dak-bk-day:not([disabled])' ) ] );
			} else if ( ! state.time ) {
				errors.push( [ 'time', 'Choose a time to continue.', document.querySelector( '.dak-bk-slot:not([disabled])' ) || document.getElementById( 'dak-bk-slots-status' ) ] );
			}
		}

		if ( 'identity' === key ) {
			if ( cfg.isStaff && ! staffNewPatient() ) {
				// A registered patient is chosen — nothing else to check here.
			} else if ( cfg.isStaff || guestMode() ) {
				[ 'guest_name', 'guest_email', 'guest_phone' ].forEach( function ( field ) {
					var message = guestFieldError( field );

					if ( message ) {
						errors.push( [ field, message, document.getElementById( 'dak-booking-' + field.replace( '_', '-' ) ) ] );
					} else {
						clearFieldError( field );
					}
				} );
			} else if ( cfg.isLoggedIn && 'video' === state.type && ! normalizeMsisdn( ( cfg.user || {} ).phone ) ) {
				toggle( document.getElementById( 'dak-booking-loggedin-phone-missing' ), true );
				errors.push( [ '', '', document.getElementById( 'dak-booking-loggedin-phone-missing-link' ) ] );
			}
		}

		if ( 'review' === key ) {
			var c = charges();

			if ( paymentChoiceOffered( c.total ) && ! state.paymentChoice ) {
				errors.push( [ 'payment_choice', 'Choose how you’d like to pay.', document.getElementById( 'dak-booking-pay-now' ) ] );
			}

			if ( 'now' === effectivePaymentChoice( c.total ) ) {
				if ( guestMode() || staffNewPatient() ) {
					if ( ! normalizeMsisdn( valueOf( 'dak-booking-guest-phone' ) ) ) {
						errors.push( [ 'payment_choice', 'Paying online needs a valid mobile number. Add one under Your details (Edit above), or choose Pay later.', document.getElementById( 'dak-booking-pay-later' ) ] );
					}
				} else if ( cfg.isLoggedIn && ! cfg.isStaff && ! normalizeMsisdn( ( cfg.user || {} ).phone ) ) {
					errors.push( [ 'payment_choice', 'Paying online needs a mobile number on your profile. Add one, or choose Pay later.', document.getElementById( 'dak-booking-pay-later' ) ] );
				}
			}
		}

		errors.forEach( function ( e ) {
			if ( e[ 0 ] ) {
				showFieldError( e[ 0 ], e[ 1 ] );
			}
		} );

		if ( errors.length ) {
			var target = errors[ 0 ][ 2 ];

			if ( target ) {
				scrollToEl( target );
				target.focus( { preventScroll: true } );
			}

			return false;
		}

		return true;
	}

	/* =====================================================================
	 * Submit
	 * =================================================================== */

	function wireSubmit() {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( 'confirmation' === state.step ) {
			return;
		}

		if ( state.submitting || 'review' !== state.step ) {
				// Enter pressed on an earlier step behaves like Continue.
				if ( ! state.submitting && 'confirmation' !== state.step ) {
					continueFrom( state.step );
				}

				return;
			}

			hideStepAlert();

			// Re-check every step; jump to the first one with a problem.
			for ( var i = 0; i < STEP_KEYS.length; i++ ) {
				var key = STEP_KEYS[ i ];

				if ( 'identity' === key && identitySkipped() ) {
					continue;
				}

				if ( ! validateStepQuiet( key ) ) {
					goToStep( key );
					validateStep( key );
					return;
				}
			}

			submitBooking();
		} );
	}

	function validateStepQuiet( key ) {
		if ( 'review' === key ) {
			return validateStep( key );
		}

		if ( 'identity' === key ) {
			if ( cfg.isStaff && ! staffNewPatient() ) {
				return true;
			}

			if ( cfg.isStaff || guestMode() ) {
				return ! guestFieldError( 'guest_name' ) && ! guestFieldError( 'guest_email' ) && ! guestFieldError( 'guest_phone', true );
			}

			return ! ( 'video' === state.type && ! normalizeMsisdn( ( cfg.user || {} ).phone ) );
		}

		return stepComplete( key );
	}

	function submitBooking() {
		var c = charges();
		var choice = effectivePaymentChoice( c.total ) || 'later';
		var button = document.getElementById( 'dak-booking-submit' );

		state.submitting = true;
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		button.classList.add( 'is-loading' );
		renderSubmitLabel( c );
		renderProgress();

		var body = new FormData();
		body.append( 'action', 'doctor_ak_book_appointment' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'doctor_id', state.doctorId );
		body.append( 'type', state.type );
		body.append( 'date', state.date );
		body.append( 'time', state.time );
		body.append( 'service_id', 'clinic' === state.type && state.serviceIds.length ? state.serviceIds[ 0 ] : '0' );

		if ( 'clinic' === state.type ) {
			state.serviceIds.forEach( function ( id ) {
				body.append( 'service_ids[]', id );
			} );
		}

		body.append( 'clinic_id', 'clinic' === state.type ? state.clinicId : '' );
		body.append( 'payment_choice', choice );

		if ( cfg.isStaff && staffPatientId() ) {
			body.append( 'patient_id', staffPatientId() );
		}

		if ( guestMode() || staffNewPatient() ) {
			body.append( 'guest_name', valueOf( 'dak-booking-guest-name' ).trim() );
			body.append( 'guest_email', valueOf( 'dak-booking-guest-email' ).trim() );
			body.append( 'guest_phone', valueOf( 'dak-booking-guest-phone' ).trim() );
		}

		var snapshot = {
			doctor: doctorCard( state.doctorId ) ? 'Dr. ' + doctorCard( state.doctorId ).getAttribute( 'data-doctor-name' ) : '',
			type: state.type,
			clinic: selectedClinic(),
			services: serviceNames(),
			date: state.date,
			time: state.time,
			total: c.total,
			choice: choice,
		};

		fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( result && result.success ) {
					showConfirmation( result.data || {}, snapshot );
					return;
				}

				endSubmitting();
				handleSubmitError( result && result.data ? result.data : {} );
			} )
			.catch( function () {
				endSubmitting();
				showStepAlert( 'review', 'We couldn’t reach the server, so we can’t confirm whether your booking went through. Check your connection, then try again — or check your dashboard/email first if you were already charged.' );
			} );
	}

	function endSubmitting() {
		var button = document.getElementById( 'dak-booking-submit' );

		state.submitting = false;
		button.disabled = false;
		button.removeAttribute( 'aria-busy' );
		button.classList.remove( 'is-loading' );
		renderSubmitLabel( charges() );
		renderProgress();
	}

	function handleSubmitError( data ) {
		if ( data.errors ) {
			var fields = Object.keys( data.errors );
			var firstStep = fields.map( function ( f ) { return FIELD_STEP[ f ] || 'review'; } )
				.sort( function ( a, b ) { return STEP_KEYS.indexOf( a ) - STEP_KEYS.indexOf( b ); } )[ 0 ];

			goToStep( firstStep, { focus: false } );

			fields.forEach( function ( f ) {
				showFieldError( f, data.errors[ f ] );
			} );

			var firstField = fields.filter( function ( f ) { return ( FIELD_STEP[ f ] || 'review' ) === firstStep; } )[ 0 ];
			var input = document.getElementById( 'dak-booking-' + String( firstField ).replace( '_', '-' ) );
			var focusTarget = input && input.offsetParent ? input : document.querySelector( '.dak-field-error[data-field="' + firstField + '"]' );

			if ( focusTarget ) {
				if ( ! input || ! input.offsetParent ) {
					focusTarget.setAttribute( 'tabindex', '-1' );
				}

				scrollToEl( focusTarget );
				focusTarget.focus( { preventScroll: true } );
			}

			return;
		}

		var message = data.message || 'Something went wrong. Please try again.';

		// The chosen time was taken in the meantime: return to Date & time
		// with fresh availability rather than leaving a dead time selected.
		if ( /time slot|no longer available|already booked/i.test( message ) ) {
			clearTime();
			goToStep( 'schedule', { focus: false } );
			fetchSlots();
			showStepAlert( 'schedule', message );
			return;
		}

		showStepAlert( 'review', message );
	}

	function showConfirmation( data, snap ) {
		state.submitting = false;

		var title = document.getElementById( 'dak-bk-title-confirmation' );
		var message = document.getElementById( 'dak-booking-success' );
		var actions = document.getElementById( 'dak-bk-result-actions' );
		var rows = [];

		if ( data.appointment_id ) {
			rows.push( [ 'Reference', '#' + data.appointment_id ] );
		}

		rows.push( [ 'Doctor', snap.doctor ], [ 'Visit', 'video' === snap.type ? 'Online video consultation' : 'Clinic visit' ] );

		if ( 'clinic' === snap.type && snap.clinic ) {
			var address = addressWithoutName( snap.clinic.address, snap.clinic.name );
			rows.push( [ 'Clinic', snap.clinic.name + ( address ? '\n' + address : '' ) ] );
		}

		if ( snap.services.length ) {
			rows.push( [ 'Services', snap.services.join( ', ' ) ] );
		}

		rows.push( [ 'When', dateLong( snap.date ) + ', ' + timeLabel( snap.time ) + ( cfg.timezoneLabel ? '\n' + cfg.timezoneLabel : '' ) ] );

		if ( data.payment_url ) {
			rows.push( [ 'Amount', money( snap.total ) ] );
		} else if ( snap.total > 0 ) {
			rows.push( [ 'Payment', money( snap.total ) + ' — pending' ] );
		} else {
			rows.push( [ 'Payment', 'No payment due' ] );
		}

		fillFacts( 'dak-bk-result-details', rows );
		actions.innerHTML = '';

		if ( data.payment_url ) {
			// "Pay now": the appointment exists with payment pending until the
			// gateway confirms it — say exactly that, then hand over.
			title.textContent = 'Continue to payment';
			message.textContent = 'Your appointment is reserved with payment pending. Taking you to the secure payment page to pay ' + money( snap.total ) + '…';
			actions.appendChild( actionLink( data.payment_url, 'Continue to payment', true ) );
			goToStep( 'confirmation' );
			window.setTimeout( function () {
				window.location.href = data.payment_url;
			}, 1200 );
			return;
		}

		title.textContent = cfg.isStaff ? 'Appointment booked for the patient' : ( cfg.isLoggedIn ? 'Appointment booked' : 'Request received' );
		message.textContent = data.message || 'Your appointment request has been received.';

		if ( cfg.isLoggedIn && ! cfg.isStaff ) {
			actions.appendChild( actionLink( data.redirect_url || cfg.dashboardUrl || cfg.homeUrl || '/', 'Go to my dashboard', true ) );
		} else if ( cfg.isStaff ) {
			actions.appendChild( actionLink( cfg.pageUrl || window.location.pathname, 'Book another appointment', true ) );
		} else {
			actions.appendChild( actionLink( cfg.homeUrl || '/', 'Back to home', true ) );
		}

		goToStep( 'confirmation' );
	}

	function actionLink( href, text, primary ) {
		var a = document.createElement( 'a' );
		a.className = 'dak-button ' + ( primary ? 'dak-button-primary' : 'dak-button-secondary' );
		a.href = href;
		a.textContent = text;

		return a;
	}

	/* =====================================================================
	 * Render all
	 * =================================================================== */

	function renderAll() {
		renderSelectedDoctor();
		renderVisit();
		renderWeek();
		renderPicked();
		renderIdentity();
		renderSummaryAndReview();
		renderProgress();
	}

	/* =====================================================================
	 * UI helpers
	 * =================================================================== */

	function showStepAlert( stepKey, message ) {
		var alert = document.getElementById( 'dak-booking-error' );

		if ( ! alert ) {
			return;
		}

		alert.textContent = message;
		show( alert );
		scrollToEl( alert );
		alert.focus( { preventScroll: true } );
	}

	function hideStepAlert() {
		hide( document.getElementById( 'dak-booking-error' ) );
	}

	function setNotice( id, text, kind ) {
		var el = document.getElementById( id );

		if ( el ) {
			el.className = 'dak-bk-notice ' + ( kind || '' );
			el.textContent = text;
			show( el );
		}
	}

	function showFieldError( field, message ) {
		var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );
		var input = document.getElementById( 'dak-booking-' + field.replace( '_', '-' ) );

		if ( el ) {
			el.textContent = message;
			el.classList.add( 'is-visible' );
		}

		if ( input && 'INPUT' === input.tagName && 'hidden' !== input.type ) {
			input.setAttribute( 'aria-invalid', 'true' );
		}
	}

	function clearFieldError( field ) {
		var el = form.querySelector( '.dak-field-error[data-field="' + field + '"]' );
		var input = document.getElementById( 'dak-booking-' + field.replace( '_', '-' ) );

		if ( el ) {
			el.textContent = '';
			el.classList.remove( 'is-visible' );
		}

		if ( input ) {
			input.removeAttribute( 'aria-invalid' );
		}
	}

	/** Scrolls an element into view below the sticky site header. */
	function scrollToEl( el ) {
		if ( ! el || ! el.getBoundingClientRect ) {
			return;
		}

		var offset = stickyOffset() + 16;
		var top = el.getBoundingClientRect().top + window.pageYOffset - offset;
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		if ( el.getBoundingClientRect().top < offset || el.getBoundingClientRect().bottom > window.innerHeight - 80 ) {
			window.scrollTo( { top: Math.max( 0, top ), behavior: reduce ? 'auto' : 'smooth' } );
		}
	}

	function stickyOffset() {
		var offset = 0;

		document.querySelectorAll( '.dak-site-header, #wpadminbar' ).forEach( function ( el ) {
			var style = window.getComputedStyle( el );

			if ( 'fixed' === style.position || 'sticky' === style.position ) {
				offset += el.getBoundingClientRect().height;
			}
		} );

		return offset;
	}

	function checkIcon() {
		var span = document.createElement( 'span' );
		span.className = 'dak-bk-check';
		span.setAttribute( 'aria-hidden', 'true' );
		span.innerHTML = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>';

		return span;
	}

	function escapeHtml( str ) {
		return String( str ).replace( /[&<>"']/g, function ( ch ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ ch ];
		} );
	}

	function toInt( v ) {
		var n = parseInt( v, 10 );

		return isNaN( n ) ? 0 : n;
	}

	function valueOf( id ) {
		var el = document.getElementById( id );

		return el ? String( el.value || '' ) : '';
	}

	function setValue( id, value ) {
		var el = document.getElementById( id );

		if ( el ) {
			el.value = value;
		}
	}

	function setText( id, text ) {
		var el = document.getElementById( id );

		if ( el ) {
			el.textContent = text;
		}
	}

	function toggle( el, on ) {
		if ( el ) {
			el.classList.toggle( 'dak-hidden', ! on );
		}
	}

	function show( el ) {
		toggle( el, true );
	}

	function hide( el ) {
		toggle( el, false );
	}
} )();
