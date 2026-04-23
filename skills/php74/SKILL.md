---
name: php74
description: "PHP 7.4 coding skill for VvvebCMS - conventions, patterns, and compatibility rules"
argument-hint: [controller|model|plugin|component|helper]
triggers:
  - php code
  - write php
  - create controller
  - create model
  - create plugin
  - php 7.4
---

# PHP 7.4 Skill - VvvebCMS

Guide complet pour ecrire du code PHP 7.4 compatible avec les conventions et design patterns de VvvebCMS.

## Regles de compatibilite PHP 7.4

### Fonctionnalites AUTORISEES (PHP <= 7.4)

```php
// Typed properties (7.4)
protected string $type = 'post';
protected int $site_id = 1;

// Arrow functions (7.4)
$ids = array_map(fn($item) => $item['id'], $items);

// Null coalescing assignment (7.4)
$options['limit'] ??= 10;

// Spread operator in arrays (7.4)
$merged = ['default' => true, ...$options];

// Null coalescing operator (7.0)
$name = $data['name'] ?? 'default';

// Spaceship operator (7.0)
usort($items, fn($a, $b) => $a['sort'] <=> $b['sort']);

// Group use declarations (7.0)
use Vvveb\System\{Cache, Session, Sites};
```

### Fonctionnalites INTERDITES (PHP 8.0+)

```php
// INTERDIT - Union types (8.0)
function get(int|string $id) {}          // NON
function get($id) {}                      // OUI

// INTERDIT - Named arguments (8.0)
array_slice(array: $arr, offset: 1);     // NON
array_slice($arr, 1);                     // OUI

// INTERDIT - Match expression (8.0)
match($status) { 'active' => 1 };        // NON
switch($status) { case 'active': ... }   // OUI

// INTERDIT - Nullsafe operator (8.0)
$user?->getAddress()?->getCity();         // NON
$user ? $user->getAddress() : null;       // OUI
isset($user) ? $user->getAddress() : null; // OUI

// INTERDIT - Constructor promotion (8.0)
public function __construct(private int $id) {} // NON

// INTERDIT - Enums (8.1)
enum Status { case Active; }              // NON
const STATUS_ACTIVE = 'active';           // OUI

// INTERDIT - Readonly properties (8.1)
public readonly string $name;             // NON

// INTERDIT - Fibers (8.1)
// INTERDIT - Intersection types (8.1)
// INTERDIT - First class callable syntax (8.1)
$fn = strlen(...);                        // NON
$fn = 'strlen';                           // OUI

// INTERDIT - DNF types (8.2)
// INTERDIT - #[\Override] (8.3)
```

### Exception: #[\AllowDynamicProperties]

VvvebCMS utilise `#[\AllowDynamicProperties]` pour compatibilite PHP 8.2+ sur certaines classes de base. C'est le seul attribut PHP 8.0+ tolere, et uniquement sur les classes qui en ont besoin.

---

## Conventions de nommage VvvebCMS

### Fichiers
| Type | Convention | Exemple |
|------|-----------|---------|
| Controller | snake_case | `app/controller/content/post.php` |
| SQL model | snake_case | `app/sql/mysqli/post.sql` |
| System class | snake_case (from camelCase) | `system/core/front_controller.php` |
| Plugin | kebab-case (dossier) | `plugins/mon-plugin/plugin.php` |

### Code
| Element | Convention | Exemple |
|---------|-----------|---------|
| Classes | PascalCase | `FrontController`, `PostSQL` |
| Methodes | camelCase | `getModuleName()`, `setStatus()` |
| Fonctions globales | snake_case | `array_insert_before()`, `clean_url()` |
| Variables | snake_case | `$post_id`, `$language_id`, `$site_data` |
| Constantes | SCREAMING_SNAKE | `DB_ENGINE`, `DIR_ROOT`, `SITE_ID` |
| Colonnes BDD | snake_case | `post_id`, `language_id`, `admin_id` |
| Namespaces | PascalCase | `Vvveb\Controller\Content` |

### Visibilite des methodes

VvvebCMS omet souvent le mot-cle `public` sur les methodes d'action des controllers :

```php
// Style VvvebCMS - methodes d'action sans visibilite explicite
function index() { }
function save() { }

// Proprietes avec visibilite
protected $type = 'post';
```

---

## Design Patterns VvvebCMS

### 1. Front Controller Pattern

Point d'entree unique qui dispatche vers les controllers.

