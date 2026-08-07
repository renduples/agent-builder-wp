<?php
/**
 * Tool: load_skill
 *
 * @package Agent_Builder
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the full SKILL.md body for one skill by slug.
 */
class Load_Skill extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'load_skill';
	}

	public function get_description(): string {
		return 'Load the full instructions for one skill by slug (from the [SKILLS] list in your system prompt). Call this before acting on a task that matches a skill — the index only gives you a name and description, not the workflow itself.';
	}

	public function get_category(): string {
		return 'knowledge';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'slug' => array(
					'type'        => 'string',
					'description' => 'Skill slug, from the [slug] shown in the [SKILLS] index.',
				),
			),
			'required'   => array( 'slug' ),
		);
	}

	public function execute( array $arguments ): array {
		$slug = sanitize_key( $arguments['slug'] ?? '' );
		if ( '' === $slug ) {
			return array( 'error' => 'slug is required.' );
		}

		if ( ! class_exists( '\\Agentic\\Skills_Registry' ) ) {
			return array( 'error' => 'Skills are not available on this site.' );
		}

		// Only skills actually available to the calling agent — global skills
		// plus any scoped specifically to it — can be loaded, so an agent
		// cannot pull in instructions scoped to a different agent.
		$agent_slug     = $this->get_calling_agent_slug();
		$visible_skills = \Agentic\Skills_Registry::get_for_agent( $agent_slug );
		$skill          = null;
		foreach ( $visible_skills as $candidate ) {
			if ( $slug === $candidate['slug'] ) {
				$skill = $candidate;
				break;
			}
		}

		if ( ! $skill ) {
			return array( 'error' => "No enabled skill found with slug '{$slug}'." );
		}

		return array(
			'slug'        => $skill['slug'],
			'name'        => $skill['name'],
			'description' => $skill['description'],
			'body'        => $skill['content'],
		);
	}
}

return new Load_Skill();
