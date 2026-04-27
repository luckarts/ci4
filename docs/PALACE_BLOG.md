# Palace Blog System

## Vue d'ensemble

Le **Palace Blog System** génère automatiquement un résumé du projet (`.md` de blog) à chaque fois que `mempalace mine` est exécuté.

### Flux automatisé

```
git commit / mempalace mine
    ↓
mempalace.yaml hook (post_mine)
    ↓
scripts/palace/blog_generator.py
    ↓
docs/sessions/palace-blog-YYYY-MM-DD.md ✅
```

---

## Installation & Configuration

### 1️⃣ Script déjà en place

Le script `scripts/palace/blog_generator.py` existe et est configuré.

### 2️⃣ Hook mempalace activé

La configuration `mempalace.yaml` inclut un hook `post_mine` :

```yaml
hooks:
  post_mine:
    - description: "Generate Palace blog"
      run: "python3 scripts/palace/blog_generator.py"
```

### 3️⃣ Activation du hook

```bash
# Enregistrer le hook auprès de mempalace
mempalace hook --init

# Ou manuellement après chaque mine
mempalace mine /path/to/project
python3 scripts/palace/blog_generator.py
```

---

## Utilisation

### Automatique (après mempalace mine)

```bash
mempalace mine /home/luc/Documents/ci4
# ✅ Palace minée
# ✅ Blog généré automatiquement
```

### Manuel

```bash
python3 scripts/palace/blog_generator.py
# ✅ Blog généré dans docs/sessions/palace-blog-YYYY-MM-DD.md
```

### Via Makefile (optionnel)

```bash
make blog
# Génère le blog
```

---

## Output

### Fichier généré

```
docs/sessions/palace-blog-YYYY-MM-DD.md
```

### Contenu du blog

Le blog contient automatiquement :

1. **En-tête** — Date, branche git, commit
2. **Vision** — Description du projet
3. **Architecture** — Stack technique, structure monorepo
4. **Modules** — Status des Bounded Contexts (✅ Ready, ⏳ In Progress, 🔜 Next)
5. **Roadmap** — 7 phases principales
6. **Conventions** — Git, code, testing, réponses
7. **Métriques** — Fichiers minés, drawers, technologies
8. **Prochaines étapes** — Actions prioritaires
9. **Utilisation Palace** — Commandes mempalace search

### Exemple de titre

```markdown
# Palace Blog — 2026-04-24 · `feature/backend/authentification-oauth2`

**Commit** : a3f5b2c
```

---

## Ce que le script extrait automatiquement

### ✅ Depuis `mempalace wake-up`

- Vision du projet
- Architecture générale
- Stack technique
- Modules et Bounded Contexts
- Conventions

### ✅ Depuis `memverify.py`

- Technologies confirmées (✅)
- Technologies manquantes (❌)

### ✅ Depuis `git`

- Branche actuelle
- Commit courant (short SHA)

### ✅ System

- Date du jour (YYYY-MM-DD)
- Heure de génération

---

## Workflow typique

### Après une session de développement

```bash
# 1. Miner les changements au palace
mempalace mine /home/luc/Documents/ci4

# ✅ Blog généré automatiquement :
# docs/sessions/palace-blog-2026-04-24.md

# 2. Vérifier les tags technologiques
python3 scripts/palace/memverify.py

# 3. Commit les changements (si nécessaire)
git add -A
git commit -m "feat: update palace knowledge graph"

# 4. Lire le blog généré
cat docs/sessions/palace-blog-$(date +%Y-%m-%d).md
```

---

## Intégration avec CI/CD (optionnel)

### GitHub Actions

Ajouter à `.github/workflows/palace.yml` :

```yaml
name: Palace Blog Generation

on:
  push:
    branches:
      - develop
      - main
    paths:
      - 'backend/**'
      - 'frontend/**'
      - 'docs/**'

jobs:
  blog:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-python@v4
        with:
          python-version: '3.10'
      - run: pip install -q mempalace pyyaml
      - run: mempalace mine .
      - run: python3 scripts/palace/blog_generator.py
      - run: |
          git add docs/sessions/palace-blog-*.md
          git commit -m "chore: update palace blog" || true
          git push
```

---

## Fonctionnalités du script

### blog_generator.py

#### Fonctions principales

