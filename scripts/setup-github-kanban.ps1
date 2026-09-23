# RecruteSmart — GitHub Kanban setup (Projects v2 + Issues + Labels + Milestones)
# Usage:
#   & "C:\Program Files\GitHub CLI\gh.exe" auth login   # scopes: repo, project, workflow
#   powershell -ExecutionPolicy Bypass -File scripts/setup-github-kanban.ps1
# Idempotent: safe to re-run (labels --force, milestones/issues skipped if exists).

$ErrorActionPreference = "Stop"
$GH = "C:\Program Files\GitHub CLI\gh.exe"
$Owner = "Pro-joseph"
$Repo = "Pro-joseph/RecruteSmart"
$ProjectTitle = "RecruteSmart — Lots 0-6"

function Invoke-Gh($args) {
  & $GH @args
}

Write-Host "== 0. Auth check ==" -ForegroundColor Cyan
Invoke-Gh @("auth", "status")
Invoke-Gh @("repo", "view", $Repo, "--json", "name,owner,url")

Write-Host "`n== 1. Labels ==" -ForegroundColor Cyan
$labels = @(
  @("prio:M", "d73a4a", "Must — MVP indispensable"),
  @("prio:S", "fbca04", "Should — important"),
  @("prio:C", "9e9e9e", "Could — desirable"),
  @("area:backend", "1d76db", "Laravel API"),
  @("area:frontend", "5319e7", "Angular UI"),
  @("area:ai", "0e8a16", "AI pipeline"),
  @("area:infra", "0052cc", "Docker / CI / deploy"),
  @("area:qa", "e99695", "Tests / security / perf"),
  @("lot:0", "bfd4f2", "Lot 0 Socle"),
  @("lot:1", "bfd4f2", "Lot 1 Offers + forms"),
  @("lot:2", "bfd4f2", "Lot 2 Public apply + list"),
  @("lot:3", "bfd4f2", "Lot 3 AI analysis"),
  @("lot:4", "bfd4f2", "Lot 4 Screening + filters"),
  @("lot:5", "bfd4f2", "Lot 5 Interviews + forwards"),
  @("lot:6", "bfd4f2", "Lot 6 Compliance + hardening"),
  @("type:feature", "a2eeef", "Feature"),
  @("type:chore", "fef2c0", "Chore")
)
foreach ($l in $labels) {
  Invoke-Gh @("label", "create", $l[0], "--repo", $Repo, "--color", $l[1], "--description", $l[2], "--force")
}

Write-Host "`n== 2. Milestones (via API) ==" -ForegroundColor Cyan
$milestones = @(
  @("Lot 0 — Socle", "Git, Docker, CI, Laravel+Angular skeletons, auth. ~1 wk. EF-101/102."),
  @("Lot 1 — Offers & forms", "Offers CRUD, criteria, form catalog/editor, public link. ~2 wks."),
  @("Lot 2 — Public apply & list", "Public page, uploads, dashboard list. ~2 wks."),
  @("Lot 3 — AI analysis", "Extraction, ATS, match, summary. ~3 wks."),
  @("Lot 4 — Screening & filters", "Candidate sheet, statuses, filters/sort/search. ~2 wks."),
  @("Lot 5 — Interviews & forwards", "Interviews, rejections, email forwards, notifications. ~2 wks."),
  @("Lot 6 — Compliance & hardening", "Purge/delete/audit, hardening, load tests, deploy. ~2 wks.")
)
$existingMs = Invoke-Gh @("api", "repos/$Repo/milestones", "--paginate", "-q", ".[].title") | Out-String
foreach ($m in $milestones) {
  if ($existingMs -match [regex]::Escape($m[0])) { Write-Host "milestone exists: $($m[0])" }
  else { Invoke-Gh @("api", "repos/$Repo/milestones", "-f", "title=$($m[0])", "-f", "description=$($m[1])", "-f", "state=open") | Out-Null; Write-Host "created: $($m[0])" }
}

