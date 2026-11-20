<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\{BlockType, TokenTree};

class StatementBuilderTest extends PassTestCase
{
    public function testBlockCloserFollowedByParenthesis(): void
    {
        $tree = $this->job("<?php if (\$a) { \$b = 1; } (new Foo())->bar(); \$c = 2;")->tree;

        self::assertSame(
            ['0 <?php', '0 if ( $a ) {', '1 $b = 1 ;', '0 }', '0 ( new Foo ( ) ) -> bar ( ) ;', '0 $c = 2 ;'],
            $this->statements($tree)
        );
    }

    public function testABlockKnowsWhatItIsInside(): void
    {
        $job = $this->job('<?php class A { function f() { if ($a) { $b = 1; } } }');
        $block = $this->token($job, '$b')->block;

        self::assertSame(BlockType::Control, $block->type);
        self::assertTrue($block->isInside(BlockType::Control));
        self::assertTrue($block->isInside(BlockType::HookBody, BlockType::Function));
        self::assertTrue($block->isInside(BlockType::ClassLike));
        self::assertFalse($block->isInside(BlockType::Closure, BlockType::Namespace));
    }

    public function testDynamicStaticMethodNameIsNoBlock(): void
    {
        $tree = $this->job('<?php $a = Foo::{$b}(); $c = 1;')->tree;

        self::assertSame(['0 <?php', '0 $a = Foo :: { $b } ( ) ;', '0 $c = 1 ;'], $this->statements($tree));
    }

    public function testTraitAdaptationIsABlock(): void
    {
        $tree = $this->job('<?php class A { use B, C { B::f insteadof C; C::f as g; } }')->tree;

        self::assertSame(
            [
                '0 <?php',
                '0 class A {',
                '1 use B , C {',
                '2 B :: f insteadof C ;',
                '2 C :: f as g ;',
                '1 }',
                '0 }',
            ],
            $this->statements($tree)
        );
    }

    public function testAlternativeSyntaxIsSplitIntoBlocks(): void
    {
        $tree = $this->job('<?php if ($a): $b = 1; else: $b = 2; endif; $c = 3;')->tree;

        self::assertSame(
            ['0 <?php', '0 if ( $a ) :', '1 $b = 1 ;', '0 else :', '1 $b = 2 ;', '0 endif ;', '0 $c = 3 ;'],
            $this->statements($tree)
        );
    }

    public function testBlocksHaveTheirType(): void
    {
        $tree = $this->job('<?php namespace A { class B { function c() { if ($d) { $e = 1; } } } }')->tree;
        $types = [];

        foreach ($tree->statements() as $statement) {
            $types[] = $statement->block->type;
        }

        self::assertSame(
            [
                BlockType::File,
                BlockType::File,
                BlockType::Namespace,
                BlockType::ClassLike,
                BlockType::Function,
                BlockType::Control,
                BlockType::Function,
                BlockType::ClassLike,
                BlockType::Namespace,
                BlockType::File,
            ],
            $types
        );
    }

    public function testMatchArmsAreStatements(): void
    {
        $tree = $this->job('<?php $a = match ($b) { 1, 2 => fn() => 3, default => 4 };')->tree;

        self::assertSame(
            ['0 <?php', '0 $a = match ( $b ) {', '1 1 , 2 => fn ( ) => 3 ,', '1 default => 4', '0 } ;'],
            $this->statements($tree)
        );
    }

    /**
     * @return string[] Each statement as its depth and token texts
     */
    private function statements(TokenTree $tree): array
    {
        $statements = [];

        foreach ($tree->statements() as $statement) {
            $texts = array_map(fn($token) => $token->text, iterator_to_array($statement, false));
            $statements[] = $statement->block->depth . ' ' . implode(' ', $texts);
        }

        return $statements;
    }
}
