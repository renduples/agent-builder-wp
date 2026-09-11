# Safety Center

The Safety Center is your command center for agent security. It shows you what tools your agents can access, which actions are waiting for approval, whether your audit logs have been tampered with, and gives you an instant kill switch if anything goes wrong.

## Overview

Safety Center brings together five critical safety controls:
- **Tool risk inventory** — What your agents can do and how risky each capability is
- **Approvals status** — Actions waiting for your go-ahead
- **Audit integrity** — Whether your event log has been tampered with
- **Emergency Stop** — Instantly shut down all agents
- **Active agents** — Which agents are running and what high-risk capabilities they have

This is where you come when you need to understand your security posture at a glance, or when you need to lock everything down quickly.

## Safety Center sections

### Tool risk inventory

Tools are the actions your agents can perform. This card shows:
- **Enabled count** — How many tools are currently active
- **Disabled count** — How many tools are turned off
- **Highest enabled risk** — The most dangerous thing your agents are allowed to do right now (shown in color: green/low, yellow/medium, red/high, black/extreme)

The hint explains: high-risk tools need careful review before you enable them. Click **Review tools** to jump to the [Tools](https://agentic-plugin.com/agent-tools/) page and see the full list.

In **Advanced mode**, you can drill down to see every tool and its risk level on this very screen.

### Approvals status

This shows you the current approval backlog and safety settings:
- **Pending count** — How many actions are waiting for you right now
- **Mode** — Whether agents are running in Automatic, On Request, or Manual mode
- **Comfort profile** — Your chosen safety level (Relaxed, Balanced, or Cautious)

The hint explains: agents pause here automatically when they want to make important changes. Nothing runs until you say yes.

Click **Open approvals** to review and action each pending approval, or jump to [Approvals](https://agentic-plugin.com/approval-queue/) to customize how many approvals you see and which ones auto-approve.

### Last verification

Agent Builder's audit log is cryptographically chained — each entry includes a hash of the previous entry. This card shows the integrity check status:
- **Verified** (green) — The chain is unbroken; your audit log hasn't been tampered with
- **Needs attention** (red) — The chain is broken; someone may have edited or deleted entries

Below the status:
- **Rows checked** — How many audit entries were verified
- **Broken at entry** (if broken) — The specific entry where tampering was detected

Click **View integrity details** to see the full audit chain and exactly which entry is broken. If you see this card turn red, investigate immediately — it means your audit log can't be trusted.

### Emergency Stop

Your ultimate safety control:
- **Status indicator** — On (red) or Off (green)
- **Description** — What Emergency Stop does when activated

**What happens when you turn it ON:**
- All agents are deactivated immediately
- All pending and in-progress jobs are cancelled
- AI providers are disconnected
- No new agent activity is allowed until you restore service

**What happens when you restore (turn it OFF):**
- Agents are re-enabled in their previous on/off state
- Providers are reconnected
- The job queue resumes processing
- Everything is logged in [Activity](https://agentic-plugin.com/audit-log/)

Turn this on only in emergencies. You'll be asked to confirm the action before it takes effect.

### Active agents

This card shows you what agents are currently running and their capability summary:
- **Active count** — How many agents are enabled right now
- **With high-risk tools** — How many of those agents have access to dangerous capabilities
- **With MCP enabled** — How many can reach external APIs or data sources via MCP

Click **View agent scopes** to see the full per-agent breakdown. In **Advanced mode**, you can see every tool each agent has access to.

## Navigation in Safety Center

The screen is divided into sections:

**Overview cards** (top) — The five cards described above, visible in both Basic and Advanced modes.

**Advanced sections** (below, Advanced mode only) — Additional drill-downs:
- **Agent scopes** — Detailed list of each active agent, their risk tier, and capabilities
- **Audit integrity details** — Full audit chain with hash verification and tampering indicators

## Common tasks

### Reviewing a pending approval

1. Open Safety Center
2. In **Approvals status**, click **Open approvals**
3. The [Approvals](https://agentic-plugin.com/approval-queue/) page opens
4. Review the pending action (what agent, what it wants to do, why it needs approval)
5. Click Approve to let it run once, or Reject to cancel it
6. The action is logged either way

### Checking tool risk levels

1. Open Safety Center
2. In **Tool risk inventory**, look at the "Highest enabled risk" badge
3. If it's red (high), click **Review tools** to see which tool is dangerous
4. On the [Tools](https://agentic-plugin.com/agent-tools/) page, you can disable risky tools or adjust their risk settings

In Advanced mode, you can see all tools and their risk levels right on this screen.

### Understanding audit integrity

If the **Last verification** card shows "Needs attention" (red):

1. Click **View integrity details**
2. Find the entry marked as broken (shown in red or highlighted)
3. Check the [Activity](https://agentic-plugin.com/audit-log/) > Audit tab to see what that entry logs
4. If you didn't authorize that change, investigate who accessed your admin panel

The broken entry is a tamper marker, not necessarily proof of malice — it could mean a database backup was restored, or a plugin directly modified the log. But always investigate.

### Turning on Emergency Stop

Only do this in a crisis (e.g., agents are running wild, consuming credits, or making unwanted changes):

1. Open Safety Center
2. Scroll to **Emergency Stop**
3. Click **Disable All Agents**
4. Confirm the action in the popup
5. The banner turns red and says "Emergency Stop is ACTIVE"

To restore:

1. Click **Restore agent system** on the Emergency Stop card
2. Confirm again
3. Wait for the system to reconnect and resume

All actions are logged.

### Viewing agent capabilities

1. Open Safety Center
2. Scroll to **Active agents**
3. If in Advanced mode, click **View agent scopes** to see a table of every active agent
4. Each row shows:
   - Agent name
   - Risk tier (the highest risk any of its tools carry)
   - Tools it can access (listed by name)
   - MCP status (whether it can call external APIs)

This is where you see the full picture of what each agent can actually do.

## FAQ

**Q: What's the difference between high and extreme risk?**

A: **High risk** tools require approval before running (they pause in the Approvals queue). **Extreme risk** tools are so dangerous that agents can't see them at all — they're hidden entirely. You can't enable extreme-risk tools; they're locked.

**Q: Why does my audit integrity check show "Broken at entry #12345"?**

A: The cryptographic hash chain breaks at that entry. This usually means:
- Someone restored a database backup
- A plugin directly edited the audit log table
- There was a server crash during a log write

To fix it, manually verify that entry looks correct in the [Activity](https://agentic-plugin.com/audit-log/) > Audit tab. If it's legitimate, the next log entry will start a fresh chain. If it's suspicious, investigate who had access to your database.

**Q: Can I see which agent is using which tool?**

A: Yes. Open Safety Center and switch to Advanced mode (top-right toggle). Under "Agent scopes," you'll see a full breakdown: each agent, its risk tier, and every tool it can access.

**Q: What happens to jobs when I turn on Emergency Stop?**

A: All running jobs are cancelled immediately. Their status is logged in [Activity](https://agentic-plugin.com/audit-log/) > Audit. Jobs that were approved but not yet started are also cancelled. The approvals themselves stay in the queue — you'll see them when you restore service.

**Q: Should I leave Emergency Stop on for security?**

A: No. Emergency Stop is for emergencies. If you want to disable agents normally, go to [Agents](https://agentic-plugin.com/installed-agents/) and toggle them off individually. Emergency Stop is for "something is wrong, lock it down NOW" scenarios.

**Q: Can I adjust which tools agents can see?**

A: Yes, but not from Safety Center. Go to [Tools](https://agentic-plugin.com/agent-tools/) to enable/disable tools globally. From there, you can also set per-tool risk levels and approval requirements. If you need per-agent tool control, that's a Pro feature.

**Q: What's MCP?**

A: Model Context Protocol (MCP) lets your agents reach external APIs, databases, and data sources. The **With MCP enabled** count shows how many agents have this capability turned on. You can control it per-agent in the [Settings](https://agentic-plugin.com/settings/).

**Q: Why do I need "Comfort profiles"?**

A: Comfort profiles are pre-configured approval rules. **Relaxed** auto-approves most actions. **Balanced** auto-approves low-risk actions and pauses medium-risk ones. **Cautious** requires approval for anything risky. They're a shortcut so you don't have to set approval thresholds manually.

**Q: Can I undo a rejected approval?**

A: Not directly. When you reject an approval, the action is cancelled and the agent is notified. If the agent tries again later, it creates a fresh approval entry. The old rejection stays in the [Activity](https://agentic-plugin.com/audit-log/) log forever.

**Q: How often does the audit integrity check run?**

A: It runs on-demand when you view Safety Center. The "Last verification" card shows when the most recent check happened and how many rows it checked. If you want continuous monitoring, that's a Pro feature.

**Q: What's the difference between "disabled" and "extreme risk" tools?**

A: **Disabled** tools are off but can be re-enabled by you at any time. **Extreme risk** tools are locked off and can't be enabled — they're permanently hidden from agents. Extreme risk is used for truly dangerous operations like database drops or permission changes.
