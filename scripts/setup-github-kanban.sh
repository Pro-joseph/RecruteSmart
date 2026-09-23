#!/usr/bin/env bash
# RecruteSmart — GitHub Kanban setup for Linux / GitHub Actions
# Idempotent: safe to re-run.
set -euo pipefail

OWNER="Pro-joseph"
REPO="Pro-joseph/RecruteSmart"
PROJECT_TITLE="RecruteSmart — Lots 0-6"

echo "== 0. Auth check =="
gh auth status
gh repo view "$REPO" --json name,owner,url -q '{name, url}'

echo ""
echo "== 1. Labels =="
create_label() { gh label create "$1" --repo "$REPO" --color "$2" --description "$3" --force; }
create_label "prio:M" "d73a4a" "Must - MVP indispensable"
create_label "prio:S" "fbca04" "Should - important"
create_label "prio:C" "9e9e9e" "Could - desirable"
create_label "area:backend" "1d76db" "Laravel API"
create_label "area:frontend" "5319e7" "Angular UI"
create_label "area:ai" "0e8a16" "AI pipeline"
create_label "area:infra" "0052cc" "Docker / CI / deploy"
create_label "area:qa" "e99695" "Tests / security / perf"
create_label "lot:0" "bfd4f2" "Lot 0 Socle"
create_label "lot:1" "bfd4f2" "Lot 1 Offers + forms"
create_label "lot:2" "bfd4f2" "Lot 2 Public apply + list"
create_label "lot:3" "bfd4f2" "Lot 3 AI analysis"
create_label "lot:4" "bfd4f2" "Lot 4 Screening + filters"
create_label "lot:5" "bfd4f2" "Lot 5 Interviews + forwards"
create_label "lot:6" "bfd4f2" "Lot 6 Compliance + hardening"
create_label "type:feature" "a2eeef" "Feature"
create_label "type:chore" "fef2c0" "Chore"

echo ""
echo "== 2. Milestones =="
# title|description
MILESTONES=(
"Lot 0 — Socle|Git, Docker, CI, Laravel+Angular skeletons, auth. ~1 wk. EF-101/102."
"Lot 1 — Offers & forms|Offers CRUD, criteria, form catalog/editor, public link. ~2 wks."
"Lot 2 — Public apply & list|Public page, uploads, dashboard list. ~2 wks."
"Lot 3 — AI analysis|Extraction, ATS, match, summary. ~3 wks."
"Lot 4 — Screening & filters|Candidate sheet, statuses, filters/sort/search. ~2 wks."
"Lot 5 — Interviews & forwards|Interviews, rejections, email forwards, notifications. ~2 wks."
"Lot 6 — Compliance & hardening|Purge/delete/audit, hardening, load tests, deploy. ~2 wks."
)
EXISTING_MS="$(gh api "repos/$REPO/milestones?state=all" --paginate -q '.[].title' || true)"
for entry in "${MILESTONES[@]}"; do
  title="${entry%%|*}"
  desc="${entry#*|}"
  if printf '%s' "$EXISTING_MS" | grep -Fxq "$title"; then
    echo "milestone exists: $title"
  else
    gh api "repos/$REPO/milestones" -f "title=$title" -f "description=$desc" -f state=open --jq '.title'
    echo "created: $title"
  fi
done

echo ""
echo "== 3. Issues =="
EXISTING_TITLES="$(gh issue list --repo "$REPO" --limit 200 --json title -q '.[].title' || true)"

create_issue_if_missing() {
  local title="$1" milestone="$2" labels="$3" body="$4"
  if printf '%s' "$EXISTING_TITLES" | grep -Fxq "$title"; then
    echo "issue exists: $title"
    return 0
  fi
  # labels: convert comma list to repeated -l flags via IFS
  IFS=',' read -ra LABS <<< "$labels"
  args=(issue create --repo "$REPO" --title "$title" --body "$body" --milestone "$milestone")
  for l in "${LABS[@]}"; do args+=(--label "$l"); done
  url="$(gh "${args[@]}")"
  echo "created: $title -> $url"
  EXISTING_TITLES="${EXISTING_TITLES}"$'\n'"${title}"
}

