<?php
declare(strict_types=1);

namespace Agentic\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Product Search
 *
 * Search and filter products with pagination.
 *
 * @package Agentic\Tools
 */
class Wc_Search_Products extends \Agentic\Tool_Base {

	public function get_name(): string {
		return 'wc_search_products';
	}

	public function get_description(): string {
		return 'Search WooCommerce products by keyword, category, status, stock level, or price range. Returns paginated results.';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'search'       => array(
					'type'        => 'string',
					'description' => 'Keyword to search in product title and description.',
				),
				'category'     => array(
					'type'        => 'string',
					'description' => 'Product category slug to filter by.',
				),
				'status'       => array(
					'type'        => 'string',
					'description' => 'Product status: publish, draft, pending, private. Defaults to any.',
				),
				'stock_status' => array(
					'type'        => 'string',
					'description' => 'Stock status filter: instock, outofstock, onbackorder.',
					'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
				),
				'min_price'    => array(
					'type'        => 'number',
					'description' => 'Minimum price filter.',
				),
				'max_price'    => array(
					'type'        => 'number',
					'description' => 'Maximum price filter.',
				),
				'orderby'      => array(
					'type'        => 'string',
					'description' => 'Sort field: date, title, price, popularity, rating. Defaults to date.',
					'enum'        => array( 'date', 'title', 'price', 'popularity', 'rating' ),
				),
				'order'        => array(
					'type'        => 'string',
					'description' => 'Sort direction: ASC or DESC. Defaults to DESC.',
					'enum'        => array( 'ASC', 'DESC' ),
				),
				'page'         => array(
					'type'        => 'integer',
					'description' => 'Page number for pagination. Defaults to 1.',
				),
				'per_page'     => array(
					'type'        => 'integer',
					'description' => 'Results per page (max 50). Defaults to 20.',
				),
			),
		);
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not active.' );
		}

		$per_page = min( (int) ( $arguments['per_page'] ?? 20 ), 50 );
		$page     = max( (int) ( $arguments['page'] ?? 1 ), 1 );

		$args = array(
			'limit'   => $per_page,
			'page'    => $page,
			'orderby' => $arguments['orderby'] ?? 'date',
			'order'   => $arguments['order'] ?? 'DESC',
		);

		if ( ! empty( $arguments['search'] ) ) {
			$args['s'] = sanitize_text_field( $arguments['search'] );
		}
		if ( ! empty( $arguments['category'] ) ) {
			$args['category'] = array( sanitize_text_field( $arguments['category'] ) );
		}
		if ( ! empty( $arguments['status'] ) ) {
			$args['status'] = sanitize_key( $arguments['status'] );
		}
		if ( ! empty( $arguments['stock_status'] ) ) {
			$args['stock_status'] = sanitize_key( $arguments['stock_status'] );
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
			$results[] = array(
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'type'          => $product->get_type(),
				'status'        => $product->get_status(),
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'sku'           => $product->get_sku(),
				'stock_status'  => $product->get_stock_status(),
				'stock_qty'     => $product->get_stock_quantity(),
				'categories'    => wp_list_pluck( $product->get_category_ids() ? array_map( 'get_term', $product->get_category_ids() ) : array(), 'name' ),
				'permalink'     => $product->get_permalink(),
			);
		}

		// Total count for pagination.
		$count_args           = $args;
		$count_args['limit']  = -1;
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

return new Wc_Search_Products();
