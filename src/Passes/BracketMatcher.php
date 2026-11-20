<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{Exceptions, Token, TokenType};

/**
 * Links each opening bracket to its closing bracket and back (```matchingBracket```), and marks the tokens that are
 * inside a string or an attribute.
 *
 * Opening brackets are (, [, {, the {$ and ${ of string interpolation, and the #[ of an attribute.
 */
#[Service]
readonly class BracketMatcher implements TokenizerPass
{
    private const array OPENERS = [
        TokenType::ROUND_BRACKET_OPEN,
        TokenType::SQUARE_BRACKET_OPEN,
        TokenType::CURLY_BRACKET_OPEN,
        T_CURLY_OPEN,
        T_DOLLAR_OPEN_CURLY_BRACES,
        T_ATTRIBUTE,
    ];

    private const array CLOSERS = [
        TokenType::ROUND_BRACKET_CLOSE,
        TokenType::SQUARE_BRACKET_CLOSE,
        TokenType::CURLY_BRACKET_CLOSE,
    ];

    public function runsAfter(): array
    {
        return [Lexer::class];
    }

    public function run(TokenizerJob $job): void
    {
        /** @var Token[] $open */
        $open = [];
        $inString = false;

        foreach ($job->tokens as $token) {
            $inString = $this->markString($token, $inString);

            if ($token->is(self::OPENERS)) {
                $open[] = $token;
            }
            elseif ($token->is(self::CLOSERS)) {
                $opener = array_pop($open);

                if ($opener === null) {
                    throw new Exceptions\BracketsAreUnbalanced($token->text, $token->line);
                }

                $opener->matchingBracket = $token;
                $token->matchingBracket = $opener;
            }
        }

        if ($open) {
            throw new Exceptions\BracketsAreUnbalanced($open[0]->text, $open[0]->line);
        }

        $this->markAttributes($job->tokens);
    }

    /**
     * The tokens between the quotes of "...", and between the start and end of a heredoc, are in a string. The quotes
     * themselves and the heredoc start are not, the heredoc end is.
     */
    private function markString(Token $token, bool $inString): bool
    {
        if ($inString && $token->is(TokenType::DOUBLE_QUOTE)) {
            $token->inString = false;

            return false;
        }

        $token->inString = $inString;

        if ($token->is([TokenType::DOUBLE_QUOTE, T_START_HEREDOC])) {
            return true;
        }

        if ($token->is(T_END_HEREDOC)) {
            return false;
        }

        return $inString;
    }

    /**
     * The tokens after #[ up to and including its closing ] are in the attribute; that ] closes it.
     *
     * @param Token[] $tokens
     */
    private function markAttributes(array $tokens): void
    {
        $closer = null;

        foreach ($tokens as $token) {
            if ($closer !== null) {
                $token->inAttribute = true;

                if ($token === $closer) {
                    $token->closesAttribute = true;
                    $closer = null;
                }
            }
            elseif ($token->is(T_ATTRIBUTE)) {
                $closer = $token->matchingBracket;
            }
        }
    }
}
