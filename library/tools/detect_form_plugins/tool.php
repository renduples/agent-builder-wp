<?php
/**
 * Tool: detect_form_plugins
 *
 * Detect which WordPress form plugins are active on the site.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.3.0
 *
 * php version 8.1
 */

declare( strict_types=1 );

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect which form plugins are installed and active.
 *
 * Checks for Contact Form 7, WPForms, Gravity Forms, Fluent Forms, and Ninja Forms.
 * Returns the primary (first active) plugin slug to use as the default for other form tools.
 */
class Detect_Form_Plugins extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'detect_form_plugins';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Detect which WordPress form plugins are installed and active. Returns the primary plugin to use for form operations. Always call this first before any other form tool.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'forms';
	}

	/**
	 * Get the parameter schema.
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Tool arguments (none required).
	 * @return array
	 */
	public function execute( array $arguments ): array {
		$detected = array();

		// Contact Form 7.
		if ( class_exists( 'WPCF7' ) ) {
			$detected[] = array(
				'slug'    => 'contact-form-7',
				'name'    => 'Contact Form 7',
				'version' => defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : 'unknown',
				'create'  => true,
				'entries' => false,
				'note'    => 'CF7 does not store entries by default. Install Flamingo for entry storage.',
			);
		}

		// WPForms (Lite or Pro).
		if ( function_exists( 'wpforms' ) || class_exists( 'WPForms\WPForms' ) ) {
			$detected[] = array(
				'slug'    => 'wpforms',
				'name'    => 'WPForms',
				'version' => defined( 'WPFORMS_VERSION' ) ? WPFORMS_VERSION : 'unknown',
				'create'  => true,
				'entries' => class_exists( 'WPForms\Pro\Forms\Fields\EntryAction\Field' ) || function_exists( 'wpforms_get_entry' ),
			);
		}

		// Gravity Forms.
		if ( class_exists( 'GFAPI' ) ) {
			$detected[] = array(
				'slug'    => 'gravityforms',
				'name'    => 'Gravity Forms',
				'version' => defined( 'GFCommon::$version' ) ? GFCommon::$version : ( class_exists( 'GFCommon' ) ? GFCommon::$version : 'unknown' ),
				'create'  => true,
				'entries' => true,
			);
		}

		// Fluent Forms.
		if ( function_exists( 'wpFluentForm' ) || class_exists( '\FluentForm\App\Models\Form' ) ) {
			$detected[] = array(
				'slug'    => 'fluentform',
				'name'    => 'Fluent Forms',
				'version' => defined( 'FLUENTFORM_VERSION' ) ? FLUENTFORM_VERSION : 'unknown',
				'create'  => true,
				'entries' => true,
			);
		}

		// Ninja Forms.
		if ( class_exists( 'Ninja_Forms' ) ) {
			$detected[] = array(
				'slug'    => 'ninja-forms',
				'name'    => 'Ninja Forms',
				'version' => defined( 'NF_PLUGIN_VERSION' ) ? NF_PLUGIN_VERSION : 'unknown',
				'create'  => false,
				'note'    => 'Read-only support. Use advanced form creation via the Ninja Forms admin.',
			);
		}

		if ( empty( $detected ) ) {
			return array(
				'plugins' => array(),
				'count'   => 0,
				'primary' => null,
				'error'   => 'No supported form plugin detected. Install Contact Form 7, WPForms, Gravity Forms, or Fluent Forms to use form management features.',
			);
		}

		return array(
			'plugins' => $detected,
			'count'   => count( $detected ),
			'primary' => $detected[0]['slug'],
		);
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Detect_Form_Plugins();
