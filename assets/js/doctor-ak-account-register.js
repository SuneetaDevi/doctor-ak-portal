/**
 * Doctor AK Portal — Registration page flow ([doctor_register]).
 *
 * - Patient / Doctor role cards. Switching keeps the name, email and phone
 *   (in memory only), clears both password fields and the terms tick, and
 *   disables the other role's fields so they're neither validated nor sent.
 * - Patient sign-up is one page; doctor sign-up is three steps with a review
 *   before submitting. "Continue" only checks the current step — nothing is
 *   sent until the final submit, and the server validates everything again.
 * - Inline errors linked to their fields, a compact summary for the final
 *   submit, a loading label, and no duplicate requests.
 *
 * Nothing here writes to localStorage/sessionStorage or the URL. Shared
 * pieces (the tag picker) come from doctor-ak-registration.js.
 */
( function () {
	'use strict';

	var MIN_PASSWORD = 8;            // Registration_Handler::handle_register().
	var MAX_PHOTO_BYTES = 5 * 1024 * 1024; // Profile_Picture_Uploader::MAX_FILE_SIZE.
	var PHOTO_TYPES = [ 'image/jpeg', 'image/png', 'image/webp' ];
	var GENERIC_ERROR = 'We couldn’t submit your registration just now. Please check your connection and try again.';
	var STEP_NAMES = { 1: 'Account details', 2: 'Professional profile', 3: 'Location and review' };

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '[data-dak-register]' );
		var form = document.getElementById( 'dak-registration-form' );

		if ( ! root || ! form || ! window.dakRegister ) {
			return;
		}

		var containers = {
			patient: root.querySelector( '[data-role-fields="patient"]' ),
			doctor: root.querySelector( '[data-role-fields="doctor"]' ),
		};
		// Password (doctor step 1) and consent (doctor step 3) are shared by
		// both roles and moved into the active role's slots.
		var pwBlock = root.querySelector( '[data-password-block]' );
		var termsBlock = root.querySelector( '[data-terms-block]' );
		var pwSlots = {
			patient: root.querySelector( '[data-password-slot="patient"]' ),
			doctor: root.querySelector( '[data-password-slot="doctor"]' ),
		};
		var termsSlots = {
			patient: root.querySelector( '[data-terms-slot="patient"]' ),
			doctor: root.querySelector( '[data-terms-slot="doctor"]' ),
		};
		var title = document.getElementById( 'dak-register-title' );
		var intro = document.getElementById( 'dak-register-intro' );
		var summary = document.getElementById( 'dak-register-general-error' );
		var backBtn = root.querySelector( '[data-step-back]' );
		var nextBtn = root.querySelector( '[data-step-next]' );
		var submitBtn = document.getElementById( 'dak-register-submit' );
		var stepStatus = root.querySelector( '[data-step-status]' );
		var review = root.querySelector( '[data-review]' );

		var role = root.getAttribute( 'data-initial-role' ) === 'doctor' ? 'doctor' : 'patient';
		var step = 1;
		var busy = false;
		var photoUploading = false;

		/* ------------------------------------------------------------------
		 * Small helpers
		 * ---------------------------------------------------------------- */

		function scopeEls() {
			return [ containers[ role ], pwBlock, termsBlock ];
		}

		function qs( selector ) {
			var found = null;

			scopeEls().some( function ( scope ) {
				found = scope.querySelector( selector );
				return !! found;
			} );

			return found;
		}

		function fieldValue( name ) {
			var el = qs( '[name="' + name + '"]' );
			return el ? String( el.value || '' ).trim() : '';
		}

		function setLoading( loading ) {
			var label = submitBtn.querySelector( '.dak-button-label' );

			if ( loading ) {
				submitBtn.setAttribute( 'data-idle-label', label.textContent );
				label.textContent = submitBtn.getAttribute( 'data-loading-label' );
			} else if ( submitBtn.getAttribute( 'data-idle-label' ) ) {
				label.textContent = submitBtn.getAttribute( 'data-idle-label' );
			}

			submitBtn.disabled = loading;
			backBtn.disabled = loading;
			submitBtn.classList.toggle( 'is-loading', loading );
			submitBtn.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		}

		/** The element that should receive focus/aria-invalid for a field. */
		function inputFor( field ) {
			if ( 'date_of_birth' === field ) {
				return qs( '[data-dob-day]' );
			}

			if ( 'profile_picture' === field ) {
				return document.getElementById( 'dak-profile-picture-input' );
			}

			if ( 'specializations' === field || 'keywords' === field ) {
				var select = qs( '[name="' + field + '[]"]' );
				var wrapper = select ? select.previousElementSibling : null;
				return wrapper && wrapper.classList.contains( 'dak-multiselect' ) ? wrapper.querySelector( '.dak-multiselect-control' ) : select;
			}

			if ( 'terms_accepted' === field ) {
				return document.getElementById( 'dak-terms-accepted' );
			}

			return qs( '[name="' + field + '"]' );
		}

		function showError( field, message ) {
			var slot = qs( '.dak-field-error[data-field="' + field + '"]' );
			var input = inputFor( field );

			if ( slot ) {
				slot.textContent = message;
				slot.classList.toggle( 'is-visible', '' !== message );
			}

			if ( input ) {
				input.setAttribute( 'aria-invalid', '' !== message ? 'true' : 'false' );
				var group = input.closest( '.dak-field' );

				if ( group ) {
					group.classList.toggle( 'has-error', '' !== message );
				}
			}
		}

		function clearErrors( scope ) {
			( scope ? [ scope ] : [ containers.patient, containers.doctor, pwBlock, termsBlock ] ).forEach( function ( s ) {
				s.querySelectorAll( '.dak-field-error' ).forEach( function ( el ) {
					el.textContent = '';
					el.classList.remove( 'is-visible' );
				} );
				s.querySelectorAll( '[aria-invalid="true"]' ).forEach( function ( el ) {
					el.setAttribute( 'aria-invalid', 'false' );
				} );
				s.querySelectorAll( '.dak-field.has-error' ).forEach( function ( el ) {
					el.classList.remove( 'has-error' );
				} );
			} );
		}

		function hideSummary() {
			summary.classList.add( 'dak-hidden' );
		}

		/**
		 * Shows the compact error summary: a title plus (optionally) links to
		 * each problem field.
		 *
		 * @param {string} heading
		 * @param {Object} [errors] field => message
		 */
		function showSummary( heading, errors ) {
			var titleEl = summary.querySelector( '[data-summary-title]' );
			var list = summary.querySelector( '[data-summary-list]' );

			titleEl.textContent = heading;
			list.textContent = '';

			Object.keys( errors || {} ).forEach( function ( field ) {
				var target = inputFor( field );
				var li = document.createElement( 'li' );

				if ( target && target.id ) {
					var a = document.createElement( 'a' );
					a.href = '#' + target.id;
					a.textContent = errors[ field ];
					a.addEventListener( 'click', function ( event ) {
						event.preventDefault();
						goToField( field );
					} );
					li.appendChild( a );
				} else {
					li.textContent = errors[ field ];
				}

				list.appendChild( li );
			} );

			list.hidden = ! list.children.length;
			summary.classList.remove( 'dak-hidden' );
			summary.focus();
		}

		/* ------------------------------------------------------------------
		 * Validation (mirrors Registration_Handler — the server re-checks)
		 * ---------------------------------------------------------------- */

		var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

		function validateIdentity( errors ) {
			if ( ! fieldValue( 'first_name' ) ) {
				errors.first_name = 'Enter your first name.';
			}

			if ( ! fieldValue( 'last_name' ) ) {
				errors.last_name = 'Enter your last name.';
			}

			var email = fieldValue( 'email' );

			if ( ! email ) {
				errors.email = 'Enter your email address.';
			} else if ( ! EMAIL_RE.test( email ) ) {
				errors.email = 'Enter an email address like name@example.com.';
			}

			var digits = fieldValue( 'phone_number' ).replace( /\D/g, '' );

			if ( ! digits ) {
				errors.phone_number = 'Enter your phone number.';
			} else if ( digits.length < 6 || digits.length > 14 ) {
				errors.phone_number = 'Enter a phone number of 6 to 14 digits, without the country code.';
			}
		}

		function validateDob( errors ) {
			var result = composeDob();

			if ( result.error ) {
				errors.date_of_birth = result.error;
			}
		}

		function validateDoctorStep( n, errors ) {
			if ( 1 === n ) {
				validateIdentity( errors );

				if ( ! fieldValue( 'gender' ) ) {
					errors.gender = 'Select your gender.';
				}

				validatePassword( errors );
			}

			if ( 3 === n ) {
				if ( ! fieldValue( 'country' ) ) {
					errors.country = 'Select your country.';
				} else if ( ! fieldValue( 'city' ) ) {
					errors.city = 'Select your city.';
				} else if ( ! fieldValue( 'area' ) ) {
					errors.area = 'Select your area.';
				}

				validateTerms( errors );
			}

			if ( 2 === n ) {
				if ( ! fieldValue( 'qualification' ) ) {
					errors.qualification = 'Enter your qualifications, e.g. MBBS, FCPS.';
				}

				var years = fieldValue( 'years_experience' );

				if ( '' === years ) {
					errors.years_experience = 'Enter your years of experience.';
				} else if ( ! /^\d+$/.test( years ) || Number( years ) > 80 ) {
					errors.years_experience = 'Enter a whole number of years from 0 to 80.';
				}

				var specs = qs( '[name="specializations[]"]' );

				if ( specs && ! Array.prototype.some.call( specs.options, function ( o ) {
					return o.selected;
				} ) ) {
					errors.specializations = 'Choose at least one specialization.';
				}

				if ( photoUploading ) {
					errors.profile_picture = 'Please wait for your photo to finish uploading.';
				}
			}
		}

		function validatePassword( errors ) {
			var password = pwBlock.querySelector( '[name="password"]' ).value;
			var confirm = pwBlock.querySelector( '[name="confirm_password"]' ).value;

			if ( ! password ) {
				errors.password = 'Create a password.';
			} else if ( password.length < MIN_PASSWORD ) {
				errors.password = 'Use at least ' + MIN_PASSWORD + ' characters.';
			}

			if ( password && ! confirm ) {
				errors.confirm_password = 'Enter your password again.';
			} else if ( password && confirm !== password ) {
				errors.confirm_password = 'The passwords don’t match.';
			}
		}

		function validateTerms( errors ) {
			if ( ! document.getElementById( 'dak-terms-accepted' ).checked ) {
				errors.terms_accepted = 'Please agree to the Terms & Conditions to continue.';
			}
		}

		/** Shows errors inline; returns true when there were none. */
		function applyErrors( errors, scope ) {
			clearErrors( scope );

			Object.keys( errors ).forEach( function ( field ) {
				showError( field, errors[ field ] );
			} );

			return ! Object.keys( errors ).length;
		}

		function focusFirstError( errors ) {
			var first = Object.keys( errors )[ 0 ];
			var target = first ? inputFor( first ) : null;

			if ( target ) {
				target.focus();
			}
		}

		/* ------------------------------------------------------------------
		 * Date of birth: Day / Month / Year → date_of_birth (Y-m-d)
		 * ---------------------------------------------------------------- */

		var dobDay = root.querySelector( '[data-dob-day]' );
		var dobMonth = root.querySelector( '[data-dob-month]' );
		var dobYear = root.querySelector( '[data-dob-year]' );
		var dobValue = root.querySelector( '[data-dob-value]' );

		function pad( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		}

		function composeDob() {
			var d = parseInt( dobDay.value, 10 );
			var m = parseInt( dobMonth.value, 10 );
			var y = parseInt( dobYear.value, 10 );

			dobValue.value = '';

			if ( ! dobDay.value && ! dobMonth.value && ! dobYear.value ) {
				return { error: 'Enter your date of birth.' };
			}

			if ( ! d || ! m || ! dobYear.value ) {
				return { error: 'Enter the day, month and year of your birth.' };
			}

			if ( 4 !== String( dobYear.value ).length || y < 1900 ) {
				return { error: 'Enter the year as 4 digits, e.g. 1985.' };
			}

			var date = new Date( y, m - 1, d );

			if ( date.getFullYear() !== y || date.getMonth() !== m - 1 || date.getDate() !== d ) {
				return { error: 'Enter a real date, e.g. 14 March 1985.' };
			}

			var today = new Date();
			today.setHours( 0, 0, 0, 0 );

			if ( date > today ) {
				return { error: 'Your date of birth can’t be in the future.' };
			}

			dobValue.value = y + '-' + pad( m ) + '-' + pad( d );

			return { error: '' };
		}

		[ dobDay, dobYear ].forEach( function ( input ) {
			input.addEventListener( 'input', function () {
				var digits = input.value.replace( /\D/g, '' );

				if ( digits !== input.value ) {
					input.value = digits;
				}
			} );
		} );

		/* ------------------------------------------------------------------
		 * Roles
		 * ---------------------------------------------------------------- */

		var memory = {};

		function rememberShared( from ) {
			containers[ from ].querySelectorAll( '[data-share]' ).forEach( function ( input ) {
				memory[ input.getAttribute( 'data-share' ) ] = input.value;
			} );

			var code = containers[ from ].querySelector( '[name="phone_country_code"]' );
			var number = containers[ from ].querySelector( '[name="phone_number"]' );
			memory.phone_country_code = code ? code.value : memory.phone_country_code;
			memory.phone_number = number ? number.value : memory.phone_number;
		}

		function restoreShared( to ) {
			containers[ to ].querySelectorAll( '[data-share]' ).forEach( function ( input ) {
				var key = input.getAttribute( 'data-share' );

				if ( ! input.value && memory[ key ] ) {
					input.value = memory[ key ];
				}
			} );

			var code = containers[ to ].querySelector( '[name="phone_country_code"]' );
			var number = containers[ to ].querySelector( '[name="phone_number"]' );

			if ( code && memory.phone_country_code && ! number.value ) {
				code.value = memory.phone_country_code;
			}

			if ( number && ! number.value && memory.phone_number ) {
				number.value = memory.phone_number;
			}
		}

		/**
		 * Disables the inactive role's fields (so they're neither validated
		 * nor submitted) and re-enables only the ones this disabled — a City
		 * or Area select that's disabled because no country/city is chosen
		 * yet stays that way.
		 */
		function setDisabled( container, disabled ) {
			container.querySelectorAll( 'input, select, textarea' ).forEach( function ( field ) {
				// The shared password/consent blocks move between roles — leave them.
				if ( pwBlock.contains( field ) || termsBlock.contains( field ) ) {
					return;
				}

				if ( disabled && ! field.disabled ) {
					field.disabled = true;
					field.setAttribute( 'data-role-disabled', '1' );
				} else if ( ! disabled && field.hasAttribute( 'data-role-disabled' ) ) {
					field.disabled = false;
					field.removeAttribute( 'data-role-disabled' );
				}
			} );
		}

		function setRole( next, initial ) {
			if ( ! initial && next === role ) {
				return;
			}

			if ( ! initial ) {
				rememberShared( role );
			}

			role = next;

			containers.patient.hidden = 'patient' !== role;
			containers.doctor.hidden = 'doctor' !== role;
			setDisabled( containers.patient, 'patient' !== role );
			setDisabled( containers.doctor, 'doctor' !== role );
			pwSlots[ role ].appendChild( pwBlock );
			termsSlots[ role ].appendChild( termsBlock );

			root.querySelectorAll( '.dak-role-card' ).forEach( function ( card ) {
				card.classList.toggle( 'is-selected', card.getAttribute( 'data-role' ) === role );
			} );

			title.textContent = title.getAttribute( 'data-' + role );
			intro.textContent = intro.getAttribute( 'data-' + role );
			submitBtn.querySelector( '.dak-button-label' ).textContent = submitBtn.getAttribute( 'data-' + role + '-label' );

			var doctorTerms = document.getElementById( 'dak-doctor-terms-content' );
			var patientTerms = document.getElementById( 'dak-patient-terms-content' );
			doctorTerms.classList.toggle( 'dak-hidden', 'doctor' !== role );
			patientTerms.classList.toggle( 'dak-hidden', 'patient' !== role );

			if ( ! initial ) {
				restoreShared( role );

				// Passwords and consent never carry over to the other role.
				pwBlock.querySelectorAll( 'input[name="password"], input[name="confirm_password"]' ).forEach( function ( input ) {
					input.value = '';
				} );
				document.getElementById( 'dak-terms-accepted' ).checked = false;

				clearErrors();
				hideSummary();
			}

			if ( 'doctor' === role ) {
				goToStep( 1, ! initial );
			} else {
				backBtn.hidden = true;
				nextBtn.hidden = true;
				submitBtn.hidden = false;
				stepStatus.textContent = '';
			}
		}

		root.querySelectorAll( 'input[name="register_as"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( radio.checked ) {
					setRole( radio.value, false );
				}
			} );
		} );

		/* ------------------------------------------------------------------
		 * Doctor steps
		 * ---------------------------------------------------------------- */

		var sections = Array.prototype.slice.call( containers.doctor.querySelectorAll( '[data-step]' ) );

		function goToStep( n, moveFocus ) {
			step = n;

			sections.forEach( function ( section ) {
				section.hidden = Number( section.getAttribute( 'data-step' ) ) !== n;
			} );

			containers.doctor.querySelectorAll( '[data-step-indicator]' ).forEach( function ( li ) {
				var i = Number( li.getAttribute( 'data-step-indicator' ) );
				li.classList.toggle( 'is-current', i === n );
				li.classList.toggle( 'is-done', i < n );

				if ( i === n ) {
					li.setAttribute( 'aria-current', 'step' );
				} else {
					li.removeAttribute( 'aria-current' );
				}
			} );

			stepStatus.textContent = 'Step ' + n + ' of 3: ' + STEP_NAMES[ n ];
			backBtn.hidden = 1 === n;
			nextBtn.hidden = 3 === n;
			submitBtn.hidden = 3 !== n;

			if ( 3 === n ) {
				renderReview();
			}

			if ( moveFocus ) {
				var heading = document.getElementById( 'dak-doctor-step-' + n + '-title' );

				if ( heading ) {
					heading.focus();
				}

				var top = root.getBoundingClientRect().top + window.pageYOffset - 80;

				if ( window.pageYOffset > top ) {
					window.scrollTo( 0, Math.max( 0, top ) );
				}
			}
		}

		nextBtn.addEventListener( 'click', function () {
			var errors = {};
			validateDoctorStep( step, errors );
			hideSummary();

			if ( ! applyErrors( errors, sections[ step - 1 ] ) ) {
				focusFirstError( errors );
				return;
			}

			goToStep( step + 1, true );
		} );

		backBtn.addEventListener( 'click', function () {
			hideSummary();
			goToStep( Math.max( 1, step - 1 ), true );
		} );

		/** Which doctor step a field lives on. */
		function stepOf( field ) {
			var el = containers.doctor.querySelector( '.dak-field-error[data-field="' + field + '"]' );

			if ( ! el ) {
				return 3;
			}

			var section = el.closest( '[data-step]' );
			return section ? Number( section.getAttribute( 'data-step' ) ) : 3;
		}

		function goToField( field ) {
			if ( 'doctor' === role ) {
				goToStep( stepOf( field ), false );
			}

			var target = inputFor( field );

			if ( target ) {
				target.focus();
			}
		}

		/* ------------------------------------------------------------------
		 * Review (step 3)
		 * ---------------------------------------------------------------- */

		function optionText( name ) {
			var el = qs( '[name="' + name + '"]' );

			if ( ! el || ! el.value ) {
				return '';
			}

			return el.tagName === 'SELECT' ? el.options[ el.selectedIndex ].textContent : el.value;
		}

		function selectedLabels( name ) {
			var el = qs( '[name="' + name + '[]"]' );

			return el ? Array.prototype.filter.call( el.options, function ( o ) {
				return o.selected;
			} ).map( function ( o ) {
				return o.textContent;
			} ) : [];
		}

		function renderReview() {
			var phoneCode = qs( '[name="phone_country_code"]' );
			var expertise = document.getElementById( 'dak-expertise' );
			var photoSet = !! document.getElementById( 'dak-profile-picture-id' ).value;
			var groups = [
				{
					step: 1,
					title: 'Account details',
					rows: [
						[ 'Name', ( fieldValue( 'first_name' ) + ' ' + fieldValue( 'last_name' ) ).trim() ],
						[ 'Email', fieldValue( 'email' ) ],
						[ 'Phone', ( phoneCode ? '+' + phoneCode.value + ' ' : '' ) + fieldValue( 'phone_number' ) ],
						[ 'Gender', optionText( 'gender' ) ],
						[ 'Password', pwBlock.querySelector( '[name="password"]' ).value ? 'Set' : '' ],
					],
				},
				{
					step: 2,
					title: 'Professional profile',
					rows: [
						[ 'Qualifications', fieldValue( 'qualification' ) ],
						[ 'Experience', fieldValue( 'years_experience' ) ? fieldValue( 'years_experience' ) + ' years' : '' ],
						[ 'Specializations', selectedLabels( 'specializations' ).join( ', ' ) ],
						[ 'Short description', fieldValue( 'short_description' ) ],
						[ 'Keywords', selectedLabels( 'keywords' ).join( ', ' ) ],
						[ 'Other expertise', expertise ? expertise.textContent.trim().slice( 0, 140 ) : '' ],
						[ 'Profile photo', photoSet ? 'Added' : 'Not added' ],
					],
				},
			];

			review.textContent = '';

			var heading = document.createElement( 'h3' );
			heading.textContent = 'Check your details';
			review.appendChild( heading );

			groups.forEach( function ( group ) {
				var box = document.createElement( 'div' );
				box.className = 'dak-acct-review-group';

				var head = document.createElement( 'div' );
				head.className = 'dak-acct-review-head';

				var h = document.createElement( 'h4' );
				h.textContent = group.title;

				var edit = document.createElement( 'button' );
				edit.type = 'button';
				edit.className = 'dak-acct-link';
				edit.textContent = 'Edit';
				edit.setAttribute( 'aria-label', 'Edit ' + group.title.toLowerCase() );
				edit.addEventListener( 'click', function () {
					goToStep( group.step, true );
				} );

				head.appendChild( h );
				head.appendChild( edit );
				box.appendChild( head );

				var dl = document.createElement( 'dl' );

				group.rows.forEach( function ( row ) {
					var dt = document.createElement( 'dt' );
					var dd = document.createElement( 'dd' );
					dt.textContent = row[ 0 ];
					dd.textContent = row[ 1 ] || '—';
					dl.appendChild( dt );
					dl.appendChild( dd );
				} );

				box.appendChild( dl );
				review.appendChild( box );
			} );
		}

		/* ------------------------------------------------------------------
		 * Profile photo: checked here, uploaded immediately, preview +
		 * Replace / Remove
		 * ---------------------------------------------------------------- */

		var photoInput = document.getElementById( 'dak-profile-picture-input' );
		var photoId = document.getElementById( 'dak-profile-picture-id' );
		var photoPreview = document.getElementById( 'dak-profile-picture-preview' );
		var photoStatus = document.getElementById( 'dak-upload-status' );
		var photoChoose = root.querySelector( '[data-photo-choose]' );
		var photoRemove = root.querySelector( '[data-photo-remove]' );
		var emptyPreview = photoPreview.innerHTML;

		function photoState( hasPhoto ) {
			photoChoose.textContent = hasPhoto ? 'Replace photo' : 'Upload photo';
			photoRemove.hidden = ! hasPhoto;
		}

		photoChoose.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || ' ' === event.key ) {
				event.preventDefault();
				photoInput.click();
			}
		} );
		photoChoose.setAttribute( 'tabindex', '0' );
		photoChoose.setAttribute( 'role', 'button' );

		photoInput.addEventListener( 'change', function () {
			var file = photoInput.files[ 0 ];

			showError( 'profile_picture', '' );

			if ( ! file ) {
				return;
			}

			if ( PHOTO_TYPES.indexOf( file.type ) === -1 ) {
				showError( 'profile_picture', 'Choose a JPG, PNG or WebP image.' );
				photoInput.value = '';
				return;
			}

			if ( file.size > MAX_PHOTO_BYTES ) {
				showError( 'profile_picture', 'This image is ' + ( file.size / 1048576 ).toFixed( 1 ) + ' MB. Choose one up to 5 MB.' );
				photoInput.value = '';
				return;
			}

			photoUploading = true;
			photoStatus.textContent = 'Uploading photo…';

			var data = new FormData();
			data.append( 'action', 'doctor_ak_upload_profile_picture' );
			data.append( 'nonce', dakRegister.nonce );
			data.append( 'profile_picture', file );

			fetch( dakRegister.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					photoUploading = false;

					if ( result && result.success ) {
						photoId.value = result.data.attachment_id;
						photoPreview.textContent = '';

						var img = document.createElement( 'img' );
						img.src = result.data.url;
						img.alt = '';
						photoPreview.appendChild( img );
						photoStatus.textContent = 'Photo added.';
						photoState( true );
					} else {
						photoStatus.textContent = '';
						showError( 'profile_picture', ( result && result.data && result.data.message ) || 'The photo couldn’t be uploaded. Please try again.' );
					}

					photoInput.value = '';
				} )
				.catch( function () {
					photoUploading = false;
					photoStatus.textContent = '';
					photoInput.value = '';
					showError( 'profile_picture', 'The photo couldn’t be uploaded. Please check your connection and try again.' );
				} );
		} );

		photoRemove.addEventListener( 'click', function () {
			photoId.value = '';
			photoPreview.innerHTML = emptyPreview;
			photoStatus.textContent = 'Photo removed.';
			photoState( false );
			photoChoose.focus();
		} );

		/* ------------------------------------------------------------------
		 * Location: wire the Country → City → Area selects and explain why a
		 * select is unavailable
		 * ---------------------------------------------------------------- */

		var country = document.getElementById( 'dak-country' );
		var city = document.getElementById( 'dak-city' );
		var area = document.getElementById( 'dak-area' );

		if ( window.dakCityArea ) {
			window.dakCityArea.wire( country, city, area, dakRegister.locations );
		}

		function locationHints() {
			var cityHint = root.querySelector( '[data-location-hint="city"]' );
			var areaHint = root.querySelector( '[data-location-hint="area"]' );
			var noCountries = country.options.length <= 1;

			if ( noCountries ) {
				cityHint.textContent = 'Locations aren’t available right now. Please try again later.';
			} else if ( ! country.value ) {
				cityHint.textContent = 'Choose a country first.';
			} else {
				cityHint.textContent = 'No cities are listed for this country yet.';
			}

			cityHint.hidden = ! city.disabled;

			areaHint.textContent = ! city.value ? 'Choose a city first.' : 'No areas are listed for this city yet.';
			areaHint.hidden = ! area.disabled;
		}

		country.addEventListener( 'change', locationHints );
		city.addEventListener( 'change', locationHints );
		locationHints();

		/* ------------------------------------------------------------------
		 * Tag pickers + phone inputs
		 * ---------------------------------------------------------------- */

		var shared = window.DoctorAKPortal || {};

		if ( typeof shared.initMultiSelect === 'function' ) {
			shared.initMultiSelect( document.getElementById( 'dak-specializations' ), {
				copyCut: true,
				placeholder: 'Search specializations…',
			} );
			shared.initMultiSelect( document.getElementById( 'dak-keywords' ), {
				allowCustom: true,
				copyCut: true,
				placeholder: 'Search or type a keyword…',
			} );
		}

		[ [ 'dak-phone-number-number', 'dak-phone-number-error' ], [ 'dak-doctor-phone-number', 'dak-doctor-phone-error' ] ].forEach( function ( pair ) {
			var input = document.getElementById( pair[ 0 ] );

			if ( input ) {
				input.setAttribute( 'aria-describedby', pair[ 1 ] );
				input.setAttribute( 'autocomplete', 'tel-national' );
				input.setAttribute( 'inputmode', 'tel' );
			}
		} );

		[ 'dak-phone-number-code', 'dak-doctor-phone-code' ].forEach( function ( id ) {
			var select = document.getElementById( id );

			if ( select ) {
				select.setAttribute( 'aria-label', 'Country code' );
				select.setAttribute( 'autocomplete', 'tel-country-code' );

				// "Pakistan (+92)" → "+92 Pakistan": the narrow select then still
				// shows the part that matters when the name is cut off.
				Array.prototype.forEach.call( select.options, function ( option ) {
					var m = option.textContent.match( /^(.*)\s+\((\+\d+)\)\s*$/ );

					if ( m ) {
						option.textContent = m[ 2 ] + ' ' + m[ 1 ];
					}
				} );
			}
		} );

		// The password requirement turns into a tick once it's met.
		var pwInput = pwBlock.querySelector( '[name="password"]' );
		var pwRule = pwBlock.querySelector( '[data-password-rule]' );

		pwInput.addEventListener( 'input', function () {
			pwRule.classList.toggle( 'is-met', pwInput.value.length >= MIN_PASSWORD );
		} );

		// Clear a field's error once it's been corrected.
		form.addEventListener( 'input', function ( event ) {
			var name = event.target.name;

			if ( name && event.target.getAttribute( 'aria-invalid' ) === 'true' ) {
				showError( name, '' );
			} else if ( event.target.closest( '[data-dob]' ) && ! composeDob().error ) {
				showError( 'date_of_birth', '' );
			}
		} );

		form.addEventListener( 'change', function ( event ) {
			var name = event.target.name;

			if ( ! name ) {
				return;
			}

			if ( 'specializations[]' === name ) {
				showError( 'specializations', '' );
			} else if ( event.target.getAttribute( 'aria-invalid' ) === 'true' || 'terms_accepted' === name ) {
				showError( name, '' );
			}
		} );

		/* ------------------------------------------------------------------
		 * Terms dialog — viewing never ticks the box
		 * ---------------------------------------------------------------- */

		var termsModal = document.getElementById( 'dak-terms-modal' );
		var termsOpen = document.getElementById( 'dak-terms-view-link' );
		var termsOpener = null;

		function openModal( modal, opener ) {
			termsOpener = opener || document.activeElement;
			modal.classList.add( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'dak-modal-open' );
			modal.querySelector( '[role="dialog"]' ).focus();
		}

		function closeModal( modal ) {
			modal.classList.remove( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'dak-modal-open' );

			if ( termsOpener ) {
				termsOpener.focus();
			}
		}

		termsOpen.addEventListener( 'click', function () {
			openModal( termsModal, termsOpen );
		} );

		termsModal.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-dak-terms-close]' ) ) {
				closeModal( termsModal );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! termsModal.classList.contains( 'is-open' ) ) {
				return;
			}

			if ( 'Escape' === event.key ) {
				closeModal( termsModal );
				return;
			}

			// Keep Tab inside the dialog.
			if ( 'Tab' === event.key ) {
				var focusable = termsModal.querySelectorAll( 'button, a[href], [tabindex]:not([tabindex="-1"])' );
				var first = focusable[ 0 ];
				var last = focusable[ focusable.length - 1 ];

				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		} );

		/* ------------------------------------------------------------------
		 * Submit
		 * ---------------------------------------------------------------- */

		function showSuccess( message ) {
			var modal = document.getElementById( 'dak-register-success-modal' );
			var heading = document.getElementById( 'dak-register-success-modal-title' );
			var ok = document.getElementById( 'dak-register-success-ok' );

			heading.textContent = heading.getAttribute( 'data-' + role );
			ok.textContent = ok.getAttribute( 'data-' + role );
			document.getElementById( 'dak-register-success-modal-message' ).textContent = message;
			modal.querySelector( '[data-doctor-only]' ).hidden = 'doctor' !== role;

			modal.classList.add( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'dak-modal-open' );
			modal.querySelector( '[role="dialog"]' ).focus();

			ok.addEventListener( 'click', function () {
				window.location.href = modal.getAttribute( 'data-' + role + '-url' ) || '/';
			}, { once: true } );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( busy ) {
				return;
			}

			// Enter inside a tag search box adds a tag; it never submits.
			if ( document.activeElement && document.activeElement.classList.contains( 'dak-multiselect-search' ) ) {
				return;
			}

			// Enter on an earlier doctor step means "Continue", never submit.
			if ( 'doctor' === role && step < 3 ) {
				nextBtn.click();
				return;
			}

			hideSummary();

			var errors = {};

			if ( 'patient' === role ) {
				validateIdentity( errors );
				validateDob( errors );
				validatePassword( errors );
				validateTerms( errors );
			} else {
				[ 1, 2, 3 ].forEach( function ( n ) {
					validateDoctorStep( n, errors );
				} );
			}

			if ( 'patient' === role ) {
				composeDob();
			}

			if ( ! applyErrors( errors ) ) {
				if ( 'doctor' === role ) {
					var firstStep = Math.min.apply( null, Object.keys( errors ).map( stepOf ) );

					if ( firstStep !== step ) {
						goToStep( firstStep, false );
					}
				}

				if ( Object.keys( errors ).length > 1 ) {
					showSummary( 'Please fix the following:', errors );
				} else {
					focusFirstError( errors );
				}

				return;
			}

			busy = true;
			setLoading( true );

			var data = new FormData( form );
			data.append( 'action', 'doctor_ak_register' );
			data.append( 'nonce', dakRegister.nonce );

			fetch( dakRegister.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result && result.success ) {
						// Keep the button disabled — the account now exists.
						pwBlock.querySelectorAll( 'input[name="password"], input[name="confirm_password"]' ).forEach( function ( input ) {
							input.value = '';
						} );
						showSuccess( result.data.message );
						return;
					}

					busy = false;
					setLoading( false );

					if ( result && result.data && result.data.errors ) {
						var serverErrors = result.data.errors;

						applyErrors( serverErrors );

						if ( 'doctor' === role ) {
							goToStep( Math.min.apply( null, Object.keys( serverErrors ).map( stepOf ) ), false );
						}

						showSummary( 'Please fix the following:', serverErrors );
					} else {
						showSummary( ( result && result.data && result.data.message ) || GENERIC_ERROR );
					}
				} )
				.catch( function () {
					busy = false;
					setLoading( false );
					showSummary( GENERIC_ERROR );
				} );
		} );

		setRole( role, true );
	} );
} )();
