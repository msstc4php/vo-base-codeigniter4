# Changelog

## 0.2.0 - 2026-11-12

- `Controller\ValueObjectArguments`: controller trait (`_remap()`) converting route parameters to scalar-backed value
  objects and backed enums; invalid input is a 404. Explicit routes only: Auto Routing (Improved) refuses `_remap()`.
- `Controller\ValidatedValueObjects`: `validatedValueObject($field, $class)` reads a validated field as a value
  object (dot keys, composites from arrays or JSON text).
- A parameter typed with a trusted-source-only or composite class is a `LogicException`, never the raw string.
- No change to the 0.1.0 public API.

## 0.1.0 - 2026-11-11

First release. No stability promise before 1.0.

- `Entity\ValueObjectCast`: Entity cast handler for value objects (`vo[<class>]`, `?vo[<class>]`).
- `DataCaster\ValueObjectCast`: Model field cast handler with the same behavior.
- `Validation\ValueObjectRules`: rule set with `valid_value_object[<class>]` and an English default message.
- Storage through `msstc4php/vo-base` 1.1's `ValueObjectMapper`; composites as JSON text.
- `BoundedString` is never built from untrusted input: the rule refuses it, the casts write it only from an
  instance.
- Requires `codeigniter4/framework` ^4.7.4.
