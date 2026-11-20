<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

use Medas\PhpTokenizer\Passes\{TokenizerJob, TokenizerPass};

/**
 * A pass with the given dependencies, for testing the order of passes. It is not a #[Service], so it isn't found.
 */
class FakePass implements TokenizerPass
{
    public function __construct(private array $runsAfter)
    {
    }

    public function runsAfter(): array
    {
        return $this->runsAfter;
    }

    public function run(TokenizerJob $job): void
    {
    }
}
