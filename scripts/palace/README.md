# Palace Scripts

Outils pour gérer la knowledge graph MemPalace du projet ci4.

## 🗂️ Fichiers

| Script | Description | Usage |
|--------|-------------|-------|
| **`palace_blog_auto.py`** | **[NOUVEAU]** Automatise mine → extract → blog → verify | `python3 palace_blog_auto.py` |
| `blog_generator.py` | Génère un `.md` de blog après `mempalace mine` | `python3 blog_generator.py` |
| `memverify.py` | Vérifie les tags technologiques vs palace | `python3 memverify.py` |
| `extract_session.py` | Extrait une session JSONL en résumé blog | `python3 extract_session.py [--markdown] <file.jsonl>` |

---

## 🚀 Utilisation rapide

### ⚡ NEW: Palace Blog Automation (One Command!)

```bash
# Everything in one: mine → extract → blog → verify
make palace

# Or directly
python3 scripts/palace/palace_blog_auto.py
```

**Output**:
```
✅ Palace mined: 23 files, 147 drawers
✅ Blog generated: docs/sessions/palace-blog-2026-04-24.md
✅ Session extracted: docs/sessions/palace-extract-2026-04-24.md
✅ Tech verified: all tags found
```

---

### 1️⃣ Générer un blog après mining (classique)

```bash
# Option A : Via Makefile (recommandé)
make palace

# Option B : Commandes manuelles (legacy)
mempalace mine .
python3 scripts/palace/blog_generator.py

# Option C : Juste générer le blog (palace déjà miné)
python3 scripts/palace/blog_generator.py
```

### 2️⃣ Vérifier les tags technologiques

```bash
# Option A : Via Makefile
make palace-verify

# Option B : Direct
python3 scripts/palace/memverify.py
```

### 3️⃣ Chercher dans le palace

```bash
# Option A : Via Makefile (interactif)
make palace-search

# Option B : Direct avec query
mempalace search "authentication"
mempalace search "state machine"
mempalace search "testing"
```

---

## 🤖 palace_blog_auto.py [NEW]

### Description

**One-command automation** for entire palace blog workflow:
1. Mine palace (detects all changes)
2. Extract latest session (JSONL → markdown)
3. Generate blog (palace-blog-YYYY-MM-DD.md)
4. Verify tech stack (memverify checks)

### Quick Start

```bash
# Full automation
make palace
# or
python3 scripts/palace/palace_blog_auto.py

# Extract session only
make palace-extract
python3 scripts/palace/palace_blog_auto.py --extract

# Verify tech stack only
make palace-verify
python3 scripts/palace/palace_blog_auto.py --verify
```

### Output Files

After running `make palace`:

```
docs/sessions/
├─ palace-blog-2026-04-24.md       (main blog: vision + architecture)
├─ palace-extract-2026-04-24.md    (session summary: decisions + commands)
└─ palace-analysis-2026-04-24.md   (optional: deep architecture analysis)
```

### Features

- ✅ **Auto-mine**: Detects new/modified files since last mine
- ✅ **Auto-extract**: Converts latest JSONL session to blog
- ✅ **Auto-blog**: Generates vision + architecture + roadmap
- ✅ **Auto-verify**: Confirms tech stack matches palace
- ✅ **Error handling**: Clear error messages if any step fails
- ✅ **Timestamped**: Each blog dated (YYYY-MM-DD)

### Command Reference

```bash
# Full workflow
python3 scripts/palace/palace_blog_auto.py
  └─ mines palace + extracts + generates blog + verifies

# Extract only (session JSONL → markdown)
python3 scripts/palace/palace_blog_auto.py --extract
  └─ converts latest Claude Code session to blog format

# Verify only (check tech stack)
python3 scripts/palace/palace_blog_auto.py --verify
  └─ runs memverify to confirm declared techs are in palace

# Help
python3 scripts/palace/palace_blog_auto.py --help-full
```

### Integration with CCPM

Skill: `.claude/skills/palace-blog-automation.md`

Auto-activates:
- `/palace-blog` command
- After `/ccpm:done` (optional)
- Manual workflow trigger

### Examples

**After completing a feature:**
```bash
git commit -m "feat(auth): add OAuth2 password grant"
make palace
# → Generates palace-blog-2026-04-24.md documenting this session
```

**Before code review:**
```bash
make palace
# → Latest blog ready for review in docs/sessions/
```

**Weekly architecture sync:**
```bash
make palace-verify
# → Confirms all declared technologies are present
```

---

## 📖 blog_generator.py

### Description

Génère automatiquement un résumé `.md` du projet (blog) à partir du palace minié.

### Features

- ✅ Extrait automatiquement branche git et commit
- ✅ Récupère la vision et l'architecture depuis le palace
- ✅ Affiche les tags technologiques (confirmés vs manquants)
- ✅ Inclut la roadmap et les modules
- ✅ Liste les conventions clés
- ✅ Affiche les prochaines étapes

### Output

```
docs/sessions/palace-blog-YYYY-MM-DD.md
```

### Hooks (automatisé)

Le script s'exécute automatiquement après `mempalace mine` via le hook dans `mempalace.yaml`.

Pour forcer une génération manuelle :

```bash
python3 scripts/palace/blog_generator.py
```

### Dépendances

