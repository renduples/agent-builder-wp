# Agent Builder Cloudflare Transactional Email Worker

This directory contains the recommended Worker code for using Cloudflare's native Email Service as a reliable, cheap, and secure transactional email backend.

## Files

- `agentic-transactional-email.js` — The main Worker code (copy into Cloudflare dashboard or use with Wrangler)
- `wrangler.toml.example` — Example configuration

## Quick Deploy (Dashboard - Easiest)

1. Go to Cloudflare Dashboard → Workers & Pages → Create a Worker
2. Delete the default code and paste the entire contents of `agentic-transactional-email.js`
3. Deploy the Worker
4. Go to **Settings → Variables and Secrets**
   - Add a secret called `EMAIL_AUTH_TOKEN` (generate a strong random string)
5. Add an Email binding:
   - Settings → Bindings → Add binding → Email
   - Binding name: `EMAIL`
   - Destination address: Use an address you have verified in Cloudflare Email (or a catch-all on your domain)

6. Copy the Worker URL (e.g. `https://agentic-email-relay.your-subdomain.workers.dev`)

7. In Agent Builder Pro:
   - Integrations → Cloudflare → Transactional Email
   - Paste the Worker URL
   - Paste the exact same `EMAIL_AUTH_TOKEN` value
   - Save and use the **Test** button

## Using with Wrangler (Recommended for Version Control)

```bash
npm create cloudflare@latest my-email-relay -- --template hello-world
cd my-email-relay
```

Replace `src/index.js` with the content from `agentic-transactional-email.js`.

Create a `wrangler.toml`:

```toml
name = "agentic-email-relay"
main = "src/index.js"
compatibility_date = "2025-05-01"

[[send_email]]
name = "EMAIL"
destination_address = "noreply@yourdomain.com"
```

Then:

```bash
wrangler secret put EMAIL_AUTH_TOKEN
wrangler deploy
```

## Why This Approach?

- No email credentials or API keys are ever stored in your WordPress database.
- You get Cloudflare's excellent global deliverability and reputation.
- Extremely cheap (~$0.35 per 1,000 emails as of 2026).
- Perfectly aligned with agentic workflows (the Worker can even contain additional logic, logging, or suppression lists later).

This is the post-Brevo recommended path for transactional and drip emails in Agent Builder.