| Fonction | Rôle |
|----------|------|
| `get_git_info()` | Récupère branche et commit |
| `get_palace_context()` | Lance `mempalace wake-up` |
| `get_stack_tags()` | Lance `memverify.py` et parse output |
| `extract_palace_sections()` | Extrait les sections clés du palace |
| `parse_memverify_tags()` | Parse ✅ et ❌ tags |
| `generate_blog_content()` | Assemble le markdown final |
| `main()` | Orchestre la génération et sauvegarde |

#### Options d'exécution

```bash
# Standard — génère le blog
python3 scripts/palace/blog_generator.py

# Avec debug (voir le output palace)
DEBUG=1 python3 scripts/palace/blog_generator.py
```

---

## Dépendances

- **Python 3.8+**
- **mempalace** — pour wake-up
- **pyyaml** — pour parser mempalace.yaml (optionnel)
- **git** — pour branch/commit info
- **scripts/palace/memverify.py** — pour stack tags

Toutes les dépendances sont disponibles dans l'environnement.

---

## Troubleshooting

### ❌ Hook ne s'exécute pas

```bash
# Vérifier que mempalace.yaml est à jour
grep -A 2 "post_mine" mempalace.yaml

# Enregistrer les hooks
mempalace hook --init

# Relancer manuellement
python3 scripts/palace/blog_generator.py
```

### ❌ Blog génère un fichier vide

```bash
# Vérifier que mempalace wake-up fonctionne
mempalace wake-up --wing ci4 | head -20

# Vérifier que memverify.py fonctionne
python3 scripts/palace/memverify.py

# Vérifier que git est à jour
git status
```

### ❌ Erreur de permissions

```bash
# Rendre le script exécutable
chmod +x scripts/palace/blog_generator.py

# Vérifier l'accès au dossier docs/sessions
ls -la docs/sessions/
```

---

## Personnalisation

### Modifier le template du blog

Éditer la fonction `generate_blog_content()` dans `scripts/palace/blog_generator.py` (lignes 80-180).

### Ajouter des sections

```python
# Dans generate_blog_content(), ajouter une section :
blog += """
## 🎨 Nouvelle Section

Contenu personnalisé...
"""
```

### Changer la fréquence de génération

**Actuellement** : après chaque `mempalace mine`

**Alternatives** :
- Post-commit hook (après git commit)
- Cron job quotidien
- Action GitHub Actions planifiée
- Manually avec `python3 scripts/palace/blog_generator.py`

---

## Fichiers impliqués

| Fichier | Rôle |
|---------|------|
| `scripts/palace/blog_generator.py` | Script de génération |
| `mempalace.yaml` | Configuration hook |
| `docs/sessions/palace-blog-*.md` | Blogs générés |
| `scripts/palace/memverify.py` | Vérification tags (dépendance) |

---

## Exemples de blogs générés

### 2026-04-24 — Feature Auth OAuth2

```
# Palace Blog — 2026-04-24 · `feature/backend/authentification-oauth2`

**Commit** : a3f5b2c

## 📖 Vision du Projet
...

## 🏗️ Architecture
...

## 🎯 Modules (Bounded Contexts)
...

## ✅ Conventions Clés
...

## 📊 Prochaines étapes
...
```

---

## FAQ

**Q: Combien de temps prend la génération ?**  
A: ~3-5 secondes (mempalace wake-up est la part la plus lente)

**Q: Peut-on générer plusieurs blogs par jour ?**  
A: Oui — le script ajoute un timestamp (YYYY-MM-DD). Changer le format si besoin (YYYY-MM-DD HH:MM).

**Q: Comment intégrer avec des blogs en ligne (Medium, Dev.to) ?**  
A: Exporter le `.md` vers ces plateformes manuellement ou via API.

**Q: Quelle est la taille typique d'un blog ?**  
A: ~3-5 KB (Markdown non compressé)

**Q: Peut-on versionner les blogs dans git ?**  
A: Oui — ajouter `docs/sessions/` au `.gitignore` ou les committer selon votre préférence.

---

## Prochaines améliorations

- [ ] Compression du contenu palace pour blogs plus concis
- [ ] Export formaté pour Medium / Dev.to
- [ ] Graphiques intégrés (architecture, timeline)
- [ ] Comparaison inter-blogs (delta depuis dernière mine)
- [ ] Statistiques (fichiers changés, modules affectés)

---

*Documentation du Palace Blog System — v1.0*  
*Généré avec mempalace + scripts/palace/blog_generator.py*
