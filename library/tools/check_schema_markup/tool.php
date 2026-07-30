<?php
/**
 * Tool: Check Schema Markup
 *
 * Analyse homepage JSON-LD structured data for AI visibility.
 *
 * @package Agentic\Tools
 */

declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches the homepage and analyses JSON-LD schema markup.
 */
class Check_Schema_Markup extends \Agentic\Tool_Base {

	/**
	 * Get the tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'check_schema_markup';
	}

	/**
	 * Get the tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Fetch the homepage HTML and analyse JSON-LD structured data. Returns detected schema types, installed schema plugins, and a score out of 25 with specific gaps identified.';
	}

	/**
	 * Get the tool category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'ai-visibility';
	}

	/**
	 * Get the tool parameters.
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
	 * Execute the schema markup check.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array Schema analysis results with score and issues.
	 */
	public function execute( array $arguments ): array {
		$rendered = \Agentic\Page_Renderer::fetch( home_url( '/' ) );
		if ( ! $rendered['success'] ) {
			return array(
				'score'   => 0,
				'max'     => 25,
				'error'   => 'Could not fetch homepage: ' . ( $rendered['error'] ?? 'unknown error' ),
				'issues'  => array(
					array(
						'message'  => 'Could not fetch homepage HTML to check schema.',
						'severity' => 'important',
					),
				),
				'passing' => array(),
			);
		}

		$schema_types    = $rendered['meta']['schema_types'] ?? array();
		$schema_warnings = $rendered['meta']['schema_warnings'] ?? array();

		$score   = 0;
		$issues  = array();
		$passing = array();

		foreach ( $schema_warnings as $warning ) {
			$issues[] = array(
				'message'  => $warning,
				'severity' => 'important',
			);
		}

		$has_org = ! empty( array_intersect( $schema_types, array( 'Organization', 'LocalBusiness', 'Corporation', 'NGO', 'EducationalOrganization' ) ) );
		if ( $has_org ) {
			$score    += 8;
			$passing[] = 'Organization/LocalBusiness schema present';
		} else {
			$issues[] = array(
				'message'  => 'No Organization or LocalBusiness schema — AI platforms don\'t know who you are.',
				'severity' => 'important',
			);
		}

		$has_article = ! empty( array_intersect( $schema_types, array( 'Article', 'BlogPosting', 'NewsArticle', 'TechArticle' ) ) );
		if ( $has_article ) {
			$score    += 5;
			$passing[] = 'Article/BlogPosting schema detected';
		} else {
			$issues[] = array(
				'message'  => 'No Article or BlogPosting schema — AI platforms cannot classify your content type.',
				'severity' => 'important',
			);
		}

		if ( in_array( 'BreadcrumbList', $schema_types, true ) ) {
			$score    += 4;
			$passing[] = 'BreadcrumbList schema present';
		} else {
			$issues[] = array(
				'message'  => 'No BreadcrumbList schema.',
				'severity' => 'low',
			);
		}

		if ( in_array( 'FAQPage', $schema_types, true ) ) {
			$score    += 5;
			$passing[] = 'FAQPage schema detected';
		} else {
			$issues[] = array(
				'message'  => 'No FAQPage schema — missing easiest way to get cited by AI.',
				'severity' => 'important',
			);
		}

		if ( in_array( 'Product', $schema_types, true ) ) {
			$score     = min( 25, $score + 3 );
			$passing[] = 'Product schema detected';
		}

		return array(
			'score'                 => min( 25, $score ),
			'max'                   => 25,
			'detected_schema_types' => array_values( $schema_types ),
			'issues'                => $issues,
			'passing'               => $passing,
		);
	}

	/**
	 * Get the tool annotations.
	 *
	 * @return array
	 */
	public function get_annotations(): array {
		return array(
			'readonly' => true,
		);
	}
}

return new Check_Schema_Markup();
