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

```text
feature/<name> ──pull request + approval──► develop ──release pull request──► main (production)
```

| Branch | Purpose | How it changes |
|---|---|---|
| `main` | Production: what runs at the schools. | Only through a release pull request from `develop`. Nobody commits or pushes to it. |
| `develop` | The next release: approved features, tested together. | Only through approved pull requests from feature branches. Nobody commits or pushes to it. |
| `feature/<name>` | One feature or fix, such as `feature/fee-installments` or `fix/voucher-total`. | Its developer commits and pushes freely. Deleted after it is merged. |

The rule is enforced on every computer by the git hooks in `.githooks/`: they refuse a commit on `main` or `develop`, and a push to `main` or `develop`. Turn them on once after cloning:

```bash
git config core.hooksPath .githooks
```

## Developing a feature

1. Make a feature branch from an up-to-date `develop`:

```bash
git switch develop
git pull
git switch -c feature/<short-name>
```

2. Make your change and commit as often as you like. Keep features generic and configurable rather than specific to one school.
3. Add or update tests for the behaviour you changed, then run the tests and the code style fixer:

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
```

4. Push the branch:

```bash
git push -u origin feature/<short-name>
```

5. On GitHub, open a pull request with **base: `develop`** and **compare: your branch**. Fill in the checklist and ask a reviewer.
6. The reviewer reads the changes, asks for corrections if needed (push them to the same branch: the pull request updates itself), and approves.
7. Once approved, merge with **Squash and merge**: the feature becomes one commit on `develop`. Delete the branch when GitHub offers.

If `develop` moved on while you worked, bring its changes into your branch before the review:

```bash
git switch feature/<short-name>
git pull origin develop
git push
```

## Releasing to production

When `develop` has been tested and is ready:

1. On GitHub, open a pull request with **base: `main`** and **compare: `develop`**: https://github.com/mushtaq-ali20/CampusFlow/compare/main...develop
2. List what goes to production, check the changes, and merge with **Create a merge commit**. Do not squash a release: squashing gives `main` different commits from `develop`, and the next release would show the same changes again.
3. Deploy `main` to the production server.

## Urgent fix in production

When production needs a fix that cannot wait for the next release:

1. Make a branch from `main`: `git switch main && git pull && git switch -c hotfix/<short-name>`.
2. Fix, test, push, and open a pull request into `main`. Merge it after approval and deploy.
3. Open a second pull request from the same branch into `develop`, so the fix is not lost at the next release.

## Conventions

- Code style follows [Laravel Pint](https://laravel.com/docs/pint) with its default preset.
- Tests use PHPUnit and run against an in-memory SQLite database.
- User-facing text must be translatable: add strings to the files in `lang/`.
- Database changes need a migration. The schema must stay compatible with existing installations.

## Translations

Translation files live in `lang/`. To improve a translation or add a language, edit or add the corresponding files and open a pull request.

## License

By contributing, you agree that your contributions are licensed under the [MIT License](LICENSE).
