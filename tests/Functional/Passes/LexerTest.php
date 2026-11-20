<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

class LexerTest extends PassTestCase
{
    public function testWhitespaceIsDroppedAndStoredOnTheTokenBefore(): void
    {
        $job = $this->job("<?php\n\n\$a  =\n    1;\n");

        self::assertSame(['<?php', '$a', '=', '1', ';'], array_map(fn($token) => $token->text, $job->tokens));

        self::assertSame(2, $this->token($job, '<?php')->originalLineBreaksAfter);
        self::assertSame(2, $this->token($job, '$a')->originalSpacesAfter);
        self::assertSame(0, $this->token($job, '$a')->originalLineBreaksAfter);
        self::assertSame(1, $this->token($job, '=')->originalLineBreaksAfter);
        self::assertSame(0, $this->token($job, '=')->originalSpacesAfter);
        self::assertSame(0, $this->token($job, '1')->originalSpacesAfter);
        self::assertSame(1, $this->token($job, ';')->originalLineBreaksAfter);
    }

    public function testTokensAreLinkedWithoutWhitespace(): void
    {
        $job = $this->job("<?php \$a = 1;");

        self::assertNull($job->tokens[0]->previous);
        self::assertSame($job->tokens[1], $job->tokens[0]->next);
        self::assertSame($job->tokens[0], $job->tokens[1]->previous);
        self::assertNull($job->tokens[4]->next);
    }

    public function testTheCollectionHasNoWhitespaceLeft(): void
    {
        $job = $this->job("<?php \$a = 1;");

        self::assertCount(5, $job->collection);
    }
}
