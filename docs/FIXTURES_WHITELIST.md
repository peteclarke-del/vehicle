FIXTURES_WHITELIST
==================

This project includes a runtime guard that prevents accidental execution of
`doctrine:fixtures:load` in non-test environments. The guard allows running
fixtures only when one of the following is true:

- `APP_ENV=test`
- `FORCE_FIXTURES=1`
- A whitelisted CLI flag is present (configured via `FIXTURES_WHITELIST`).

Configuring the whitelist
-------------------------

Set the environment variable `FIXTURES_WHITELIST` to a comma-separated list of
flags you consider safe. Each item can be a short `-a` or long `--append` form
or a plain name (the code will prefix `--` when needed).

Examples:

```
# allow --append only
export FIXTURES_WHITELIST=--append

# allow --append and -n
export FIXTURES_WHITELIST="--append,-n"
```

Notes and safe usage
--------------------

- `--append` is considered safe because it does not purge the database. When
  you use append, fixtures will attempt to insert data and can fail with
  duplicate-key errors if the data already exists — this is expected.
- When you need to fully re-seed (purge + load), prefer setting
  `APP_ENV=test` or explicitly exporting `FORCE_FIXTURES=1` so the guard
  does not block you.
- In CI, always run fixtures against a test-only database (set `APP_ENV=test`).

Quick commands
--------------

Run fixtures (append) with whitelist:

```
export FIXTURES_WHITELIST=--append
php bin/console doctrine:fixtures:load --append
```

Force a full fixtures run (purge + load):

```
export FORCE_FIXTURES=1
php bin/console doctrine:fixtures:load
```

If you prefer stricter control, avoid whitelisting flags and always use
`FORCE_FIXTURES=1` when you intentionally want to purge and reload data.
