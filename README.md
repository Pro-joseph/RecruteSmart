# RecruteSmart — Recruitment Platform

Centralize applications per job offer: unique application link per offer, configurable form per offer, and AI-assisted screening (ATS compliance + match score).

Full specification (FR): [`cahier-des-charges-plateforme-recrutement.md`](./cahier-des-charges-plateforme-recrutement.md) — functional spec (Part I) + technical spec (Part II).

## 1. What it does

1. Recruiter creates an offer, selects form fields, publishes → system generates a unique, unguessable public link.
2. Candidate opens the link (no account), fills the dynamic form, uploads CV (PDF/DOCX, 5 MB max).
3. Application is stored instantly; AI analysis runs in background: ATS score (0-100) + match score (0-100%) + summary, skills, strengths/gaps.
4. Recruiter works in the offer workspace: list sorted by match score, combined filters (%, ATS, skills, experience, location, status, date), candidate sheet, bulk select.
5. Next steps: shortlist, schedule interview (.ics invite), reject, forward candidates by email (name + CV, or secure links if >15 MB).

Scores are indicative only — they never trigger automatic rejection or status changes.

## 2. Roles

| Actor | Access | Main actions |
|---|---|---|
| Recruiter | Authenticated | Own offers only; CRUD offers, form builder, screening, interviews, forwards |
| Candidate | Anonymous | View offer via `/apply/:token`, submit form + CV + consent |
| Forward recipient | No account | Receives email with names + CVs |
| AI engine | System | Async extraction, scoring, summarization |

Status flow: `new → shortlisted → interview → offer → hired`, plus `rejected` from `new/shortlisted/interview/offer` with reactivation to `new`.

## 3. Tech stack

Versions pinned Sept 2026, to be frozen in `composer.lock` / `package-lock.json` at Lot 0.

| Layer | Tech | Target |
|---|---|---|
| Backend API | PHP 8.4 (min 8.3), Laravel 13.x | `backend/` — REST `/api/v1`, Sanctum SPA (cookie+CSRF), Horizon queues |
| Frontend UI | Angular 22.x, TypeScript 6.0.x, Reactive Forms | `frontend/` — SPA, standalone components, signals |
| DB / Queue / Cache | PostgreSQL 16+, Redis 7+ | Indexed filter columns, `analysis/mail/default` queues |
| Files | Private disk (local dev), S3-compatible (prod) | Signed URLs only, 5 min for preview, 7 days for forward links |
| AI | Pluggable `CvAnalyzer` interface | LLM JSON-schema output, temp 0-0.2, 60s timeout, 1500 max output tokens |
| Mail | Mailpit (dev), transactional provider (prod) | SPF/DKIM/DMARC, Reply-To = recruiter |
| Quality | Pint, Larastan, Pest / ESLint, Prettier, Playwright | CI blocking, 80% coverage target on Analysis/Forwarding |

Same-origin deployment: nginx serves Angular at `/`, proxies `/api` and `/sanctum` to Laravel. No CORS.

PHP extensions beyond Laravel defaults: `pdo_pgsql, redis, intl, zip, gd`.

## 4. Monorepo layout

```text
RecruteSmart/
├─ backend/                  # Laravel API
│  ├─ app/Enums/             # OfferStatus, ApplicationStatus, FieldType, AtsVerdict…
│  ├─ app/Http/Controllers/Api/      # Offer, Application, Interview, Forward
│  ├─ app/Http/Controllers/Public/   # PublicOffer, PublicApplication
│  ├─ app/Http/Requests/     # FormRequests incl. dynamic application validation
│  ├─ app/Http/Resources/    # JSON resources (snake_case, ISO-8601 UTC)
│  ├─ app/Jobs/              # AnalyzeApplicationJob, SendForwardJob
│  ├─ app/Services/Analysis/ # CvTextExtractor, AtsScorer, CvAnalyzer, ScoreCalculator
│  ├─ app/Services/Forwarding/ # ForwardService
│  ├─ config/llm.php, config/recruitment.php
│  ├─ database/              # migrations, factories, seeders
│  └─ routes/api.php
├─ frontend/                 # Angular UI
│  └─ src/app/
│     ├─ core/               # auth, guards, HTTP interceptors, API clients
│     ├─ shared/             # score badges, filter chips, pipes
│     ├─ features/auth/      # login, register, forgot-password
│     ├─ features/offers/    # list, offer form, application-form editor
│     ├─ features/applications/ # offer workspace list + filters + candidate sheet
│     ├─ features/public-apply/ # /apply/:token dynamic form
│     ├─ features/interviews/
│     └─ features/forwards/
├─ docker-compose.yml        # nginx, app, horizon, scheduler, postgres, redis, mailpit (+minio)
├─ cahier-des-charges-plateforme-recrutement.md
└─ README.md
```

