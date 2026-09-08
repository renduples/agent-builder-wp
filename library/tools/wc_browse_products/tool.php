<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Browse Products
 *
 * A public-safe product catalog browser — the shopper-facing counterpart to
 * wc_search_products, which is an admin tool (defaults to every status,
 * returns SKU/stock-quantity/edit_link/total_sales). This tool hard-forces
 * published-only results and returns only fields a storefront's own product
 * pages already show any visitor, so it is safe to expose to an anonymous
 * caller via the WebMCP Bridge (see Webmcp_Bridge::ANONYMOUS_SAFE_TOOLS).
 *
 * @package Agentic\Tools
 */
class Wc_Browse_Products extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_browse_products';
	}

	public function get_description(): string {
		return 'Browse or search the store\'s published product catalog — by keyword, category, or price range. Returns only publicly-visible product details (no SKU-level inventory counts, no sales/admin data).';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'search'    => array(
					'type'        => 'string',
					'description' => 'Keyword to search in product title and description.',
				),
				'category'  => array(
					'type'        => 'string',
					'description' => 'Product category slug to filter by.',
				),
				'min_price' => array(
					'type'        => 'number',
					'description' => 'Minimum price filter.',
				),
				'max_price' => array(
					'type'        => 'number',
					'description' => 'Maximum price filter.',
				),
				'orderby'   => array(
					'type'        => 'string',
					'description' => 'Sort field: date, title, price, popularity, rating. Defaults to date.',
					'enum'        => array( 'date', 'title', 'price', 'popularity', 'rating' ),
				),
				'order'     => array(
					'type'        => 'string',
					'description' => 'Sort direction: ASC or DESC. Defaults to DESC.',
					'enum'        => array( 'ASC', 'DESC' ),
				),
				'page'      => array(
					'type'        => 'integer',
					'description' => 'Page number for pagination. Defaults to 1.',
				),
				'per_page'  => array(
					'type'        => 'integer',
					'description' => 'Results per page (max 24). Defaults to 12.',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$per_page = min( max( (int) ( $arguments['per_page'] ?? 12 ), 1 ), 24 );
		$page     = max( (int) ( $arguments['page'] ?? 1 ), 1 );

		// status is deliberately not a caller-controllable argument at all —
		// this tool must never be able to disclose a draft/pending/private
		// product to an anonymous visitor, regardless of what a caller passes.
		$args = array(
			'status'  => 'publish',
			'limit'   => $per_page,
			'page'    => $page,
			'orderby' => in_array( $arguments['orderby'] ?? '', array( 'date', 'title', 'price', 'popularity', 'rating' ), true ) ? $arguments['orderby'] : 'date',
			'order'   => 'ASC' === strtoupper( (string) ( $arguments['order'] ?? '' ) ) ? 'ASC' : 'DESC',
		);

		if ( ! empty( $arguments['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $arguments['search'] );
		}
		if ( ! empty( $arguments['category'] ) ) {
			$args['category'] = array( sanitize_text_field( (string) $arguments['category'] ) );
		}
		if ( isset( $arguments['min_price'] ) ) {
			$args['min_price'] = (float) $arguments['min_price'];
		}
		if ( isset( $arguments['max_price'] ) ) {
			$args['max_price'] = (float) $arguments['max_price'];
		}

		$products = wc_get_products( $args );
		$results  = array();

		foreach ( $products as $product ) {
			if ( ! $product->is_visible() ) {
				continue;
			}

			$image_id = $product->get_image_id();

			$results[] = array(
				'id'                => $product->get_id(),
				'name'              => $product->get_name(),
				'type'              => $product->get_type(),
				'sku'               => $product->get_sku(),
				'short_description' => wp_strip_all_tags( $product->get_short_description() ),
				'price'             => $product->get_price(),
				'regular_price'     => $product->get_regular_price(),
				'sale_price'        => $product->get_sale_price(),
				'on_sale'           => $product->is_on_sale(),
				'currency'          => get_woocommerce_currency(),
				'in_stock'          => $product->is_in_stock(),
				'purchasable'       => $product->is_purchasable(),
				'average_rating'    => $product->get_average_rating(),
				'review_count'      => $product->get_review_count(),
				'categories'        => wp_list_pluck( array_filter( array_map( 'get_term', $product->get_category_ids() ) ), 'name' ),
				'permalink'         => $product->get_permalink(),
				'image'             => $image_id ? wp_get_attachment_url( $image_id ) : null,
			);
		}

		$count_args           = $args;
		$count_args['limit']  = -1;
		$count_args['page']   = 1;
		$count_args['return'] = 'ids';
		$total                = count( wc_get_products( $count_args ) );

		return array(
			'products'    => $results,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	public function get_annotations(): array {
		return array( 'readonly' => true );
	}
}

return new Wc_Browse_Products();