Write-Host "`n== 3. Issues ==" -ForegroundColor Cyan
# Each: Title | Milestone | Labels(csv) | Body
$issues = @(
  # ---- LOT 0 ----
  @("[lot0] Monorepo backend/ + frontend/ + .env.example", "Lot 0 — Socle", "lot:0,prio:M,area:infra,type:chore",
"Spec: Part II §3, §15.1-2.`nScope: create backend/ (Laravel 13) + frontend/ (Angular 22) in monorepo, .env.example with all §11.2 keys.`nAccept: folders + env example present, README quickstart valid."),
  @("[lot0] docker-compose (nginx,app,horizon,scheduler,postgres,redis,mailpit) + /up", "Lot 0 — Socle", "lot:0,prio:M,area:infra,type:feature",
"Spec: Part II §11.1.`nScope: compose services, nginx routes / -> Angular, /api + /sanctum -> Laravel, health /up.`nAccept: docker compose up responds on /up; mailpit UI reachable."),
  @("[lot0] Laravel 13 + Sanctum SPA + Angular proxy auth smoke", "Lot 0 — Socle", "lot:0,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-101,102; D1/D2; §15.4.`nScope: Sanctum statefulApi + SANCTUM_STATEFUL_DOMAINS, proxy.conf.json, register/login/me flow.`nAccept: Angular can register, login, GET /api/v1/auth/me."),
  @("[lot0] CI backend (Pint/Larastan/Pest+audit) + frontend (ESLint/build/audit)", "Lot 0 — Socle", "lot:0,prio:M,area:infra,type:chore",
"Spec: Part II §11.3.`nScope: GitHub Actions backend + frontend jobs, blocking on lint/test fail.`nAccept: PR runs both jobs green on skeleton."),
  @("[lot0] Freeze versions + choose LLM provider + 20-30 sample CVs", "Lot 0 — Socle", "lot:0,prio:M,area:ai,type:chore",
"Spec: H-07; §6, §12, §15.6.`nScope: lock composer/package locks (PHP 8.4, PG16, Redis7), pick provider/region/DPA, add anonymized CV fixtures.`nAccept: provider + model + test set documented."),

  # ---- LOT 1 ----
  @("[lot1] Offer CRUD + statuses + close/duplicate", "Lot 1 — Offers & forms", "lot:1,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-201,204,209,210; RG-10.`nAPI: GET/POST /offers, GET/PUT/DELETE /offers/{offer}, POST /close, /duplicate.`nAccept: draft->published->closed->archived; closed blocks new applications; recette #11."),
  @("[lot1] Evaluation criteria + scoring weights + knockout + criteria_version", "Lot 1 — Offers & forms", "lot:1,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-202,211,607; RG-08,09; §5.3.`nScope: required/preferred skills, min exp, education, languages, location, knockout list, weights json, version bump marks analyses stale.`nAccept: editing criteria sets is_stale=true."),
  @("[lot1] Field catalog + per-offer form config", "Lot 1 — Offers & forms", "lot:1,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-301-304,306; §4.4 catalog; RG-12.`nAPI: GET /form-field-catalog, GET/PUT /offers/{offer}/form-fields.`nAccept: full_name/email/cv locked required; photo/age/birth_date flagged sensitive optional, excluded from AI."),
  @("[lot1] Publish -> public_token + copy/QR/regenerate", "Lot 1 — Offers & forms", "lot:1,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-205-208; RG-11.`nAPI: POST /offers/{offer}/publish, /regenerate-link.`nAccept: Str::random(24) unique token; regenerate invalidates old, keeps applications."),
  @("[lot1] Form preview + salary/posts/deadline + hide-not-delete rule", "Lot 1 — Offers & forms", "lot:1,prio:S,area:backend,area:frontend,type:feature",
"Spec: EF-203,305,307.`nAccept: preview matches candidate view; used field cannot be deleted, only hidden (is_hidden)."),

  # ---- LOT 2 ----
  @("[lot2] Public offer page + Angular /apply/:token (mobile-first)", "Lot 2 — Public apply & list", "lot:2,prio:M,area:frontend,type:feature",
"Spec: EF-401,402,410; ENF-06/07/08.`nScope: dynamic FormGroup from offer config, client validation mirrors server, <2s on 4G, WCAG AA.`nAccept: recette #2 — anonymous sees only chosen fields, CV required."),
  @("[lot2] POST application multipart + dynamic validation + private storage", "Lot 2 — Public apply & list", "lot:2,prio:M,area:backend,type:feature",
"Spec: EF-402,403,408; §5.4; RG-02/04.`nAPI: POST /public/offers/{token}/applications (full_name,email,answers[],files[cv],consent,captcha).`nAccept: recette #3/#4 — .exe/no-CV rejected with clear FR message; valid -> 201 + pending analysis in <5s in correct offer only."),
  @("[lot2] Consent + confirmation + candidate email + no-double-email", "Lot 2 — Public apply & list", "lot:2,prio:M,area:backend,type:feature",
"Spec: EF-404-407; RG-03,14.`nScope: timestamped consent+version, confirm screen, ApplicationReceivedMail, UNIQUE(offer_id,email).`nAccept: second submit same email -> 422 dedicated message."),
  @("[lot2] Anti-spam rate-limit + CAPTCHA + honeypot", "Lot 2 — Public apply & list", "lot:2,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-409; §8 Anti-abus.`nScope: 5/min + 30/h per IP, Turnstile/hCaptcha, honeypot, per-offer quota.`nAccept: spam burst throttled 429 without breaking legit submit."),
  @("[lot2] Recruiter dashboard + offer workspace list (25pp, live analysis badge)", "Lot 2 — Public apply & list", "lot:2,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-501-506; ENF-01.`nAPI: GET /offers/{offer}/applications paginated, with analysis.`nAccept: columns name/match/ATS/exp/city/skills/status/date; default sort -match_score; polling 5s while pending/processing."),

  # ---- LOT 3 ----
  @("[lot3] CvTextExtractor PDF/DOCX + unreadable path (no LLM call)", "Lot 3 — AI analysis", "lot:3,prio:M,area:backend,area:ai,type:feature",
"Spec: EF-602,609; §6.2 steps 2-3; recette #12.`nScope: pdfparser + PHPWord, <200 chars -> ATS-01 fail, match_score=null, message 'texte non extractible'.`nAccept: scanned CV -> non_compliant ATS, no provider call, retry button."),
  @("[lot3] AtsScorer 6 checks + verdict + advice", "Lot 3 — AI analysis", "lot:3,prio:M,area:backend,area:ai,type:feature",
"Spec: EF-603; Part I §5.2 ATS-01..06.`nScope: one class per check (AtsCheck interface), 25+20+10+10+10+25, verdict 75/50.`nAccept: Pest covers each check; failed checks show message+advice on sheet."),
  @("[lot3] CvTextSanitizer (PII masking, sensitive strip, truncate)", "Lot 3 — AI analysis", "lot:3,prio:M,area:backend,area:ai,type:feature",
"Spec: EF-610; §6.2 step 5; RG-07.`nScope: mask emails/phones/URLs, replace name->[CANDIDAT], drop age/birth/family/photo lines, LLM_MAX_INPUT_CHARS=30000.`nAccept: test proves no sensitive/direct contact sent to LLM (recette #5)."),
  @("[lot3] CvAnalyzer interface + LlmCvAnalyzer prompt v1 + JSON schema", "Lot 3 — AI analysis", "lot:3,prio:M,area:backend,area:ai,type:feature",
"Spec: §6.3/6.4; schema app/Services/Analysis/schemas/cv_analysis.schema.json.`nScope: temp 0-0.2, 1500 max tokens, 60s timeout, structured output, retry once on invalid with error feedback.`nAccept: sample CVs validate against schema; FakeCvAnalyzer for tests."),
  @("[lot3] ScoreCalculator pure + renormalization + knockout flags", "Lot 3 — AI analysis", "lot:3,prio:M,area:backend,area:ai,type:feature",
"Spec: Part I §5.3; EF-604.`nScope: weighted avg renormalized on filled criteria only, knockout_threshold=40 default.`nAccept: example 90/50/70/100/80 -> 81%; unit-tested; never reads final % from LLM."),
  @("[lot3] AnalyzeApplicationJob retries/cache/idempotence/quotas + reanalyze", "Lot 3 — AI analysis", "lot:3,prio:M,area:backend,area:ai,type:feature",
"Spec: EF-601,608; §6.7; API POST /applications/{id}/reanalyze + /offers/{id}/reanalyze.`nScope: tries=3 backoff 30/120/600 timeout 180, RateLimited+WithoutOverlapping+ShouldBeUnique, input_hash cache, token logging, LLM_DAILY_LIMIT_PER_USER.`nAccept: provider outage -> queued+replayable; recalc in one click when stale."),
  @("[lot3] Prompt-injection + bias guards + anomalies badge", "Lot 3 — AI analysis", "lot:3,prio:S,area:backend,area:ai,type:feature",
"Spec: §6.8.`nScope: CV framed as data, ignore-instructions rule, schema lock, suspect-pattern detect (ignore instructions/give 100%), anomalies[] + badge.`nAccept: malicious CV fixture flagged, score not blindly 100."),

  # ---- LOT 4 ----
  @("[lot4] Candidate sheet + CV preview/signed URL + scores breakdown", "Lot 4 — Screening & filters", "lot:4,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-701,702.`nAPI: GET /applications/{id}, GET /files/{key} (5-min signed).`nAccept: answers+files, ATS/match per-criterion evidence, summary/strengths/gaps."),
  @("[lot4] Status change single + bulk + auto on interview", "Lot 4 — Screening & filters", "lot:4,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-703,901; §5.1; API PATCH /status + POST /bulk-status.`nAccept: manual anytime; interview plan -> interview; forward never changes status; rejected->new reactivation."),
  @("[lot4] Notes 1-5 + history timeline", "Lot 4 — Screening & filters", "lot:4,prio:S,area:backend,area:frontend,type:feature",
"Spec: EF-704,705; API POST /notes, GET /events.`nAccept: application_events logs status/note/interview/forward/analysis."),
  @("[lot4] Filters score/ATS/skills/exp/location", "Lot 4 — Screening & filters", "lot:4,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-801-805; §5.3 params.`nScope: min/max_score, ats verdict+range, skills[] any/all via application_skill, exp range, city_normalized+country.`nAccept: recette #6 combined filter returns exact expected set on fixtures."),
  @("[lot4] Filters status/date/combine/URL-sync/saved views", "Lot 4 — Screening & filters", "lot:4,prio:S,area:backend,area:frontend,type:feature",
"Spec: EF-806-809.`nScope: AND logic, result count, reset, router query sync, saved combos (C).`nAccept: shareable URL restores filters+sort; back button works."),
  @("[lot4] Search q + /skills with counts + /stats aggregates", "Lot 4 — Screening & filters", "lot:4,prio:S,area:backend,area:frontend,type:feature",
"Spec: EF-507,508; API GET /offers/{id}/skills + /stats.`nScope: q on name/email/skills (ilike), skills from offer candidates only, SQL aggregates cached 60s.`nAccept: p95 <500ms on 5000 apps fixture."),

  # ---- LOT 5 ----
  @("[lot5] Interview plan/track/report/decision + .ics invite", "Lot 5 — Interviews & forwards", "lot:5,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-902-904; API POST /applications/{id}/interviews, PATCH /interviews/{id}.`nAccept: recette #9 — plan -> status interview + invite with .ics; done/canceled/rescheduled + notes/decision."),
  @("[lot5] Reject with reason + optional template mail", "Lot 5 — Interviews & forwards", "lot:5,prio:S,area:backend,area:frontend,type:feature",
"Spec: EF-905.`nAccept: internal motive stored, optional refuse mail from template."),
  @("[lot5] Forward single + bulk + select_all+filters (50 max)", "Lot 5 — Interviews & forwards", "lot:5,prio:M,area:backend,area:frontend,type:feature",
"Spec: EF-1001-1003; §5.5; API POST /forwards (202 async).`nScope: checkboxes persist across pages, select_all sends filters, to[] max 10, subject/message prefilled.`nAccept: recette #7 — 3 selected -> 1 mail with 3 names + 3 CVs + history entry."),
  @("[lot5] SendForwardJob attachments vs signed links + Reply-To + slug names", "Lot 5 — Interviews & forwards", "lot:5,prio:M,area:backend,type:feature",
"Spec: EF-1004,1005; §7.`nScope: total<=FORWARD_MAX_ATTACHMENT_MB(15) -> attachments else temporarySignedRoute 7d logged per download; from system, Reply-To recruiter; CV_Nom via Str::slug.`nAccept: heavy forward uses links; light uses attachments."),
  @("[lot5] Forward options history + privacy warning + include_analysis opt-in", "Lot 5 — Interviews & forwards", "lot:5,prio:S,area:backend,area:frontend,type:feature",
"Spec: EF-1006-1008; RG-07.`nAPI: GET /forwards + /{forward}.`nAccept: default include_analysis=false; pre-send PII warning; who/when/to/which + sent/failed."),
  @("[lot5] Email templates + new-application + daily digest notifications", "Lot 5 — Interviews & forwards", "lot:5,prio:C,area:backend,area:frontend,type:feature",
"Spec: EF-906,1101,1102.`nScope: invite/refuse/confirm customizable (C), per-application + daily recap.`nAccept: template edit reflects in next mail."),

  # ---- LOT 6 ----
  @("[lot6] Delete/export/purge (RETENTION_MONTHS=12)", "Lot 6 — Compliance & hardening", "lot:6,prio:M,area:backend,type:feature",
"Spec: EF-1201-1203; RG-13; cmd applications:purge-expired.`nAPI: DELETE /applications/{id}.`nAccept: recette #10 — delete wipes rows+files; forward shows 'Deleted candidate'; purge daily via scheduler."),
  @("[lot6] audit_logs (cv_viewed/forward/deleted/link regen)", "Lot 6 — Compliance & hardening", "lot:6,prio:S,area:backend,type:feature",
"Spec: EF-1204; §8 Audit.`nAccept: sensitive actions logged with user/subject/ip/metadata + per-candidate history."),
  @("[lot6] Hardening OWASP/uploads/signed DL/headers/secrets", "Lot 6 — Compliance & hardening", "lot:6,prio:M,area:backend,area:infra,type:feature",
"Spec: Part II §8; ENF-04.`nScope: finfo+%PDF/ZIP check, uuid names, private disk, image re-encode, 5-min signed DL, HSTS/CSP, Sanctum HttpOnly/SameSite/Secure, 5/min login throttle, owner Policies + isolation tests (recette #8).`nAccept: A cannot access B offers/apps; ZAP baseline clean."),
  @("[lot6] Perf p95 + k6 + deploy docs + full recette 1-12", "Lot 6 — Compliance & hardening", "lot:6,prio:M,area:infra,area:qa,type:chore",
"Spec: ENF-01/02/03/09/10; §9.2, §12.`nScope: eager with(analysis), indexes §4.2, aggregates, k6 5000-list + concurrent submits, backup/restore RPO24h/RTO4h, Horizon/Sentry//up alerts.`nAccept: list <500ms p95, analysis <60s p95, recette grid completed.")
)

