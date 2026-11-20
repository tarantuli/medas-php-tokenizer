<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

/**
 * The ids of the tokens that PHP has no ```T_*``` constant for: a single character, whose id is its character code.
 * Use them with ```Token::is()```, like ```$token->is(TokenType::COMMA)```. Being ids, they only match the token
 * itself, not a token that only looks the same, like the ```{``` of ```"{$a}"``` (```T_CURLY_OPEN```).
 *
 * Two characters are never a token of their own. An ```&``` is ```T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG``` or
 * ```T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG```, so ```AMPERSANDS``` lists both. A ```\``` on its own is
 * ```T_NS_SEPARATOR```, which ```BACKSLASH``` is.
 */
final class TokenType
{
    public const int EXCLAMATION_POINT = 33;
    public const int DOUBLE_QUOTE = 34;
    public const int DOLLAR = 36;
    public const int MOD = 37;
    public const int ROUND_BRACKET_OPEN = 40;
    public const int ROUND_BRACKET_CLOSE = 41;
    public const int ASTERISK = 42;
    public const int PLUS = 43;
    public const int COMMA = 44;
    public const int MINUS = 45;
    public const int CONCATENATOR = 46;
    public const int SLASH = 47;
    public const int COLON = 58;
    public const int SEMICOLON = 59;
    public const int LESS_THAN = 60;
    public const int ASSIGNMENT = 61;
    public const int MORE_THAN = 62;
    public const int QUESTION_MARK = 63;
    public const int AT = 64;
    public const int SQUARE_BRACKET_OPEN = 91;
    public const int SQUARE_BRACKET_CLOSE = 93;
    public const int CURLY_BRACKET_OPEN = 123;
    public const int VERTICAL_BAR = 124;
    public const int CURLY_BRACKET_CLOSE = 125;

    public const int BACKSLASH = T_NS_SEPARATOR;

    /** @var int[] */
    public const array AMPERSANDS = [
        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
    ];

    private function __construct()
    {
    }
}
