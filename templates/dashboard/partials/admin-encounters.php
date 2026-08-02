<?php
/**
 * Template: "Encounters" admin section — a read-only visit log of every
 * completed appointment, with a short clinical note the admin can add or
 * edit per visit. Not a full clinical record (no diagnosis/prescription/
 * vitals fields) — just a log of what happened at each completed visit.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $encounters     Rows from Appointments::all_for_admin( [ 'status' => 'completed', ... ] ).
 * @var string $encounters_url Unfiltered URL of this section, for the filter form and "Clear" link.
 * @var array  $doctors        Doctor users { ID, display_name }, for the filter's Doctor select.
 * @var array  $filters        Active filter values: date_from, date_to, doctor_id.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_has_filters = '' !== $filters['date_from'] || '' !== $filters['date_to'] || $filters['doctor_id'] > 0;
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Encounters', 'doctor-ak-portal' ); ?></h1>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: number of completed visits. */
				_n( '%d completed visit', '%d completed visits', count( $encounters ), 'doctor-ak-portal' ),
				count( $encounters )
			)
		);
		?>
	</p>
</div>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
	</div>

	<form method="get" action="<?php echo esc_url( $encounters_url ); ?>" class="dak-field-row">
		<input type="hidden" name="section" value="encounters">

		<div class="dak-field">
			<label for="dak-admin-encounters-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-encounters-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-encounters-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-encounters-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-encounters-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-encounters-doctor" name="doctor_id">
				<option value="0"><?php esc_html_e( 'All doctors', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctors as $dak_doctor_option ) : ?>
					<option value="<?php echo esc_attr( $dak_doctor_option->ID ); ?>" <?php selected( $filters['doctor_id'], $dak_doctor_option->ID ); ?>><?php echo esc_html( sprintf( 'Dr. %s', $dak_doctor_option->display_name ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-admin-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $encounters_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Visit log', 'doctor-ak-portal' ); ?></h2>
	</div>

	<div class="dak-alert dak-alert-error dak-hidden" id="dak-encounters-general-error" role="alert"></div>

	<?php if ( empty( $encounters ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No completed visits match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $encounters as $row ) : ?>
			<div class="dak-admin-record-row dak-encounter-row" data-encounter-row="<?php echo esc_attr( $row['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
						<?php if ( $row['patient_avatar_url'] ) : ?>
							<img src="<?php echo esc_url( $row['patient_avatar_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo esc_html( $row['patient_initials'] ); ?>
						<?php endif; ?>
					</span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $row['patient_name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Dr. %s', 'doctor-ak-portal' ), $row['doctor_name'] ) ); ?></span>
					</span>
					<span class="dak-admin-record-row-meta"><?php echo esc_html( $row['date'] . ' &middot; ' . $row['time'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() already applied before concatenation. ?></span>
					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( '' !== $row['service_name'] ? $row['service_name'] : $row['type_label'] ); ?></span>
					</span>
				</div>

				<div class="dak-encounter-note">
					<label for="dak-encounter-note-<?php echo esc_attr( $row['id'] ); ?>" class="dak-field-label"><?php esc_html_e( 'Visit note', 'doctor-ak-portal' ); ?></label>
					<textarea id="dak-encounter-note-<?php echo esc_attr( $row['id'] ); ?>" class="dak-encounter-note-input" rows="2" placeholder="<?php esc_attr_e( 'Add a short note about this visit…', 'doctor-ak-portal' ); ?>"><?php echo esc_textarea( $row['encounter_notes'] ); ?></textarea>
					<div class="dak-encounter-note-actions">
						<span class="dak-encounter-note-status" id="dak-encounter-note-status-<?php echo esc_attr( $row['id'] ); ?>"></span>
						<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-encounter-save data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Save Note', 'doctor-ak-portal' ); ?></button>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
