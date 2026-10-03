<?php
/**
 * Template: the unread-notifications count badge — one capping convention
 * everywhere it appears (sidebar nav item and topbar bell): the visible
 * text stops at "99+", while the full number stays in data-unread-count
 * (what doctor-ak-notifications.js decrements on mark-as-read) and in a
 * visually hidden label next to it, so a screen reader always hears the
 * real count rather than "99 plus".
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $count Unread count (> 0; callers skip rendering at 0).
 * @var string $id    Optional element ID (the sidebar badge keeps its existing 'dak-notifications-badge').
 * @var string $class Badge class, e.g. 'dak-nav-badge' or 'dak-topbar-bell-badge'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$count = isset( $count ) ? max( 0, (int) $count ) : 0;
$id    = isset( $id ) ? (string) $id : '';
$class = isset( $class ) ? (string) $class : 'dak-nav-badge';
?>
<span class="<?php echo esc_attr( $class ); ?>"<?php echo '' !== $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> data-unread-count="<?php echo esc_attr( $count ); ?>" aria-hidden="true"><?php echo esc_html( $count > 99 ? '99+' : number_format_i18n( $count ) ); ?></span><span class="dak-visually-hidden" data-unread-label><?php echo esc_html( sprintf( /* translators: %s: number of unread notifications. */ _n( '(%s unread)', '(%s unread)', $count, 'doctor-ak-portal' ), number_format_i18n( $count ) ) ); ?></span>
