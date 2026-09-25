# LLM provider choice (Lot 0 — H-07, decided in Lot 3)

Status: **decided — Groq**. Architecture stays provider-agnostic
(`App\Services\Analysis\CvAnalyzer` interface + `FakeCvAnalyzer` for tests).

## Decision criteria (spec Part I §8.1, Part II §6.7)

1. Cost per analysis (input + max 1500 output tokens, temp 0-0.2)
2. Latency p95 < 60 s end-to-end (queue + HTTP 60 s timeout)
3. Confidentiality: DPA, no-training-on-data option, hosting region choice
4. Structured JSON output support (schema-constrained or tool call)

## Decision

| Field | Value |
|---|---|
| Provider | **Groq** (OpenAI-compatible `POST {base_url}/chat/completions`) |
| Model (default, economical) | `llama-3.3-70b-versatile` |
| Model (powerful, optional) | `openai/gpt-oss-120b` (set `LLM_STRUCTURED_OUTPUT=true`) |
| Region | Groq Cloud, US (AWS us-east-1 / GCP us-central1) — verify residency in console |
| DPA signed | no (sign via Groq Trust Center before prod) |
| `LLM_MODEL` / `LLM_API_KEY` set in prod secrets | no (empty in `.env.example`, secret injected at deploy) |

### Rationale

- **Latency**: Groq LPU inference is well within the p95 < 60 s budget
  (typical 70B completion ≈ 1–5 s for ≤ 1500 tokens).
- **Cost**: among the cheapest tokens on the market; fits
  `LLM_DAILY_LIMIT_PER_USER=300` + monthly cap.
- **Structured output**: two modes, selected by `LLM_STRUCTURED_OUTPUT`
  - `false` (default): `response_format: {type: "json_object"}` works on all
    models; the app validates the payload against
    `app/Services/Analysis/schemas/cv_analysis.schema.json` with `opis/json-schema`
    and retries once with the validation errors (spec §6.5 / §6.7).
  - `true`: `response_format: {type: "json_schema", strict: true}` (constrained
    decoding) — only `openai/gpt-oss-*` models support strict mode today.
- **Configured via `config/llm.php`** (`LLM_PROVIDER`, `LLM_API_KEY`, `LLM_BASE_URL`,
  `LLM_MODEL`, `LLM_STRUCTURED_OUTPUT`, temp, token caps, quotas).

### To do before production

- [ ] Put `LLM_API_KEY` in prod secrets (never in repo)
- [ ] Sign DPA / confirm no-training-on-data + residency in Groq console
- [ ] Tune `LLM_RATE_LIMIT_PER_MINUTE` to the plan's actual RPM/TPM quota

Quotas already wired: `LLM_DAILY_LIMIT_PER_USER=300`, `LLM_RATE_LIMIT_PER_MINUTE=20`,
monthly global cap + alert (see `config/llm.php`, `config/recruitment.php`).
