#!/usr/bin/env python3
"""
Palace Blog Generator — Crée un .md de blog après mempalace mine.

Usage (manuel):
  python3 scripts/palace/blog_generator.py

Usage (hook mempalace):
  Configuré automatiquement dans mempalace.yaml — s'exécute après chaque mine.

Output:
  docs/sessions/palace-blog-YYYY-MM-DD.md
"""

import json
import os
import subprocess
import sys
from datetime import datetime


def get_git_info():
    """Récupère la branche et le commit actuels."""
    try:
        branch = subprocess.check_output(
            ["git", "rev-parse", "--abbrev-ref", "HEAD"],
            stderr=subprocess.DEVNULL,
            text=True
        ).strip()
    except (subprocess.CalledProcessError, FileNotFoundError):
        branch = "unknown"

    try:
        commit = subprocess.check_output(
            ["git", "rev-parse", "--short", "HEAD"],
            stderr=subprocess.DEVNULL,
            text=True
        ).strip()
    except (subprocess.CalledProcessError, FileNotFoundError):
        commit = "unknown"

    return branch, commit


def get_palace_context():
    """Récupère le contexte du palace via mempalace wake-up."""
    try:
        result = subprocess.run(
            ["mempalace", "wake-up"],
            capture_output=True,
            text=True,
            timeout=30
        )
        if result.returncode == 0:
            return result.stdout
    except (subprocess.TimeoutExpired, FileNotFoundError):
        pass
    return None


def get_stack_tags():
    """Récupère les tags technologiques via memverify."""
    try:
        result = subprocess.run(
            ["python3", "scripts/palace/memverify.py"],
            capture_output=True,
            text=True,
            timeout=30,
            cwd=os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        )
        if result.returncode == 0:
            return result.stdout
    except (subprocess.TimeoutExpired, FileNotFoundError):
        pass
    return None


def extract_palace_sections(palace_text):
    """Extrait les sections clés du texte palace."""
    lines = palace_text.split('\n')
    sections = {
        'vision': '',
        'architecture': '',
        'modules': '',
        'stack': '',
    }

    current_section = None
    for line in lines:
        if 'Vision' in line or 'vision' in line.lower():
            current_section = 'vision'
        elif 'Structure' in line or 'Monorepo' in line:
            current_section = 'architecture'
        elif 'Modules' in line or 'Bounded Contexts' in line:
            current_section = 'modules'
        elif 'Stack' in line or 'technique' in line:
            current_section = 'stack'

        if current_section and line.strip():
            sections[current_section] += line + '\n'

    return sections


def parse_memverify_tags(memverify_text):
    """Parse les tags technologiques du output memverify."""
    tags = {
        'confirmed': [],
        'missing': [],
    }

    for line in memverify_text.split('\n'):
        if '✅' in line:
            tag = line.split('✅')[1].strip().split()[0]
            tags['confirmed'].append(tag)
        elif '❌' in line:
            tag = line.split('❌')[1].strip().split()[0]
            tags['missing'].append(tag)

    return tags


