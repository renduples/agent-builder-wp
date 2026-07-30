<?php
/**
 * Tool: save_native_form
 *
 * Saves a form definition as a native WordPress CPT record
 * (no third-party form plugin required) and returns the shortcode
 * that embeds the form anywhere on the site.
 *
 * This tool is agent-agnostic: any agent that deals with forms
 * can use it as the zero-dependency write path.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.5.0
 *
 * php version 8.1
 */

declare( strict_types=1 );

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save a form definition as a native agentic_form CPT record.
 *
 * The record is entirely self-contained — no third-party plugin is required.
 * On the front end the shortcode [agentic_form id="X"] renders the form,
 * handles submissions, stores entries, and emails the site admin.
 */
class Save_Native_Form extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'save_native_form';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Save a form definition directly to WordPress (no form plugin required). ' .
			'Returns an [agentic_form id="X"] shortcode that can be inserted into any post or page. ' .
			'The form will render on the front end, collect submissions, store entries, and email the admin. ' .
			'Use this when no supported form plugin is detected, or when a lightweight built-in form is preferred.';
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
			'properties' => array(
				'title'                => array(
					'type'        => 'string',
					'description' => 'The form title (shown as a heading above the form).',
				),
				'fields'               => array(
					'type'        => 'array',
					'description' => 'Array of field objects. Each field: { type (text|email|tel|textarea|select|radio|checkbox|number|date|hidden), label, name (slug, no spaces), required (bool), placeholder, options (array for select/radio/checkbox — each item: string or { label, value }) }',
					'items'       => array( 'type' => 'object' ),
				),
				'submit_label'         => array(
					'type'        => 'string',
					'description' => 'Label for the submit button. Defaults to "Submit".',
					'default'     => 'Submit',
				),
				'confirmation_message' => array(
					'type'        => 'string',
					'description' => 'Message shown to the user after a successful submission. Defaults to a generic thank-you message.',
				),
				'form_id'              => array(
					'type'        => 'integer',
					'description' => 'If provided, updates an existing native form instead of creating a new one.',
				),
				'styles'               => array(
					'type'        => 'object',
					'description' => 'Optional visual styling for the form. All values are CSS values (e.g. "14px", "#333", "8px 16px"). Supported keys: font_size, font_family, label_color, label_font_size, input_text_color, input_background, input_border_color, input_border_radius, input_padding, input_focus_color, button_background, button_hover_background, button_text_color, button_border_radius, button_padding, button_font_size, form_background, form_padding, form_border, form_border_radius, field_gap, max_width.',
					'properties'  => array(
						'font_size'               => array( 'type' => 'string' ),
						'font_family'             => array( 'type' => 'string' ),
						'label_color'             => array( 'type' => 'string' ),
						'label_font_size'         => array( 'type' => 'string' ),
						'input_text_color'        => array( 'type' => 'string' ),
						'input_background'        => array( 'type' => 'string' ),
						'input_border_color'      => array( 'type' => 'string' ),
						'input_border_radius'     => array( 'type' => 'string' ),
						'input_padding'           => array( 'type' => 'string' ),
						'input_focus_color'       => array( 'type' => 'string' ),
						'button_background'       => array( 'type' => 'string' ),
						'button_hover_background' => array( 'type' => 'string' ),
						'button_text_color'       => array( 'type' => 'string' ),
						'button_border_radius'    => array( 'type' => 'string' ),
						'button_padding'          => array( 'type' => 'string' ),
						'button_font_size'        => array( 'type' => 'string' ),
						'form_background'         => array( 'type' => 'string' ),
						'form_padding'            => array( 'type' => 'string' ),
						'form_border'             => array( 'type' => 'string' ),
						'form_border_radius'      => array( 'type' => 'string' ),
						'field_gap'               => array( 'type' => 'string' ),
						'max_width'               => array( 'type' => 'string' ),
					),
				),
			),
			'required'   => array( 'title', 'fields' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'error' => 'You do not have permission to save forms.' );
		}

		$title        = sanitize_text_field( $arguments['title'] ?? '' );
		$fields       = $arguments['fields'] ?? array();
		$submit_label = sanitize_text_field( $arguments['submit_label'] ?? 'Submit' );
		$confirmation = sanitize_text_field( $arguments['confirmation_message'] ?? '' );
		$form_id      = absint( $arguments['form_id'] ?? 0 );
		$raw_styles   = is_array( $arguments['styles'] ?? null ) ? $arguments['styles'] : array();

		if ( '' === $title ) {
			return array( 'error' => 'A form title is required.' );
		}

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return array( 'error' => 'At least one field is required.' );
		}

		// Sanitise and normalise each field.
		$clean_fields = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$f = array(
				'type'        => sanitize_key( $field['type'] ?? 'text' ),
				'label'       => sanitize_text_field( $field['label'] ?? '' ),
				'name'        => sanitize_key( $field['name'] ?? $field['label'] ?? 'field' ),
				'required'    => ! empty( $field['required'] ),
				'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
			);

			// Options (select / radio / checkbox).
			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				$f['options'] = array_map(
					function ( $opt ) {
						if ( is_array( $opt ) ) {
							return array(
								'label' => sanitize_text_field( $opt['label'] ?? '' ),
								'value' => sanitize_text_field( $opt['value'] ?? $opt['label'] ?? '' ),
							);
						}
						return sanitize_text_field( (string) $opt );
					},
					$field['options']
				);
			}

			$clean_fields[] = $f;
		}

		$definition = array(
			'title'        => $title,
			'submit_label' => $submit_label ? $submit_label : 'Submit',
			'fields'       => $clean_fields,
		);

		if ( '' !== $confirmation ) {
			$definition['confirmation_message'] = $confirmation;
		}

		// Sanitise and store per-form styles (strip anything that could be CSS injection).
		if ( ! empty( $raw_styles ) ) {
			$allowed_keys = array(
				'font_size',
				'font_family',
				'label_color',
				'label_font_size',
				'input_text_color',
				'input_background',
				'input_border_color',
				'input_border_radius',
				'input_padding',
				'input_focus_color',
				'button_background',
				'button_hover_background',
				'button_text_color',
				'button_border_radius',
				'button_padding',
				'button_font_size',
				'form_background',
				'form_padding',
				'form_border',
				'form_border_radius',
				'field_gap',
				'max_width',
			);
			$clean_styles = array();
			foreach ( $allowed_keys as $key ) {
				if ( isset( $raw_styles[ $key ] ) ) {
					// Strip characters that have no place in a CSS value.
					$val = preg_replace( '/[{}<>"\';]/', '', (string) $raw_styles[ $key ] );
					$val = trim( $val );
					if ( '' !== $val ) {
						$clean_styles[ $key ] = $val;
					}
				}
			}
			if ( ! empty( $clean_styles ) ) {
				$definition['styles'] = $clean_styles;
			}
		}

		// Delegate to the shared service.
		$engine = \Agentic_Native_Forms::get_instance();
		$result = $engine->save_form( $title, $definition, $form_id );

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return array_merge(
			$result,
			array(
				'note' => 'Paste the form shortcode into any page or post to display the form. ' .
					'Use the entries shortcode on a private/admin page to display submitted entries. ' .
					'Submissions are stored in WordPress and sent to the admin email automatically.',
			)
		);
	}
}

return new Save_Native_Form();
