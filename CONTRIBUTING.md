# Contributing

Thanks for your interest in contributing! This fork focuses on the web panel and its integration with the bot core.

- Issues: open in this repo for panel/integration topics
- PRs: target the `main` branch

Development guidelines
- Follow existing code style (PHP + vanilla JS)
- Keep security in mind: CSRF on POST, admin-only enforcement, prepared statements
- Prefer using bot_core helpers (`select`, `update`, `telegram`, `sendNotification`)
- For large features, open an issue first to discuss scope

Dev setup
- Copy `.env.example` (if present) or configure `config.php`
- Run a local PHP server or Nginx/PHP-FPM
- Create a test Telegram bot via @BotFather and set webhook after panel is reachable

Testing changes
- Verify payments approve/reject flows
- Verify service actions (extend/toggle/reset/revoke)
- Verify notifications delivery to configured channels
- Verify CSRF and role checks block unauthorized access

Release process
- Update CHANGELOG.md
- Bump version in README if needed
- Create a single, descriptive commit or a PR with clean history