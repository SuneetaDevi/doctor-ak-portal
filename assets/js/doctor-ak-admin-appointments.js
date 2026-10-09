/**
 * Doctor AK Portal — Admin "Appointments" table.
 *
 * Lets an administrator add, edit, view, print, or delete any appointment,
 * via Appointment_Handler's admin AJAX endpoints
 * (doctor_ak_admin_appointment_save/_delete) and print endpoint
 * (doctor_ak_admin_appointment_print).
 */
( function () {
	'use strict';

	var servicesByDoctorAndType = {};
	// [doctorId] => [{ id, name, place, location_id }] — each doctor's
	// physical clinics, for the Clinic picker (see physical_clinics_by_doctor()).
	var clinicsByDoctor = {};
	// The time slot the appointment currently open in the modal already
	// occupies (edit mode only) — its own slot shouldn't read as "booked"
	// just because it's already booked by itself.
	var currentEditTime = '';

	// The Add/Edit dialog doubles as the focused "Reschedule appointment"
	// dialog: 'add' | 'edit' | 'reschedule' (see setMode()).
	var mode = 'add';
	// Reschedule mode only: the appointment being moved, from its trigger's
	// data-* attributes ({ id, label, doctorId, type, clinicId, date, time, … }).
	var rescheduleCtx = null;
	var isSubmitting = false;
	var slotsLoading = false;
	// Every slot request is numbered; a response that isn't the latest one
	// (the date changed again meanwhile) is dropped instead of rendered.
	var slotRequestSeq = 0;
	// Row to highlight once the live-filtered list has refreshed after a
	// successful reschedule.
	var pendingHighlightId = '';
	// After a "slot taken" reply, focus lands on the refreshed times (the
	// Confirm button that had focus was disabled during the request).
	var focusSlotsAfterLoad = false;
	var SLOT_GROUP_THRESHOLD = 8;
	var FLASH_KEY = 'dakAdminAppointmentFlash';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAdminAppointments ) {
			return;
		}

		// Mark Paid / Pay Now are standalone actions (no modal involved), so
		// they're wired up wherever an appointment row/pill appears with
		// this script loaded — not just the Appointments section's own
		// table, but also the Dashboard overview's "Latest appointments"
		// widget.
		wireMarkPaid();
		wirePayNow();
		wireBulkActions();

		var modal = document.getElementById( 'dak-admin-appointment-modal' );
		var viewModal = document.getElementById( 'dak-admin-appointment-view-modal' );
		var refundModal = document.getElementById( 'dak-admin-process-refund-modal' );

		if ( ! modal || ! viewModal ) {
			return;
		}

		try {
			servicesByDoctorAndType = JSON.parse( modal.getAttribute( 'data-services' ) || '{}' );
		} catch ( e ) {
			servicesByDoctorAndType = {};
		}

		try {
			clinicsByDoctor = JSON.parse( modal.getAttribute( 'data-clinics' ) || '{}' ) || {};
		} catch ( e ) {
			clinicsByDoctor = {};
		}

		wireModalClose( modal, 'dak-admin-appointment-modal-close' );
		wireModalClose( viewModal, 'dak-admin-appointment-view-modal-close' );
		wireDoctorTypeChange();
		wireDateTimePicker();
		wireSlotChoice();
		wirePatientToggle();
		wireAdd( modal );
		wireEdit( modal );
		wireReschedule( modal );
		wireView( viewModal );
		wireCopy( viewModal );
		wireSave( modal );
		wireDelete();
		wireViewportHeight( modal );
		showFlash();
		openRescheduleFromUrl();

		if ( refundModal ) {
			wireModalClose( refundModal, 'dak-admin-process-refund-modal-close' );
			wireProcessRefund( refundModal );
			wireProcessRefundSave( refundModal );
		}
	} );

	function wireProcessRefund( refundModal ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-process-refund]' );

			if ( ! trigger ) {
				return;
			}

			clearRefundErrors();

			document.getElementById( 'dak-admin-process-refund-appointment-id' ).value = trigger.getAttribute( 'data-appointment-id' ) || '0';
			setText( 'dak-admin-process-refund-patient', trigger.getAttribute( 'data-patient-name' ) );
			setText( 'dak-admin-process-refund-reason', trigger.getAttribute( 'data-reason' ) );

			var charge = parseFloat( trigger.getAttribute( 'data-charge' ) || '0' );
			var refundAmount = parseFloat( trigger.getAttribute( 'data-refund-amount' ) || '0' );

			setText( 'dak-admin-process-refund-charge', 'PKR' + charge.toFixed( 0 ) );
			document.getElementById( 'dak-admin-process-refund-amount' ).value = refundAmount > 0 ? refundAmount : charge;
			document.getElementById( 'dak-admin-process-refund-amount' ).max = charge;

			openModal( refundModal );
		} );
	}

	function wireProcessRefundSave( refundModal ) {
		var saveButton = document.getElementById( 'dak-admin-process-refund-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearRefundErrors();

			if ( ! window.confirm( 'Process this refund via Swich? This cannot be undone.' ) ) {
				return;
			}

			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_process_refund' );
			formData.append( 'nonce', window.dakAdminAppointments.nonce );
			formData.append( 'appointment_id', document.getElementById( 'dak-admin-process-refund-appointment-id' ).value );
			formData.append( 'amount', document.getElementById( 'dak-admin-process-refund-amount' ).value );

			fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						window.location.reload();
						return;
					}

					showRefundError( errorsToMessage( result ) );
				} )
				.catch( function () {
					saveButton.disabled = false;
					showRefundError( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function clearRefundErrors() {
		document.querySelectorAll( '#dak-admin-process-refund-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-process-refund-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showRefundError( message ) {
		var el = document.getElementById( 'dak-admin-process-refund-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}

	function refreshSearchable( id ) {
		if ( window.DAKSearchableSelect ) {
			window.DAKSearchableSelect.refresh( document.getElementById( id ) );
		}
	}

	function resetModalFields() {
		document.getElementById( 'dak-admin-appointment-id' ).value = '0';
		document.getElementById( 'dak-admin-appointment-doctor' ).value = '';
		refreshSearchable( 'dak-admin-appointment-doctor' );
		document.getElementById( 'dak-admin-appointment-type' ).value = 'clinic';
		document.getElementById( 'dak-admin-appointment-patient' ).value = '';
		refreshSearchable( 'dak-admin-appointment-patient' );
		document.getElementById( 'dak-admin-appointment-guest-name' ).value = '';
		document.getElementById( 'dak-admin-appointment-guest-email' ).value = '';
		document.getElementById( 'dak-admin-appointment-guest-phone' ).value = '';
		var dateField = document.getElementById( 'dak-admin-appointment-date' );
		dateField.value = '';
		// Only enforced for a brand-new appointment — openEditModal() clears
		// this again so editing an appointment that's already in the past
		// (e.g. logging/adjusting a completed visit) isn't blocked.
		dateField.min = new Date().toISOString().slice( 0, 10 );
		currentEditTime = '';
		document.getElementById( 'dak-admin-appointment-time' ).value = '';
		resetSlots();
		document.getElementById( 'dak-admin-appointment-status' ).value = 'confirmed';
		document.getElementById( 'dak-admin-appointment-payment-status' ).value = 'pending';
		document.getElementById( 'dak-admin-appointment-payment-mode' ).value = 'manual';
		document.getElementById( 'dak-admin-appointment-notes' ).value = '';
		show( document.getElementById( 'dak-admin-appointment-guest-fields' ) );
		updateClinicOptions( '', 'clinic', '' );
		updateServiceOptions( '', 'clinic', [] );
	}

	function wireAdd( modal ) {
		// Delegated: the button lives inside the live-filtered section, so a
		// direct binding was lost after the first filter change.
		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest( '#dak-admin-appointment-add' ) ) {
				return;
			}

			clearErrors();
			setMode( modal, 'add' );
			resetModalFields();
			setModalTitle( 'Add Appointment' );
			openModal( modal );
		} );
	}

	/**
	 * Switches the shared dialog between its full Add/Edit form and the
	 * focused Reschedule view: shows/hides [data-edit-only] and
	 * [data-reschedule-only] parts, swaps field labels (data-edit-label /
	 * data-reschedule-label) and the primary button's text.
	 *
	 * @param {HTMLElement} modal   The .dak-modal wrapper.
	 * @param {string}      newMode 'add' | 'edit' | 'reschedule'.
	 */
	function setMode( modal, newMode ) {
		var isReschedule = 'reschedule' === newMode;
		var dialog = modal.querySelector( '.dak-modal-dialog' );

		mode = newMode;
		isSubmitting = false;
		dialog.setAttribute( 'data-mode', newMode );

		if ( isReschedule ) {
			dialog.setAttribute( 'aria-describedby', 'dak-admin-appointment-modal-subtitle' );
		} else {
			dialog.removeAttribute( 'aria-describedby' );
			rescheduleCtx = null;
		}

		modal.querySelectorAll( '[data-edit-only]' ).forEach( function ( el ) {
			el.hidden = isReschedule;
		} );

		modal.querySelectorAll( '[data-reschedule-only]' ).forEach( function ( el ) {
			el.hidden = ! isReschedule;
		} );

		modal.querySelectorAll( '[data-edit-label]' ).forEach( function ( el ) {
			el.textContent = el.getAttribute( isReschedule ? 'data-reschedule-label' : 'data-edit-label' );
		} );

		var saveButton = document.getElementById( 'dak-admin-appointment-save' );
		var schedule = modal.querySelector( '.dak-schedule-section' );

		setSaveLabel( isReschedule ? 'Confirm reschedule' : 'Save Appointment' );
		saveButton.disabled = false;
		saveButton.removeAttribute( 'aria-busy' );
		setRaw( 'dak-resched-hint', '' );

		if ( schedule ) {
			schedule.disabled = false;
		}
	}

	function setSaveLabel( text ) {
		var label = document.querySelector( '#dak-admin-appointment-save .dak-button-label' );

		if ( label ) {
			label.textContent = text;
		}
	}

	function setModalTitle( text ) {
		var title = document.getElementById( 'dak-admin-appointment-modal-title' );

		if ( title ) {
			title.textContent = text;
		}
	}

	function wireModalClose( modal, closeAttr ) {
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-' + closeAttr + ']' ) ) {
				closeModal( modal );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal.classList.contains( 'is-open' ) ) {
				closeModal( modal );
			}
		} );
	}

	function openModal( modal ) {
		modal.classList.add( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'dak-modal-open' );

		// Always open at the top — the body kept its scroll position from
		// the previous time this dialog was used, which left the first
		// fields hidden under the header.
		modal.scrollTop = 0;
		modal.querySelectorAll( '.dak-modal-body' ).forEach( function ( body ) {
			body.scrollTop = 0;
		} );
	}

	function closeModal( modal ) {
		modal.classList.remove( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'dak-modal-open' );
	}

	/**
	 * Fills the Clinic picker with the chosen doctor's physical clinics
	 * (clinic visits only). One clinic is picked automatically; a doctor
	 * with none shows a short note instead (the visit is saved without one).
	 *
	 * @param {string}        doctorId     Doctor user ID (may be empty).
	 * @param {string}        type         'clinic' or 'video'.
	 * @param {string|number} keepClinicId Clinic to reselect if it's still in the list (edit mode).
	 */
	function updateClinicOptions( doctorId, type, keepClinicId ) {
		var field = document.getElementById( 'dak-admin-appointment-clinic-field' );
		var select = document.getElementById( 'dak-admin-appointment-clinic' );
		var noClinicNote = document.getElementById( 'dak-admin-appointment-no-clinic-note' );

		if ( ! field || ! select ) {
			return;
		}

		var clinics = ( doctorId && clinicsByDoctor[ doctorId ] ) ? clinicsByDoctor[ doctorId ] : [];
		var applies = 'video' !== type && !! doctorId;
		var keep = keepClinicId && '0' !== String( keepClinicId ) ? String( keepClinicId ) : '';

		while ( select.options.length > 1 ) {
			select.remove( 1 );
		}

		clinics.forEach( function ( clinic ) {
			var option = document.createElement( 'option' );
			option.value = clinic.id;
			option.textContent = clinic.place ? clinic.name + ' \u2014 ' + clinic.place : clinic.name;
			option.setAttribute( 'data-location-id', clinic.location_id || 0 );
			select.appendChild( option );
		} );

		select.value = '';

		if ( keep && select.querySelector( 'option[value="' + keep + '"]' ) ) {
			select.value = keep;
		} else if ( 1 === clinics.length ) {
			select.value = String( clinics[0].id );
		}

		field.classList.toggle( 'dak-hidden', ! applies || ! clinics.length );

		if ( noClinicNote ) {
			noClinicNote.classList.toggle( 'dak-hidden', ! applies || clinics.length > 0 );
		}
	}

	/**
	 * The chosen clinic's ID ('' when none), only for a clinic visit.
	 *
	 * @return {string}
	 */
	function selectedClinicId() {
		var field = document.getElementById( 'dak-admin-appointment-clinic-field' );
		var select = document.getElementById( 'dak-admin-appointment-clinic' );

		if ( ! select || ! field || field.classList.contains( 'dak-hidden' ) ) {
			return '';
		}

		return select.value;
	}

	/**
	 * The chosen clinic's shared location ID — services' per-clinic prices
	 * are keyed by it. '' when no clinic is chosen.
	 *
	 * @return {string}
	 */
	function selectedClinicLocationId() {
		var select = document.getElementById( 'dak-admin-appointment-clinic' );

		if ( ! selectedClinicId() || ! select || ! select.selectedOptions.length ) {
			return '';
		}

		var locationId = select.selectedOptions[0].getAttribute( 'data-location-id' );

		return locationId && '0' !== locationId ? locationId : '';
	}

	/**
	 * Repopulates the (multi-select) Service <select> for the given doctor +
	 * type, keeping any IDs in `keepServiceIds` selected if still in the list
	 * (used when editing). Also updates the running total shown below it.
	 *
	 * @param {string} doctorId       Doctor user ID (may be empty).
	 * @param {string} type           'clinic' or 'video'.
	 * @param {Array<string|number>} keepServiceIds Service IDs to reselect if present.
	 */
	function updateServiceOptions( doctorId, type, keepServiceIds ) {
		var select = document.getElementById( 'dak-admin-appointment-service' );

		if ( ! select ) {
			return;
		}

		var keepIds = ( keepServiceIds || [] ).map( String );

		// Video consultations have no services (they're charged at the
		// doctor's video fee), so the picker is replaced by a short note.
		var serviceField = document.getElementById( 'dak-admin-appointment-service-field' );
		var videoNote = document.getElementById( 'dak-admin-appointment-video-fee-note' );

		if ( serviceField ) {
			serviceField.classList.toggle( 'dak-hidden', 'video' === type );
		}

		if ( videoNote ) {
			videoNote.classList.toggle( 'dak-hidden', 'video' !== type );
		}

		if ( 'video' === type ) {
			keepIds = [];
		}

		select.innerHTML = '';

		var services = servicesByDoctorAndType[ doctorId ] ? servicesByDoctorAndType[ doctorId ][ type ] : null;

		// With a clinic chosen: only services offered there (an empty
		// clinic_charges map means "every clinic"), at that clinic's price —
		// the same rule the server applies when saving.
		var locationId = 'video' === type ? '' : selectedClinicLocationId();

		if ( services && services.length ) {
			services.forEach( function ( service ) {
				var clinicCharges = service.clinic_charges || {};
				var hasClinicPrices = Object.keys( clinicCharges ).length > 0;

				if ( locationId && hasClinicPrices && ! Object.prototype.hasOwnProperty.call( clinicCharges, locationId ) ) {
					return;
				}

				var charge = ( locationId && hasClinicPrices ) ? parseFloat( clinicCharges[ locationId ] ) || 0 : parseFloat( service.charge ) || 0;
				var option = document.createElement( 'option' );
				option.value = service.id;
				option.textContent = service.name + ( charge > 0 ? ' (PKR ' + charge + ')' : '' );
				option.selected = -1 !== keepIds.indexOf( String( service.id ) );
				option.setAttribute( 'data-charge', charge );
				select.appendChild( option );
			} );
		}

		window.DAKSearchableSelect && window.DAKSearchableSelect.enhance( select );
		window.DAKSearchableSelect && window.DAKSearchableSelect.refresh( select );
		updateServiceTotal();
	}

	/**
	 * Shows the summed charge of every currently-selected service under the
	 * multi-select, so an admin picking several services can see the combined
	 * total before saving.
	 */
	function updateServiceTotal() {
		var select = document.getElementById( 'dak-admin-appointment-service' );
		var totalEl = document.getElementById( 'dak-admin-appointment-service-total' );

		if ( ! select || ! totalEl ) {
			return;
		}

		var total = Array.prototype.filter.call( select.options, function ( opt ) { return opt.selected; } )
			.reduce( function ( sum, opt ) { return sum + ( parseFloat( opt.getAttribute( 'data-charge' ) ) || 0 ); }, 0 );

		totalEl.textContent = total > 0 ? 'Total: PKR ' + total : '';
	}

	function wireDoctorTypeChange() {
		var doctorSelect = document.getElementById( 'dak-admin-appointment-doctor' );
		var typeSelect = document.getElementById( 'dak-admin-appointment-type' );

		if ( ! doctorSelect || ! typeSelect ) {
			return;
		}

		function refresh() {
			updateClinicOptions( doctorSelect.value, typeSelect.value, '' );
			updateServiceOptions( doctorSelect.value, typeSelect.value, [] );
		}

		doctorSelect.addEventListener( 'change', refresh );
		typeSelect.addEventListener( 'change', refresh );

		// A different clinic changes which services are offered and their
		// prices — keep any still-offered selections.
		var clinicSelect = document.getElementById( 'dak-admin-appointment-clinic' );

		if ( clinicSelect ) {
			clinicSelect.addEventListener( 'change', function () {
				var kept = Array.prototype.map.call( document.getElementById( 'dak-admin-appointment-service' ).selectedOptions, function ( opt ) { return opt.value; } );

				updateServiceOptions( doctorSelect.value, typeSelect.value, kept );
			} );
		}

		var serviceSelect = document.getElementById( 'dak-admin-appointment-service' );

		if ( serviceSelect ) {
			serviceSelect.addEventListener( 'change', updateServiceTotal );
		}
	}

	/**
	 * Wires the "Add/Edit Appointment" modal's date + slot-grid picker —
	 * mirrors the public booking page's Date & Time step
	 * (doctor_ak_available_slots, see Booking_Handler) instead of a
	 * free-text time field, so an admin sees the same available/booked/past
	 * slots a patient would.
	 */
	function wireDateTimePicker() {
		var doctorSelect = document.getElementById( 'dak-admin-appointment-doctor' );
		var typeSelect = document.getElementById( 'dak-admin-appointment-type' );
		var dateField = document.getElementById( 'dak-admin-appointment-date' );

		if ( ! doctorSelect || ! typeSelect || ! dateField ) {
			return;
		}

		function refresh( event ) {
			// Reschedule mode: doctor/type are fixed; only the date changes,
			// and a still-valid time survives it (see renderSlotGrid()).
			if ( 'reschedule' === mode ) {
				if ( event && event.target === dateField ) {
					refreshRescheduleSlots();
				}

				return;
			}

			// Picking a different doctor/type/date invalidates whatever slot
			// was selected for the previous one.
			document.getElementById( 'dak-admin-appointment-time' ).value = '';

			// Slots are day-specific — as soon as a doctor is chosen, default
			// the date to today (or its min, if that's later) so the slot
			// grid appears immediately instead of waiting on a separate date
			// pick. The admin can still change the date afterwards.
			if ( doctorSelect.value && ! dateField.value ) {
				var todayStr = new Date().toISOString().slice( 0, 10 );
				dateField.value = dateField.min && dateField.min > todayStr ? dateField.min : todayStr;
			}

			// Times come from the chosen clinic's own sessions.
			fetchSlots( doctorSelect.value, typeSelect.value, dateField.value, selectedClinicId() );
		}

		doctorSelect.addEventListener( 'change', refresh );
		typeSelect.addEventListener( 'change', refresh );
		dateField.addEventListener( 'change', refresh );

		var clinicSelect = document.getElementById( 'dak-admin-appointment-clinic' );

		if ( clinicSelect ) {
			clinicSelect.addEventListener( 'change', refresh );
		}
	}

	function resetSlots() {
		slotRequestSeq++;
		slotsLoading = false;
		document.getElementById( 'dak-admin-appointment-slots-groups' ).innerHTML = '';
		setSlotStatus( '', '' );
		hide( document.getElementById( 'dak-admin-appointment-no-slots' ) );
		show( document.getElementById( 'dak-admin-appointment-slots-hint' ) );
	}

	/**
	 * The slot list's single message line (a polite live region): loading,
	 * nothing available, or a load failure with a Retry button.
	 *
	 * @param {string} kind    '' (clear) | 'loading' | 'empty' | 'error'.
	 * @param {string} message Text to show.
	 */
	function setSlotStatus( kind, message ) {
		var status = document.getElementById( 'dak-admin-appointment-slots-status' );

		if ( ! status ) {
			return;
		}

		status.className = 'dak-slot-status' + ( kind ? ' is-' + kind : '' );
		status.textContent = message || '';

		if ( 'error' === kind ) {
			var retry = document.createElement( 'button' );
			retry.type = 'button';
			retry.className = 'dak-slot-retry';
			retry.setAttribute( 'data-dak-slot-retry', '' );
			retry.textContent = 'Try again';
			status.appendChild( document.createTextNode( ' ' ) );
			status.appendChild( retry );
		}
	}

	/**
	 * Loads the real slot grid for a doctor/type/date (the public booking
	 * page's doctor_ak_available_slots endpoint — never invented times).
	 *
	 * @param {string} doctorId Doctor user ID.
	 * @param {string} type     'clinic' or 'video'.
	 * @param {string} date     'YYYY-MM-DD'.
	 * @param {string} clinicId Optional: limit a clinic visit to that clinic's sessions.
	 */
	function fetchSlots( doctorId, type, date, clinicId ) {
		var groups = document.getElementById( 'dak-admin-appointment-slots-groups' );
		var noSlots = document.getElementById( 'dak-admin-appointment-no-slots' );
		var hint = document.getElementById( 'dak-admin-appointment-slots-hint' );
		var requestId = ++slotRequestSeq;

		if ( ! groups ) {
			return;
		}

		if ( ! doctorId || ! date ) {
			slotsLoading = false;
			groups.innerHTML = '';
			setSlotStatus( '', '' );
			hide( noSlots );

			if ( 'reschedule' === mode ) {
				updateRescheduleState();
			} else {
				show( hint );
			}

			return;
		}

		hide( hint );
		hide( noSlots );
		slotsLoading = true;
		groups.innerHTML = '';
		groups.setAttribute( 'aria-busy', 'true' );
		setSlotStatus( 'loading', 'Loading available times…' );
		updateRescheduleState();

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_available_slots' );
		formData.append( 'nonce', window.dakAdminAppointments.slotsNonce );
		formData.append( 'doctor_id', doctorId );
		formData.append( 'type', type );
		formData.append( 'date', date );

		if ( clinicId && '0' !== String( clinicId ) && 'video' !== type ) {
			formData.append( 'clinic_id', clinicId );
		}

		fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( requestId !== slotRequestSeq ) {
					return;
				}

				slotsLoading = false;
				groups.removeAttribute( 'aria-busy' );
				renderSlotGrid( ( result.success && result.data && result.data.slots ) ? result.data.slots : [], date );
			} )
			.catch( function () {
				if ( requestId !== slotRequestSeq ) {
					return;
				}

				slotsLoading = false;
				groups.removeAttribute( 'aria-busy' );
				groups.innerHTML = '';

				if ( 'reschedule' === mode ) {
					setSlotStatus( 'error', 'Couldn’t load available times.' );
					updateRescheduleState();
				} else {
					setSlotStatus( '', '' );
					show( noSlots );
				}
			} );
	}

	function formatTimeLabel( time ) {
		var parts = time.split( ':' );
		var hour = parseInt( parts[ 0 ], 10 );
		var period = hour >= 12 ? 'PM' : 'AM';
		var displayHour = hour % 12;

		if ( 0 === displayHour ) {
			displayHour = 12;
		}

		return displayHour + ':' + parts[ 1 ] + ' ' + period;
	}

	/**
	 * "09:30 AM" — the same 'h:i A' pattern the Appointments list prints, so
	 * the Current/New comparison reads exactly like the row it came from.
	 */
	function formatTimePadded( time ) {
		var label = formatTimeLabel( time );

		return /^\d:/.test( label ) ? '0' + label : label;
	}

	/**
	 * A stored 'YYYY-MM-DD' as an unambiguous label ("Tue 14 Oct 2026", or
	 * the long form for the date readout). Built in UTC from the date parts
	 * themselves, so the browser's timezone can never shift the day — the
	 * date is already in the site's scheduling timezone.
	 */
	function formatDateLabel( ymd, long ) {
		var parts = String( ymd || '' ).split( '-' );

		if ( 3 !== parts.length ) {
			return ymd || '';
		}

		var date = new Date( Date.UTC( +parts[ 0 ], +parts[ 1 ] - 1, +parts[ 2 ] ) );
		var options = long
			? { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }
			: { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' };

		try {
			return new Intl.DateTimeFormat( 'en-GB', options ).format( date ).replace( ',', '' );
		} catch ( e ) {
			return ymd;
		}
	}

	/**
	 * Renders the day's slots as one native radio group styled as buttons
	 * (arrow keys move between times, the checked one is announced as
	 * selected). Unavailable times stay visible but disabled, with a text
	 * reason. More than SLOT_GROUP_THRESHOLD visible times are grouped into
	 * Morning / Afternoon / Evening by their actual start times.
	 *
	 * @param {Array}  slots From doctor_ak_available_slots.
	 * @param {string} date  The date they belong to.
	 */
	function renderSlotGrid( slots, date ) {
		var groupsEl = document.getElementById( 'dak-admin-appointment-slots-groups' );
		var noSlots = document.getElementById( 'dak-admin-appointment-no-slots' );
		var timeField = document.getElementById( 'dak-admin-appointment-time' );
		var isReschedule = 'reschedule' === mode;

		groupsEl.innerHTML = '';
		setSlotStatus( '', '' );

		// The appointment being edited already occupies its own slot — the
		// server reports it 'booked' (by this same appointment), but that
		// shouldn't stop the admin from keeping it selected.
		if ( currentEditTime && ! isReschedule ) {
			slots = slots.map( function ( slot ) {
				if ( slot.time === currentEditTime && 'available' !== slot.status ) {
					return { time: slot.time, status: 'available', is_instant: false, surcharge: 0 };
				}
				return slot;
			} );
		}

		if ( isReschedule ) {
			slots = slots
				// Times already gone today are noise when choosing a new one.
				.filter( function ( slot ) { return 'past' !== slot.status; } )
				// Its own current slot can't be "moved" to — shown, not offered.
				.map( function ( slot ) {
					if ( rescheduleCtx && date === rescheduleCtx.date && slot.time === rescheduleCtx.time ) {
						return { time: slot.time, status: 'current' };
					}
					return slot;
				} );
		}

		var openSlots = slots.filter( function ( slot ) { return 'available' === slot.status; } );

		// Reschedule: an earlier choice survives a date change only if it's
		// still open. (Add/Edit keep their existing behaviour untouched.)
		if ( isReschedule && timeField.value && ! openSlots.some( function ( slot ) { return slot.time === timeField.value; } ) ) {
			var hadTime = timeField.value;
			timeField.value = '';

			if ( isReschedule && hadTime ) {
				setFieldError( 'time', formatTimeLabel( hadTime ) + ' isn’t available on this date — choose another time.', false );
			}
		}

		if ( ! openSlots.length ) {
			if ( isReschedule ) {
				setSlotStatus( 'empty', 'No available times on ' + formatDateLabel( date ) + '. Try another date.' );
				updateRescheduleState();
				return;
			}

			if ( ! slots.length ) {
				show( noSlots );
				return;
			}
		}

		hide( noSlots );

		var periods = [
			{ label: 'Morning', test: function ( t ) { return t < '12:00'; } },
			{ label: 'Afternoon', test: function ( t ) { return t >= '12:00' && t < '17:00'; } },
			{ label: 'Evening', test: function ( t ) { return t >= '17:00'; } },
		];
		var buckets = slots.length > SLOT_GROUP_THRESHOLD
			? periods.map( function ( period ) {
				return { label: period.label, slots: slots.filter( function ( slot ) { return period.test( slot.time ); } ) };
			} ).filter( function ( bucket ) { return bucket.slots.length; } )
			: [ { label: '', slots: slots } ];

		buckets.forEach( function ( bucket, index ) {
			var group = document.createElement( 'div' );
			group.className = 'dak-slot-group';

			if ( bucket.label ) {
				var heading = document.createElement( 'p' );
				heading.className = 'dak-slot-group-title';
				heading.id = 'dak-admin-appointment-slot-group-' + index;
				heading.textContent = bucket.label;
				group.setAttribute( 'role', 'group' );
				group.setAttribute( 'aria-labelledby', heading.id );
				group.appendChild( heading );
			}

			var grid = document.createElement( 'div' );
			grid.className = 'dak-slot-grid';

			bucket.slots.forEach( function ( slot ) {
				grid.appendChild( slotOption( slot, timeField.value ) );
			} );

			group.appendChild( grid );
			groupsEl.appendChild( group );
		} );

		if ( focusSlotsAfterLoad ) {
			focusSlotsAfterLoad = false;
			var firstOpen = groupsEl.querySelector( '.dak-slot-input:not(:disabled)' );

			if ( firstOpen ) {
				firstOpen.focus();
			}
		}

		updateRescheduleState();
	}

	/**
	 * One time option: <label><input type=radio><span>9:30 AM</span></label>.
	 */
	function slotOption( slot, selectedTime ) {
		var reasons = { booked: 'Booked', past: 'Passed', current: 'Current time' };
		var isOpen = 'available' === slot.status;
		var label = document.createElement( 'label' );
		var input = document.createElement( 'input' );
		var face = document.createElement( 'span' );
		var time = document.createElement( 'span' );

		label.className = 'dak-slot' + ( isOpen ? '' : ' is-unavailable is-' + slot.status );
		input.type = 'radio';
		input.name = 'dak-admin-appointment-slot';
		input.value = slot.time;
		input.className = 'dak-slot-input';
		input.disabled = ! isOpen;
		input.checked = isOpen && slot.time === selectedTime;

		face.className = 'dak-slot-face';
		time.className = 'dak-slot-time';
		time.textContent = formatTimeLabel( slot.time );
		face.appendChild( time );

		if ( ! isOpen ) {
			var reason = document.createElement( 'span' );
			reason.className = 'dak-slot-reason';
			reason.textContent = reasons[ slot.status ] || 'Unavailable';
			face.appendChild( reason );
		}

		label.appendChild( input );
		label.appendChild( face );

		return label;
	}

	/**
	 * Native radios: a choice (click, Space, or arrow keys) updates the
	 * hidden time field the save/reschedule requests read. Delegated, since
	 * the radios are rebuilt for every date.
	 */
	function wireSlotChoice() {
		var groups = document.getElementById( 'dak-admin-appointment-slots-groups' );

		if ( ! groups ) {
			return;
		}

		groups.addEventListener( 'change', function ( event ) {
			if ( event.target.classList.contains( 'dak-slot-input' ) && event.target.checked ) {
				document.getElementById( 'dak-admin-appointment-time' ).value = event.target.value;
				clearFieldError( 'time' );
				updateRescheduleState();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-slot-retry]' ) ) {
				refreshRescheduleSlots();
			}
		} );
	}

	function clearFieldError( field ) {
		var el = document.querySelector( '#dak-admin-appointment-modal .dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = '';
		}

		if ( 'time' === field ) {
			var groups = document.getElementById( 'dak-admin-appointment-slots-groups' );

			if ( groups ) {
				groups.classList.remove( 'is-invalid' );
				groups.removeAttribute( 'aria-invalid' );
				groups.removeAttribute( 'aria-errormessage' );
			}
		}
	}

	/**
	 * Inline field message. `invalid` (validation errors only) also marks
	 * the slot group red; an informational note doesn't.
	 */
	function setFieldError( field, message, invalid ) {
		var el = document.querySelector( '#dak-admin-appointment-modal .dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = message;
		}

		if ( 'time' === field && invalid ) {
			var groups = document.getElementById( 'dak-admin-appointment-slots-groups' );

			if ( groups ) {
				groups.classList.add( 'is-invalid' );
				groups.setAttribute( 'aria-invalid', 'true' );
				groups.setAttribute( 'aria-errormessage', 'dak-admin-appointment-time-error' );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Reschedule mode                                                     */
	/* ------------------------------------------------------------------ */

	function wireReschedule( modal ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-appointment-reschedule]' );

			if ( ! trigger ) {
				return;
			}

			var attr = function ( name ) {
				return trigger.getAttribute( name ) || '';
			};

			openReschedule( modal, {
				id: attr( 'data-appointment-id' ),
				label: attr( 'data-appointment-label' ),
				doctorId: attr( 'data-doctor-id' ),
				type: attr( 'data-type' ) || 'clinic',
				clinicId: attr( 'data-clinic-id' ),
				date: attr( 'data-date' ),
				time: attr( 'data-time' ),
				patientName: attr( 'data-patient-name' ),
				doctorName: attr( 'data-doctor-name' ),
				serviceName: attr( 'data-service-name' ),
				typeLabel: attr( 'data-type-label' ),
				clinicName: attr( 'data-clinic-name' ),
			} );
		} );
	}

	function openReschedule( modal, ctx ) {
		clearErrors();
		setMode( modal, 'reschedule' );
		rescheduleCtx = ctx;
		setModalTitle( 'Reschedule appointment' );

		document.getElementById( 'dak-admin-appointment-id' ).value = ctx.id || '0';

		// Read-only summary of what's being moved.
		setText( 'dak-resched-patient', ctx.patientName );
		setRaw( 'dak-resched-label', ctx.label );
		setText( 'dak-resched-doctor', ctx.doctorName ? 'Dr. ' + ctx.doctorName : '' );

		// "General Consultation · Clinic visit" — the visit type alone when
		// the service just repeats it (a video consultation).
		var visit = [ ctx.serviceName, ctx.typeLabel ].filter( function ( part, index, all ) {
			return part && ( 0 === index || part.toLowerCase() !== all[ 0 ].toLowerCase() );
		} );
		setText( 'dak-resched-visit', visit.join( ' · ' ) );
		setOptional( 'dak-resched-clinic', 'video' === ctx.type ? '' : ctx.clinicName );
		setText( 'dak-resched-current', formatDateLabel( ctx.date ) + ' · ' + formatTimePadded( ctx.time ) );
		setText( 'dak-resched-compare-current', formatDateLabel( ctx.date ) + ' · ' + formatTimePadded( ctx.time ) );

		// Earliest date: today in the site's scheduling timezone. Starts on the
		// current date if that's still ahead, otherwise today.
		var today = ( window.dakAdminAppointments && window.dakAdminAppointments.today ) || new Date().toISOString().slice( 0, 10 );
		var dateField = document.getElementById( 'dak-admin-appointment-date' );

		dateField.min = today;
		dateField.value = ctx.date && ctx.date >= today ? ctx.date : today;
		currentEditTime = '';
		document.getElementById( 'dak-admin-appointment-time' ).value = '';

		openModal( modal );
		refreshRescheduleSlots();
	}

	function refreshRescheduleSlots() {
		if ( ! rescheduleCtx ) {
			return;
		}

		var dateField = document.getElementById( 'dak-admin-appointment-date' );
		var date = dateField.value;

		clearFieldError( 'date' );
		clearFieldError( 'time' );
		updateDateReadout( date );

		// Typed dates can bypass the picker's own min.
		if ( date && dateField.min && date < dateField.min ) {
			resetSlots();
			hide( document.getElementById( 'dak-admin-appointment-slots-hint' ) );
			setFieldError( 'date', 'Choose today or a later date.', true );
			updateRescheduleState();
			return;
		}

		fetchSlots( rescheduleCtx.doctorId, rescheduleCtx.type, date, rescheduleCtx.clinicId );
	}

	function updateDateReadout( date ) {
		setRaw( 'dak-admin-appointment-date-readout', date ? formatDateLabel( date, true ) : '' );
	}

	/**
	 * Footer state: what's still missing (and Confirm disabled), or the
	 * Current → New comparison (and Confirm enabled).
	 */
	function updateRescheduleState() {
		if ( 'reschedule' !== mode || ! rescheduleCtx ) {
			return;
		}

		var date = document.getElementById( 'dak-admin-appointment-date' ).value;
		var time = document.getElementById( 'dak-admin-appointment-time' ).value;
		var list = document.getElementById( 'dak-resched-compare-list' );
		var saveButton = document.getElementById( 'dak-admin-appointment-save' );
		var hint = '';

		if ( ! date ) {
			hint = 'Choose a new date to see available times.';
		} else if ( slotsLoading ) {
			hint = 'Checking availability…';
		} else if ( ! time ) {
			hint = 'Select an available time to continue.';
		} else if ( date === rescheduleCtx.date && time === rescheduleCtx.time ) {
			hint = 'Choose a date or time different from the current one.';
		}

		var isReady = '' === hint;

		setRaw( 'dak-resched-hint', hint );
		document.getElementById( 'dak-resched-hint' ).hidden = isReady;

		if ( list ) {
			list.hidden = ! isReady;
		}

		if ( isReady ) {
			setText( 'dak-resched-compare-new', formatDateLabel( date ) + ' · ' + formatTimePadded( time ) );
		}

		saveButton.disabled = ! isReady || isSubmitting;
	}

	function submitReschedule( modal ) {
		if ( isSubmitting || ! rescheduleCtx ) {
			return;
		}

		updateRescheduleState();

		var saveButton = document.getElementById( 'dak-admin-appointment-save' );

		if ( saveButton.disabled ) {
			return;
		}

		var schedule = modal.querySelector( '.dak-schedule-section' );
		var ctx = rescheduleCtx;
		var date = document.getElementById( 'dak-admin-appointment-date' ).value;
		var time = document.getElementById( 'dak-admin-appointment-time' ).value;

		clearErrors();
		isSubmitting = true;
		saveButton.disabled = true;
		saveButton.setAttribute( 'aria-busy', 'true' );
		setSaveLabel( 'Rescheduling…' );

		// Freeze the choice while the server checks it (a fieldset's
		// `disabled` covers the date input and every radio).
		if ( schedule ) {
			schedule.disabled = true;
		}

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_admin_appointment_reschedule' );
		formData.append( 'nonce', window.dakAdminAppointments.nonce );
		formData.append( 'appointment_id', ctx.id );
		formData.append( 'date', date );
		formData.append( 'time', time );

		function finish() {
			isSubmitting = false;
			saveButton.removeAttribute( 'aria-busy' );
			setSaveLabel( 'Confirm reschedule' );

			if ( schedule ) {
				schedule.disabled = false;
			}
		}

		fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				finish();

				if ( result.success ) {
					rescheduled( modal, ctx, date, time );
					return;
				}

				if ( 'reschedule' !== mode || ctx !== rescheduleCtx ) {
					return; // Dialog was closed or reused meanwhile.
				}

				var message = ( result.data && result.data.message ) || 'Something went wrong. Please try again.';

				if ( result.data && 'slot_unavailable' === result.data.code ) {
					// Keep the chosen date; drop the taken time and reload
					// what's actually open now.
					document.getElementById( 'dak-admin-appointment-time' ).value = '';
					focusSlotsAfterLoad = true;
					refreshRescheduleSlots();
					setFieldError( 'time', message, true );
					return;
				}

				showGeneralError( message );
				focusGeneralError();
				updateRescheduleState();
			} )
			.catch( function () {
				finish();

				if ( 'reschedule' === mode && ctx === rescheduleCtx ) {
					showGeneralError( 'Something went wrong. Please check your connection and try again.' );
					focusGeneralError();
					updateRescheduleState();
				}
			} );
	}

	/**
	 * Success: close, confirm in a toast, and refresh the list in place (the
	 * live filter keeps the admin's current filters), highlighting the moved
	 * row. Without a live-filter form on the page, reload and show the
	 * message after.
	 */
	function rescheduled( modal, ctx, date, time ) {
		var message = 'Appointment ' + ( ctx.label ? ctx.label + ' ' : '' ) + 'rescheduled to ' + formatDateLabel( date ) + ' at ' + formatTimePadded( time ) + '.';
		var filterForm = document.getElementById( 'dak-appt-filter-form' );

		closeModal( modal );

		if ( filterForm && filterForm.hasAttribute( 'data-live-filter' ) && 'function' === typeof filterForm.requestSubmit ) {
			pendingHighlightId = ctx.id;
			showToast( message );
			filterForm.requestSubmit();
			return;
		}

		try {
			window.sessionStorage.setItem( FLASH_KEY, message );
		} catch ( e ) {
			// Storage unavailable — the reload still shows the new time.
		}

		window.location.reload();
	}

	document.addEventListener( 'dak:live-filter-updated', function () {
		if ( ! pendingHighlightId ) {
			return;
		}

		var row = document.getElementById( 'dak-appointment-' + pendingHighlightId );
		pendingHighlightId = '';

		if ( ! row ) {
			return; // Moved outside the current filters.
		}

		row.classList.add( 'is-just-updated' );
		row.scrollIntoView( { block: 'center', behavior: 'smooth' } );

		// The Reschedule button that opened the dialog is gone with the old
		// list — put focus on the same row instead of losing it.
		var focusTarget = row.querySelector( '[data-admin-appointment-view]' );

		if ( focusTarget ) {
			focusTarget.focus( { preventScroll: true } );
		}

		window.setTimeout( function () {
			row.classList.remove( 'is-just-updated' );
		}, 2400 );
	} );

	function focusGeneralError() {
		var el = document.getElementById( 'dak-admin-appointment-general-error' );

		if ( el ) {
			el.setAttribute( 'tabindex', '-1' );
			el.focus();
		}
	}

	function showToast( message ) {
		var toast = document.getElementById( 'dak-admin-toast' );

		if ( ! toast ) {
			toast = document.createElement( 'div' );
			toast.id = 'dak-admin-toast';
			toast.className = 'dak-admin-toast';
			toast.setAttribute( 'role', 'status' );
			toast.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( toast );
		}

		toast.textContent = message;
		toast.classList.add( 'is-visible' );
		window.clearTimeout( toast.__dakTimer );
		toast.__dakTimer = window.setTimeout( function () {
			toast.classList.remove( 'is-visible' );
		}, 5000 );
	}

	function showFlash() {
		var message = '';

		try {
			message = window.sessionStorage.getItem( FLASH_KEY ) || '';
			window.sessionStorage.removeItem( FLASH_KEY );
		} catch ( e ) {
			message = '';
		}

		if ( message ) {
			showToast( message );
		}
	}

	/**
	 * `?reschedule=ID` (the Dashboard overview's Reschedule link) opens that
	 * row's Reschedule dialog once, then drops the parameter so a refresh
	 * doesn't reopen it.
	 */
	function openRescheduleFromUrl() {
		var url = new URL( window.location.href );
		var id = url.searchParams.get( 'reschedule' );

		if ( ! id ) {
			return;
		}

		url.searchParams.delete( 'reschedule' );
		window.history.replaceState( null, '', url.toString() );

		var trigger = document.querySelector( '[data-admin-appointment-reschedule][data-appointment-id="' + String( id ).replace( /[^0-9]/g, '' ) + '"]' );

		if ( trigger ) {
			trigger.click();
		} else {
			showToast( 'That appointment can’t be rescheduled from here — its time may already have been changed.' );
		}
	}

	/**
	 * On phones the on-screen keyboard shrinks the visible area without
	 * changing 100dvh in every browser — track the visual viewport so the
	 * dialog (and its footer) always fits what's actually visible.
	 */
	function wireViewportHeight( modal ) {
		if ( ! window.visualViewport ) {
			return;
		}

		function update() {
			if ( modal.classList.contains( 'is-open' ) ) {
				modal.style.setProperty( '--dak-vvh', window.visualViewport.height + 'px' );
			}
		}

		window.visualViewport.addEventListener( 'resize', update );
		new MutationObserver( update ).observe( modal, { attributes: true, attributeFilter: [ 'class' ] } );
	}

	function wirePatientToggle() {
		var patientSelect = document.getElementById( 'dak-admin-appointment-patient' );
		var guestFields = document.getElementById( 'dak-admin-appointment-guest-fields' );

		if ( ! patientSelect || ! guestFields ) {
			return;
		}

		patientSelect.addEventListener( 'change', function () {
			guestFields.classList.toggle( 'dak-hidden', '' !== patientSelect.value );
		} );
	}

	function wireEdit( modal ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-appointment-edit]' );

			if ( ! trigger ) {
				return;
			}

			clearErrors();
			setMode( modal, 'edit' );
			setModalTitle( 'Edit Appointment' );

			document.getElementById( 'dak-admin-appointment-id' ).value = trigger.getAttribute( 'data-appointment-id' ) || '0';
			document.getElementById( 'dak-admin-appointment-doctor' ).value = trigger.getAttribute( 'data-doctor-id' ) || '';
			refreshSearchable( 'dak-admin-appointment-doctor' );
			document.getElementById( 'dak-admin-appointment-type' ).value = trigger.getAttribute( 'data-type' ) || 'clinic';
			document.getElementById( 'dak-admin-appointment-patient' ).value = trigger.getAttribute( 'data-patient-id' ) && '0' !== trigger.getAttribute( 'data-patient-id' ) ? trigger.getAttribute( 'data-patient-id' ) : '';
			refreshSearchable( 'dak-admin-appointment-patient' );
			document.getElementById( 'dak-admin-appointment-guest-name' ).value = trigger.getAttribute( 'data-guest-name' ) || '';
			document.getElementById( 'dak-admin-appointment-guest-email' ).value = trigger.getAttribute( 'data-guest-email' ) || '';
			document.getElementById( 'dak-admin-appointment-guest-phone' ).value = trigger.getAttribute( 'data-guest-phone' ) || '';
			var dateField = document.getElementById( 'dak-admin-appointment-date' );
			dateField.min = ''; // Editing an existing (possibly already-past) appointment isn't restricted to future dates — see resetModalFields().
			dateField.value = trigger.getAttribute( 'data-date' ) || '';
			var editTime = trigger.getAttribute( 'data-time' ) || '';
			currentEditTime = editTime;
			document.getElementById( 'dak-admin-appointment-time' ).value = editTime;
			document.getElementById( 'dak-admin-appointment-status' ).value = trigger.getAttribute( 'data-status' ) || 'confirmed';
			document.getElementById( 'dak-admin-appointment-payment-status' ).value = trigger.getAttribute( 'data-payment-status' ) || 'pending';
			document.getElementById( 'dak-admin-appointment-payment-mode' ).value = trigger.getAttribute( 'data-payment-mode' ) || 'manual';
			document.getElementById( 'dak-admin-appointment-notes' ).value = trigger.getAttribute( 'data-notes' ) || '';

			document.getElementById( 'dak-admin-appointment-guest-fields' ).classList.toggle(
				'dak-hidden',
				'' !== document.getElementById( 'dak-admin-appointment-patient' ).value
			);

			var editServiceIds = [];

			try {
				editServiceIds = JSON.parse( trigger.getAttribute( 'data-service-ids' ) || '[]' );
			} catch ( e ) {
				editServiceIds = [];
			}

			if ( ! editServiceIds.length && trigger.getAttribute( 'data-service-id' ) && '0' !== trigger.getAttribute( 'data-service-id' ) ) {
				editServiceIds = [ trigger.getAttribute( 'data-service-id' ) ];
			}

			// Clinic first: it decides which services are listed and their prices.
			updateClinicOptions( trigger.getAttribute( 'data-doctor-id' ) || '', trigger.getAttribute( 'data-type' ) || 'clinic', trigger.getAttribute( 'data-clinic-id' ) || '' );
			updateServiceOptions( trigger.getAttribute( 'data-doctor-id' ) || '', trigger.getAttribute( 'data-type' ) || 'clinic', editServiceIds );

			fetchSlots( trigger.getAttribute( 'data-doctor-id' ) || '', trigger.getAttribute( 'data-type' ) || 'clinic', dateField.value, selectedClinicId() );

			openModal( modal );
		} );
	}

	function wireView( viewModal ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-appointment-view]' );

			if ( ! trigger ) {
				return;
			}

			var attr = function ( name ) {
				return trigger.getAttribute( name ) || '';
			};
			var appointmentLabel = attr( 'data-appointment-label' );
			var clinicName = attr( 'data-clinic-name' );
			var clinicAddress = attr( 'data-clinic-address' );
			var doctorName = attr( 'data-doctor-name' );
			var title = document.getElementById( 'dak-admin-appointment-view-modal-title' );

			if ( title ) {
				title.textContent = appointmentLabel ? 'Appointment ' + appointmentLabel : 'Appointment details';
			}

			// Patient
			setText( 'dak-admin-appointment-view-patient', attr( 'data-patient-name' ) );
			setText( 'dak-admin-appointment-view-id', appointmentLabel );
			setText( 'dak-admin-appointment-view-phone', attr( 'data-patient-phone' ) );
			setOptional( 'dak-admin-appointment-view-age', attr( 'data-patient-age' ) );

			// Schedule and visit — the date/time strings are pre-formatted
			// server-side with the list's own formatter, so both always match.
			// Older callers (Dashboard overview) only send data-datetime-label.
			setText( 'dak-admin-appointment-view-date', attr( 'data-date-label' ) || attr( 'data-datetime-label' ) );
			setText( 'dak-admin-appointment-view-time', attr( 'data-time-label' ) );
			setText( 'dak-admin-appointment-view-doctor', doctorName ? 'Dr. ' + doctorName : '' );
			setText( 'dak-admin-appointment-view-service', attr( 'data-service-name' ) );
			setText( 'dak-admin-appointment-view-type', attr( 'data-type-label' ) );
			// Some clinics' saved street address starts with the clinic's own
			// name — drop that repeat so the name isn't printed twice.
			var locationAddress = clinicAddress;

			if ( clinicName && locationAddress && 0 === locationAddress.toLowerCase().indexOf( clinicName.toLowerCase() ) ) {
				locationAddress = locationAddress.slice( clinicName.length ).replace( /^[\s,.;:–—-]+/, '' );
			}

			setOptional( 'dak-admin-appointment-view-location', clinicName && locationAddress ? clinicName + '\n' + locationAddress : clinicName );
			setText( 'dak-admin-appointment-view-status', attr( 'data-status-label' ) );

			// Payment
			setText( 'dak-admin-appointment-view-charge', attr( 'data-charge' ) );
			setText( 'dak-admin-appointment-view-payment-status', attr( 'data-payment-status-label' ) );
			setText( 'dak-admin-appointment-view-payment-mode', attr( 'data-payment-mode' ) );
			setOptional( 'dak-admin-appointment-view-order', attr( 'data-online-order-id' ) );
			setOptional( 'dak-admin-appointment-view-refund', attr( 'data-refund-label' ) );

			// Notes
			setText( 'dak-admin-appointment-view-notes', attr( 'data-notes' ) );

			var printLink = document.getElementById( 'dak-admin-appointment-view-print' );

			if ( printLink ) {
				printLink.href = trigger.getAttribute( 'data-print-url' ) || '#';
			}

			var staleCopy = viewModal.querySelector( '.dak-appointment-copy-fallback' );

			if ( staleCopy ) {
				staleCopy.parentNode.removeChild( staleCopy );
			}

			// For the copied message's wording (see appointmentSummary()).
			viewModal.setAttribute( 'data-copy-status', attr( 'data-status' ) );
			viewModal.setAttribute( 'data-copy-payment-status', attr( 'data-payment-status' ) );
			viewModal.setAttribute( 'data-copy-type', attr( 'data-type' ) );
			viewModal.setAttribute( 'data-copy-date', attr( 'data-message-date' ) );
			viewModal.setAttribute( 'data-copy-time', attr( 'data-message-time' ) );

			resetCopyButton();
			openModal( viewModal );
		} );
	}

	/*
	 * "Copy details" in the Appointment details dialog. Builds a short,
	 * ready-to-send message to the patient from what the dialog shows:
	 * greeting, an opening sentence that follows the appointment's status
	 * ("Your appointment with *Dr. X* has been scheduled successfully."),
	 * a bulleted list of details with bold labels, fee and payment status,
	 * a closing line and a thank-you. Copied twice over:
	 *   - text/plain, for WhatsApp, SMS or chat (WhatsApp shows *x* bold);
	 *   - text/html, a formatted version, so pasting into an email keeps
	 *     the layout.
	 * Phone, age, payment mode/reference, refund and internal notes are not
	 * part of the message; empty fields are left out. Wording comes from the
	 * template (data-strings), so it is translatable.
	 */
	var copyResetTimer = 0;

	function copyStrings() {
		var button = document.getElementById( 'dak-admin-appointment-view-copy' );

		try {
			return JSON.parse( ( button && button.getAttribute( 'data-strings' ) ) || '{}' );
		} catch ( e ) {
			return {};
		}
	}

	function resetCopyButton() {
		var button = document.getElementById( 'dak-admin-appointment-view-copy' );
		var status = document.getElementById( 'dak-admin-appointment-view-copy-status' );

		if ( ! button ) {
			return;
		}

		window.clearTimeout( copyResetTimer );
		button.classList.remove( 'is-copied' );
		button.querySelector( '[data-dak-copy-label]' ).textContent = copyStrings().copy || 'Copy details';

		if ( status ) {
			status.textContent = '';
		}
	}

	function appointmentSummary( viewModal ) {
		var strings = copyStrings();
		var labels = strings.labels || {};
		var status = viewModal.getAttribute( 'data-copy-status' ) || '';
		var type = viewModal.getAttribute( 'data-copy-type' ) || '';

		// The dialog's own (already formatted) values; '' when empty.
		var field = function ( id ) {
			var el = document.getElementById( 'dak-admin-appointment-view-' + id );
			var wrapper = el ? el.closest( '[data-optional]' ) : null;
			var value = el && ! ( wrapper && wrapper.hidden ) ? el.textContent.trim() : '';

			// A multi-line value (clinic name + address) reads as one line.
			return '—' === value ? '' : value.split( /\s*\n\s*/ ).join( ', ' );
		};

		var patient = field( 'patient' );
		var doctor = field( 'doctor' );
		var site = strings.site || '';

		// Which wording: scheduled, awaiting payment, rescheduled, cancelled, checked in, completed.
		var kind = {
			confirmed: 'scheduled',
			paid: 'scheduled',
			pending_payment: 'pending',
			rescheduled: 'rescheduled',
			cancelled: 'cancelled',
			checked_in: 'checkedin',
			completed: 'completed'
		}[ status ] || 'other';
		var intros = strings.intros || {};
		var closings = strings.closings || {};
		var visitTypes = strings.visitTypes || {};

		var location = field( 'location' );

		if ( location && ! /[.!?]$/.test( location ) ) {
			location += '.';
		}

		var fee = field( 'charge' );

		if ( /^PKR\s*0(\.0+)?$/i.test( fee ) ) {
			fee = '';
		}

		var details = [
			[ labels.id, field( 'id' ) ],
			// Friendlier long forms ("10 October 2026", "7:20 PM") sent by the row.
			[ labels.date, viewModal.getAttribute( 'data-copy-date' ) || field( 'date' ) ],
			[ labels.time, viewModal.getAttribute( 'data-copy-time' ) || field( 'time' ) ],
			// The doctor is named in the opening sentence; listed only when it isn't.
			[ labels.doctor, doctor && intros[ kind ] ? '' : doctor ],
			[ labels.service, field( 'service' ) ],
			[ labels.type, visitTypes[ type ] || field( 'type' ) ],
			[ labels.location, location ]
		].filter( function ( row ) {
			return row[0] && row[1];
		} );

		// Fee and payment aren't repeated on a cancelled appointment's message.
		var payment = 'cancelled' === kind ? [] : [
			[ labels.amount, fee ],
			[ labels.payment, field( 'payment-status' ) ]
		].filter( function ( row ) {
			return row[0] && row[1];
		} );

		var greeting = patient ? strings.greeting.replace( '%s', patient ) : ( strings.greetingNoName || '' );
		var intro = intros[ kind ] && doctor ? intros[ kind ] : ( intros.other || '' );
		var closing = closings[ kind ] || closings[ 'default' ] || '';

		// Plain text: WhatsApp shows *text* as bold.
		var b = function ( value ) {
			return '*' + value + '*';
		};
		var text = [
			greeting,
			intro.replace( '%s', b( doctor ) ),
			b( strings.detailsHeading || 'Appointment Details' ),
			details.map( function ( row ) {
				return '• ' + b( row[0] + ':' ) + ' ' + row[1];
			} ).join( '\n' ),
			payment.map( function ( row ) {
				return b( row[0] + ':' ) + ' ' + row[1];
			} ).join( '\n' ),
			closing,
			site ? ( strings.thanks || '%s' ).replace( '%s', b( site ) ) : ''
		].filter( function ( part ) {
			return '' !== String( part ).trim();
		} ).join( '\n\n' );

		// Formatted copy for email: the same message with real bold and a list.
		var esc = function ( value ) {
			return String( value ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
		};
		var font = 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#182b3a;';
		var p = function ( inner, extra ) {
			return inner ? '<p style="margin:0 0 14px;' + font + ( extra || '' ) + '">' + inner + '</p>' : '';
		};
		var strong = function ( value ) {
			return '<strong>' + esc( value ) + '</strong>';
		};

		var html = '<div style="' + font + '">'
			+ p( esc( greeting ) )
			+ p( esc( intro ).replace( '%s', strong( doctor ) ) )
			+ p( strong( strings.detailsHeading || 'Appointment Details' ), 'margin-bottom:6px;' )
			+ ( details.length ? '<ul style="margin:0 0 14px;padding-left:22px;' + font + '">' + details.map( function ( row ) {
				return '<li style="margin:0 0 4px;">' + strong( row[0] + ':' ) + ' ' + esc( row[1] ) + '</li>';
			} ).join( '' ) + '</ul>' : '' )
			+ p( payment.map( function ( row ) {
				return strong( row[0] + ':' ) + ' ' + esc( row[1] );
			} ).join( '<br>' ) )
			+ p( esc( closing ) )
			+ ( site ? p( esc( strings.thanks || '%s' ).replace( '%s', strong( site ) ), 'margin-bottom:0;' ) : '' )
			+ '</div>';

		return { text: text, html: html };
	}

	function wireCopy( viewModal ) {
		var button = document.getElementById( 'dak-admin-appointment-view-copy' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var summary = appointmentSummary( viewModal );

			copyToClipboard( summary ).then( showCopied, function () {
				// Last resort: show the text selected, so Ctrl+C copies it.
				selectForManualCopy( summary.text, viewModal );
				announce( copyStrings().failed || '' );
			} );
		} );

		function showCopied() {
			var strings = copyStrings();

			button.classList.add( 'is-copied' );
			button.querySelector( '[data-dak-copy-label]' ).textContent = strings.copied || 'Copied';
			announce( strings.done || '' );

			window.clearTimeout( copyResetTimer );
			copyResetTimer = window.setTimeout( resetCopyButton, 2500 );
		}

		function announce( message ) {
			var status = document.getElementById( 'dak-admin-appointment-view-copy-status' );

			if ( ! status ) {
				return;
			}

			// Cleared first, so copying twice is announced twice.
			status.textContent = '';
			window.setTimeout( function () {
				status.textContent = message;
			}, 30 );
		}
	}

	/*
	 * Rich copy (plain text + HTML) where the browser supports it, then
	 * plain text through the async API, then the older execCommand route
	 * (older browsers, or a page that isn't served over HTTPS).
	 */
	function copyToClipboard( summary ) {
		if ( window.isSecureContext && navigator.clipboard ) {
			if ( window.ClipboardItem && navigator.clipboard.write ) {
				try {
					var item = new window.ClipboardItem( {
						'text/plain': new Blob( [ summary.text ], { type: 'text/plain' } ),
						'text/html': new Blob( [ summary.html ], { type: 'text/html' } )
					} );

					return navigator.clipboard.write( [ item ] ).catch( function () {
						return plainCopy( summary.text );
					} );
				} catch ( e ) {
					return plainCopy( summary.text );
				}
			}

			return plainCopy( summary.text );
		}

		return legacyCopy( summary.text ) ? Promise.resolve() : Promise.reject();
	}

	function plainCopy( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return legacyCopy( text ) ? undefined : Promise.reject();
			} );
		}

		return legacyCopy( text ) ? Promise.resolve() : Promise.reject();
	}

	function legacyCopy( text ) {
		var field = document.createElement( 'textarea' );
		var active = document.activeElement;
		var ok = false;

		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;';
		document.body.appendChild( field );
		field.select();

		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}

		document.body.removeChild( field );

		if ( active && active.focus ) {
			active.focus();
		}

		return ok;
	}

	function selectForManualCopy( text, viewModal ) {
		var body = viewModal.querySelector( '.dak-modal-body' );
		var field = viewModal.querySelector( '.dak-appointment-copy-fallback' );

		if ( ! field ) {
			field = document.createElement( 'textarea' );
			field.className = 'dak-appointment-copy-fallback';
			field.setAttribute( 'readonly', '' );
			field.setAttribute( 'aria-label', copyStrings().copy || 'Copy details' );
			body.appendChild( field );
		}

		field.value = text;
		field.rows = Math.min( 16, text.split( '\n' ).length + 1 );
		field.focus();
		field.select();
	}

	function setText( id, value ) {
		var el = document.getElementById( id );

		if ( el ) {
			el.textContent = value || '—';
		}
	}

	/** Like setText(), but an empty value stays empty (no dash). */
	function setRaw( id, value ) {
		var el = document.getElementById( id );

		if ( el ) {
			el.textContent = value || '';
		}
	}

	/**
	 * Like setText(), but hides the whole label/value pair (its wrapping
	 * <div data-optional>) instead of showing a dash when there's no value.
	 */
	function setOptional( id, value ) {
		var el = document.getElementById( id );

		if ( ! el ) {
			return;
		}

		el.textContent = value || '';

		var wrapper = el.closest( '[data-optional]' );

		if ( wrapper ) {
			wrapper.hidden = ! value;
		}
	}

	function wireSave( modal ) {
		var saveButton = document.getElementById( 'dak-admin-appointment-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			if ( 'reschedule' === mode ) {
				submitReschedule( modal );
				return;
			}

			clearErrors();

			var doctorId = document.getElementById( 'dak-admin-appointment-doctor' ).value;

			if ( ! doctorId ) {
				var doctorError = document.querySelector( '.dak-field-error[data-field="doctor_id"]' );

				if ( doctorError ) {
					doctorError.textContent = 'Please select a doctor.';
				}

				return;
			}

			var isVideo = 'video' === document.getElementById( 'dak-admin-appointment-type' ).value;
			// A clinic visit always needs a clinic (and at least one service).
			if ( ! isVideo && ! selectedClinicId() ) {
				var noClinicNote = document.getElementById( 'dak-admin-appointment-no-clinic-note' );

				// The doctor has no clinic set up: the note already says so —
				// bring it into view instead of pointing at a hidden field.
				if ( noClinicNote && ! noClinicNote.classList.contains( 'dak-hidden' ) ) {
					noClinicNote.scrollIntoView( { block: 'center' } );
					return;
				}

				var clinicError = document.querySelector( '.dak-field-error[data-field="clinic_id"]' );

				if ( clinicError ) {
					clinicError.textContent = 'Please select a clinic.';
				}

				document.getElementById( 'dak-admin-appointment-clinic' ).focus();

				return;
			}

			if ( ! isVideo && ! document.getElementById( 'dak-admin-appointment-service' ).selectedOptions.length ) {
				var serviceError = document.querySelector( '.dak-field-error[data-field="service_ids"]' );

				if ( serviceError ) {
					serviceError.textContent = 'Please select at least one service.';
				}

				return;
			}

			saveButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_appointment_save' );
			formData.append( 'nonce', window.dakAdminAppointments.nonce );
			formData.append( 'appointment_id', document.getElementById( 'dak-admin-appointment-id' ).value );
			formData.append( 'doctor_id', doctorId );
			formData.append( 'type', document.getElementById( 'dak-admin-appointment-type' ).value );
			if ( ! isVideo ) {
				Array.prototype.forEach.call( document.getElementById( 'dak-admin-appointment-service' ).selectedOptions, function ( opt ) {
					formData.append( 'service_ids[]', opt.value );
				} );

				// Required for a clinic visit; the server checks it belongs to
				// this doctor.
				formData.append( 'clinic_id', selectedClinicId() );
			}
			formData.append( 'patient_id', document.getElementById( 'dak-admin-appointment-patient' ).value );
			formData.append( 'guest_name', document.getElementById( 'dak-admin-appointment-guest-name' ).value );
			formData.append( 'guest_email', document.getElementById( 'dak-admin-appointment-guest-email' ).value );
			formData.append( 'guest_phone', document.getElementById( 'dak-admin-appointment-guest-phone' ).value );
			formData.append( 'date', document.getElementById( 'dak-admin-appointment-date' ).value );
			formData.append( 'time', document.getElementById( 'dak-admin-appointment-time' ).value );
			formData.append( 'status', document.getElementById( 'dak-admin-appointment-status' ).value );
			formData.append( 'payment_status', document.getElementById( 'dak-admin-appointment-payment-status' ).value );
			formData.append( 'payment_mode', document.getElementById( 'dak-admin-appointment-payment-mode' ).value );
			formData.append( 'notes', document.getElementById( 'dak-admin-appointment-notes' ).value );

			fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					saveButton.disabled = false;

					if ( result.success ) {
						window.location.reload();
						return;
					}

					showGeneralError( errorsToMessage( result ) );
				} )
				.catch( function () {
					saveButton.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function wireDelete() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-appointment-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this appointment? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_appointment_delete' );
			formData.append( 'nonce', window.dakAdminAppointments.nonce );
			formData.append( 'appointment_id', trigger.getAttribute( 'data-appointment-id' ) );

			fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					window.alert( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					window.alert( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function wireMarkPaid() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-appointment-mark-paid]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Mark this appointment as paid?' ) ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_appointment_mark_paid' );
			formData.append( 'nonce', window.dakAdminAppointments.nonce );
			formData.append( 'appointment_id', trigger.getAttribute( 'data-appointment-id' ) );

			fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	function wirePayNow() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-appointment-pay-now]' );

			if ( ! trigger ) {
				return;
			}

			trigger.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_appointment_pay_now' );
			formData.append( 'nonce', window.dakAdminAppointments.nonce );
			formData.append( 'appointment_id', trigger.getAttribute( 'data-appointment-id' ) );

			fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success && result.data && result.data.payment_url ) {
						window.location.href = result.data.payment_url;
						return;
					}

					trigger.disabled = false;
					window.alert( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					trigger.disabled = false;
					window.alert( 'Something went wrong. Please try again.' );
				} );
		} );
	}

	/**
	 * Row checkboxes + "Select all" + the bulk actions bar (Mark paid / Send
	 * reminder / Clear) on the Appointments list. Selected IDs are read
	 * straight from the checked checkboxes when a bulk action runs — no
	 * separate state array to keep in sync. Both bulk actions just call the
	 * existing single-appointment AJAX endpoints once per selected row
	 * (there are only ever a handful selected at a time) rather than adding
	 * bulk-specific endpoints.
	 */
	function wireBulkActions() {
		// Every element is looked up at event time, not captured on load: the
		// live filter replaces the whole list (select-all, bulk bar and all)
		// with fresh HTML, which used to leave these handlers pointing at the
		// detached old nodes so bulk selection silently stopped working.
		function selectedCheckboxes() {
			return Array.prototype.slice.call( document.querySelectorAll( '.dak-appt-select' ) );
		}

		function checkedCheckboxes() {
			return selectedCheckboxes().filter( function ( box ) { return box.checked; } );
		}

		function refreshBulkBar() {
			var selectAll = document.getElementById( 'dak-appt-select-all' );
			var bulkBar = document.getElementById( 'dak-appt-bulk-actions' );
			var bulkCount = document.getElementById( 'dak-appt-bulk-count' );
			var checked = checkedCheckboxes();
			var all = selectedCheckboxes();

			if ( bulkBar ) {
				bulkBar.classList.toggle( 'dak-hidden', 0 === checked.length );
			}

			if ( bulkCount ) {
				bulkCount.textContent = checked.length + ' selected';
			}

			if ( selectAll ) {
				selectAll.checked = all.length > 0 && checked.length === all.length;
				selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
			}
		}

		document.addEventListener( 'change', function ( event ) {
			if ( event.target && 'dak-appt-select-all' === event.target.id ) {
				var checkAll = event.target.checked;

				selectedCheckboxes().forEach( function ( box ) {
					box.checked = checkAll;
				} );

				refreshBulkBar();
				return;
			}

			if ( event.target.classList && event.target.classList.contains( 'dak-appt-select' ) ) {
				refreshBulkBar();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-appt-bulk-clear]' ) ) {
				selectedCheckboxes().forEach( function ( box ) { box.checked = false; } );
				refreshBulkBar();
				return;
			}

			var markPaidTrigger = event.target.closest( '[data-appt-bulk-mark-paid]' );

			if ( markPaidTrigger ) {
				runBulkAction( markPaidTrigger, 'doctor_ak_admin_appointment_mark_paid', 'Mark the selected appointments as paid?' );
				return;
			}

			var reminderTrigger = event.target.closest( '[data-appt-bulk-send-reminder]' );

			if ( reminderTrigger ) {
				runBulkAction( reminderTrigger, 'doctor_ak_admin_appointment_send_reminder', 'Send a reminder for each selected appointment?' );
			}
		} );

		function runBulkAction( trigger, action, confirmMessage ) {
			var ids = checkedCheckboxes().map( function ( box ) { return box.getAttribute( 'data-appointment-id' ); } );

			if ( ! ids.length ) {
				return;
			}

			if ( ! window.confirm( confirmMessage ) ) {
				return;
			}

			trigger.disabled = true;

			Promise.all(
				ids.map( function ( appointmentId ) {
					var formData = new FormData();
					formData.append( 'action', action );
					formData.append( 'nonce', window.dakAdminAppointments.nonce );
					formData.append( 'appointment_id', appointmentId );

					return fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
						.then( function ( response ) { return response.json(); } )
						.catch( function () { return { success: false }; } );
				} )
			).then( function ( results ) {
				var failed = results.filter( function ( result ) { return ! result.success; } ).length;

				if ( failed > 0 ) {
					trigger.disabled = false;
					window.alert( failed + ' of ' + ids.length + ' appointments could not be updated.' );
					return;
				}

				window.location.reload();
			} );
		}
	}

	function errorsToMessage( result ) {
		if ( result.data && result.data.errors ) {
			return Object.keys( result.data.errors ).map( function ( field ) { return result.data.errors[ field ]; } ).join( ' ' );
		}

		return ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
	}

	function clearErrors() {
		document.querySelectorAll( '#dak-admin-appointment-modal .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		clearFieldError( 'time' );

		var generalError = document.getElementById( 'dak-admin-appointment-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-appointment-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );

			// It sits at the top of the scrolling body — bring it into view.
			var body = el.closest( '.dak-modal-body' );

			if ( body ) {
				body.scrollTop = 0;
			}
		}
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
