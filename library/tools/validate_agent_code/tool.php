<?php
/**
 * Tool: validate_agent_code
 *
 * @package    Agent_Builder
 * @subpackage Tools
 * @since      2.0.0
 *
 * php version 8.1
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Tool_Base;

class Validate_Agent_Code extends Tool_Base {

	public function get_name(): string {
		return 'validate_agent_code';
	}

	public function get_description(): string {
		return 'Validate agent PHP code for syntax errors and compliance with Agentic standards.';
	}

	public function get_category(): string {
		return 'assistant-trainer';
	}

	public function get_risk_level(): string {
		return 'none';
	}

	public function get_annotations(): array {
		return array(
			'read_only'   => true,
			'destructive' => false,
		);
	}

	public function get_parameters(): array {
		return array(
			'code' => array(
				'type'        => 'string',
				'description' => 'The PHP code to validate.',
				'required'    => true,
			),
		);
	}

	public function execute( array $args ): array {
		$code = $args['code'] ?? '';

		if ( empty( $code ) ) {
			return array( 'error' => 'Code is required' );
		}

		$issues   = array();
		$warnings = array();

		if ( strpos( $code, '<?php' ) !== 0 ) {
			$issues[] = 'Must start with <?php';
		}

		if ( strpos( $code, "if ( ! defined( 'ABSPATH' )" ) === false ) {
			$issues[] = 'Missing ABSPATH security check';
		}

		if ( strpos( $code, 'extends \\Agentic\\Agent_Base' ) === false && strpos( $code, 'extends \Agentic\Agent_Base' ) === false ) {
			$issues[] = 'Must extend \\Agentic\\Agent_Base';
		}

		$required_methods = array( 'get_id' );
		foreach ( $required_methods as $method ) {
			if ( strpos( $code, "function {$method}(" ) === false ) {
				$issues[] = "Missing required method: {$method}() — agents must implement get_id()";
			}
		}

		// Quality checks for high-trust agents
		if ( strpos( $code, 'extends \\Agentic\\Agent_Base' ) === false && strpos( $code, 'extends \Agentic\Agent_Base' ) === false ) {
			$issues[] = 'Must extend \\Agentic\\Agent_Base (do not use a custom base)';
		}

		if ( strpos( $code, 'get_default_welcome_message' ) === false ) {
			$warnings[] = 'Missing get_default_welcome_message() — the agent will feel impersonal';
		}

		if ( strpos( $code, 'get_default_suggested_prompts' ) === false ) {
			$warnings[] = 'Missing get_default_suggested_prompts() — reduces discoverability';
		}

		if ( strpos( $code, 'get_tool_names' ) === false ) {
			$warnings[] = 'No tools declared via get_tool_names() — the agent may be underpowered';
		}

		// Check for weak/generic system prompt patterns in the header
		if ( preg_match( '/Description: .+?\\n/i', $code, $m ) && strlen( trim( $m[0] ) ) < 30 ) {
			$warnings[] = 'Very short description in header — high-quality agents have clear, specific descriptions';
		}

		$syntax_error = $this->validate_php_syntax( $code );
		if ( $syntax_error ) {
			$issues[] = 'PHP syntax error: ' . $syntax_error;
		}

		$is_valid = empty( $issues );

		return array(
			'valid'    => $is_valid,
			'issues'   => $issues,
			'warnings' => $warnings,
			'message'  => $is_valid ? 'Agent code is valid!' : 'Agent code has issues.',
		);
	}

	private function validate_php_syntax( string $code ): ?string {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Required for safe PHP syntax validation.
		set_error_handler(
			function ( $errno, $errstr ) use ( &$last_error ) {
				$last_error = $errstr;
			}
		);

		$last_error = null;
		$tokens     = @token_get_all( $code );

		restore_error_handler();

		if ( $last_error ) {
			return $last_error;
		}

		$bracket_stack = array();
		$line_num      = 1;

		foreach ( $tokens as $token ) {
			if ( is_array( $token ) ) {
				list( $id, $text, $line ) = $token;
				$line_num                 = $line;
				if ( defined( 'T_ERROR' ) && $id === T_ERROR ) {
					return sprintf( 'Parse error on line %d', $line );
				}
			} elseif ( is_string( $token ) ) {
				if ( in_array( $token, array( '{', '[', '(' ), true ) ) {
					$bracket_stack[] = $token;
				} elseif ( in_array( $token, array( '}', ']', ')' ), true ) ) {
					if ( empty( $bracket_stack ) ) {
						return sprintf( 'Unmatched closing bracket "%s" on line %d', $token, $line_num );
					}
					$last  = array_pop( $bracket_stack );
					$pairs = array(
						'{' => '}',
						'[' => ']',
						'(' => ')',
					);
					if ( $pairs[ $last ] !== $token ) {
						return sprintf( 'Mismatched brackets: expected "%s" but got "%s" on line %d', $pairs[ $last ], $token, $line_num );
					}
				}
			}
		}

		if ( ! empty( $bracket_stack ) ) {
			return sprintf( 'Unclosed bracket "%s"', end( $bracket_stack ) );
		}

		if ( strpos( trim( $code ), '<?php' ) !== 0 ) {
			return 'Code must start with <?php tag';
		}

		if ( ! preg_match( '/class\s+\w+/', $code ) ) {
			return 'No valid class definition found';
		}

		return null;
	}
}

return new Validate_Agent_Code();
