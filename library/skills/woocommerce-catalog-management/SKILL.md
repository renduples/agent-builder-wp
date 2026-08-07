---
name: woocommerce-catalog-management
description: "Use this skill whenever the user wants to create, edit, price, stock, or organize WooCommerce products, categories, or coupons. Trigger on requests like 'add a product', 'update the price of X', 'restock Y', 'create a coupon', 'organize my products into categories', 'which products are low on stock', or any mention of the WooCommerce catalog, inventory, or pricing. Do NOT trigger for order-specific requests (refunds, order status, customer lookups) — use woocommerce-order-management for those."
allowed-tools: wc_get_product wc_search_products wc_create_product wc_update_product wc_bulk_update_products wc_update_stock wc_get_stock_report wc_get_categories wc_manage_category wc_get_coupons wc_create_coupon
---

# WooCommerce Catalog Management

## Available Tools

| Tool | When to use |
|---|---|
| `wc_get_product` | Load full details for one product (variations, images, attributes, SEO) before editing it. |
| `wc_search_products` | Find products by keyword, category, status, stock level, or price range. Use before create/update when the product might already exist. |
| `wc_create_product` | Create a new product. Defaults to **draft** status — it will not appear on the storefront until published. |
| `wc_update_product` | Edit one existing product's name, description, price, stock, status, categories, or SEO metadata. |
| `wc_bulk_update_products` | Edit several products in one call (name, description, short_description, regular_price, sale_price, status). Use this instead of looping `wc_update_product` for anything touching more than ~3 products. |
| `wc_update_stock` | Set/increase/decrease a product's (or variation's) stock quantity. Auto-enables stock management if it was off. |
| `wc_get_stock_report` | List out-of-stock, low-stock, and backordered products with current quantities. |
| `wc_get_categories` | Get the category tree with slugs and product counts. |
| `wc_manage_category` | Create, update, or delete a product category. |
| `wc_get_coupons` | List existing coupons — check before creating a new one to avoid duplicates. |
| `wc_create_coupon` | Create a discount coupon (type, amount, usage limits, expiry). |

## Workflows

### Creating a new product
1. Call `wc_search_products` with the intended title/SKU to check it doesn't already exist.
2. If it needs a category that may not exist yet, call `wc_get_categories` and, if missing, `wc_manage_category` to create it first.
3. Call `wc_create_product` with title, description, price, SKU, categories. It lands as **draft** — tell the user it needs publishing (`wc_update_product` with `status: publish`) once they've reviewed it.

### Updating price or details on one product
1. Call `wc_get_product` first if you don't already have the current values — never guess at what's being changed from.
2. Call `wc_update_product` with only the changed fields.
3. Confirm back to the user what changed (old value → new value), not just "updated".

### Updating many products at once
1. Use `wc_search_products` to build the list of `product_id`s that match the criteria (category, status, price range, etc.).
2. Call `wc_bulk_update_products` once with all of them in `updates` — do not loop `wc_update_product` per item.
3. Report how many succeeded/failed if the tool result indicates partial failure.

### Restocking or adjusting inventory
1. Call `wc_get_stock_report` first if the user's request is vague ("restock what's low") rather than naming a specific product.
2. Use `wc_update_stock` with `operation: "increase"`/`"decrease"` for relative changes, `"set"` for an exact new total — confirm which the user means before calling; "add 50" is `increase`, "we now have 50" is `set`.
3. For variable products, `product_id` is the **variation** ID, not the parent product ID — use `wc_get_product` to find variation IDs first.

### Creating a coupon
1. Call `wc_get_coupons` to check a similar code doesn't already exist.
2. Confirm discount type, amount, and any usage limit/expiry with the user before calling `wc_create_coupon` — coupons are customer-facing and mistakes are visible immediately.

## Quality Rules

- **Never publish a product the user hasn't reviewed.** New products default to draft; leave them there unless explicitly told to publish.
- **Confirm price changes in currency, not just numbers** — restate "$19.99 → $24.99", don't just say "price updated".
- **Bulk over loop.** Any edit touching more than ~3 products goes through `wc_bulk_update_products`, not repeated single calls.
- **Check before creating.** Search for an existing product/category/coupon before creating a new one — duplicate catalog entries are hard to clean up later.
- **Stock operation ambiguity is real** — "increase" vs "set" changes the outcome completely. If the user's phrasing is ambiguous, ask rather than guess.
