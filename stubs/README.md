# Stubs

Stub interfaces for use when Drupal core is not available. They contain only the
methods required by Deuteros.

## Structure

- `bootstrap.php` - Conditionally loads stubs when Drupal core is absent
- `stubs.php` - All interface definitions in a single file

All interfaces are consolidated in `stubs.php` to ensure new interfaces are
automatically loaded without maintaining a separate manifest.
