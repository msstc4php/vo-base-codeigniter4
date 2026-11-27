# Changelog

## [1.0.0] - unreleased

First stable release: from here on a breaking change needs 2.0. What 1.x covers is listed in the README,
"Backward compatibility": the two cast handlers, the rule set and the two controller traits with their methods, the
cast syntax, the rule name, the service name, the argument binding rules, the translation key and its placeholder,
the stored formats, and which exception each path throws. Not covered: message wording otherwise and anything
`@internal`.

### Changed

- Requires `msstc4php/vo-base` ^1.4 (was ^1.1): an abstract class implementing `ResolvesConcreteClass` is a valid
  cast class, rule class, controller argument type and validated-data class, and a `valueObjectMapper` service built
  from a `ProvidesCodecs` set (`new ValueObjectMapper(Codecs::all())`) reaches the casts, the rule, controller
  arguments and validated data; both are tested.
- The `LogicException` for a rule parameter that is not a class name no longer quotes it (it read `parameter "..." is
  not a class name`), and the one for a class the rule cannot validate from input no longer names the class: a
  `{field}` placeholder in the parameter fills it from the submitted data, so the message repeated the client's text.
  `validatedValueObject()` and the casts, whose class is developer code, keep naming it (`validatedValueObject()`
  now says "`<class>` cannot be built from input").
- A controller method that is not routable (missing, non-public, static, `_`-prefixed or declared by
  `CodeIgniter\Controller`) is a 404 with CodeIgniter's generic `HTTP.pageNotFound` message, like an invalid
  segment; it used `forMethodNotFound()`, whose message names the method, which a route such as `Users::$1` takes
  from the URL.
- README: the backward-compatibility promise, the install constraint `^1.0`, and a note on worker mode (the class
  checks and the fallback mapper live as long as the worker).

### Upgrading from 0.x

- No change to the casts, the rule, the stored formats, the controller traits' methods, the translation key or the
  service name.
- Update `msstc4php/vo-base` to ^1.4 if it is pinned lower.
- Code or tests that match the old texts of the rule-parameter `LogicException`s (`parameter "<text>" is not a class
  name`, `<class> cannot be validated from input`, the latter also from `validatedValueObject()`) or the old 404 text (`Controller method is not found: "<method>"`)
  need the new ones; message wording is not covered by 1.x.

## 0.3.0 - 2026-11-23

- Custom payload codecs: the casts, the rule, controller arguments and validated data use the application's
  `valueObjectMapper` service when it defines one (`Config\Services::valueObjectMapper()` or `Services::override()`),
  else a mapper with the core's codecs, as before. Composites of your own and vo-documents' composite names now work;
  before, any class with a codec of its own was refused with a `LogicException`. A `valueObjectMapper` service that
  is no `ValueObjectMapper` throws `LogicException`. The service name is looked up in lowercase (`valueobjectmapper`),
  the form CodeIgniter keeps shared instances and mocks under.

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
