<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

/**
 * What a block is the body of. Set by @class(Passes\BlockTypeResolver) on every block and on the token that opens it.
 */
enum BlockType
{
    // The file itself, the root of the tree
    case File;

    // namespace Foo { ... }
    case Namespace;

    // The body of a class, interface, trait or enum, including anonymous classes
    case ClassLike;

    // The body of a named function or method
    case Function;

    // The body of a closure: function () use (...) { ... }
    case Closure;

    // The body of if, elseif, else, for, foreach, while, do, try, catch, finally and declare, braced or with
    // alternative syntax
    case Control;

    // The body of switch, braced or with alternative syntax
    case Switch;

    // The arms of match (...) { ... }
    case Match;

    // The hooks of a property: $name { get => ...; set { ... } }
    case HookList;

    // The body of one property hook: set (...) { ... }
    case HookBody;

    // The rules of a trait use: use A, B { A::x insteadof B; }
    case TraitAdaptation;

    // A bare block statement: { ... }
    case Plain;
}
