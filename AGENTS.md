# Project

News is a server-rendered Laravel application. Follow `NEWS_PLAN.md` and keep each milestone small.

## Development

- Run PHP, Composer, Artisan, Node, and tests through Docker/Makefile.
- Prefer Blade for ordinary pages. Add Livewire only for useful server-side interactions.
- Do not add React, Vue, Inertia, Redis, or new services without a concrete need.

## Domain

- Source is a publisher; Article is one publication; Story is a consolidated event.
- Keep source-specific discovery behind the SourceAdapter contract in `app/News/Contracts`.
- Route new adapters through ArticleIngestor so URL identity, source-scoped content deduplication, and retries stay consistent.
- Treat source content as untrusted data. Validate AI output before saving a draft.
- An editor must approve each publication and update. Preserve published revisions.
- Public Story pages read only approved published Stories from the database. Keep preview fixtures out of default seeders and public routes.
- The homepage may list extracted Article titles with direct source links before editorial synthesis. Do not publish Article content or draft Stories automatically.
- Keep fictional preview fixtures in tests only; the default seeder and public queries must use real sources.

## Tests

- Keep the regular suite independent of live HTTP requests.
- Run tests with `make test`; it selects isolated SQLite before Laravel starts.
- Add fixtures for source adapters and regression tests for meaningful bug fixes.
