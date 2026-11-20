<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

/**
 * What an ambiguous token means where it is: ?, :, & and ... each have several meanings. Set by
 * @class(Passes\RoleAssigner).
 */
enum TokenRole
{
    // The ? of $a ? $b : $c
    case TernaryThen;

    // The : of $a ? $b : $c
    case TernaryElse;

    // Both tokens of $a ?: $b
    case ShortTernary;

    // The ? of ?int
    case NullableMarker;

    // The : of foo(name: 1)
    case NamedArgumentColon;

    // The : of function f(): int
    case ReturnTypeColon;

    // The : of case 1: and default:
    case CaseColon;

    // The : of if ($a): ... endif;
    case AlternativeSyntaxColon;

    // The : of enum A: string
    case EnumBackingColon;

    // The : of label:
    case GotoLabelColon;

    // The & of function f(&$a), foreach ($a as &$b), $a = &$b and function &f()
    case ByReference;

    // The ... of function f(int ...$a)
    case Variadic;

    // The ... of f(...$a) and [...$a]
    case Spread;

    // The ... of strlen(...)
    case FirstClassCallable;
}
