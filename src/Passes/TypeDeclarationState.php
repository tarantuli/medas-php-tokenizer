<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

/**
 * The state of @class(LegacyTypeDeclarationMarker) while it walks through the tokens of one file.
 */
class TypeDeclarationState
{
    public bool $nextBracesIsArguments = false;
    public bool $inArguments = false;

    // The number of brackets opened inside the argument list, so the ) of a nested call doesn't end it
    public int $argumentsDepth = 0;

    // The number of promoted property hook bodies opened inside the argument list; their contents aren't types
    public int $hookDepth = 0;

    // Between the = of a parameter and the next comma at argument level, nothing is a type
    public bool $inDefaultValue = false;

    public bool $nextValueIsReturnType = false;
    public bool $nextNextValueIsReturnType = false;
    public bool $afterVisibilityKeyword = false;
}
