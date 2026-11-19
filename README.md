# CRM Assistant: Backend (Laravel)

REST API for a simplified CRM with three AI features: customer summaries, a sales report summary, and a deal Q&A assistant. The AI never produces numbers: figures come from PostgreSQL, and the model only writes text around them.

Frontend repository: `<link to your frontend repo>`
Technical documentation (architecture, ERD, security, testing): [docs/TECHNICAL.md](docs/TECHNICAL.md)

## Requirements
- PHP 8.2+ with the `pdo_pgsql` extension enabled
- Composer
- PostgreSQL 14+
- A Google Gemini API key (free tier, from aistudio.google.com)

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the databases (the second one is used only by the automated tests):
```sql
CREATE DATABASE crm_ai;
CREATE DATABASE crm_ai_test;
```

Edit `.env`:

| Variable | Purpose | Example |
|---|---|---|
| `DB_CONNECTION` | Must be `pgsql` | `pgsql` |
| `DB_HOST`, `DB_PORT` | PostgreSQL server | `127.0.0.1`, `5432` |
| `DB_DATABASE` | Application database | `crm_ai` |
| `DB_USERNAME`, `DB_PASSWORD` | Database credentials | |
| `FRONTEND_URL` | Allowed CORS origin | `http://localhost:3000` |
| `GEMINI_API_KEY` | AI provider key (never commit it) | |
| `GEMINI_MODEL` | Gemini model ID | `gemini-3.5-flash` |

Create the tables and seed demo data:
```bash
php artisan migrate --seed
```
The seeder creates a demo user (`demo@example.com` / `password`), 12 customers, and 1 to 4 deals per customer.

## Run
```bash
php artisan serve
```
The API is available at `http://localhost:8000/api`. Start the frontend as described in its README.

## AI provider configuration
- The provider is Google Gemini, called through the REST API from `App\Services\Ai\GeminiClient`.
- Set `GEMINI_API_KEY` and `GEMINI_MODEL` in `.env`. Model IDs change over time: pick a current Flash model in Google AI Studio.
- The free tier can return `429` (rate limited) or `503` (overloaded). The client retries once and then returns a clean error message to the user.
- To use another provider, implement `App\Services\Ai\LlmClient` and change the binding in `AppServiceProvider`. No other code changes.

## Tests
```bash
php artisan test
```
Tests use the `crm_ai_test` database and a fake AI provider. No test calls the real Gemini API.

## API overview
All routes except `POST /api/login` need the header `Authorization: Bearer <token>`.

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/login` | Returns a token (5 requests per minute) |
| POST | `/api/logout` | Revokes the current token |
| GET | `/api/me` | Current user |
| GET, POST | `/api/customers` | List (`?search=&page=`) and create |
| GET, PUT, DELETE | `/api/customers/{id}` | Show (with deals), update, delete |
| GET, POST | `/api/deals` | List (`?stage=&page=`) and create |
| GET, PUT, DELETE | `/api/deals/{id}` | Show (with customer), update, delete |
| POST | `/api/customers/{id}/summary` | AI customer summary |
| GET | `/api/reports/sales` | Sales statistics (database only) |
| POST | `/api/reports/sales/summary` | Statistics plus AI narrative |
| POST | `/api/assistant/deals/ask` | Deal Q&A, body: `{"question": "..."}` |

AI endpoints are limited to 10 requests per minute per user.