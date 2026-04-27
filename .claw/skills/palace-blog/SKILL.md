---
name: palace-blog
description: Automatise mining MemPalace + extraction session + génération devlog. Fallback git log si pas de JSONL. One-command workflow (make palace).
---

# Palace Blog Automation

> **One command**: `make palace` — Mine MemPalace, extrait la session (JSONL ou git log), génère le devlog, vérifie la stack tech.

Workflow complet :
1. **Mine** — `mempalace mine .` (détecte tous les changements)
2. **Extrait** — `extract_session.py` sur le dernier JSONL Claude Code, **ou fallback `--from-git`** si aucun JSONL trouvé
3. **Blog** — `blog_generator.py` (vue architecture + roadmap, inchangé)
4. **Vérifie** — `memverify.py` (confirmation tech stack)

---

## Quand l'utiliser

**Après chaque feature complétée** :
```bash
git commit -m "feat(auth): add OAuth2 password grant"
make palace
# → blog/sessions/devlog-2026-04-25.md généré automatiquement
```

**Extract seulement (fin de session rapide)** :
```bash
make palace-extract
```

**Vérification stack uniquement** :
```bash
make palace-verify
```

---

## Output files

```
blog/sessions/
├─ devlog-YYYY-MM-DD.md    ← Devlog de session (décisions + impasses + commits)
└─ INDEX.md                ← Index auto-mis à jour à chaque run

docs/sessions/
└─ palace-blog-YYYY-MM-DD.md  ← Vue architecture (vision + stack + roadmap)
```

### devlog-YYYY-MM-DD.md (format)

Généré depuis JSONL **ou git log** :
- **Décisions** — Pourquoi les choix ont été faits
- **Impasses** — Dead-ends et bugs bloquants
- **Commandes** — Bash commands exécutées (JSONL seulement)
- **Commits de la session** — Tableau hash / type / sujet
- **À ne pas oublier** — Impasses avec solution documentée

---

## Sources de données

### Source 1 : JSONL Claude Code (automatique)
Le fichier `~/.mempalace/palace/ci4/**/*.jsonl` le plus récent est utilisé.
Extrait les vraies décisions et impasses depuis la conversation.

### Source 2 : Git log (fallback automatique)
Si aucun JSONL n'existe, `extract_session.py --from-git --commits 15` est lancé.
Parse les corps de commits pour détecter décisions (tokens : "solution:", "fix:", "parce que"…)
et impasses (tokens : "⚠️", "issue:", "failed", "requires debugging"…).

```bash
# Forcer le mode git (utile pour recréer un devlog a posteriori)
python3 scripts/palace/extract_session.py --markdown --from-git --commits 20
```

---

## Usage détaillé

### Full automation
```bash
make palace
# ou
python3 scripts/palace/palace_blog_auto.py
```

### Extract / devlog seulement
```bash
make palace-extract
# ou
python3 scripts/palace/palace_blog_auto.py --extract
```

### Verify seulement
```bash
make palace-verify
# ou
python3 scripts/palace/palace_blog_auto.py --verify
```

### Git fallback manuel
```bash
# Générer un devlog sur les 20 derniers commits
python3 scripts/palace/extract_session.py --markdown --from-git --commits 20 > blog/sessions/devlog-$(date +%Y-%m-%d).md
```

---

## Files & Structure

```
scripts/palace/
├─ palace_blog_auto.py    ← Orchestrateur principal
├─ blog_generator.py      ← Architecture overview (palace wake-up)
├─ extract_session.py     ← Extraction JSONL + fallback git
├─ memverify.py           ← Vérification tech stack
└─ README.md

.claw/skills/palace-blog/
└─ SKILL.md               ← Ce fichier

blog/sessions/
├─ devlog-*.md            ← Devlogs de session
└─ INDEX.md               ← Index auto-mis à jour

Makefile
├─ make palace            ← Full automation
├─ make palace-extract    ← Devlog seulement
├─ make palace-verify     ← Verify seulement
└─ make palace-search     ← Search interactif
```

---

## INDEX.md — Mise à jour automatique

À chaque run de `palace_blog_auto.py --extract`, `INDEX.md` est mis à jour :
```markdown
### abc1234 — feat(auth): add OAuth2 token revocation
- **Date** : 2026-04-25
- **Branch** : feature/backend/authentification-oauth2
- **Source** : git log
- **Fichier** : [devlog-2026-04-25.md](devlog-2026-04-25.md)
```

---

## Palace Structure (ci4)

```
Wings/ci4/
├─ backend/        (Auth/, Task/, Project/, Shared/, Palace/, Org/)
├─ testing/
├─ documentation/
├─ skills/
└─ scripts/
```

---

## Troubleshooting

### ❌ "mempalace: command not found"
```bash
pip install mempalace
```

### ❌ "No JSONL session found"
Normal si `~/.mempalace/palace/ci4/` est vide — le fallback git prend le relais automatiquement.

### ❌ Devlog vide (0 décisions, 0 impasses en mode git)
Les corps de commits sont trop courts. Écrire des corps détaillés dans les commits :
```
feat(auth): add token revocation

Issue: PostgreSQL returns booleans as "t"/"f" strings.
Solution: Compare with === 't' instead of loose cast.
```

### ❌ Blog architecture non généré
```bash
python3 scripts/palace/blog_generator.py
# Requiert mempalace + palace initialisé
```

---

## Safety

✅ ALWAYS :
- Lire le devlog généré — vérifier l'absence de secrets
- Committer les devlogs (`git add blog/sessions/`)

❌ NEVER :
- Modifier les drawers palace manuellement
- Publier des clés/tokens dans les corps de commits

---

**Status**: ✅ Production Ready  
**Use**: `make palace` — fallback git automatique si pas de JSONL
