# msstc4php/vo-base-codeigniter4

CodeIgniter 4 glue for [`msstc4php/vo-base`][core]: value objects in Entity and Model casts, and a validation rule.

| Class | What it does |
|---|---|
| `Entity\ValueObjectCast` | Entity cast handler: a property reads as a value object, stores its payload |
| `DataCaster\ValueObjectCast` | Model field cast handler (`$casts` / `$castHandlers` on `CodeIgniter\Model`) |
| `Validation\ValueObjectRules` | Rule set with `valid_value_object[<class>]` |

Every conversion goes through the core's `Payload\ValueObjectMapper`, so a column written here reads back through
the Doctrine types, the Laravel cast or the Cycle typecast.

Requires PHP >= 8.4, `codeigniter4/framework` ^4.7.4 (every earlier release carries a security advisory that
Composer blocks by default) and `msstc4php/vo-base` ^1.4.

## Install

Neither this package nor the core is on Packagist yet, so register both GitHub repositories:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base" },
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base-codeigniter4" }
    ],
    "require": {
        "msstc4php/vo-base-codeigniter4": "^1.0"
    }
}
```

## Entity casts

```php
use CodeIgniter\Entity\Entity;
use MSSTC4PHP\ValueObject\CodeIgniter4\Entity\ValueObjectCast;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Primitive\IntRange;

final class User extends Entity
{
    protected $castHandlers = ['vo' => ValueObjectCast::class];

    protected $casts = [
        'email' => 'vo[' . EmailAddress::class . ']',
        'age_range' => '?vo[' . IntRange::class . ']',
    ];
}

$user = new User(['email' => 'a@example.com']); // raw input is validated on the way in
$user->email;                                   // EmailAddress
$user->email = EmailAddress::from('b@example.com');
$user->toRawArray();                            // ['email' => 'b@example.com']
```

The class goes inside the brackets as a fully qualified name; CodeIgniter splits cast parameters on commas only, so
backslashes are fine and a leading backslash is ignored. Null passes in both directions with or without `?`: an
Entity reads an attribute it does not have as null.

`set()` (property assignment, `fill()`, `new User($data)`) treats its input as untrusted and validates it.
`injectRawData()` and `setAttributes()` do not go through `set()`: they are the trusted path the Model uses for
database rows, so never feed request data to them. Query builder calls such as `where('email', ...)` take the stored
form (`(string) $email`, `$port->value`, the JSON text), not the value object.

## Model casts

```php
use CodeIgniter\Model;
use MSSTC4PHP\ValueObject\CodeIgniter4\DataCaster\ValueObjectCast;

final class UserModel extends Model
{
    protected $table = 'users';
    protected $allowedFields = ['email', 'port'];

    protected array $casts = [
        'email' => 'vo[' . EmailAddress::class . ']',
        'port' => '?vo[' . Port::class . ']',
    ];

    // Set here rather than as a property default: redeclaring $castHandlers trips PHPStan's
    // missingType.generics on CodeIgniter's own PHPDoc. initialize() runs before the casts are built.
    protected function initialize(): void
    {
        $this->castHandlers = ['vo' => ValueObjectCast::class];
    }
}

