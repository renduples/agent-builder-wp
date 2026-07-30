<?php
/**
 * Tool: update_form
 *
 * Update an existing form's title, fields, notification, or confirmation.
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
 * Update an existing WordPress form.
 *
 * All parameters except form_id are optional — only the supplied fields are updated.
 * Supports Contact Form 7, WPForms, Gravity Forms, and Fluent Forms.
 */
class Update_Form extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'update_form';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Update an existing WordPress form: change its title, replace its fields, update notification email, or change the confirmation message.';
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
				'form_id'              => array(
					'type'        => 'integer',
					'description' => 'The ID of the form to update.',
				),
				'plugin'               => array(
					'type'        => 'string',
					'description' => 'Plugin slug: contact-form-7, wpforms, gravityforms, fluentform. Omit to auto-detect.',
				),
				'title'                => array(
					'type'        => 'string',
					'description' => 'New form title.',
				),
				'fields'               => array(
					'type'        => 'array',
					'description' => 'Replacement field list using universal schema. Replaces ALL existing fields if provided.',
					'items'       => array( 'type' => 'object' ),
				),
				'notification_email'   => array(
					'type'        => 'string',
					'description' => 'New notification recipient email.',
				),
				'confirmation_message' => array(
					'type'        => 'string',
					'description' => 'New confirmation message shown after submission.',
				),
			),
			'required'   => array( 'form_id' ),
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
			return array( 'error' => 'You do not have permission to update forms.' );
		}

		$form_id = (int) ( $arguments['form_id'] ?? 0 );
		$plugin  = sanitize_key( $arguments['plugin'] ?? '' );

		if ( $form_id <= 0 ) {
			return array( 'error' => 'form_id is required and must be a positive integer.' );
		}

		$updates = array_filter(
			array(
				'title'                => isset( $arguments['title'] ) ? sanitize_text_field( $arguments['title'] ) : null,
				'fields'               => $arguments['fields'] ?? null,
				'notification_email'   => isset( $arguments['notification_email'] ) ? sanitize_email( $arguments['notification_email'] ) : null,
				'confirmation_message' => isset( $arguments['confirmation_message'] ) ? sanitize_text_field( $arguments['confirmation_message'] ) : null,
			),
			static fn( $v ) => null !== $v
		);

		if ( empty( $updates ) ) {
			return array( 'error' => 'No update parameters provided. Supply at least one of: title, fields, notification_email, confirmation_message.' );
		}

		if ( '' === $plugin ) {
			$plugin = $this->detect_plugin();
		}

		switch ( $plugin ) {
			case 'contact-form-7':
				return $this->update_cf7( $form_id, $updates );
			case 'wpforms':
				return $this->update_wpforms( $form_id, $updates );
			case 'gravityforms':
				return $this->update_gravity( $form_id, $updates );
			case 'fluentform':
				return $this->update_fluent( $form_id, $updates );
			default:
				return array( 'error' => 'No supported form plugin detected. Specify the "plugin" parameter explicitly.' );
		}
	}

	// -------------------------------------------------------------------------
	// Plugin-specific update implementations
	// -------------------------------------------------------------------------

	/**
	 * Update a Contact Form 7 form.
	 *
	 * @param int   $form_id Form post ID.
	 * @param array $updates Updates to apply.
	 * @return array
	 */
	private function update_cf7( int $form_id, array $updates ): array {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return array( 'error' => 'Contact Form 7 is not active.' );
		}

		$cf7 = \WPCF7_ContactForm::get_instance( $form_id );
		if ( ! $cf7 ) {
			return array( 'error' => "Contact Form 7 form ID {$form_id} not found." );
		}

		$post_data = array( 'ID' => $form_id );

		if ( isset( $updates['title'] ) ) {
			$post_data['post_title'] = $updates['title'];
		}

		if ( ! empty( $post_data ) ) {
			wp_update_post( $post_data );
		}

		if ( isset( $updates['fields'] ) ) {
			$markup = $this->build_cf7_markup( $updates['fields'] );
			update_post_meta( $form_id, '_form', $markup );
		}

		if ( isset( $updates['notification_email'] ) ) {
			$mail = get_post_meta( $form_id, '_mail', true );
			if ( ! is_array( $mail ) ) {
				$mail = array();
			}
			$mail['recipient'] = $updates['notification_email'];
			update_post_meta( $form_id, '_mail', $mail );
		}

		if ( isset( $updates['confirmation_message'] ) ) {
			$messages = get_post_meta( $form_id, '_messages', true );
			if ( ! is_array( $messages ) ) {
				$messages = array();
			}
			$messages['mail_sent_ok'] = $updates['confirmation_message'];
			update_post_meta( $form_id, '_messages', $messages );
		}

		return array(
			'plugin'  => 'contact-form-7',
			'id'      => $form_id,
			'updated' => array_keys( $updates ),
			'success' => true,
		);
	}

	/**
	 * Update a WPForms form.
	 *
	 * @param int   $form_id Form post ID.
	 * @param array $updates Updates to apply.
	 * @return array
	 */
	private function update_wpforms( int $form_id, array $updates ): array {
		if ( ! function_exists( 'wpforms' ) ) {
			return array( 'error' => 'WPForms is not active.' );
		}

		$form = wpforms()->form->get( $form_id );
		if ( ! $form ) {
			return array( 'error' => "WPForms form ID {$form_id} not found." );
		}

		$data = wpforms_decode( $form->post_content );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( isset( $updates['title'] ) ) {
			$data['settings']['form_title'] = $updates['title'];
		}

		if ( isset( $updates['fields'] ) ) {
			$wpf_fields      = array();
			$field_id_cursor = 1;
			foreach ( $updates['fields'] as $field ) {
				$type      = sanitize_key( $field['type'] ?? 'text' );
				$label     = sanitize_text_field( $field['label'] ?? 'Field ' . $field_id_cursor );
				$wpf_field = array(
					'id'          => $field_id_cursor,
					'type'        => $type,
					'label'       => $label,
					'required'    => ! empty( $field['required'] ) ? '1' : '',
					'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
					'size'        => 'medium',
				);
				if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
					$options = array_map( 'sanitize_text_field', (array) ( $field['options'] ?? array() ) );
					$choices = array();
					foreach ( $options as $i => $opt ) {
						$choices[ $i + 1 ] = array(
							'label' => $opt,
							'value' => '',
							'image' => '',
						);
					}
					$wpf_field['choices'] = $choices;
				}
				$wpf_fields[ $field_id_cursor ] = $wpf_field;
				++$field_id_cursor;
			}
			$data['fields'] = $wpf_fields;
		}

		if ( isset( $updates['notification_email'] ) ) {
			$notifs = $data['settings']['notifications'] ?? array();
			if ( ! empty( $notifs ) ) {
				$first_key                     = array_key_first( $notifs );
				$notifs[ $first_key ]['email'] = $updates['notification_email'];
			}
			$data['settings']['notifications'] = $notifs;
		}

		if ( isset( $updates['confirmation_message'] ) ) {
			$confs = $data['settings']['confirmations'] ?? array();
			if ( ! empty( $confs ) ) {
				$first_key                      = array_key_first( $confs );
				$confs[ $first_key ]['message'] = $updates['confirmation_message'];
			}
			$data['settings']['confirmations'] = $confs;
		}

		$result = wpforms()->form->update(
			$form_id,
			$data,
			array( 'post_title' => $updates['title'] ?? $form->post_title )
		);

		return array(
			'plugin'  => 'wpforms',
			'id'      => $form_id,
			'updated' => array_keys( $updates ),
			'success' => (bool) $result,
		);
	}

	/**
	 * Update a Gravity Forms form.
	 *
	 * @param int   $form_id GF form ID.
	 * @param array $updates Updates to apply.
	 * @return array
	 */
	private function update_gravity( int $form_id, array $updates ): array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array( 'error' => 'Gravity Forms is not active.' );
		}

		$form = \GFAPI::get_form( $form_id );
		if ( ! $form || is_wp_error( $form ) ) {
			return array( 'error' => "Gravity Forms form ID {$form_id} not found." );
		}

		if ( isset( $updates['title'] ) ) {
			$form['title'] = $updates['title'];
		}

		if ( isset( $updates['fields'] ) ) {
			$form['fields'] = $this->build_gf_fields( $updates['fields'] );
		}

		if ( isset( $updates['notification_email'] ) ) {
			if ( ! empty( $form['notifications'] ) ) {
				$first_key                                 = array_key_first( $form['notifications'] );
				$form['notifications'][ $first_key ]['to'] = $updates['notification_email'];
			}
		}

		if ( isset( $updates['confirmation_message'] ) ) {
			if ( ! empty( $form['confirmations'] ) ) {
				$first_key                                      = array_key_first( $form['confirmations'] );
				$form['confirmations'][ $first_key ]['message'] = $updates['confirmation_message'];
			}
		}

		$result = \GFAPI::update_form( $form );

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return array(
			'plugin'  => 'gravityforms',
			'id'      => $form_id,
			'updated' => array_keys( $updates ),
			'success' => true,
		);
	}

	/**
	 * Update a Fluent Forms form.
	 *
	 * @param int   $form_id FF form ID.
	 * @param array $updates Updates to apply.
	 * @return array
	 */
	private function update_fluent( int $form_id, array $updates ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'fluentform_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array( 'error' => 'Fluent Forms is not active or its tables are missing.' );
		}

		$set_data   = array( 'updated_at' => current_time( 'mysql' ) );
		$set_format = array( '%s' );

		if ( isset( $updates['title'] ) ) {
			$set_data['title'] = $updates['title'];
			$set_format[]      = '%s';
		}

		if ( isset( $updates['fields'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$existing                = $wpdb->get_var( $wpdb->prepare( "SELECT form_fields FROM {$table} WHERE id = %d", $form_id ) );
			$form_fields             = json_decode( $existing ?? '{}', true );
			$form_fields['fields']   = $this->build_fluentform_fields( $updates['fields'] );
			$set_data['form_fields'] = wp_json_encode( $form_fields );
			$set_format[]            = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->update(
			$table,
			$set_data,
			array( 'id' => $form_id ),
			$set_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return array( 'error' => 'Failed to update the Fluent Forms form.' );
		}

		return array(
			'plugin'  => 'fluentform',
			'id'      => $form_id,
			'updated' => array_keys( $updates ),
			'success' => true,
		);
	}

	// -------------------------------------------------------------------------
	// Field format translators (shared with create_form logic)
	// -------------------------------------------------------------------------

	/**
	 * Build CF7 markup from universal field schema.
	 *
	 * @param array $fields Universal fields.
	 * @return string
	 */
	private function build_cf7_markup( array $fields ): string {
		$lines = array();
		foreach ( $fields as $index => $field ) {
			$type        = sanitize_key( $field['type'] ?? 'text' );
			$label       = sanitize_text_field( $field['label'] ?? 'Field ' . ( $index + 1 ) );
			$required    = ! empty( $field['required'] );
			$placeholder = sanitize_text_field( $field['placeholder'] ?? '' );
			$slug        = sanitize_title( $label );
			$req_mark    = $required ? '*' : '';
			$ph_tag      = $placeholder ? ' placeholder "' . $placeholder . '"' : '';

			switch ( $type ) {
				case 'email':
					$lines[] = "<p>{$label}\n    [email{$req_mark} {$slug}{$ph_tag}]</p>";
					break;
				case 'tel':
					$lines[] = "<p>{$label}\n    [tel{$req_mark} {$slug}{$ph_tag}]</p>";
					break;
				case 'textarea':
					$lines[] = "<p>{$label}\n    [textarea{$req_mark} {$slug}{$ph_tag}]</p>";
					break;
				case 'select':
					$opts    = implode( '" "', array_map( 'sanitize_text_field', (array) ( $field['options'] ?? array() ) ) );
					$lines[] = "<p>{$label}\n    [select{$req_mark} {$slug} \"{$opts}\"]</p>";
					break;
				case 'radio':
					$opts    = implode( '" "', array_map( 'sanitize_text_field', (array) ( $field['options'] ?? array() ) ) );
					$lines[] = "<p>{$label}\n    [radio{$req_mark} {$slug} \"{$opts}\"]</p>";
					break;
				case 'checkbox':
					$opts    = implode( '" "', array_map( 'sanitize_text_field', (array) ( $field['options'] ?? array() ) ) );
					$lines[] = "<p>{$label}\n    [checkbox {$slug} \"{$opts}\"]</p>";
					break;
				default:
					$cf7_type = 'hidden' === $type ? 'hidden' : 'text';
					$lines[]  = "<p>{$label}\n    [{$cf7_type}{$req_mark} {$slug}{$ph_tag}]</p>";
					break;
			}
		}
		$lines[] = '';
		$lines[] = '<p>[submit "Send"]</p>';
		return implode( "\n", $lines );
	}

	/**
	 * Build GF fields array from universal schema.
	 *
	 * @param array $fields Universal fields.
	 * @return array
	 */
	private function build_gf_fields( array $fields ): array {
		$gf_fields = array();
		$id        = 1;
		foreach ( $fields as $field ) {
			$type = sanitize_key( $field['type'] ?? 'text' );
			$gf_f = array(
				'id'         => $id,
				'type'       => $type,
				'label'      => sanitize_text_field( $field['label'] ?? 'Field ' . $id ),
				'isRequired' => ! empty( $field['required'] ),
			);
			if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
				$choices = array();
				foreach ( (array) ( $field['options'] ?? array() ) as $opt ) {
					$opt       = sanitize_text_field( $opt );
					$choices[] = array(
						'text'       => $opt,
						'value'      => $opt,
						'isSelected' => false,
						'price'      => '',
					);
				}
				$gf_f['choices'] = $choices;
			}
			$gf_fields[] = $gf_f;
			++$id;
		}
		return $gf_fields;
	}

	/**
	 * Build Fluent Forms fields array from universal schema.
	 *
	 * @param array $fields Universal fields.
	 * @return array
	 */
	private function build_fluentform_fields( array $fields ): array {
		$ff_fields = array();
		$el_map    = array(
			'text'     => 'input_text',
			'email'    => 'input_email',
			'tel'      => 'phone',
			'textarea' => 'textarea',
			'select'   => 'select',
			'radio'    => 'input_radio',
			'checkbox' => 'input_checkbox',
			'number'   => 'input_number',
			'date'     => 'input_date',
			'file'     => 'input_file',
			'hidden'   => 'input_hidden',
		);
		foreach ( $fields as $index => $field ) {
			$type     = sanitize_key( $field['type'] ?? 'text' );
			$label    = sanitize_text_field( $field['label'] ?? 'Field ' . ( $index + 1 ) );
			$slug     = 'field_' . ( $index + 1 );
			$ff_field = array(
				'index'    => $index,
				'element'  => $el_map[ $type ] ?? 'input_text',
				'settings' => array(
					'label'            => $label,
					'name'             => $slug,
					'placeholder'      => sanitize_text_field( $field['placeholder'] ?? '' ),
					'validation_rules' => array(
						'required' => array(
							'value'   => ! empty( $field['required'] ),
							'message' => 'This field is required.',
						),
					),
				),
			);
			if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
				$adv_opts = array();
				foreach ( (array) ( $field['options'] ?? array() ) as $opt ) {
					$opt        = sanitize_text_field( $opt );
					$adv_opts[] = array(
						'label' => $opt,
						'value' => sanitize_title( $opt ),
					);
				}
				$ff_field['settings']['advanced_options'] = $adv_opts;
			}
			$ff_fields[] = $ff_field;
		}
		return $ff_fields;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Auto-detect the primary active form plugin.
	 *
	 * @return string Plugin slug or empty string if none found.
	 */
	private function detect_plugin(): string {
		if ( class_exists( 'WPCF7' ) ) {
			return 'contact-form-7';
		}
		if ( function_exists( 'wpforms' ) ) {
			return 'wpforms';
		}
		if ( class_exists( 'GFAPI' ) ) {
			return 'gravityforms';
		}
		if ( function_exists( 'wpFluentForm' ) ) {
			return 'fluentform';
		}
		return '';
	}

	/**
	 * Get tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}

return new Update_Form();
