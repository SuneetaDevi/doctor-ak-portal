<?php
/**
 * AJAX endpoint and assets for the CRM screen (admin dashboard section and
 * the doctor dashboard's CRM tab).
 *
 * @package DoctorAKPortal\Frontend
 */

namespace DoctorAKPortal\Frontend;

use DoctorAKPortal\Includes\Appointments;
use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Crm;
use DoctorAKPortal\Includes\Crm_Email;
use DoctorAKPortal\Includes\Crm_Reports;
use DoctorAKPortal\Includes\Page_Finder;
use DoctorAKPortal\Includes\Phone;
use DoctorAKPortal\Includes\Role_Permissions;
use DoctorAKPortal\Includes\Roles;
use DoctorAKPortal\Includes\Template_Loader;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Crm_Handler
 *
 * One action (`doctor_ak_crm`) with an `op` parameter. Who may do what is
 * checked on the server for every op:
 * - Administrators and receptionists ("staff"): everything.
 * - Doctors: only contacts who booked or visited them; they can view, add
 *   notes and tasks, and set a follow-up date — not change stage, owner,
 *   tags or contact details, merge, delete, bulk-edit, export, email or
 *   see reports. Their timeline shows only their own bookings, visits and
 *   reviews, and no emails.
 * Each role can be switched off under Roles & Permissions ("CRM").
 */
class Crm_Handler {

	const NONCE_ACTION = 'doctor_ak_crm';

	/**
	 * Who's asking: { role: 'admin'|'receptionist'|'doctor', scope: doctor ID or 0 }, or null.
	 *
	 * @return array|null
	 */
	public static function viewer() {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			return null;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return Role_Permissions::is_tab_allowed( Role_Permissions::ADMIN_KEY, 'crm' ) ? array( 'role' => 'admin', 'scope' => 0 ) : null;
		}

		$roles = (array) $user->roles;

		if ( in_array( Roles::RECEPTIONIST_ROLE, $roles, true ) ) {
			return in_array( 'crm', Admin_Dashboard::RECEPTIONIST_ALLOWED_SECTIONS, true ) && Role_Permissions::is_tab_allowed( Roles::RECEPTIONIST_ROLE, 'crm' ) ? array( 'role' => 'receptionist', 'scope' => 0 ) : null;
		}

		if ( in_array( Roles::DOCTOR_ROLE, $roles, true ) ) {
			return Role_Permissions::is_tab_allowed( Roles::DOCTOR_ROLE, 'crm' ) ? array( 'role' => 'doctor', 'scope' => (int) $user->ID ) : null;
		}

