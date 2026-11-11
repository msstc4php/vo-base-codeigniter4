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
  throws `InvalidPayloadException` for trusted-source-only classes (`BoundedString`: its payload carries its own
  bounds) and `LogicException` only for unsupported ones. Every
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

`payloadOf()` JSON-decodes a string only when `ValueObjectMapper::isComposite($class)` says the payload is an array;
everything else goes to the mapper as is. Bad JSON is an `InvalidPayloadException` with the `JsonException` as
previous. (Before core 8c795e1 this was try-then-retry; `isComposite()` replaced it.)

## Class checks

`targetClass()` / `untrustedTargetClass()` first match the parameter against an FQCN regex (ASCII letters, digits,
`_` and bytes `\x80-\xff`, as PHP identifiers allow) (so nothing that is not a
class name reaches `is_subclass_of()` and the autoloader), then ask `supports()` / `supportsUntrusted()`. The result
is cached per parameter string in a static array: casts run on every property read.

`fromInput()` asks `supportsUntrusted()` before any JSON decoding, so a trusted-source-only class is refused with the
same message whatever the client sent.

## Writing

`toStorage()` = `toPayload(fromInput($value))`: a value object passes through, raw input (form strings, arrays,
JSON) is validated by building the value object first through the untrusted path, so mass assignment cannot pick
`BoundedString` bounds.
