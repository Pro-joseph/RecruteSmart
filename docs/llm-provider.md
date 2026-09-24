# LLM provider choice (Lot 0 — H-07)

Status: **TBD before Lot 3**. Architecture stays provider-agnostic
(`App\Services\Analysis\CvAnalyzer` interface + `FakeCvAnalyzer` for tests).

## Decision criteria (spec Part I §8.1, Part II §6.7)

1. Cost per analysis (input + max 1500 output tokens, temp 0-0.2)
2. Latency p95 < 60 s end-to-end (queue + HTTP 60 s timeout)
3. Confidentiality: DPA, no-training-on-data option, hosting region choice
4. Structured JSON output support (schema-constrained or tool call)

## Shortlist to evaluate in Lot 0/1

- Option A: incumbent general LLM with structured output (e.g. OpenAI / Anthropic / Mistral)
- Option B: EU-hosted equivalent (data residency)

## Record the decision here before Lot 3

| Field | Value |
|---|---|
| Provider | TBD |
| Model (default, economical) | TBD |
| Model (powerful, optional) | TBD |
| Region | TBD |
| DPA signed | no |
| `LLM_MODEL` / `LLM_API_KEY` set in prod secrets | no |

Quotas already wired: `LLM_DAILY_LIMIT_PER_USER=300`, monthly global cap + alert
(see `backend/config/recruitment.php`).
