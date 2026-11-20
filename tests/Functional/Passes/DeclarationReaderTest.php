<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\DeclarationKind;

class DeclarationReaderTest extends PassTestCase
{
    public function testDeclarations(): void
    {
        $tree = $this->job(<<<'PHP'
            <?php
            /** Doc */
            #[Attr]
            abstract class A
            {
                use B;
                public const int C = 1;
                #[Inject] private static ?D $e = null;
                final public function &f(): void { $closure = function () {}; }
            }
            enum G: string
            {
                case H = 'h';
            }
            function i() {}
            PHP)->tree;

        $declarations = [];

        foreach ($tree->statements() as $statement) {
            $declaration = $statement->declaration;

            if ($declaration !== null) {
                $declarations[] = [$declaration->kind, $declaration->name, $declaration->modifiers];
            }
        }

        self::assertEquals(
            [
                [DeclarationKind::Class_, 'A', ['abstract']],
                [DeclarationKind::TraitUse, null, []],
                [DeclarationKind::Constant, 'C', ['public']],
                [DeclarationKind::Property, '$e', ['private', 'static']],
                [DeclarationKind::Method, 'f', ['final', 'public']],
                [DeclarationKind::Enum, 'G', []],
                [DeclarationKind::EnumCase, 'H', []],
                [DeclarationKind::Function, 'i', []],
            ],
            $declarations
        );
    }

    public function testAttributesAreKept(): void
    {
        $tree = $this->job("<?php #[A] #[B(1)]\nclass C {}")->tree;
        $statement = iterator_to_array($tree->statements(), false)[1];

        self::assertCount(2, $statement->attributes);
        self::assertSame(']', $statement->attributes[1]->matchingBracket->text);
    }
}
