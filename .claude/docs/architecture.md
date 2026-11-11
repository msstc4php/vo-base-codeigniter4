# Architecture

```
Entity      DataCaster      Validation
   \            |              /
    +------ Internal ---------+
                |
   core: Payload\ValueObjectMapper
```

- **`Internal\ValueObjectColumn`** (`@internal`): parses the cast parameters, checks the class against
  `ValueObjectMapper::supports()` (casts) or `supportsUntrusted()` (rule), converts stored value <-> value object,
  JSON-encodes composite payloads. Trust boundary: `fromStorage()` (cast `get()`, DB rows) uses `fromPayload()`;
  `fromInput()` (cast `set()` of a raw non-instance value, the validation rule) uses `fromUntrustedPayload()`, which
  throws `LogicException` for trusted-source-only classes (`BoundedString`: its payload carries its own bounds). Every
  kind decision is the mapper's; this class only adds the transport (JSON text for array payloads).
- **`Entity\ValueObjectCast`** and **`DataCaster\ValueObjectCast`**: thin adapters over `Internal` for CodeIgniter's
  two cast interfaces (`CodeIgniter\Entity\Cast\CastInterface` and `CodeIgniter\DataCaster\Cast\CastInterface`).
- **`Validation\ValueObjectRules`**: rule set object, also over `Internal`.
- `src/Language/en/Validation.php`: the default message, a plain PHP array file.

`deptrac.yaml` lets the three public areas depend on `Internal` only; they never reference each other.

## Why static

CodeIgniter calls cast handlers statically by class name (`$handler::get(...)`), so there is no instance to inject a
mapper into. `ValueObjectColumn` keeps one lazily built `ValueObjectMapper` with the core's built-in codecs. Custom
`PayloadCodec`s are therefore not pluggable in 0.1.0 (see known-issues).

## Reading a stored value

`read()` (behind both) first hands the raw value to the mapper. Only when that fails with `InvalidPayloadException` (wrong type)
and the raw value is a string does it try JSON: a decoded array is handed to the mapper again; anything else (bad
JSON, a JSON scalar) becomes `[]`, which no codec accepts. If the second attempt also fails on type, the *first*
error is rethrown, so `Port` given `"[1]"` reports "must be an integer, string given", not "array given". This
avoids re-implementing "is this class a composite?".

## Writing

`toStorage()` = `toPayload(fromInput($value))`: a value object passes through, raw input (form strings, arrays,
JSON) is validated by building the value object first through the untrusted path, so mass assignment cannot pick
`BoundedString` bounds.
