<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\StructureFinder;

class TypeDeclarationState
{
    public bool $nextBracesIsArguments = false;
    public bool $inArguments = false;
    public bool $nextValueIsReturnType = false;
    public bool $nextNextValueIsReturnType = false;
}
