# Known issues

## Lower bound is 4.7.4, not 4.5

Every `codeigniter4/framework` release below 4.7.4 is covered by a Packagist security advisory (latest batch
2026-08-07 UTC: SQL injection in `deleteBatch()`, path traversal in `UploadedFile::move()`, ...). Composer refuses
them by default, so `--prefer-lowest` resolves to 4.7.4 and nothing older can be tested. The API we use exists
since 4.5.0 (checked the 4.5.0 source: `Boot`, `DataCaster\Cast\BaseCast`, Entity on `DataCaster`), but 4.5.0 did
not append the `nullable` param yet. Verified: 4.7.4 + PHPUnit 11.5 (lowest) and 4.7.6 + PHPUnit 13 (highest).
Rechecked for 1.0 (2026-11-27 UTC): roave/security-advisories still conflicts with `<4.7.4` only, so 4.7.4 stays
installable and the floor stays; from 1.0 raising it needs 2.0 (README "Versions"), so a future advisory batch that
blocks 4.7.4 turns the lowest job red without a way out in a minor. vo-base floor is ^1.4 (O1); the lowest job is the
only place 1.4.0 is resolved (the local profile is a path repository).

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

Since 0.3.0 the casts, the rule, the binder and validated data use the `valueobjectmapper` service when one is
defined (see "Mapper service name is lowercase"); without it, the core's codecs only. A `ProvidesCodecs` set goes in
as `new ValueObjectMapper(Codecs::all())` in that service (`tests/Integration/ProvidesCodecsTest`).

## Worker mode (CodeIgniter 4.7 + FrankenPHP)

`ValueObjectColumn`'s static fallback mapper and its per-parameter class caches (`$castClasses`, `$inputClasses`)
live as long as the worker process, not the request. README says to define the service at boot. A mapper swapped
mid-process must support the same classes (the cache does not re-check).

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
`ValueObjectColumn::className()` refuses it before any class lookup; `ValueObjectRulesTest` pins it. Since 1.0 the
message quotes no part of the parameter (audit11 S1), and the "cannot be validated from input" message no longer
names the class either: `valid_value_object[{type}]` with `type=App\Foo` passes the regex, so the class name would be
the client's text. `testAPlaceholderThatNamesAClassIsNotQuotedEither` pins it.

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
  refuse a `BoundedString` anyway; the `??=` caches (mapper, checked class per parameter) only save work;
  `PublicVisibility` on `mapper()` escapes because Infection runs the unit suite only (`infection.json5`:
  `--testsuite=unit`), and the calls from outside the class that would fail (`MapperServiceTest`, the integration
  tests through `ArgumentBinder`/`ValidatedData`) are integration tests. The ResolvesConcreteClass/ProvidesCodecs
  tests are integration tests too, so they add no kills. Full run at 1.0
  (2026-11-27 UTC): 128 mutants, 120 killed, 8 escaped (all of these), MSI 93.75% (CI gate 75%).
- `ArgumentBinder`: none proven. Infection counts only tests that `#[CoversClass]` the mutated class, so
  `ArgumentBinder` behaviour is pinned in `ArgumentBinderTest`, not only in the integration tests (those
  `#[UsesClass]` it). Plans are computed per call (no cache) to keep the binder stateless.

## Argument binding rules

A bound (value object or enum) parameter that is missing and required is a 404 (no 500 for client input); non-bound
parameters keep PHP's behaviour (`ArgumentCountError`, weak-mode `'5'` -> `5`). Every parameter's bindability is
checked before binding, so an unbindable type fails on every request. A variadic with a bound type converts each
element.

## Mapper service name is lowercase

`ValueObjectColumn::SERVICE = 'valueobjectmapper'`. CodeIgniter lowercases the name in `getSharedInstance()`,
`injectMock()` and `resetSingle()` but not in `Services::get()`/`override()`, so a camelCase name leaked a mocked
mapper between tests. A service that is not a `ValueObjectMapper` is a `LogicException`; a missing one falls back to
`new ValueObjectMapper()`.

## 404 messages of the binder

`ArgumentBinder` throws `PageNotFoundException::forPageNotFound()` everywhere (CodeIgniter's `HTTP.pageNotFound`,
"Page Not Found", code 404, no previous). Until 0.3.0 the routability check used `forMethodNotFound($method)`,
whose message names the method; a route such as `Users::$1` takes the method from the URL
(`ControllerArgumentsTest::testAMethodTakenFromTheRouteIsNotEchoedIn404`). CodeIgniter hides 404 messages in
production anyway, but the test harness's 404 override echoes them, which is how the tests see the text. The
invalid-value 404 deliberately has no previous: `forPageNotFound()` takes no previous, and the mapper's exception
quotes the segment.

## Roave bc-check

Exercised since v0.1.0; last green on 0c2f52a (2026-11-27T05:55Z UTC) against v0.3.0. Expected **green** on the
1.0.0 commit as well: 1.0 changes no public signature and adds no `@internal` to an existing symbol (everything in
`Internal\` was `@internal` already, and the traits and casts keep their methods); message texts are not checked.
If it turns red, the report is real, not a 0.x-to-1.0 artefact. Not run locally (the VCS repository of vo-base needs
GitHub authentication).

