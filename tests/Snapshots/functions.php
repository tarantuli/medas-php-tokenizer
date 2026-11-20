<?php

function plain($a, $b = 1, ...$rest)
{
}

function typed(int $a, ?string $b = null, int|float $c = 0, array &$d = [], Foo|Bar|null $e = null): ?string
{
    return null;
}

function &byReference(): array
{
    static $cache = [];

    return $cache;
}

function defaults($a = FOO, $b = Bar::BAZ, $c = [1, 2], $d = new Foo(1)): void
{
}

$closure = function (int $x) use ($y, &$z): int {
    return $x + $y;
};

$static = static fn(int $x): int => $x * 2;

$nested = fn($x) => fn($y) => $x + $y;

$callable = strlen(...);
