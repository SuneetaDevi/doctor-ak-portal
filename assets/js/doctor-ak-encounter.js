/**
 * Doctor AK Portal — Encounter detail screen (Problems / Prescription /
 * Bill / Close Encounter). Fetches the full view-model from
 * Encounter_Handler::handle_get_encounter() on load and after every
 * add/delete, re-rendering the three lists + bill total in place rather
 * than reloading the page.
 */
( function () {
	'use strict';

	var root;
	var encounterId;

	document.addEventListener( 'DOMContentLoaded', function () {
		root = document.getElementById( 'dak-encounter-root' );

		if ( ! root || ! window.dakEncounter ) {
			return;
		}

		encounterId = root.getAttribute( 'data-encounter-id' );

		wireAddForm( 'dak-encounter-add-problem-form', 'doctor_ak_add_encounter_problem', function ( formData ) {
			formData.append( 'description', document.getElementById( 'dak-encounter-problem-description' ).value );
			formData.append( 'notes', document.getElementById( 'dak-encounter-problem-notes' ).value );
		}, function () {
			document.getElementById( 'dak-encounter-problem-description' ).value = '';
			document.getElementById( 'dak-encounter-problem-notes' ).value = '';
		} );

		wireAddForm( 'dak-encounter-add-prescription-form', 'doctor_ak_add_encounter_prescription', function ( formData ) {
			formData.append( 'medicine_name', document.getElementById( 'dak-encounter-prescription-medicine-name' ).value );
			formData.append( 'dosage', document.getElementById( 'dak-encounter-prescription-dosage' ).value );
			formData.append( 'frequency', document.getElementById( 'dak-encounter-prescription-frequency' ).value );
			formData.append( 'duration', document.getElementById( 'dak-encounter-prescription-duration' ).value );
			formData.append( 'instructions', document.getElementById( 'dak-encounter-prescription-instructions' ).value );
		}, function () {
			document.getElementById( 'dak-encounter-prescription-medicine-name' ).value = '';
			document.getElementById( 'dak-encounter-prescription-dosage' ).value = '';
			document.getElementById( 'dak-encounter-prescription-frequency' ).value = '';
			document.getElementById( 'dak-encounter-prescription-duration' ).value = '';
			document.getElementById( 'dak-encounter-prescription-instructions' ).value = '';
		} );

		wireAddForm( 'dak-encounter-add-bill-item-form', 'doctor_ak_add_encounter_bill_item', function ( formData ) {
			var serviceSelect = document.getElementById( 'dak-encounter-bill-service' );
			formData.append( 'service_id', serviceSelect ? serviceSelect.value : '0' );
			formData.append( 'description', document.getElementById( 'dak-encounter-bill-description' ).value );
			formData.append( 'amount', document.getElementById( 'dak-encounter-bill-amount' ).value );
		}, function () {
			document.getElementById( 'dak-encounter-bill-service' ).value = '0';
			document.getElementById( 'dak-encounter-bill-description' ).value = '';
			document.getElementById( 'dak-encounter-bill-amount' ).value = '';
		} );

		wireUploadReportForm();
		wireProblemSuggestions();

		document.addEventListener( 'click', function ( event ) {
			var deleteTrigger = event.target.closest( '[data-encounter-delete]' );

			if ( deleteTrigger ) {
				var action = deleteTrigger.getAttribute( 'data-action' );
				var idField = deleteTrigger.getAttribute( 'data-id-field' );
				var id = deleteTrigger.getAttribute( 'data-id' );

				var formData = new FormData();
				formData.append( 'action', action );
				formData.append( 'nonce', window.dakEncounter.nonce );
				formData.append( 'encounter_id', encounterId );
				formData.append( idField, id );

				post( formData );
				return;
			}

			var closeTrigger = event.target.closest( '[data-encounter-close]' );

			if ( closeTrigger ) {
				if ( ! window.confirm( window.dakEncounter.confirmCloseMessage ) ) {
					return;
				}

				closeTrigger.disabled = true;

				var closeFormData = new FormData();
				closeFormData.append( 'action', 'doctor_ak_close_encounter' );
				closeFormData.append( 'nonce', window.dakEncounter.nonce );
				closeFormData.append( 'encounter_id', encounterId );

				fetch( window.dakEncounter.ajaxUrl, { method: 'POST', body: closeFormData, credentials: 'same-origin' } )
					.then( function ( response ) { return response.json(); } )
					.then( function ( result ) {
						if ( result.success ) {
							window.location.reload();
							return;
						}

						closeTrigger.disabled = false;
						showError( ( result.data && result.data.message ) || window.dakEncounter.genericError );
					} )
					.catch( function () {
						closeTrigger.disabled = false;
						showError( window.dakEncounter.genericError );
					} );
			}
		} );

		var serviceSelect = document.getElementById( 'dak-encounter-bill-service' );

		if ( serviceSelect ) {
			serviceSelect.addEventListener( 'change', fillBillFieldsFromService );
		}

		refresh();
	} );

	/**
	 * Picking one of the doctor's Services pre-fills the Description/Amount
	 * fields from that service's name/charge (still editable afterward,
	 * e.g. to adjust the amount) — "— Custom charge —" (value 0) leaves
	 * both fields as-is for a free-typed one-off charge.
	 */
	function fillBillFieldsFromService() {
		var select = document.getElementById( 'dak-encounter-bill-service' );
		var option = select.options[ select.selectedIndex ];

		if ( ! option || '0' === select.value ) {
			return;
		}

		document.getElementById( 'dak-encounter-bill-description' ).value = option.getAttribute( 'data-name' ) || '';
		document.getElementById( 'dak-encounter-bill-amount' ).value = option.getAttribute( 'data-charge' ) || '';
	}

	/**
	 * Uploads a report file — a plain multipart POST (not JSON-shaped like
	 * wireAddForm()'s other forms) since it carries an actual File. No
	 * separate "Upload" button — picking a file (via the dropzone's click-
	 * to-browse or an actual drag-and-drop) uploads it immediately.
	 */
	function wireUploadReportForm() {
		var fileInput = document.getElementById( 'dak-encounter-report-file' );
		var dropzone = fileInput ? fileInput.closest( '.dak-encounter-upload-dropzone' ) : null;

		if ( ! fileInput ) {
			return;
		}

		fileInput.addEventListener( 'change', function () {
			uploadReport( fileInput.files[ 0 ] );
		} );

		if ( ! dropzone ) {
			return;
		}

		[ 'dragenter', 'dragover' ].forEach( function ( eventName ) {
			dropzone.addEventListener( eventName, function ( event ) {
				event.preventDefault();
				dropzone.classList.add( 'is-dragover' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( eventName ) {
			dropzone.addEventListener( eventName, function ( event ) {
				event.preventDefault();
				dropzone.classList.remove( 'is-dragover' );
			} );
		} );

		dropzone.addEventListener( 'drop', function ( event ) {
			var file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[ 0 ] : null;
			uploadReport( file );
		} );
	}

	function uploadReport( file ) {
		if ( ! file ) {
			return;
		}

		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_upload_encounter_report' );
		formData.append( 'nonce', window.dakEncounter.nonce );
		formData.append( 'encounter_id', encounterId );
		formData.append( 'report', file );

		post( formData, function () {
			var fileInput = document.getElementById( 'dak-encounter-report-file' );

			if ( fileInput ) {
				fileInput.value = '';
			}
		} );
	}

	/**
	 * Clicking a common-problem chip fills the description field (doesn't
	 * submit — the doctor can still add notes before clicking "+ Add Problem").
	 */
	function wireProblemSuggestions() {
		var container = document.getElementById( 'dak-encounter-problem-suggestions' );
		var descriptionField = document.getElementById( 'dak-encounter-problem-description' );

		if ( ! container || ! descriptionField ) {
			return;
		}

		container.addEventListener( 'click', function ( event ) {
			var chip = event.target.closest( '[data-suggest-problem]' );

			if ( ! chip ) {
				return;
			}

			descriptionField.value = chip.getAttribute( 'data-suggest-problem' ) || '';
			descriptionField.focus();
		} );
	}

	function wireAddForm( formId, action, appendFields, resetFields ) {
		var form = document.getElementById( formId );

		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var formData = new FormData();
			formData.append( 'action', action );
			formData.append( 'nonce', window.dakEncounter.nonce );
			formData.append( 'encounter_id', encounterId );
			appendFields( formData );

			post( formData, resetFields );
		} );
	}

	function post( formData, onSuccess ) {
		hideError();

		fetch( window.dakEncounter.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( ! result.success ) {
					showError( ( result.data && result.data.message ) || window.dakEncounter.genericError );
					return;
				}

				render( result.data );

				if ( onSuccess ) {
					onSuccess();
				}
			} )
			.catch( function () {
				showError( window.dakEncounter.genericError );
			} );
	}

	function refresh() {
		var formData = new FormData();
		formData.append( 'action', 'doctor_ak_get_encounter' );
		formData.append( 'nonce', window.dakEncounter.nonce );
		formData.append( 'encounter_id', encounterId );

		post( formData );
	}

	function render( data ) {
		var isOpen = 'open' === data.encounter.status;

		setText( 'dak-encounter-patient-name', data.appointment.patient_name || '' );

		var avatar = document.getElementById( 'dak-encounter-avatar' );

		if ( avatar && data.appointment.patient_avatar_url ) {
			avatar.innerHTML = '<img src="' + data.appointment.patient_avatar_url + '" alt="">';
		}

		var statusEl = document.getElementById( 'dak-encounter-status' );

		if ( statusEl ) {
			statusEl.textContent = isOpen ? 'Open' : 'Closed';
			statusEl.classList.toggle( 'dak-status-pill-is-active', isOpen );
			statusEl.classList.toggle( 'dak-status-pill-is-disabled', ! isOpen );
		}

		var metaParts = [];

		if ( data.appointment.doctor_name ) {
			metaParts.push( 'Dr. ' + data.appointment.doctor_name );
		}

		if ( data.encounter.clinic_name ) {
			metaParts.push( data.encounter.clinic_name );
		} else if ( data.appointment.type_label ) {
			// No physical clinic on this encounter means it's a video
			// consultation — say so instead of just omitting the clinic.
			metaParts.push( data.appointment.type_label );
		}

		if ( data.appointment.datetime_label ) {
			metaParts.push( data.appointment.datetime_label );
		}

		setText( 'dak-encounter-meta', metaParts.join( ' · ' ) );

		renderProblems( data.problems, isOpen );
		renderPrescriptions( data.prescriptions, isOpen );
		renderMedicineSuggestions( data.medicines );
		renderServiceOptions( data.services );
		renderBillItems( data.bill_items, data.bill_total, isOpen );
		renderReports( data.reports, isOpen );

		setText( 'dak-encounter-problems-count', String( data.problems.length ) );
		setText( 'dak-encounter-prescriptions-count', String( data.prescriptions.length ) );
		setText( 'dak-encounter-bill-count', String( data.bill_items.length ) );
		setText( 'dak-encounter-reports-count', String( data.reports.length ) );

		setText( 'dak-encounter-summary-problems', String( data.problems.length ) );
		setText( 'dak-encounter-summary-medicines', String( data.prescriptions.length ) );
		setText( 'dak-encounter-summary-charges', String( data.bill_items.length ) );
		setText( 'dak-encounter-summary-reports', String( data.reports.length ) );
		setText( 'dak-encounter-summary-amount', 'PKR ' + Math.round( data.bill_total ) );

		var prescriptionLink = document.getElementById( 'dak-encounter-download-prescription' );

		if ( prescriptionLink ) {
			prescriptionLink.href = data.prescription_pdf_url;
		}

		var billLink = document.getElementById( 'dak-encounter-download-bill' );

		if ( billLink ) {
			billLink.href = data.bill_pdf_url;
		}

		var closeButton = document.getElementById( 'dak-encounter-close' );

		if ( closeButton ) {
			closeButton.classList.toggle( 'dak-hidden', ! isOpen );
		}

		toggleFormsVisible( isOpen );

		var closeHint = document.getElementById( 'dak-encounter-close-hint' );

		if ( closeHint ) {
			closeHint.classList.toggle( 'dak-hidden', ! isOpen );
		}

		var closedHint = document.getElementById( 'dak-encounter-closed-hint' );

		if ( closedHint ) {
			closedHint.classList.toggle( 'dak-hidden', isOpen );
		}
	}

	function toggleFormsVisible( isOpen ) {
		[ 'dak-encounter-add-problem-form', 'dak-encounter-add-prescription-form', 'dak-encounter-add-bill-item-form', 'dak-encounter-upload-report-form' ].forEach( function ( id ) {
			var form = document.getElementById( id );

			if ( form ) {
				form.classList.toggle( 'dak-hidden', ! isOpen );
			}
		} );
	}

	function renderProblems( problems, isOpen ) {
		var container = document.getElementById( 'dak-encounter-problems-list' );

		if ( ! container ) {
			return;
		}

		if ( ! problems.length ) {
			container.innerHTML = '<p class="dak-empty-state">No problems recorded yet.</p>';
			return;
		}

		container.innerHTML = '';

		problems.forEach( function ( problem ) {
			var row = document.createElement( 'div' );
			row.className = 'dak-admin-record-row';

			var main = document.createElement( 'div' );
			main.className = 'dak-admin-record-row-main';

			var info = document.createElement( 'span' );
			info.className = 'dak-admin-record-row-info';
			info.innerHTML = '<strong></strong><span class="dak-admin-record-row-id"></span>';
			info.querySelector( 'strong' ).textContent = problem.description;
			info.querySelector( '.dak-admin-record-row-id' ).textContent = problem.notes;
			main.appendChild( info );

			if ( isOpen ) {
				main.appendChild( deleteButton( 'doctor_ak_delete_encounter_problem', 'problem_id', problem.id ) );
			}

			row.appendChild( main );
			container.appendChild( row );
		} );
	}

	function renderPrescriptions( prescriptions, isOpen ) {
		var container = document.getElementById( 'dak-encounter-prescriptions-list' );

		if ( ! container ) {
			return;
		}

		if ( ! prescriptions.length ) {
			container.innerHTML = '<p class="dak-empty-state">No medicines prescribed yet.</p>';
			return;
		}

		container.innerHTML = '';

		prescriptions.forEach( function ( prescription ) {
			var row = document.createElement( 'div' );
			row.className = 'dak-admin-record-row';

			var main = document.createElement( 'div' );
			main.className = 'dak-admin-record-row-main';

			// Dosage/Frequency/Duration are always shown, labelled, with a
			// "—" placeholder for whichever ones weren't filled in — so it's
			// obvious at a glance what's missing instead of the line just
			// disappearing when a medicine was added with only its name.
			var metaParts = [
				'Dosage: ' + ( prescription.dosage || '—' ),
				'Frequency: ' + ( prescription.frequency || '—' ),
				'Duration: ' + ( prescription.duration || '—' ),
			];

			if ( prescription.instructions ) {
				metaParts.push( prescription.instructions );
			}

			var info = document.createElement( 'span' );
			info.className = 'dak-admin-record-row-info';
			info.innerHTML = '<strong></strong><span class="dak-admin-record-row-id"></span>';
			info.querySelector( 'strong' ).textContent = prescription.medicine_name;
			info.querySelector( '.dak-admin-record-row-id' ).textContent = metaParts.join( ' · ' );
			main.appendChild( info );

			if ( isOpen ) {
				main.appendChild( deleteButton( 'doctor_ak_delete_encounter_prescription', 'prescription_id', prescription.id ) );
			}

			row.appendChild( main );
			container.appendChild( row );
		} );
	}

	function renderBillItems( billItems, billTotal, isOpen ) {
		var container = document.getElementById( 'dak-encounter-bill-list' );

		if ( container ) {
			if ( ! billItems.length ) {
				container.innerHTML = '<p class="dak-empty-state">No extra charges added.</p>';
			} else {
				container.innerHTML = '';

				billItems.forEach( function ( item ) {
					var row = document.createElement( 'div' );
					row.className = 'dak-admin-record-row';

					var main = document.createElement( 'div' );
					main.className = 'dak-admin-record-row-main';

					var info = document.createElement( 'span' );
					info.className = 'dak-admin-record-row-info';
					info.innerHTML = '<strong></strong>';
					info.querySelector( 'strong' ).textContent = item.description;
					main.appendChild( info );

					var amount = document.createElement( 'span' );
					amount.className = 'dak-admin-record-row-amount';
					amount.textContent = 'PKR ' + Math.round( item.amount );
					main.appendChild( amount );

					if ( isOpen ) {
						main.appendChild( deleteButton( 'doctor_ak_delete_encounter_bill_item', 'item_id', item.id ) );
					}

					row.appendChild( main );
					container.appendChild( row );
				} );
			}
		}

		setText( 'dak-encounter-bill-total', 'PKR ' + Math.round( billTotal ) );
	}

	function renderReports( reports, isOpen ) {
		var container = document.getElementById( 'dak-encounter-reports-list' );

		if ( ! container ) {
			return;
		}

		if ( ! reports.length ) {
			container.innerHTML = '<p class="dak-empty-state">No reports uploaded yet.</p>';
			return;
		}

		container.innerHTML = '';

		reports.forEach( function ( report ) {
			var row = document.createElement( 'div' );
			row.className = 'dak-admin-record-row';

			var main = document.createElement( 'div' );
			main.className = 'dak-admin-record-row-main';

			var link = document.createElement( 'a' );
			link.className = 'dak-admin-record-row-info';
			link.href = report.url;
			link.target = '_blank';
			link.rel = 'noopener';
			link.innerHTML = '<strong></strong>';
			link.querySelector( 'strong' ).textContent = report.file_name;
			main.appendChild( link );

			if ( isOpen ) {
				main.appendChild( deleteButton( 'doctor_ak_delete_encounter_report', 'report_id', report.id ) );
			}

			row.appendChild( main );
			container.appendChild( row );
		} );
	}

	function renderServiceOptions( services ) {
		var select = document.getElementById( 'dak-encounter-bill-service' );

		if ( ! select ) {
			return;
		}

		var currentValue = select.value;
		select.innerHTML = '<option value="0">— Custom charge —</option>';

		services.forEach( function ( service ) {
			var option = document.createElement( 'option' );
			option.value = service.id;
			option.setAttribute( 'data-name', service.name );
			option.setAttribute( 'data-charge', service.charge );
			option.textContent = service.name + ' (PKR ' + Math.round( service.charge ) + ')';
			select.appendChild( option );
		} );

		select.value = currentValue || '0';
	}

	/**
	 * Fills the medicine name field's <datalist> with this doctor's own
	 * medicines plus the shared common list — the browser filters these
	 * as-you-type, so typing "amox" surfaces "Amoxicillin" without any
	 * per-keystroke request. Saving a name that isn't in this list yet
	 * still works (Encounter_Handler auto-creates it), so it appears here
	 * on the next refresh.
	 */
	function renderMedicineSuggestions( medicines ) {
		var datalist = document.getElementById( 'dak-encounter-medicine-suggestions' );

		if ( ! datalist ) {
			return;
		}

		datalist.innerHTML = '';

		medicines.forEach( function ( medicine ) {
			var option = document.createElement( 'option' );
			option.value = medicine.name;
			datalist.appendChild( option );
		} );
	}

	function deleteButton( action, idField, id ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'dak-icon-button dak-icon-button-danger';
		button.setAttribute( 'data-encounter-delete', '' );
		button.setAttribute( 'data-action', action );
		button.setAttribute( 'data-id-field', idField );
		button.setAttribute( 'data-id', id );
		button.setAttribute( 'aria-label', 'Delete' );
		button.textContent = '×';
		return button;
	}

	function setText( id, text ) {
		var el = document.getElementById( id );

		if ( el ) {
			el.textContent = text;
		}
	}

	function showError( message ) {
		var el = document.getElementById( 'dak-encounter-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}

	function hideError() {
		var el = document.getElementById( 'dak-encounter-error' );

		if ( el ) {
			el.classList.add( 'dak-hidden' );
		}
	}
} )();
