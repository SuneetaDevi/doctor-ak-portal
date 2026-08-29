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
			formData.append( 'problem_id', document.getElementById( 'dak-encounter-problem-id' ).value );
			formData.append( 'description', document.getElementById( 'dak-encounter-problem-description' ).value );
			formData.append( 'notes', document.getElementById( 'dak-encounter-problem-notes' ).value );
		}, function () {
			resetProblemForm();
		} );

		wireAddForm( 'dak-encounter-add-prescription-form', 'doctor_ak_add_encounter_prescription', function ( formData ) {
			formData.append( 'prescription_id', document.getElementById( 'dak-encounter-prescription-id' ).value );
			formData.append( 'medicine_name', document.getElementById( 'dak-encounter-prescription-medicine-name' ).value );
			formData.append( 'dosage', document.getElementById( 'dak-encounter-prescription-dosage' ).value );
			formData.append( 'frequency', document.getElementById( 'dak-encounter-prescription-frequency' ).value );
			formData.append( 'duration', document.getElementById( 'dak-encounter-prescription-duration' ).value );
			formData.append( 'instructions', document.getElementById( 'dak-encounter-prescription-instructions' ).value );
		}, function () {
			resetPrescriptionForm();
		} );

		wireEditCancelButtons();

		wireAddForm( 'dak-encounter-add-bill-item-form', 'doctor_ak_add_encounter_bill_item', function ( formData ) {
			var serviceSelect = document.getElementById( 'dak-encounter-bill-service' );
			formData.append( 'service_id', serviceSelect ? serviceSelect.value : '0' );
			formData.append( 'description', document.getElementById( 'dak-encounter-bill-description' ).value );
			formData.append( 'amount', document.getElementById( 'dak-encounter-bill-amount' ).value );
			formData.append( 'discount_percent', document.getElementById( 'dak-encounter-bill-discount' ).value || '0' );
		}, function () {
			document.getElementById( 'dak-encounter-bill-service' ).value = '0';
			document.getElementById( 'dak-encounter-bill-description' ).value = '';
			document.getElementById( 'dak-encounter-bill-amount' ).value = '';
			document.getElementById( 'dak-encounter-bill-discount' ).value = '';
		} );

		wireUploadReportForm();
		wireProblemSuggestions();

		document.addEventListener( 'click', function ( event ) {
			var editTrigger = event.target.closest( '[data-encounter-edit]' );

			if ( editTrigger ) {
				var row;

				try {
					row = JSON.parse( editTrigger.getAttribute( 'data-row' ) || '{}' );
				} catch ( e ) {
					row = {};
				}

				enterEditMode( editTrigger.getAttribute( 'data-edit-type' ), row );
				return;
			}

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

				// Deleting the row currently open in the edit form (e.g. via
				// its own delete button while mid-edit) resets that form back
				// to "add" mode instead of leaving it pointed at an id that
				// no longer exists.
				post( formData, function () {
					if ( 'problem_id' === idField && document.getElementById( 'dak-encounter-problem-id' ).value === id ) {
						resetProblemForm();
					} else if ( 'prescription_id' === idField && document.getElementById( 'dak-encounter-prescription-id' ).value === id ) {
						resetPrescriptionForm();
					}
				} );
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

	/**
	 * Populates the Problem/Prescription add-form with an existing row's
	 * values and switches it into "update" mode (id field set, submit
	 * button relabeled, Cancel button shown) — triggered by that row's
	 * pencil button (see editButton()). Submitting from here still posts to
	 * the same doctor_ak_add_encounter_problem/_prescription action; the
	 * server updates in place instead of creating a new row whenever a
	 * non-zero id is present (see Encounter_Handler::handle_add_problem()/
	 * handle_add_prescription()).
	 *
	 * @param {string} type 'problem' or 'prescription'.
	 * @param {Object} row  The clicked row's data (id + its own fields).
	 */
	function enterEditMode( type, row ) {
		if ( 'problem' === type ) {
			document.getElementById( 'dak-encounter-problem-id' ).value = row.id;
			document.getElementById( 'dak-encounter-problem-description' ).value = row.description || '';
			document.getElementById( 'dak-encounter-problem-notes' ).value = row.notes || '';
			setEditState( 'dak-encounter-problem-submit', 'dak-encounter-problem-cancel', 'Update Problem' );
			document.getElementById( 'dak-encounter-problem-description' ).scrollIntoView( { behavior: 'smooth', block: 'center' } );
			document.getElementById( 'dak-encounter-problem-description' ).focus();
			return;
		}

		if ( 'prescription' === type ) {
			document.getElementById( 'dak-encounter-prescription-id' ).value = row.id;
			document.getElementById( 'dak-encounter-prescription-medicine-name' ).value = row.medicine_name || '';
			document.getElementById( 'dak-encounter-prescription-dosage' ).value = row.dosage || '';
			document.getElementById( 'dak-encounter-prescription-frequency' ).value = row.frequency || '';
			document.getElementById( 'dak-encounter-prescription-duration' ).value = row.duration || '';
			document.getElementById( 'dak-encounter-prescription-instructions' ).value = row.instructions || '';
			setEditState( 'dak-encounter-prescription-submit', 'dak-encounter-prescription-cancel', 'Update Medicine' );
			document.getElementById( 'dak-encounter-prescription-medicine-name' ).scrollIntoView( { behavior: 'smooth', block: 'center' } );
			document.getElementById( 'dak-encounter-prescription-medicine-name' ).focus();
		}
	}

	function setEditState( submitId, cancelId, label ) {
		var submitButton = document.getElementById( submitId );
		var cancelButton = document.getElementById( cancelId );

		if ( submitButton ) {
			submitButton.textContent = label;
		}

		if ( cancelButton ) {
			cancelButton.classList.remove( 'dak-hidden' );
		}
	}

	function resetProblemForm() {
		document.getElementById( 'dak-encounter-problem-id' ).value = '0';
		document.getElementById( 'dak-encounter-problem-description' ).value = '';
		document.getElementById( 'dak-encounter-problem-notes' ).value = '';
		setText( 'dak-encounter-problem-submit', '+ Add Problem' );
		hide( document.getElementById( 'dak-encounter-problem-cancel' ) );
	}

	function resetPrescriptionForm() {
		document.getElementById( 'dak-encounter-prescription-id' ).value = '0';
		document.getElementById( 'dak-encounter-prescription-medicine-name' ).value = '';
		document.getElementById( 'dak-encounter-prescription-dosage' ).value = '';
		document.getElementById( 'dak-encounter-prescription-frequency' ).value = '';
		document.getElementById( 'dak-encounter-prescription-duration' ).value = '';
		document.getElementById( 'dak-encounter-prescription-instructions' ).value = '';
		setText( 'dak-encounter-prescription-submit', '+ Add Medicine' );
		hide( document.getElementById( 'dak-encounter-prescription-cancel' ) );
	}

	function wireEditCancelButtons() {
		var problemCancel = document.getElementById( 'dak-encounter-problem-cancel' );

		if ( problemCancel ) {
			problemCancel.addEventListener( 'click', resetProblemForm );
		}

		var prescriptionCancel = document.getElementById( 'dak-encounter-prescription-cancel' );

		if ( prescriptionCancel ) {
			prescriptionCancel.addEventListener( 'click', resetPrescriptionForm );
		}
	}

	function hide( el ) {
		if ( el ) {
			el.classList.add( 'dak-hidden' );
		}
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

		// Problems/Prescriptions/Bill/Reports stay fully editable even once
		// the encounter is closed (fixing a typo, adding a missed charge,
		// attaching a late-arriving report, etc.) — only "Close encounter"
		// itself, gated below, is a one-way action. A closed encounter's
		// bill edits also re-sync the revenue ledger server-side (see
		// Encounter_Handler::handle_add_bill_item()/handle_delete_bill_item()).
		renderProblems( data.problems, true );
		renderPrescriptions( data.prescriptions, true );
		renderMedicineSuggestions( data.medicines );
		renderServiceOptions( data.services );
		renderBillItems( data.bill_items, data.bill_total, true );
		renderReports( data.reports, true );

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

		toggleFormsVisible( true );

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
				main.appendChild( editButton( 'problem', problem ) );
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
				main.appendChild( editButton( 'prescription', prescription ) );
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

					if ( item.discount_percent > 0 ) {
						var discountNote = document.createElement( 'span' );
						discountNote.className = 'dak-admin-record-row-id';
						discountNote.textContent = item.discount_percent + '% off — was PKR ' + Math.round( item.original_amount );
						info.appendChild( discountNote );
					}

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

	/**
	 * A pencil icon button that loads a Problem/Prescription row back into
	 * its add-form for editing (see enterEditMode()).
	 *
	 * @param {string} type 'problem' or 'prescription'.
	 * @param {Object} row  The row's data, stashed as JSON on the button so
	 *   the click handler doesn't need a separate lookup.
	 */
	function editButton( type, row ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'dak-icon-button';
		button.setAttribute( 'data-encounter-edit', '' );
		button.setAttribute( 'data-edit-type', type );
		button.setAttribute( 'data-row', JSON.stringify( row ) );
		button.setAttribute( 'aria-label', 'Edit' );
		button.innerHTML = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>';
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