# ---- LOT 0 ----
create_issue_if_missing "[lot0] Monorepo backend/ + frontend/ + .env.example" "Lot 0 — Socle" "lot:0,prio:M,area:infra,type:chore" "Spec: Part II §3, §15.1-2.
Scope: create backend/ (Laravel 13) + frontend/ (Angular 22) in monorepo, .env.example with all §11.2 keys.
Accept: folders + env example present, README quickstart valid."
create_issue_if_missing "[lot0] docker-compose (nginx,app,horizon,scheduler,postgres,redis,mailpit) + /up" "Lot 0 — Socle" "lot:0,prio:M,area:infra,type:feature" "Spec: Part II §11.1.
Scope: compose services, nginx routes / -> Angular, /api + /sanctum -> Laravel, health /up.
Accept: docker compose up responds on /up; mailpit UI reachable."
create_issue_if_missing "[lot0] Laravel 13 + Sanctum SPA + Angular proxy auth smoke" "Lot 0 — Socle" "lot:0,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-101,102; D1/D2; §15.4.
Scope: Sanctum statefulApi + SANCTUM_STATEFUL_DOMAINS, proxy.conf.json, register/login/me flow.
Accept: Angular can register, login, GET /api/v1/auth/me."
create_issue_if_missing "[lot0] CI backend (Pint/Larastan/Pest+audit) + frontend (ESLint/build/audit)" "Lot 0 — Socle" "lot:0,prio:M,area:infra,type:chore" "Spec: Part II §11.3.
Scope: GitHub Actions backend + frontend jobs, blocking on lint/test fail.
Accept: PR runs both jobs green on skeleton."
create_issue_if_missing "[lot0] Freeze versions + choose LLM provider + 20-30 sample CVs" "Lot 0 — Socle" "lot:0,prio:M,area:ai,type:chore" "Spec: H-07; §6, §12, §15.6.
Scope: lock composer/package locks (PHP 8.4, PG16, Redis7), pick provider/region/DPA, add anonymized CV fixtures.
Accept: provider + model + test set documented."

# ---- LOT 1 ----
create_issue_if_missing "[lot1] Offer CRUD + statuses + close/duplicate" "Lot 1 — Offers & forms" "lot:1,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-201,204,209,210; RG-10.
API: GET/POST /offers, GET/PUT/DELETE /offers/{offer}, POST /close, /duplicate.
Accept: draft->published->closed->archived; closed blocks new applications; recette #11."
create_issue_if_missing "[lot1] Evaluation criteria + scoring weights + knockout + criteria_version" "Lot 1 — Offers & forms" "lot:1,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-202,211,607; RG-08,09; §5.3.
Scope: required/preferred skills, min exp, education, languages, location, knockout list, weights json, version bump marks analyses stale.
Accept: editing criteria sets is_stale=true."
create_issue_if_missing "[lot1] Field catalog + per-offer form config" "Lot 1 — Offers & forms" "lot:1,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-301-304,306; §4.4 catalog; RG-12.
API: GET /form-field-catalog, GET/PUT /offers/{offer}/form-fields.
Accept: full_name/email/cv locked required; photo/age/birth_date flagged sensitive optional, excluded from AI."
create_issue_if_missing "[lot1] Publish -> public_token + copy/QR/regenerate" "Lot 1 — Offers & forms" "lot:1,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-205-208; RG-11.
API: POST /offers/{offer}/publish, /regenerate-link.
Accept: Str::random(24) unique token; regenerate invalidates old, keeps applications."
create_issue_if_missing "[lot1] Form preview + salary/posts/deadline + hide-not-delete rule" "Lot 1 — Offers & forms" "lot:1,prio:S,area:backend,area:frontend,type:feature" "Spec: EF-203,305,307.
Accept: preview matches candidate view; used field cannot be deleted, only hidden (is_hidden)."

