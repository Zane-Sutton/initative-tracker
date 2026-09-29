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
| GET | `/encounters` | List encounters |
| GET | `/encounters/new` | New encounter form |
| POST | `/encounters` | Create encounter |
| GET | `/encounters/{id}` | Live encounter initiative tracker |
| GET | `/encounters/{id}/edit` | Edit encounter form |
| POST | `/encounters/{id}` | Update encounter |
| POST | `/encounters/{id}/delete` | Delete encounter |
| POST | `/encounters/{id}/start` | Start combat (Round 1) |
| POST | `/encounters/{id}/next` | Advance turn & round |
| POST | `/encounters/{id}/prev` | Previous turn |
| POST | `/encounters/{id}/reset` | Reset combat to planned |
| POST | `/encounters/{id}/finish` | Mark encounter finished |
| POST | `/encounters/{id}/roll-initiative` | Roll initiatives for encounter |
| POST | `/encounters/{id}/sort` | Sort turn order by initiative rules |
| POST | `/encounters/{id}/participants/character` | Add player character snapshot |
| POST | `/encounters/{id}/participants/monster` | Add monster snapshot(s) |
| POST | `/encounters/{id}/participants/custom` | Add custom combatant |
| POST | `/encounters/{id}/participants/{pId}` | Inline update combatant snapshot |
| POST | `/encounters/{id}/participants/{pId}/hp` | Quick damage / heal / temp HP |
| POST | `/encounters/{id}/participants/{pId}/roll-initiative` | Roll individual initiative |
| POST | `/encounters/{id}/participants/{pId}/condition` | Toggle status condition |
| POST | `/encounters/{id}/participants/{pId}/toggle-active` | Toggle active status |
| POST | `/encounters/{id}/participants/{pId}/delete` | Remove combatant |
| GET/POST | `/api/dice` | Live dice roller API |

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
