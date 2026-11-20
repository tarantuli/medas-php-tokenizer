<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\TokenRole;
use PHPUnit\Framework\Attributes\DataProvider;

class RoleAssignerTest extends PassTestCase
{
    /**
     * @return array<string, array{string, string, int, TokenRole|null}> Code, the token text, which occurrence of
     *     it, and its role
     */
    public static function roles(): array
    {
        return [
            'ternary then' => ['$a = $b ? $c : $d;', '?', 1, TokenRole::TernaryThen],
            'ternary else' => ['$a = $b ? $c : $d;', ':', 1, TokenRole::TernaryElse],
            'nested ternary else' => ['$a = $b ? ($c ? 1 : 2) : 3;', ':', 2, TokenRole::TernaryElse],
            'elvis' => ['$a = $b ?: $c;', '?', 1, TokenRole::Elvis],
            'elvis colon' => ['$a = $b ?: $c;', ':', 1, TokenRole::Elvis],
            'nullable' => ['function f(?int $a) {}', '?', 1, TokenRole::NullableMarker],
            'named argument' => ['f($a ? 1 : 2, name: 3);', ':', 2, TokenRole::NamedArgumentColon],
            'return type' => ['function f(): int {}', ':', 1, TokenRole::ReturnTypeColon],
            'case' => ['switch ($a) { case $b ? 1 : 2: break; }', ':', 2, TokenRole::CaseColon],
            'case ternary' => ['switch ($a) { case $b ? 1 : 2: break; }', ':', 1, TokenRole::TernaryElse],
            'default' => ['switch ($a) { default: break; }', ':', 1, TokenRole::CaseColon],
            'alternative syntax' => ['if ($a): endif;', ':', 1, TokenRole::AlternativeSyntaxColon],
            'enum backing' => ['enum A: string {}', ':', 1, TokenRole::EnumBackingColon],
            'goto label' => ['label: goto label;', ':', 1, TokenRole::GotoLabelColon],
            'by-reference parameter' => ['function f(array &$a) {}', '&', 1, TokenRole::ByReference],
            'by-reference foreach' => ['foreach ($a as &$b) {}', '&', 1, TokenRole::ByReference],
            'by-reference assignment' => ['$a = &$b;', '&', 1, TokenRole::ByReference],
            'by-reference return' => ['function &f() {}', '&', 1, TokenRole::ByReference],
            'bitwise and' => ['$a = $b & $c;', '&', 1, null],
            'intersection type' => ['function f(A&B $a) {}', '&', 1, null],
            'variadic' => ['function f(int ...$a) {}', '...', 1, TokenRole::Variadic],
            'spread' => ['f(...$a);', '...', 1, TokenRole::Spread],
            'first-class callable' => ['$f = strlen(...);', '...', 1, TokenRole::FirstClassCallable],
        ];
    }

    #[DataProvider('roles')]
    public function testRole(string $code, string $text, int $occurrence, TokenRole|null $role): void
    {
        $job = $this->job('<?php ' . $code);

        self::assertSame($role, $this->token($job, $text, $occurrence)->role);
    }
}
