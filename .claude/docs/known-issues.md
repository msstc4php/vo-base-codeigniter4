# Known issues

## Lower bound is 4.7.4, not 4.5

Every `codeigniter4/framework` release below 4.7.4 is covered by a Packagist security advisory (latest batch
2026-08-07 UTC: SQL injection in `deleteBatch()`, path traversal in `UploadedFile::move()`, ...). Composer refuses
them by default, so `--prefer-lowest` resolves to 4.7.4 and nothing older can be tested. The API we use exists
since 4.5.0 (checked the 4.5.0 source: `Boot`, `DataCaster\Cast\BaseCast`, Entity on `DataCaster`), but 4.5.0 did
not append the `nullable` param yet. Verified: 4.7.4 + PHPUnit 11.5 (lowest) and 4.7.6 + PHPUnit 13 (highest).

## Test bootstrap

`phpunit.xml.dist` is a template (bootstrap `vendor/autoload.php`), so CI's own `system/Test/bootstrap.php` is not
used. `tests/Support/CodeIgniterApp` extends `CodeIgniter\Boot` and runs only `loadConstants`,
`loadCommonFunctions`, `loadAutoloader`, `autoloadHelpers` against the framework's skeleton in
`vendor/codeigniter4/framework/app`. It defines `ENVIRONMENT=testing`, `APPPATH`/`ROOTPATH`/`SYSTEMPATH`
itself (`definePathConstants()` needs `Config\Paths`, invisible to PHPStan), `WRITEPATH` in the system temp dir,
and `TESTPATH`/`SUPPORTPATH` (required by `AutoloadConfig` in the testing environment). Skipped on purpose:
`setExceptionHandler()` (would replace PHPUnit's error handler) and `.env` loading.

Model tests connect with an explicit array (`SQLite3`, `:memory:`, `DBPrefix ''`): the skeleton's `tests` group has
`DBPrefix 'db_'`. CI's SQLite driver is `SQLite3`, i.e. **ext-sqlite3**, not pdo_sqlite.

## Language file vs strict coverage metadata

A failing validation makes CI `require` every `Language/en/Validation.php`, ours included. That file is under
`src/` and has no class, so no `#[CoversClass]`/`#[UsesClass]` can list it and `beStrictAboutCoverageMetadata`
marks the test risky under coverage (infection). Such tests live in `tests/Integration` with `#[CoversNothing]`.

## Language discovery for the root package

`Autoloader::loadComposerNamespaces()` adds a Composer namespace only if its path starts with an installed
package's `install_path`. It works for this package as root too (the root is in `InstalledVersions`). An app that
sets `Modules::$discoverInComposer = false` or excludes the package gets the raw key
`Validation.valid_value_object` as message.

## PHPStan on user Models

Redeclaring `protected array $castHandlers = [...]` on a Model trips `missingType.generics` (the inherited PHPDoc
names generic `CastInterface`s without types), and a typed PHPDoc is rejected as not matching the parent's. The
test fixture sets `$castHandlers` in `initialize()` instead (it runs before `createDataConverter()`).

## Custom codecs

The cast handlers are static, so the mapper is built with the core's codecs only. A user composite with its own
`PayloadCodec` is unsupported (`LogicException`) in 0.1.0.

## Core strictness (vo-base 72d0fc5)

Int payloads must be canonical digits (`"080"` is rejected), float payloads plain decimals, `DateRange` ISO 8601 with
an offset. A database column holding a zero-padded number fails loudly on read; that is intended.

## `InvalidPayloadException` is a `LogicException`

The core's exceptions extend `InvalidArgumentException`, which PHP derives from `LogicException`. A test expecting
`LogicException` therefore also passes on `InvalidPayloadException`; tests that mean the client error expect
`InvalidPayloadException` explicitly.

## Placeholders in rule parameters

CodeIgniter fills `{field}` placeholders in rule parameters from the submitted data
(`valid_value_object[{type}]` with `type=..\Evil` reaches the rule as `..\Evil`). The FQCN regex in
`ValueObjectColumn::className()` refuses it before any class lookup; `ValueObjectRulesTest` pins it.

## `@` is not a permitted URI character

`Router` rejects a segment outside `Config\App::$permittedURIChars` (`a-z 0-9~%.:_\-` by default) with a 400
`BadRequestException`, after URL-decoding, so `%40` does not help. An email address in a route needs `@` added to the
setting. The test harness sets it.

## HTTP test harness (`tests/Support/Http`)

`FeatureTestTrait` needs `CIUnitTestCase`, so tests run `CodeIgniter::run($routes, true)` themselves. Gotchas:
the shared `filters` and `router` services keep the first request (the controller then receives a stale request and
its POST data: reset both per call with `Services::resetSingle()`); `Controller::validate()` reads
`$request->getVar()`, which is the `request` global, so set `post` and `request` both; `Config\App` is invisible to
PHPStan (one `@phpstan-ignore class.notFound` with its Reason); the writable path needs `cache/`, `logs/`, `session/`.
A route 404 override that echoes the message turns the rethrown `PageNotFoundException` into a 404 response.

## `_remap()` and Auto Routing (Improved)

Documented in the README: a controller using `ValueObjectArguments` is unreachable through Auto Routing (Improved),
and a controller with its own `_remap()` cannot use the trait (a trait method is overridden by the class's own).

## Equivalent mutants

- `ValueObjectColumn`: the `is_a(ValueObject|BackedEnum)` checks in `targetClass()`/`untrustedTargetClass()` are implied
  by `supports()`/`supportsUntrusted()` and exist for PHPStan narrowing; in the rule, `fromUntrustedPayload()` would
  refuse a `BoundedString` anyway; the `??=` caches (mapper, checked class per parameter) only save work.
- `ArgumentBinder`: the `isDefaultValueAvailable()`/`allowsNull()` operands of the absent-parameter check and its
  `break` (a missing required parameter ends in the same `ArgumentCountError` one parameter later), the
  `array_slice()` offset (surplus segments), the `$type instanceof` / `isBuiltin()` pair and its early `return null`
  (a builtin or union type fails `supports()` right after), and `array_values()` in `_remap()` (the variadic is
  already a list).
