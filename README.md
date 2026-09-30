# File Service

A self-hosted file sync & share service inspired by [Seafile](https://github.com/haiwen/seafile), built with
**Laravel 13** (REST API), **Filament 5** (admin panel) and **Quasar 2 / Vue 3** (web client).

```
file-service/
├── backend/    Laravel API + Filament admin panel (/admin)
└── frontend/   Quasar SPA (web client)
```

## Features

| Area | What you get |
|------|--------------|
| Libraries | Personal libraries (like Seafile "repos"), each with its own folder tree, size and file counters |
| Files & folders | Upload (drag & drop, multi-file, progress), new folder, rename, move, copy, download (folders as ZIP), search, in-browser preview (images, video, audio, PDF, text) |
| Versioning | Uploading a file with the same name creates a new version; browse history, download or restore any version |
| Trash | Deleted items go to a per-library trash; restore or purge, "empty trash" |
| Storage | Content-addressable blob store (identical files stored once), per-user quotas, admin-defined upload limit |
| Sharing | Share a library or a folder with users or groups, read-only or read/write |
| Share links | Public download links (optional password, expiry, download on/off) and anonymous **upload links** |
| Groups | Create groups, add members with admin/member roles, share libraries to a group |
| Starred | Star files/folders for quick access |
| Activity | Per-library / per-user activity log (create, rename, move, delete, share, download, …) |
| Languages | Persian (RTL, default) and English UI with a language switcher; Jalali dates in Persian. Admin panel follows `APP_LOCALE` (fa/en) |
| Admin panel | Filament: users (quota, admin flag, activate/deactivate), libraries (browse files, trash, shares), groups, share links, activity log, global settings, dashboard stats |

## Requirements

- PHP 8.3+, Composer
- Node.js 22+ and npm
- SQLite (default) or MySQL/PostgreSQL

## Backend (Laravel + Filament)

```bash
cd backend
composer install
cp .env.example .env            # already contains sane defaults for SQLite
php artisan key:generate
touch database/database.sqlite   # if it does not exist
php artisan migrate --seed       # creates demo users and a sample library
php artisan storage:link
php artisan serve                # http://localhost:8000
```

Seeded accounts (password `password`):

| Email | Role |
|-------|------|
| `admin@example.com` | Administrator (can open `/admin`) |
| `user@example.com` | Regular user with a sample library |

Important `.env` keys:

| Key | Purpose |
|-----|---------|
| `FRONTEND_URL` | Base URL of the Quasar app, used to build public share links (default `http://localhost:9000`) |
| `FILESERVICE_DISK` | Filesystem disk that stores file blobs (default `blobs` → `storage/app/blobs`). Point it at an S3 disk for object storage |
| `DB_*` | Database connection |

Admin panel: `http://localhost:8000/admin`

Run the test suite:

```bash
cd backend && php artisan test
```

## Frontend (Quasar)

```bash
cd frontend
npm install
cp .env.example .env             # set API_URL if the API is not on http://localhost:8000
npm run dev                      # http://localhost:9000
npm run build                    # production build in dist/spa
```

`API_URL` is read at build time (`quasar.config.js → build.defineEnv`). The Content-Security-Policy in
`index.html` automatically allows that origin for API calls and file previews.

## API overview

All endpoints live under `/api/v1` and use Laravel Sanctum bearer tokens.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `auth/register`, `auth/login`, `auth/logout` | Authentication |
| GET/POST | `auth/me`, PUT `auth/password` | Profile |
| GET/POST | `libraries`, `libraries/{id}` | Libraries CRUD, `libraries/shared` = shared with me |
| GET | `libraries/{id}/nodes?parent_id=` | List a folder |
| POST | `libraries/{id}/folders`, `libraries/{id}/upload` | Create folder / upload file |
| PATCH/DELETE | `nodes/{id}` | Rename / move to trash |
| POST | `nodes/{id}/move`, `nodes/{id}/copy` | Move / copy (across libraries too) |
| GET | `nodes/{id}/download`, `nodes/{id}/download-url` | Download (folders as ZIP) / short-lived signed URL |
| GET/POST | `nodes/{id}/versions`, `nodes/{id}/versions/{v}/restore` | Version history |
| GET/POST/DELETE | `libraries/{id}/trash…` | Trash listing, restore, purge, empty |
| GET/POST/PUT/DELETE | `libraries/{id}/shares`, `shares/{id}` | Share with users/groups |
| GET/POST/DELETE | `share-links` | Public links |
| GET/POST | `share/{token}`, `share/{token}/browse`, `share/{token}/download`, `share/{token}/upload` | Anonymous link access |
| CRUD | `groups`, `groups/{id}/members` | Groups |
| GET | `starred`, `activities`, `users/search` | Misc |

## Docker

```bash
cp .env.example .env
# generate an application key and paste it into .env
docker compose run --rm --no-deps api php artisan key:generate --show
docker compose up -d --build
docker compose exec api php artisan db:seed     # optional demo data
```

- Web client: http://localhost:9000 · API: http://localhost:8000 · Admin: http://localhost:8000/admin
- File blobs and the MySQL data live in named volumes (`blobs`, `db-data`).
- To serve on another host, change `APP_URL`, `FRONTEND_URL` and the `API_URL` build argument in `docker-compose.yml`.

## Production notes

- Serve `frontend/dist/spa` from any static host (or from Laravel's `public/`), and set `FRONTEND_URL` accordingly.
- Configure `FILESERVICE_DISK` to an S3-compatible disk for scalable storage.
- Put the API behind HTTPS; share-link passwords are sent as a header (`X-Share-Password`).
- Run `php artisan queue:work` if you later move heavy work (ZIP building, thumbnails) onto queues.
