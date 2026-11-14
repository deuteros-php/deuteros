# Stubs

Stub interfaces for use when Drupal core is not available. They contain only the
methods required by Deuteros.

## Structure

- `bootstrap.php` - Conditionally loads stubs when Drupal core is absent
- `drupal.stub` - All interface definitions in a single file

All interfaces are consolidated in `drupal.stub` to ensure new interfaces are
automatically loaded without maintaining a separate manifest.

The file doesn't use a `.php` extension. Static analyzers and IDEs read every
`.php` file under `vendor/`, and in a Drupal project they would take these
declarations in place of core's. `require_once` loads the file whatever its
extension.
