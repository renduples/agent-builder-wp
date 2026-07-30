---
name: git
description: "Use this skill whenever the user wants to interact with a git repository or the GitHub API. Trigger for: checking repo status or history, deploying code by pulling from a remote, staging and committing file changes, pushing commits to a remote, creating or listing GitHub issues or pull requests, reading commit history on GitHub, managing GitHub releases, or any task that mentions 'git', 'GitHub', 'commit', 'push', 'pull', 'deploy via git', 'branch', 'PR', or 'issue tracker'. Two modes: local git operations (tools that run git commands on the server) and GitHub API calls (tool that calls the GitHub REST API). Use local tools for server-side deployment and repository management; use github_api for anything that requires talking to GitHub's servers."
---

# Git & GitHub Skill

## Available Tools

### Local git tools (run on the server)

| Tool | When to use |
|---|---|
| `git_status` | Check working tree — branch, staged/unstaged/untracked files, ahead/behind. Always call this first. |
| `git_log` | Read recent commit history. Supports filtering by branch or file path. |
| `git_diff` | Show the actual diff of changes. Pass `staged: true` for what would be committed. |
| `git_pull` | Pull latest commits from a remote. Primary tool for deploying code. |
| `git_commit` | Stage files and create a commit. Pass specific `files` or omit to stage all tracked changes. |
| `git_push` | Push local commits to a remote. Confirm with user before running on production remotes. |

### GitHub API tool

| Tool | When to use |
|---|---|
| `github_api` | Call any GitHub REST API endpoint: issues, PRs, commits, releases, actions, repositories. Requires a GitHub token in settings. |

## Repo Paths

All local git tools accept a `repo_path`. Use:
- **Omit** the param to target the WordPress install root (most common)
- **Relative path** from WP root: e.g. `"wp-content/themes/my-theme"`
- **Absolute path**: e.g. `"/var/www/mysite.com/public"`

The path must be an existing directory containing a git repository.

## GitHub Token

The `github_api` tool reads a personal access token from the WordPress option `agentic_github_token`. If the user hasn't added one, tell them: **Settings → [your plugin settings] → GitHub Token field**. The token needs the `repo` scope for private repos and issue/PR operations.

## GitHub API Endpoint Reference

Common endpoints (always start with `/`):

```
List repos:          GET  /user/repos
Get repo:            GET  /repos/{owner}/{repo}
List issues:         GET  /repos/{owner}/{repo}/issues?state=open
Get issue:           GET  /repos/{owner}/{repo}/issues/{number}
Create issue:        POST /repos/{owner}/{repo}/issues
  body: { "title": "...", "body": "...", "labels": [...] }
Close issue:         PATCH /repos/{owner}/{repo}/issues/{number}
  body: { "state": "closed" }
List PRs:            GET  /repos/{owner}/{repo}/pulls?state=open
Get PR:              GET  /repos/{owner}/{repo}/pulls/{number}
Create PR:           POST /repos/{owner}/{repo}/pulls
  body: { "title": "...", "body": "...", "head": "branch", "base": "main" }
List commits:        GET  /repos/{owner}/{repo}/commits
Get commit:          GET  /repos/{owner}/{repo}/commits/{sha}
List releases:       GET  /repos/{owner}/{repo}/releases
Create release:      POST /repos/{owner}/{repo}/releases
  body: { "tag_name": "v1.0.0", "name": "v1.0.0", "body": "notes", "draft": false }
List workflow runs:  GET  /repos/{owner}/{repo}/actions/runs
Search code:         GET  /search/code?q={query}+repo:{owner}/{repo}
Get file contents:   GET  /repos/{owner}/{repo}/contents/{path}
```

Use `params` for query string options (e.g. `{"state": "open", "per_page": "20"}`).

## Common Workflows

### Deploy: pull latest code to server
1. `git_status` — confirm the repo is clean or note any local changes
2. `git_pull` — pull from origin
3. Report what changed (commits pulled, files changed)

### Commit and push a change
1. `git_status` — confirm what's modified
2. `git_diff` with `staged: false` — show the user what will be staged
3. Ask the user to confirm the commit message before committing
4. `git_commit` with `message` and optionally specific `files`
5. `git_push` — **ask the user before pushing to a production or shared remote**

### Create a GitHub issue
1. `github_api` with `method: "POST"`, `endpoint: "/repos/{owner}/{repo}/issues"`, body with `title` and `body`
2. Report the returned issue URL

### Review open pull requests
1. `github_api` with `GET /repos/{owner}/{repo}/pulls`
2. For a specific PR: `GET /repos/{owner}/{repo}/pulls/{number}`
3. For the diff: `GET /repos/{owner}/{repo}/pulls/{number}/files`

### Check recent deployments
1. `git_log` with a reasonable `count` (e.g. 10)
2. Cross-reference with `github_api` commit history if needed

## Safety Rules

- **Always `git_status` before `git_commit` or `git_push`**. Never commit or push blind.
- **Confirm with the user before `git_push`** to any production or shared remote.
- **`git_push` does not support force push** — intentionally. If a force push is needed, tell the user to run it manually.
- **`git_commit` without `files` uses `git add -u`** — it stages modified tracked files but never adds untracked files. This is safe for deployment repos.
- **Credentials must be pre-configured on the server** — the tools use whatever auth git has configured (SSH key or HTTPS credential store). They cannot inject credentials.
- If `git_pull` fails with a merge conflict, report the conflicted files and tell the user to resolve them manually.
