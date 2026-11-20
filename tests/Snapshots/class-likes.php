<?php

interface Shape extends Countable, Stringable
{
    public function area(): float;
}

trait Greets
{
    abstract public function name(): string;

    public function greet(): string
    {
        return 'Hello ' . $this->name();
    }
}

abstract class Base
{
    use Greets;
    use Logs, Caches;

    abstract protected function build(): static;
}

enum Status: string implements HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';

    const DEFAULT = self::Active;

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

$anonymous = new class(1) extends Base implements Shape {
    public function __construct(public int $x)
    {
    }
};

readonly class Point
{
    public function __construct(public int $x = 0, public int $y = 0)
    {
    }
}