$model->insert(['email' => EmailAddress::from('a@example.com'), 'port' => Port::from(8080)]);
$model->find(1)['email']; // EmailAddress
```

The Model's caster is strict: without `?` it rejects null itself, before this handler runs. Use Entity casts or
Model casts for a field, not both.

## Validation

Add the rule set in `app/Config/Validation.php`:

```php
public array $ruleSets = [
    Rules::class,
    FormatRules::class,
    FileRules::class,
    CreditCardRules::class,
    \MSSTC4PHP\ValueObject\CodeIgniter4\Validation\ValueObjectRules::class,
];
```

```php
$rules = [
    'email' => 'required|valid_value_object[' . EmailAddress::class . ']',
    'port' => 'permit_empty|valid_value_object[' . Port::class . ']',
];
```

Empty input (`null`, `''`, and `[]` — an empty array counts as empty, as with `permit_empty`) passes; `required` is
the rule for presence.

The rule parameter must be a literal class name. Never put a `{field}` placeholder in it: CodeIgniter fills
placeholders from the submitted data, which would let a client pick the class. A parameter that is not a class name
is refused with a `LogicException` before any class lookup or autoload; a value shaped like a class name
(`App\Foo`) still reaches the autoloader. Neither that message nor the one for a class the rule cannot validate
quotes the parameter, since a placeholder would make it the client's text.

Composites (`IntRange`, `GeoCoordinates`, `DateRange`) validate from JSON text or from an array with real types, as
a JSON request body gives them. HTML form fields are always strings, so `range[min]=1&range[max]=5` fails for
`IntRange`, whose codec wants integers. The default message
("The {field} field does not contain a valid value.") comes from this package's `Language/en/Validation.php`,
which CodeIgniter finds through Composer package discovery (`Config\Modules::$discoverInComposer`, on by default);
with discovery off, add `valid_value_object` to your own `app/Language/en/Validation.php`. Per-field custom
messages work as for any rule. CodeIgniter also fills `{value}` and `{param}` in every rule message: `{value}` would
repeat the client's input and `{param}` the class name. The default uses neither.

`BoundedString` is refused by the rule (a `LogicException`, a configuration error): its payload carries its own
bounds, so input would choose them. Check lengths with `min_length`/`max_length`, or use `NonEmptyString`. For the
same reason both casts write a `BoundedString` only from an instance: raw input for it (mass assignment) throws
`InvalidPayloadException`. Which classes input may build is decided by the core's
`ValueObjectMapper::supportsUntrusted()`.

## Controller arguments

Route parameters as typed controller arguments. Add the trait and route explicitly:

```php
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValueObjectArguments;

final class Users extends BaseController
{
    use ValueObjectArguments;

    public function show(EmailAddress $email, ?Priority $priority = null): string
    {
        // ...
    }
}
```

```php
// app/Config/Routes.php
$routes->get('users/(:segment)', 'Users::show/$1');
```

A scalar-backed value object (`EmailAddress`, `Port`, `Uuid`, ...) or a backed enum is built from the segment; an
invalid one answers 404 (`PageNotFoundException`) with CodeIgniter's generic message (`HTTP.pageNotFound`, "Page Not
Found"), which names neither the class nor the segment. A nullable
parameter whose segment is absent or `''` gets `null`; `''` for a non-nullable one is a 404, and so is a missing required (non-nullable, no default) value-object or enum
parameter. A variadic parameter typed with a value object converts every element. Other types keep CodeIgniter's
and PHP's behaviour (`int $id` still receives `'5'` as `5`); union and
intersection types, and every other type are left alone (a class the package does not support, such as your own
service or entity, is never touched), so `EmailAddress|string $id` receives the plain string.

A parameter typed with a supported class that cannot come from a URL segment (`BoundedString`, whose payload carries
its own bounds; a composite such as `DateRange`) throws a `LogicException` naming the parameter: it is a programming
error, found on the first request.

Limitations of the trait:

- **Explicit routes only.** The trait implements `_remap()`, and Auto Routing (Improved) answers 404 for any
  controller that has one (`AutoRouterImproved::checkRemap()`). Auto Routing is off by default
  (`Config\Routing::$autoRoute = false`).
- A controller with its own `_remap()` cannot use the trait.
- `_remap()` switches CodeIgniter's own "method exists" check off, so the trait repeats it: a missing, non-public,
  static, `_`-prefixed method or one declared by `CodeIgniter\Controller` is a 404 with the same generic message (a
  route such as `Users::$1` takes the method name from the URL, so it is not repeated).
- CodeIgniter rejects `@` in URI segments unless `Config\App::$permittedURIChars` allows it (an email address in a
  path needs `@` added to it).

## Validated data

After a validation that includes `valid_value_object`, read the vetted fields as value objects:

```php
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValidatedValueObjects;

final class Signup extends BaseController
{
    use ValidatedValueObjects;

