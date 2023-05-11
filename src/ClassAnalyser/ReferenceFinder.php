<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\ClassAnalyser;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{Contexts\MethodParameters,
    Contexts\MethodReturnType,
    StatementTypeFinder,
    StatementTypes\AttributeStatement,
    StatementTypes\UseTraitStatement,
    Token,
    TokenTree};

#[Service]
class ReferenceFinder
{
    private const INTERNAL_TYPES = [
        'bool', 'int', 'float', 'string', 'array', 'object', 'callable', 'iterable',
        'resource', 'null', 'void', 'never', 'self', 'parent', 'static', 'mixed',
        'false',
    ];

    private const REFERENCE_TYPES = [T_STRING, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED];

    public function __construct(
        private readonly FqnProperties       $fqnProperties,
        private readonly StatementTypeFinder $statementTypeFinder,
    )
    {
    }

    public function find(TokenTree $tree, ClassAnalysis $results): void
    {
        foreach ($tree as $token) {
            if ($token->is(T_EXTENDS)) {
                // Class extension declaration
                $results->extends = $this->getReference($results, $token->next);
                $this->addUsage($results, $token->next);
            }

            if ($token->is(T_IMPLEMENTS)) {
                // Class implementation declaration, could be multiple
                foreach ($this->gatherCommaSeparatedTokens($token->next) as $implementToken) {
                    $this->addImplements($results, $implementToken);
                    $this->addUsage($results, $implementToken);
                }
            }

            if ($token->is([T_NEW, T_INSTANCEOF])) {
                // Object instantiaion or instanceof comparison
                $this->addUsage($results, $token->next);
            }

            if ($token->is(T_USE) && $this->statementTypeFinder->for($token->statement) instanceof UseTraitStatement) {
                // A use trait statement, could be multiple
                foreach ($this->gatherCommaSeparatedTokens($token->next) as $useToken) {
                    $this->addUsage($results, $useToken);
                }
            }

            if ($this->couldBeClassName($token)) {
                if ($token->next->is(T_DOUBLE_COLON)) {
                    // "ClassName::..."
                    $this->addUsage($results, $token);
                }

                if ($token->context instanceof MethodParameters
                    || $token->context instanceof MethodReturnType
                    || $this->statementTypeFinder->for($token->statement) instanceof AttributeStatement) {
                    // Parameter type, return type or name within an attribute
                    $this->addUsage($results, $token);
                }
            }
        }
    }

    private function getReference(ClassAnalysis $results, Token $token): ClassReference
    {
        $reference = $this->resolveReference($results, $token);
        $token->reference = $reference;

        return $reference;
    }

    private function resolveReference(ClassAnalysis $results, Token $token): ClassReference
    {
        $label = $token->text;
        $firstPart = $this->fqnProperties->getFirstPart($label);

        if ($firstPart === '') {
            // It's an absolute path
            return new ClassReference($label, $label);
        }

        $resolvedFirstPart = $results->resolveImport($firstPart);

        if ($resolvedFirstPart === null) {
            // It's a path relative to the namespace
            $fqn = $results->namespace ? '\\' . $results->namespace . '\\' . $label : '\\' . $label;
            return new ClassReference($label, $fqn);
        }
        else {
            // It's a path relative to an alias
            return new ClassReference(
                $label,
                $resolvedFirstPart . substr($label, strlen($firstPart))
            );
        }
    }

    private function addUsage(ClassAnalysis $results, Token $token): void
    {
        $reference = $this->getReference($results, $token);
        $results->uses[$reference->label] = $reference;
    }

    private function gatherCommaSeparatedTokens(Token $token): array
    {
        $tokens = [];

        do {
            $tokens[] = $token;
            $token = $token->next->next;
        } while ($token->previous->is(T_COMMA));

        return $tokens;
    }

    private function addImplements(ClassAnalysis $results, Token $token): void
    {
        $reference = $this->getReference($results, $token);
        $results->implements[$reference->label] = $reference;
    }

    private function couldBeClassName(Token $token): bool
    {
        return $token->is(self::REFERENCE_TYPES)
            && !in_array($token->text, self::INTERNAL_TYPES, true);
    }
}
