# Assembly Map — MemVerify

## Composants requis

| Composant | Couvert par | Effort | Notes |
|-----------|-------------|--------|-------|
| Lecteur memory store | MemPalace CLI (`mempalace wake-up / search`) | XS | API déjà disponible |
| Extracteur de claims | `extract_session.py` (existant) → étendre | S | Base déjà là |
| Scanner codebase | Python + grep/regex sur `package.json`, `composer.json`, `.env` | S | Stdlib suffisant |
| Git log reader | `git log --since` via subprocess | XS | Trivial |
| Confidence scorer | Règles déterministes (phase 1) puis LLM (phase 2) | M | Phase 1 sans LLM |
| Rapport MD/JSON | Format existant dans `extract_session.py` | XS | Déjà le pattern |
| Pre-session hook | Claude Code hooks (`~/.claude/settings.json`) | XS | Docs disponibles |
| Contrat mémoire YAML | Parser YAML + moteur de règles | S | À construire |
| CI integration | Bash wrapper + exit code non-zéro si LOW facts | XS | Trivial |

---

## Score d'assemblage

**~65% couvert par briques existantes**

### Déjà disponible
- MemPalace CLI (lecture des drawers)
- `extract_session.py` (parsing JSONL, structure de rapport)
- git subprocess pattern
- Claude Code hooks

### À construire
- Extracteur de claims depuis contenu drawer (regex → puis LLM)
- Moteur de cross-référencement codebase
- Confidence scorer
- Contrat mémoire YAML

---

## Chemin MVP (2-3 jours)

```
Jour 1 : Fact extractor (regex technos + frameworks depuis drawers)
         + Codebase scanner (package.json / composer.json)
         → output JSON : [{ claim, source_drawer, found_in_code: bool }]

Jour 2 : Confidence scorer + rapport Markdown
         + CLI `memverify <path-to-palace>`

Jour 3 : Pre-session hook + `.memcontracts.yml` minimal
         + Tests sur PalaceWork CI3 comme projet pilote
```