# ---- LOT 2 ----
create_issue_if_missing "[lot2] Public offer page + Angular /apply/:token (mobile-first)" "Lot 2 — Public apply & list" "lot:2,prio:M,area:frontend,type:feature" "Spec: EF-401,402,410; ENF-06/07/08.
Scope: dynamic FormGroup from offer config, client validation mirrors server, <2s on 4G, WCAG AA.
Accept: recette #2 — anonymous sees only chosen fields, CV required."
create_issue_if_missing "[lot2] POST application multipart + dynamic validation + private storage" "Lot 2 — Public apply & list" "lot:2,prio:M,area:backend,type:feature" "Spec: EF-402,403,408; §5.4; RG-02/04.
API: POST /public/offers/{token}/applications (full_name,email,answers[],files[cv],consent,captcha).
Accept: recette #3/#4 — .exe/no-CV rejected with clear FR message; valid -> 201 + pending analysis in <5s in correct offer only."
create_issue_if_missing "[lot2] Consent + confirmation + candidate email + no-double-email" "Lot 2 — Public apply & list" "lot:2,prio:M,area:backend,type:feature" "Spec: EF-404-407; RG-03,14.
Scope: timestamped consent+version, confirm screen, ApplicationReceivedMail, UNIQUE(offer_id,email).
Accept: second submit same email -> 422 dedicated message."
create_issue_if_missing "[lot2] Anti-spam rate-limit + CAPTCHA + honeypot" "Lot 2 — Public apply & list" "lot:2,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-409; §8 Anti-abus.
Scope: 5/min + 30/h per IP, Turnstile/hCaptcha, honeypot, per-offer quota.
Accept: spam burst throttled 429 without breaking legit submit."
create_issue_if_missing "[lot2] Recruiter dashboard + offer workspace list (25pp, live analysis badge)" "Lot 2 — Public apply & list" "lot:2,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-501-506; ENF-01.
API: GET /offers/{offer}/applications paginated, with analysis.
Accept: columns name/match/ATS/exp/city/skills/status/date; default sort -match_score; polling 5s while pending/processing."

