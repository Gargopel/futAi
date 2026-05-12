# Integrations

FutIA Local currently supports API-Football and Football-Data.org.

## API-Football

Default base URL:

```env
API_FOOTBALL_BASE_URL=https://v3.football.api-sports.io
```

Authentication header:

```text
x-apisports-key
```

The app can store the API key through `/integrations`.

Default Brazilian Serie A configuration:

```text
league=71
season=2024
timezone=America/Sao_Paulo
```

Free plans may restrict seasons and filters.

## Football-Data.org

Default base URL:

```env
FOOTBALL_DATA_ORG_BASE_URL=https://api.football-data.org/v4
```

Authentication header:

```text
X-Auth-Token
```

Default Brazilian Serie A configuration:

```text
competition=BSA
season=2026
```

## Key Storage

Keys entered in the UI are stored encrypted in the local SQLite database using Laravel encrypted casts.

Do not commit:

- `.env`
- local SQLite databases
- logs
