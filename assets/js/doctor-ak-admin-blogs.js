/**
 * Doctor AK Portal — Admin "Blogs" table.
 *
 * Add/Edit happens on its own full-screen page (see
 * admin-blog-form-screen.php / doctor-ak-admin-blog-form.js) so this file
 * only wires the table's Delete action — doctor_ak_admin_blog_delete,
 * checked against the admin nonce. Mirrors doctor-ak-admin-services.js.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAdminBlogs ) {
			return;
		}

		wireDelete();
	} );

	function wireDelete() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-admin-blog-delete]' );

			if ( ! trigger ) {
				return;
			}

			if ( ! window.confirm( 'Delete this post? This cannot be undone.' ) ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_blog_delete' );
			formData.append( 'nonce', window.dakAdminBlogs.nonce );
			formData.append( 'blog_id', trigger.getAttribute( 'data-blog-id' ) );

			fetch( window.dakAdminBlogs.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}

					window.alert( ( result.data && result.data.message ) || 'Something went wrong. Please try again.' );
				} )
				.catch( function () {
					window.alert( 'Something went wrong. Please try again.' );
				} );
		} );
	}
} )();
