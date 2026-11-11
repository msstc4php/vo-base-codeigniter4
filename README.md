# msstc4php/vo-base-codeigniter4

CodeIgniter 4 glue for [`msstc4php/vo-base`][core]: value objects in Entity and Model casts, and a validation rule.

| Class | What it does |
|---|---|
| `Entity\ValueObjectCast` | Entity cast handler: a property reads as a value object, stores its payload |
| `DataCaster\ValueObjectCast` | Model field cast handler (`$casts` / `$castHandlers` on `CodeIgniter\Model`) |
| `Validation\ValueObjectRules` | Rule set with `valid_value_object[<class>]` |

Every conversion goes through the core's `Payload\ValueObjectMapper`, so a column written here reads back through
the Doctrine types, the Laravel cast or the Cycle typecast.

Requires PHP >= 8.4 and `codeigniter4/framework` ^4.7.4 (every earlier release carries a security advisory that
Composer blocks by default).

## Install

Neither this package nor the core is on Packagist yet, so register both GitHub repositories:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base" },
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base-codeigniter4" }
    ],
    "require": {
        "msstc4php/vo-base-codeigniter4": "^0.1"
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

    protected array $castHandlers = ['vo' => ValueObjectCast::class];
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

Empty input (`null`, `''`, `[]`) passes; `required` is the rule for presence. The default message
("The {field} field does not contain a valid value.") comes from this package's `Language/en/Validation.php`,
which CodeIgniter finds through Composer package discovery (`Config\Modules::$discoverInComposer`, on by default);
with discovery off, add `valid_value_object` to your own `app/Language/en/Validation.php`. Per-field custom
messages work as for any rule.

`BoundedString` is refused by the rule (a `LogicException`): its payload carries its own bounds, so input would
choose them. Check lengths with `min_length`/`max_length`, or use `NonEmptyString`. For the same reason both casts
write a `BoundedString` only from an instance: raw input for it (mass assignment) is a `LogicException`. Which
classes input may build is decided by the core's `ValueObjectMapper::supportsUntrusted()`.

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

## Development

```
make check   # php -l, PHPStan level max, cs-fixer, composer validate/audit, Rector, deptrac
make test    # PHPUnit
make fix     # cs-fixer + Rector, applied
```

[core]: https://github.com/msstc4php/vo-base
