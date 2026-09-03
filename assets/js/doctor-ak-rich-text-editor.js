/**
 * Doctor AK Portal — Reusable rich-text (contenteditable) editor toolbar.
 *
 * Wires every `[data-rich-text]` container found on the page: each toolbar
 * button carries `data-rte-command` (a document.execCommand() command name —
 * 'bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList',
 * 'createLink', 'removeFormat') and acts on the container's own
 * `.dak-rich-text-editor` (a plain contenteditable div — no external
 * library, matching this plugin's hand-rolled-widget convention elsewhere,
 * e.g. doctor-ak-searchable-select.js).
 *
 * The editor itself is just a contenteditable element. Two ways a page can
 * read/write its content, both supported:
 * - Give it a real id and read/write `.innerHTML` directly (e.g. the admin
 *   "Add/Edit Service" modal's JS-driven form — see doctor-ak-admin-services.js).
 * - Pair it with `<input type="hidden" name="..." data-rich-text-value>` in
 *   the same `[data-rich-text]` container, for a plain HTML form submitted
 *   via `new FormData(form)` — a contenteditable div has no `name`/`.value`
 *   of its own, so FormData() can't see it directly. This script keeps that
 *   hidden input's value mirroring the editor's HTML on every edit, so the
 *   rest of the form's existing FormData(form) submission code needs no
 *   changes (e.g. registration/profile/admin "Add/Edit Doctor" forms' own
 *   'expertise' field).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-rich-text]' ).forEach( wireEditor );
	} );

	function wireEditor( container ) {
		var editor = container.querySelector( '.dak-rich-text-editor' );
		var hiddenInput = container.querySelector( '[data-rich-text-value]' );

		if ( ! editor ) {
			return;
		}

		function syncHiddenInput() {
			if ( hiddenInput ) {
				hiddenInput.value = editor.innerHTML;
			}
		}

		if ( hiddenInput ) {
			editor.addEventListener( 'input', syncHiddenInput );
		}

		container.querySelectorAll( '[data-rte-command]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				editor.focus();

				var command = button.getAttribute( 'data-rte-command' );

				if ( 'createLink' === command ) {
					var url = window.prompt( 'Link URL' ); // eslint-disable-line no-alert -- no custom prompt component exists in this plugin; matches the browser-native confirm()/prompt() already used elsewhere (e.g. delete confirmations).

					if ( ! url ) {
						return;
					}

					document.execCommand( 'createLink', false, url );
					syncHiddenInput();
					return;
				}

				document.execCommand( command, false, null );
				syncHiddenInput();
			} );
		} );
	}
} )();