    public function create(): ResponseInterface
    {
        if (!$this->validate(['email' => 'required|valid_value_object[' . EmailAddress::class . ']'])) {
            return $this->response->setStatusCode(422)->setJSON($this->validator->getErrors());
        }

        $email = $this->validatedValueObject('email', EmailAddress::class); // EmailAddress
        // ...
    }
}
```

`validatedValueObject($field, $class)` reads `$this->validator->getValidated()`; a dot key (`profile.email`) reaches
nested data. A missing, `null` or `''` field gives `null`; a composite is built from an array or JSON text. It
throws a `LogicException` when no validation ran, the field's value does not convert (the rule was not applied to it)
or the class is not allowed from input (`BoundedString`). Read only fields validated with `valid_value_object[<same
class>]`: any other value that does not convert is a `LogicException` (a 500, a programming error). Only fields
covered by the validation rules are visible, and a `*` wildcard in the field name is refused with a `LogicException`.

With `ResponseTrait`, `failValidationErrors()` answers 400 (`$codes['invalid_data']`). The bridge does not change it.
To answer 422, call `$this->fail($errors, 422)` directly, or change only that key of the property:

```php
public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
{
    parent::initController($request, $response, $logger);
    $this->codes['invalid_data'] = 422;
}
```

## Stored formats

| Value object | Column holds | Example |
|---|---|---|
| String-backed (`EmailAddress`, `Uuid`, `Iban`, ...) | string | `a@example.com` |
| Int-backed (`Port`, `PositiveInt`, `NonNegativeInt`) | integer (numeric strings from the driver are read) | `8080` |
| Float-backed (`Percentage`, `Latitude`, `Longitude`) | float (numeric strings are read) | `52.5` |
| Backed enum (`CountryCode`, `CurrencyCode`, `LanguageCode`, your own) | its value | `EUR` |
| Composite (`IntRange`, `GeoCoordinates`, `DateRange`, `BoundedString`) | JSON text | `{"min":1,"max":5}` |

An invalid stored value throws the value object's exception, a wrong type or malformed JSON
`InvalidPayloadException`; both extend `InvalidValueObjectException`. A class the mapper cannot handle throws a
`LogicException` on first use.

## Custom codecs

CodeIgniter calls cast handlers statically, so the casts, the rule, controller arguments and validated data all ask for
one `valueObjectMapper` service. Define it in `app/Config/Services.php` with your codecs (or a package's whole set
through its `ProvidesCodecs` class); without it they use a mapper with the core's built-in codecs:

```php
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;