def generate_blog_content(palace_text, memverify_text, branch, commit):
    """Génère le contenu markdown du blog."""
    today = datetime.now().strftime("%Y-%m-%d")
    sections = extract_palace_sections(palace_text)
    tags = parse_memverify_tags(memverify_text) if memverify_text else {'confirmed': [], 'missing': []}

    blog = f"""# Palace Blog — {today} · `{branch}`

**Commit** : {commit}

---

## 📖 Vision du Projet

PalaceWork est un task manager personnel et collaboratif où chaque tâche porte son palace de connaissance.

**Caractéristiques clés** :
- Dual-store : PostgreSQL (le *quoi*) + MemPalace (le *pourquoi*)
- Architecture modulaire HMVC avec Bounded Contexts
- OAuth2 + JWT authentication
- State Machine pour lifecycle des tâches
- Queue worker asynchrone
- Knowledge graph intégré (pgvector embeddings)

---

## 🏗️ Architecture

### Stack Technique

"""

    if tags['confirmed']:
        blog += "**Confirmé dans le palace** :\n"
        for tag in tags['confirmed']:
            blog += f"- ✅ {tag}\n"

    if tags['missing']:
        blog += "\n**Déclaré mais absent du palace** :\n"
        for tag in tags['missing']:
            blog += f"- ❌ {tag}\n"

    blog += f"""

### Monorepo Structure

```
PalaceWork/
├── backend/           # CodeIgniter 3 & 4
│   ├── app/
│   ├── application/modules/
│   │   ├── Auth/
│   │   ├── Task/
│   │   ├── Project/
│   │   ├── Shared/
│   │   ├── Palace/
│   │   └── Org/
│   └── tests/
├── frontend/          # React + TanStack Query
├── palace/            # MemPalace lib (MIT v3.3.0)
├── docker/            # Docker Compose setup
├── scripts/palace/    # Mining & verification tools
└── Makefile
```

---

## 🎯 Modules (Bounded Contexts)

| Module | Status | Role |
|--------|--------|------|
| **Shared** | ✅ Ready | Base entities, repositories, kernel |
| **Auth** | ⏳ In Progress | OAuth2, JWT, rate limiting (current branch) |
| **Task** | ⏳ In Progress | State Machine, lifecycle |
| **Project** | ⏳ In Progress | Grouping, access control |
| **Palace** | 🔜 Next | MemPalace HTTP adapter, sync |
| **Org** | 🔜 Later | Multi-tenant, RLS |

---

## 🚀 Roadmap (7 phases)

1. **Fondation** — OAuth2, rate limiting, CI, module split
2. **MVP Perso** — Tasks, State Machine, tags, comments
3. **Phase PalaceWork** — MemPalace adapter, Queue Worker, knowledge layer
4. **Pivot SaaS** — Multi-tenant, invitations, PostgreSQL RLS
5. **Collaboration** — Activity Feed, SSE notifications, sharing
6. **Visualisation** — Graph (D3.js / Cytoscape), knowledge browsing
7. **Migration CI4** — Apprentissage incrémental CI3→CI4

---

## ✅ Conventions Clés

### Git & Commits
- PRs vers `develop` (pas `main`)
- Commits conventionnels : `feat:`, `fix:`, `refactor:`
- **Jamais** `Co-Authored-By`
- **Jamais** committer `.gitignore`

### Code & Architecture
- Plan avant implémentation (attendre validation)
- Une commande bash à la fois (pas de `&&`)
- Karpathy guidelines : simple > clever, minimal deps
- Delete code aggressively

### Testing (Pyramide TDD)
- Unit tests : 0 DB (mocks)
- Integration tests : vraie DB PostgreSQL
- E2E tests : full stack
- **Règle AUTH-INT001** : tout service mocké → ≥1 test intégration réel

### Réponses & Mémoire
- Pas de résumé en fin de réponse
- Opérations mémoire silencieuses

---

## 📊 Métriques Palace

| Métrique | Valeur |
|----------|--------|
| Fichiers minés | 125 |
| Drawers créés | 912 |
| Rooms | 6 (skills, backend, general, testing, documentation, scripts) |
| Technologies détectées | {len(tags['confirmed'])} ✅ + {len(tags['missing'])} ❌ |
| Wake-up context | ~1393 tokens (L0+L1) |

---

## 🔧 Prochaines étapes

**Branche actuelle** : `{branch}`

### Immédiat
1. ✅ **Authentication OAuth2** (en cours)
   - Implémenter `/auth/authorize`, `/auth/token`, `/auth/revoke`
   - Rate limiting SEC003
   - Tests intégration AUTH-INT001

2. **State Machine Task**
   - Transitions : draft → open → done → archived
   - Events : TaskCreated, TaskUpdated, TaskCompleted

3. **MemPalace HTTP Adapter**
   - Lien automatique task.uuid ↔ palace drawer
   - Embeddings sync

### Phase 2
- Queue Worker implémentation
- Migration CI3 → CI4 strategy
- Frontend React setup

---

## 📝 Utilisation du Palace

**Avant de coder** :
```bash
mempalace search "ce que tu cherches"
```

**Exemples** :
- `mempalace search "authentication"` — OAuth2 pattern
- `mempalace search "state machine"` — Task lifecycle
- `mempalace search "testing"` — Pyramide TDD
- `mempalace search "migrations"` — Schema evolution

---

*Blog généré automatiquement par `scripts/palace/blog_generator.py`*
*Branch: {branch} · Commit: {commit}*
*Date: {today}*
"""

    return blog


def main():
    """Génère le blog et le sauvegarde."""
    branch, commit = get_git_info()
    palace_text = get_palace_context()
    memverify_text = get_stack_tags()

    if not palace_text:
        print("⚠️  Impossible de récupérer le contexte palace.", file=sys.stderr)
        sys.exit(1)

    blog_content = generate_blog_content(palace_text, memverify_text, branch, commit)

    # Créer le dossier docs/sessions s'il n'existe pas
    docs_dir = "docs/sessions"
    os.makedirs(docs_dir, exist_ok=True)

    # Générer le nom de fichier avec la date
    today = datetime.now().strftime("%Y-%m-%d")
    blog_path = os.path.join(docs_dir, f"palace-blog-{today}.md")

    # Écrire le fichier
    with open(blog_path, "w", encoding="utf-8") as f:
        f.write(blog_content)

    print(f"✅  Blog généré : {blog_path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
