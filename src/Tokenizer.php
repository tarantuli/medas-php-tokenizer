<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
readonly class Tokenizer
{
    public function __construct(
        private AdditionalTokensDefiner $additionalTokensDefiner,
    )
    {
        $this->additionalTokensDefiner->define();
    }

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
                $token->text = rtrim($token->text);
            }

            $collection->add($token);
        }
    }
}
