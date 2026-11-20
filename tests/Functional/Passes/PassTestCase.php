<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\{Passes\TokenizerJob, Passes\TokenizerPasses, Token, Tokenizer};
use PHPUnit\Framework\TestCase;

/**
 * Runs all tokenizer passes on a piece of code, and finds tokens in the result.
 */
abstract class PassTestCase extends TestCase
{
    protected function job(string $code): TokenizerJob
    {
        $job = new TokenizerJob(service(Tokenizer::class)->tokenize($code));

        foreach (service(TokenizerPasses::class)->ordered() as $pass) {
            $pass->run($job);
        }

        return $job;
    }

    /**
     * The n-th token (counting from 1) with the given text.
     */
    protected function token(TokenizerJob $job, string $text, int $n = 1): Token
    {
        foreach ($job->tokens as $token) {
            if ($token->text === $text && --$n === 0) {
                return $token;
            }
        }

        self::fail(sprintf('token "%s" not found', $text));
    }
}
