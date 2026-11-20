<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional\Passes;

class ClassReferenceMarkerTest extends PassTestCase
{
    public function testClassReferences(): void
    {
        $job = $this->job(<<<'PHP'
            <?php
            #[Attr(Arg::class, name: CONSTANT), Other]
            final class A extends B implements C, D
            {
                use E, F { E::f insteadof F; }

                public function g(H|null $h, int $i): self
                {
                    try {
                        $j = new J();
                        $k = $j instanceof K ? L::m() : M::N;
                        $this->o::P;
                        q(R);
                    } catch (S) {
                    }
                }
            }
            PHP);

        $references = [];

        foreach ($job->tokens as $token) {
            if ($token->isClassReference) {
                $references[] = $token->text;
            }
        }

        self::assertSame(
            ['Attr', 'Arg', 'Other', 'B', 'C', 'D', 'E', 'F', 'E', 'F', 'H', 'J', 'K', 'L', 'M', 'S'],
            $references
        );
    }
}
