/**
 * Doctor AK Portal — public Service profile page ([service_profile_view]).
 *
 * Wires the "Doctors & Pricing" list — grouped by clinic, each with the
 * doctors who offer this service there nested underneath (see
 * Service_Profile_View::build_clinic_groups() and
 * templates/directory/service-profile-view.php for the markup this
 * expects): filtering (Specialization), sorting doctor rows within each
 * clinic group (Price/Name), selecting a doctor (updates the sidebar's
 * price and "Book Appointment" button), and clicking a row (anywhere except
 * the radio or the doctor-name link) to open that doctor's profile. A
 * clinic group with no rows left after filtering hides itself too.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var container = document.getElementById( 'dak-service-doctor-offers' );

		if ( ! container ) {
			return;
		}

		var groups = Array.prototype.slice.call( container.querySelectorAll( '[data-service-clinic-group]' ) );
		var cards = Array.prototype.slice.call( container.querySelectorAll( '[data-service-doctor-offer]' ) );
		var emptyState = document.getElementById( 'dak-service-doctor-offers-empty' );
		var specializationFilter = document.getElementById( 'dak-service-filter-specialization' );
		var sortSelect = document.getElementById( 'dak-service-filter-sort' );

		var bookingFee = document.getElementById( 'dak-service-booking-fee' );
		var bookingButton = document.getElementById( 'dak-service-booking-button' );
		var bookingHint = document.getElementById( 'dak-service-booking-hint' );

		wireCardClicks();
		wireSelection();
		wireFilters();
		sortCards();

		function wireCardClicks() {
			cards.forEach( function ( card ) {
				card.addEventListener( 'click', function ( event ) {
					// Let the radio and the doctor-name link handle their
					// own clicks (selecting / navigating) — anywhere else
					// on the row opens the doctor's profile.
					if ( event.target.closest( 'a, label, input' ) ) {
						return;
					}

					var profileUrl = card.getAttribute( 'data-profile-url' );

					if ( profileUrl ) {
						window.location.href = profileUrl;
					}
				} );

				card.addEventListener( 'keydown', function ( event ) {
					if ( event.target !== card ) {
						return;
					}

					if ( 'Enter' === event.key || ' ' === event.key ) {
						event.preventDefault();

						var profileUrl = card.getAttribute( 'data-profile-url' );

						if ( profileUrl ) {
							window.location.href = profileUrl;
						}
					}
				} );
			} );
		}

		function wireSelection() {
			container.addEventListener( 'change', function ( event ) {
				if ( 'radio' !== event.target.type ) {
					return;
				}

				var selectedCard = event.target.closest( '[data-service-doctor-offer]' );

				if ( ! selectedCard ) {
					return;
				}

				cards.forEach( function ( card ) {
					card.classList.toggle( 'is-selected', card === selectedCard );
				} );

				updateBookingCard( selectedCard );
			} );
		}

		function updateBookingCard( card ) {
			var doctorName = card.getAttribute( 'data-doctor-name' ) || '';
			var priceLabel = card.getAttribute( 'data-price-label' ) || '';
			var bookingUrl = card.getAttribute( 'data-booking-url' ) || '';

			if ( bookingFee ) {
				bookingFee.textContent = priceLabel;
			}

			if ( bookingHint && doctorName ) {
				bookingHint.textContent = ( window.dakServiceProfile && window.dakServiceProfile.bookingWithLabel )
					? window.dakServiceProfile.bookingWithLabel.replace( '%s', doctorName )
					: 'Booking with ' + doctorName + '.';
			}

			if ( bookingButton && bookingUrl ) {
				bookingButton.setAttribute( 'href', bookingUrl );
				bookingButton.textContent = ( window.dakServiceProfile && window.dakServiceProfile.bookAppointmentLabel ) || 'Book Appointment';
				bookingButton.classList.remove( 'dak-button-disabled' );
			}
		}

		function wireFilters() {
			[ specializationFilter, sortSelect ].forEach( function ( select ) {
				if ( select ) {
					select.addEventListener( 'change', applyFiltersAndSort );
				}
			} );
		}

		function applyFiltersAndSort() {
			var specialization = specializationFilter ? specializationFilter.value : '';
			var visibleCount = 0;

			cards.forEach( function ( card ) {
				var matches = ! specialization || card.getAttribute( 'data-category' ) === specialization;

				card.classList.toggle( 'dak-hidden', ! matches );

				if ( matches ) {
					visibleCount++;
				}
			} );

			groups.forEach( function ( group ) {
				var anyVisible = group.querySelector( '[data-service-doctor-offer]:not(.dak-hidden)' );
				group.classList.toggle( 'dak-hidden', ! anyVisible );
			} );

			if ( emptyState ) {
				emptyState.classList.toggle( 'dak-hidden', visibleCount > 0 );
			}

			sortCards();
		}

		/**
		 * Reorders the doctor rows WITHIN each clinic group (never across
		 * groups — a clinic group's own position stays alphabetical, set
		 * server-side).
		 */
		function sortCards() {
			var sortBy = sortSelect ? sortSelect.value : 'price-asc';

			groups.forEach( function ( group ) {
				var groupCards = Array.prototype.slice.call( group.querySelectorAll( '[data-service-doctor-offer]' ) );

				var sorted = groupCards.sort( function ( a, b ) {
					if ( 'name' === sortBy ) {
						return a.getAttribute( 'data-doctor-name' ).localeCompare( b.getAttribute( 'data-doctor-name' ) );
					}

					var priceA = parseFloat( a.getAttribute( 'data-price' ) ) || 0;
					var priceB = parseFloat( b.getAttribute( 'data-price' ) ) || 0;

					return 'price-desc' === sortBy ? priceB - priceA : priceA - priceB;
				} );

				sorted.forEach( function ( card ) {
					group.appendChild( card );
				} );
			} );
		}
	} );
} )();
