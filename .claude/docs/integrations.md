# Integrations (CodeIgniter 4.7)

## Cast parameters — `DataCaster::castAs()`

- Type string `vo[<FQCN>]` is parsed with `/\A(.+)\[(.+)\]\z/`, params = `explode(',')` + `trim`. Backslashes in the
  FQCN are fine; spaces around it are trimmed. A leading backslash is stripped by us (the mapper's codec lookup is
  keyed by `::class` spelling).
- `?` prefix: null returns null **before** the handler. For a non-null value in **non-strict** mode (Entity) CI
  appends the param `'nullable'` — `targetClass()` ignores it. Any other extra param is a `LogicException`.
- Strict mode (Model's `DataConverter`): a null for a cast without `?` throws
  `CodeIgniter\Exceptions\InvalidArgumentException` ("Field ... is not nullable") before the handler runs.
- Non-strict mode (Entity): null reaches the handler. Our Entity cast returns null both ways; a fresh Entity reads
  missing attributes as null through the cast.
- Unknown handler key: `InvalidArgumentException('No such handler ...')` from CI.

## Entity

- `__set` runs the `set` cast; `new Entity($data)` / `fill()` go through `__set`, so form input is validated there.
- `injectRawData()` (used by the Model when hydrating) bypasses `set`; `__get` runs `get` each read (no caching).
- `toRawArray()` returns the stored payloads; a Model saving an Entity without Model casts uses it.

## Model (`BaseModel`)

- `$casts` + `$castHandlers` -> `DataConverter` (strict, helper = the DB connection). Rows from the driver go through
  `fromDataSource()`; insert/update arrays through `toDataSource()`; an Entity through `extract()`.
- Combining Model casts and Entity casts on the same field works only because `toPhp()` passes an instance through;
  documented as "use one".

## Validation

- Rule sets: `Config\Validation::$ruleSets` lists classes; CI instantiates each and calls
  `$set->{$rule}($value, $param, $data, $error, $field)` when the rule has `[...]`, else `$set->{$rule}($value, $error)`.
  Our method declares only `($value, ?string $class = null)`: extra arguments are legal in PHP, and Rector's
  unused-parameter rule strips them anyway. Without brackets `$class` is null -> `LogicException`.
- Message: `$error` (by-ref) would override per-field custom messages, so we don't set it. Lookup is
  `customErrors[field][rule]` then `lang('Validation.valid_value_object')`. `Language::requireFile()` merges every
  `Language/en/Validation.php` found by `FileLocator::search()` across namespaces; our namespace is there via Composer
  discovery (`Modules::$discoverInComposer`, default true; `composerPackages` only/exclude can remove it).
- `permit_empty` short-circuits empty input before our rule; `required` is separate. We also pass `null`/`''`/`[]`.
- Form arrays are strings: `range[min]=1` gives `['min' => '1']`, which `IntRangeCodec` rejects (it wants ints). JSON
  bodies work. Composite validation is therefore mostly for JSON APIs.
