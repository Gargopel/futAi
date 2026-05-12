# Security

## Secrets

Never commit:

- `.env`
- API keys
- local SQLite files
- production logs

The repository ignores:

```text
.env
.env.*
database/*.sqlite
storage/logs/*.log
```

## Local API Keys

API keys configured through the app UI are saved encrypted in `api_integrations`.

## Current Scope

This release is local-first and single-user. It does not include authentication or authorization screens.

If the app is deployed to a public server, add:

- authentication
- CSRF/session hardening for browser workflows
- user-scoped data ownership
- production secret management
- HTTPS termination

## Responsible Use

The app provides statistical analysis and personal bankroll tracking. It does not guarantee betting outcomes.
