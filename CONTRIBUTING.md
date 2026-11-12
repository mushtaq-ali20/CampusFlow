# Contributing to CampusFlow

Thank you for your interest in CampusFlow! Contributions of any kind are welcome: code, documentation, translations, bug reports and feature ideas.

## Reporting bugs and suggesting features

- Search the [existing issues](https://github.com/mushtaq-ali20/CampusFlow/issues) first.
- Open a new issue using the bug report or feature request form.
- Security problems must **not** be reported in a public issue: see [SECURITY.md](SECURITY.md).

## Setting up a development environment

Docker is the only requirement. PHP, Composer and Node all run inside containers.

```bash
git clone https://github.com/mushtaq-ali20/CampusFlow.git
cd CampusFlow
docker compose up -d --wait
```

The first start takes a few minutes. See the [README](README.md#getting-started) for details, including how to load sample data.

Your checkout is mounted into the containers, so changes to PHP and Blade files are visible immediately. Frontend assets (`resources/css`, `resources/js`) are rebuilt automatically by the `assets` container.

## Branches

| Branch | Purpose | Who changes it |
|---|---|---|
| `main` | Production. What runs at the schools. | Nobody commits or pushes to it. It only changes through a pull request from `develop`, merged on GitHub. |
| `develop` | Work in progress, tested before release. | Everybody: commit and push here, or merge your own branches into it. |

The rule is enforced on every computer by the git hooks in `.githooks/`: they refuse a commit on `main` and a push to `main`. Turn them on once after cloning:

```bash
git config core.hooksPath .githooks
```

## Making a change

1. Start from an up-to-date `develop`:

```bash
git switch develop
git pull
```

2. Make your change, on `develop` or on a branch made from it (`git switch -c fix-fee-voucher`). Keep features generic and configurable rather than specific to one school.
3. Add or update tests for the behaviour you changed.
4. Run the tests and the code style fixer:

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
```

5. Commit and push to `develop` (`git push origin develop`). If you worked on your own branch, push it and open a pull request into `develop`.

## Releasing to production

1. On GitHub, open a pull request from `develop` into `main`: https://github.com/mushtaq-ali20/CampusFlow/compare/main...develop
2. Describe what goes to production, check the changes, and merge it with **Create a merge commit**. Do not squash: squashing gives `main` different commits from `develop`, and the next release then shows the same changes again.
3. Deploy `main`.

## Conventions

- Code style follows [Laravel Pint](https://laravel.com/docs/pint) with its default preset.
- Tests use PHPUnit and run against an in-memory SQLite database.
- User-facing text must be translatable: add strings to the files in `lang/`.
- Database changes need a migration. The schema must stay compatible with existing installations.

## Translations

Translation files live in `lang/`. To improve a translation or add a language, edit or add the corresponding files and open a pull request.

## License

By contributing, you agree that your contributions are licensed under the [MIT License](LICENSE).
