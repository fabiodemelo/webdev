---
name: delivery-gate
description: This skill must be used before claiming any coding/feature work "done", "complete", "ready", or "delivered" — and whenever the user asks to check for gaps, mistakes, issues, or problems in built software. It runs a mandatory gap hunt (static scan → build/tests → user-lens walkthrough of every screen and button → adversarial review) and produces a Gap Report that leads with what does NOT work. A delivery claim without a delivery-gate Gap Report is invalid.
---

# Delivery Gate

Purpose: stop shallow deliveries. Software is "done" only when a user can operate every surface of it without reading the code. This skill is the gate between "I built it" and "it is delivered."

## Iron rules

1. **Never say "done", "complete", "100%", "ready", or "delivered" without running this gate first.** No exceptions for time pressure or scope size.
2. **The Gap Report leads with gaps.** Verified items come last. An empty gap list must be earned with evidence, not asserted.
3. **Evidence or it didn't happen.** Every "verified" line cites proof: a command + its output, an HTTP status, or a screenshot path. "Should work" is a gap, not a verification.
4. **The user's words are the acceptance criteria.** Before auditing, restate the user's original request as a checklist and audit against *that*, not against the implementation plan.
5. **Testing code ≠ testing the product.** API 200s do not prove a human can use the feature. The walkthrough phase is mandatory for anything with a UI.

## Process

### Phase 0 — Acceptance criteria
Extract the user's requirements verbatim into a checklist (quote their words). Add the implicit table-stakes for the feature type from `references/user-lens-checklist.md` (e.g. "secured" implies password change; "settings" implies editable settings; "connect X" implies setup instructions).

### Phase 1 — Static gap scan
Run `scripts/gap_scan.sh <project-root>` (or apply its grep patterns manually). It hunts:
- TODO / FIXME / placeholder / stub / "coming soon" / "not implemented"
- UI elements with no handler (buttons without onClick/submit, forms without action)
- Read-only screens that should be editable (inputs with no save path)
- API endpoints with no UI caller, and UI calls with no matching endpoint
- Hardcoded URLs/credentials, console.log for user feedback, empty catch blocks

### Phase 2 — Build, tests, lint
Run the project's build, test suite, and typecheck. A failing or absent test suite is itself a reported gap. Never skip because "it built earlier".

### Phase 3 — User-lens walkthrough (mandatory for UI)
Operate the product as the user would, in a real browser (Playwright/preview/browser tools) or against the live deployment:
- Visit **every** route/screen. Click **every** button. Submit **every** form (valid + invalid input).
- Follow `references/user-lens-checklist.md` for the feature-type checklists (auth lifecycle, CRUD completeness, states, integrations).
- Capture a screenshot or response evidence per screen.
- Any dead end (no back path, no error message, silent failure, unreachable feature) is a gap.

### Phase 4 — Adversarial review
Spawn or invoke independent review passes — at minimum `code-reviewer` for correctness and `security-reviewer` for security-sensitive surfaces (auth, payments, secrets, spend). Their findings merge into the Gap Report. Do not filter "minor" findings out; rank them.

### Phase 5 — Gap Report (the only valid delivery format)

```
## GAP REPORT — <feature/project> — <date>
### ❌ Blockers (user cannot accomplish the goal)
### ⚠️ Gaps (missing/unfinished vs acceptance criteria — cite the criterion)
### 🟡 Warnings (works but risky/ugly/undocumented)
### ✅ Verified (each line: item — evidence)
### Verdict: BLOCKED | GAPS REMAIN | PASS
```

Verdict PASS requires: zero blockers, zero gaps, every acceptance criterion in Verified with evidence. Otherwise the delivery message must open with the gap list — never with achievements.

## Iteration
After fixes, re-run from Phase 1 on the changed surface and re-issue the report. Never carry a "Verified" forward from a previous run if its code changed.
