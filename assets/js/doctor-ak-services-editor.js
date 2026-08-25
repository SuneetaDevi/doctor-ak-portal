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
 * JSON `{ slug: label }` map (same list Specializations::get_all() feeds
 * the standalone Services form).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-services-editor]' ).forEach( wireEditor );
	} );

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

		function refreshHeader() {
			if ( header ) {
				header.classList.toggle( 'dak-hidden', 0 === rows.children.length );
			}
		}

		addButton.addEventListener( 'click', function () {
			addRow( rows, categories );
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

		refreshHeader();
	}

	/**
	 * Appends one row (optionally pre-filled) to a rows container.
	 *
	 * @param {HTMLElement} rows       The `[data-services-rows]` container.
	 * @param {Object}      categories Category slug => label.
	 * @param {Object}      [values]   Optional pre-filled { name, category, charge, durationMinutes }.
	 * @return {void}
	 */
	function addRow( rows, categories, values ) {
		values = values || {};

		var row = document.createElement( 'div' );
		row.className = 'dak-services-row';
		row.setAttribute( 'data-services-row', '' );

		// Keeps this row's position aligned with any server-rendered
		// existing rows ahead of it in the same [] arrays — '0' tells the
		// handler "create a new row" instead of updating one by ID.
		var idInput = document.createElement( 'input' );
		idInput.type = 'hidden';
		idInput.name = 'service_id[]';
		idInput.value = values.id || '0';
		row.appendChild( idInput );

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

		row.appendChild( nameInput );
		row.appendChild( categorySelect );
		row.appendChild( chargeInput );
		row.appendChild( durationInput );
		row.appendChild( removeButton );

		rows.appendChild( row );
	}

	window.dakServicesEditor = { addRow: addRow };
} )();
