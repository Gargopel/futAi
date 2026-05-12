# API Reference

All endpoints are served under `/api`.

## Dashboard

- `GET /dashboard`

## Leagues

- `GET /leagues`
- `POST /leagues`

## Teams

- `GET /teams`
- `POST /teams`

## Matches

- `GET /matches`
- `POST /matches`
- `GET /matches/{match}`
- `PATCH /matches/{match}`
- `GET /matches/{match}/analysis`

Supported match filters:

```text
month=YYYY-MM
league_id=1
team_id=1
sort=starts_at
bettable=1
```

## Analysis

- `GET /analysis-results`
- `GET /analysis-performance`
- `GET /analysis-rules`
- `PATCH /analysis-rules/{key}`

## Backtesting

- `POST /backtests`

## Imports

- `POST /imports/matches`

Modes:

- `preview`
- `commit`

Formats:

- `csv`
- `json`

## Integrations

- `GET /integrations`
- `PATCH /integrations/{provider}`
- `POST /integrations/{provider}/sync`

Providers:

- `api-football`
- `football-data-org`
- `csv-source`

## Bankroll and Bets

- `GET /bookmakers`
- `GET /bankrolls`
- `POST /bankrolls`
- `GET /bets`
- `POST /bets`
- `PATCH /bets/{bet}`

Bet status values:

- `pending`
- `won`
- `lost`
- `void`
- `cashed_out`
