/**
 * Doctor AK Portal — Videos page ([videos_gallery]): only one video plays at
 * a time — starting one pauses whichever was playing.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var videos = Array.prototype.slice.call( document.querySelectorAll( '[data-dak-gallery-video]' ) );

		videos.forEach( function ( video ) {
			video.addEventListener( 'play', function () {
				videos.forEach( function ( other ) {
					if ( other !== video && ! other.paused ) {
						other.pause();
					}
				} );
			} );
		} );
	} );
} )();
