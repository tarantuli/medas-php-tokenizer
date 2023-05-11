<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\ClassAnalyser;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{Token, TokenGroups, TokenTree};

/**
 * Finds the namespace of the file, the class name, the class type (class, interface, trait)
 * and modifiers (abstract, final).
 */
#[Service]
class NameFinder
{
    public function __construct(
        private readonly TokenGroups $tokenGroups,
    )
    {
    }

    public function find(TokenTree $tree, ClassAnalysis $results): void
    {
        foreach ($tree as $token) {
            if ($token->is(T_NAMESPACE)) {
                $results->namespace = $token->next->text;
            }

            if ($token->is($this->tokenGroups->structureTypes())) {
                $this->processName($token, $results);

                // We found both the namespace and the name, we're done!
                return;
            }
        }
    }

    private function processName(Token $token, ClassAnalysis $results): void
    {
        $results->name = $token->next->text;
        $results->fqn = '\\' . $results->namespace . '\\' . $results->name;

        match ($token->id) {
            T_CLASS => $results->isClass = true,
            T_INTERFACE => $results->isInterface = true,
            T_TRAIT => $results->isTrait = true,
        };

        if ($token->statement->containsType(T_FINAL)) {
            $results->isFinal = true;
        }

        if ($token->statement->containsType(T_ABSTRACT)) {
            $results->isAbstract = true;
        }
    }
}
