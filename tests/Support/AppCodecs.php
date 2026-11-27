<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use MSSTC4PHP\ValueObject\Payload\ProvidesCodecs;
use Override;

/**
 * A package's codec set, as vo-documents ships one.
 */
final class AppCodecs implements ProvidesCodecs
{
    #[Override]
    public static function all(): array
    {
        return [new DimensionsCodec()];
    }
}
