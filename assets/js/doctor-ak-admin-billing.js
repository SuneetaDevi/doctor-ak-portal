/**
 * Doctor AK Portal — Admin dashboard "Billing" page: create a settlement for
 * the currently-filtered doctor's outstanding balance, and mark an existing
 * settlement Paid/Received.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.dakAdminBilling ) {
			return;
		}

		var panel = document.getElementById( 'dak-billing-settlement-panel' );
		var createForm = document.getElementById( 'dak-billing-create-settlement-form' );

		if ( createForm && panel ) {
			createForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				hide( 'dak-billing-settlement-error' );
				hide( 'dak-billing-settlement-success' );

				var formData = new FormData();
				formData.append( 'action', 'doctor_ak_admin_create_settlement' );
				formData.append( 'nonce', window.dakAdminBilling.nonce );
				formData.append( 'doctor_id', panel.getAttribute( 'data-doctor-id' ) );
				formData.append( 'period_start', valueOf( 'dak-settlement-period-start' ) );
				formData.append( 'period_end', valueOf( 'dak-settlement-period-end' ) );
				formData.append( 'notes', valueOf( 'dak-settlement-notes' ) );

				post( formData ).then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}
					show( 'dak-billing-settlement-error', errorMessage( result ) );
				} );
			} );
		}

		var detailsModal = document.getElementById( 'dak-billing-details-modal' );
		var detailsClose = document.getElementById( 'dak-billing-details-close' );
		var detailsBody = document.getElementById( 'dak-billing-details-body' );
		var detailsTitle = document.getElementById( 'dak-billing-details-title' );

		if ( detailsModal ) {
			document.querySelectorAll( '.dak-billing-view-details' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					openDetailsModal(
						button.getAttribute( 'data-doctor-id' ),
						button.getAttribute( 'data-clinic-id' ),
						button.getAttribute( 'data-doctor-name' ),
						button.getAttribute( 'data-clinic-name' )
					);
				} );
			} );

			if ( detailsClose ) {
				detailsClose.addEventListener( 'click', closeDetailsModal );
			}

			var detailsOverlay = document.getElementById( 'dak-billing-details-overlay' );

			if ( detailsOverlay ) {
				detailsOverlay.addEventListener( 'click', closeDetailsModal );
			}

			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && detailsModal.classList.contains( 'is-open' ) ) {
					closeDetailsModal();
				}
			} );
		}

		function openDetailsModal( doctorId, clinicId, doctorName, clinicName ) {
			detailsTitle.textContent = doctorName + ' — ' + clinicName;
			detailsBody.innerHTML = '<p class="dak-empty-state">Loading…</p>';
			detailsModal.classList.add( 'is-open' );
			detailsModal.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'dak-modal-open' );

			var formData = new FormData();
			formData.append( 'action', 'doctor_ak_admin_get_doctor_clinic_details' );
			formData.append( 'nonce', window.dakAdminBilling.nonce );
			formData.append( 'doctor_id', doctorId );
			formData.append( 'clinic_id', clinicId );
			formData.append( 'date_from', valueOf( 'dak-billing-date-from' ) );
			formData.append( 'date_to', valueOf( 'dak-billing-date-to' ) );

			post( formData ).then( function ( result ) {
				if ( ! result.success ) {
					detailsBody.innerHTML = '<p class="dak-empty-state">' + escapeHtml( errorMessage( result ) ) + '</p>';
					return;
				}

				var items = result.data.items || [];

				if ( 0 === items.length ) {
					detailsBody.innerHTML = '<p class="dak-empty-state">No appointments in this period.</p>';
					return;
				}

				var rows = items.map( function ( item ) {
					return '<div class="dak-admin-record-row"><div class="dak-admin-record-row-main">' +
						'<span class="dak-admin-record-row-info"><strong>' + escapeHtml( item.label ) + '</strong>' +
						'<span class="dak-admin-record-row-id">' + item.quantity + ' &times; PKR ' + formatNumber( item.avg_price ) + '</span></span>' +
						'<span class="dak-admin-record-row-amount">PKR ' + formatNumber( item.total_amount ) + '</span>' +
						'</div></div>';
				} );

				detailsBody.innerHTML = rows.join( '' );
			} );
		}

		function closeDetailsModal() {
			detailsModal.classList.remove( 'is-open' );
			detailsModal.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'dak-modal-open' );
		}

		function escapeHtml( text ) {
			var div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		}

		function formatNumber( n ) {
			return Math.round( n ).toLocaleString();
		}

		document.querySelectorAll( '.dak-billing-mark-paid, .dak-billing-mark-received' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				hide( 'dak-billing-settlement-error' );
				hide( 'dak-billing-settlement-success' );

				var formData = new FormData();
				formData.append( 'action', button.classList.contains( 'dak-billing-mark-paid' ) ? 'doctor_ak_admin_settlement_mark_paid' : 'doctor_ak_admin_settlement_mark_received' );
				formData.append( 'nonce', window.dakAdminBilling.nonce );
				formData.append( 'settlement_id', button.getAttribute( 'data-settlement-id' ) );

				button.disabled = true;

				post( formData ).then( function ( result ) {
					if ( result.success ) {
						window.location.reload();
						return;
					}
					button.disabled = false;
					show( 'dak-billing-settlement-error', errorMessage( result ) );
				} );
			} );
		} );

		function post( formData ) {
			return fetch( window.dakAdminBilling.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.catch( function () {
					return { success: false };
				} );
		}

		function errorMessage( result ) {
			return ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
		}

		function valueOf( id ) {
			var el = document.getElementById( id );
			return el ? el.value : '';
		}

		function hide( id ) {
			var el = document.getElementById( id );
			if ( el ) {
				el.classList.add( 'dak-hidden' );
			}
		}

		function show( id, message ) {
			var el = document.getElementById( id );
			if ( el ) {
				el.textContent = message;
				el.classList.remove( 'dak-hidden' );
			}
		}
	} );
} )();
