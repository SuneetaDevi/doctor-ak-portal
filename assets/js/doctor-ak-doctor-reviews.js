/**
 * Doctor profile: star picker + review submission.
 */
( function () {
	'use strict';

	var form = document.getElementById( 'dak-review-form' );

	if ( ! form || ! window.dakDoctorReviews ) {
		return;
	}

	var stars = form.querySelectorAll( '.dak-review-star' );
	var errorBox = document.getElementById( 'dak-review-error' );
	var rating = parseInt( form.getAttribute( 'data-rating' ), 10 ) || 0;

	function paint( value ) {
		stars.forEach( function ( star ) {
			star.classList.toggle( 'is-on', parseInt( star.getAttribute( 'data-value' ), 10 ) <= value );
		} );
	}

	stars.forEach( function ( star ) {
		var value = parseInt( star.getAttribute( 'data-value' ), 10 );

		star.addEventListener( 'click', function () {
			rating = value;
			paint( rating );
		} );
		star.addEventListener( 'mouseenter', function () {
			paint( value );
		} );
		star.addEventListener( 'mouseleave', function () {
			paint( rating );
		} );
	} );

	function showError( message ) {
		errorBox.textContent = message;
		errorBox.classList.remove( 'dak-hidden' );
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		errorBox.classList.add( 'dak-hidden' );

		if ( rating < 1 ) {
			showError( 'Please choose a star rating.' );
			return;
		}

		var button = form.querySelector( 'button[type="submit"]' );
		var body = new URLSearchParams();

		body.append( 'action', 'doctor_ak_submit_review' );
		body.append( 'nonce', window.dakDoctorReviews.nonce );
		body.append( 'doctor_id', form.getAttribute( 'data-doctor-id' ) );
		body.append( 'rating', String( rating ) );
		body.append( 'comment', form.elements.comment.value );

		button.disabled = true;

		fetch( window.dakDoctorReviews.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					window.location.hash = 'dak-profile-reviews';
					window.location.reload();
					return;
				}
				button.disabled = false;
				showError( json && json.data && json.data.message ? json.data.message : 'Something went wrong.' );
			} )
			.catch( function () {
				button.disabled = false;
				showError( 'Network error. Please try again.' );
			} );
	} );
}() );