```
HTTP Request → public/index.php → index.php → FrontController::dispatch()
                                                      ↓
                                              Routes::match($uri)
                                                      ↓
                                              FrontController::redirect($module, $action)
                                                      ↓
                                              FrontController::call() → Controller->$action()
```

**Implementation** : `system/core/front_controller.php`

```php
namespace Vvveb\System\Core;

class FrontController {
    static function dispatch() {
        // 1. Parse URI
        // 2. Match route
        // 3. Sanitize module/action avec regex [a-zA-Z_/0-9\-]
        // 4. redirect($module, $action)
    }

    static function redirect($module, $action) {
        // Resout le fichier controller depuis le module
        // "content/post" → app/controller/content/post.php
    }

    static function call($controller_file, $action) {
        // 1. include base.php
        // 2. include controller
        // 3. new Controller()
        // 4. DI: request, response, view, session
        // 5. controller->init()
        // 6. controller->$action()
    }
}
```

### 2. Template Method Pattern (Controllers)

Les controllers heritent de classes de base qui definissent le squelette des operations. Les sous-classes redefinissent les etapes specifiques.

```php
// Classe de base definit le squelette
namespace Vvveb\Controller;

class Edit extends Base {
    protected $type;
    protected $object;
    protected $module;

    function save() {
        // 1. Valide les donnees
        // 2. Prepare le modele SQL
        // 3. Appelle set() ou add()
        // 4. Gere le resultat
        // Template: les sous-classes definissent $type, $object, $module
    }

    function delete() {
        // Squelette commun de suppression
    }
}

// Sous-classe personnalise les proprietes
namespace Vvveb\Controller\Content;

class Post extends Edit {
    protected $type = 'post';
    protected $object = 'post';
    protected $module = 'content/post';

    function index() {
        // Action specifique au post
    }

    function save() {
        // Peut enrichir le comportement parent
        parent::save();
        return $this->index();
    }
}
```

**Hierarchie des controllers :**

```
Base (request, response, view, session, global)
 ├── Edit (CRUD: save, delete)
 │    ├── Post
 │    ├── Page
 │    └── Product
 ├── Listing (pagination, filtres, tri)
 │    ├── Posts
 │    ├── Pages
 │    └── Products
 └── Controllers specifiques
      ├── Index
      ├── Search
      └── Error404
```

### 3. Dependency Injection (Property Injection)

VvvebCMS injecte les dependances via les proprietes, pas le constructeur.

```php
// FrontController::call() injecte :
$controller->request  = $request;    // Objet Request
$controller->response = $response;   // Objet Response
$controller->view     = $view;       // Objet View
$controller->session  = $session;    // Objet Session

// Utilisation dans le controller
class Post extends Base {
    function index() {
        $post_id = $this->request->get['post_id'] ?? 0;
        $this->view->set(['post' => $data]);
        $this->response->setType('html');
    }
}
```

### 4. Singleton Pattern (DB, Cache, Session)

Les services systeme utilisent le pattern Singleton.

```php
namespace Vvveb\System;

class Db {
    private static $instance = null;

    static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Utilisation
    // $db = Db::getInstance();
}
```

**Singletons dans VvvebCMS :**
- `Db::getInstance()` - connexion base de donnees
- `Cache::getInstance()` - cache objet
- `Session` - session PHP

### 5. Factory Pattern (SQL Models)

La fonction `model()` est une factory qui instancie les modeles SQL.

```php
// Factory helper
function model($name) {
    // Resout 'post' → Vvveb\Sql\PostSQL
    // Auto-genere la classe depuis post.sql si necessaire
    return new $class();
}

// Utilisation dans un controller
$posts = model('post');
$post  = $posts->get(['post_id' => $id] + $this->global);
$list  = $posts->getAll(['limit' => 10] + $this->global);
```

### 6. Code Generation Pattern (SQL-P → PHP)

VvvebCMS genere automatiquement les classes Model PHP depuis des fichiers SQL.

```
app/sql/mysqli/post.sql  →  Sqlp::compile()  →  storage/model/app/post.mysqli.php
```

**Fichier SQL-P :**
```sql
CREATE PROCEDURE get(
    IN post_id INT,
    IN slug CHAR,
    IN language_id INT,
    OUT fetch_row,
)
BEGIN
    SELECT _.*, pd.*
    FROM post AS _
    LEFT JOIN post_description AS pd ON pd.post_id = _.post_id
    WHERE _.post_id = :post_id
    AND pd.language_id = :language_id
END

CREATE PROCEDURE getAll(
    IN limit INT,
    IN start INT,
    OUT fetch_all,
)
BEGIN
    SELECT _.*, pd.name
    FROM post AS _
    LEFT JOIN post_description AS pd ON pd.post_id = _.post_id
    LIMIT :limit OFFSET :start
END
```