# ---- LOT 3 ----
create_issue_if_missing "[lot3] CvTextExtractor PDF/DOCX + unreadable path (no LLM call)" "Lot 3 — AI analysis" "lot:3,prio:M,area:backend,area:ai,type:feature" "Spec: EF-602,609; §6.2 steps 2-3; recette #12.
Scope: pdfparser + PHPWord, <200 chars -> ATS-01 fail, match_score=null, message texte non extractible.
Accept: scanned CV -> non_compliant ATS, no provider call, retry button."
create_issue_if_missing "[lot3] AtsScorer 6 checks + verdict + advice" "Lot 3 — AI analysis" "lot:3,prio:M,area:backend,area:ai,type:feature" "Spec: EF-603; Part I §5.2 ATS-01..06.
Scope: one class per check (AtsCheck interface), 25+20+10+10+10+25, verdict 75/50.
Accept: Pest covers each check; failed checks show message+advice on sheet."
create_issue_if_missing "[lot3] CvTextSanitizer (PII masking, sensitive strip, truncate)" "Lot 3 — AI analysis" "lot:3,prio:M,area:backend,area:ai,type:feature" "Spec: EF-610; §6.2 step 5; RG-07.
Scope: mask emails/phones/URLs, replace name->[CANDIDAT], drop age/birth/family/photo lines, LLM_MAX_INPUT_CHARS=30000.
Accept: test proves no sensitive/direct contact sent to LLM (recette #5)."
create_issue_if_missing "[lot3] CvAnalyzer interface + LlmCvAnalyzer prompt v1 + JSON schema" "Lot 3 — AI analysis" "lot:3,prio:M,area:backend,area:ai,type:feature" "Spec: §6.3/6.4; schema app/Services/Analysis/schemas/cv_analysis.schema.json.
Scope: temp 0-0.2, 1500 max tokens, 60s timeout, structured output, retry once on invalid with error feedback.
Accept: sample CVs validate against schema; FakeCvAnalyzer for tests."
create_issue_if_missing "[lot3] ScoreCalculator pure + renormalization + knockout flags" "Lot 3 — AI analysis" "lot:3,prio:M,area:backend,area:ai,type:feature" "Spec: Part I §5.3; EF-604.
Scope: weighted avg renormalized on filled criteria only, knockout_threshold=40 default.
Accept: example 90/50/70/100/80 -> 81%; unit-tested; never reads final % from LLM."
create_issue_if_missing "[lot3] AnalyzeApplicationJob retries/cache/idempotence/quotas + reanalyze" "Lot 3 — AI analysis" "lot:3,prio:M,area:backend,area:ai,type:feature" "Spec: EF-601,608; §6.7; API POST /applications/{id}/reanalyze + /offers/{id}/reanalyze.
Scope: tries=3 backoff 30/120/600 timeout 180, RateLimited+WithoutOverlapping+ShouldBeUnique, input_hash cache, token logging, LLM_DAILY_LIMIT_PER_USER.
Accept: provider outage -> queued+replayable; recalc in one click when stale."
create_issue_if_missing "[lot3] Prompt-injection + bias guards + anomalies badge" "Lot 3 — AI analysis" "lot:3,prio:S,area:backend,area:ai,type:feature" "Spec: §6.8.
Scope: CV framed as data, ignore-instructions rule, schema lock, suspect-pattern detect, anomalies[] + badge.
Accept: malicious CV fixture flagged, score not blindly 100."

# ---- LOT 4 ----
create_issue_if_missing "[lot4] Candidate sheet + CV preview/signed URL + scores breakdown" "Lot 4 — Screening & filters" "lot:4,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-701,702.
API: GET /applications/{id}, GET /files/{key} (5-min signed).
Accept: answers+files, ATS/match per-criterion evidence, summary/strengths/gaps."
create_issue_if_missing "[lot4] Status change single + bulk + auto on interview" "Lot 4 — Screening & filters" "lot:4,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-703,901; §5.1; API PATCH /status + POST /bulk-status.
Accept: manual anytime; interview plan -> interview; forward never changes status; rejected->new reactivation."
create_issue_if_missing "[lot4] Notes 1-5 + history timeline" "Lot 4 — Screening & filters" "lot:4,prio:S,area:backend,area:frontend,type:feature" "Spec: EF-704,705; API POST /notes, GET /events.
Accept: application_events logs status/note/interview/forward/analysis."
create_issue_if_missing "[lot4] Filters score/ATS/skills/exp/location" "Lot 4 — Screening & filters" "lot:4,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-801-805; §5.3 params.
Scope: min/max_score, ats verdict+range, skills[] any/all via application_skill, exp range, city_normalized+country.
Accept: recette #6 combined filter returns exact expected set on fixtures."
create_issue_if_missing "[lot4] Filters status/date/combine/URL-sync/saved views" "Lot 4 — Screening & filters" "lot:4,prio:S,area:backend,area:frontend,type:feature" "Spec: EF-806-809.
Scope: AND logic, result count, reset, router query sync, saved combos (C).
Accept: shareable URL restores filters+sort; back button works."
create_issue_if_missing "[lot4] Search q + /skills with counts + /stats aggregates" "Lot 4 — Screening & filters" "lot:4,prio:S,area:backend,area:frontend,type:feature" "Spec: EF-507,508; API GET /offers/{id}/skills + /stats.
Scope: q on name/email/skills (ilike), skills from offer candidates only, SQL aggregates cached 60s.
Accept: p95 <500ms on 5000 apps fixture."

# ---- LOT 5 ----
create_issue_if_missing "[lot5] Interview plan/track/report/decision + .ics invite" "Lot 5 — Interviews & forwards" "lot:5,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-902-904; API POST /applications/{id}/interviews, PATCH /interviews/{id}.
Accept: recette #9 — plan -> status interview + invite with .ics; done/canceled/rescheduled + notes/decision."
create_issue_if_missing "[lot5] Reject with reason + optional template mail" "Lot 5 — Interviews & forwards" "lot:5,prio:S,area:backend,area:frontend,type:feature" "Spec: EF-905.
Accept: internal motive stored, optional refuse mail from template."
create_issue_if_missing "[lot5] Forward single + bulk + select_all+filters (50 max)" "Lot 5 — Interviews & forwards" "lot:5,prio:M,area:backend,area:frontend,type:feature" "Spec: EF-1001-1003; §5.5; API POST /forwards (202 async).
Scope: checkboxes persist across pages, select_all sends filters, to[] max 10, subject/message prefilled.
Accept: recette #7 — 3 selected -> 1 mail with 3 names + 3 CVs + history entry."
create_issue_if_missing "[lot5] SendForwardJob attachments vs signed links + Reply-To + slug names" "Lot 5 — Interviews & forwards" "lot:5,prio:M,area:backend,type:feature" "Spec: EF-1004,1005; §7.
Scope: total<=FORWARD_MAX_ATTACHMENT_MB(15) -> attachments else temporarySignedRoute 7d logged per download; from system, Reply-To recruiter; CV names via Str::slug.
Accept: heavy forward uses links; light uses attachments."
create_issue_if_missing "[lot5] Forward options history + privacy warning + include_analysis opt-in" "Lot 5 — Interviews & forwards" "lot:5,prio:S,area:backend,area:frontend,type:feature" "Spec: EF-1006-1008; RG-07.
API: GET /forwards + /{forward}.
Accept: default include_analysis=false; pre-send PII warning; who/when/to/which + sent/failed."
create_issue_if_missing "[lot5] Email templates + new-application + daily digest notifications" "Lot 5 — Interviews & forwards" "lot:5,prio:C,area:backend,area:frontend,type:feature" "Spec: EF-906,1101,1102.
Scope: invite/refuse/confirm customizable (C), per-application + daily recap.
Accept: template edit reflects in next mail."

# ---- LOT 6 ----
create_issue_if_missing "[lot6] Delete/export/purge (RETENTION_MONTHS=12)" "Lot 6 — Compliance & hardening" "lot:6,prio:M,area:backend,type:feature" "Spec: EF-1201-1203; RG-13; cmd applications:purge-expired.
API: DELETE /applications/{id}.
Accept: recette #10 — delete wipes rows+files; forward shows Deleted candidate; purge daily via scheduler."
create_issue_if_missing "[lot6] audit_logs (cv_viewed/forward/deleted/link regen)" "Lot 6 — Compliance & hardening" "lot:6,prio:S,area:backend,type:feature" "Spec: EF-1204; §8 Audit.
Accept: sensitive actions logged with user/subject/ip/metadata + per-candidate history."
create_issue_if_missing "[lot6] Hardening OWASP/uploads/signed DL/headers/secrets" "Lot 6 — Compliance & hardening" "lot:6,prio:M,area:backend,area:infra,type:feature" "Spec: Part II §8; ENF-04.
Scope: finfo+PDF/ZIP check, uuid names, private disk, image re-encode, 5-min signed DL, HSTS/CSP, Sanctum HttpOnly/SameSite/Secure, 5/min login throttle, owner Policies + isolation tests (recette #8).
Accept: A cannot access B offers/apps; ZAP baseline clean."
create_issue_if_missing "[lot6] Perf p95 + k6 + deploy docs + full recette 1-12" "Lot 6 — Compliance & hardening" "lot:6,prio:M,area:infra,area:qa,type:chore" "Spec: ENF-01/02/03/09/10; §9.2, §12.
Scope: eager with(analysis), indexes §4.2, aggregates, k6 5000-list + concurrent submits, backup/restore RPO24h/RTO4h, Horizon/Sentry//up alerts.
Accept: list <500ms p95, analysis <60s p95, recette grid completed."

echo ""
echo "== 4. Project v2 =="
PROJ_NUMBER="$(gh project list --owner "$OWNER" --format json | jq -r --arg t "$PROJECT_TITLE" '.projects[] | select(.title==$t) | .number' | head -n1)"
if [ -z "$PROJ_NUMBER" ] || [ "$PROJ_NUMBER" = "null" ]; then
  PROJ_NUMBER="$(gh project create --owner "$OWNER" --title "$PROJECT_TITLE" --format json | jq -r '.number')"
  echo "project created: #$PROJ_NUMBER"
else
  echo "project exists: #$PROJ_NUMBER"
fi
gh project link "$PROJ_NUMBER" --owner "$OWNER" --repo "$REPO" || echo "link skipped (already linked?)"

echo ""
echo "== 5. Project fields =="
EXISTING_FIELDS="$(gh project field-list "$PROJ_NUMBER" --owner "$OWNER" --format json || echo '{"fields":[]}')"
ensure_field() {
  local name="$1" type="$2" options="${3:-}"
  if echo "$EXISTING_FIELDS" | jq -e --arg n "$name" '.fields[] | select(.name==$n)' >/dev/null 2>&1; then
    echo "field exists: $name"
  else
    if [ -n "$options" ]; then
      gh project field-create "$PROJ_NUMBER" --owner "$OWNER" --name "$name" --data-type "$type" --single-select-options "$options" >/dev/null
    else
      gh project field-create "$PROJ_NUMBER" --owner "$OWNER" --name "$name" --data-type "$type" >/dev/null
    fi
    echo "field created: $name"
  fi
}
ensure_field "Lot" "SINGLE_SELECT" "0-Socle,1-Offres,2-Candidature,3-IA,4-Tri,5-Entretien+Transfert,6-Conformite"
ensure_field "Priority" "SINGLE_SELECT" "M-MVP,S,C"
ensure_field "Area" "SINGLE_SELECT" "backend,frontend,infra/CI,AI,QA-sec"
ensure_field "EF" "TEXT" ""
ensure_field "Estimate" "NUMBER" ""

echo ""
echo "== 6. Add issues to project =="
gh issue list --repo "$REPO" --limit 200 --json url -q '.[].url' | tr -d '\r' | while read -r url; do
  [ -z "$url" ] && continue
  gh project item-add "$PROJ_NUMBER" --owner "$OWNER" --url "$url" >/dev/null 2>&1 && echo "added $url" || echo "skip (already in project?) $url"
done

echo ""
echo "Done. Open: https://github.com/users/$OWNER/projects/$PROJ_NUMBER/views/1"
echo "Manual in project UI (not API): create Board view grouped by Status + Table grouped by Lot, set Workflow auto-status."
