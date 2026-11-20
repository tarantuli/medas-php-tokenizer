<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

/**
 * What a statement declares, set by @class(Passes\DeclarationReader) on ```Statement::$declaration```.
 */
final readonly class Declaration
{
    /**
     * @param string|null $name The name of the class, function, constant, enum case or property ($ included); null
     *     for a trait use
     * @param string[] $modifiers The modifiers in lower case and in source order, like "final", "public",
     *     "private(set)", "static" and "readonly"
     * @param Token $keyword The first token after the comments, attributes and modifiers: class, function, const, the
     *     type or the variable of a property, etc.
     */
    public function __construct(
        public DeclarationKind $kind,
        public string|null     $name,
        public array           $modifiers,
        public Token           $keyword,
    )
    {
    }

    public function hasModifier(string $modifier): bool
    {
        return in_array(strtolower($modifier), $this->modifiers, true);
    }
}
