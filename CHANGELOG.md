# Changelog

All notable changes to FutIA Local are documented here.

This project follows [Semantic Versioning](docs/VERSIONING.md).

## [0.1.0] - 2026-05-12

### Added

- Local Laravel 11 API with React 18, Vite, TypeScript and Tailwind CSS.
- SQLite-first data model for leagues, teams, matches, match stats, analysis rules and analysis results.
- Dashboard with league, team, match and analysis counters.
- Match management with scheduled, finished and cancelled statuses.
- Statistical analysis engine with confidence, risk, suggested market, reasons and team-form metrics.
- Suggestions for:
  - Mais de 1.5 gols
  - Mais de 2.5 gols
  - Ambas marcam
  - Dupla chance mandante
  - Dupla chance visitante
  - Vitoria mandante
  - Vitoria visitante
- Analysis history with market, risk and status filters.
- Backtesting endpoint and UI for historical validation without persisting new analysis records.
- Configurable analysis rules.
- CSV/JSON match import with preview and commit modes.
- API-Football integration for fixture import.
- Football-Data.org integration for Campeonato Brasileiro Serie A (`BSA`) including 2026 data.
- Integrations page for storing API keys locally and running provider syncs from the UI.
- Bankroll and bet journal module with bookmakers, bankroll balances, stakes, odds, status, profit and ROI.
- Bet market/selection pickers aligned with the analysis engine outputs.
- Demo-data cleanup command.
- GitHub-ready documentation, screenshots and CI workflow.

### Security

- API keys are ignored in `.env`.
- Integration keys stored through the UI are encrypted in the local database.
- Local SQLite databases are ignored by Git.

### Known Limitations

- The first release is single-user/local-first and does not include authentication.
- Some API provider access depends on the user's paid/free plan.
- Bet settlement is manual in this version.
