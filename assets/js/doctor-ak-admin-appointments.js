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

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.getElementById( 'dak-admin-appointment-modal' );
		var viewModal = document.getElementById( 'dak-admin-appointment-view-modal' );
		var refundModal = document.getElementById( 'dak-admin-process-refund-modal' );

		if ( ! modal || ! viewModal || ! window.dakAdminAppointments ) {
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
		wirePatientToggle();
		wireAdd( modal );
		wireEdit( modal );
		wireView( viewModal );
		wireSave( modal );
		wireDelete();
		wireMarkPaid();

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

	function resetModalFields() {
		document.getElementById( 'dak-admin-appointment-id' ).value = '0';
		document.getElementById( 'dak-admin-appointment-doctor' ).value = '';
		document.getElementById( 'dak-admin-appointment-type' ).value = 'clinic';
		document.getElementById( 'dak-admin-appointment-patient' ).value = '';
		document.getElementById( 'dak-admin-appointment-guest-name' ).value = '';
		document.getElementById( 'dak-admin-appointment-guest-email' ).value = '';
		document.getElementById( 'dak-admin-appointment-guest-phone' ).value = '';
		document.getElementById( 'dak-admin-appointment-date' ).value = '';
		document.getElementById( 'dak-admin-appointment-time' ).value = '';
		document.getElementById( 'dak-admin-appointment-status' ).value = 'confirmed';
		document.getElementById( 'dak-admin-appointment-payment-status' ).value = 'pending';
		document.getElementById( 'dak-admin-appointment-payment-mode' ).value = 'manual';
		document.getElementById( 'dak-admin-appointment-notes' ).value = '';
		show( document.getElementById( 'dak-admin-appointment-guest-fields' ) );
		updateServiceOptions( '', 'clinic', 0 );
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
	 * Repopulates the Service <select> for the given doctor + type, keeping
	 * `keepServiceId` selected if it's still in the list (used when editing).
	 *
	 * @param {string} doctorId     Doctor user ID (may be empty).
	 * @param {string} type         'clinic' or 'video'.
	 * @param {string|number} keepServiceId Service ID to reselect if present.
	 */
	function updateServiceOptions( doctorId, type, keepServiceId ) {
		var select = document.getElementById( 'dak-admin-appointment-service' );

		if ( ! select ) {
			return;
		}

		select.innerHTML = '<option value="">No service / free booking</option>';

		var services = servicesByDoctorAndType[ doctorId ] ? servicesByDoctorAndType[ doctorId ][ type ] : null;

		if ( services && services.length ) {
			services.forEach( function ( service ) {
				var option = document.createElement( 'option' );
				option.value = service.id;
				option.textContent = service.name + ( service.charge > 0 ? ' (PKR ' + service.charge + ')' : '' );
				select.appendChild( option );
			} );
		}

		if ( keepServiceId ) {
			select.value = String( keepServiceId );
		}
	}

	function wireDoctorTypeChange() {
		var doctorSelect = document.getElementById( 'dak-admin-appointment-doctor' );
		var typeSelect = document.getElementById( 'dak-admin-appointment-type' );

		if ( ! doctorSelect || ! typeSelect ) {
			return;
		}

		function refresh() {
			updateServiceOptions( doctorSelect.value, typeSelect.value, 0 );
		}

		doctorSelect.addEventListener( 'change', refresh );
		typeSelect.addEventListener( 'change', refresh );
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
			document.getElementById( 'dak-admin-appointment-type' ).value = trigger.getAttribute( 'data-type' ) || 'clinic';
			document.getElementById( 'dak-admin-appointment-patient' ).value = trigger.getAttribute( 'data-patient-id' ) && '0' !== trigger.getAttribute( 'data-patient-id' ) ? trigger.getAttribute( 'data-patient-id' ) : '';
			document.getElementById( 'dak-admin-appointment-guest-name' ).value = trigger.getAttribute( 'data-guest-name' ) || '';
			document.getElementById( 'dak-admin-appointment-guest-email' ).value = trigger.getAttribute( 'data-guest-email' ) || '';
			document.getElementById( 'dak-admin-appointment-guest-phone' ).value = trigger.getAttribute( 'data-guest-phone' ) || '';
			document.getElementById( 'dak-admin-appointment-date' ).value = trigger.getAttribute( 'data-date' ) || '';
			document.getElementById( 'dak-admin-appointment-time' ).value = trigger.getAttribute( 'data-time' ) || '';
			document.getElementById( 'dak-admin-appointment-status' ).value = trigger.getAttribute( 'data-status' ) || 'confirmed';
			document.getElementById( 'dak-admin-appointment-payment-status' ).value = trigger.getAttribute( 'data-payment-status' ) || 'pending';
			document.getElementById( 'dak-admin-appointment-payment-mode' ).value = trigger.getAttribute( 'data-payment-mode' ) || 'manual';
			document.getElementById( 'dak-admin-appointment-notes' ).value = trigger.getAttribute( 'data-notes' ) || '';

			document.getElementById( 'dak-admin-appointment-guest-fields' ).classList.toggle(
				'dak-hidden',
				'' !== document.getElementById( 'dak-admin-appointment-patient' ).value
			);

			updateServiceOptions( trigger.getAttribute( 'data-doctor-id' ) || '', trigger.getAttribute( 'data-type' ) || 'clinic', trigger.getAttribute( 'data-service-id' ) || 0 );

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
			setText( 'dak-admin-appointment-view-datetime', ( trigger.getAttribute( 'data-date' ) || '' ) + ' ' + ( trigger.getAttribute( 'data-time' ) || '' ) );
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
			formData.append( 'service_id', document.getElementById( 'dak-admin-appointment-service' ).value );
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
} )();
