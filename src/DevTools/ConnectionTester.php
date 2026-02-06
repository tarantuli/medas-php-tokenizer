<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\DevTools;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{Exceptions\TokensAreNotWellConnected, Statement};

#[Service]
readonly class ConnectionTester
{
    /**
     * This method checks whether the tokens in the given statement all refer to the correct neighbors via $previous
     * and $next.
     */
    public function check(Statement $statement): void
    {
        $token = null;
        $previousToken = null;

        foreach ($statement as $token) {
            if ($previousToken) {
                if ($previousToken->next !== $token || $token->previous !== $previousToken) {
                    throw new TokensAreNotWellConnected(
                        $previousToken->text,
                        $previousToken->next?->text,
                        $token->text,
                        $token->previous?->text
                    );
                }
            }
            elseif ($token->previous !== null) {
                throw new TokensAreNotWellConnected(
                    '–',
                    '–',
                    $token->text,
                    $token->previous->text
                );
            }

            $previousToken = $token;
        }

        if ($token && $token->next !== null) {
            throw new TokensAreNotWellConnected($token->text, $token->next->text, '–', '–');
        }
    }
}