		return null;
	}

	/**
	 * Enqueues the screen's script and styles (callers check access first).
	 *
	 * @return void
	 */
	public static function enqueue_screen_assets() {
		$viewer = self::viewer();

		if ( ! $viewer ) {
			return;
		}

		wp_enqueue_style( 'doctor-ak-portal-crm', DOCTOR_AK_PORTAL_URL . 'assets/css/doctor-ak-crm.css', array(), Assets::version( 'assets/css/doctor-ak-crm.css' ) );
		wp_enqueue_script( 'doctor-ak-portal-crm', DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-crm.js', array(), Assets::version( 'assets/js/doctor-ak-crm.js' ), true );

		$staff = 'doctor' !== $viewer['role'];

		wp_localize_script(
			'doctor-ak-portal-crm',
			'dakCrm',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
				'role'       => $viewer['role'],
				'isStaff'    => $staff,
				'userId'     => get_current_user_id(),
				'today'      => current_time( 'Y-m-d' ),
				'stages'     => Crm::stages(),
				'sources'    => Crm::sources(),
				'views'      => Crm::views(),
				'taskStatus' => Crm::task_statuses(),
				'staff'      => self::options( Crm::staff() ),
				'doctors'    => $staff ? self::options( Appointments::doctor_options() ) : array(),
				'tags'       => Crm::all_tags(),
				'imported'   => Crm::backfill_done(),
				'refreshed'  => Crm::refresh_done(),
				'urls'       => self::urls( $viewer ),
				'email'      => $staff ? array(
					'purposes'    => Crm_Email::purposes(),
					'mergeFields' => Crm_Email::merge_fields(),
					'statuses'    => Crm_Email::statuses(),
					'segments'    => Crm_Email::segments(),
					'myEmail'     => wp_get_current_user()->user_email,
					'max'         => Crm_Email::MAX_RECIPIENTS,
				) : null,
			)
		);
	}

	/** id => label map as a list (keeps the order through JSON). */
	private static function options( array $map ) {
		$out = array();

		foreach ( $map as $id => $label ) {
			$out[] = array(
				'id'    => (int) $id,
				'label' => $label,
			);
		}

		return $out;
	}

	/** Where "Open" links in the timeline go. */
	private static function urls( array $viewer ) {
		if ( 'doctor' === $viewer['role'] ) {
			$base = Page_Finder::url_for_shortcode( Doctor_Dashboard::SHORTCODE_TAG );

			return array(
				'appointments' => $base ? add_query_arg( 'tab', 'appointments', $base ) : '',
				'encounter'    => $base ? add_query_arg( 'tab', 'encounter', $base ) : '',
			);
		}

		$base = Page_Finder::url_for_shortcode( Admin_Dashboard::SHORTCODE_TAG );

		return array(
			'appointments' => $base ? add_query_arg( 'section', 'appointments', $base ) : '',
			'encounter'    => $base ? add_query_arg( 'section', 'encounter', $base ) : '',
		);
	}

	/**
	 * The screen's markup.
	 *
	 * @param Template_Loader $loader Template loader.
	 * @return string
	 */
	public static function screen_html( Template_Loader $loader ) {
		$viewer = self::viewer();

		return $viewer ? $loader->get_template( 'dashboard/partials/crm.php', array( 'is_staff' => 'doctor' !== $viewer['role'] ) ) : '';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * Handles every op.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'doctor-ak-portal' ) ), 403 );
		}

		$viewer = self::viewer();

		if ( ! $viewer ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'doctor-ak-portal' ) ), 403 );
		}

		$op      = sanitize_key( $this->text( 'op' ) );
		$staff   = 'doctor' !== $viewer['role'];
		$user_id = get_current_user_id();
		$staff_ops = array( 'create', 'bulk', 'merge', 'export', 'delete', 'templates', 'template_save', 'template_status', 'email_check', 'email_preview', 'email_test', 'email_send', 'campaign_create', 'campaign_process', 'campaigns', 'campaign', 'campaign_cancel', 'campaign_retry', 'opt_out', 'segment_save', 'segment_delete', 'report' );

		if ( ! $staff && in_array( $op, $staff_ops, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Only clinic staff can do that.', 'doctor-ak-portal' ) ), 403 );
		}

		switch ( $op ) {
			case 'sync':
				wp_send_json_success( Crm::backfill_batch( 150 ) );
				break;

			case 'refresh':
				wp_send_json_success( Crm::refresh_batch( 100 ) );
				break;

			case 'report':
				$report = Crm_Reports::build( $this->text( 'from' ), $this->text( 'to' ) );
				$this->fail_if_error( $report );
				wp_send_json_success( $report );
				break;

			case 'list':
				$result = Crm::query( array_merge( $this->filters(), array( 'scope_doctor' => $viewer['scope'] ) ) );
				wp_send_json_success(
					array(
						'rows'   => array_map(
							function ( $c ) use ( $viewer ) {
								return self::shape( $c, $viewer );
							},
							$result['rows']
						),
						'total'  => $result['total'],
						'page'   => $result['page'],
						'pages'  => $result['pages'],
						'counts' => $result['counts'],
					)
				);
				break;

			case 'contact':
				$contact = $this->contact_or_fail( $viewer, true );
				wp_send_json_success( $this->detail( $contact, $viewer ) );
				break;

			case 'create':
				$id = Crm::create_manual(
					array(
						'name'     => $this->text( 'name' ),
						'phone'    => $this->text( 'phone' ),
						'email'    => $this->text( 'email' ),
						'source'   => sanitize_key( $this->text( 'source' ) ),
						'owner_id' => absint( $this->text( 'owner_id' ) ),
					),
					$user_id
				);
				$this->fail_if_error( $id );

				$duplicates = Crm::duplicates( $id );
				wp_send_json_success(
					array(
						'message' => $duplicates ? __( 'Added. Someone with the same phone or email already exists — check “Possible duplicates”.', 'doctor-ak-portal' ) : __( 'Contact added.', 'doctor-ak-portal' ),
						'id'      => $id,
					)
				);
				break;

			case 'update':
				$contact = $this->contact_or_fail( $viewer );
				$fields  = $staff ? $this->posted_fields() : array_intersect_key( $this->posted_fields(), array( 'follow_up_date' => true ) );

				if ( ! $fields ) {
					wp_send_json_error( array( 'message' => __( 'Nothing to change.', 'doctor-ak-portal' ) ) );
				}

				$this->fail_if_error( Crm::update_fields( $contact['id'], $fields, $user_id ) );
				wp_send_json_success(
					array(
						'message' => __( 'Saved.', 'doctor-ak-portal' ),
						'contact' => $this->detail( Crm::find( $contact['id'] ), $viewer ),
					)
				);
				break;

			case 'bulk':
				$this->bulk( $user_id );
				break;

			case 'note':
				$contact = $this->contact_or_fail( $viewer );
				$this->fail_if_error( Crm::add_note( $contact['id'], $this->raw( 'body' ), $user_id ) );
				wp_send_json_success(
					array(
						'message' => __( 'Note added.', 'doctor-ak-portal' ),
						'contact' => $this->detail( Crm::find( $contact['id'] ), $viewer ),
					)
				);
				break;

			case 'task_add':
				$contact = $this->contact_or_fail( $viewer );
				$this->fail_if_error(
					Crm::add_task(
						$contact['id'],
						array(
							'title'       => $this->text( 'title' ),
							'due_date'    => $this->text( 'due_date' ),
							'assignee_id' => absint( $this->text( 'assignee_id' ) ),
						),
						$user_id
					)
				);
				wp_send_json_success(
					array(
						'message' => __( 'Task added.', 'doctor-ak-portal' ),
						'contact' => $this->detail( Crm::find( $contact['id'] ), $viewer ),
					)
				);
				break;

			case 'task_update':
				$task = Crm::find_task( absint( $this->text( 'task_id' ) ) );

				if ( ! $task || ! $this->can_see( Crm::find( (int) $task['contact_id'] ), $viewer ) ) {
					wp_send_json_error( array( 'message' => __( 'That task isn’t available.', 'doctor-ak-portal' ) ), 404 );
				}

				$this->fail_if_error( Crm::update_task( $task['id'], sanitize_key( $this->text( 'status' ) ), $this->raw( 'outcome' ), $user_id ) );
				wp_send_json_success(
					array(
						'message' => __( 'Task updated.', 'doctor-ak-portal' ),
						'contact' => $this->detail( Crm::find( (int) $task['contact_id'] ), $viewer ),
					)
				);
				break;

			case 'tasks':
				$mine = 'all' !== $this->text( 'who' ) || ! $staff;
				wp_send_json_success(
					array(
						'tasks' => Crm::open_tasks( $mine && $staff ? $user_id : 0, $viewer['scope'], '1' === $this->text( 'upcoming' ) ),
					)
				);
				break;

			case 'delete':
				$contact = $this->contact_or_fail( $viewer );
				$this->fail_if_error( Crm::delete_contact( $contact['id'], $user_id ) );
				wp_send_json_success(
					array(
						/* translators: %s: name. */
						'message' => sprintf( __( '%s was deleted from the CRM.', 'doctor-ak-portal' ), $contact['name'] ),
					)
				);
				break;

			case 'merge':
				$target = Crm::find( absint( $this->text( 'id' ) ) );
				$source = Crm::find( absint( $this->text( 'source_id' ) ) );

				if ( ! $target || ! $source ) {
					wp_send_json_error( array( 'message' => __( 'That contact no longer exists.', 'doctor-ak-portal' ) ), 404 );
				}

				$merged = Crm::merge( $target['id'], $source['id'], $user_id );
				$this->fail_if_error( $merged );
				wp_send_json_success(
					array(
						'message' => __( 'Contacts merged.', 'doctor-ak-portal' ),
						'contact' => $this->detail( $merged, $viewer ),
					)
				);
				break;

			case 'export':
				$rows = array();

				for ( $page = 1; $page <= 200; $page++ ) {
					$result = Crm::query( array_merge( $this->filters(), array( 'page' => $page ) ) );
					$rows   = array_merge( $rows, $result['rows'] );

					if ( $page >= $result['pages'] ) {
						break;
					}
				}

				wp_send_json_success(
					array(
						'csv'      => Crm::to_csv( $rows ),
						'filename' => 'crm-contacts-' . current_time( 'Y-m-d' ) . '.csv',
						'count'    => count( $rows ),
					)
				);
				break;

			default:
				$this->email_op( $op, $viewer, $user_id );
		}
	}

	/**
	 * Email ops (staff only — checked in handle()).
	 *
	 * @param string $op      Op.
	 * @param array  $viewer  Viewer.
	 * @param int    $user_id Acting user.
	 * @return void
	 */
	private function email_op( $op, array $viewer, $user_id ) {
		switch ( $op ) {
			case 'templates':
				wp_send_json_success( array( 'templates' => Crm_Email::templates() ) );
				break;

			case 'template_save':
				$id = Crm_Email::save_template( absint( $this->text( 'template_id' ) ), $this->content(), $user_id );
				$this->fail_if_error( $id );
				wp_send_json_success(
					array(
						'message'   => __( 'Template saved.', 'doctor-ak-portal' ),
						'id'        => $id,
						'templates' => Crm_Email::templates(),
					)
				);
				break;

			case 'template_status':
				$active = '1' === $this->text( 'active' );
				$this->fail_if_error( Crm_Email::set_template_status( absint( $this->text( 'template_id' ) ), $active, $user_id ) );
				wp_send_json_success(
					array(
						'message'   => $active ? __( 'Template restored.', 'doctor-ak-portal' ) : __( 'Template archived. Past emails that used it aren’t affected.', 'doctor-ak-portal' ),
						'templates' => Crm_Email::templates(),
					)
				);
				break;

			case 'email_check':
				$contacts = $this->audience();
				$split    = Crm_Email::recipients( $contacts, $this->text( 'purpose' ) );
				wp_send_json_success(
					array(
						'matched' => count( $contacts ),
						'send'    => count( $split['send'] ),
						'counts'  => $split['counts'],
						'sample'  => $split['send'] ? array(
							'id'   => $split['send'][0]['id'],
							'name' => $split['send'][0]['name'],
						) : null,
					)
				);
				break;

			case 'email_preview':
				$content = Crm_Email::clean_content( $this->content() );
				$this->fail_if_error( $content );
				$contact = Crm::find( absint( $this->text( 'contact_id' ) ) );
				wp_send_json_success(
					array_merge(
						Crm_Email::preview( $content, $contact ? $contact : Crm_Email::sample_contact() ),
						array( 'example' => ! $contact )
					)
				);
				break;

			case 'email_test':
				$to = wp_get_current_user()->user_email;
				$this->fail_if_error( Crm_Email::send_test( $this->content(), Crm::find( absint( $this->text( 'contact_id' ) ) ), $to ) );
				/* translators: %s: email address. */
				wp_send_json_success( array( 'message' => sprintf( __( 'Test sent to %s.', 'doctor-ak-portal' ), $to ) ) );
				break;

			case 'email_send':
				$contact = $this->contact_or_fail( $viewer );
				$this->fail_if_error( Crm_Email::send_single( $contact['id'], $this->content(), $user_id ) );
				wp_send_json_success(
					array(
						/* translators: %s: email address. */
						'message' => sprintf( __( 'Email sent to %s.', 'doctor-ak-portal' ), $contact['email'] ),
						'contact' => $this->detail( Crm::find( $contact['id'] ), $viewer ),
					)
				);
				break;

			case 'campaign_create':
				$result = Crm_Email::create_campaign( $this->content(), $this->audience(), $this->text( 'audience_label' ), $user_id );
				$this->fail_if_error( $result );
				wp_send_json_success( $result );
				break;

			case 'campaign_process':
				$id = absint( $this->text( 'campaign_id' ) );
				Crm_Email::process_queue( 10, $id );
				$campaign = Crm_Email::campaign( $id );

				if ( ! $campaign ) {
					wp_send_json_error( array( 'message' => __( 'That email no longer exists.', 'doctor-ak-portal' ) ), 404 );
				}

				unset( $campaign['recipients'] );
				wp_send_json_success( $campaign );
				break;

			case 'campaigns':
				// Picks up anything left waiting (e.g. after reactivation).
				if ( Crm_Email::queued_count() ) {
					Crm_Email::schedule_queue();
				}

				wp_send_json_success( Crm_Email::campaigns( absint( $this->text( 'page' ) ) ) );
				break;

			case 'campaign':
				$campaign = Crm_Email::campaign( absint( $this->text( 'campaign_id' ) ), sanitize_key( $this->text( 'status' ) ), absint( $this->text( 'page' ) ) );

				if ( ! $campaign ) {
					wp_send_json_error( array( 'message' => __( 'That email no longer exists.', 'doctor-ak-portal' ) ), 404 );
				}

				wp_send_json_success( $campaign );
				break;

			case 'campaign_cancel':
				$this->fail_if_error( Crm_Email::cancel_campaign( absint( $this->text( 'campaign_id' ) ) ) );
				wp_send_json_success( array( 'message' => __( 'Stopped. Emails already sent can’t be called back.', 'doctor-ak-portal' ) ) );
				break;

			case 'campaign_retry':
				$n = Crm_Email::retry_failed( absint( $this->text( 'campaign_id' ) ) );
				$this->fail_if_error( $n );
				/* translators: %d: number of emails. */
				wp_send_json_success( array( 'message' => $n ? sprintf( _n( '%d email will be tried again.', '%d emails will be tried again.', $n, 'doctor-ak-portal' ), $n ) : __( 'Nothing to try again.', 'doctor-ak-portal' ) ) );
				break;

			case 'opt_out':
				$contact = $this->contact_or_fail( $viewer );
				$out     = '1' === $this->text( 'out' );
				$this->fail_if_error( Crm_Email::set_opt_out( $contact['id'], $out, 'staff', $user_id ) );
				wp_send_json_success(
					array(
						'message' => $out ? __( 'They won’t get offers and news emails.', 'doctor-ak-portal' ) : __( 'Offers and news emails allowed again.', 'doctor-ak-portal' ),
						'contact' => $this->detail( Crm::find( $contact['id'] ), $viewer ),
					)
				);
				break;

			case 'segment_save':
				$segments = Crm_Email::save_segment( $this->text( 'name' ), sanitize_key( $this->text( 'view' ) ), $this->filters(), $user_id );
				$this->fail_if_error( $segments );
				wp_send_json_success(
					array(
						'message'  => __( 'Segment saved.', 'doctor-ak-portal' ),
						'segments' => $segments,
					)
				);
				break;

			case 'segment_delete':
				wp_send_json_success(
					array(
						'message'  => __( 'Segment deleted.', 'doctor-ak-portal' ),
						'segments' => Crm_Email::delete_segment( sanitize_key( $this->text( 'segment_id' ) ) ),
					)
				);
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown request.', 'doctor-ak-portal' ) ) );
		}
	}

	/** Email content from the request. */
	private function content() {
		return array(
			'name'         => $this->text( 'name' ),
			'purpose'      => sanitize_key( $this->text( 'purpose' ) ),
			'subject'      => $this->text( 'subject' ),
			'heading'      => $this->text( 'heading' ),
			'body'         => $this->raw( 'body' ),
			'button_label' => $this->text( 'button_label' ),
			'button_url'   => $this->raw( 'button_url' ),
			'template_id'  => absint( $this->text( 'template_id' ) ),
		);
	}

	/**
	 * Who a group email goes to: the chosen people (audience=ids), or
	 * everyone matching the list's current view and filters.
	 *
	 * @return array[] Contacts.
	 */
	private function audience() {
		$ids = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); absint()ed.

		if ( 'ids' === $this->text( 'audience' ) && ! $ids ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one person first.', 'doctor-ak-portal' ) ) );
		}

		$contacts = Crm_Email::audience( $this->filters(), 'ids' === $this->text( 'audience' ) ? $ids : array() );
		$this->fail_if_error( $contacts );

		return $contacts;
	}

	/** List filters from the request. */
	private function filters() {
		return array(
			'view'      => sanitize_key( $this->text( 'view' ) ),
			'q'         => $this->text( 'q' ),
			'stage'     => sanitize_key( $this->text( 'stage' ) ),
			'source'    => sanitize_key( $this->text( 'source' ) ),
			'doctor'    => absint( $this->text( 'doctor' ) ),
			'owner'     => '' === $this->text( 'owner' ) ? '' : absint( $this->text( 'owner' ) ),
			'tag'       => $this->text( 'tag' ),
			'date_from'       => $this->text( 'date_from' ),
			'date_to'         => $this->text( 'date_to' ),
			'last_visit_from' => $this->text( 'last_visit_from' ),
			'last_visit_to'   => $this->text( 'last_visit_to' ),
			'sort'            => sanitize_key( $this->text( 'sort' ) ),
			'page'            => absint( $this->text( 'page' ) ),
		);
	}

	/** Editable fields actually posted. */
	private function posted_fields() {
		$out = array();

		foreach ( array( 'name', 'phone', 'email', 'source', 'stage', 'lost_reason', 'owner_id', 'follow_up_date' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle().
				$out[ $key ] = 'owner_id' === $key ? absint( $this->text( $key ) ) : $this->text( $key );
			}
		}

		if ( isset( $_POST['tags'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle().
			$out['tags'] = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['tags'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); sanitized per item.
		}

		return $out;
	}

	/**
	 * Applies one change to several contacts.
	 */
	private function bulk( $user_id ) {
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); absint()ed.
		$action = sanitize_key( $this->text( 'bulk_action' ) );
		$value  = $this->text( 'value' );
		$done   = 0;
		$failed = array();

		foreach ( array_slice( array_unique( $ids ), 0, 500 ) as $id ) {
			$contact = Crm::find( $id );

			if ( ! $contact ) {
				continue;
			}

			switch ( $action ) {
				case 'stage':
					$fields = array( 'stage' => $value );
					break;
				case 'owner':
					$fields = array( 'owner_id' => absint( $value ) );
					break;
				case 'tag_add':
					$fields = array( 'tags' => array_merge( $contact['tags'], array( $value ) ) );
					break;
				case 'tag_remove':
					$fields = array(
						'tags' => array_values(
							array_filter(
								$contact['tags'],
								function ( $t ) use ( $value ) {
									return 0 !== strcasecmp( $t, $value );
								}
							)
						),
					);
					break;
				case 'follow_up':
					$fields = array( 'follow_up_date' => $value );
					break;
				default:
					wp_send_json_error( array( 'message' => __( 'Unknown bulk action.', 'doctor-ak-portal' ) ) );
			}

			$result = Crm::update_fields( $id, $fields, $user_id );

			if ( is_wp_error( $result ) ) {
				$failed[] = $contact['name'] . ': ' . $result->get_error_message();
			} else {
				++$done;
			}
		}

		wp_send_json_success(
			array(
				/* translators: %d: number of contacts. */
				'message' => sprintf( _n( '%d contact updated.', '%d contacts updated.', $done, 'doctor-ak-portal' ), $done ),
				'failed'  => $failed,
			)
		);
	}

	/** Whether this viewer may see this contact. */
	private function can_see( $contact, array $viewer ) {
		return $contact && ( ! $viewer['scope'] || in_array( $viewer['scope'], $contact['doctor_ids'], true ) );
	}

	/**
	 * The contact named in the request, or a 404 for anyone who may not see it.
	 *
	 * @param bool $refresh Rebuild its summary first (opening the panel).
	 */
	private function contact_or_fail( array $viewer, $refresh = false ) {
		$id      = absint( $this->text( 'id' ) );
		$contact = $refresh ? Crm::refresh( $id ) : Crm::find( $id );

		if ( ! $this->can_see( $contact, $viewer ) ) {
			wp_send_json_error( array( 'message' => __( 'That contact isn’t available.', 'doctor-ak-portal' ) ), 404 );
		}

		return $contact;
	}

	private function fail_if_error( $result ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'field'   => is_array( $data ) && isset( $data['field'] ) ? $data['field'] : '',
				)
			);
		}
	}

	/**
	 * A contact shaped for the table.
	 */
	private static function shape( array $c, array $viewer ) {
		$staff   = Crm::staff();
		$doctors = array();

		foreach ( $c['doctor_ids'] as $doctor_id ) {
			// Doctors only ever see themselves here, not the patient's other doctors.
			if ( $viewer['scope'] && $doctor_id !== $viewer['scope'] ) {
				continue;
			}

			$user = get_userdata( $doctor_id );

			if ( $user ) {
				$doctors[] = 'Dr. ' . trim( $user->first_name . ' ' . $user->last_name );
			}
		}

		$summary = $c['summary'];
		$doctor  = (bool) $viewer['scope'];

		return array(
			'id'               => $c['id'],
			'name'             => $c['name'],
			'phone'            => $c['user_id'] ? Phone::format_display( $c['phone'] ) : $c['phone'],
			'email'            => $c['email'],
			'hasAccount'       => $c['user_id'] > 0,
			'patientNumber'    => $c['user_id'] ? Appointments::patient_number( $c['user_id'] ) : '',
			'source'           => $c['source'],
			'stage'            => $c['stage'],
			'lostReason'       => $c['lost_reason'],
			'ownerId'          => $c['owner_id'],
			'ownerName'        => isset( $staff[ $c['owner_id'] ] ) ? $staff[ $c['owner_id'] ] : '',
			'tags'             => $c['tags'],
			'followUp'         => (string) $c['follow_up_date'],
			'doctors'          => $doctors,
			// Services and money can come from other doctors' visits.
			'services'         => $doctor ? array() : ( isset( $summary['services'] ) ? $summary['services'] : array() ),
			'flags'            => $doctor ? array() : $c['flags'],
			'summary'          => $doctor ? array() : $summary,
			'lastActivityAt'   => $c['last_activity_at'],
			'lastActivity'     => $c['last_activity_label'],
			'createdAt'        => $c['created_at'],
			'hasEmail'         => '' !== $c['email_norm'],
			'marketing'        => $doctor ? null : Crm_Email::marketing_status( $c ),
			// Why Delete isn't possible ('' = it is), for staff.
			'deleteBlock'      => $doctor ? null : Crm::delete_block( $c ),
		);
	}

	/**
	 * A contact with timeline, tasks and duplicates, for the side panel.
	 */
	private function detail( array $contact, array $viewer ) {
		$out             = self::shape( $contact, $viewer );
		$out['timeline'] = Crm::timeline( $contact['id'], $viewer['scope'] );
		$out['tasks']    = Crm::tasks_for( $contact['id'] );

		if ( ! $viewer['scope'] ) {
			$out['duplicates'] = array_map(
				function ( $d ) use ( $viewer ) {
					return self::shape( $d, $viewer );
				},
				Crm::duplicates( $contact['id'] )
			);
		} else {
			$out['duplicates'] = array();
		}

		return $out;
	}

	private function text( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle().
	}

	private function raw( $key ) {
		return isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in handle(); sanitized in Crm.
	}
}
