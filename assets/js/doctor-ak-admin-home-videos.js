/**
 * Doctor AK Portal — dashboard "Videos" section editor (admin and
 * receptionist; see templates/dashboard/partials/admin-videos.php).
 *
 * One row per video: a preview (the uploaded thumbnail, or the video's own
 * first frame), a title, Upload/Replace video, Thumbnail, move up/down and
 * remove. Every upload, title change, reorder and removal saves the whole
 * list straight away (persistRows()) — what staff see here is what the home
 * page (first few, marked "On home page") and the Videos page (all) show.
 * window.dakHomeVideosEditor.collectRows() stays available for any page
 * that saves the list as part of a bigger form.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var editor = document.querySelector( '[data-home-videos-editor]' );

		if ( ! editor || ! window.dakHomeVideos ) {
			return;
		}

		var rowsContainer = editor.querySelector( '[data-home-videos-rows]' );
		var addButton = editor.querySelector( '[data-home-videos-add-row]' );
		var countEl = editor.querySelector( '[data-home-videos-count]' );
		var emptyEl = editor.querySelector( '[data-home-videos-empty]' );
		var homeLimit = parseInt( editor.getAttribute( 'data-home-limit' ), 10 ) || 8;
		var maxVideos = parseInt( editor.getAttribute( 'data-max-videos' ), 10 ) || window.dakHomeVideos.maxVideos || 60;

		if ( ! rowsContainer || ! addButton ) {
			return;
		}

		( window.dakHomeVideos.rows || [] ).forEach( function ( row ) {
			addRow( rowsContainer, row );
		} );

		refreshState();

		addButton.addEventListener( 'click', function () {
			if ( rows().length >= maxVideos ) {
				return;
			}

			var row = addRow( rowsContainer, {} );
			refreshState();

			// Straight to choosing the file.
			var picker = row.querySelector( '[data-home-video-file]' );

			if ( picker ) {
				picker.click();
			}
		} );

		rowsContainer.addEventListener( 'click', function ( event ) {
			var row = event.target.closest( '[data-home-video-row]' );

			if ( ! row ) {
				return;
			}

			if ( event.target.closest( '[data-home-video-remove]' ) ) {
				var title = row.querySelector( '[data-home-video-title]' ).value;

				if ( row.getAttribute( 'data-video-url' ) && ! window.confirm( title ? 'Remove “' + title + '” from the website?' : 'Remove this video from the website?' ) ) {
					return;
				}

				row.remove();
				refreshState();
				persistRows();
				return;
			}

			var move = event.target.closest( '[data-home-video-move]' );

			if ( move ) {
				var up = 'up' === move.getAttribute( 'data-home-video-move' );
				var sibling = up ? row.previousElementSibling : row.nextElementSibling;

				if ( sibling ) {
					rowsContainer.insertBefore( row, up ? sibling : sibling.nextElementSibling );
					refreshState();
					move.focus();
					persistRows();
				}
			}
		} );

		rowsContainer.addEventListener( 'change', function ( event ) {
			if ( event.target.hasAttribute( 'data-home-video-title' ) ) {
				persistRows();
			}
		} );

		function rows() {
			return Array.prototype.slice.call( rowsContainer.querySelectorAll( '[data-home-video-row]' ) );
		}

		/**
		 * Numbers rows, marks the ones the home page shows, disables the
		 * arrows that can't move, and updates the count / empty / limit state.
		 */
		function refreshState() {
			var all = rows();
			var saved = all.filter( function ( row ) {
				return !! row.getAttribute( 'data-video-url' );
			} );

			all.forEach( function ( row, index ) {
				var badge = row.querySelector( '[data-home-video-badge]' );
				var savedIndex = saved.indexOf( row );

				row.querySelector( '[data-home-video-number]' ).textContent = String( index + 1 );
				row.querySelector( '[data-home-video-move="up"]' ).disabled = 0 === index;
				row.querySelector( '[data-home-video-move="down"]' ).disabled = index === all.length - 1;
				badge.hidden = savedIndex < 0 || savedIndex >= homeLimit;
			} );

			if ( countEl ) {
				countEl.textContent = saved.length + ( 1 === saved.length ? ' video' : ' videos' ) + ' · first ' + homeLimit + ' shown on the home page';
			}

			if ( emptyEl ) {
				emptyEl.hidden = all.length > 0;
			}

			addButton.disabled = all.length >= maxVideos;
			addButton.title = addButton.disabled ? 'You can add up to ' + maxVideos + ' videos.' : '';
		}

		/**
		 * @return {Array<Object>} { title, video_url, poster_url } for every
		 *   row that has a video uploaded, in display order.
		 */
		function collectRows() {
			return rows()
				.map( function ( row ) {
					return {
						title: row.querySelector( '[data-home-video-title]' ).value,
						video_url: row.getAttribute( 'data-video-url' ) || '',
						poster_url: row.getAttribute( 'data-poster-url' ) || '',
					};
				} )
				.filter( function ( row ) {
					return '' !== row.video_url;
				} );
		}

		/**
		 * Saves the current list immediately.
		 *
		 * @return {Promise<Object>} Resolves with the parsed JSON response, never rejects.
		 */
		function persistRows() {
			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_home_videos_save' );
			formData.append( 'nonce', window.dakHomeVideos.nonce );
			formData.append( 'rows', JSON.stringify( collectRows() ) );

			return fetch( window.dakHomeVideos.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.catch( function () {
					return { success: false };
				} );
		}

		window.dakHomeVideosEditor = {
			collectRows: collectRows,
		};

		/**
		 * Builds and appends one row, optionally pre-filled.
		 *
		 * @param {HTMLElement} container Rows container.
		 * @param {Object}      data      { title, video_url, poster_url }.
		 * @return {HTMLElement} The row.
		 */
		function addRow( container, data ) {
			var row = document.createElement( 'div' );
			row.className = 'dak-home-video-row';
			row.setAttribute( 'data-home-video-row', '' );

			if ( data.video_url ) {
				row.setAttribute( 'data-video-url', data.video_url );
			}

			if ( data.poster_url ) {
				row.setAttribute( 'data-poster-url', data.poster_url );
			}

			var number = document.createElement( 'span' );
			number.className = 'dak-home-video-number';
			number.setAttribute( 'data-home-video-number', '' );
			number.setAttribute( 'aria-hidden', 'true' );

			row.appendChild( number );
			row.appendChild( buildPreview( row, data ) );
			row.appendChild( buildFields( row, data ) );
			row.appendChild( buildActions() );

			container.appendChild( row );

			return row;
		}

		function buildPreview( row, data ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'dak-home-video-poster-picker';

			var preview = document.createElement( 'span' );
			preview.className = 'dak-home-video-poster-preview';
			preview.setAttribute( 'data-home-video-preview', '' );
			fillPreview( preview, data.poster_url, data.video_url );

			var badge = document.createElement( 'span' );
			badge.className = 'dak-home-video-badge';
			badge.setAttribute( 'data-home-video-badge', '' );
			badge.textContent = 'On home page';
			badge.hidden = true;

			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'dak-button dak-button-secondary dak-button-sm';
			button.textContent = data.poster_url ? 'Change thumbnail' : 'Add thumbnail';

			var input = document.createElement( 'input' );
			input.type = 'file';
			input.accept = 'image/png,image/jpeg,image/webp';
			input.className = 'dak-hidden';

			button.addEventListener( 'click', function () {
				input.click();
			} );

			input.addEventListener( 'change', function () {
				if ( ! input.files || ! input.files[ 0 ] ) {
					return;
				}

				button.disabled = true;

				uploadFile( 'doctor_ak_admin_home_video_poster_upload', 'poster', input.files[ 0 ] )
					.then( function ( result ) {
						button.disabled = false;

						if ( result.success ) {
							row.setAttribute( 'data-poster-url', result.data.poster_url );
							fillPreview( preview, result.data.poster_url, row.getAttribute( 'data-video-url' ) );
							button.textContent = 'Change thumbnail';
							persistRows();
						} else {
							window.alert( ( result.data && result.data.message ) || 'Thumbnail upload failed.' );
						}
					} );
			} );

			wrap.appendChild( preview );
			wrap.appendChild( badge );
			wrap.appendChild( button );
			wrap.appendChild( input );

			return wrap;
		}

		/**
		 * Thumbnail if there is one, otherwise the video's first frame.
		 */
		function fillPreview( preview, posterUrl, videoUrl ) {
			preview.innerHTML = '';

			if ( posterUrl ) {
				var img = document.createElement( 'img' );
				img.src = posterUrl;
				img.alt = '';
				preview.appendChild( img );
				return;
			}

			if ( videoUrl ) {
				var video = document.createElement( 'video' );
				video.src = videoUrl + '#t=0.5';
				video.muted = true;
				video.preload = 'metadata';
				video.setAttribute( 'playsinline', '' );
				video.setAttribute( 'aria-hidden', 'true' );
				preview.appendChild( video );
				return;
			}

			var placeholder = document.createElement( 'span' );
			placeholder.className = 'dak-home-video-poster-empty';
			placeholder.textContent = 'No video yet';
			preview.appendChild( placeholder );
		}

		function buildFields( row, data ) {
			var fields = document.createElement( 'div' );
			fields.className = 'dak-home-video-fields';

			var label = document.createElement( 'label' );
			label.className = 'dak-home-video-title-label';
			label.textContent = 'Title (optional)';

			var titleInput = document.createElement( 'input' );
			titleInput.type = 'text';
			titleInput.maxLength = 120;
			titleInput.placeholder = 'e.g. Inside our endoscopy suite';
			titleInput.value = data.title || '';
			titleInput.setAttribute( 'data-home-video-title', '' );
			label.appendChild( titleInput );

			fields.appendChild( label );
			fields.appendChild( buildVideoPicker( row, data.video_url ) );

			return fields;
		}

		function buildVideoPicker( row, videoUrl ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'dak-home-video-picker';

			var status = document.createElement( 'span' );
			status.className = 'dak-home-video-status';
			status.setAttribute( 'aria-live', 'polite' );
			status.textContent = videoUrl ? fileNameOf( videoUrl ) : 'No video uploaded yet';

			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'dak-button dak-button-secondary dak-button-sm';
			button.textContent = videoUrl ? 'Replace video' : 'Upload video';

			var input = document.createElement( 'input' );
			input.type = 'file';
			input.accept = 'video/mp4,video/webm,video/quicktime,video/ogg';
			input.className = 'dak-hidden';
			input.setAttribute( 'data-home-video-file', '' );

			button.addEventListener( 'click', function () {
				input.click();
			} );

			input.addEventListener( 'change', function () {
				if ( ! input.files || ! input.files[ 0 ] ) {
					return;
				}

				status.textContent = 'Uploading ' + input.files[ 0 ].name + '…';
				button.disabled = true;

				uploadFile( 'doctor_ak_admin_home_video_upload', 'video', input.files[ 0 ] )
					.then( function ( result ) {
						button.disabled = false;

						if ( result.success ) {
							row.setAttribute( 'data-video-url', result.data.video_url );
							button.textContent = 'Replace video';
							fillPreview( row.querySelector( '[data-home-video-preview]' ), row.getAttribute( 'data-poster-url' ), result.data.video_url );
							refreshState();
							persistRows().then( function ( saveResult ) {
								status.textContent = saveResult.success
									? fileNameOf( result.data.video_url ) + ' — saved'
									: fileNameOf( result.data.video_url ) + ' — uploaded, but saving failed. Try again.';
							} );
						} else {
							status.textContent = ( result.data && result.data.message ) || 'Upload failed. The file may be too large for the server.';
						}
					} );
			} );

			wrap.appendChild( button );
			wrap.appendChild( status );
			wrap.appendChild( input );

			return wrap;
		}

		function buildActions() {
			var actions = document.createElement( 'div' );
			actions.className = 'dak-home-video-actions';

			[ [ 'up', 'Move up', '↑' ], [ 'down', 'Move down', '↓' ] ].forEach( function ( spec ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'dak-home-video-icon-btn';
				b.setAttribute( 'data-home-video-move', spec[0] );
				b.setAttribute( 'aria-label', spec[1] );
				b.title = spec[1];
				b.textContent = spec[2];
				actions.appendChild( b );
			} );

			var removeButton = document.createElement( 'button' );
			removeButton.type = 'button';
			removeButton.className = 'dak-home-video-icon-btn dak-home-video-remove';
			removeButton.setAttribute( 'data-home-video-remove', '' );
			removeButton.setAttribute( 'aria-label', 'Remove video' );
			removeButton.title = 'Remove video';
			removeButton.textContent = '×';
			actions.appendChild( removeButton );

			return actions;
		}

		function fileNameOf( url ) {
			return decodeURIComponent( url.split( '?' )[ 0 ].split( '/' ).pop() );
		}

		/**
		 * @param {string} action        AJAX action name.
		 * @param {string} fileFieldName Field name the handler expects ('video' or 'poster').
		 * @param {File}   file          Selected file.
		 * @return {Promise<Object>} Resolves with the parsed JSON response, never rejects.
		 */
		function uploadFile( action, fileFieldName, file ) {
			var formData = new FormData();
			formData.append( 'action', action );
			formData.append( 'nonce', window.dakHomeVideos.nonce );
			formData.append( fileFieldName, file );

			return fetch( window.dakHomeVideos.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.catch( function () {
					return { success: false };
				} );
		}
	} );
} )();
