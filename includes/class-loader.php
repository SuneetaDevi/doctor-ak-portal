<?php
/**
 * Registers all actions and filters for the plugin.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 *
 * Maintains a list of hooks to be registered with WordPress and registers
 * them in a single place, keeping wiring out of individual classes.
 */
class Loader {

	/**
	 * Registered actions.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Adds a WordPress action to the collection to be registered.
	 *
	 * @param string $hook          Action hook name.
	 * @param object $component     Object instance the callback belongs to.
	 * @param string $callback      Method name on the component.
	 * @param int    $priority      Hook priority. Default 10.
	 * @param int    $accepted_args Number of accepted arguments. Default 1.
	 * @return void
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Adds a WordPress filter to the collection to be registered.
	 *
	 * @param string $hook          Filter hook name.
	 * @param object $component     Object instance the callback belongs to.
	 * @param string $callback      Method name on the component.
	 * @param int    $priority      Hook priority. Default 10.
	 * @param int    $accepted_args Number of accepted arguments. Default 1.
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Appends a hook definition to the given collection.
	 *
	 * @param array  $hooks         Existing collection of hooks.
	 * @param string $hook          Hook name.
	 * @param object $component     Object instance the callback belongs to.
	 * @param string $callback      Method name on the component.
	 * @param int    $priority      Hook priority.
	 * @param int    $accepted_args Number of accepted arguments.
	 * @return array Updated collection of hooks.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Registers all stored actions and filters with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