$existingTitles = Invoke-Gh @("issue", "list", "--repo", $Repo, "--limit", "200", "--json", "title", "-q", ".[].title") | Out-String
$createdUrls = @()
foreach ($it in $issues) {
  $title = $it[0]; $ms = $it[1]; $labs = $it[2]; $body = $it[3]
  if ($existingTitles -match [regex]::Escape($title)) { Write-Host "issue exists: $title" -ForegroundColor DarkGray; continue }
  $url = Invoke-Gh @("issue", "create", "--repo", $Repo, "--title", $title, "--body", $body, "--label", $labs, "--milestone", $ms) | Out-String
  $url = $url.Trim()
  Write-Host "created: $title -> $url" -ForegroundColor Green
  $createdUrls += $url
}

Write-Host "`n== 4. Project v2 ==" -ForegroundColor Cyan
$projJson = Invoke-Gh @("project", "list", "--owner", $Owner, "--format", "json") | Out-String | ConvertFrom-Json
$proj = $projJson.projects | Where-Object { $_.title -eq $ProjectTitle } | Select-Object -First 1
if (-not $proj) {
  $created = Invoke-Gh @("project", "create", "--owner", $Owner, "--title", $ProjectTitle, "--format", "json") | Out-String | ConvertFrom-Json
  $projNumber = $created.number
  Write-Host "project created: #$projNumber"
} else {
  $projNumber = $proj.number
  Write-Host "project exists: #$projNumber"
}
# Link repo (idempotent-ish; ignore error if already linked)
try { Invoke-Gh @("project", "link", $projNumber, "--owner", $Owner, "--repo", $Repo) | Out-Null; Write-Host "linked to $Repo" } catch { Write-Host "link skipped (already linked?)" -ForegroundColor DarkGray }

