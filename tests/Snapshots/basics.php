<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\HasName;
use App\Support\{Collection, Str as Strings};
use function App\Support\helper;
use const App\Support\VERSION;

final class User extends Model implements HasName
{
    public const string TABLE = 'users';
    private static int $count = 0;
    protected readonly array $roles;

    public function __construct(private string $name, int ...$ids)
    {
        self::$count++;
    }

    public function name(): string
    {
        return $this->name;
    }
}
