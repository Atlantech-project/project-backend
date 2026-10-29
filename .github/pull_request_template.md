## What and why

<!-- Task ID and one or two lines. Plan: ATLANTECH/docs/tasks/<ID>.md -->

## Checklist

- [ ] Base branch is `develop` (never `main`)
- [ ] Touches `database/migrations` or `database/grants` → `ai-impact` label and Rashwan's review
- [ ] Adds or changes an endpoint → `ATLANTECH/openapi/port-api.yaml` updated, `composer contracts:openapi`, paired PortHub PR linked here
- [ ] `docs/tasks/<ID>.md` progress log and done-when updated
- [ ] `composer lint` and `php artisan test --compact` pass locally
