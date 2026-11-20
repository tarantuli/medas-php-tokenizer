<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\BlockType;
use PHPUnit\Framework\Attributes\DataProvider;

class BlockTypeResolverTest extends PassTestCase
{
    /**
     * @return array<string, array{string, string, int, BlockType|null}> Code, the text of the opener, which occurrence
     *     of it, and the block type it opens
     */
    public static function openers(): array
    {
        return [
            'class' => ['final class A extends B implements C, D {}', '{', 1, BlockType::ClassLike],
            'interface' => ['interface A extends B, C {}', '{', 1, BlockType::ClassLike],
            'backed enum' => ['enum A: string implements B {}', '{', 1, BlockType::ClassLike],
            'anonymous class' => ['$a = new class (1) extends B {};', '{', 1, BlockType::ClassLike],
            'anonymous class without arguments' => ['$a = new class {};', '{', 1, BlockType::ClassLike],
            'function' => ['function f(int $a): ?string {}', '{', 1, BlockType::Function],
            'by-reference function' => ['function &f() {}', '{', 1, BlockType::Function],
            'method' => ['class A { public static function f(): static {} }', '{', 2, BlockType::Function],
            'DNF return type' => ['function f(): (A&B)|null {}', '{', 1, BlockType::Function],
            'closure' => ['$f = function ($a) use ($b): int {};', '{', 1, BlockType::Closure],
            'static closure' => ['$f = static function () {};', '{', 1, BlockType::Closure],
            'if' => ['if ($a) {}', '{', 1, BlockType::Control],
            'else' => ['if ($a) {} else {}', '{', 2, BlockType::Control],
            'try' => ['try {} catch (A|B $e) {} finally {}', '{', 1, BlockType::Control],
            'catch' => ['try {} catch (A|B $e) {} finally {}', '{', 2, BlockType::Control],
            'finally' => ['try {} catch (A|B $e) {} finally {}', '{', 3, BlockType::Control],
            'do' => ['do {} while ($a);', '{', 1, BlockType::Control],
            'foreach' => ['foreach ($a as $b) {}', '{', 1, BlockType::Control],
            'switch' => ['switch ($a) {}', '{', 1, BlockType::Switch],
            'match' => ['$a = match ($b) { default => 1 };', '{', 1, BlockType::Match],
            'braced namespace' => ['namespace A\B {}', '{', 1, BlockType::Namespace],
            'global namespace' => ['namespace {}', '{', 1, BlockType::Namespace],
            'trait adaptation' => ['class A { use B, C { B::f insteadof C; } }', '{', 2, BlockType::TraitAdaptation],
            'bare block' => ['$a = 1; { $b = 2; }', '{', 1, BlockType::Plain],
            'property name' => ['$a->{$b} = 1;', '{', 1, null],
            'nullsafe property name' => ['$a?->{$b}();', '{', 1, null],
            'static method name' => ['Foo::{$b}();', '{', 1, null],
            'variable variable' => ['${"a"} = 1;', '{', 1, null],
            'group import' => ['use A\{B, C};', '{', 1, null],
            'alternative if' => ['if ($a): endif;', ':', 1, BlockType::Control],
            'alternative else' => ['if ($a): else: endif;', ':', 2, BlockType::Control],
            'alternative switch' => ['switch ($a): endswitch;', ':', 1, BlockType::Switch],
            'ternary' => ['$a = $b ? ($c) : $d;', ':', 1, null],
            'return type' => ['function f(): int {}', ':', 1, null],
            'switch case' => ['switch ($a) { case (1): break; }', ':', 1, null],
        ];
    }

    #[DataProvider('openers')]
    public function testOpener(string $code, string $text, int $occurrence, BlockType|null $type): void
    {
        $job = $this->job('<?php ' . $code);

        self::assertSame($type, $this->token($job, $text, $occurrence)->opensBlock);
    }

    public function testPropertyHooks(): void
    {
        $job = $this->job('<?php class A { public int $a { get => 1; set (int $v) { $this->a = $v; } } }');

        self::assertSame(BlockType::HookList, $this->token($job, '{', 2)->opensBlock);
        self::assertSame(BlockType::HookBody, $this->token($job, '{', 3)->opensBlock);
    }

    public function testPromotedPropertyHooks(): void
    {
        $job = $this->job('<?php class A { public function __construct(public int $a { set => $v; }, $b = 1) {} }');

        self::assertSame(BlockType::HookList, $this->token($job, '{', 2)->opensBlock);
        self::assertSame(BlockType::Function, $this->token($job, '{', 3)->opensBlock);
    }

    public function testClosersMatchTheirOpeners(): void
    {
        $job = $this->job('<?php if ($a) { $b->{$c}; }');

        self::assertTrue($this->token($job, '}', 2)->closesBlock);
        self::assertFalse($this->token($job, '}', 1)->closesBlock);
    }

    public function testAlternativeSyntaxIsClosedByItsKeywords(): void
    {
        $job = $this->job('<?php if ($a): if ($b) { } else { } elseif ($c): else: endif;');

        // The braced else belongs to the inner if, so it doesn't close the alternative syntax block
        self::assertFalse($this->token($job, 'else', 1)->closesBlock);
        self::assertTrue($this->token($job, 'elseif')->closesBlock);
        self::assertTrue($this->token($job, 'else', 2)->closesBlock);
        self::assertTrue($this->token($job, 'endif')->closesBlock);
    }

    public function testBracketDepthDoesNotCountBlocks(): void
    {
        $job = $this->job('<?php function f() { foo([1, function () { return 2; }]); }');

        self::assertSame(0, $this->token($job, 'foo')->bracketDepth);
        self::assertSame(0, $this->token($job, '(', 2)->bracketDepth);
        self::assertSame(2, $this->token($job, '1')->bracketDepth);
        self::assertSame(2, $this->token($job, 'return')->bracketDepth);
        self::assertSame(0, $this->token($job, ')', 3)->bracketDepth);
    }
}