- `mempalace` (pour wake-up)
- `git` (pour branch/commit)
- `memverify.py` (pour stack tags)

---

## 📋 memverify.py

### Description

Vérifie que les tags technologiques déclarés dans `.memtags.yml` correspondent à ce qui est réellement détecté dans le palace.

### Fonctionnalités

| Feature | Commande |
|---------|----------|
| Vérification | `python3 memverify.py` |
| Initialiser tags | `python3 memverify.py --init` |
| Promouvoir tag (pending → declared) | `python3 memverify.py --promote <tag>` |
| Rejeter tag | `python3 memverify.py --dismiss <tag>` |

### Output

```
memverify — ci3-tasks
========================================

  ✅  oauth2
  ✅  postgresql
  ✅  jwt
  ❌  composer  ← absent du palace
  ❌  github-actions  ← absent du palace

→ 2 tag(s) déclarés absents du palace.
```

### Configuration

Éditer `.memtags.yml` :

```yaml
project: ci4
stack_declared:
  - oauth2
  - postgresql
  - php
  - codeigniter
stack_pending: []
stack_ignored:
  - jquery  # ancien, not used anymore
```

---

## 🔍 extract_session.py

### Description

Extrait les décisions, impasses et commandes Bash d'une session JSONL (conversation Claude Code).

### Usage

```bash
# Générer JSON
python3 extract_session.py session.jsonl

# Générer Markdown blog
python3 extract_session.py --markdown session.jsonl
```

### Features

- Détecte les décisions architecturales
- Détecte les impasses (dead ends) et blocages
- Indexe les commandes Bash exécutées
- Support des chunk markers (`<!-- chunk: <tag> -->`)
- Filtre in-scope vs out-of-scope

### Input

Fichier JSONL contenant une session Claude Code :

```jsonl
{"type":"user", "message":{"content":[...]}, ...}
{"type":"assistant", "message":{"content":[...]}, ...}
...
```

### Output

```markdown
# Session — 2026-04-24 · `feature/auth`

## Décisions
- Chose OAuth2 plutôt que JWT simple car...

## Impasses
- Tenté d'utiliser bshaffer mais configuration complexe...

## Commandes
```bash
composer require bshaffer/oauth2-server-php
```

## Configuration (bloc out-of-scope)
...
```

---

## 🔄 Workflow typique

### Session de développement classique

```bash
# 1. Développer une feature
git checkout -b feature/oauth2

# ... faire du code ...

# 2. Miner le palace et générer le blog
make palace

# ✅ docs/sessions/palace-blog-2026-04-24.md généré

# 3. Vérifier les tags technologiques
make palace-verify

# 4. Committer (optionnel)
git add -A
git commit -m "feat: oauth2 implementation"

# 5. Chercher dans le palace
make palace-search
# → Search query: authentication
```

---

## 📊 Makefile Commands

Depuis la racine du projet :

```bash
make palace              # Mine + génère blog
make blog               # Génère juste le blog
make palace-verify      # Vérif les tags
make palace-search      # Cherche interactif
```

---

## 🐛 Troubleshooting

### ❌ "mempalace: command not found"

```bash
# Installer mempalace
pip install mempalace

# Ou via package manager
apt install mempalace  # Linux
brew install mempalace # macOS
```

### ❌ Blog ne se génère pas

```bash
# Vérifier que mempalace est initialisé
ls mempalace.yaml

# Sinon, initialiser
mempalace init .

# Puis miner
mempalace mine .
```

### ❌ Hook ne s'exécute pas

```bash
# Enregistrer les hooks
mempalace hook --init

# Ou relancer manuellement
python3 scripts/palace/blog_generator.py
```

---

## 📝 Configuration avancée

### Personnaliser blog_generator.py

Éditer la fonction `generate_blog_content()` pour changer le template.

### Ajouter des commands Palace custom

Dans le Makefile :

```makefile
palace-stats:
	@echo "Palace Statistics:"
	@mempalace status
	@wc -l docs/sessions/palace-blog-*.md | tail -1
```

### Automatiser avec cron

```bash
# Générer un blog quotidien
0 8 * * * cd /home/luc/Documents/ci4 && python3 scripts/palace/blog_generator.py

# Miner quotidien
0 9 * * * cd /home/luc/Documents/ci4 && mempalace mine .
```

---

## 📚 Documentation

- **[PALACE_BLOG.md](../PALACE_BLOG.md)** — Système complet de blog
- **[PALACE.md](../PALACE.md)** — Structure du palace (knowledge graph)
- **[mempalace](https://github.com/sov-ai/mempalace)** — Docs officielles

---

## 💡 Tips & Tricks

### Lire le blog généré

```bash
cat docs/sessions/palace-blog-$(date +%Y-%m-%d).md
```

### Chercher une architecture pattern

```bash
mempalace search "state machine"
mempalace search "repository pattern"
mempalace search "bounded context"
```

### Voir les changements du palace

```bash
# Voir les fichiers minés récemment
mempalace status

# Voir la taille du palace
du -sh ~/.mempalace/palace
```

### Exporter le blog pour medium

```bash
# Les fichiers .md peuvent être directement importés dans Medium
# Format : docs/sessions/palace-blog-*.md
```

---

*Scripts MemPalace — v1.0*  
*Partie du système ci4 (PalaceWork Task Manager)*
