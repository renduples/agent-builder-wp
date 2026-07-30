/**
 * Agent Builder Cloudflare Transactional Email Relay
 * Enhanced version with better HTML support and structure.
 */
export default {
  async fetch(request, env, ctx) {
    if (request.method !== 'POST') {
      return new Response('Method Not Allowed', { status: 405 });
    }

    const authHeader = request.headers.get('Authorization') || '';
    const token = authHeader.replace('Bearer ', '').trim();

    if (!env.EMAIL_AUTH_TOKEN || token !== env.EMAIL_AUTH_TOKEN) {
      return Response.json({ success: false, error: 'Unauthorized' }, { status: 401 });
    }

    let payload;
    try {
      payload = await request.json();
    } catch {
      return Response.json({ success: false, error: 'Invalid JSON' }, { status: 400 });
    }

    const { to, subject, text, html, from, from_name, reply_to, headers = {} } = payload;

    if (!to || !subject || (!text && !html)) {
      return Response.json({ success: false, error: 'Missing required fields: to, subject, text or html' }, { status: 400 });
    }

    try {
      const message = {
        to,
        from: from || 'noreply@yourdomain.com',
        subject,
        ...(text && { text }),
        ...(html && { html }),
        ...(reply_to && { reply_to }),
        ...(Object.keys(headers).length && { headers }),
      };

      await env.EMAIL.send(message);

      return Response.json({
        success: true,
        provider: 'cloudflare_email_service',
        timestamp: new Date().toISOString(),
      });
    } catch (err) {
      console.error('Email send failed', err);
      return Response.json({ success: false, error: err.message || String(err) }, { status: 500 });
    }
  },
};