Conventions: PSR-12 via Pint, `declare(strict_types=1)`, thin controllers + services, API versioned `/api/v1`, short feature branches + conventional commits.

## 5. Prerequisites

* Docker + Docker Compose
* Git
* For local (no Docker) dev: PHP 8.4 + Composer, Node LTS matching Angular CLI 22, PostgreSQL 16, Redis 7

## 6. Quickstart (Lot 0 target)

```bash
# 1. Clone and configure
git clone <repo-url> RecruteSmart
cp backend/.env.example backend/.env
# set DB_HOST, REDIS_HOST, SANCTUM_STATEFUL_DOMAINS, APP_URL, LLM_*, MAIL_*

# 2. Start infra
docker compose up -d --build
docker compose exec app php artisan migrate --seed
# health: GET http://localhost/up

# 3. Auth smoke test (Sanctum SPA)
# GET /sanctum/csrf-cookie -> POST /api/v1/auth/login -> GET /api/v1/auth/me

# 4. Frontend dev (proxy to API for same-origin)
cd frontend
npm ci
npm start
# open http://localhost:4200, proxy.conf.json -> http://localhost:8000
```

Upload limits (keep consistent): nginx `client_max_body_size 10m`, PHP `upload_max_filesize=6M`, `post_max_size=12M`.

## 7. Environment variables

See `backend/.env.example` (to be created Lot 0). Main keys from spec §11.2:

```text
APP_URL=https://recrutement.example.com
DB_CONNECTION=pgsql
REDIS_HOST=redis
QUEUE_CONNECTION=redis
SANCTUM_STATEFUL_DOMAINS=recrutement.example.com
FILESYSTEM_DISK=private
MAIL_FROM_ADDRESS=no-reply@recrutement.example.com
LLM_PROVIDER= / LLM_API_KEY= / LLM_MODEL=
LLM_TIMEOUT=60 / LLM_MAX_INPUT_CHARS=30000
LLM_DAILY_LIMIT_PER_USER=300
PROMPT_VERSION=v1
CV_MAX_SIZE_MB=5 / PHOTO_MAX_SIZE_MB=2
FORWARD_MAX_ATTACHMENT_MB=15 / FORWARD_MAX_CANDIDATES=50
RETENTION_MONTHS=12
TURNSTILE_SECRET_KEY=
SENTRY_LARAVEL_DSN=
```

## 8. API overview

Auth: `GET /sanctum/csrf-cookie`, then session cookie. Recruiter routes use `auth:sanctum` + Policies (owner-only scoping).

| Method | Route | Notes |
|---|---|---|
| POST | `/api/v1/auth/register, /auth/login, /auth/logout` | Session |
| GET,PATCH | `/api/v1/auth/me` | Profile |
| GET,POST | `/api/v1/offers` | Filter `?status=` |
| POST | `/api/v1/offers/{offer}/publish, /close, /duplicate, /regenerate-link, /reanalyze` | Link invalidation on regenerate |
| GET,PUT | `/api/v1/offers/{offer}/form-fields` | Builder config |
| GET | `/api/v1/offers/{offer}/applications?min_score=70&ats=compliant&skills[]=laravel&exp_min=2&sort=-match_score` | Paginated 25 (max 100), whitelisted sorts |
| POST | `/api/v1/forwards` | `{application_ids[]}` or `{select_all:true, filters:{}}`, max 50 candidates / 10 recipients, `202` async |
| GET | `/api/v1/public/offers/{token}` | Public, rate-limited |
| POST | `/api/v1/public/offers/{token}/applications` | `multipart/form-data`, rate-limited + CAPTCHA |

