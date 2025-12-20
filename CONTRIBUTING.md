# Contributing

Thanks for considering contributing to PocketDB! A few guidelines to help your contribution get accepted quickly.

1. Reporting Issues

- Please open issues with a clear title and reproduction steps. Include PHP version and any relevant environment notes.

2. Feature Requests

- Describe the use-case and a suggested API if possible. Small focused PRs are easier to review.

3. Development

- Run tests locally:

```bash
composer install
vendor/bin/phpunit
```

- Keep changes small and add unit tests for bug fixes or new features.

4. Coding Standards

- Follow PSR-12 coding style. Optionally run `php-cs-fixer` if configured.

5. Pull Requests

- Fork the repo, create a feature branch, and open a PR against `main`.
- Ensure tests pass and update `CHANGELOG.md` if needed.

6. Licensing

- By contributing you agree to license your contribution under the project's MIT license.
