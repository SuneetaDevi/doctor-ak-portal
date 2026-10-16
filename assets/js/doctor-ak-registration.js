/**
 * Doctor AK Portal — Shared form helpers.
 *
 * The searchable tag picker (initMultiSelect), day pills, check cards and
 * field-error helpers, used by the registration page (whose own flow is in
 * doctor-ak-account-register.js), the profile editor and the dashboards.
 * Vanilla JS, no jQuery/Select2 dependency.
 */
( function () {
	'use strict';

	// Gives each enhanced multi-select unique ids for its ARIA wiring.
	var multiSelectCount = 0;

	// Shared helpers only. The public registration page's own flow (roles,
	// doctor steps, review, submission) lives in doctor-ak-account-register.js;
	// this file is also loaded by the profile editor and the dashboards for
	// initMultiSelect() and the field-error helpers exported at the bottom.
	document.addEventListener( 'DOMContentLoaded', function () {
		initDayPills();
		initCheckCards();
	} );

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
	 * Writes text to the clipboard — the modern Clipboard API where
	 * available (requires a secure/HTTPS context), falling back to a
	 * hidden, off-screen textarea + execCommand('copy') for older or
	 * plain-HTTP installs where navigator.clipboard doesn't exist at all.
	 *
	 * @param {string} text
	 * @return {void}
	 */
	function copyTextToClipboard( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).catch( function () {
				legacyCopyTextToClipboard( text );
			} );

			return;
		}

		legacyCopyTextToClipboard( text );
	}

	function legacyCopyTextToClipboard( text ) {
		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild( textarea );
		textarea.select();

		try {
			document.execCommand( 'copy' );
		} catch ( e ) {
			// Nothing more to do — the visitor can still select and copy the
			// chips' text by hand.
		}

		document.body.removeChild( textarea );
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

		multiSelectCount += 1;

		var uid = ( select.id || 'dak-multiselect' ) + '-' + multiSelectCount;
		var fieldLabel = select.id ? document.querySelector( 'label[for="' + select.id + '"]' ) : null;

		if ( fieldLabel && ! fieldLabel.id ) {
			fieldLabel.id = uid + '-label';
		}

		var wrapper = document.createElement( 'div' );
		wrapper.className = 'dak-multiselect';

		// Opens the suggestion list. A button-like control (not a text box)
		// so screen readers announce it as "<label>, collapsed".
		var control = document.createElement( 'div' );
		control.className = 'dak-multiselect-control';
		control.setAttribute( 'tabindex', '0' );
		control.setAttribute( 'role', 'button' );
		control.setAttribute( 'aria-haspopup', 'listbox' );
		control.setAttribute( 'aria-expanded', 'false' );

		if ( fieldLabel ) {
			control.setAttribute( 'aria-labelledby', fieldLabel.id );

			// The native <select> is visually hidden, so its label would focus
			// nothing — send clicks to the control instead.
			fieldLabel.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				control.focus();
			} );
		}

		if ( select.getAttribute( 'aria-describedby' ) ) {
			control.setAttribute( 'aria-describedby', select.getAttribute( 'aria-describedby' ) );
		}

		// Screen-reader announcements ("Cardiology added").
		var announcer = document.createElement( 'span' );
		announcer.className = 'dak-visually-hidden';
		announcer.setAttribute( 'aria-live', 'polite' );

		function announce( text ) {
			announcer.textContent = '';
			setTimeout( function () {
				announcer.textContent = text;
			}, 30 );
		}

		var chipsContainer = document.createElement( 'div' );
		chipsContainer.className = 'dak-multiselect-chips';

		var placeholder = document.createElement( 'span' );
		placeholder.className = 'dak-multiselect-placeholder';
		placeholder.textContent = opts.placeholder || 'Select specializations…';

		control.appendChild( chipsContainer );
		control.appendChild( placeholder );

		var dropdown = document.createElement( 'div' );
		dropdown.className = 'dak-multiselect-dropdown dak-hidden';

		var search = document.createElement( 'input' );
		search.type = 'text';
		search.className = 'dak-multiselect-search';
		search.setAttribute( 'placeholder', opts.allowCustom ? 'Search or type to add new…' : 'Search…' );
		search.setAttribute( 'role', 'combobox' );
		search.setAttribute( 'aria-autocomplete', 'list' );
		search.setAttribute( 'aria-expanded', 'true' );
		search.setAttribute( 'aria-controls', uid + '-list' );
		search.setAttribute( 'autocomplete', 'off' );
		search.setAttribute( 'aria-label', ( fieldLabel ? fieldLabel.textContent.replace( /\*/g, '' ).trim() + ': ' : '' ) + ( opts.allowCustom ? 'search or add' : 'search' ) );

		var optionsList = document.createElement( 'ul' );
		optionsList.className = 'dak-multiselect-options';
		optionsList.id = uid + '-list';
		optionsList.setAttribute( 'role', 'listbox' );
		optionsList.setAttribute( 'aria-multiselectable', 'true' );

		if ( fieldLabel ) {
			optionsList.setAttribute( 'aria-labelledby', fieldLabel.id );
		}

		var optionCount = 0;

		function addOptionRow( option ) {
			var li = document.createElement( 'li' );
			li.className = 'dak-multiselect-option';
			li.setAttribute( 'data-value', option.value );
			li.setAttribute( 'role', 'option' );
			li.id = uid + '-opt-' + ( optionCount += 1 );
			li.textContent = option.textContent;
			li.setAttribute( 'aria-selected', option.selected ? 'true' : 'false' );

			if ( option.selected ) {
				li.classList.add( 'is-selected' );
			}

			li.addEventListener( 'click', function () {
				option.selected = ! option.selected;
				li.classList.toggle( 'is-selected', option.selected );
				li.setAttribute( 'aria-selected', option.selected ? 'true' : 'false' );
				renderChips();
				announce( option.textContent + ( option.selected ? ' added' : ' removed' ) );
				select.dispatchEvent( new Event( 'change' ) );
			} );

			// Keep focus in the search box when picking with the mouse.
			li.addEventListener( 'mousedown', function ( event ) {
				event.preventDefault();
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
			var existing = Array.prototype.filter.call( select.options, function ( option ) {
				return option.textContent.trim().toLowerCase() === label.trim().toLowerCase();
			} )[ 0 ];

			// Never a duplicate tag: typing an existing one (any letter case)
			// selects that one instead.
			if ( existing ) {
				if ( ! existing.selected ) {
					existing.selected = true;
					Array.prototype.forEach.call( optionsList.children, function ( li ) {
						if ( li.getAttribute( 'data-value' ) === existing.value ) {
							li.classList.add( 'is-selected' );
							li.setAttribute( 'aria-selected', 'true' );
						}
					} );
					renderChips();
					announce( existing.textContent + ' added' );
					select.dispatchEvent( new Event( 'change' ) );
				}

				return;
			}

			var option = document.createElement( 'option' );
			option.value = label;
			option.textContent = label;
			option.selected = true;
			select.appendChild( option );

			addOptionRow( option );
			renderChips();
			announce( label + ' added' );
			select.dispatchEvent( new Event( 'change' ) );
		}

		/**
		 * Splits comma-separated text into individual tags — pasting
		 * "Cardiology, Neurology, Dermatology" (or typing it out and
		 * committing with Enter/Tab) adds three separate chips instead of
		 * one chip containing the whole string. Each part reuses a matching
		 * existing option (case-insensitive, exact) when there is one,
		 * rather than creating a duplicate custom tag next to it.
		 *
		 * @param {string} text Raw text — one or more comma-separated values.
		 * @return {void}
		 */
		function addFromCommaSeparatedText( text ) {
			text.split( ',' ).forEach( function ( part ) {
				var value = part.trim();

				if ( '' === value ) {
					return;
				}

				var match = Array.prototype.filter.call( optionsList.children, function ( li ) {
					return ! li.classList.contains( 'is-selected' ) && li.textContent.toLowerCase() === value.toLowerCase();
				} )[ 0 ];

				if ( match ) {
					match.click();
				} else if ( opts.allowCustom ) {
					addCustomOption( value );
				}
			} );
		}

		dropdown.appendChild( search );
		dropdown.appendChild( optionsList );

		wrapper.appendChild( control );
		wrapper.appendChild( dropdown );
		wrapper.appendChild( announcer );

		select.classList.add( 'dak-visually-hidden' );
		select.setAttribute( 'tabindex', '-1' );
		select.setAttribute( 'aria-hidden', 'true' );
		select.parentNode.insertBefore( wrapper, select );

		var activeIndex = -1;

		function visibleOptions() {
			return Array.prototype.filter.call( optionsList.children, function ( li ) {
				return ! li.classList.contains( 'dak-hidden' );
			} );
		}

		function setActive( index ) {
			var items = visibleOptions();

			Array.prototype.forEach.call( optionsList.children, function ( li ) {
				li.classList.remove( 'is-active' );
			} );

			// -1 means "nothing highlighted".
			if ( ! items.length || index < 0 ) {
				activeIndex = -1;
				search.removeAttribute( 'aria-activedescendant' );
				return;
			}

			activeIndex = Math.max( 0, Math.min( index, items.length - 1 ) );
			items[ activeIndex ].classList.add( 'is-active' );
			items[ activeIndex ].scrollIntoView( { block: 'nearest' } );
			search.setAttribute( 'aria-activedescendant', items[ activeIndex ].id );
		}

		function openList() {
			resetSearch();
			dropdown.classList.remove( 'dak-hidden' );
			control.setAttribute( 'aria-expanded', 'true' );
			search.focus();
		}

		function closeList( returnFocus ) {
			if ( dropdown.classList.contains( 'dak-hidden' ) ) {
				return;
			}

			dropdown.classList.add( 'dak-hidden' );
			control.setAttribute( 'aria-expanded', 'false' );
			setActive( -1 );

			if ( returnFocus ) {
				control.focus();
			}
		}

		// Closes once focus moves on to the rest of the form.
		wrapper.addEventListener( 'focusout', function ( event ) {
			if ( event.relatedTarget && ! wrapper.contains( event.relatedTarget ) ) {
				closeList( false );
			}
		} );

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
					announce( option.textContent + ' removed' );
					select.dispatchEvent( new Event( 'change' ) );
					control.focus();
				} );

				chip.appendChild( remove );
				chipsContainer.appendChild( chip );
			} );
		}

		function deselectOptionInList( value ) {
			Array.prototype.forEach.call( optionsList.children, function ( li ) {
				if ( li.getAttribute( 'data-value' ) === value ) {
					li.classList.remove( 'is-selected' );
					li.setAttribute( 'aria-selected', 'false' );
				}
			} );
		}

		control.addEventListener( 'click', function ( event ) {
			// A chip's own × button handles itself.
			if ( event.target.closest( '.dak-chip-remove' ) ) {
				return;
			}

			if ( dropdown.classList.contains( 'dak-hidden' ) ) {
				openList();
			} else {
				closeList( false );
			}
		} );

		control.addEventListener( 'keydown', function ( event ) {
			if ( event.target !== control ) {
				return;
			}

			if ( 'Enter' === event.key || ' ' === event.key || 'ArrowDown' === event.key ) {
				event.preventDefault();
				openList();
			}
		} );

		// Ctrl/Cmd+C (or +X) while the chip area itself has focus (Tab into
		// it, or click its empty space rather than a chip's own × button)
		// copies every current chip as one comma-separated string — handy
		// for pasting the same set into another doctor's field, or outside
		// the form entirely. Cut does the same and then clears every chip,
		// same as a real input field's cut would empty it.
		control.setAttribute(
			'title',
			opts.allowCustom
				? 'Click to search or add. Ctrl+C copies all tags as comma-separated text, Ctrl+X copies and clears them.'
				: 'Click to select. Ctrl+C copies all tags as comma-separated text, Ctrl+X copies and clears them.'
		);

		control.addEventListener( 'keydown', function ( event ) {
			var key = event.key ? event.key.toLowerCase() : '';
			var isCopy = ( event.ctrlKey || event.metaKey ) && 'c' === key;
			var isCut = ( event.ctrlKey || event.metaKey ) && 'x' === key;

			if ( ! isCopy && ! isCut ) {
				return;
			}

			var selectedOptions = Array.prototype.filter.call( select.options, function ( option ) {
				return option.selected;
			} );

			if ( ! selectedOptions.length ) {
				return;
			}

			event.preventDefault();

			copyTextToClipboard(
				selectedOptions.map( function ( option ) {
					return option.textContent;
				} ).join( ', ' )
			);

			if ( ! isCut ) {
				return;
			}

			selectedOptions.forEach( function ( option ) {
				option.selected = false;
				deselectOptionInList( option.value );
			} );

			renderChips();
			select.dispatchEvent( new Event( 'change' ) );
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

			// Highlight the first match not chosen yet, so Enter adds it — never
			// one already chosen (Enter would remove it). With no unchosen
			// match, Enter falls through to "add as typed" (or nothing).
			var firstNew = '' === query ? -1 : visibleOptions().findIndex( function ( li ) {
				return ! li.classList.contains( 'is-selected' );
			} );

			setActive( firstNew );
		} );

		// Arrow keys move through the suggestions, Escape closes the list.
		search.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();
				setActive( activeIndex + ( 'ArrowDown' === event.key ? 1 : -1 ) );
			} else if ( 'Escape' === event.key ) {
				event.preventDefault();
				event.stopPropagation();
				closeList( true );
			} else if ( 'Enter' === event.key && activeIndex > -1 ) {
				var items = visibleOptions();

				if ( items[ activeIndex ] ) {
					event.preventDefault();
					event.stopImmediatePropagation();
					items[ activeIndex ].click();
				}
			}
		} );

		function resetSearch() {
			search.value = '';
			Array.prototype.forEach.call( optionsList.children, function ( li ) {
				li.classList.remove( 'dak-hidden' );
			} );
			setActive( -1 );
		}

		// Pasting a comma-separated list ("Cardiology, Neurology,
		// Dermatology") splits and adds each one as its own chip right
		// away — no need to also press Enter. A pasted value with no comma
		// falls through to the default paste behaviour, so it just fills
		// the search box as normal and Enter/Tab below still commits it.
		search.addEventListener( 'paste', function ( event ) {
			var clipboard = event.clipboardData || window.clipboardData;
			var pasted = clipboard ? clipboard.getData( 'text' ) : '';

			if ( ! pasted || -1 === pasted.indexOf( ',' ) ) {
				return;
			}

			event.preventDefault();
			addFromCommaSeparatedText( pasted );
			resetSearch();
		} );

		// Tag-style shortcut: type a few letters, press Tab (or Enter) to
		// turn the top matching option into a chip immediately, without
		// having to reach for the mouse — same idea as an email "To:" field.
		// When opts.allowCustom is set and nothing matches, the typed text
		// itself becomes a brand-new tag instead of being dropped. Typed (or
		// pasted-without-triggering-the-paste-handler-above) text containing
		// a comma is split into one tag per part instead.
		search.addEventListener( 'keydown', function ( event ) {
			if ( 'Tab' !== event.key && 'Enter' !== event.key ) {
				return;
			}

			var query = search.value.trim();

			if ( '' === query ) {
				return;
			}

			if ( query.indexOf( ',' ) !== -1 ) {
				event.preventDefault();
				addFromCommaSeparatedText( query );
				resetSearch();

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

			resetSearch();
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! wrapper.contains( event.target ) ) {
				closeList( false );
			}
		} );

		/**
		 * Opt-in (opts.copyCut) Copy/Cut toolbar — two buttons above the chip
		 * area. Unlike the Ctrl+C/Ctrl+X shortcut further up (which always
		 * acts on every chip), clicking either one opens a checklist so the
		 * visitor can pick exactly which tags to copy or cut before
		 * confirming, then writes them to the clipboard as one
		 * comma-separated string (chosen tags at a time — see
		 * copyTextToClipboard() above).
		 *
		 * @return {void}
		 */
		function initCopyCutToolbar() {
			var toolbar = document.createElement( 'div' );
			toolbar.className = 'dak-multiselect-toolbar';

			var copyButton = document.createElement( 'button' );
			copyButton.type = 'button';
			copyButton.className = 'dak-multiselect-tool';
			copyButton.setAttribute( 'aria-label', 'Copy tags' );
			copyButton.title = 'Copy tags…';
			copyButton.innerHTML = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5"/><path d="M13 7V4.5A1.5 1.5 0 0 0 11.5 3h-8A1.5 1.5 0 0 0 2 4.5v8A1.5 1.5 0 0 0 3.5 14H6"/></svg><span>Copy</span>';

			var cutButton = document.createElement( 'button' );
			cutButton.type = 'button';
			cutButton.className = 'dak-multiselect-tool';
			cutButton.setAttribute( 'aria-label', 'Cut tags' );
			cutButton.title = 'Cut tags…';
			cutButton.innerHTML = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="6" r="2"/><circle cx="6" cy="14" r="2"/><path d="M7.5 7.5L16 16M16 4L7.5 12.5"/></svg><span>Cut</span>';

			toolbar.appendChild( copyButton );
			toolbar.appendChild( cutButton );
			wrapper.insertBefore( toolbar, control );

			var panel = document.createElement( 'div' );
			panel.className = 'dak-multiselect-copycut-panel dak-hidden';
			wrapper.appendChild( panel );

			copyButton.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				openPanel( 'copy' );
			} );

			cutButton.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				openPanel( 'cut' );
			} );

			panel.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
			} );

			document.addEventListener( 'click', function ( event ) {
				if ( event.target !== copyButton && event.target !== cutButton && ! panel.contains( event.target ) ) {
					closePanel();
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key ) {
					closePanel();
				}
			} );

			function closePanel() {
				panel.classList.add( 'dak-hidden' );
			}

			/**
			 * Rebuilds and shows the checklist against whatever's currently
			 * selected — built fresh every time rather than kept in sync, since
			 * chips can change any time the panel isn't open.
			 *
			 * @param {'copy'|'cut'} mode
			 * @return {void}
			 */
			function openPanel( mode ) {
				var selectedOptions = Array.prototype.filter.call( select.options, function ( option ) {
					return option.selected;
				} );

				if ( ! selectedOptions.length ) {
					return;
				}

				dropdown.classList.add( 'dak-hidden' );
				panel.innerHTML = '';

				var header = document.createElement( 'p' );
				header.className = 'dak-multiselect-copycut-header';
				header.textContent = 'copy' === mode ? 'Select tags to copy:' : 'Select tags to cut:';
				panel.appendChild( header );

				var list = document.createElement( 'div' );
				list.className = 'dak-multiselect-copycut-list';
				panel.appendChild( list );

				var selectAllRow = document.createElement( 'label' );
				selectAllRow.className = 'dak-multiselect-copycut-row dak-multiselect-copycut-select-all';

				var selectAllCheckbox = document.createElement( 'input' );
				selectAllCheckbox.type = 'checkbox';
				selectAllCheckbox.checked = true;

				var selectAllText = document.createElement( 'span' );
				selectAllText.textContent = 'Select all';

				selectAllRow.appendChild( selectAllCheckbox );
				selectAllRow.appendChild( selectAllText );
				list.appendChild( selectAllRow );

				var entries = selectedOptions.map( function ( option ) {
					var row = document.createElement( 'label' );
					row.className = 'dak-multiselect-copycut-row';

					var checkbox = document.createElement( 'input' );
					checkbox.type = 'checkbox';
					checkbox.checked = true;

					var text = document.createElement( 'span' );
					text.textContent = option.textContent;

					row.appendChild( checkbox );
					row.appendChild( text );
					list.appendChild( row );

					checkbox.addEventListener( 'change', function () {
						selectAllCheckbox.checked = entries.every( function ( entry ) {
							return entry.checkbox.checked;
						} );
						updateConfirmState();
					} );

					return { option: option, checkbox: checkbox };
				} );

				selectAllCheckbox.addEventListener( 'change', function () {
					entries.forEach( function ( entry ) {
						entry.checkbox.checked = selectAllCheckbox.checked;
					} );
					updateConfirmState();
				} );

				var actions = document.createElement( 'div' );
				actions.className = 'dak-multiselect-copycut-actions';

				var cancelButton = document.createElement( 'button' );
				cancelButton.type = 'button';
				cancelButton.className = 'dak-button dak-button-secondary dak-button-sm';
				cancelButton.textContent = 'Cancel';
				cancelButton.addEventListener( 'click', closePanel );

				var confirmButton = document.createElement( 'button' );
				confirmButton.type = 'button';
				confirmButton.className = 'dak-button dak-button-primary dak-button-sm';

				confirmButton.addEventListener( 'click', function () {
					var chosen = entries.filter( function ( entry ) {
						return entry.checkbox.checked;
					} );

					if ( ! chosen.length ) {
						return;
					}

					copyTextToClipboard(
						chosen.map( function ( entry ) {
							return entry.option.textContent;
						} ).join( ', ' )
					);

					if ( 'cut' === mode ) {
						chosen.forEach( function ( entry ) {
							entry.option.selected = false;
							deselectOptionInList( entry.option.value );
						} );

						renderChips();
						select.dispatchEvent( new Event( 'change' ) );
					}

					closePanel();
				} );

				actions.appendChild( cancelButton );
				actions.appendChild( confirmButton );
				panel.appendChild( actions );

				function updateConfirmState() {
					var checkedCount = entries.filter( function ( entry ) {
						return entry.checkbox.checked;
					} ).length;

					confirmButton.textContent = ( 'copy' === mode ? 'Copy ' : 'Cut ' ) + checkedCount;
					confirmButton.disabled = 0 === checkedCount;
				}

				updateConfirmState();
				panel.classList.remove( 'dak-hidden' );
			}
		}

		if ( opts.copyCut ) {
			initCopyCutToolbar();
		}

		renderChips();
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
