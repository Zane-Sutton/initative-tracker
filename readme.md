# D&D Initiative Tracker

Personal-use initiative tracker. Plain PHP 8.3+, PDO, MariaDB. No framework.

## Setup

```bash
composer install
cp .env.example .env            # then edit credentials

# Create the database and user once (as a MariaDB admin):
#   CREATE DATABASE dnd_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#   CREATE USER 'dnd_tracker'@'localhost' IDENTIFIED BY 'change_me';
#   GRANT ALL ON dnd_tracker.* TO 'dnd_tracker'@'localhost';

php bin/migrate.php             # applies migrations/*.sql in order
php -S localhost:8000 -t public
```

Open http://localhost:8000. The home page shows DB connection status and row counts.

## Layout

- `public/` web root (front controller, assets)
- `src/` PSR-4 `App\` namespace: Router, View, Database, controllers, repositories, models
- `templates/` PHP views rendered inside `layout.php`
- `migrations/` numbered SQL files, applied by `bin/migrate.php`
- `config/config.php` reads `.env`

## Data model note

`characters` and `monsters` are templates. `encounter_participants` holds the live
instances (rolled initiative, current/temp HP, turn order), so several goblins can
come from one stat block.

## Routes

| Method | Path | Action |
|--------|------|--------|
| GET | `/characters`, `/monsters` | List (with `?q=` name search) |
| GET | `/characters/new`, `/monsters/new` | New form |
| POST | `/characters`, `/monsters` | Create |
| GET | `/monsters/{id}` | Stat block |
| GET | `/characters/{id}/edit`, `/monsters/{id}/edit` | Edit form |
| POST | `/characters/{id}`, `/monsters/{id}` | Update |
| POST | `/characters/{id}/delete`, `/monsters/{id}/delete` | Delete |

All POST routes require the session CSRF token (`csrf_field()` in forms).

## JSON import

Open `/import` (nav: Import) to paste JSON or upload a `.json` file. The format is
`{ "characters": [...], "monsters": [...] }`; see the "Format reference" on that page.
`samples/import-sample.json` contains a five-character party and some SRD undead/goblins,
and the **Load sample** button fills the box with it.

- Validation uses the same rules as the forms (`src/Rules.php`).
- All-or-nothing: if any entry is invalid, nothing is written and every error is listed.
- Entries are matched by name. Existing matches are skipped, or overwritten if
  "Update existing" is ticked (an update replaces all editable fields).
- In monsters, `stats` may be a JSON object and `actions` may be a list of strings.
