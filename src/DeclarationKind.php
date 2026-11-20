<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

/**
 * What a statement declares. See @class(Declaration).
 */
enum DeclarationKind
{
    // "class" can't be the name of an enum case
    case Class_;
    case Interface;
    case Trait;
    case Enum;
    case Function;
    case Method;
    case Property;
    case Constant;
    case EnumCase;
    case TraitUse;

    /**
     * A class, interface, trait or enum.
     */
    public function isClassLike(): bool
    {
        return in_array($this, [self::Class_, self::Interface, self::Trait, self::Enum], true);
    }
}