Validation errors: Laravel format (`message` + `errors`), `422` incl. duplicate email per offer, `429` on abuse.

## 9. AI pipeline (async)

`POST public application (201)` → `AnalyzeApplicationJob` on `analysis` queue → extract text (pdfparser/PHPWord) → `AtsScorer` (6 checks, §5.2) → if <200 chars: no LLM call, `match_score=null`, `unreadable_cv` → else sanitize (mask emails/phones/URLs, `[CANDIDAT]`, strip sensitive lines, truncate) → `CvAnalyzer::analyze()` → JSON-schema validate (retry once) → `ScoreCalculator` (weighted avg, renormalized) → persist `completed` + `application_skill` links.

* ATS: 75+=compliant, 50-74=improvable, <50=non_compliant.
* Match weights default: `required_skills 35, preferred 10, experience 30, education 15, languages 10`.
* Robustness: `tries=3, backoff 30/120/600, timeout 180`, idempotent `updateOrCreate`, input-hash cache, token/usage logging, quotas.
* Safety: CV framed as data (prompt-injection guard), sensitive fields never sent, no PII in logs, DPA + region choice required.

## 10. Key business rules

* Only `published` + before `deadline_at` accepts applications; ` RG-02`.
* One email per offer (case-insensitive); locked fields `full_name, email, cv` always required.
* After first application, used form fields can only be hidden, not deleted.
* Criteria change → `criteria_version++`, existing analyses flagged `is_stale`.
* Retention 12 months after offer close (configurable); delete = hard delete files+rows, forward history shows “Deleted candidate”.
* Forward contains only name + CV by default (`include_analysis` opt-in off).

## 11. Testing & CI

* Backend: Pest — `ScoreCalculator`, ATS checks, sanitizer, filters; Feature with `Storage::fake, Queue::fake, Mail::fake, Http::fake` for isolation, forwards, purge.
* Analysis quality: 20-30 sample CVs + `FakeCvAnalyzer`, schema + score stability.
* Frontend: CLI runner (dynamic form, filters), Playwright E2E (create offer → apply → filter → forward).
* `composer audit`, `npm audit`, ZAP baseline, k6 optional (5000-application list <500ms p95, analysis <60s p95).
* GitHub Actions: `backend` / `frontend` on PR, `e2e` on main/nightly, `images` + manual `deploy` (migrate + `horizon:terminate`).

## 12. Roadmap (indicative, 1 full-time dev ~14 wks)

| Lot | Scope |
|---|---|
| 0 | Repo, Docker, CI, Laravel+Angular skeletons, auth — 1 wk |
| 1 | Offers, field catalog, form editor, public link — 2 wks |
| 2 | Public apply page, file storage, simple list — 2 wks |
| 3 | AI analysis: extraction, ATS, match, summary — 3 wks |
| 4 | Candidate sheet, statuses, filters, sort, search — 2 wks |
| 5 | Interviews, rejections, email forwards, notifications — 2 wks |
| 6 | Compliance (purge/delete/audit), hardening, load tests, deploy — 2 wks |

MVP = priority **M** items; **S/C** if time permits.

## 13. Security & compliance notes

Sanctum `HttpOnly/SameSite=Lax/Secure`, Policies + owner scoping, random 24-char `public_token`, content-sniffed uploads (UUID names, private storage, image re-encode), 5-min signed downloads, 5/min + 30/h IP rate-limit + Turnstile/hCaptcha + honeypot, HTTPS/HSTS/CSP, `.env` out of repo, encrypted backups (RPO 24h/RTO 4h), versioned consent + daily `applications:purge-expired` + `audit_logs`.

## 14. Contributing

* Branch per feature, PR review required, CI must pass.
* Backend: `composer lint` (Pint), `composer analyse` (Larastan), `php artisan test`.
* Frontend: `npm run lint`, `npm test`, `npm run build`.
