<?php
/**
 * Runtime wrapper for a site-local (declarative) tool definition.
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      3.3.0
 */

declare(strict_types=1);

namespace Agentic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tool_Base instance backed by a stored site-local definition.
 */
class Site_Local_Tool extends Tool_Base {

	/**
	 * @var array<string, mixed>
	 */
	private array $def;

	/**
	 * @param array<string, mixed> $definition Stored definition.
	 */
	public function __construct( array $definition ) {
		$this->def = $definition;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return (string) ( $this->def['name'] ?? '' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return (string) ( $this->def['description'] ?? '' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_category(): string {
		return (string) ( $this->def['category'] ?? 'custom' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_parameters(): array {
		$params = $this->def['parameters'] ?? null;
		if ( ! is_array( $params ) || empty( $params ) ) {
			return array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			);
		}
		// Ensure properties is object for empty set when encoded to JSON.
		if ( isset( $params['properties'] ) && is_array( $params['properties'] ) && array() === $params['properties'] ) {
			$params['properties'] = new \stdClass();
		}
		return $params;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_risk_level(): string {
		$risk = (string) ( $this->def['risk_level'] ?? 'low' );
		$ok   = array( 'none', 'low', 'medium', 'high', 'extreme' );
		return in_array( $risk, $ok, true ) ? $risk : 'low';
	}

	/**
	 * Site-local tools require Pro at runtime.
	 *
	 * {@inheritdoc}
	 */
	public function is_pro_only(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return Site_Local_Tools::is_feature_available();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_annotations(): array {
		$handler = (string) ( $this->def['handler'] ?? '' );
		$readonly = in_array( $handler, array( 'wp_list_posts', 'wp_get_post', 'wp_get_option', 'http_get' ), true );
		return array(
			'readonly'    => $readonly,
			'destructive' => in_array( $handler, array( 'wp_update_post' ), true ),
			'idempotent'  => $readonly,
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array $arguments Arguments from the LLM.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		return Site_Local_Tools::execute_handler( $this->def, $arguments );
	}
}
