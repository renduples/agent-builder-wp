<?php
/**
 * Tool: form_add_conditional_logic
 *
 * Set conditional show/hide rules for fields in a native form.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.9.0
 *
 * php version 8.1
 */

declare( strict_types=1 );

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set conditional logic rules for a native agentic_form.
 */
class Form_Add_Conditional_Logic extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form_add_conditional_logic';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Set conditional logic rules for a native form. Each rule ties a source field value ' .
			'to a show/hide action on a target field. For example: show "Company Name" only when ' .
			'"Type" equals "Business".';
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
				'form_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the native form.',
				),
				'rules'   => array(
					'type'        => 'array',
					'description' => 'Array of conditional logic rules. Each rule: { field (source field name), operator (equals|not_equals|contains|not_empty|empty), value (comparison value), target (target field name), action (show|hide) }.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'field'    => array(
								'type'        => 'string',
								'description' => 'The source field name that triggers the condition.',
							),
							'operator' => array(
								'type'        => 'string',
								'description' => 'Comparison operator: equals, not_equals, contains, not_empty, or empty.',
								'enum'        => array( 'equals', 'not_equals', 'contains', 'not_empty', 'empty' ),
							),
							'value'    => array(
								'type'        => 'string',
								'description' => 'The value to compare against (not required for not_empty/empty operators).',
							),
							'target'   => array(
								'type'        => 'string',
								'description' => 'The target field name to show or hide.',
							),
							'action'   => array(
								'type'        => 'string',
								'description' => 'Action to take on the target field: show or hide.',
								'enum'        => array( 'show', 'hide' ),
							),
						),
						'required'   => array( 'field', 'operator', 'target', 'action' ),
					),
				),
			),
			'required'   => array( 'form_id', 'rules' ),
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
			return array( 'error' => 'You do not have permission to configure form logic.' );
		}

		$form_id = absint( $arguments['form_id'] ?? 0 );
		$rules   = $arguments['rules'] ?? array();

		if ( 0 === $form_id ) {
			return array( 'error' => 'A form_id is required.' );
		}

		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return array( 'error' => 'At least one rule is required.' );
		}

		$post = get_post( $form_id );
		if ( ! $post || \Agentic_Native_Forms::FORM_CPT !== $post->post_type ) {
			return array( 'error' => 'Native form with ID ' . $form_id . ' was not found.' );
		}

		// Load the form definition to validate field names.
		$engine     = \Agentic_Native_Forms::get_instance();
		$definition = $engine->get_definition( $form_id );
		$fields     = $definition['fields'] ?? array();

		$field_names = array_map(
			function ( $f ) {
				return $f['name'] ?? '';
			},
			$fields
		);
		$field_names = array_filter( $field_names );

		$valid_operators = array( 'equals', 'not_equals', 'contains', 'not_empty', 'empty' );
		$valid_actions   = array( 'show', 'hide' );
		$clean_rules     = array();
		$errors          = array();

		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) ) {
				$errors[] = 'Rule ' . $index . ' is not a valid object.';
				continue;
			}

			$field    = sanitize_key( $rule['field'] ?? '' );
			$operator = sanitize_key( $rule['operator'] ?? '' );
			$value    = sanitize_text_field( $rule['value'] ?? '' );
			$target   = sanitize_key( $rule['target'] ?? '' );
			$action   = sanitize_key( $rule['action'] ?? '' );

			if ( '' === $field || '' === $target ) {
				$errors[] = 'Rule ' . $index . ': field and target are required.';
				continue;
			}

			if ( ! in_array( $field, $field_names, true ) ) {
				$errors[] = 'Rule ' . $index . ': source field "' . $field . '" does not exist in the form.';
				continue;
			}

			if ( ! in_array( $target, $field_names, true ) ) {
				$errors[] = 'Rule ' . $index . ': target field "' . $target . '" does not exist in the form.';
				continue;
			}

			if ( ! in_array( $operator, $valid_operators, true ) ) {
				$errors[] = 'Rule ' . $index . ': invalid operator "' . $operator . '".';
				continue;
			}

			if ( ! in_array( $action, $valid_actions, true ) ) {
				$errors[] = 'Rule ' . $index . ': invalid action "' . $action . '".';
				continue;
			}

			$clean_rules[] = array(
				'field'    => $field,
				'operator' => $operator,
				'value'    => $value,
				'target'   => $target,
				'action'   => $action,
			);
		}

		if ( ! empty( $errors ) ) {
			return array(
				'error'   => 'Some rules failed validation.',
				'details' => $errors,
			);
		}

		update_post_meta( $form_id, \Agentic_Native_Forms::META_CONDITIONS, wp_json_encode( $clean_rules ) );

		return array(
			'form_id'     => $form_id,
			'rules_saved' => count( $clean_rules ),
			'rules'       => $clean_rules,
			'message'     => count( $clean_rules ) . ' conditional logic rule(s) saved for form ' . $form_id . '.',
		);
	}
}

return new Form_Add_Conditional_Logic();