Write-Host "`n== 5. Project fields ==" -ForegroundColor Cyan
$existingFields = Invoke-Gh @("project", "field-list", $projNumber, "--owner", $Owner, "--format", "json") | Out-String | ConvertFrom-Json
function Ensure-Field($name, $type, $options) {
  $f = $existingFields.fields | Where-Object { $_.name -eq $name } | Select-Object -First 1
  if ($f) { Write-Host "field exists: $name" -ForegroundColor DarkGray; return }
  if ($options) { Invoke-Gh @("project", "field-create", $projNumber, "--owner", $Owner, "--name", $name, "--data-type", $type, "--single-select-options", $options) | Out-Null }
  else { Invoke-Gh @("project", "field-create", $projNumber, "--owner", $Owner, "--name", $name, "--data-type", $type) | Out-Null }
  Write-Host "field created: $name"
}
Ensure-Field "Lot" "SINGLE_SELECT" "0-Socle,1-Offres,2-Candidature,3-IA,4-Tri,5-Entretien+Transfert,6-Conformite"
Ensure-Field "Priority" "SINGLE_SELECT" "M-MVP,S,C"
Ensure-Field "Area" "SINGLE_SELECT" "backend,frontend,infra/CI,AI,QA-sec"
Ensure-Field "EF" "TEXT" $null
Ensure-Field "Estimate" "NUMBER" $null

