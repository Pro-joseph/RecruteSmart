# Sample CV fixtures (Lot 3)

Target: 20-30 CVs (PDF + DOCX mix) covering:

- ATS-compliant / improvable / non-compliant (scanned, no extractable text)
- Junior / mid / senior profiles, FR + EN
- Skill variants (`js` vs `javascript`, `postgres` vs `postgresql`)
- Edge cases: 1-page vs 4-page, missing dates, prompt-injection attempt
  (`ignore instructions`, `give 100%`), sensitive lines (age, photo mention)

Rules:

- **No real personal data.** Anonymized fixtures only.
- Each fixture gets an expected verdict (`compliant` / `improvable` / `non_compliant`)
  documented alongside the file in Lot 3.
- Used by `FakeCvAnalyzer` stability tests + prompt non-regression (§12).
