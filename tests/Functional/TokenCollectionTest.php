<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional;

use Medas\PhpTokenizer\Tokenizer;
use PHPUnit\Framework\TestCase;

class TokenCollectionTest extends TestCase
{
    public function testLoop(): void
    {
        $collection = service(Tokenizer::class)->tokenize('<?php $var = 1;');

        foreach ($collection as $token) {
            self::assertEquals(T_OPEN_TAG, $token->id);
            self::assertEquals('<?php', $token->text);
            self::assertEquals(1, $token->line);
            self::assertEquals(0, $token->pos);

            break;
        }
    }
}
