/**
 * Doctor AK Portal — public Service profile page ([service_profile_view]).
 *
 * Works with templates/directory/service-profile-view.php:
 *  - the pinned section tabs mark the section being read;
 *  - "Doctors & prices" (grouped by clinic): picking a doctor row (a radio
 *    card) updates the booking card, the header button and the phone's
 *    bottom bar to "Book with Dr. X" with that doctor's price, linking to the
 *    booking page with this doctor, service and clinic already chosen;
 *  - the clinic chips show one clinic's doctors (or all);
 *  - "Sort" reorders the doctors within each clinic.
 * The "Request this service" form is handled by doctor-ak-service-request.js.
 */
( function () {
	'use strict';

	var labels = window.dakServiceProfile || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		initSectionTabs();
		initDoctorPicker();
	} );

	/**
	 * Marks the tab of the section being read (aria-current + .is-active) as
	 * the page scrolls — same behaviour as the doctor profile's tabs.
	 */
	function initSectionTabs() {
		var nav = document.querySelector( '.dak-service-view [data-dak-dp-tabs]' );

		if ( ! nav ) {
			return;
		}

		var links = Array.prototype.slice.call( nav.querySelectorAll( 'a[href^="#"]' ) );
		var sections = links.map( function ( link ) {
			return document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
		} );
		var ticking = false;

		function update() {
			ticking = false;

			var line = nav.getBoundingClientRect().bottom + 24;
			var current = -1;

			sections.forEach( function ( section, index ) {
				if ( section && section.getBoundingClientRect().top <= line ) {
					current = index;
				}
			} );

			if ( window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2 ) {
				current = sections.length - 1;
			}

			links.forEach( function ( link, index ) {
				var active = index === current;

				link.classList.toggle( 'is-active', active );

				if ( active ) {
					link.setAttribute( 'aria-current', 'true' );

					if ( nav.scrollWidth > nav.clientWidth ) {
						nav.scrollLeft = Math.max( 0, link.offsetLeft - ( nav.clientWidth - link.offsetWidth ) / 2 );
					}
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );
		}

		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( update );
			}
		}, { passive: true } );

		window.addEventListener( 'resize', update );
		update();
	}

	function format( template, values, fallback ) {
		if ( ! template ) {
			return fallback;
		}

		return template
			.replace( '%1$s', values[0] || '' )
			.replace( '%2$s', values[1] || '' )
			.replace( '%s', values[0] || '' );
	}

	function initDoctorPicker() {
		var container = document.getElementById( 'dak-service-doctor-offers' );

		if ( ! container ) {
			return;
		}

		var groups = Array.prototype.slice.call( container.querySelectorAll( '[data-service-clinic-group]' ) );
		var rows = Array.prototype.slice.call( container.querySelectorAll( '[data-service-doctor-offer]' ) );
		var chips = Array.prototype.slice.call( document.querySelectorAll( '[data-sv-clinic-filter]' ) );
		var sortSelect = document.getElementById( 'dak-service-filter-sort' );

		var fee = document.getElementById( 'dak-service-booking-fee' );
		var hint = document.getElementById( 'dak-service-booking-hint' );
		var ctas = [
			document.getElementById( 'dak-service-booking-button' ),
			document.getElementById( 'dak-service-header-cta' ),
			document.getElementById( 'dak-service-mobile-cta' )
		].filter( Boolean );
		var mobileSummary = document.getElementById( 'dak-service-mobile-summary' );

		container.addEventListener( 'change', function ( event ) {
			if ( 'radio' !== event.target.type ) {
				return;
			}

			var selected = event.target.closest( '[data-service-doctor-offer]' );

			rows.forEach( function ( row ) {
				row.classList.toggle( 'is-selected', row === selected );
			} );

			if ( selected ) {
				choose( selected );
			}
		} );

		function choose( row ) {
			var doctor = row.getAttribute( 'data-doctor-name' ) || '';
			var clinic = row.getAttribute( 'data-clinic-name' ) || '';
			var price = row.getAttribute( 'data-price-label' ) || '';
			var url = row.getAttribute( 'data-booking-url' ) || '';
			var who = clinic ? format( labels.bookingAtLabel, [ doctor, clinic ], doctor + ' at ' + clinic ) : doctor;
			var bookLabel = format( labels.bookWithLabel, [ doctor ], 'Book with ' + doctor );

			if ( fee ) {
				fee.textContent = price;
			}

			if ( hint ) {
				hint.textContent = who;
			}

			if ( mobileSummary ) {
				mobileSummary.textContent = price + ' · ' + doctor;
			}

			if ( url ) {
				ctas.forEach( function ( cta ) {
					cta.setAttribute( 'href', url );
					// The phone bar already names the doctor beside its button.
					cta.textContent = 'dak-service-mobile-cta' === cta.id ? ( labels.bookNowLabel || 'Book now' ) : bookLabel;
					cta.classList.add( 'is-ready' );
				} );
			}
		}

		// Clinic chips: one clinic's doctors, or all.
		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				var value = chip.getAttribute( 'data-sv-clinic-filter' );

				chips.forEach( function ( other ) {
					var on = other === chip;

					other.classList.toggle( 'is-active', on );
					other.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				} );

				groups.forEach( function ( group ) {
					group.classList.toggle( 'dak-hidden', '' !== value && group.getAttribute( 'data-service-clinic-group' ) !== value );
				} );
			} );
		} );

		// Sort within each clinic's list (the clinics keep their order).
		function sortRows() {
			var by = sortSelect ? sortSelect.value : 'price-asc';

			groups.forEach( function ( group ) {
				var list = group.querySelector( '[data-sv-doctor-list]' );

				if ( ! list ) {
					return;
				}

				Array.prototype.slice.call( list.querySelectorAll( '[data-service-doctor-offer]' ) )
					.sort( function ( a, b ) {
						if ( 'name' === by ) {
							return ( a.getAttribute( 'data-doctor-name' ) || '' ).localeCompare( b.getAttribute( 'data-doctor-name' ) || '' );
						}

						var diff = ( parseFloat( a.getAttribute( 'data-price' ) ) || 0 ) - ( parseFloat( b.getAttribute( 'data-price' ) ) || 0 );

						return 'price-desc' === by ? -diff : diff;
					} )
					.forEach( function ( row ) {
						list.appendChild( row );
					} );
			} );
		}

		if ( sortSelect ) {
			sortSelect.addEventListener( 'change', sortRows );
		}

		// Only one doctor in one clinic: nothing to compare, so pick it.
		var bookable = rows.filter( function ( row ) {
			var radio = row.querySelector( 'input[type="radio"]' );

			return radio && ! radio.disabled;
		} );

		if ( 1 === rows.length && 1 === bookable.length ) {
			bookable[0].querySelector( 'input[type="radio"]' ).checked = true;
			bookable[0].classList.add( 'is-selected' );
			choose( bookable[0] );
		}
	}
} )();
