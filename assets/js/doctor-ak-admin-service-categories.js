/**
 * Doctor AK Portal — Admin Services section's "Categories" tab.
 *
 * Lets an administrator add or delete a service category, via
 * Service_Category_Handler's admin AJAX endpoints
 * (doctor_ak_admin_service_category_save/_delete). The default
 * "Miscellaneous/Other Services" category has no delete button (see
 * admin-service-categories.php) — it's the header mega-menu's permanent
 * catch-all bucket and can't be removed.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var addButton = document.getElementById( 'dak-admin-service-category-add' );
		var input = document.getElementById( 'dak-admin-service-category-new-label' );

		if ( ! addButton || ! input || ! window.dakAdminServiceCategories ) {
			return;
		}

		function clearErrors() {
			var fieldError = document.querySelector( '.dak-service-category-add-row .dak-field-error' );

			if ( fieldError ) {
				fieldError.textContent = '';
			}

			hideGeneralError();
		}

		function showFieldError( message ) {
			var fieldError = document.querySelector( '.dak-service-category-add-row .dak-field-error' );

			if ( fieldError ) {
				fieldError.textContent = message;
				return;
			}

			showGeneralError( message );
		}

		function showGeneralError( message ) {
			var el = document.getElementById( 'dak-admin-service-categories-general-error' );

			if ( el ) {
				el.textContent = message;
				el.classList.remove( 'dak-hidden' );
			}
		}

		function hideGeneralError() {
			var el = document.getElementById( 'dak-admin-service-categories-general-error' );

			if ( el ) {
				el.classList.add( 'dak-hidden' );
			}
		}

		addButton.addEventListener( 'click', function () {
			clearErrors();

			var label = input.value.trim();

			if ( '' === label ) {
				showFieldError( 'Please provide a name for this category.' );
				return;
			}

			addButton.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_category_save' );
			formData.append( 'nonce', window.dakAdminServiceCategories.nonce );
			formData.append( 'label', label );

			fetch( window.dakAdminServiceCategories.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					addButton.disabled = false;

					if ( result.success ) {
						window.location.reload();
						return;
					}

					if ( result.data && result.data.errors && result.data.errors.label ) {
						showFieldError( result.data.errors.label );
						return;
					}

					showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					addButton.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
				} );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				addButton.click();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-service-category-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this category? Services already using it will be grouped under "Miscellaneous/Other Services" instead.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_service_category_delete' );
			formData.append( 'nonce', window.dakAdminServiceCategories.nonce );
			formData.append( 'slug', trigger.getAttribute( 'data-slug' ) );

			fetch( window.dakAdminServiceCategories.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
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
	} );
} )();
