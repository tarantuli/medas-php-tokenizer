<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional;

use Medas\PhpTokenizer\{
    Exceptions\StatementNotFoundInBlock,
    Exceptions\TokenNotFoundInStatementException,
    Statement,
    Token,
    TokenTree,
    TreeBuilder
};
use PHPUnit\Framework\TestCase;

/**
 * Changing statements and blocks keeps the tokens linked in the order of the tree.
 */
class TreeMutationTest extends TestCase
{
    public function testInsertedStatementIsLinked(): void
    {
        $tree = service(TreeBuilder::class)->fromCode('<?php $a = 1; $b = 2;');
        $first = $this->statement($tree, 1);

        $new = new Statement($first->block);
        $new->appendToken(Token::fromText('foo', $first->firstToken()));
        $new->appendToken(Token::fromText(';', $first->firstToken()));

        $first->block->insertStatementAfter($new, $first);

        $tree->assertConsistent();
        self::assertSame($new->firstToken(), $first->lastToken()->next);
    }

    public function testInsertedStatementInAnEmptyBlockIsLinkedToTheOpener(): void
    {
        $tree = service(TreeBuilder::class)->fromCode('<?php if ($a) { $b = 1; }');
        $body = $this->statement($tree, 2);

        $new = new Statement($body->block);
        $new->appendToken(Token::fromText('foo', $body->firstToken()));

        $body->block->insertStatementBefore($new, $body);

        $tree->assertConsistent();
        self::assertSame('{', $new->firstToken()->previous->text);
    }

    public function testRemovedStatementIsUnlinked(): void
    {
        $tree = service(TreeBuilder::class)->fromCode('<?php $a = 1; $b = 2; $c = 3;');
        $second = $this->statement($tree, 2);

        $second->block->removeStatement($second);

        $tree->assertConsistent();
        self::assertNull($second->firstToken()->previous);
    }

    public function testMovedStatementIsLinked(): void
    {
        $tree = service(TreeBuilder::class)->fromCode('<?php $a = 1; $b = 2; $c = 3;');

        $first = $this->statement($tree, 1);
        $third = $this->statement($tree, 3);

        $tree->block()->moveStatementAfter($first, $third);

        $tree->assertConsistent();
        self::assertSame($first->firstToken(), $third->lastToken()->next);
        self::assertSame('<?php', $this->statement($tree, 1)->firstToken()->previous->text);
    }

    public function testRemovedTokenIsUnlinked(): void
    {
        $tree = service(TreeBuilder::class)->fromCode('<?php $a = 1; $b = 2;');
        $second = $this->statement($tree, 2);

        $removed = $second->firstToken();

        // The first token of a statement links to the last token of the statement before
        $second->removeToken($removed);

        $tree->assertConsistent();
        self::assertSame('=', $this->statement($tree, 1)->lastToken()->next->text);
        self::assertNull($removed->previous);
        self::assertNull($removed->next);
    }

    public function testStatementNotInBlockThrows(): void
    {
        $tree = service(TreeBuilder::class)->fromCode('<?php if ($a) { $b = 1; }');

        $this->expectException(StatementNotFoundInBlock::class);

        // The statement is in the if block, not in the root block
        $tree->block()->removeStatement($this->statement($tree, 2));
    }

    public function testTokenNotInStatementThrows(): void
    {
        $tree = service(TreeBuilder::class)->fromCode('<?php $a = 1; $b = 2;');

        $this->expectException(TokenNotFoundInStatementException::class);

        $this->statement($tree, 1)->getIndex($this->statement($tree, 2)->firstToken());
    }

    public function testTokenFromTextGetsItsId(): void
    {
        $nearby = new Token(T_STRING, 'x', 7);

        self::assertSame(T_FUNCTION, Token::fromText('function', $nearby)->id);
        self::assertSame(ord('{'), Token::fromText('{', $nearby)->id);
        self::assertSame(T_VARIABLE, Token::fromText('$a', $nearby)->id);
        self::assertSame(T_STRING, Token::fromText('foo', $nearby)->id);
        self::assertSame(7, Token::fromText('foo', $nearby)->line);
    }

    private function statement(TokenTree $tree, int $index): Statement
    {
        return iterator_to_array($tree->statements(), false)[$index];
    }
}
