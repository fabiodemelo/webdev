#!/usr/bin/env bash
# dmcode update check: hub freshness + upstream (jeffallan/claude-skills) drift.
set -u
HUB_DIR="${HUB_DIR:-$HOME/.claude/webdev-hub}"
HUB_REMOTE="https://github.com/fabiodemelo/webdev.git"
UPSTREAM_REMOTE="https://github.com/jeffallan/claude-skills.git"

# 1. ensure local hub clone
if [ ! -d "$HUB_DIR/.git" ]; then
  echo "Cloning hub → $HUB_DIR"
  git clone -q "$HUB_REMOTE" "$HUB_DIR" || { echo "ERROR: cannot clone hub"; exit 1; }
fi
cd "$HUB_DIR" || exit 1
git fetch -q origin

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main 2>/dev/null || git rev-parse origin/master)
if [ "$LOCAL" != "$REMOTE" ]; then
  echo "HUB: behind origin ($(git rev-list --count HEAD..$REMOTE) commits) — run: git -C $HUB_DIR pull"
else
  echo "HUB: up to date ($LOCAL)"
fi

# 2. upstream drift vs recorded hash
RECORDED=$(grep -oE '[0-9a-f]{40}' skills/UPSTREAM.md 2>/dev/null | head -1)
UPSTREAM_HEAD=$(git ls-remote "$UPSTREAM_REMOTE" HEAD 2>/dev/null | cut -f1)
if [ -z "$UPSTREAM_HEAD" ]; then
  echo "UPSTREAM: unreachable (offline?)"
elif [ -z "$RECORDED" ]; then
  echo "UPSTREAM: no recorded sync hash in skills/UPSTREAM.md — run sync_upstream.sh"
elif [ "$RECORDED" = "$UPSTREAM_HEAD" ]; then
  echo "UPSTREAM: in sync ($RECORDED)"
else
  echo "UPSTREAM: NEW CODE AVAILABLE"
  echo "  recorded: $RECORDED"
  echo "  latest:   $UPSTREAM_HEAD"
  echo "  → review changes: https://github.com/jeffallan/claude-skills/compare/${RECORDED:0:12}...main"
  echo "  → sync with: sync_upstream.sh (asks before overwriting)"
fi
