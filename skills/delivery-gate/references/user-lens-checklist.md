# User-Lens Checklists

Implicit acceptance criteria by feature type. When a delivery touches one of these areas, its checklist items are REQUIRED even if the user never listed them — users assume them, and their absence reads as broken software.

## Authentication ("login", "auth", "secured")
- [ ] Register or bootstrap path documented and working
- [ ] Login with wrong password shows a clear error (not a crash/silence)
- [ ] Logout works and actually invalidates the session
- [ ] **Change password** (verifies current password, revokes other sessions)
- [ ] Locked-out recovery path exists and is documented in the UI
- [ ] Session expiry handled gracefully (auto-refresh or clean redirect to login)

## Settings screens
- [ ] Every displayed value that looks editable IS editable and saves
- [ ] Save gives visible confirmation; failure gives visible error
- [ ] Read-only values are visually distinct and explain where they're managed
- [ ] Profile basics: name, email changeable

## CRUD features
- [ ] Create, Read, Update, Delete all reachable from the UI (not just the API)
- [ ] Delete asks for confirmation; destructive actions are reversible or clearly warned
- [ ] List views: loading state, empty state with CTA, error state with retry
- [ ] Pagination or sane limits on long lists

## Third-party integrations ("connect X", "API keys")
- [ ] Exact setup steps IN the UI: numbered, with direct links to the external console pages
- [ ] Every credential the user must obtain: where to get it, where to put it, what to restart
- [ ] Callback/redirect URLs displayed copy-ready where the platform needs them
- [ ] Connection health visible; failure states explain the fix, not just "error"
- [ ] Unconfigured state fails with instructions, never silently

## Forms
- [ ] Client + server validation, field-level error messages
- [ ] Submit disabled while pending; double-submit safe
- [ ] Invalid submit tested (not just happy path)

## Navigation
- [ ] Every route reachable from visible navigation (no URL-only screens)
- [ ] Back always possible; deep links load correctly (SPA fallback)
- [ ] 404 page with a way home

## Money / spend / irreversible actions
- [ ] Hard confirmation before spend or irreversible ops
- [ ] Limits/caps visible before arming; kill/stop control prominent
- [ ] Action log the user can inspect

## Deployment claims ("live", "deployed")
- [ ] Verified from OUTSIDE the server (public URL, fresh session)
- [ ] Survives process restart (persistence, PM2 save, cron)
- [ ] Error pages don't leak stack traces
