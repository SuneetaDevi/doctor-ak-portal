/**
 * Doctor AK Portal — Registration form behaviour.
 *
 * Everything here is vanilla JS (no jQuery/Select2 dependency) and talks to
 * WordPress exclusively through admin-ajax.php using the localized
 * `dakRegister` object (ajaxUrl + nonce).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initRoleToggle();
		initDayPills();
		initCheckCards();
		initProfilePictureUpload();
		initMultiSelect( document.getElementById( 'dak-specializations' ) );
		initRegistrationForm();

		if ( window.dakCityArea && window.dakRegister ) {
			window.dakCityArea.wire(
				document.getElementById( 'dak-city' ),
				document.getElementById( 'dak-area' ),
				window.dakRegister.locations
			);
		}
	} );

	/**
	 * Shows/hides the doctor vs. patient field groups based on the
	 * selected "Register As" radio, highlights the selected role card, and
	 * updates the submit button's label to match.
	 */
	function initRoleToggle() {
		var radios = document.querySelectorAll( 'input[name="register_as"]' );
		var doctorFields = document.getElementById( 'dak-doctor-fields' );
		var patientFields = document.getElementById( 'dak-patient-fields' );
		var submitLabel = document.querySelector( '#dak-register-submit .dak-button-label' );

		if ( ! radios.length || ! doctorFields || ! patientFields ) {
			return;
		}

		function setFieldsDisabled( container, disabled ) {
			container.querySelectorAll( 'input, select, textarea' ).forEach( function ( field ) {
				field.disabled = disabled;
			} );
		}

		function update() {
			var selected = document.querySelector( 'input[name="register_as"]:checked' );
			var value = selected ? selected.value : 'doctor';

			doctorFields.classList.toggle( 'dak-hidden', 'doctor' !== value );
			patientFields.classList.toggle( 'dak-hidden', 'patient' !== value );

			// Hidden role fields share input names ("first_name", "email", etc.)
			// with the visible ones; disabling them keeps FormData from picking
			// up the empty hidden copy and overwriting the filled-in one.
			setFieldsDisabled( doctorFields, 'doctor' !== value );
			setFieldsDisabled( patientFields, 'patient' !== value );

			document.querySelectorAll( '.dak-role-card' ).forEach( function ( card ) {
				card.classList.toggle( 'is-selected', card.getAttribute( 'data-role' ) === value );
			} );

			if ( submitLabel ) {
				var labelAttr = 'doctor' === value ? 'data-doctor-label' : 'data-patient-label';
				submitLabel.textContent = submitLabel.getAttribute( labelAttr ) || submitLabel.textContent;
			}
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', update );
		} );

		update();
	}

	/**
	 * Wires the Weekly Availability day pills: clicking a pill toggles its
	 * hidden checkbox and a visual "selected" state.
	 */
	function initDayPills() {
		document.querySelectorAll( '.dak-day-pill' ).forEach( function ( pill ) {
			var checkbox = pill.querySelector( 'input[type="checkbox"]' );

			if ( ! checkbox ) {
				return;
			}

			checkbox.addEventListener( 'change', function () {
				pill.classList.toggle( 'is-selected', checkbox.checked );
			} );
		} );
	}

	/**
	 * Wires card-style checkboxes (e.g. "I offer video consultations") so
	 * the whole card reflects the checked state visually.
	 */
	function initCheckCards() {
		document.querySelectorAll( '.dak-check-card' ).forEach( function ( card ) {
			var checkbox = card.querySelector( 'input[type="checkbox"]' );

			if ( ! checkbox ) {
				return;
			}

			function sync() {
				card.classList.toggle( 'is-selected', checkbox.checked );
			}

			checkbox.addEventListener( 'change', sync );
			sync();
		} );
	}

	/**
	 * Enables/disables each day's start & end time inputs based on its
	 * "enabled" checkbox.
	 */
	function initAvailabilityToggles() {
		document.querySelectorAll( '.dak-availability-row' ).forEach( function ( row ) {
			var toggle = row.querySelector( '.dak-availability-toggle' );
			var start = row.querySelector( '.dak-availability-start' );
			var end = row.querySelector( '.dak-availability-end' );

			if ( ! toggle || ! start || ! end ) {
				return;
			}

			function sync() {
				start.disabled = ! toggle.checked;
				end.disabled = ! toggle.checked;
			}

			toggle.addEventListener( 'change', sync );
			sync();
		} );
	}

	/**
	 * Uploads the chosen profile picture immediately via AJAX so the form
	 * can preview it and store the resulting attachment ID.
	 */
	function initProfilePictureUpload() {
		var input = document.getElementById( 'dak-profile-picture-input' );
		var preview = document.getElementById( 'dak-profile-picture-preview' );
		var hiddenId = document.getElementById( 'dak-profile-picture-id' );
		var status = document.getElementById( 'dak-upload-status' );

		if ( ! input || ! preview || ! hiddenId || ! status ) {
			return;
		}

		input.addEventListener( 'change', function () {
			var file = input.files[0];

			if ( ! file ) {
				return;
			}

			status.textContent = 'Uploading…';
			hiddenId.value = '';

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_upload_profile_picture' );
			formData.append( 'nonce', dakRegister.nonce );
			formData.append( 'profile_picture', file );

			fetch( dakRegister.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						hiddenId.value = result.data.attachment_id;
						preview.innerHTML = '';

						var img = document.createElement( 'img' );
						img.src = result.data.url;
						img.alt = '';
						preview.appendChild( img );

						status.textContent = '';
					} else {
						status.textContent = ( result.data && result.data.message ) || 'Upload failed. Please try again.';
					}
				} )
				.catch( function () {
					status.textContent = 'Upload failed. Please try again.';
				} );
		} );
	}

	/**
	 * Progressively enhances a native <select multiple> into a searchable,
	 * chip-based multi-select. Built in-house (no external library) so the
	 * plugin has no third-party JS dependency; the underlying <select>
	 * remains the source of truth for form submission and still works
	 * unassisted if this script fails to load.
	 *
	 * @param {HTMLSelectElement} select Native multi-select element.
	 * @param {Object}            [opts] Optional settings.
	 * @param {boolean}           [opts.allowCustom] When true, typing a value
	 *   that doesn't match any existing option and pressing Tab/Enter adds it
	 *   as a brand-new option (and chip) instead of being ignored — used for
	 *   the admin's Add/Edit Doctor screen, where an admin may need to tag a
	 *   specialization that isn't in the canonical dropdown yet.
	 */
	function initMultiSelect( select, opts ) {
		if ( ! select ) {
			return;
		}

		opts = opts || {};

		var wrapper = document.createElement( 'div' );
		wrapper.className = 'dak-multiselect';

		var control = document.createElement( 'div' );
		control.className = 'dak-multiselect-control';
		control.setAttribute( 'tabindex', '0' );

		var chipsContainer = document.createElement( 'div' );
		chipsContainer.className = 'dak-multiselect-chips';

		var placeholder = document.createElement( 'span' );
		placeholder.className = 'dak-multiselect-placeholder';
		placeholder.textContent = 'Select specializations…';

		control.appendChild( chipsContainer );
		control.appendChild( placeholder );

		var dropdown = document.createElement( 'div' );
		dropdown.className = 'dak-multiselect-dropdown dak-hidden';

		var search = document.createElement( 'input' );
		search.type = 'text';
		search.className = 'dak-multiselect-search';
		search.setAttribute( 'placeholder', opts.allowCustom ? 'Search or type to add new…' : 'Search…' );

		var optionsList = document.createElement( 'ul' );
		optionsList.className = 'dak-multiselect-options';

		function addOptionRow( option ) {
			var li = document.createElement( 'li' );
			li.className = 'dak-multiselect-option';
			li.setAttribute( 'data-value', option.value );
			li.textContent = option.textContent;

			if ( option.selected ) {
				li.classList.add( 'is-selected' );
			}

			li.addEventListener( 'click', function () {
				option.selected = ! option.selected;
				li.classList.toggle( 'is-selected', option.selected );
				renderChips();
				select.dispatchEvent( new Event( 'change' ) );
			} );

			optionsList.appendChild( li );

			return li;
		}

		Array.prototype.forEach.call( select.options, addOptionRow );

		/**
		 * Adds a brand-new <option> (not part of the original dropdown) to
		 * the underlying select, selected, plus its matching row — only
		 * reachable when opts.allowCustom is true.
		 *
		 * @param {string} label Raw typed text to use as both the option's
		 *   value and its display label.
		 * @return {void}
		 */
		function addCustomOption( label ) {
			var alreadyExists = Array.prototype.some.call( select.options, function ( option ) {
				return option.textContent.toLowerCase() === label.toLowerCase();
			} );

			if ( alreadyExists ) {
				return;
			}

			var option = document.createElement( 'option' );
			option.value = label;
			option.textContent = label;
			option.selected = true;
			select.appendChild( option );

			addOptionRow( option );
			renderChips();
			select.dispatchEvent( new Event( 'change' ) );
		}

		dropdown.appendChild( search );
		dropdown.appendChild( optionsList );

		wrapper.appendChild( control );
		wrapper.appendChild( dropdown );

		select.classList.add( 'dak-visually-hidden' );
		select.parentNode.insertBefore( wrapper, select );

		function renderChips() {
			chipsContainer.innerHTML = '';

			var selectedOptions = Array.prototype.filter.call( select.options, function ( option ) {
				return option.selected;
			} );

			placeholder.classList.toggle( 'dak-hidden', selectedOptions.length > 0 );

			selectedOptions.forEach( function ( option ) {
				var chip = document.createElement( 'span' );
				chip.className = 'dak-chip';
				chip.textContent = option.textContent;

				var remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.className = 'dak-chip-remove';
				remove.setAttribute( 'aria-label', 'Remove ' + option.textContent );
				remove.textContent = '×';

				remove.addEventListener( 'click', function ( event ) {
					event.stopPropagation();
					option.selected = false;
					deselectOptionInList( option.value );
					renderChips();
					select.dispatchEvent( new Event( 'change' ) );
				} );

				chip.appendChild( remove );
				chipsContainer.appendChild( chip );
			} );
		}

		function deselectOptionInList( value ) {
			Array.prototype.forEach.call( optionsList.children, function ( li ) {
				if ( li.getAttribute( 'data-value' ) === value ) {
					li.classList.remove( 'is-selected' );
				}
			} );
		}

		control.addEventListener( 'click', function () {
			var isHidden = dropdown.classList.contains( 'dak-hidden' );
			dropdown.classList.toggle( 'dak-hidden' );

			if ( isHidden ) {
				search.value = '';
				Array.prototype.forEach.call( optionsList.children, function ( li ) {
					li.classList.remove( 'dak-hidden' );
				} );
				search.focus();
			}
		} );

		search.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
		} );

		search.addEventListener( 'input', function () {
			var query = search.value.toLowerCase();

			Array.prototype.forEach.call( optionsList.children, function ( li ) {
				var matches = li.textContent.toLowerCase().indexOf( query ) !== -1;
				li.classList.toggle( 'dak-hidden', ! matches );
			} );
		} );

		// Tag-style shortcut: type a few letters, press Tab (or Enter) to
		// turn the top matching option into a chip immediately, without
		// having to reach for the mouse — same idea as an email "To:" field.
		// When opts.allowCustom is set and nothing matches, the typed text
		// itself becomes a brand-new tag instead of being dropped.
		search.addEventListener( 'keydown', function ( event ) {
			if ( 'Tab' !== event.key && 'Enter' !== event.key ) {
				return;
			}

			var query = search.value.trim();

			if ( '' === query ) {
				return;
			}

			var topMatch = Array.prototype.filter.call( optionsList.children, function ( li ) {
				return ! li.classList.contains( 'dak-hidden' ) && ! li.classList.contains( 'is-selected' );
			} )[ 0 ];

			if ( topMatch ) {
				event.preventDefault();
				topMatch.click();
			} else if ( opts.allowCustom ) {
				event.preventDefault();
				addCustomOption( query );
			} else {
				return;
			}

			search.value = '';
			Array.prototype.forEach.call( optionsList.children, function ( li ) {
				li.classList.remove( 'dak-hidden' );
			} );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! wrapper.contains( event.target ) ) {
				dropdown.classList.add( 'dak-hidden' );
			}
		} );

		renderChips();
	}

	/**
	 * Submits the registration form over AJAX and renders field-level and
	 * general errors returned by the server.
	 */
	function initRegistrationForm() {
		var form = document.getElementById( 'dak-registration-form' );

		if ( ! form ) {
			return;
		}

		var submitBtn = document.getElementById( 'dak-register-submit' );
		var successAlert = document.getElementById( 'dak-register-success' );
		var errorAlert = document.getElementById( 'dak-register-general-error' );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			clearFieldErrors( form );
			successAlert.classList.add( 'dak-hidden' );
			errorAlert.classList.add( 'dak-hidden' );

			var password = form.querySelector( '[name="password"]' ).value;
			var confirmPassword = form.querySelector( '[name="confirm_password"]' ).value;

			if ( password !== confirmPassword ) {
				showFieldError( form, 'confirm_password', 'Passwords do not match.' );
				return;
			}

			submitBtn.disabled = true;
			submitBtn.classList.add( 'is-loading' );

			var formData = new FormData( form );
			formData.append( 'action', 'doctor_ak_register' );
			formData.append( 'nonce', dakRegister.nonce );

			fetch( dakRegister.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					submitBtn.disabled = false;
					submitBtn.classList.remove( 'is-loading' );

					if ( result.success ) {
						successAlert.textContent = result.data.message;
						successAlert.classList.remove( 'dak-hidden' );
						form.reset();

						// form.reset() clears checkbox/radio state natively, but
						// not the JS-driven visual classes layered on top of them.
						document.querySelectorAll( '.dak-day-pill.is-selected' ).forEach( function ( pill ) {
							pill.classList.remove( 'is-selected' );
						} );
						document.querySelectorAll( '.dak-check-card.is-selected' ).forEach( function ( card ) {
							card.classList.remove( 'is-selected' );
						} );
						document.getElementById( 'dak-profile-picture-id' ).value = '';
						document.getElementById( 'dak-profile-picture-preview' ).innerHTML =
							document.getElementById( 'dak-profile-picture-preview' ).getAttribute( 'data-empty-html' ) || '';
					} else if ( result.data && result.data.errors ) {
						Object.keys( result.data.errors ).forEach( function ( field ) {
							showFieldError( form, field, result.data.errors[ field ] );
						} );
						errorAlert.textContent = 'Please correct the errors below and try again.';
						errorAlert.classList.remove( 'dak-hidden' );
					} else {
						errorAlert.textContent = ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
						errorAlert.classList.remove( 'dak-hidden' );
					}
				} )
				.catch( function () {
					submitBtn.disabled = false;
					submitBtn.classList.remove( 'is-loading' );
					errorAlert.textContent = 'Something went wrong. Please try again.';
					errorAlert.classList.remove( 'dak-hidden' );
				} );
		} );
	}

	/**
	 * Displays a message under the given field's error slot.
	 *
	 * @param {HTMLFormElement} form    Registration form.
	 * @param {string}          field   Field name matching `data-field`.
	 * @param {string}          message Error message to display.
	 */
	function showFieldError( form, field, message ) {
		// The doctor and patient sections both have a field with this name
		// (e.g. "email"), so querySelector() alone would always grab the
		// first one in DOM order — the doctor section's, even while the
		// patient section is the one showing. Prefer whichever match sits
		// in the currently active (non dak-hidden) role section. Note:
		// checking the error span's own offsetParent doesn't work here —
		// every .dak-field-error starts as display:none via its own CSS
		// rule (until .is-visible is added), so both candidates would
		// report a null offsetParent regardless of which section is shown.
		var candidates = form.querySelectorAll( '.dak-field-error[data-field="' + field + '"]' );
		var el = null;

		candidates.forEach( function ( candidate ) {
			if ( ! el && ! candidate.closest( '.dak-hidden' ) ) {
				el = candidate;
			}
		} );

		if ( ! el && candidates.length ) {
			el = candidates[ 0 ];
		}

		if ( el ) {
			el.textContent = message;
			el.classList.add( 'is-visible' );
		}
	}

	/**
	 * Clears every field-level error message in the form.
	 *
	 * @param {HTMLFormElement} form Registration form.
	 */
	function clearFieldErrors( form ) {
		form.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
			el.textContent = '';
			el.classList.remove( 'is-visible' );
		} );
	}

	// Expose reusable pieces so other pages (e.g. the profile editor in
	// Phase 6) can use the same multi-select/availability/error-display
	// behaviour without duplicating this code.
	window.DoctorAKPortal = window.DoctorAKPortal || {};
	window.DoctorAKPortal.initMultiSelect = initMultiSelect;
	window.DoctorAKPortal.initAvailabilityToggles = initAvailabilityToggles;
	window.DoctorAKPortal.showFieldError = showFieldError;
	window.DoctorAKPortal.clearFieldErrors = clearFieldErrors;
} )();
