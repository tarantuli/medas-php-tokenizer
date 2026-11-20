<?php

$a = $b ? $c : $d;
$e = $f ?: $g;
$h = $i ?? $j;
$k ??= 'default';
$l = $m ? FOO : BAR;
$n = $o?->p?->q();
$r = $s ? ($t ? 1 : 2) : 3;

function nullable(?Foo $foo, ?int $count = null): ?Bar
{
    return $foo ? new Bar(name: $foo->name, count: $count ?? 0) : null;
}

label:
goto label;
