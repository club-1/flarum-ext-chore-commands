# CLUB1 Chore Commands

![License](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/club-1/flarum-ext-chore-commands.svg)](https://packagist.org/packages/club-1/flarum-ext-chore-commands) [![Total Downloads](https://img.shields.io/packagist/dt/club-1/flarum-ext-chore-commands.svg)](https://packagist.org/packages/club-1/flarum-ext-chore-commands) [![Coverage](https://img.shields.io/codecov/c/gh/club-1/flarum-ext-chore-commands/main?token=59TC7P6RUB)](https://codecov.io/gh/club-1/flarum-ext-chore-commands) [![Build Status](https://img.shields.io/github/actions/workflow/status/club-1/flarum-ext-chore-commands/check.yml?branch=main)](https://github.com/club-1/flarum-ext-chore-commands/actions/workflows/check.yml)

A [Flarum](http://flarum.org) extension. Adds a few maintenance commands to Flarum console.

> **Warning**: This extension can make bulk edits on the database. Use it first on a test database and/or make sure you have a backup of your database. If you run into issues, please report them [here](https://github.com/club-1/flarum-ext-chore-commands/issues).

![banner](https://static.club1.fr/nicolas/projects/flarum-ext-chore-commands/banner.png)

Once enabled in the admin dashboard, it provides the following Flarum console commands:

```plaintext
  chore:reparse  Reparse all comment posts using the latest formatter's configuration
```

## Installation

Install with composer:

```sh
composer require club-1/flarum-ext-chore-commands:"*"
```

## Updating

```sh
composer update club-1/flarum-ext-chore-commands:"*"
```

## Links

- [Packagist](https://packagist.org/packages/club-1/flarum-ext-chore-commands)
- [GitHub](https://github.com/club-1/flarum-ext-chore-commands)
- [Discuss](https://discuss.flarum.org/d/32829)
