# Contributing

## Development Flow

1. Create a branch.
2. Make focused changes.
3. Run validation:

```bash
php artisan test
npm run typecheck
npm run build
```

4. Update docs and changelog when user-facing behavior changes.

## Code Style

- Follow existing Laravel controller/service patterns.
- Keep React state local unless shared server state belongs in TanStack Query.
- Prefer small services for provider integrations.
- Keep data labels consistent across analysis, bets and history.

## Pull Requests

PRs should include:

- what changed
- why it changed
- validation performed
- screenshots for UI changes
