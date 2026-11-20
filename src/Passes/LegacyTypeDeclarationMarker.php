<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{Token, TokenGroups};

/**
 * Sets ```inTypeDeclaration``` on the types of parameters, return types and properties. This is the logic of the
 * former StructureFinder, kept as it was until @class(TypeDeclarationMarker) replaces it in 4.0.
 *
 * @deprecated Replaced by the type declaration kinds in php-tokenizer 4.0
 */
#[Service]
readonly class LegacyTypeDeclarationMarker implements TokenizerPass
{
    private array $typeDeclarationTypes;
    private array $visibilityKeywords;

    public function __construct(
        TokenGroups $tokenGroups,
    )
    {
        $this->typeDeclarationTypes = $tokenGroups->typeDeclarationTypes();
        $this->visibilityKeywords = $tokenGroups->visibilityKeywords();
    }

    public function runsAfter(): array
    {
        return [BracketMatcher::class];
    }

    public function run(TokenizerJob $job): void
    {
        $state = new TypeDeclarationState();

        foreach ($job->tokens as $token) {
            $this->check($state, $token);
        }
    }

    private function check(TypeDeclarationState $state, Token $token): void
    {
        if ($state->inArguments
                && !$state->inDefaultValue
                && $state->hookDepth === 0
                && !$token->inAttribute
                && $token->next
                && $token->is($this->typeDeclarationTypes)) {
            $token->inTypeDeclaration = true;
        }

        if ($state->inArguments && !$token->inAttribute) {
            $atArgumentLevel = $state->argumentsDepth === 0 && $state->hookDepth === 0;

            if ($token->is([T_ROUND_BRACKET_OPEN, T_SQUARE_BRACKET_OPEN])) {
                ++$state->argumentsDepth;
            }
            elseif ($token->is(T_SQUARE_BRACKET_CLOSE)) {
                --$state->argumentsDepth;
            }
            elseif ($token->is(T_CURLY_BRACKET_OPEN)) {
                ++$state->hookDepth;
            }
            elseif ($token->is(T_CURLY_BRACKET_CLOSE)) {
                --$state->hookDepth;
            }
            elseif ($atArgumentLevel && $token->is(T_ASSIGNMENT)) {
                $state->inDefaultValue = true;
            }
            elseif ($atArgumentLevel && $token->is(T_COMMA)) {
                $state->inDefaultValue = false;
            }
        }

        if ($state->nextValueIsReturnType) {
            if ($token->is($this->typeDeclarationTypes)) {
                $token->inTypeDeclaration = true;
            }
            else {
                $state->nextValueIsReturnType = false;
            }
        }

        if ($state->nextNextValueIsReturnType) {
            $state->nextValueIsReturnType = true;
            $state->nextNextValueIsReturnType = false;
        }

        // "use function A\b;" imports a function, it doesn't declare one
        if ($token->is([T_FN, T_FUNCTION]) && !$token->previous?->is(T_USE)) {
            $state->nextBracesIsArguments = true;
        }

        if ($state->nextBracesIsArguments && !$token->inAttribute && $token->is(T_ROUND_BRACKET_OPEN)) {
            $state->inArguments = true;
            $state->argumentsDepth = 0;
            $state->hookDepth = 0;
            $state->inDefaultValue = false;
            $state->nextBracesIsArguments = false;
        }

        if ($state->inArguments && !$token->inAttribute && $token->is(T_ROUND_BRACKET_CLOSE)) {
            if ($state->argumentsDepth > 0) {
                // This closes a nested call or a DNF type group, not the argument list
                --$state->argumentsDepth;
            }
            else {
                $state->inArguments = false;
                $state->inDefaultValue = false;

                if ($token->next && $token->next->is(T_COLON)) {
                    $state->nextNextValueIsReturnType = true;
                }
            }
        }

        if ($state->afterVisibilityKeyword) {
            if ($token->is($this->typeDeclarationTypes)) {
                $token->inTypeDeclaration = true;
            }
            elseif ($token->is([T_FUNCTION, T_VARIABLE, T_CONST])) {
                $state->afterVisibilityKeyword = false;
            }
        }

        if ($token->is($this->visibilityKeywords)) {
            $state->afterVisibilityKeyword = true;
        }
    }
}
