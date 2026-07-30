<?php
/**
 * Tool: get_agent_template
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

class Get_Agent_Template extends Tool_Base {

	public function get_name(): string {
		return 'get_agent_template';
	}

	public function get_description(): string {
		return 'Get a blank agent template with all required components.';
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
			'minimal' => array(
				'type'        => 'boolean',
				'description' => 'Return minimal template instead of full.',
				'required'    => false,
			),
		);
	}

	public function execute( array $args ): array {
		$minimal      = $args['minimal'] ?? false;
		$library_path = \Agentic\Tool_Helpers::get_library_path();

		if ( $minimal ) {
			$template_file = $library_path . 'assistant-trainer/templates/minimal-agent.php.template';
			$template      = file_exists( $template_file ) ? file_get_contents( $template_file ) : '';
		} else {
			$content_writer_file = $library_path . 'content-writer/agent.php';
			if ( ! file_exists( $content_writer_file ) ) {
				// Bundled agents are declarative — use the agent.json as the reference.
				$content_writer_file = $library_path . 'content-writer/agent.json';
			}
			$template            = file_exists( $content_writer_file ) ? (string) file_get_contents( $content_writer_file ) : '';
			$template            = preg_replace( '/Content Writer/', '[AGENT_NAME]', $template );
			$template            = preg_replace( '/content-writer/', '[SLUG]', $template );
		}

		return array(
			'template'     => $template,
			'type'         => $minimal ? 'minimal' : 'full',
			'placeholders' => array(
				'[AGENT_NAME]'  => 'Display name',
				'[SLUG]'        => 'kebab-case-slug',
				'[CLASS_NAME]'  => 'Agentic_Your_Agent_Name',
				'[DESCRIPTION]' => 'What your agent does',
				'[CATEGORY]'    => 'Content, Admin, E-commerce, Frontend, Developer, or Marketing',
			),
		);
	}
}

return new Get_Agent_Template();
