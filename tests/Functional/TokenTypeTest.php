<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional;

use Medas\PhpTokenizer\{Token, TokenType};
use PHPUnit\Framework\TestCase;

class TokenTypeTest extends TestCase
{
    /**
     * Code with every character of ```TokenType``` as a token, and the ```TokenType``` constant per character.
     */
    private const string CODE = '<?php use A\{B}; $a = &$b & $c; $a = !$b * 2 / 3 % 4 + 5 - 6 . "x$y";'
        . ' $q = $a ? [$b] : $c, $o = ($a < $b) | ($c > $d); @f(); $$v;';

    private const array CHARACTERS = [
        '!' => TokenType::EXCLAMATION_POINT,
        '"' => TokenType::DOUBLE_QUOTE,
        '$' => TokenType::DOLLAR,
        '%' => TokenType::MOD,
        '(' => TokenType::ROUND_BRACKET_OPEN,
        ')' => TokenType::ROUND_BRACKET_CLOSE,
        '*' => TokenType::ASTERISK,
        '+' => TokenType::PLUS,
        ',' => TokenType::COMMA,
        '-' => TokenType::MINUS,
        '.' => TokenType::CONCATENATOR,
        '/' => TokenType::SLASH,
        ':' => TokenType::COLON,
        ';' => TokenType::SEMICOLON,
        '<' => TokenType::LESS_THAN,
        '=' => TokenType::ASSIGNMENT,
        '>' => TokenType::MORE_THAN,
        '?' => TokenType::QUESTION_MARK,
        '@' => TokenType::AT,
        '[' => TokenType::SQUARE_BRACKET_OPEN,
        ']' => TokenType::SQUARE_BRACKET_CLOSE,
        '{' => TokenType::CURLY_BRACKET_OPEN,
        '|' => TokenType::VERTICAL_BAR,
        '}' => TokenType::CURLY_BRACKET_CLOSE,
        '\\' => TokenType::BACKSLASH,
    ];

    public function testEveryCharacterHasTheIdPhpGivesIt(): void
    {
        $ids = [];

        foreach (Token::tokenize(self::CODE) as $token) {
            $ids[$token->text][] = $token->id;
        }

        foreach (self::CHARACTERS as $character => $id) {
            self::assertContains($id, $ids[$character] ?? [], sprintf('the id of "%s"', $character));
        }

        foreach ($ids['&'] as $id) {
            self::assertContains($id, TokenType::AMPERSANDS);
        }
    }

    public function testAnIdOnlyMatchesTheTokenItself(): void
    {
        $tokens = Token::tokenize('<?php "{$a}";');

        // The { of "{$a}" is T_CURLY_OPEN, a token that only looks like a {
        self::assertSame('{', $tokens[2]->text);
        self::assertFalse($tokens[2]->is(TokenType::CURLY_BRACKET_OPEN));
        self::assertTrue($tokens[4]->is(TokenType::CURLY_BRACKET_CLOSE));
    }
}