**Classe PHP generee :**
```php
namespace Vvveb\Sql;

class PostSQL {
    function get($options = []) {
        // Prepared statement avec binding de types
        // Retourne une ligne (fetch_row)
    }

    function getAll($options = []) {
        // Prepared statement avec binding de types
        // Retourne toutes les lignes (fetch_all)
    }
}
```

**Regeneration automatique :** si `filemtime(.sql) > filemtime(.php)`

### 7. Observer Pattern (Event System)

Systeme d'evenements decouple pour les plugins.

```php
namespace Vvveb\System;

class Event {
    static function on($event, $callback, $priority = 0) {
        // Enregistre un listener
    }

    static function trigger($event, &...$args) {
        // Declenche tous les listeners
    }

    static function off($event, $callback = null) {
        // Retire un listener
    }
}

// Dans un plugin
Event::on('post.save.before', function(&$data) {
    // Modifier les donnees avant sauvegarde
    $data['slug'] = clean_url($data['title']);
});

Event::on('page.render.after', function(&$html) {
    // Modifier le HTML avant envoi
});
```

**Evenements courants :**
| Evenement | Quand |
|-----------|-------|
| `*.save.before` | Avant sauvegarde d'une entite |
| `*.save.after` | Apres sauvegarde |
| `*.delete.before` | Avant suppression |
| `page.render.after` | Apres rendu de la page |
| `plugin.activate` | Activation d'un plugin |
| `plugin.deactivate` | Desactivation d'un plugin |

### 8. Strategy Pattern (DB Drivers & Cache Drivers)

Les drivers de BDD et de cache sont interchangeables.

```
DB Drivers:              Cache Drivers:
├── mysqli               ├── file (defaut)
├── pgsql                └── apcu
└── sqlite

SQL files par driver:
app/sql/mysqli/post.sql
app/sql/pgsql/post.sql
app/sql/sqlite/post.sql
```

Le driver est selectionne via `DB_ENGINE` et les fichiers SQL correspondants sont charges automatiquement.

### 9. Composite Pattern (Vtpl Template Engine)

Le moteur de templates compose des fragments HTML via XPath.

```php
// Template .tpl utilise des selecteurs CSS/XPath
// article[data-v-post]
//   h1 = $post.title
//   .content = $post.content
//   img@src = $post.image

// Le moteur Vtpl :
// 1. Charge le HTML du theme (DOMDocument)
// 2. Parse les .tpl (selecteurs XPath)
// 3. Lie les donnees
// 4. Compile en PHP
// 5. Cache dans storage/compiled-templates/
```

### 10. Decorator Pattern (Traits)

Les traits enrichissent les controllers avec des fonctionnalites transversales.

```php
namespace Vvveb\Controller\Content;

class Post extends Edit {
    use TaxonomiesTrait;     // Gestion categories/tags
    use AutocompleteTrait;   // Recherche autocomplete
    use SitesTrait;          // Gestion multisite
    use MediaTrait;          // Upload/gestion media

    // Les traits ajoutent des methodes sans modifier la hierarchie
}
```

### 11. Registry Pattern (Global Data)

Les controllers partagent un etat global via `$this->global`.

```php
// Base controller initialise les donnees globales
class Base {
    protected $global = [];

    function init() {
        $this->global = [
            'site_id'     => SITE_ID,
            'language_id' => $this->session->get('language_id') ?? 1,
            'admin_id'    => $this->session->get('admin_id') ?? 0,
            'user_id'     => $this->session->get('user_id') ?? 0,
        ];
    }
}

// Utilisation dans les requetes SQL
$options = ['post_id' => $id] + $this->global;
$post = model('post')->get($options);
// → SELECT ... WHERE post_id = :post_id AND language_id = :language_id
```

### 12. Two-Level Cache Pattern

```
Requete HTTP
     ↓
Page Cache (Niveau 1 - avant bootstrap)
     │
     ├── HIT → HTML statique (ultra rapide)
     │
     └── MISS ↓
         Object Cache (Niveau 2 - pendant execution)
              │
              ├── file (storage/cache/)
              └── apcu (memoire partagee)
```

**Gestion des stampedes :**
- Fichier `.new` cree pendant la regeneration
- Les requetes suivantes attendent max 10 secondes
- Apres 10s, regeneration forcee

---

## Templates de code

### Nouveau Controller (CRUD)

