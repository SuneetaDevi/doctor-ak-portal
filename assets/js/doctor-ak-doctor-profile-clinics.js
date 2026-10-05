/**
 * Doctor AK Portal — public doctor profile page's "Clinics & Fees" picker.
 *
 * A 3-step native-radio sequence (visit type -> clinic -> service), built
 * from server-rendered markup (see templates/directory/doctor-profile-view.php
 * + Doctor_Profile_View::clinics_with_services()) — no AJAX, every clinic's
 * services are already in the DOM, just shown/hidden as the picker narrows
 * down. A step with only one real option (e.g. a doctor with a single
 * physical clinic, or only one of clinic/video actually offered) isn't
 * rendered as a choice at all — see #dak-profile-picker's
 * data-fixed-type/data-fixed-clinic-id attributes, read below instead of
 * looking for a radio that was never printed.
 *
 * Three buttons always mirror the exact same state — the header's primary
 * CTA, the sidebar's booking card CTA, and the mobile fixed-bottom-bar CTA
 * (see initCtaButtons() below) — fixing the old asymmetry where only the
 * sidebar button reflected the picker and the header one always skipped
 * straight to an empty Selection step. Before everything needed is chosen,
 * none of the three carry `data-dak-book-appointment` at all (so the
 * site-wide click listener in doctor-ak-booking-redirect.js ignores them);
 * clicking one instead scrolls to and focuses whichever step still needs an
 * answer. Once resolved, all three gain `data-dak-book-appointment` plus
 * `data-doctor-id`/`data-booking-type`/`data-service-id`/`data-clinic-id` —
 * the exact same attribute contract that script already reads, so it needs
 * no changes, and Booking_Page::resolved_selection() keeps working exactly
 * as before.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initServiceGroups();
		initTagShowAll();
		initPicker();
	} );

	/**
	 * CSS.escape() fallback for older browsers — only ever used to build an
	 * attribute-value selector from a clinic id we already control (an
	 * integer string), so this is a defensive fallback, not a real sanitizer.
	 *
	 * @param {string} value
	 * @return {string}
	 */
	function cssEscape( value ) {
		return window.CSS && window.CSS.escape ? window.CSS.escape( value ) : value;
	}

	/**
	 * Each clinic's "Services at X" fieldset gets its own search-filter and
	 * "Show all" expansion, independent of the others (only one is ever
	 * visible at once, driven by initPicker() below, but each keeps its own
	 * state so switching clinics and back doesn't lose it).
	 */
	function initServiceGroups() {
		document.querySelectorAll( '.dak-profile-service-group' ).forEach( function ( fieldset ) {
			var searchInput = fieldset.querySelector( '[data-service-search]' );
			var showAllButton = fieldset.querySelector( '[data-show-all-services]' );
			var noResults = fieldset.querySelector( '[data-service-no-results]' );
			var options = Array.prototype.slice.call( fieldset.querySelectorAll( '.dak-profile-service-option' ) );
			var expanded = false;

			function applyFilter() {
				var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
				var visibleCount = 0;

				options.forEach( function ( option, index ) {
					var name = option.getAttribute( 'data-service-name' ) || '';
					var visible = '' !== query ? ( name.indexOf( query ) !== -1 ) : ( expanded || index < 6 );

					option.classList.toggle( 'dak-hidden', ! visible );

					if ( visible ) {
						visibleCount++;
					}
				} );

				if ( noResults ) {
					noResults.classList.toggle( 'dak-hidden', visibleCount > 0 );
				}
			}

			if ( searchInput ) {
				searchInput.addEventListener( 'input', applyFilter );
			}

			if ( showAllButton ) {
				showAllButton.addEventListener( 'click', function () {
					expanded = true;
					showAllButton.setAttribute( 'aria-expanded', 'true' );
					showAllButton.classList.add( 'dak-hidden' );
					applyFilter();
				} );
			}
		} );
	}

	/**
	 * Generic "Show all N" expansion for any [data-show-all-tags] button
	 * (currently just the Procedures/Conditions tag wall) — reveals every
	 * .dak-hidden child of the element its aria-controls points at.
	 */
	function initTagShowAll() {
		document.querySelectorAll( '[data-show-all-tags]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var targetId = button.getAttribute( 'aria-controls' ) || button.getAttribute( 'data-show-all-tags' );
				var target = targetId ? document.getElementById( targetId ) : null;

				if ( ! target ) {
					return;
				}

				Array.prototype.slice.call( target.querySelectorAll( '.dak-hidden' ) ).forEach( function ( el ) {
					el.classList.remove( 'dak-hidden' );
				} );

				button.setAttribute( 'aria-expanded', 'true' );
				button.classList.add( 'dak-hidden' );
			} );
		} );
	}

	function initPicker() {
		var picker = document.getElementById( 'dak-profile-picker' );
		var ctaButtons = Array.prototype.filter.call(
			[
				document.getElementById( 'dak-profile-header-cta' ),
				document.getElementById( 'dak-profile-sidebar-cta' ),
				document.getElementById( 'dak-profile-mobile-cta' ),
			],
			Boolean
		);
		var sidebarHint = document.getElementById( 'dak-profile-booking-hint' );
		var mobileSummary = document.getElementById( 'dak-profile-mobile-bar-summary' );
		var feeBox = document.getElementById( 'dak-profile-booking-fee' );
		var feeLabelEl = document.getElementById( 'dak-profile-booking-fee-label' );
		var feeAmountEl = document.getElementById( 'dak-profile-booking-fee-amount' );

		// No picker at all (no clinics and no video) and/or no CTA buttons on
		// the page — nothing to wire up.
		if ( ! ctaButtons.length ) {
			return;
		}

		var doctorId = picker ? picker.getAttribute( 'data-doctor-id' ) || '' : '';
		var doctorName = picker ? picker.getAttribute( 'data-doctor-name' ) || '' : '';
		var fixedType = picker ? picker.getAttribute( 'data-fixed-type' ) || '' : '';
		var fixedClinicId = picker ? picker.getAttribute( 'data-fixed-clinic-id' ) || '' : '';
		var videoStatic = picker ? picker.querySelector( '[data-picker-static="video"]' ) : null;
		var clinicStatic = picker ? picker.querySelector( '[data-picker-static="clinic"]' ) : null;
		var serviceGroups = picker ? Array.prototype.slice.call( picker.querySelectorAll( '.dak-profile-service-group' ) ) : [];
		var hasInteracted = false;
		var nextFocusTarget = null;

		function getSelectedType() {
			if ( fixedType ) {
				return fixedType;
			}

			var checked = picker.querySelector( 'input[name="dak-visit-type"]:checked' );

			return checked ? checked.value : '';
		}

		function getSelectedClinicId() {
			if ( fixedClinicId ) {
				return fixedClinicId;
			}

			var checked = picker.querySelector( 'input[name="dak-clinic-choice"]:checked' );

			return checked ? checked.value : '';
		}

		function textOf( el, selector ) {
			var found = el ? el.querySelector( selector ) : null;

			return found ? found.textContent.trim() : '';
		}

		function updateSelectedRowClasses() {
			if ( ! picker ) {
				return;
			}

			Array.prototype.slice.call( picker.querySelectorAll( '.dak-profile-radio-row' ) ).forEach( function ( row ) {
				var input = row.querySelector( '.dak-profile-radio-input' );

				row.classList.toggle( 'is-selected', !! ( input && input.checked ) );
			} );
		}

		function render() {
			nextFocusTarget = null;

			var state = {
				resolved: false,
				summary: window.dakDoctorProfile && window.dakDoctorProfile.chooseTypeLabel ? window.dakDoctorProfile.chooseTypeLabel : 'Choose a visit type to get started.',
				feeLabel: '',
				bookingType: '',
				serviceId: '',
				clinicId: '',
			};

			if ( ! picker ) {
				applyState( state );
				return;
			}

			updateSelectedRowClasses();

			var type = getSelectedType();

			serviceGroups.forEach( function ( group ) {
				group.hidden = true;
			} );

			if ( clinicStatic ) {
				clinicStatic.hidden = 'clinic' !== type;
			}

			if ( videoStatic ) {
				videoStatic.hidden = 'video' !== type;
			}

			if ( 'video' === type ) {
				state.resolved = true;
				state.bookingType = 'video';
				state.summary = window.dakDoctorProfile && window.dakDoctorProfile.videoSummaryLabel ? window.dakDoctorProfile.videoSummaryLabel : 'Online Video Consultation';
				state.feeLabel = textOf( videoStatic, '.dak-profile-clinic-fee strong' );
			} else if ( 'clinic' === type ) {
				var clinicId = getSelectedClinicId();

				if ( ! clinicId ) {
					state.summary = window.dakDoctorProfile && window.dakDoctorProfile.chooseClinicLabel ? window.dakDoctorProfile.chooseClinicLabel : 'Choose a clinic to see its services and fees.';

					var clinicGroup = picker.querySelector( '[data-picker-group="clinic"]' );

					nextFocusTarget = clinicGroup ? clinicGroup.querySelector( 'input[type="radio"]' ) : null;
				} else {
					var activeGroup = serviceGroups.filter( function ( group ) {
						return group.getAttribute( 'data-clinic-id' ) === clinicId;
					} )[ 0 ];

					var clinicName = '';
					var clinicRadio = picker.querySelector( 'input[name="dak-clinic-choice"][value="' + cssEscape( clinicId ) + '"]' );

					if ( clinicRadio ) {
						clinicName = textOf( clinicRadio.closest( '.dak-profile-radio-row' ), '.dak-profile-clinic-info strong' );
					} else if ( clinicStatic ) {
						clinicName = textOf( clinicStatic, '.dak-profile-clinic-info strong' );
					}

					if ( activeGroup ) {
						activeGroup.hidden = false;

						var serviceChecked = activeGroup.querySelector( 'input[type="radio"]:checked' );

						if ( ! serviceChecked ) {
							state.summary = clinicName
								? ( window.dakDoctorProfile && window.dakDoctorProfile.chooseServiceAtLabel ? window.dakDoctorProfile.chooseServiceAtLabel.replace( '%s', clinicName ) : 'Choose a service at ' + clinicName + ' to see the fee.' )
								: ( window.dakDoctorProfile && window.dakDoctorProfile.chooseServiceLabel ? window.dakDoctorProfile.chooseServiceLabel : 'Choose a service to see the fee.' );

							var visibleOption = activeGroup.querySelector( '.dak-profile-service-option:not(.dak-hidden) input[type="radio"]' );

							nextFocusTarget = visibleOption || activeGroup.querySelector( 'input[type="radio"]' );
						} else {
							var serviceRow = serviceChecked.closest( '.dak-profile-radio-row' );
							var serviceName = textOf( serviceRow, '.dak-profile-clinic-info strong' );

							state.resolved = true;
							state.bookingType = 'clinic';
							state.serviceId = serviceChecked.value;
							state.clinicId = clinicId;
							state.feeLabel = textOf( serviceRow, '.dak-profile-clinic-fee strong' );
							state.summary = clinicName ? serviceName + ' at ' + clinicName : serviceName;
						}
					}
				}
			} else if ( fixedType ) {
				// A fixed type with neither branch matching means this doctor
				// has no bookable option at all (shouldn't normally happen —
				// the picker card itself is only rendered when at least one
				// of clinics/video exists) — leave the default "choose a
				// visit type" copy as a harmless fallback.
			} else {
				var visitTypeGroup = picker.querySelector( '[data-picker-group="visit-type"]' );

				nextFocusTarget = visitTypeGroup ? visitTypeGroup.querySelector( 'input[type="radio"]' ) : null;
			}

			applyState( state );
		}

		function applyState( state ) {
			ctaButtons.forEach( function ( button ) {
				var label = button.querySelector( '.dak-profile-cta-label' );

				if ( state.resolved ) {
					if ( label ) {
						label.textContent = window.dakDoctorProfile && window.dakDoctorProfile.chooseDateTimeLabel ? window.dakDoctorProfile.chooseDateTimeLabel : 'Choose date & time';
					}

					button.setAttribute( 'data-dak-book-appointment', '' );
					button.setAttribute( 'data-doctor-id', doctorId );
					button.setAttribute( 'data-doctor-name', doctorName );
					button.setAttribute( 'data-booking-type', state.bookingType );
					button.setAttribute( 'data-service-id', state.serviceId );
					button.setAttribute( 'data-clinic-id', state.clinicId );
				} else {
					if ( label ) {
						label.textContent = window.dakDoctorProfile && window.dakDoctorProfile.chooseConsultationLabel ? window.dakDoctorProfile.chooseConsultationLabel : 'Choose consultation';
					}

					button.removeAttribute( 'data-dak-book-appointment' );
					button.removeAttribute( 'data-booking-type' );
					button.removeAttribute( 'data-service-id' );
					button.removeAttribute( 'data-clinic-id' );
				}
			} );

			var summaryText = state.resolved && state.feeLabel ? state.summary + ' — ' + state.feeLabel : state.summary;

			if ( sidebarHint ) {
				sidebarHint.textContent = summaryText;
			}

			if ( mobileSummary ) {
				mobileSummary.textContent = summaryText;
			}

			if ( feeBox && feeLabelEl && feeAmountEl ) {
				if ( state.resolved && state.feeLabel ) {
					feeLabelEl.textContent = window.dakDoctorProfile && window.dakDoctorProfile.feeLabel ? window.dakDoctorProfile.feeLabel : 'Fee';
					feeAmountEl.textContent = state.feeLabel;
					feeBox.classList.remove( 'dak-hidden' );
				} else if ( hasInteracted ) {
					// Once the visitor has actually touched a radio, the
					// server-rendered "Consultation from X" pre-selection
					// teaser no longer applies — hide it rather than leave a
					// stale figure up once they've moved past it (e.g. picked
					// a different clinic and haven't chosen a service there
					// yet). Left alone entirely before any interaction.
					feeBox.classList.add( 'dak-hidden' );
				}
			}
		}

		function scrollToNextStep() {
			if ( ! nextFocusTarget ) {
				if ( picker ) {
					picker.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				}

				return;
			}

			var container = nextFocusTarget.closest( 'fieldset' ) || nextFocusTarget;

			container.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			nextFocusTarget.focus( { preventScroll: true } );
		}

		if ( picker ) {
			picker.addEventListener( 'change', function ( event ) {
				if ( event.target && 'radio' === event.target.type ) {
					hasInteracted = true;
					render();
				}
			} );
		}

		ctaButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				// Once resolved the button carries data-dak-book-appointment
				// and the site-wide listener in doctor-ak-booking-redirect.js
				// (a separate, document-level click listener) handles the
				// actual navigation — nothing to do here but let that bubble.
				if ( button.hasAttribute( 'data-dak-book-appointment' ) ) {
					return;
				}

				event.preventDefault();
				scrollToNextStep();
			} );
		} );

		render();
	}
} )();
