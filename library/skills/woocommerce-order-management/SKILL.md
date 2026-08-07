---
name: woocommerce-order-management
description: "Use this skill whenever the user wants to look up, update, refund, or annotate WooCommerce orders, or look up a customer's order history or store performance. Trigger on requests like 'find order #1234', 'mark this order as shipped', 'refund this customer', 'add a note to this order', 'what has this customer bought before', or 'how are sales this month'. Do NOT trigger for product/catalog/pricing/inventory requests — use woocommerce-catalog-management for those."
allowed-tools: wc_get_orders wc_get_order wc_add_order_note wc_update_order_status wc_create_refund wc_get_customer wc_get_customers wc_get_store_stats
---

# WooCommerce Order Management

## Available Tools

| Tool | When to use |
|---|---|
| `wc_get_orders` | Search orders by status, date range, or customer. Returns summaries — use `wc_get_order` for full detail on one. |
| `wc_get_order` | Full detail on a single order: line items, billing/shipping, existing notes. Always call this before changing or refunding an order. |
| `wc_add_order_note` | Add a note. **Customer-facing notes email the customer** — default to an internal admin note unless the user explicitly wants the customer notified. |
| `wc_update_order_status` | Change status (pending/processing/on-hold/completed/cancelled/refunded), optionally with a note. |
| `wc_create_refund` | Record a refund against an order. **Does not trigger an actual payment gateway refund** — it's a WooCommerce record only. Requires `order_id`, `amount`, and `reason`. |
| `wc_get_customer` | Full profile + order history for one customer by WordPress user ID. |
| `wc_get_customers` | Search customers by name/email to find their ID first. |
| `wc_get_store_stats` | Revenue, order counts, top products, stock health for a period. |

## Workflows

### Looking up an order
1. If the user gives an order number, call `wc_get_order` directly.
2. If they describe it instead ("the order from yesterday for John"), call `wc_get_orders` with the relevant filters first, confirm which one they mean if more than one matches.

### Changing order status
1. Call `wc_get_order` first to see the current status and whether the transition makes sense (e.g. don't mark a `cancelled` order `completed` without asking).
2. Call `wc_update_order_status`. Only set `customer_note: true` if the user wants the customer emailed — default is internal-only.

### Issuing a refund
1. Call `wc_get_order` to confirm the order total and that the refund amount requested doesn't exceed it.
2. **Always confirm the exact amount and reason with the user before calling `wc_create_refund`** — this is explicit in the tool's own guidance, not optional.
3. After recording the refund, tell the user clearly that this created a WooCommerce record only — if payment was taken through a gateway (Stripe, PayPal, etc.), the actual money movement needs to happen in that gateway's dashboard or the WooCommerce admin UI, not through this tool.
4. Consider whether `restock_items` should be true — ask if unclear.

### Investigating a customer
1. If you only have a name or email, call `wc_get_customers` to resolve the WordPress user ID first.
2. Call `wc_get_customer` for their full profile and order history.

### Reporting on store performance
1. Call `wc_get_store_stats` with the requested period.
2. Lead with the headline numbers (revenue, order count) before top products or stock health, unless the user asked specifically about one of those.

## Quality Rules

- **Never call `wc_create_refund` without restating the exact amount and reason back to the user first** — refunds move money records and are hard to cleanly reverse.
- **Default order notes to internal.** Only email the customer (`customer_note: true` / note on `wc_update_order_status`) when the user's intent is clearly to communicate with them.
- **State the refund-record caveat every time.** A user unfamiliar with WooCommerce may assume a "refund" tool call returns the customer's money — it doesn't, and saying so prevents a real support problem.
- **Resolve names to IDs before acting** — `wc_get_customers`/`wc_get_orders` first, never guess an ID.
