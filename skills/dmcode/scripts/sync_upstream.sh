#!/usr/bin/env bash
# Sync the hub's skills/ mirror from upstream jeffallan/claude-skills.
# Overwrites mirrored skill folders; NEVER touches Fabio's own skills (dmcode, delivery-gate)
# or the spec dirs (blocks/patterns/systems/playbooks). Commits + pushes the hub.
set -eu
HUB_DIR="${HUB_DIR:-$HOME/.claude/webdev-hub}"
UPSTREAM_REMOTE="https://github.com/jeffallan/claude-skills.git"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

git clone -q --depth 1 "$UPSTREAM_REMOTE" "$TMP/upstream"
HASH=$(git -C "$TMP/upstream" rev-parse HEAD)
cd "$HUB_DIR"

mkdir -p skills
for d in "$TMP"/upstream/skills/*/; do
  name=$(basename "$d")
  case "$name" in dmcode|delivery-gate) continue ;; esac
  rm -rf "skills/$name"
  cp -r "$d" "skills/$name"
done
cp "$TMP/upstream/LICENSE" skills/LICENSE-upstream 2>/dev/null || true
cp "$TMP/upstream/SKILLS_GUIDE.md" skills/SKILLS_GUIDE.md 2>/dev/null || true

cat > skills/UPSTREAM.md <<EOF
# Upstream mirror record
Source: https://github.com/jeffallan/claude-skills
Synced commit: $HASH
Synced at: $(date -u +%Y-%m-%dT%H:%M:%SZ)
License: MIT (see LICENSE-upstream)
Fabio's own skills (never overwritten by sync): dmcode, delivery-gate
EOF

# refresh the locally-installed copies too
for d in skills/*/; do
  name=$(basename "$d")
  [ -f "$d/SKILL.md" ] || continue
  rm -rf "$HOME/.claude/skills/$name.tmp"
  cp -r "$d" "$HOME/.claude/skills/$name.tmp" && rm -rf "$HOME/.claude/skills/$name" && mv "$HOME/.claude/skills/$name.tmp" "$HOME/.claude/skills/$name"
done

git add skills
git commit -q -m "sync skills mirror from upstream $HASH" || echo "nothing to commit"
git push -q origin HEAD
echo "Synced to upstream $HASH, hub pushed, local ~/.claude/skills refreshed."
