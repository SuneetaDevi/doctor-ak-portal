/**
 * Doctor AK Portal — Reusable "Services" repeatable-row editor.
 *
 * Used by the Add/Edit Doctor form's onboarding "Services" step (see
 * admin-user-form-screen.php) to add several services in one go instead of
 * a separate trip to the Services section for each one. Wires every
 * `[data-services-editor]` container found on the page; each one needs a
 * `[data-services-rows]` child to hold the rows and a
 * `[data-services-add-row]` button to add a new blank row. The container's
 * `data-categories` attribute carries the Category <select>'s options as a
 * JSON `{ slug: label }` map (same list Service_Categories::get_all() feeds
 * the standalone Services form).
 *
 * Each row also has an "Available at" clinic checkbox list — which of this
 * doctor's clinics (from the Clinics step's multi-select, given via the
 * editor's `data-clinic-select-id` attribute) this particular service is
 * offered at. Leaving every box unchecked means "every clinic" (the
 * existing, backward-compatible default — see
 * Doctor_Profile_View::clinic_fee_label()); checking one or more narrows it
 * down. The clinic list re-renders on every row whenever the Clinics
 * step's selection changes, keeping a row's already-checked clinics checked
 * as long as they're still selected there.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-services-editor]' ).forEach( wireEditor );
	} );

	// Every row gets a client-side-only key so its posted clinic checkboxes
	// (service_clinic_ids[<key>][]) can be matched back to the right row
	// server-side without depending on DOM order — see
	// Admin_User_Handler::handle_save() reading service_row_key[] alongside
	// service_name[]. Existing rows use their own real service ID as the
	// key (already unique); only brand-new rows (service_id "0") need this.
	var newRowCounter = 0;

	function wireEditor( editor ) {
		var rows = editor.querySelector( '[data-services-rows]' );
		var addButton = editor.querySelector( '[data-services-add-row]' );
		var header = editor.querySelector( '[data-services-row-header]' );

		if ( ! rows || ! addButton ) {
			return;
		}

		var categories = {};

		try {
			categories = JSON.parse( editor.getAttribute( 'data-categories' ) || '{}' );
		} catch ( e ) {
			categories = {};
		}

		var clinicSelectId = editor.getAttribute( 'data-clinic-select-id' ) || '';
		var clinicSelect = clinicSelectId ? document.getElementById( clinicSelectId ) : null;

		function currentClinics() {
			if ( ! clinicSelect ) {
				return [];
			}

			return Array.prototype.map.call( clinicSelect.selectedOptions || [], function ( option ) {
				return { id: option.value, label: option.textContent };
			} );
		}

		function refreshAllRowClinics() {
			var clinics = currentClinics();

			rows.querySelectorAll( '[data-services-row]' ).forEach( function ( row ) {
				renderRowClinics( row, clinics );
			} );
		}

		if ( clinicSelect ) {
			clinicSelect.addEventListener( 'change', refreshAllRowClinics );
		}

		function refreshHeader() {
			if ( header ) {
				header.classList.toggle( 'dak-hidden', 0 === rows.children.length );
			}
		}

		addButton.addEventListener( 'click', function () {
			var row = addRow( rows, categories );
			renderRowClinics( row, currentClinics() );
			refreshHeader();
		} );

		rows.addEventListener( 'click', function ( event ) {
			var removeButton = event.target.closest( '[data-services-remove-row]' );

			if ( ! removeButton ) {
				return;
			}

			var row = removeButton.closest( '[data-services-row]' );

			if ( row ) {
				row.remove();
				refreshHeader();
			}
		} );

		// Initial paint: PHP already rendered each existing row's own
		// checked clinics (data-checked-clinic-ids on the row), but the
		// clinic list itself — labels, and any clinic added/removed since
		// this doctor was last saved — always comes from the live <select>.
		refreshAllRowClinics();
		refreshHeader();
	}

	/**
	 * (Re)builds one row's "Available at" clinic checkbox list, preserving
	 * whichever of the given clinics were already checked on it.
	 *
	 * @param {HTMLElement} row     A `[data-services-row]` element.
	 * @param {Array}       clinics [{ id, label }] — the doctor's currently selected clinics.
	 * @return {void}
	 */
	function renderRowClinics( row, clinics ) {
		var wrap = row.querySelector( '[data-services-row-clinics]' );

		if ( ! wrap ) {
			return;
		}

		var rowKey = row.getAttribute( 'data-row-key' ) || '';
		var previouslyChecked = {};

		wrap.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( box ) {
			if ( box.checked ) {
				previouslyChecked[ box.value ] = true;
			}
		} );

		// First paint of a PHP-rendered existing row: nothing's checked yet
		// in the (still-empty) DOM, so fall back to the server-supplied set.
		if ( 0 === Object.keys( previouslyChecked ).length && row.hasAttribute( 'data-checked-clinic-ids' ) ) {
			( row.getAttribute( 'data-checked-clinic-ids' ) || '' ).split( ',' ).forEach( function ( id ) {
				if ( '' !== id ) {
					previouslyChecked[ id ] = true;
				}
			} );
		}

		wrap.innerHTML = '';

		if ( ! clinics.length ) {
			wrap.classList.add( 'dak-hidden' );
			return;
		}

		wrap.classList.remove( 'dak-hidden' );

		var caption = document.createElement( 'span' );
		caption.className = 'dak-services-row-clinics-label';
		caption.textContent = 'Available at (leave all unchecked for every clinic):';
		wrap.appendChild( caption );

		var list = document.createElement( 'div' );
		list.className = 'dak-services-row-clinics-list';

		clinics.forEach( function ( clinic ) {
			var label = document.createElement( 'label' );
			label.className = 'dak-services-clinic-chip';

			var box = document.createElement( 'input' );
			box.type = 'checkbox';
			box.name = 'service_clinic_ids[' + rowKey + '][]';
			box.value = clinic.id;
			box.checked = !! previouslyChecked[ clinic.id ];

			label.appendChild( box );
			label.appendChild( document.createTextNode( clinic.label ) );
			list.appendChild( label );
		} );

		wrap.appendChild( list );
	}

	/**
	 * Appends one row (optionally pre-filled) to a rows container.
	 *
	 * @param {HTMLElement} rows       The `[data-services-rows]` container.
	 * @param {Object}      categories Category slug => label.
	 * @param {Object}      [values]   Optional pre-filled { name, category, charge, durationMinutes }.
	 * @return {HTMLElement} The row just added.
	 */
	function addRow( rows, categories, values ) {
		values = values || {};

		var row = document.createElement( 'div' );
		row.className = 'dak-services-row';
		row.setAttribute( 'data-services-row', '' );

		var rowKey = String( values.id || '' );

		if ( ! rowKey || '0' === rowKey ) {
			newRowCounter += 1;
			rowKey = 'new' + newRowCounter;
		}

		row.setAttribute( 'data-row-key', rowKey );

		var fields = document.createElement( 'div' );
		fields.className = 'dak-services-row-fields';

		// Keeps this row's position aligned with any server-rendered
		// existing rows ahead of it in the same [] arrays — '0' tells the
		// handler "create a new row" instead of updating one by ID.
		var idInput = document.createElement( 'input' );
		idInput.type = 'hidden';
		idInput.name = 'service_id[]';
		idInput.value = values.id || '0';
		fields.appendChild( idInput );

		var rowKeyInput = document.createElement( 'input' );
		rowKeyInput.type = 'hidden';
		rowKeyInput.name = 'service_row_key[]';
		rowKeyInput.value = rowKey;
		fields.appendChild( rowKeyInput );

		var nameInput = document.createElement( 'input' );
		nameInput.type = 'text';
		nameInput.name = 'service_name[]';
		nameInput.placeholder = 'e.g. OPD Consultation';
		nameInput.value = values.name || '';

		var categorySelect = document.createElement( 'select' );
		categorySelect.name = 'service_category[]';

		var noneOption = document.createElement( 'option' );
		noneOption.value = '';
		noneOption.textContent = 'No category';
		categorySelect.appendChild( noneOption );

		Object.keys( categories ).forEach( function ( slug ) {
			var option = document.createElement( 'option' );
			option.value = slug;
			option.textContent = categories[ slug ];

			if ( values.category === slug ) {
				option.selected = true;
			}

			categorySelect.appendChild( option );
		} );

		var chargeInput = document.createElement( 'input' );
		chargeInput.type = 'number';
		chargeInput.name = 'service_charge[]';
		chargeInput.min = '0';
		chargeInput.step = '0.01';
		chargeInput.placeholder = '0';
		chargeInput.value = values.charge || '';

		var durationInput = document.createElement( 'input' );
		durationInput.type = 'number';
		durationInput.name = 'service_duration_minutes[]';
		durationInput.min = '0';
		durationInput.max = '480';
		durationInput.placeholder = '0';
		durationInput.value = values.durationMinutes || '';

		var removeButton = document.createElement( 'button' );
		removeButton.type = 'button';
		removeButton.className = 'dak-services-remove';
		removeButton.setAttribute( 'data-services-remove-row', '' );
		removeButton.setAttribute( 'aria-label', 'Remove service' );
		removeButton.textContent = '×';

		fields.appendChild( nameInput );
		fields.appendChild( categorySelect );
		fields.appendChild( chargeInput );
		fields.appendChild( durationInput );
		fields.appendChild( removeButton );

		var clinicsWrap = document.createElement( 'div' );
		clinicsWrap.className = 'dak-services-row-clinics dak-hidden';
		clinicsWrap.setAttribute( 'data-services-row-clinics', '' );

		row.appendChild( fields );
		row.appendChild( clinicsWrap );

		rows.appendChild( row );

		return row;
	}

	window.dakServicesEditor = { addRow: addRow };
} )();
