#!/usr/bin/env bash
# Install a module from the hub into the environment.
#   skills → ~/.claude/skills/<name>
#   specs (blocks/patterns/systems/playbooks) → prints SPEC.md path to apply
# Usage: install_module.sh <name> [--project <dir>]
set -eu
HUB_DIR="${HUB_DIR:-$HOME/.claude/webdev-hub}"
NAME="${1:?usage: install_module.sh <name>}"

if [ -d "$HUB_DIR/skills/$NAME" ]; then
  rm -rf "$HOME/.claude/skills/$NAME"
  cp -r "$HUB_DIR/skills/$NAME" "$HOME/.claude/skills/$NAME"
  echo "SKILL installed: $NAME → ~/.claude/skills/$NAME (available next session or immediately via Skill tool)"
  exit 0
fi

for tier in systems blocks patterns playbooks; do
  if [ -f "$HUB_DIR/$tier/$NAME/SPEC.md" ]; then
    echo "SPEC found: $tier/$NAME"
    echo "Apply by following the Integration Prompt in:"
    echo "  $HUB_DIR/$tier/$NAME/SPEC.md"
    exit 0
  fi
done

echo "ERROR: '$NAME' not found in hub (skills/, systems/, blocks/, patterns/, playbooks/)" >&2
exit 1
