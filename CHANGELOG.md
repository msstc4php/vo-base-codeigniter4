# Changelog

## 0.1.0 - 2026-11-11

First release. No stability promise before 1.0.

- `Entity\ValueObjectCast`: Entity cast handler for value objects (`vo[<class>]`, `?vo[<class>]`).
- `DataCaster\ValueObjectCast`: Model field cast handler with the same behavior.
- `Validation\ValueObjectRules`: rule set with `valid_value_object[<class>]` and an English default message.
- Storage through `msstc4php/vo-base` 1.1's `ValueObjectMapper`; composites as JSON text.
- `BoundedString` is never built from untrusted input: the rule refuses it, the casts write it only from an
  instance.
- Requires `codeigniter4/framework` ^4.7.4.