Write-Host "`n== 6. Add issues to project ==" -ForegroundColor Cyan
$allUrls = Invoke-Gh @("issue", "list", "--repo", $Repo, "--limit", "200", "--json", "url", "-q", ".[].url") | Out-String | ConvertFrom-Json
if (-not $allUrls) { $allUrls = @() }
# gh returns array of objects when json url; normalize
$urls = @()
foreach ($u in $allUrls) { if ($u.url) { $urls += $u.url } elseif ($u -is [string] -and $u.StartsWith("http")) { $urls += $u } }
if ($urls.Count -eq 0 -and $createdUrls.Count -gt 0) { $urls = $createdUrls }
foreach ($u in $urls) {
  try { Invoke-Gh @("project", "item-add", $projNumber, "--owner", $Owner, "--url", $u) | Out-Null; Write-Host "added $u" -ForegroundColor DarkGray }
  catch { Write-Host "skip (already in project?) $u" -ForegroundColor DarkGray }
}

Write-Host "`nDone. Open: https://github.com/users/$Owner/projects/$projNumber/views/1" -ForegroundColor Cyan
Write-Host "Manual in project UI (not API): create Board view grouped by Status + Table grouped by Lot, set Workflow auto-status (Todo on add, In Progress on PR link, Done on close)." -ForegroundColor Yellow
