# Changelog

## [unreleased]

### Added

- Support handling signals like `SIGINT` when interrupted with <kbd>^C</kbd>.
  This will rollback any changes made so far when running the process in a
  transaction. This rollback can be disabled with the new `--no-transaction`
  option. ([#4])

[#4]: https://github.com/club-1/flarum-ext-chore-commands/pull/4

## [v1.0.0] - 2023-05-03

First stable release.

[unreleased]: https://github.com/club-1/flarum-ext-chore-commands/compare/v1.0.0...HEAD
[v1.0.0]: https://github.com/club-1/flarum-ext-chore-commands/releases/tag/v1.0.0
