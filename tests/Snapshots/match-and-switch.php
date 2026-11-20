<?php

$label = match ($status) {
    Status::Active, Status::Pending => 'open',
    Status::Closed => 'closed',
    default => throw new UnexpectedValueException(),
};

switch ($type) {
    case 'a':
    case 'b':
        $x = 1;
        break;
    case Foo::BAR:
        $x = $y ? 2 : 3;
        break;
    default:
        $x = 0;
}

$nested = match (true) {
    $a > 1 => match ($b) {
        1 => 'one',
        default => 'many',
    },
    default => 'none',
};
