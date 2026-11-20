<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;

/**
 * Takes the tokens out of the collection, drops the whitespace tokens and stores the whitespace on the token before it
 * (```originalLineBreaksAfter```, ```originalSpacesAfter```), and links the remaining tokens with previous and next.
 */
#[Service]
readonly class Lexer implements TokenizerPass
{
    public function runsAfter(): array
    {
        return [];
    }

    public function run(TokenizerJob $job): void
    {
        $previous = null;

        foreach ($job->collection as $token) {
            if ($token->is(T_WHITESPACE)) {
                if ($previous) {
                    $lineBreaks = substr_count($token->text, "\n");
                    $previous->originalLineBreaksAfter += $lineBreaks;

                    if ($previous->originalLineBreaksAfter === 0) {
                        $previous->originalSpacesAfter += strlen($token->text);
                    }
                    else {
                        $previous->originalSpacesAfter = 0;
                    }
                }

                continue;
            }

            $token->previous = $previous;
            $token->next = null;

            if ($previous) {
                $previous->next = $token;
            }

            $job->tokens[] = $token;
            $previous = $token;
        }

        // Formatters loop over the collection too, and expect it without whitespace
        $job->collection->replaceAll($job->tokens);
    }
}
