/**
 * Doctor AK Portal — Admin dashboard "Settings" page: "Home page
 * testimonials" repeatable-row editor (see
 * templates/dashboard/partials/admin-settings-section.php).
 *
 * Plain text rows (quote, patient name, optional attribution) — unlike Home
 * Videos there's nothing to upload, so rows are only persisted when the
 * page's combined "Save Settings" button is clicked (see
 * window.dakHomeTestimonialsEditor.collectRows(), called from
 * doctor-ak-admin-settings-save.js).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var editor = document.querySelector( '[data-home-testimonials-editor]' );

		if ( ! editor || ! window.dakHomeTestimonials ) {
			return;
		}

		var rowsContainer = editor.querySelector( '[data-home-testimonials-rows]' );
		var addButton = editor.querySelector( '[data-home-testimonials-add-row]' );

		if ( ! rowsContainer || ! addButton ) {
			return;
		}

		( window.dakHomeTestimonials.rows || [] ).forEach( function ( row ) {
			addRow( rowsContainer, row );
		} );

		addButton.addEventListener( 'click', function () {
			addRow( rowsContainer, {} );
		} );

		rowsContainer.addEventListener( 'click', function ( event ) {
			var removeButton = event.target.closest( '[data-home-testimonial-remove]' );

			if ( removeButton ) {
				var row = removeButton.closest( '[data-home-testimonial-row]' );

				if ( row ) {
					row.remove();
				}
			}
		} );

		/**
		 * @return {Array<Object>} { quote, name, attribution } for every row
		 *   that has both a quote and a name — incomplete rows are skipped.
		 */
		function collectRows() {
			return Array.prototype.slice.call( rowsContainer.querySelectorAll( '[data-home-testimonial-row]' ) )
				.map( function ( row ) {
					return {
						quote: row.querySelector( '[data-home-testimonial-quote]' ).value,
						name: row.querySelector( '[data-home-testimonial-name]' ).value,
						attribution: row.querySelector( '[data-home-testimonial-attribution]' ).value,
					};
				} )
				.filter( function ( row ) {
					return '' !== row.quote.trim() && '' !== row.name.trim();
				} );
		}

		window.dakHomeTestimonialsEditor = {
			collectRows: collectRows,
		};

		/**
		 * Builds and appends one row, optionally pre-filled.
		 *
		 * @param {HTMLElement} container Rows container.
		 * @param {Object}      data      { quote, name, attribution }.
		 * @return {void}
		 */
		function addRow( container, data ) {
			var row = document.createElement( 'div' );
			row.className = 'dak-home-testimonial-row';
			row.setAttribute( 'data-home-testimonial-row', '' );

			var quote = document.createElement( 'textarea' );
			quote.placeholder = 'Patient quote';
			quote.rows = 2;
			quote.value = data.quote || '';
			quote.setAttribute( 'data-home-testimonial-quote', '' );
			row.appendChild( quote );

			var fields = document.createElement( 'div' );
			fields.className = 'dak-home-testimonial-fields';

			var name = document.createElement( 'input' );
			name.type = 'text';
			name.placeholder = 'Patient name';
			name.value = data.name || '';
			name.setAttribute( 'data-home-testimonial-name', '' );
			fields.appendChild( name );

			var attribution = document.createElement( 'input' );
			attribution.type = 'text';
			attribution.placeholder = 'Attribution (optional), e.g. Clifton clinic';
			attribution.value = data.attribution || '';
			attribution.setAttribute( 'data-home-testimonial-attribution', '' );
			fields.appendChild( attribution );

			row.appendChild( fields );
			row.appendChild( buildRemoveButton() );

			container.appendChild( row );
		}

		function buildRemoveButton() {
			var removeButton = document.createElement( 'button' );
			removeButton.type = 'button';
			removeButton.className = 'dak-home-testimonial-remove';
			removeButton.setAttribute( 'data-home-testimonial-remove', '' );
			removeButton.setAttribute( 'aria-label', 'Remove testimonial' );
			removeButton.textContent = '×';

			return removeButton;
		}
	} );
} )();
