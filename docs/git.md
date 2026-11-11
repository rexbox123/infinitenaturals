# Git setup and workflow

Repository: <https://github.com/rexbox123/infinitenaturals>

## Setup

```bash
git clone https://github.com/rexbox123/infinitenaturals.git
cd infinitenaturals
git config user.name "Your Name"
git config user.email "you@example.com"
```

Then follow the Quick start in the README (copy `.env.docker.example` → `.env`, fill it in).

## What is (and isn't) committed

Committed: application code, migrations, seeders and seed content, views, Docker files, docs,
`composer.lock` and `package-lock.json` (exact dependency versions), `.env.example` and
`.env.docker.example` (templates with no secrets).

Ignored (see `.gitignore` files): `.env` and every other real environment file, `vendor/`,
`node_modules/`, built assets (`public/build`), uploaded files (`storage/app/public`), logs,
caches, compiled views, local SQLite databases, IDE folders, `auth.json`.

Before committing, check nothing secret is staged:

```bash
git status
git diff --cached --name-only | grep -E '(^|/)\.env$|\.sqlite$|auth\.json' && echo "STOP: secret/local file staged"
```

If a secret is ever committed, treat it as leaked: rotate it (new Stripe key, new DB password,
new `APP_KEY`) — removing it from history is not enough.

## Branches

- `main` — what is deployed to production.
- Feature work on short-lived branches (`feature/…`, `fix/…`), merged through pull requests after
  `php artisan test` passes.

```bash
git checkout -b feature/gift-cards
# …work…
php artisan test
git add -A && git commit -m "Add gift cards"
git push -u origin feature/gift-cards     # open a pull request on GitHub
```

Deploy by pulling `main` on the server (see [deployment.md](deployment.md)).