```php
<?php

/**
 * Vvveb
 *
 * Copyright (C) 2022  Ziadin Givan
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace Vvveb\Controller\Content;

use function Vvveb\__;

class Post extends \Vvveb\Controller\Edit {
    protected $type   = 'post';
    protected $object = 'post';
    protected $module = 'content/post';

    function index() {
        $post_id = $this->request->get['post_id'] ?? 0;

        if ($post_id) {
            $post = model('post')->get(['post_id' => $post_id] + $this->global);

            if (! $post) {
                return $this->notFound();
            }

            $this->view->set(['post' => $post]);
        }
    }

    function save() {
        parent::save();

        return $this->index();
    }
}
```

### Nouveau Controller (Listing)

```php
<?php

namespace Vvveb\Controller\Content;

use function Vvveb\__;

class Posts extends \Vvveb\Controller\Listing {
    protected $type        = 'post';
    protected $module      = 'content/posts';
    protected $controller  = 'content/post';

    function index() {
        parent::index();
    }
}
```

### Nouveau Plugin

```php
<?php

/**
 * Plugin Name: Mon Plugin
 * Description: Description du plugin
 * Version: 1.0
 * Author: Auteur
 */

namespace Vvveb\Plugins\MonPlugin;

use Vvveb\System\Event;

if (! defined('V_DIR')) {
    die('Direct access not allowed!');
}

// Enregistrer les events
Event::on('post.save.before', __NAMESPACE__ . '\onPostSave');

function onPostSave(&$data) {
    // Logique du plugin
    $data['modified_at'] = date('Y-m-d H:i:s');
}
```

### Nouveau fichier SQL-P

```sql
-- post_custom.sql

CREATE PROCEDURE getCustom(
    IN post_id INT,
    IN site_id INT,
    IN language_id INT,
    OUT fetch_row,
)
BEGIN
    SELECT _.*, pd.name, pd.slug
    FROM post AS _
    LEFT JOIN post_description AS pd
        ON pd.post_id = _.post_id
        AND pd.language_id = :language_id
    WHERE _.post_id = :post_id
    AND _.site_id = :site_id
END

CREATE PROCEDURE getAllCustom(
    IN site_id INT,
    IN language_id INT,
    IN limit INT,
    IN start INT,
    OUT fetch_all,
    OUT num_rows,
)
BEGIN
    SELECT _.*, pd.name
    FROM post AS _
    LEFT JOIN post_description AS pd
        ON pd.post_id = _.post_id
        AND pd.language_id = :language_id
    WHERE _.site_id = :site_id
    ORDER BY _.created_at DESC
    LIMIT :limit OFFSET :start
END
```

---

## Securite (obligatoire)

### Validation des entrees

```php
// Toujours filtrer les entrees utilisateur
$post_id = \Vvveb\filter('/[0-9]+/', $this->request->get['post_id'] ?? '', 10);
$slug    = \Vvveb\filter('/[a-zA-Z0-9\-]+/', $this->request->get['slug'] ?? '', 255);

// Utiliser le validateur pour les formulaires
$validator = new \Vvveb\System\Validator(['post']);
$errors    = $validator->validate($this->request->post);
```

### Prevention XSS

```php
// Echapper les sorties
\Vvveb\escHtml($html);     // htmlspecialchars() ENT_QUOTES UTF-8
\Vvveb\escAttr($attr);     // Attributs HTML
\Vvveb\escUrl($url);       // URLs
```

### Prevention SQL Injection

```php
// TOUJOURS utiliser les modeles SQL-P avec prepared statements
// JAMAIS de SQL brut dans les controllers
$post = model('post')->get(['post_id' => $post_id] + $this->global); // OUI
$db->query("SELECT * FROM post WHERE id = $post_id");                 // NON JAMAIS
```

### Securite des fichiers

```php
// Toujours sanitiser les noms de fichiers
$filename = \Vvveb\sanitizeFileName($uploaded_name);
```

---

## Checklist avant commit

- [ ] Compatible PHP 7.4 (pas de syntaxe 8.0+)
- [ ] Conventions de nommage respectees
- [ ] Namespace correct selon le chemin du fichier
- [ ] Pas de SQL brut dans les controllers
- [ ] Entrees utilisateur filtrees/validees
- [ ] Sorties echappees (XSS)
- [ ] Header copyright present
- [ ] Pas de `declare(strict_types=1)` (convention VvvebCMS)
- [ ] Pas de type hints sur les methodes d'action des controllers
- [ ] Utilisation de `$this->global` pour site_id/language_id
