<?php
/**
 * Template: Admin dashboard "Symptom Checker" section (administrators only).
 *
 * Status, the on/off switch, clinical reviewers, approval, the draft rules
 * editor and version history. Actions are handled by
 * doctor-ak-admin-symptom-checker.js → Symptom_Checker_Admin_Handler.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array      $draft          Symptom_Rules::get_draft().
 * @var array|null $published      Symptom_Rules::get_published().
 * @var array      $history        Symptom_Rules::get_history().
 * @var int[]      $reviewers      Designated reviewer user IDs.
 * @var \WP_User[] $admins         Administrator accounts (reviewer candidates).
 * @var bool       $enabled        Switch is on.
 * @var bool       $is_public      Patients can use it now.
 * @var string[]   $draft_errors   Validation problems in the draft.
 * @var bool       $draft_is_demo  Draft is the demonstration ruleset.
 * @var bool       $draft_changed  Draft differs from the published version.
 * @var string     $page_url       Public Symptom Checker page URL, or ''.
 * @var int        $current_user   Current user ID.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_user_name = function ( $user_id ) {
	$user = $user_id ? get_userdata( $user_id ) : false;

	return $user ? $user->display_name : __( 'Unknown user', 'doctor-ak-portal' );
};

$dak_has_draft      = '' !== trim( $draft['json'] );
$dak_is_reviewer    = in_array( (int) $current_user, $reviewers, true );
$dak_is_last_editor = (int) $draft['updated_by'] === (int) $current_user;
$dak_can_approve    = $dak_has_draft && $draft_changed && $dak_is_reviewer && ! $dak_is_last_editor && empty( $draft_errors ) && ! $draft_is_demo;
?>
<div class="dak-list-page dak-admin-sc" data-admin-sc>
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Symptom Checker', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'The rules behind the public Symptom Checker. Changes are saved as a draft; patients only see a version approved by a designated clinical reviewer, and only while the checker is switched on.', 'doctor-ak-portal' ); ?></p>
	</div>
	<?php if ( '' !== $page_url ) : ?>
		<a class="dak-button dak-button-secondary" href="<?php echo esc_url( add_query_arg( 'sc_preview', 'draft', $page_url ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview draft', 'doctor-ak-portal' ); ?></a>
	<?php endif; ?>
</div>

<p class="dak-admin-sc-message" role="status" aria-live="polite" data-admin-sc-message hidden></p>

<section class="dak-dashboard-card dak-admin-sc-card" aria-labelledby="dak-admin-sc-status">
	<h2 id="dak-admin-sc-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></h2>

	<dl class="dak-admin-sc-status">
		<div>
			<dt><?php esc_html_e( 'Patients', 'doctor-ak-portal' ); ?></dt>
			<dd>
				<?php if ( $is_public ) : ?>
					<span class="dak-status-pill dak-status-pill-is-active"><?php esc_html_e( 'Available to patients', 'doctor-ak-portal' ); ?></span>
				<?php else : ?>
					<span class="dak-status-pill dak-status-pill-is-neutral"><?php esc_html_e( 'Hidden from patients', 'doctor-ak-portal' ); ?></span>
				<?php endif; ?>
			</dd>
		</div>
		<div>
			<dt><?php esc_html_e( 'Published version', 'doctor-ak-portal' ); ?></dt>
			<dd>
				<?php if ( $published ) : ?>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: version, 2: reviewer name, 3: date. */
							__( 'Version %1$d — approved by %2$s on %3$s', 'doctor-ak-portal' ),
							(int) $published['version'],
							$dak_user_name( (int) $published['approved_by'] ),
							mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $published['approved_at'] )
						)
					);
					?>
					<span class="dak-cell-sub"><?php echo esc_html( $published['note'] ); ?></span>
				<?php else : ?>
					<?php esc_html_e( 'None — no ruleset has been clinically approved yet.', 'doctor-ak-portal' ); ?>
				<?php endif; ?>
			</dd>
		</div>
		<div>
			<dt><?php esc_html_e( 'Draft', 'doctor-ak-portal' ); ?></dt>
			<dd>
				<?php if ( ! $dak_has_draft ) : ?>
					<?php esc_html_e( 'No draft yet.', 'doctor-ak-portal' ); ?>
				<?php else : ?>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: user name, 2: date. */
							__( 'Last edited by %1$s on %2$s', 'doctor-ak-portal' ),
							$dak_user_name( (int) $draft['updated_by'] ),
							mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $draft['updated_at'] )
						)
					);
					?>
					<span class="dak-cell-sub">
						<?php
						if ( $draft_is_demo ) {
							esc_html_e( 'Demonstration rules — for testing only, not clinically reviewed, can never be approved.', 'doctor-ak-portal' );
						} elseif ( ! empty( $draft_errors ) ) {
							/* translators: %d: number of problems. */
							echo esc_html( sprintf( _n( '%d problem to fix before approval.', '%d problems to fix before approval.', count( $draft_errors ), 'doctor-ak-portal' ), count( $draft_errors ) ) );
						} elseif ( $draft_changed ) {
							esc_html_e( 'Awaiting clinical review — not visible to patients.', 'doctor-ak-portal' );
						} else {
							esc_html_e( 'Same as the published version.', 'doctor-ak-portal' );
						}
						?>
					</span>
				<?php endif; ?>
			</dd>
		</div>
	</dl>

	<div class="dak-admin-sc-switch">
		<label class="dak-admin-sc-toggle">
			<input type="checkbox" data-admin-sc-toggle <?php checked( $enabled ); ?> <?php disabled( ! $published || ( $published && \DoctorAKPortal\Includes\Symptom_Rules::is_demo( $published['rules'] ) ) ); ?>>
			<span><?php esc_html_e( 'Show the Symptom Checker to patients', 'doctor-ak-portal' ); ?></span>
		</label>
		<p class="dak-field-hint">
			<?php
			if ( ! $published ) {
				esc_html_e( 'Available once a clinical reviewer has approved a ruleset. When on, the page and its links on the home page and in the Doctors menu appear for everyone.', 'doctor-ak-portal' );
			} else {
				esc_html_e( 'When on, the page and its links on the home page and in the Doctors menu appear for everyone. Turning it off hides them straight away.', 'doctor-ak-portal' );
			}
			?>
		</p>
		<?php if ( '' === $page_url ) : ?>
			<p class="dak-field-hint"><?php esc_html_e( 'No page contains the [symptom_checker] shortcode yet. It is created automatically when the plugin updates its pages; you can also add the shortcode to any page.', 'doctor-ak-portal' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="dak-dashboard-card dak-admin-sc-card" aria-labelledby="dak-admin-sc-review">
	<h2 id="dak-admin-sc-review"><?php esc_html_e( 'Clinical approval', 'doctor-ak-portal' ); ?></h2>
	<p class="dak-field-hint"><?php esc_html_e( 'Editing and approving are separate. A designated clinical reviewer must approve every change before patients see it, and cannot approve a draft they edited last — a second person must check it.', 'doctor-ak-portal' ); ?></p>

	<fieldset class="dak-admin-sc-reviewers">
		<legend><?php esc_html_e( 'Clinical reviewers', 'doctor-ak-portal' ); ?></legend>
		<p class="dak-field-hint"><?php esc_html_e( 'Only administrator accounts can be reviewers. Choose only people qualified to review clinical content.', 'doctor-ak-portal' ); ?></p>
		<?php foreach ( $admins as $dak_admin ) : ?>
			<label class="dak-admin-sc-check">
				<input type="checkbox" value="<?php echo esc_attr( (int) $dak_admin->ID ); ?>" data-admin-sc-reviewer <?php checked( in_array( (int) $dak_admin->ID, $reviewers, true ) ); ?>>
				<span><?php echo esc_html( $dak_admin->display_name ); ?> <span class="dak-cell-sub"><?php echo esc_html( $dak_admin->user_email ); ?></span></span>
			</label>
		<?php endforeach; ?>
		<button type="button" class="dak-button dak-button-secondary" data-admin-sc-save-reviewers><?php esc_html_e( 'Save reviewers', 'doctor-ak-portal' ); ?></button>
	</fieldset>

	<div class="dak-admin-sc-approve">
		<h3><?php esc_html_e( 'Approve the draft', 'doctor-ak-portal' ); ?></h3>
		<?php if ( $dak_can_approve ) : ?>
			<label class="dak-admin-sc-check">
				<input type="checkbox" data-admin-sc-approve-confirm>
				<span><?php esc_html_e( 'I am qualified to review this content, and I have reviewed every symptom, synonym, question, warning sign, explanation, next step and specialty in this draft and confirm it is appropriate for patients to use.', 'doctor-ak-portal' ); ?></span>
			</label>
			<div class="dak-field">
				<label for="dak-admin-sc-note"><?php esc_html_e( 'Review note', 'doctor-ak-portal' ); ?></label>
				<textarea id="dak-admin-sc-note" rows="3" data-admin-sc-note placeholder="<?php esc_attr_e( 'What you reviewed and on what basis (e.g. guidelines used), and what changed.', 'doctor-ak-portal' ); ?>"></textarea>
			</div>
			<button type="button" class="dak-button dak-button-primary" data-admin-sc-approve><?php esc_html_e( 'Approve and publish', 'doctor-ak-portal' ); ?></button>
		<?php else : ?>
			<p class="dak-field-hint">
				<?php
				if ( ! $dak_has_draft ) {
					esc_html_e( 'There is no draft to approve.', 'doctor-ak-portal' );
				} elseif ( ! $draft_changed ) {
					esc_html_e( 'The draft is the same as the published version — nothing to approve.', 'doctor-ak-portal' );
				} elseif ( $draft_is_demo ) {
					esc_html_e( 'The draft is the demonstration ruleset, which can never be approved. Replace it with clinically written rules.', 'doctor-ak-portal' );
				} elseif ( ! empty( $draft_errors ) ) {
					esc_html_e( 'The draft has problems that must be fixed first (see below).', 'doctor-ak-portal' );
				} elseif ( ! $dak_is_reviewer ) {
					esc_html_e( 'Only a designated clinical reviewer can approve the draft.', 'doctor-ak-portal' );
				} else {
					esc_html_e( 'You made the latest changes to this draft, so a different reviewer must approve it.', 'doctor-ak-portal' );
				}
				?>
			</p>
		<?php endif; ?>
	</div>
</section>

<section class="dak-dashboard-card dak-admin-sc-card" aria-labelledby="dak-admin-sc-editor">
	<h2 id="dak-admin-sc-editor"><?php esc_html_e( 'Draft rules', 'doctor-ak-portal' ); ?></h2>
	<p class="dak-field-hint"><?php esc_html_e( 'Open a section to view or change it. Saving never changes what patients see — changes only reach patients after clinical approval.', 'doctor-ak-portal' ); ?></p>

	<div class="dak-sce" data-admin-sc-editor></div>

	<details class="dak-sce-advanced" data-admin-sc-advanced>
		<summary><?php esc_html_e( 'Advanced: edit as JSON (for technical staff)', 'doctor-ak-portal' ); ?></summary>
		<p class="dak-field-hint"><?php esc_html_e( 'The same rules in their stored format (see docs/symptom-checker.md in the plugin). Changes here update the form when you click outside the box.', 'doctor-ak-portal' ); ?></p>
		<label class="dak-sr-only" for="dak-admin-sc-json"><?php esc_html_e( 'Ruleset JSON', 'doctor-ak-portal' ); ?></label>
		<textarea id="dak-admin-sc-json" class="dak-admin-sc-json" rows="22" spellcheck="false" autocomplete="off" data-admin-sc-json><?php echo esc_textarea( $draft['json'] ); ?></textarea>
		<p class="dak-field-hint" role="status" data-admin-sc-advanced-note></p>
	</details>

	<div class="dak-admin-sc-errors" data-admin-sc-errors <?php echo empty( $draft_errors ) ? 'hidden' : ''; ?>>
		<p><strong><?php esc_html_e( 'Problems to fix before approval', 'doctor-ak-portal' ); ?></strong></p>
		<ul data-admin-sc-error-list>
			<?php foreach ( $draft_errors as $dak_error ) : ?>
				<li><?php echo esc_html( $dak_error ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="dak-admin-sc-actions">
		<button type="button" class="dak-button dak-button-secondary" data-admin-sc-validate><?php esc_html_e( 'Check for problems', 'doctor-ak-portal' ); ?></button>
		<button type="button" class="dak-button dak-button-primary" data-admin-sc-save><?php esc_html_e( 'Save draft', 'doctor-ak-portal' ); ?></button>
		<button type="button" class="dak-button dak-button-secondary" data-admin-sc-load-demo><?php esc_html_e( 'Load demonstration rules (testing only)', 'doctor-ak-portal' ); ?></button>
	</div>
</section>

<section class="dak-dashboard-card dak-admin-sc-card" aria-labelledby="dak-admin-sc-history">
	<h2 id="dak-admin-sc-history"><?php esc_html_e( 'Version history', 'doctor-ak-portal' ); ?></h2>
	<?php if ( ! $published && empty( $history ) ) : ?>
		<p class="dak-field-hint"><?php esc_html_e( 'No versions have been approved yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<ul class="dak-admin-sc-history">
			<?php if ( $published ) : ?>
				<li>
					<strong>
						<?php
						/* translators: %d: version. */
						echo esc_html( sprintf( __( 'Version %d (current)', 'doctor-ak-portal' ), (int) $published['version'] ) );
						?>
					</strong>
					<span class="dak-cell-sub">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: editor, 2: reviewer, 3: date. */
								__( 'Edited by %1$s · approved by %2$s on %3$s', 'doctor-ak-portal' ),
								$dak_user_name( (int) $published['edited_by'] ),
								$dak_user_name( (int) $published['approved_by'] ),
								mysql2date( get_option( 'date_format' ), $published['approved_at'] )
							)
						);
						?>
					</span>
					<span class="dak-cell-sub"><?php echo esc_html( $published['note'] ); ?></span>
				</li>
			<?php endif; ?>
			<?php foreach ( $history as $dak_version ) : ?>
				<li>
					<strong>
						<?php
						/* translators: %d: version. */
						echo esc_html( sprintf( __( 'Version %d', 'doctor-ak-portal' ), (int) $dak_version['version'] ) );
						?>
					</strong>
					<span class="dak-cell-sub">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: editor, 2: reviewer, 3: date. */
								__( 'Edited by %1$s · approved by %2$s on %3$s', 'doctor-ak-portal' ),
								$dak_user_name( (int) $dak_version['edited_by'] ),
								$dak_user_name( (int) $dak_version['approved_by'] ),
								mysql2date( get_option( 'date_format' ), $dak_version['approved_at'] )
							)
						);
						?>
					</span>
					<span class="dak-cell-sub"><?php echo esc_html( $dak_version['note'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
</div>
