<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
class AdditionalTokensDefiner
{
    private const ADDITIONAL_TOKENS = [
        'T_AMPERSAND' => '&',
        'T_ASSIGNMENT' => '=',
        'T_ASTERISK' => '*',
        'T_AT' => '@',
        'T_COLON' => ':',
        'T_COMMA' => ',',
        'T_CONCATENATOR' => '.',
        'T_CURLY_BRACKET_CLOSE' => '}',
        'T_CURLY_BRACKET_OPEN' => '{',
        'T_DOLLAR' => '$',
        'T_DOUBLE_QUOTE' => '"',
        'T_EXCLAMATION_POINT' => '!',
        'T_LESS_THAN' => '<',
        'T_MINUS' => '-',
        'T_MOD' => '%',
        'T_MORE_THAN' => '>',
        'T_PIPE' => '|',
        'T_PLUS' => '+',
        'T_QUESTION_MARK' => '?',
        'T_ROUND_BRACKET_CLOSE' => ')',
        'T_ROUND_BRACKET_OPEN' => '(',
        'T_SEMICOLON' => ';',
        'T_SLASH' => '/',
        'T_SQUARE_BRACKET_CLOSE' => ']',
        'T_SQUARE_BRACKET_OPEN' => '[',
    ];

    private bool $haveDefined = false;

    public function define(): void
    {
        if ($this->haveDefined) {
            return;
        }

        foreach (self::ADDITIONAL_TOKENS as $tokenName => $value) {
            if (!defined($tokenName)) {
                define($tokenName, $value);
            }
        }

        $this->haveDefined = true;
    }
}
