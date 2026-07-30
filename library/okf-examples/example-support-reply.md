---
type: Support reply
title: "Example: Order status reply"
description: Demo only — template for “where is my order” conversations.
status: stable
tags: [example, support, orders]
example: true
generated: { by: system:okf-examples, at: 2026-07-25T00:00:00Z }
---

# Example: Order status reply

> **Demo content.** Agents cannot use this until you turn off the Example flag.

1. Thank the customer; ask for the order number if missing.
2. Look up the order status and tracking.
3. Reply with status, tracking link, and expected delivery.

**Sample:**

> Thanks for getting in touch. Order #{{order_id}} is {{status}}. Tracking: {{tracking_url}}.
