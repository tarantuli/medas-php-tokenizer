<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\TypeDeclarationKind;
use PHPUnit\Framework\Attributes\DataProvider;

class TypeDeclarationMarkerTest extends PassTestCase
{
    /**
     * @return array<string, array{string, string[], TypeDeclarationKind}> Code, the texts of the type tokens in
     *     order, and their kind
     */
    public static function types(): array
    {
        return [
            'parameter' => ['function f(?int $a = FOO) {}', ['?', 'int'], TypeDeclarationKind::Parameter],
            'DNF parameter' => [
                'function f((A&B)|null $a) {}',
                ['(', 'A', '&', 'B', ')', '|', 'null'],
                TypeDeclarationKind::Parameter,
            ],
            'by reference and variadic' => [
                'function f(int &$a, string ...$b) {}',
                ['int', 'string'],
                TypeDeclarationKind::Parameter,
            ],
            'promoted' => [
                'class A { function __construct(private readonly B $b) {} }',
                ['B'],
                TypeDeclarationKind::Parameter,
            ],
            'closure' => ['$f = fn(int $a) => $a;', ['int'], TypeDeclarationKind::Parameter],
            'return type' => ['function f(): static {}', ['static'], TypeDeclarationKind::ReturnType],
            'DNF return type' => [
                'function f(): (A&B)|null {}',
                ['(', 'A', '&', 'B', ')', '|', 'null'],
                TypeDeclarationKind::ReturnType,
            ],
            'closure return type after use' => [
                '$f = function () use ($a): int {};',
                ['int'],
                TypeDeclarationKind::ReturnType,
            ],
            'property' => ['class A { public static ?Foo $a; }', ['?', 'Foo'], TypeDeclarationKind::Property],
            'class constant' => ['class A { const string B = "b"; }', ['string'], TypeDeclarationKind::ClassConstant],
            'catch' => ['try {} catch (A|B $e) {}', ['A', '|', 'B'], TypeDeclarationKind::Catch],
        ];
    }

    #[DataProvider('types')]
    public function testTypes(string $code, array $texts, TypeDeclarationKind $kind): void
    {
        $job = $this->job('<?php ' . $code);
        $marked = [];

        foreach ($job->tokens as $token) {
            if ($token->typeDeclaration !== null) {
                self::assertSame($kind, $token->typeDeclaration);

                $marked[] = $token->text;
            }
        }

        self::assertSame($texts, $marked);
    }

    public function testDefaultValuesAndUntypedConstantsAreNoTypes(): void
    {
        $job = $this->job('<?php class A { const B = 1; function f($a = C::D, $b = [E::F]) {} }');

        foreach ($job->tokens as $token) {
            self::assertNull($token->typeDeclaration, $token->text);
        }
    }

    public function testInTypeDeclarationKeepsItsOldMeaning(): void
    {
        $job = $this->job('<?php function f(A&B $a): static {}');

        self::assertTrue($this->token($job, 'A')->inTypeDeclaration);
        self::assertFalse($this->token($job, '&')->inTypeDeclaration);
        self::assertFalse($this->token($job, 'static')->inTypeDeclaration);
    }
}
