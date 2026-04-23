#!/usr/bin/env python3
"""
memverify — Vérifie que les tags stack dans .memtags.yml correspondent au palace.

Usage:
  python3 scripts/palace/memverify.py              # vérification
  python3 scripts/palace/memverify.py --init       # génère .memtags.yml depuis le palace
  python3 scripts/palace/memverify.py --promote <tag>
  python3 scripts/palace/memverify.py --dismiss <tag>
"""

import os
import re
import subprocess
import sys

import yaml


_CONFIG_PATH = ".memtags.yml"

_KNOWN_TECHS = [
    "codeigniter", "ci3", "ci4", "php", "laravel", "symfony",
    "postgresql", "mysql", "redis", "sqlite",
    "oauth2", "jwt", "bshaffer",
    "jquery", "react", "vue", "angular", "svelte",
    "typescript", "javascript", "twig", "blade",
    "tanstack", "tailwind", "bootstrap",
    "d3",
    "docker", "nginx", "apache",
    "github-actions", "phpunit", "pest",
    "mempalace", "mulch", "pgvector",
]


def _load_config(path: str) -> dict:
    if not os.path.exists(path):
        print(f"⚠️  {path} introuvable — lance --init pour le générer.", file=sys.stderr)
        sys.exit(1)
    with open(path) as f:
        return yaml.safe_load(f) or {}


def _save_config(path: str, config: dict) -> None:
    with open(path, "w") as f:
        yaml.dump(config, f, allow_unicode=True, sort_keys=False)


def _palace_text() -> str:
    try:
        r = subprocess.run(["mempalace", "wake-up"], capture_output=True, text=True, timeout=30)
        return r.stdout
    except (subprocess.TimeoutExpired, FileNotFoundError) as e:
        print(f"⚠️  mempalace wake-up échoué : {e}", file=sys.stderr)
        sys.exit(1)


def _detect_techs(text: str) -> set[str]:
    lo = text.lower()
    found = set()
    for tech in _KNOWN_TECHS:
        if re.search(r'\b' + re.escape(tech) + r'\b', lo):
            found.add(tech)
    return found


def cmd_verify(config: dict) -> None:
    declared = {t.lower() for t in config.get("stack_declared", [])}
    pending  = {t.lower() for t in config.get("stack_pending", [])}
    ignored  = {t.lower() for t in config.get("stack_ignored", [])}
    project  = config.get("project", "unknown")

    print(f"memverify — {project}")
    print("=" * 40)
    print("Lecture palace (mempalace wake-up)...\n")

    found   = _detect_techs(_palace_text())
    ok      = declared & found
    missing = declared - found
    flagged = pending & found
    unknown = found - declared - pending - ignored

    for tag in sorted(ok):
        print(f"  ✅  {tag}")
    for tag in sorted(flagged):
        print(f"  ⚠️   {tag}  ← pending trouvé  →  --promote ou --dismiss")
    for tag in sorted(unknown):
        print(f"  ❓  {tag}  ← détecté, non déclaré")
    for tag in sorted(missing):
        print(f"  ❌  {tag}  ← déclaré mais absent du palace")

    print()
    if flagged:
        print(f"→ {len(flagged)} tag(s) pending à traiter.")
    elif missing:
        print(f"→ {len(missing)} tag(s) déclarés absents du palace (pas encore minés ?).")
    else:
        print("→ Palace cohérent avec les tags déclarés.")


def cmd_init() -> None:
    print("Scan palace pour générer .memtags.yml...")
    found = _detect_techs(_palace_text())
    config = {
        "project": os.path.basename(os.getcwd()),
        "stack_declared": sorted(found),
        "stack_pending":  [],
        "stack_ignored":  [],
    }
    _save_config(_CONFIG_PATH, config)
    print(f"✅  {_CONFIG_PATH} généré — {len(found)} tags détectés.")
    print("Édite le fichier pour séparer declared / pending / ignored.")


def cmd_promote(config: dict, tag: str) -> None:
    tag = tag.lower()
    config["stack_pending"]  = [t for t in config.get("stack_pending", [])  if t.lower() != tag]
    config["stack_ignored"]  = [t for t in config.get("stack_ignored", [])  if t.lower() != tag]
    declared = [t.lower() for t in config.get("stack_declared", [])]
    if tag not in declared:
        config["stack_declared"] = config.get("stack_declared", []) + [tag]
    _save_config(_CONFIG_PATH, config)
    print(f"✅  '{tag}' → declared")


def cmd_dismiss(config: dict, tag: str) -> None:
    tag = tag.lower()
    config["stack_pending"]  = [t for t in config.get("stack_pending", [])  if t.lower() != tag]
    config["stack_declared"] = [t for t in config.get("stack_declared", []) if t.lower() != tag]
    ignored = [t.lower() for t in config.get("stack_ignored", [])]
    if tag not in ignored:
        config["stack_ignored"] = config.get("stack_ignored", []) + [tag]
    _save_config(_CONFIG_PATH, config)
    print(f"✅  '{tag}' → ignored")


def main() -> None:
    args = sys.argv[1:]

    if "--init" in args:
        cmd_init()
        return

    if "--promote" in args:
        idx = args.index("--promote")
        if idx + 1 >= len(args):
            print("Usage: memverify --promote <tag>", file=sys.stderr)
            sys.exit(1)
        cmd_promote(_load_config(_CONFIG_PATH), args[idx + 1])
        return

    if "--dismiss" in args:
        idx = args.index("--dismiss")
        if idx + 1 >= len(args):
            print("Usage: memverify --dismiss <tag>", file=sys.stderr)
            sys.exit(1)
        cmd_dismiss(_load_config(_CONFIG_PATH), args[idx + 1])
        return

    cmd_verify(_load_config(_CONFIG_PATH))


if __name__ == "__main__":
    main()
