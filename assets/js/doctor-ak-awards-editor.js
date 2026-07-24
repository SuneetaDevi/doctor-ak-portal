/**
 * Doctor AK Portal — Reusable "Awards & Recognition" repeatable-row editor.
 *
 * Shared by the doctor's own profile form (assets/js/doctor-ak-profile.js)
 * and the admin's Add/Edit Doctor modal (assets/js/doctor-ak-admin-dashboard.js).
 * Wires every `[data-awards-editor]` container found on the page; each one
 * needs a `[data-awards-rows]` child to hold the rows and a
 * `[data-awards-add-row]` button to add a new blank row. Exposes
 * `window.dakAwardsEditor.addRow()`/`.reset()` so those other scripts can
 * programmatically add/clear rows (e.g. resetting the admin modal for a
 * fresh "Add Doctor", or populating it with an existing doctor's awards).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-awards-editor]' ).forEach( wireEditor );
	} );

	function wireEditor( editor ) {
		var rows = editor.querySelector( '[data-awards-rows]' );
		var addButton = editor.querySelector( '[data-awards-add-row]' );

		if ( ! rows || ! addButton ) {
			return;
		}

		addButton.addEventListener( 'click', function () {
			addRow( rows );
		} );

		rows.addEventListener( 'click', function ( event ) {
			var removeButton = event.target.closest( '[data-awards-remove-row]' );

			if ( ! removeButton ) {
				return;
			}

			var row = removeButton.closest( '[data-awards-row]' );

			if ( row ) {
				row.remove();
			}
		} );
	}

	/**
	 * Appends one row (optionally pre-filled) to a rows container.
	 *
	 * @param {HTMLElement} rows  The `[data-awards-rows]` container.
	 * @param {string}      title Optional pre-filled title.
	 * @param {string|number} year Optional pre-filled year.
	 * @return {void}
	 */
	function addRow( rows, title, year ) {
		var row = document.createElement( 'div' );
		row.className = 'dak-awards-row';
		row.setAttribute( 'data-awards-row', '' );

		var titleInput = document.createElement( 'input' );
		titleInput.type = 'text';
		titleInput.name = 'award_title[]';
		titleInput.placeholder = 'Award title';
		titleInput.value = title || '';

		var yearInput = document.createElement( 'input' );
		yearInput.type = 'number';
		yearInput.name = 'award_year[]';
		yearInput.placeholder = 'Year';
		yearInput.min = '1950';
		yearInput.max = String( new Date().getFullYear() + 1 );
		yearInput.value = year || '';

		var removeButton = document.createElement( 'button' );
		removeButton.type = 'button';
		removeButton.className = 'dak-awards-remove';
		removeButton.setAttribute( 'data-awards-remove-row', '' );
		removeButton.setAttribute( 'aria-label', 'Remove award' );
		removeButton.textContent = '×';

		row.appendChild( titleInput );
		row.appendChild( yearInput );
		row.appendChild( removeButton );
		rows.appendChild( row );
	}

	/**
	 * Empties a rows container, then optionally repopulates it from a list
	 * of `{ title, year }` objects.
	 *
	 * @param {HTMLElement} rows   The `[data-awards-rows]` container.
	 * @param {Array}       awards Optional list of `{ title, year }`.
	 * @return {void}
	 */
	function reset( rows, awards ) {
		if ( ! rows ) {
			return;
		}

		rows.innerHTML = '';

		( awards || [] ).forEach( function ( award ) {
			addRow( rows, award.title, award.year );
		} );
	}

	window.dakAwardsEditor = { addRow: addRow, reset: reset };
} )();
