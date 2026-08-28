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
	// The time slot the appointment currently open in the modal already
	// occupies (edit mode only) — its own slot shouldn't read as "booked"
	// just because it's already booked by itself.
	var currentEditTime = '';

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

		wireModalClose( modal, 'dak-admin-appointment-modal-close' );
		wireModalClose( viewModal, 'dak-admin-appointment-view-modal-close' );
		wireDoctorTypeChange();
		wireDateTimePicker();
		wirePatientToggle();
		wireAdd( modal );
		wireEdit( modal );
		wireView( viewModal );
		wireSave( modal );
		wireDelete();

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
		updateServiceOptions( '', 'clinic', [] );
	}

	function wireAdd( modal ) {
		var addButton = document.getElementById( 'dak-admin-appointment-add' );

		if ( ! addButton ) {
			return;
		}

		addButton.addEventListener( 'click', function () {
			clearErrors();
			resetModalFields();
			setModalTitle( 'Add Appointment' );
			openModal( modal );
		} );
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
	}

	function closeModal( modal ) {
		modal.classList.remove( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'dak-modal-open' );
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

		select.innerHTML = '';

		var services = servicesByDoctorAndType[ doctorId ] ? servicesByDoctorAndType[ doctorId ][ type ] : null;

		if ( services && services.length ) {
			services.forEach( function ( service ) {
				var option = document.createElement( 'option' );
				option.value = service.id;
				option.textContent = service.name + ( service.charge > 0 ? ' (PKR ' + service.charge + ')' : '' );
				option.selected = -1 !== keepIds.indexOf( String( service.id ) );
				option.setAttribute( 'data-charge', service.charge || 0 );
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
			updateServiceOptions( doctorSelect.value, typeSelect.value, [] );
		}

		doctorSelect.addEventListener( 'change', refresh );
		typeSelect.addEventListener( 'change', refresh );

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

		function refresh() {
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

			fetchSlots( doctorSelect.value, typeSelect.value, dateField.value );
		}

		doctorSelect.addEventListener( 'change', refresh );
		typeSelect.addEventListener( 'change', refresh );
		dateField.addEventListener( 'change', refresh );
	}

	function resetSlots() {
		document.getElementById( 'dak-admin-appointment-slots-groups' ).innerHTML = '';
		hide( document.getElementById( 'dak-admin-appointment-no-slots' ) );
		show( document.getElementById( 'dak-admin-appointment-slots-hint' ) );
	}

	function fetchSlots( doctorId, type, date ) {
		var groups = document.getElementById( 'dak-admin-appointment-slots-groups' );
		var noSlots = document.getElementById( 'dak-admin-appointment-no-slots' );
		var hint = document.getElementById( 'dak-admin-appointment-slots-hint' );

		if ( ! groups ) {
			return;
		}

		if ( ! doctorId || ! date ) {
			groups.innerHTML = '';
			hide( noSlots );
			show( hint );
			return;
		}

		hide( hint );
		hide( noSlots );
		groups.innerHTML = '<p>' + 'Loading times…' + '</p>';

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_available_slots' );
		formData.append( 'nonce', window.dakAdminAppointments.slotsNonce );
		formData.append( 'doctor_id', doctorId );
		formData.append( 'type', type );
		formData.append( 'date', date );

		fetch( window.dakAdminAppointments.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				var slots = ( result.success && result.data && result.data.slots ) ? result.data.slots : [];
				renderSlotGrid( slots );
			} )
			.catch( function () {
				groups.innerHTML = '';
				show( noSlots );
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

	function renderSlotGrid( slots ) {
		var groupsEl = document.getElementById( 'dak-admin-appointment-slots-groups' );
		var noSlots = document.getElementById( 'dak-admin-appointment-no-slots' );
		var timeField = document.getElementById( 'dak-admin-appointment-time' );

		groupsEl.innerHTML = '';

		// The appointment being edited already occupies its own slot — the
		// server reports it 'booked' (by this same appointment), but that
		// shouldn't stop the admin from keeping it selected.
		if ( currentEditTime ) {
			slots = slots.map( function ( slot ) {
				if ( slot.time === currentEditTime && 'available' !== slot.status ) {
					return { time: slot.time, status: 'available', is_instant: false, surcharge: 0 };
				}
				return slot;
			} );
		}

		if ( ! slots.length ) {
			show( noSlots );
			return;
		}

		hide( noSlots );

		var gridEl = document.createElement( 'div' );
		gridEl.className = 'dak-booking-slots-grid';

		slots.forEach( function ( slot ) {
			var card = document.createElement( 'button' );
			card.type = 'button';
			card.className = 'dak-booking-slot-card is-' + slot.status;

			var timeLabel = document.createElement( 'span' );
			timeLabel.className = 'dak-booking-slot-time';
			timeLabel.textContent = formatTimeLabel( slot.time );
			card.appendChild( timeLabel );

			if ( slot.time === timeField.value ) {
				card.classList.add( 'is-selected' );
			}

			if ( 'available' === slot.status ) {
				card.addEventListener( 'click', function () {
					selectSlot( slot.time, card );
				} );
			} else {
				card.disabled = true;
			}

			gridEl.appendChild( card );
		} );

		groupsEl.appendChild( gridEl );
	}

	function selectSlot( time, card ) {
		document.getElementById( 'dak-admin-appointment-time' ).value = time;
		clearFieldError( 'time' );

		document.querySelectorAll( '#dak-admin-appointment-slots-groups .dak-booking-slot-card' ).forEach( function ( el ) {
			el.classList.remove( 'is-selected' );
		} );
		card.classList.add( 'is-selected' );
	}

	function clearFieldError( field ) {
		var el = document.querySelector( '.dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = '';
		}
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

			updateServiceOptions( trigger.getAttribute( 'data-doctor-id' ) || '', trigger.getAttribute( 'data-type' ) || 'clinic', editServiceIds );

			fetchSlots( trigger.getAttribute( 'data-doctor-id' ) || '', trigger.getAttribute( 'data-type' ) || 'clinic', dateField.value );

			openModal( modal );
		} );
	}

	function wireView( viewModal ) {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-appointment-view]' );

			if ( ! trigger ) {
				return;
			}

			setText( 'dak-admin-appointment-view-patient', trigger.getAttribute( 'data-patient-name' ) );
			setText( 'dak-admin-appointment-view-doctor', 'Dr. ' + ( trigger.getAttribute( 'data-doctor-name' ) || '' ) );
			setText( 'dak-admin-appointment-view-type', trigger.getAttribute( 'data-type-label' ) );
			setText( 'dak-admin-appointment-view-service', trigger.getAttribute( 'data-service-name' ) );
			setText( 'dak-admin-appointment-view-datetime', trigger.getAttribute( 'data-datetime-label' ) || '' );
			setText( 'dak-admin-appointment-view-charge', trigger.getAttribute( 'data-charge' ) );
			setText( 'dak-admin-appointment-view-payment-mode', trigger.getAttribute( 'data-payment-mode' ) );
			setText( 'dak-admin-appointment-view-status', trigger.getAttribute( 'data-status-label' ) );
			setText( 'dak-admin-appointment-view-notes', trigger.getAttribute( 'data-notes' ) );

			var printLink = document.getElementById( 'dak-admin-appointment-view-print' );

			if ( printLink ) {
				printLink.href = trigger.getAttribute( 'data-print-url' ) || '#';
			}

			openModal( viewModal );
		} );
	}

	function setText( id, value ) {
		var el = document.getElementById( id );

		if ( el ) {
			el.textContent = value || '—';
		}
	}

	function wireSave( modal ) {
		var saveButton = document.getElementById( 'dak-admin-appointment-save' );

		if ( ! saveButton ) {
			return;
		}

		saveButton.addEventListener( 'click', function () {
			clearErrors();

			var doctorId = document.getElementById( 'dak-admin-appointment-doctor' ).value;

			if ( ! doctorId ) {
				var doctorError = document.querySelector( '.dak-field-error[data-field="doctor_id"]' );

				if ( doctorError ) {
					doctorError.textContent = 'Please select a doctor.';
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
			Array.prototype.forEach.call( document.getElementById( 'dak-admin-appointment-service' ).selectedOptions, function ( opt ) {
				formData.append( 'service_ids[]', opt.value );
			} );
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
		var selectAll = document.getElementById( 'dak-appt-select-all' );
		var bulkBar = document.getElementById( 'dak-appt-bulk-actions' );
		var bulkCount = document.getElementById( 'dak-appt-bulk-count' );

		if ( ! selectAll || ! bulkBar ) {
			return;
		}

		function selectedCheckboxes() {
			return Array.prototype.slice.call( document.querySelectorAll( '.dak-appt-select' ) );
		}

		function checkedCheckboxes() {
			return selectedCheckboxes().filter( function ( box ) { return box.checked; } );
		}

		function refreshBulkBar() {
			var checked = checkedCheckboxes();

			bulkBar.classList.toggle( 'dak-hidden', 0 === checked.length );
			bulkCount.textContent = checked.length + ' selected';

			var all = selectedCheckboxes();
			selectAll.checked = all.length > 0 && checked.length === all.length;
			selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
		}

		selectAll.addEventListener( 'change', function () {
			selectedCheckboxes().forEach( function ( box ) {
				box.checked = selectAll.checked;
			} );

			refreshBulkBar();
		} );

		document.addEventListener( 'change', function ( event ) {
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
