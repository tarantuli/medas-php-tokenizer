<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\{Exceptions\BracketsAreUnbalanced, Token, TokenCollection, TokenType, TreeBuilder};

class BracketMatcherTest extends PassTestCase
{
    public function testBracketsArePaired(): void
    {
        $job = $this->job('<?php $a = [foo(1), $b->{$c}];');

        self::assertSame($this->token($job, ']'), $this->token($job, '[')->matchingBracket);
        self::assertSame($this->token($job, '['), $this->token($job, ']')->matchingBracket);
        self::assertSame($this->token($job, ')'), $this->token($job, '(')->matchingBracket);
        self::assertSame($this->token($job, '}'), $this->token($job, '{')->matchingBracket);
        self::assertNull($this->token($job, '$a')->matchingBracket);
    }

    public function testInterpolationAndAttributesArePaired(): void
    {
        $job = $this->job('<?php #[Attr([1])] function f() { return "{$a} ${b}"; }');

        self::assertSame($this->token($job, ']', 2), $this->token($job, '#[')->matchingBracket);
        self::assertSame($this->token($job, '}', 1), $this->token($job, '{', 2)->matchingBracket);
        self::assertSame($this->token($job, '}', 2), $this->token($job, '${')->matchingBracket);
    }

    /**
     * The string parts "[" and "]" of "[$b]" are text, not brackets.
     */
    public function testStringPartsAreNoBrackets(): void
    {
        $job = $this->job('<?php $a = "[$b]" . "(x {$c[1]})";');

        self::assertNull($this->token($job, '[')->matchingBracket);
        self::assertNull($this->token($job, ']')->matchingBracket);
        self::assertFalse($this->token($job, ']')->is(TokenType::SQUARE_BRACKET_CLOSE));
        self::assertFalse($this->token($job, ')')->is([TokenType::ROUND_BRACKET_CLOSE, TokenType::SEMICOLON]));
        self::assertTrue($this->token($job, ')')->is(T_ENCAPSED_AND_WHITESPACE));
        self::assertSame($this->token($job, ']', 2), $this->token($job, '[', 2)->matchingBracket);
        self::assertSame($this->token($job, '}'), $this->token($job, '{')->matchingBracket);
    }

    public function testStringsAreMarked(): void
    {
        $job = $this->job('<?php $a = "x {$b} y";');

        self::assertFalse($this->token($job, '"')->inString);
        self::assertTrue($this->token($job, '$b')->inString);
        self::assertFalse($this->token($job, '"', 2)->inString);
        self::assertFalse($this->token($job, ';')->inString);
    }

    public function testAttributesAreMarked(): void
    {
        $job = $this->job('<?php #[Attr([1])] function f() {}');

        self::assertFalse($this->token($job, '#[')->inAttribute);
        self::assertTrue($this->token($job, 'Attr')->inAttribute);
        self::assertFalse($this->token($job, ']')->closesAttribute);
        self::assertTrue($this->token($job, ']', 2)->inAttribute);
        self::assertTrue($this->token($job, ']', 2)->closesAttribute);
        self::assertFalse($this->token($job, 'function')->inAttribute);
    }

    public function testUnbalancedBracketsThrow(): void
    {
        // Without TOKEN_PARSE, so the invalid code gets tokenized
        $collection = new TokenCollection();

        foreach (Token::tokenize('<?php foo(1;') as $token) {
            $collection->add($token);
        }

        $this->expectException(BracketsAreUnbalanced::class);

        service(TreeBuilder::class)->fromCollection($collection);
    }
}
