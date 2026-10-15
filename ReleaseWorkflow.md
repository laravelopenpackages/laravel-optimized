# Release Workflow

Laravel Optimized ships as one Composer package, `laravelopenpackages/laravel-optimized`,
installed into Laravel applications with `composer require`.

## Versioning

Semantic versioning, from v1.0.0:

- **`MAJOR.0.0`**: breaking changes. Consumers opt in by bumping their constraint.
- **`MAJOR.MINOR.0`**: new features that break nothing.
- **`MAJOR.MINOR.PATCH`**: fixes.

Consumers on `^1.0` get every minor and patch release on `composer update`, so nothing in a 1.x
release may break an application that worked on an earlier 1.x.

## Cutting a release

1. **Green locally.** `composer test`, `composer analyse` and
   `vendor/bin/phpunit --bootstrap tests/bootstrap-compiled.php` pass.

2. **Verify in a consumer against your local working tree, before tagging.** Point a Laravel
   application at the local package through a Composer path repository, so it exercises your
   uncommitted code:

   ```jsonc
   // the consumer's composer.json
   "repositories": [
     { "type": "path", "url": "../laravel-optimized", "options": { "symlink": true } }
   ],
   "require": { "laravelopenpackages/laravel-optimized": "dev-main" }
   ```

   Run `composer update laravelopenpackages/laravel-optimized`, then run the consumer's suite
   **and drive the actual feature end to end**, under **worker mode (Laravel Octane)** for
   anything touching sessions, CSRF, cookies or navigation. A green unit suite is not proof;
   superglobal and native-session assumptions that hold under PHP-FPM can break in a long-lived
   worker.

3. **Tag.**

   ```sh
   git push origin main
   git tag -a vX.Y.Z -m "…"
   git push origin vX.Y.Z
   ```

   Get the tag right on the first push: **Packagist stable refs are immutable.** A fix means a
   new patch tag, never a re-tag of a published version.

4. **Check Packagist** picked up the tag (usually seconds, through the GitHub webhook).
