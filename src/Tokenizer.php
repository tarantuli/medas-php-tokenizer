<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
readonly class Tokenizer
{
    public function tokenize(string $code): TokenCollection
    {
        $collection = new TokenCollection();

        $this->addTokens($collection, $code);

        $collection->setSourceHash(sha1($code));

        return $collection;
    }

    private function addTokens(TokenCollection $collection, string $code): void
    {
        $tokens = Token::tokenize($code, TOKEN_PARSE);

        foreach ($tokens as $token) {
            if ($token->is(T_OPEN_TAG)) {
                // The whitespace in "<?php\n" belongs to the layout, not to the tag
                $trimmed = rtrim($token->text);
                $token->originalLineBreaksAfter = substr_count(substr($token->text, strlen($trimmed)), "\n");
                $token->text = $trimmed;
            }

            $collection->add($token);
        }
    }
}
