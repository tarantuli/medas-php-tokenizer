<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional;

use Medas\PhpTokenizer\TokenGroups;
use PHPUnit\Framework\TestCase;

class TokenGroupsTest extends TestCase
{
    public function testAllTokensMentioned(): void
    {
        $class = new \ReflectionClass(TokenGroups::class);
        $code = file_get_contents($class->getFileName());
        $tokens = get_defined_constants(true);

        foreach ($tokens['tokenizer'] as $name => $value) {
            if (!str_starts_with($name, 'T_')) {
                continue;
            }

            self::assertMatchesRegularExpression(
                sprintf('/\b%s\b/', $name),
                $code,
                sprintf('code does not mention %s', $name)
            );
        }
    }
}
