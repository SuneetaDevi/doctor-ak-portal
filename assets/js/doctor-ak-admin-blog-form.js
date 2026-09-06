/**
 * Doctor AK Portal — Admin full-screen Add/Edit Blog Post form.
 *
 * Reached via `?section=blogs&view=form[&blog_id=X]`. Submits to the
 * doctor_ak_admin_blog_save AJAX endpoint; on success it redirects back to
 * the Blogs table. Mirrors doctor-ak-admin-service-form.js, minus the
 * doctor-multiselect/clinic-pricing pieces Blogs doesn't have.
 */
( function () {
	'use strict';

	var selectedImageFile = null;

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'dak-admin-blog-form' );

		if ( ! form || ! window.dakAdminBlogs ) {
			return;
		}

		wireImagePicker();
		wireSubmit( form );
	} );

	function setImagePreview( url ) {
		var preview = document.getElementById( 'dak-admin-blog-image-preview' );

		if ( url ) {
			preview.src = url;
			preview.classList.remove( 'dak-hidden' );
		} else {
			preview.src = '';
			preview.classList.add( 'dak-hidden' );
		}
	}

	function wireImagePicker() {
		var input = document.getElementById( 'dak-admin-blog-image' );

		if ( ! input ) {
			return;
		}

		input.addEventListener( 'change', function () {
			selectedImageFile = input.files && input.files[ 0 ] ? input.files[ 0 ] : null;

			if ( selectedImageFile ) {
				setImagePreview( URL.createObjectURL( selectedImageFile ) );
			}
		} );
	}

	function wireSubmit( form ) {
		var submitButton = document.getElementById( 'dak-admin-blog-submit' );
		var listUrl = form.getAttribute( 'data-list-url' ) || '';

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();

			var title = document.getElementById( 'dak-admin-blog-title' ).value.trim();

			if ( ! title ) {
				showFieldError( 'title', 'Please provide a title for this post.' );
				return;
			}

			submitButton.disabled = true;

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_admin_blog_save' );
			formData.append( 'nonce', window.dakAdminBlogs.nonce );

			if ( selectedImageFile ) {
				formData.append( 'image', selectedImageFile );
			}

			fetch( window.dakAdminBlogs.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitButton.disabled = false;

					if ( result.success ) {
						window.location.href = listUrl || window.location.href;
						return;
					}

					showErrors( result );
					scrollToFirstError();
				} )
				.catch( function () {
					submitButton.disabled = false;
					showGeneralError( 'Something went wrong. Please try again.' );
					scrollToFirstError();
				} );
		} );
	}

	function showErrors( result ) {
		if ( result.data && result.data.errors ) {
			var messages = [];

			Object.keys( result.data.errors ).forEach( function ( field ) {
				messages.push( result.data.errors[ field ] );
				showFieldError( field, result.data.errors[ field ] );
			} );

			showGeneralError( messages.join( ' ' ) );
			return;
		}

		showGeneralError( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
	}

	function clearErrors() {
		document.querySelectorAll( '#dak-admin-blog-form .dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );

		var generalError = document.getElementById( 'dak-admin-blog-general-error' );

		if ( generalError ) {
			generalError.textContent = '';
			generalError.classList.add( 'dak-hidden' );
		}
	}

	function showFieldError( field, message ) {
		var el = document.querySelector( '#dak-admin-blog-form .dak-field-error[data-field="' + field + '"]' );

		if ( el ) {
			el.textContent = message;
			return;
		}

		showGeneralError( message );
	}

	function showGeneralError( message ) {
		var el = document.getElementById( 'dak-admin-blog-general-error' );

		if ( el ) {
			el.textContent = message;
			el.classList.remove( 'dak-hidden' );
		}
	}

	function scrollToFirstError() {
		var el = document.getElementById( 'dak-admin-blog-general-error' );

		if ( el ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	}
} )();
