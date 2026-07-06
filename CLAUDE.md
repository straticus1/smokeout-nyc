# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

SmokeoutNYC — a cannabis industry platform for NYC. Core product: tracking smoke shop closures and "Operation Smokeout" enforcement on an interactive map, plus news, user accounts, donations, an AI dispensary-risk assistant, and a large multiplayer cannabis-growing game (genetics/crossbreeding, weather effects, market dynamics, P2P trading, guilds). See `README.md` for the full feature catalog and API endpoint list.

## Architecture — two backends plus a legacy PHP frontend

This repo contains multiple runtimes that coexist; know which one you are editing:

1. **PHP API (`api/`)** — the primary feature surface. One flat PHP endpoint file per domain (`game.php`, `genetics.php`, `market.php`, `trading.php`, `ai_risk_assistant.php`, `smart_notifications.php`, `auth.php`, etc.) with shared code in `api/helpers/`, `api/models/`, `api/services/` and PDO database access via `api/config.php`. Heavier gaming/AI engines live in `server/src/{models,services}` (e.g. `EnhancedGamingSystem.php`, `EnhancedAIRiskService.php`). A PHP WebSocket server (`server/websocket_server.php`) powers real-time multiplayer.
2. **Node/TypeScript API (`src/server/`)** — Express + TypeScript entry at `src/server/index.ts` with `routes/` (auth, stores, news, admin, donations, messages, products, users), Passport OAuth (Google/Facebook), JWT auth middleware, Socket.io handlers (`src/server/socket/`), Prisma ORM (`prisma/`), Redis, and a Puppeteer/Cheerio scraper (`src/server/scripts/scrapeOperationSmokeout.ts`). `src/realtime/` is a small standalone Node realtime server.
3. **React client (`client/`)** — Create React App (react-scripts) + TypeScript + Tailwind. Maps via Leaflet/MapLibre/Google Maps; 3D via three.js/@react-three; react-query + react-router. Consumes both APIs.
4. **Legacy server-rendered PHP pages at the repo root** — `index.php`, `login.php`, `signup.php`, `news.php`, `search.php`, `add.php`.

Other pieces: `database/` holds the MySQL schema as per-feature `.sql` files (`schema.sql`, `gaming_schema.sql`, `phase1_enhancements_schema.sql`, ...) plus a SQLite dev db; `terraform/` + `ansible/` + `infrastructure/` define the AWS deployment; `mobile/` and `docs/` exist; `composer.json` covers PHP database scripts under `scripts/`.

Data stores: MySQL (main + gaming schema), Redis (cache/sessions/real-time), Prisma-managed schema for the Node server.

## Commands

Root `package.json` (Node/TS server + client orchestration):
```bash
npm run dev           # concurrently: nodemon TS server + CRA client
npm run server:dev    # Node server only (ts-node src/server/index.ts)
npm run client:dev    # React client only
npm run build         # build:server (tsc -p tsconfig.server.json) + build:client
npm start             # run compiled server (dist/server.js)
npm test              # jest
npx jest <path>       # run a single Jest test file
npm run lint          # eslint . --ext .ts,.tsx
npm run db:migrate    # prisma migrate dev
npm run db:generate   # prisma generate
npm run db:seed       # ts-node src/server/scripts/seed.ts
```

Client (from `client/`): `npm start`, `npm run build`, `npm test` (react-scripts).

Repo automation scripts (run from repo root):
```bash
./setup.sh   # full environment setup
./dev.sh     # start all development servers
./test.sh    # validate installation/setup (env files, services)
```

## Notes

- README (34 KB) is aspirational/marketing-heavy in places (e.g. it claims Vite, but `client/` uses react-scripts). Trust `package.json`/`composer.json` and the code over README prose.
- Environment config: `.env` at root (see `env.example`), `client/.env.example`, `terraform/terraform.tfvars.example`. Gaming features require the gaming SQL schemas loaded, Redis, and the PHP WebSocket server running.
