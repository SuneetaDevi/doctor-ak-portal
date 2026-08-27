/**
 * Doctor AK Portal — public Service profile page ([service_profile_view]).
 *
 * Wires the "Doctors & Pricing" list: filtering (Specialization/Location),
 * sorting (Price/Name), selecting a doctor (updates the sidebar's price and
 * "Book Appointment" button), and clicking a card (anywhere except the
 * radio or the doctor-name link) to open that doctor's profile — see
 * templates/directory/service-profile-view.php for the markup this expects.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var container = document.getElementById( 'dak-service-doctor-offers' );

		if ( ! container ) {
			return;
		}

		var cards = Array.prototype.slice.call( container.querySelectorAll( '[data-service-doctor-offer]' ) );
		var emptyState = document.getElementById( 'dak-service-doctor-offers-empty' );
		var specializationFilter = document.getElementById( 'dak-service-filter-specialization' );
		var locationFilter = document.getElementById( 'dak-service-filter-location' );
		var sortSelect = document.getElementById( 'dak-service-filter-sort' );

		var bookingFee = document.getElementById( 'dak-service-booking-fee' );
		var bookingButton = document.getElementById( 'dak-service-booking-button' );
		var bookingHint = document.getElementById( 'dak-service-booking-hint' );

		wireCardClicks();
		wireSelection();
		wireFilters();

		function wireCardClicks() {
			cards.forEach( function ( card ) {
				card.addEventListener( 'click', function ( event ) {
					// Let the radio and the doctor-name link handle their
					// own clicks (selecting / navigating) — anywhere else
					// on the card opens the doctor's profile.
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
			[ specializationFilter, locationFilter, sortSelect ].forEach( function ( select ) {
				if ( select ) {
					select.addEventListener( 'change', applyFiltersAndSort );
				}
			} );
		}

		function applyFiltersAndSort() {
			var specialization = specializationFilter ? specializationFilter.value : '';
			var location = locationFilter ? locationFilter.value : '';
			var visibleCount = 0;

			cards.forEach( function ( card ) {
				var matchesSpecialization = ! specialization || card.getAttribute( 'data-category' ) === specialization;
				var locations = ( card.getAttribute( 'data-locations' ) || '' ).split( '|' );
				var matchesLocation = ! location || locations.indexOf( location ) !== -1;
				var matches = matchesSpecialization && matchesLocation;

				card.classList.toggle( 'dak-hidden', ! matches );

				if ( matches ) {
					visibleCount++;
				}
			} );

			if ( emptyState ) {
				emptyState.classList.toggle( 'dak-hidden', visibleCount > 0 );
			}

			sortCards();
		}

		function sortCards() {
			var sortBy = sortSelect ? sortSelect.value : 'price-asc';

			var sorted = cards.slice().sort( function ( a, b ) {
				if ( 'name' === sortBy ) {
					return a.getAttribute( 'data-doctor-name' ).localeCompare( b.getAttribute( 'data-doctor-name' ) );
				}

				var priceA = parseFloat( a.getAttribute( 'data-price' ) ) || 0;
				var priceB = parseFloat( b.getAttribute( 'data-price' ) ) || 0;

				return 'price-desc' === sortBy ? priceB - priceA : priceA - priceB;
			} );

			sorted.forEach( function ( card ) {
				container.appendChild( card );
			} );
		}
	} );
} )();
