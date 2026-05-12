# Architecture

FutIA Local is a local-first football analysis app.

## Stack

- Laravel 11 API
- React 18 SPA
- Vite and TypeScript
- Tailwind CSS
- SQLite local database
- TanStack Query for client-side API state

## Main Domains

### Competition Data

- `leagues`
- `teams`
- `football_matches`
- `team_match_stats`

### Analysis

- `analysis_rules`
- `analysis_results`
- analysis engine services in `app/Services/FootballAnalysis`

The analysis engine computes:

- recent form
- home/away performance
- goals averages
- market suggestions
- confidence
- risk
- sample warnings

### Imports and Integrations

- CSV/JSON match import
- API-Football sync
- Football-Data.org sync
- encrypted API key storage in `api_integrations`

### Bankroll

- `bookmakers`
- `bankrolls`
- `bets`

Bets can be linked to system matches so future analysis can compare suggested markets with real betting outcomes.

## Local-First Design

The app is designed to run on the user's machine. Provider credentials and the SQLite database should remain local and must not be committed.

## Frontend Routes

- `/`
- `/leagues`
- `/teams`
- `/matches`
- `/matches/:id`
- `/analysis`
- `/analysis-history`
- `/analysis-rules`
- `/backtesting`
- `/imports`
- `/integrations`
- `/bankroll`
