<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller;

use CodeIgniter\Controller;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValueObjectArguments;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Priority;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Temporal\DateRange;

final class UsersController extends Controller
{
    use ValueObjectArguments;

    public function show(EmailAddress $email): string
    {
        return 'email:' . $email;
    }

    public function maybe(?EmailAddress $email = null): string
    {
        return 'maybe:' . ($email instanceof EmailAddress ? (string) $email : 'null');
    }

    public function maybeNoDefault(?EmailAddress $email): string
    {
        return 'maybe:' . ($email instanceof EmailAddress ? (string) $email : 'null');
    }

    public function withDefault(EmailAddress $email, string $suffix = 'dflt'): string
    {
        return $email . ':' . $suffix;
    }

    public function plain(string $name, EmailAddress $email): string
    {
        return $name . ':' . $email;
    }

    public function pair(EmailAddress $email, Priority $priority): string
    {
        return $email . ':' . $priority->name;
    }

    public function nullableString(?string $name): string
    {
        return $name ?? 'null';
    }

    public function mixedTail(string $name, ?EmailAddress $email): string
    {
        return $name . ':' . ($email ?? 'null');
    }

    public function defaultedEnum(?Priority $priority = Priority::High): string
    {
        return (string) $priority?->name;
    }

    public function emails(EmailAddress ...$emails): string
    {
        return implode(',', array_map(strval(...), $emails));
    }

    public function boundedVariadic(BoundedString ...$texts): string
    {
        return 'unreachable';
    }

    public function lateUnbindable(string $name, BoundedString $text): string
    {
        return 'unreachable';
    }

    public function intId(int $id): string
    {
        return get_debug_type($id) . ':' . $id;
    }

    public function priority(Priority $priority): string
    {
        return 'priority:' . $priority->name;
    }

    public function union(EmailAddress|string $id): string
    {
        return get_debug_type($id) . ':' . $id;
    }

    public function bounded(BoundedString $text): string
    {
        return (string) $text;
    }

    public function composite(DateRange $range): string
    {
        return 'unreachable';
    }

    public function rest(string $first, string ...$rest): string
    {
        return $first . '|' . implode(',', $rest);
    }

    public static function staticAction(): string
    {
        return 'static';
    }

    public function visible(): string
    {
        return $this->hidden();
    }

    public function _secret(): string
    {
        return 'secret';
    }

    private function hidden(): string
    {
        return 'hidden';
    }
}
