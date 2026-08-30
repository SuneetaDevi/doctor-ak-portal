/**
 * Doctor AK Portal — Admin dashboard "Settings" page: "Home page videos"
 * repeatable-row editor (see templates/dashboard/partials/admin-settings-section.php).
 *
 * Each row uploads its video/poster file immediately on selection (same
 * "upload now, save the resulting URL" pattern as the Clinic Branding logo —
 * see doctor-ak-admin-clinic-branding.js), storing the returned URL on the
 * row itself, then immediately persists the full row set to the database
 * (persistRows()) — a video is live on the Home page as soon as it finishes
 * uploading, without needing the page's separate combined "Save Settings"
 * button. That button still also saves the current rows (see
 * window.dakHomeVideosEditor.collectRows(), called from
 * doctor-ak-admin-settings-save.js) so editing a title and clicking Save
 * Settings works too, but it's no longer required just to make an uploaded
 * video appear.
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

		if ( ! rowsContainer || ! addButton ) {
			return;
		}

		( window.dakHomeVideos.rows || [] ).forEach( function ( row ) {
			addRow( rowsContainer, row );
		} );

		addButton.addEventListener( 'click', function () {
			addRow( rowsContainer, {} );
		} );

		rowsContainer.addEventListener( 'click', function ( event ) {
			var removeButton = event.target.closest( '[data-home-video-remove]' );

			if ( removeButton ) {
				var row = removeButton.closest( '[data-home-video-row]' );

				if ( row ) {
					row.remove();
					persistRows();
				}
			}
		} );

		rowsContainer.addEventListener( 'change', function ( event ) {
			if ( event.target.hasAttribute( 'data-home-video-title' ) ) {
				persistRows();
			}
		} );

		/**
		 * @return {Array<Object>} { title, video_url, poster_url } for every
		 *   row that has a video uploaded — rows still uploading or with no
		 *   video yet are skipped.
		 */
		function collectRows() {
			return Array.prototype.slice.call( rowsContainer.querySelectorAll( '[data-home-video-row]' ) )
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
		 * Saves the current row set immediately — called after every
		 * upload/removal/title edit so a video appears on the Home page as
		 * soon as it finishes uploading, with no separate save step needed.
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
		 * @return {void}
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

			row.appendChild( buildPosterPicker( row, data.poster_url ) );
			row.appendChild( buildFields( row, data ) );
			row.appendChild( buildRemoveButton() );

			container.appendChild( row );
		}

		function buildFields( row, data ) {
			var fields = document.createElement( 'div' );
			fields.className = 'dak-home-video-fields';

			var titleInput = document.createElement( 'input' );
			titleInput.type = 'text';
			titleInput.placeholder = 'Video title (optional)';
			titleInput.value = data.title || '';
			titleInput.setAttribute( 'data-home-video-title', '' );
			fields.appendChild( titleInput );

			fields.appendChild( buildVideoPicker( row, data.video_url ) );

			return fields;
		}

		function buildVideoPicker( row, videoUrl ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'dak-home-video-picker';

			var status = document.createElement( 'span' );
			status.className = 'dak-home-video-status';
			status.textContent = videoUrl ? fileNameOf( videoUrl ) : 'No video uploaded';

			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'dak-button dak-button-secondary dak-button-sm';
			button.textContent = videoUrl ? 'Replace video' : 'Upload video';

			var input = document.createElement( 'input' );
			input.type = 'file';
			input.accept = 'video/mp4,video/webm,video/quicktime,video/ogg';
			input.className = 'dak-hidden';

			button.addEventListener( 'click', function () {
				input.click();
			} );

			input.addEventListener( 'change', function () {
				if ( ! input.files || ! input.files[ 0 ] ) {
					return;
				}

				status.textContent = 'Uploading…';
				button.disabled = true;

				uploadFile( 'doctor_ak_admin_home_video_upload', 'video', input.files[ 0 ] )
					.then( function ( result ) {
						button.disabled = false;

						if ( result.success ) {
							row.setAttribute( 'data-video-url', result.data.video_url );
							status.textContent = fileNameOf( result.data.video_url );
							button.textContent = 'Replace video';
							persistRows().then( function ( saveResult ) {
								status.textContent = saveResult.success
									? fileNameOf( result.data.video_url ) + ' — saved'
									: fileNameOf( result.data.video_url ) + ' — uploaded, but saving failed. Try again.';
							} );
						} else {
							status.textContent = ( result.data && result.data.message ) || 'Upload failed.';
						}
					} );
			} );

			wrap.appendChild( button );
			wrap.appendChild( status );
			wrap.appendChild( input );

			return wrap;
		}

		function buildPosterPicker( row, posterUrl ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'dak-home-video-poster-picker';

			var preview = document.createElement( 'span' );
			preview.className = 'dak-home-video-poster-preview';
			preview.appendChild( posterImage( posterUrl ) );

			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'dak-button dak-button-secondary dak-button-sm';
			button.textContent = 'Thumbnail';

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
							preview.innerHTML = '';
							preview.appendChild( posterImage( result.data.poster_url ) );
							persistRows();
						}
					} );
			} );

			wrap.appendChild( preview );
			wrap.appendChild( button );
			wrap.appendChild( input );

			return wrap;
		}

		function posterImage( url ) {
			if ( url ) {
				var img = document.createElement( 'img' );
				img.src = url;
				img.alt = '';
				return img;
			}

			var placeholder = document.createElement( 'span' );
			placeholder.textContent = '🎬';
			return placeholder;
		}

		function buildRemoveButton() {
			var removeButton = document.createElement( 'button' );
			removeButton.type = 'button';
			removeButton.className = 'dak-home-video-remove';
			removeButton.setAttribute( 'data-home-video-remove', '' );
			removeButton.setAttribute( 'aria-label', 'Remove video' );
			removeButton.textContent = '×';

			return removeButton;
		}

		function fileNameOf( url ) {
			return url.split( '/' ).pop();
		}

		/**
		 * @param {string} action AJAX action name.
		 * @param {string} fileFieldName Field name the handler expects ('video' or 'poster').
		 * @param {File}   file   Selected file.
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
