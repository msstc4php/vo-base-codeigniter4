# Conventions

- **Rule method in snake_case** (`valid_value_object`): CI calls rule sets by the rule name; there is no mapping.
- **`@param mixed $value` on both `set()`s**: the generic `BaseCast<TPhpNativeValue, ...>` would type the input as the
  value object, but CI hands raw form/row input to `set()` and we accept it on purpose.
- **Exceptions**: invalid content -> the value object's exception; wrong type/shape, bad JSON
  -> `InvalidPayloadException`; raw (non-instance) `BoundedString` input on write -> `LogicException` from the
  core's `fromUntrustedPayload()`; configuration errors (missing/unsupported class, extra param, `BoundedString` in
  the rule) -> `LogicException`. Validation returns `false` only for `InvalidValueObjectException`.
- **No hard-coded class checks**: which classes input may build is the core's `supportsUntrusted()` decision.
- **JSON flags**: plain `JSON_THROW_ON_ERROR`, same as the Doctrine ORM types, so bytes match across bridges.
- **Tests**: real CI classes. Entity and DataCaster tests need no boot. Validation and Model tests call
  `Tests\Support\CodeIgniterApp::boot()` in `setUpBeforeClass()`. Tests that make CI load language files live in
  `tests/Integration` with `#[CoversNothing]` (see known-issues).
- Test fixtures avoid `Config\*` classes and global CI functions (`service()`, `config()`): PHPStan cannot see them
  (no Composer autoload for `app/Config` or `system/Common.php`). Use `CodeIgniter\Config\Services::renderer()`,
  `CodeIgniter\Database\Config::connect()` and the local `Tests\Support\ValidationConfig`.
