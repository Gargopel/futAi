# Versioning

FutIA Local uses Semantic Versioning:

```text
MAJOR.MINOR.PATCH
```

- `MAJOR`: breaking database, API or workflow changes.
- `MINOR`: new features that preserve existing workflows.
- `PATCH`: fixes, documentation updates and safe refinements.

## Release Checklist

Before publishing a version:

1. Update `VERSION`.
2. Update `CHANGELOG.md`.
3. Add or update `docs/releases/vX.Y.Z.md`.
4. Run:

```bash
php artisan test
npm run typecheck
npm run build
```

5. Create a Git tag:

```bash
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z
```