public static function valueObjectMapper(bool $getShared = true): ValueObjectMapper
{
    if ($getShared) {
        return static::getSharedInstance('valueObjectMapper');
    }

    return new ValueObjectMapper([new MoneyCodec()]);
    // vo-documents' composite names: new ValueObjectMapper(\MSSTC4PHP\ValueObject\Documents\Codecs::all())
}
```

`Services::override('valueobjectmapper', $mapper)` works too (in tests, say; `resetSingle('valueobjectmapper')` undoes
it). Write the name in lowercase there: CodeIgniter lowercases it in `getSharedInstance()`, `injectMock()` and
`resetSingle()` but not in `override()`; `Services::injectMock('valueobjectmapper', $mapper)` works too. A
service that is not a `ValueObjectMapper` throws `LogicException`. A cast class is checked against the mapper once
per process, the first time it is used, so a mapper swapped later must support the same classes. Under worker mode
(CodeIgniter 4.7 with FrankenPHP) a process serves many requests: those checks and the fallback mapper (used when no
service is defined) live as long as the worker, so define the service at boot, not per request.

## Limitations

- One failure per field, as with every CodeIgniter rule.

## Backward compatibility

1.x follows semantic versioning; a breaking change needs 2.0. Covered by 1.x:

- **Code API:** these classes and traits, with the methods below. None declares a constructor: CodeIgniter calls the
  casts statically and builds the rule set without arguments.
  - `Entity\ValueObjectCast`, the Entity cast handler, with CodeIgniter's static `get($value, array $params = [])`,
    returning `ValueObject|BackedEnum|null`, and `set($value, array $params = [])`, returning
    `string|int|float|null`;
  - `DataCaster\ValueObjectCast`, the Model field cast handler, with CodeIgniter's static
    `get(mixed $value, array $params = [], ?object $helper = null)`, returning `ValueObject|BackedEnum`, and `set(...)`
    of the same parameters, returning `string|int|float`;
  - `Validation\ValueObjectRules`, the rule set, with `valid_value_object(mixed $value, ?string $class = null): bool`;
  - `Controller\ValueObjectArguments`, the controller trait, with `_remap(string $method, string ...$params): mixed`;
  - `Controller\ValidatedValueObjects`, the controller trait, with the protected
    `validatedValueObject(string $field, string $class)`, returning `ValueObject|BackedEnum|null` (an override in
    your controller must declare a compatible return type).

  The traits hold only these methods. No 1.x release adds a method, property or constant to them: a method your
  class declares would silently replace the trait's (or fail to compile if the signatures differ), a trait method
  would silently replace one your class inherits, and a property or constant of the same name is a conflict.
- **Names users write:** the cast syntax `vo[<class>]` and `?vo[<class>]` (the handler key, `vo` in the examples, is
  yours), with the class as a fully qualified name, a leading backslash allowed, and null passing through the Entity
  cast with or without `?`; the rule `valid_value_object[<class>]` in `Config\Validation::$ruleSets`; the service name
  `valueObjectMapper`, which the casts, the rule, controller arguments and validated data look up as
  `valueobjectmapper` (one plain mapper with the core's codecs without it); the argument binding: a positional route
  parameter whose single named type is a scalar-backed value object or a backed enum (each element of a variadic
  one), leaving union and intersection types and unsupported classes alone; dot keys in `validatedValueObject()`.
- **Messages:** the rule's translation key `Validation.valid_value_object` with its `{field}` placeholder, and
  CodeIgniter's `HTTP.pageNotFound` for the 404s of controller arguments. Changing a key or dropping the placeholder
  needs 2.0; the wording of the English default is not covered.
- **Data:** the stored formats above. They are vo-base's payload layer, the same as the Doctrine types and the other
  bridges write, so a row written through one reads through another. Request input and route segments are read in the
  same forms.
- **Exceptions:**
  - the casts' `get()` and `set()`: vo-base's `InvalidValueObjectException` or a subclass, unchanged, for an invalid
    stored value or raw input, including vo-base's `InvalidPayloadException` for raw input of a trusted-source-only
    class (`BoundedString`); `InvalidPayloadException` with the `JsonException` as previous for composite JSON text
    that does not parse or a composite that cannot be encoded; `LogicException` with no previous for a missing,
    extra or unsupported class parameter, or one that is not a class name;
  - the rule returns `false` for invalid input and throws nothing for it; `LogicException` with no previous for a
    missing parameter, one that is not a class name, or a class it cannot validate from input (unsupported, or
    `BoundedString`), even for empty input;
  - controller arguments: `CodeIgniter\Exceptions\PageNotFoundException` (404) with no previous for an invalid
    segment or unknown enum case, `''` or a missing value for a non-nullable parameter, and a method that is not
    routable; `LogicException` with no previous for a parameter typed with a composite or a trusted-source-only
    class;
  - `validatedValueObject()`: `LogicException` with no previous when no validation ran, for a `*` wildcard in the
    field or a class it cannot build from input; `LogicException` with an `InvalidValueObjectException` as previous
    (vo-base's own, or for composite JSON text the bridge's `InvalidPayloadException`) for a value that does not
    convert;
  - `LogicException` from every entry when the `valueObjectMapper` service is not a `ValueObjectMapper`.
- **Enums:** the casts, the rule, controller arguments and validated data handle backed enums; an unknown case in a
  route segment is a 404, like any invalid value.
- **Versions:** a minor may add support for a new CodeIgniter or PHP major; dropping a supported CodeIgniter or PHP
  version needs a major, and so does raising the floor of any third-party dependency (CodeIgniter, PHP). A minor may
  raise the floor of a `msstc4php/*` package within its major (those follow the same promise).

A minor never refuses a value an earlier 1.x accepted: the casts, the rule, controller arguments and validated data
add no validation of their own, the value objects (vo-base and the domain packages) decide, and they follow the same
rule.

Not covered: exception message wording (the package's messages name classes, fields, cast or method parameters, never
the value, and never quote the rule's parameter), except the translation keys above; anything marked `@internal`
(`Internal\`).

## Development

```
make check   # php -l, PHPStan level max, cs-fixer, composer validate/audit, Rector, deptrac
make test    # PHPUnit
make fix     # cs-fixer + Rector, applied
```

[core]: https://github.com/msstc4php/vo-base
