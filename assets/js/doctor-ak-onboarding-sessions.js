/**
 * Doctor AK Portal — Add/Edit Doctor form's "Weekly Hours" onboarding step.
 *
 * Builds one clinic-sessions card per clinic picked in "Align to Clinics"
 * (#dak-admin-user-clinic-locations), reusing the same day/period grid
 * markup/classes the standalone Doctor Sessions form uses (see
 * admin-session-form-screen.php / doctor-ak-clinics.css) so it looks and
 * behaves identically. Every input carries a real `name` attribute
 * (`clinic_sessions[<clinic_location_id>][day][period][field]`), so the
 * form's own `new FormData(form)` submit (see doctor-ak-admin-dashboard.js)
 * picks these up automatically — no extra serialization step needed.
 *
 * The clinic multi-select is itself progressively enhanced by
 * initMultiSelect() (see doctor-ak-registration.js), which dispatches a
 * plain 'change' event on the underlying <select> whenever a chip is
 * added/removed — that's what triggers rebuilding the groups below.
 *
 * When editing an existing doctor, their already-aligned clinics come
 * pre-selected in that <select> (see admin-user-form-screen.php), and each
 * group is pre-filled from #dak-admin-user-sessions-groups's `data-existing`
 * JSON (clinic_location_id => { id, doctor_share_percent, sessions }) the
 * moment it's first built, so editing shows what's already set instead of a
 * blank grid. Each group also carries a hidden clinic_row_id[...] field
 * ('0' for a not-yet-created clinic) so the save handler knows whether to
 * update that existing row or create a new one.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var select = document.getElementById( 'dak-admin-user-clinic-locations' );
		var groupsContainer = document.getElementById( 'dak-admin-user-sessions-groups' );
		var emptyHint = document.getElementById( 'dak-admin-user-sessions-empty' );

		if ( ! select || ! groupsContainer || ! window.dakAdminUsers ) {
			return;
		}

		var sessionDays = window.dakAdminUsers.sessionDays || {};
		var sessionPeriods = window.dakAdminUsers.sessionPeriods || {};

		if ( ! Object.keys( sessionDays ).length || ! Object.keys( sessionPeriods ).length ) {
			return;
		}

		// Editing an existing doctor: clinic_location_id => { id,
		// doctor_share_percent, sessions } for their already-aligned
		// clinics (see admin-user-form-screen.php) — used to pre-fill each
		// group the moment it's first built, so editing shows what's
		// already set instead of a blank grid.
		var existingByClinicId = {};

		try {
			existingByClinicId = JSON.parse( groupsContainer.getAttribute( 'data-existing' ) || '{}' );
		} catch ( e ) {
			existingByClinicId = {};
		}

		function refresh() {
			var selectedOptions = Array.prototype.filter.call( select.options, function ( option ) {
				return option.selected;
			} );
			var selectedIds = selectedOptions.map( function ( option ) {
				return option.value;
			} );

			// Drop groups for clinics no longer selected.
			Array.prototype.slice.call( groupsContainer.children ).forEach( function ( group ) {
				if ( -1 === selectedIds.indexOf( group.getAttribute( 'data-clinic-location-id' ) ) ) {
					group.remove();
				}
			} );

			// Add a group for any newly-selected clinic that doesn't have
			// one yet — existing groups (and whatever hours are already
			// filled in) are left untouched.
			selectedOptions.forEach( function ( option ) {
				if ( ! groupsContainer.querySelector( '[data-clinic-location-id="' + cssEscape( option.value ) + '"]' ) ) {
					groupsContainer.appendChild( buildGroup( option.value, option.textContent ) );
				}
			} );

			if ( emptyHint ) {
				emptyHint.classList.toggle( 'dak-hidden', selectedOptions.length > 0 );
			}
		}

		function cssEscape( value ) {
			return window.CSS && window.CSS.escape ? window.CSS.escape( value ) : value;
		}

		function buildGroup( clinicLocationId, clinicLabel ) {
			var existing = existingByClinicId[ clinicLocationId ] || null;

			var group = document.createElement( 'div' );
			group.className = 'dak-onboarding-clinic-sessions';
			group.setAttribute( 'data-clinic-location-id', clinicLocationId );

			var title = document.createElement( 'h3' );
			title.className = 'dak-onboarding-clinic-sessions-title';
			title.textContent = clinicLabel;

			var rowIdInput = document.createElement( 'input' );
			rowIdInput.type = 'hidden';
			rowIdInput.name = 'clinic_row_id[' + clinicLocationId + ']';
			rowIdInput.value = existing ? existing.id : 0;

			group.appendChild( title );
			group.appendChild( rowIdInput );
			group.appendChild( buildShareOverride( clinicLocationId, existing ) );
			group.appendChild( buildQuickFill( group ) );
			group.appendChild( buildGrid( clinicLocationId, existing ) );

			return group;
		}

		/**
		 * Per-clinic doctor-share override — mirrors the standalone Doctor
		 * Sessions form's own "Doctor's share override at this clinic"
		 * field (see admin-session-form-screen.php), just keyed by clinic
		 * here since several clinics can be on this page at once.
		 */
		function buildShareOverride( clinicLocationId, existing ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'dak-field dak-onboarding-share-override';

			var label = document.createElement( 'label' );
			var inputId = 'dak-admin-user-clinic-share-' + clinicLocationId;
			label.setAttribute( 'for', inputId );
			label.textContent = window.dakAdminUsers.shareOverrideLabel || "Doctor's share override at this clinic (%, optional)";

			var input = document.createElement( 'input' );
			input.type = 'number';
			input.id = inputId;
			input.name = 'clinic_share_percent[' + clinicLocationId + ']';
			input.min = '0';
			input.max = '100';
			input.step = '0.01';

			if ( existing && null !== existing.doctor_share_percent && undefined !== existing.doctor_share_percent ) {
				input.value = existing.doctor_share_percent;
			}

			var hint = document.createElement( 'p' );
			hint.className = 'dak-field-hint';
			hint.textContent = window.dakAdminUsers.shareOverrideHint || "Leave blank to use this doctor's default commission below. Only applies to appointments booked at this specific clinic.";

			wrap.appendChild( label );
			wrap.appendChild( input );
			wrap.appendChild( hint );

			return wrap;
		}

		/**
		 * A small "fill several days at once" toolbar above each clinic's
		 * grid — sets one Start/End/Slot-duration on the Morning period of
		 * every day it's applied to, so the common "same hours all week"
		 * case doesn't need 7 rows filled in by hand.
		 */
		function buildQuickFill( group ) {
			var toolbar = document.createElement( 'div' );
			toolbar.className = 'dak-onboarding-quick-fill';

			var startField = quickFillField( window.dakAdminUsers.quickFillStartLabel || 'Start', 'time' );
			var endField = quickFillField( window.dakAdminUsers.quickFillEndLabel || 'End', 'time' );
			var durationField = quickFillField( window.dakAdminUsers.quickFillSlotLabel || 'Slot (min)', 'number' );
			var durationInput = durationField.querySelector( 'input' );
			durationInput.min = '5';
			durationInput.max = '240';
			durationInput.value = '20';

			toolbar.appendChild( startField );
			toolbar.appendChild( endField );
			toolbar.appendChild( durationField );

			var actions = document.createElement( 'div' );
			actions.className = 'dak-onboarding-quick-fill-actions';

			var weekdaySlugs = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ].filter( function ( day ) {
				return day in sessionDays;
			} );

			if ( weekdaySlugs.length ) {
				actions.appendChild(
					quickFillButton( window.dakAdminUsers.quickFillWeekdaysLabel || 'Apply Mon–Fri', function () {
						applyQuickFill( group, weekdaySlugs, startField, endField, durationField );
					} )
				);
			}

			actions.appendChild(
				quickFillButton( window.dakAdminUsers.quickFillAllDaysLabel || 'Apply to all days', function () {
					applyQuickFill( group, Object.keys( sessionDays ), startField, endField, durationField );
				} )
			);

			toolbar.appendChild( actions );

			return toolbar;
		}

		function quickFillField( labelText, type ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'dak-onboarding-quick-fill-field';

			var label = document.createElement( 'label' );
			label.textContent = labelText;

			var input = document.createElement( 'input' );
			input.type = type;

			wrap.appendChild( label );
			wrap.appendChild( input );

			return wrap;
		}

		function quickFillButton( labelText, onClick ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'dak-button dak-button-secondary dak-button-sm';
			button.textContent = labelText;
			button.addEventListener( 'click', onClick );

			return button;
		}

		function applyQuickFill( group, days, startField, endField, durationField ) {
			var start = startField.querySelector( 'input' ).value;
			var end = endField.querySelector( 'input' ).value;
			var duration = durationField.querySelector( 'input' ).value;

			if ( ! start || ! end || ! duration ) {
				window.alert( window.dakAdminUsers.quickFillIncompleteMessage || 'Fill in start, end, and slot duration first.' );
				return;
			}

			var firstPeriodSlug = Object.keys( sessionPeriods )[ 0 ];

			days.forEach( function ( day ) {
				var row = group.querySelector( '[data-day="' + day + '"] [data-period="' + firstPeriodSlug + '"]' );

				if ( ! row ) {
					return;
				}

				var toggle = row.querySelector( '.dak-availability-toggle' );
				var startInput = row.querySelector( '.dak-availability-start' );
				var endInput = row.querySelector( '.dak-availability-end' );
				var durationInput = row.querySelector( '.dak-clinic-slot-duration' );

				toggle.checked = true;
				startInput.value = start;
				startInput.disabled = false;
				endInput.value = end;
				endInput.disabled = false;
				durationInput.value = duration;
				durationInput.disabled = false;
			} );
		}

		function buildGrid( clinicLocationId, existing ) {
			var grid = document.createElement( 'div' );
			grid.className = 'dak-clinic-sessions-days';
			var existingSessions = existing ? existing.sessions : null;

			Object.keys( sessionDays ).forEach( function ( daySlug ) {
				var dayEl = document.createElement( 'div' );
				dayEl.className = 'dak-clinic-sessions-day';
				dayEl.setAttribute( 'data-day', daySlug );

				var dayLabel = document.createElement( 'span' );
				dayLabel.className = 'dak-clinic-sessions-day-label';
				dayLabel.textContent = sessionDays[ daySlug ];
				dayEl.appendChild( dayLabel );

				var periodsEl = document.createElement( 'div' );
				periodsEl.className = 'dak-clinic-sessions-periods';

				var existingDay = existingSessions && existingSessions[ daySlug ] ? existingSessions[ daySlug ] : null;

				Object.keys( sessionPeriods ).forEach( function ( periodSlug ) {
					var existingPeriod = existingDay && existingDay[ periodSlug ] ? existingDay[ periodSlug ] : null;
					periodsEl.appendChild( buildPeriodRow( clinicLocationId, daySlug, periodSlug, sessionPeriods[ periodSlug ], existingPeriod ) );
				} );

				dayEl.appendChild( periodsEl );
				grid.appendChild( dayEl );
			} );

			return grid;
		}

		function buildPeriodRow( clinicLocationId, daySlug, periodSlug, periodLabel, existingPeriod ) {
			var row = document.createElement( 'div' );
			row.className = 'dak-availability-row';
			row.setAttribute( 'data-period', periodSlug );

			var namePrefix = 'clinic_sessions[' + clinicLocationId + '][' + daySlug + '][' + periodSlug + ']';

			// A hidden field sharing the checkbox's exact name, placed
			// before it in the DOM — an unchecked checkbox submits nothing
			// at all, so this guarantees [enabled] is always present
			// ('' when off, overridden by the checkbox's '1' when on, since
			// a server reading raw POST data keeps the last value for a
			// repeated key).
			var enabledHidden = document.createElement( 'input' );
			enabledHidden.type = 'hidden';
			enabledHidden.name = namePrefix + '[enabled]';
			enabledHidden.value = '';

			var label = document.createElement( 'label' );
			label.className = 'dak-checkbox';

			var toggle = document.createElement( 'input' );
			toggle.type = 'checkbox';
			toggle.className = 'dak-availability-toggle';
			toggle.name = namePrefix + '[enabled]';
			toggle.value = '1';

			var toggleText = document.createElement( 'span' );
			toggleText.textContent = periodLabel;

			label.appendChild( toggle );
			label.appendChild( toggleText );

			var start = document.createElement( 'input' );
			start.type = 'time';
			start.className = 'dak-availability-start';
			start.name = namePrefix + '[start]';
			start.setAttribute( 'aria-label', periodLabel + ' start time' );
			start.disabled = true;

			var sep = document.createElement( 'span' );
			sep.className = 'dak-availability-sep';
			sep.textContent = '–';

			var end = document.createElement( 'input' );
			end.type = 'time';
			end.className = 'dak-availability-end';
			end.name = namePrefix + '[end]';
			end.setAttribute( 'aria-label', periodLabel + ' end time' );
			end.disabled = true;

			var duration = document.createElement( 'input' );
			duration.type = 'number';
			duration.className = 'dak-clinic-slot-duration';
			duration.name = namePrefix + '[slot_duration_minutes]';
			duration.min = '5';
			duration.max = '240';
			duration.placeholder = 'min';
			duration.setAttribute( 'aria-label', periodLabel + ' slot duration in minutes' );
			duration.disabled = true;

			toggle.addEventListener( 'change', function () {
				var disabled = ! toggle.checked;
				start.disabled = disabled;
				end.disabled = disabled;
				duration.disabled = disabled;
			} );

			if ( existingPeriod && existingPeriod.enabled ) {
				toggle.checked = true;
				start.value = existingPeriod.start || '';
				start.disabled = false;
				end.value = existingPeriod.end || '';
				end.disabled = false;
				duration.value = existingPeriod.slot_duration_minutes || '';
				duration.disabled = false;
			}

			row.appendChild( enabledHidden );
			row.appendChild( label );
			row.appendChild( start );
			row.appendChild( sep );
			row.appendChild( end );
			row.appendChild( duration );

			return row;
		}

		select.addEventListener( 'change', refresh );
		refresh();
	} );
} )();
