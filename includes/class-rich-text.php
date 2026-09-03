<?php
/**
 * Shared markup for the plugin's rich-text (contenteditable) editor toolbar.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rich_Text
 *
 * The toolbar buttons themselves are wired up client-side by
 * assets/js/doctor-ak-rich-text-editor.js (document.execCommand()) — this
 * class only exists so the same Bold/Italic/Underline/Lists/Link/Clear
 * button markup isn't copy-pasted across every template that uses one
 * (admin "Add/Edit Service" Description, doctor registration/profile/
 * admin "Add/Edit Doctor" Other Expertise, ...).
 */
class Rich_Text {

	/**
	 * Renders the toolbar (not the editor div itself — see the caller's own
	 * `.dak-rich-text-editor` markup, since its id/name/placeholder/existing
	 * content differ per field).
	 *
	 * @return string
	 */
	public static function toolbar_html() {
		ob_start();
		?>
		<div class="dak-rich-text-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Formatting', 'doctor-ak-portal' ); ?>">
			<button type="button" class="dak-rich-text-button" data-rte-command="bold" title="<?php esc_attr_e( 'Bold', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Bold', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h5a3 3 0 0 1 0 6H6z"/><path d="M6 10h5.5a3 3 0 0 1 0 6H6z"/></svg>
			</button>
			<button type="button" class="dak-rich-text-button" data-rte-command="italic" title="<?php esc_attr_e( 'Italic', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Italic', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 4h6M5.5 16h6M11.5 4l-3 12"/></svg>
			</button>
			<button type="button" class="dak-rich-text-button" data-rte-command="underline" title="<?php esc_attr_e( 'Underline', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Underline', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 4v6a4.5 4.5 0 0 0 9 0V4"/><path d="M4.5 16.5h11"/></svg>
			</button>
			<span class="dak-rich-text-divider" aria-hidden="true"></span>
			<button type="button" class="dak-rich-text-button" data-rte-command="insertUnorderedList" title="<?php esc_attr_e( 'Bullet list', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Bullet list', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="3.5" cy="5" r="0.9" fill="currentColor" stroke="none"/><circle cx="3.5" cy="10" r="0.9" fill="currentColor" stroke="none"/><circle cx="3.5" cy="15" r="0.9" fill="currentColor" stroke="none"/><path d="M7.5 5h9M7.5 10h9M7.5 15h9"/></svg>
			</button>
			<button type="button" class="dak-rich-text-button" data-rte-command="insertOrderedList" title="<?php esc_attr_e( 'Numbered list', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Numbered list', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 5h9M7.5 10h9M7.5 15h9"/><text x="1.2" y="6.5" font-size="5" fill="currentColor" stroke="none" font-family="sans-serif">1</text><text x="1.2" y="11.5" font-size="5" fill="currentColor" stroke="none" font-family="sans-serif">2</text><text x="1.2" y="16.5" font-size="5" fill="currentColor" stroke="none" font-family="sans-serif">3</text></svg>
			</button>
			<span class="dak-rich-text-divider" aria-hidden="true"></span>
			<button type="button" class="dak-rich-text-button" data-rte-command="createLink" title="<?php esc_attr_e( 'Insert link', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Insert link', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 11.5a3 3 0 0 0 4.3.2l2-2a3 3 0 0 0-4.2-4.2l-1.1 1.1"/><path d="M11.5 8.5a3 3 0 0 0-4.3-.2l-2 2a3 3 0 0 0 4.2 4.2l1.1-1.1"/></svg>
			</button>
			<button type="button" class="dak-rich-text-button" data-rte-command="removeFormat" title="<?php esc_attr_e( 'Clear formatting', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Clear formatting', 'doctor-ak-portal' ); ?>">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 4h8l-3 12"/><path d="M11 4h4.5"/><path d="M4.5 16l11-12"/></svg>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}
}
