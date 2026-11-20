<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

/**
 * Where a type declaration is. Set on every token of the type by @class(Passes\TypeDeclarationMarker).
 */
enum TypeDeclarationKind
{
    // function f(int $a), fn (int $a), set (int $a), including promoted constructor properties
    case Parameter;

    // function f(): int, fn (): int =>
    case ReturnType;

    // public int $a;
    case Property;

    // const int A = 1;
    case ClassConstant;

    // catch (A | B $e)
    case Catch;
}
