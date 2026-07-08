#!/usr/bin/env bash
# delivery-gate static gap scan — hunts common "shipped but not finished" markers.
# Usage: gap_scan.sh <project-root>
set -u
ROOT="${1:-.}"
cd "$ROOT" || exit 1

section() { printf "\n=== %s ===\n" "$1"; }
G() { grep -rn --include='*.ts' --include='*.tsx' --include='*.js' --include='*.jsx' --include='*.py' --include='*.php' --include='*.html' --include='*.vue' --include='*.svelte' -E "$1" . 2>/dev/null | grep -v node_modules | grep -v '/dist/' | grep -v '/build/' | head -40; }

section "TODO / stub / placeholder markers"
G 'TODO|FIXME|XXX|HACK|placeholder|coming soon|not.?implemented|stub'

section "Disabled-forever UI (disabled without condition)"
G 'disabled(=\{true\}|="true"| disabled)'

section "Buttons with no handler (heuristic)"
G '<button(?![^>]*(onClick|onSubmit|type="submit"|type=.submit))' || true
grep -rn --include='*.tsx' --include='*.jsx' -E '<button[^>]*>' . 2>/dev/null | grep -v node_modules | grep -vE 'onClick|type=.submit|onSubmit' | head -20

section "Empty catch blocks (silent failures)"
G 'catch[^{]*\{\s*\}'

section "console.log used for user feedback"
G 'console\.(log|error)\(.*(fail|error|success|saved)'

section "Hardcoded URLs / secrets smells"
G '(http://localhost|127\.0\.0\.1|api_key\s*=\s*["\x27][A-Za-z0-9]|password\s*=\s*["\x27][^"\x27]{4,})'

section "Alerts as UX"
G 'window\.alert\(|alert\('

echo ""
echo "Scan complete. Every hit above must be classified in the Gap Report (gap, warning, or false positive with reason)."
