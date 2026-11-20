<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\{
    Exceptions\CircularPassDependency,
    Exceptions\PassDependencyNotFound,
    Passes\BlockTypeResolver,
    Passes\BracketMatcher,
    Passes\Lexer,
    Passes\StatementBuilder,
    Passes\TokenizerPasses,
    Passes\TypeDeclarationMarker
};
use PHPUnit\Framework\TestCase;

class TokenizerPassesTest extends TestCase
{
    public function testEveryPassRunsAfterItsDependencies(): void
    {
        $names = array_map(fn($pass) => $pass::class, service(TokenizerPasses::class)->ordered());
        $position = array_flip($names);

        self::assertTrue($position[Lexer::class] < $position[BracketMatcher::class]);
        self::assertTrue($position[BracketMatcher::class] < $position[BlockTypeResolver::class]);
        self::assertTrue($position[BlockTypeResolver::class] < $position[StatementBuilder::class]);
        self::assertTrue($position[StatementBuilder::class] < $position[TypeDeclarationMarker::class]);
    }

    public function testMissingDependencyThrows(): void
    {
        $this->expectException(PassDependencyNotFound::class);

        service(TokenizerPasses::class)->order([new FakePass([Lexer::class])]);
    }

    public function testCircularDependencyThrows(): void
    {
        $this->expectException(CircularPassDependency::class);

        service(TokenizerPasses::class)->order([new FakePass([FakePass::class])]);
    }
}
