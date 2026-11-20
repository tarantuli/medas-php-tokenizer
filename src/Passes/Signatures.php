<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\PhpTokenizer\{BlockType, Token};

/**
 * Recognises the parameter lists and return types of functions, methods, closures, arrow functions and set hooks.
 * Needs the matching brackets, and for set hooks the block types.
 */
final class Signatures
{
    private const array AMPERSANDS = [
        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
    ];

    /**
     * Whether a ( opens the parameters of a declaration: function f(, function &f(, function (, fn (, set (.
     */
    public static function isParameterList(Token $open): bool
    {
        if (!$open->is(T_ROUND_BRACKET_OPEN)) {
            return false;
        }

        $previous = $open->previous;

        if ($previous?->is(self::AMPERSANDS)) {
            $previous = $previous->previous;
        }

        if ($previous === null) {
            return false;
        }

        if ($previous->is([T_FUNCTION, T_FN])) {
            return true;
        }

        $beforeName = $previous->previous;

        if ($beforeName?->is(self::AMPERSANDS)) {
            $beforeName = $beforeName->previous;
        }

        if ($beforeName?->is(T_FUNCTION) && preg_match('/^\w+$/', $previous->text)) {
            return true;
        }

        // set (int $value) { ... } in a property hook list
        return $previous->is(T_STRING)
            && isset($open->block)
            && $open->block->type === BlockType::HookList;
    }

    /**
     * Whether a : starts a return type: ") : int", also after the use clause of a closure.
     */
    public static function isReturnTypeColon(Token $colon): bool
    {
        $close = $colon->previous;

        if (!$colon->is(T_COLON) || !$close?->is(T_ROUND_BRACKET_CLOSE) || $close->matchingBracket === null) {
            return false;
        }

        $open = $close->matchingBracket;

        if ($open->previous?->is(T_USE) && $open->previous->previous?->is(T_ROUND_BRACKET_CLOSE)) {
            // function (...) use (...): int
            $open = $open->previous->previous->matchingBracket;
        }

        return $open !== null && self::isParameterList($open);
    }
}
