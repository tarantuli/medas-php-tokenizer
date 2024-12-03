<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
readonly class ContextAdder
{
    public function __construct(
        private StatementTypeFinder $typeFinder,
    )
    {
    }

    public function add(TokenTree $tree): void
    {
        $context = Contexts\GlobalScope::instance();
        $globalScopeDepth = null;
        $classBodyDepth = null;
        $nextStatementIsClassBody = false;
        $nextStatementIsMethodBody = false;

        foreach ($tree->statements() as $statement) {
            if ($statement->block->depth === $globalScopeDepth) {
                $context = Contexts\GlobalScope::instance();
                $globalScopeDepth = null;
            }

            if ($statement->block->depth === $classBodyDepth) {
                $context = Contexts\ClassBody::instance();
            }

            if ($nextStatementIsClassBody) {
                $context = Contexts\ClassBody::instance();
                $classBodyDepth = $statement->block->depth;
                $nextStatementIsClassBody = false;
            }

            if ($nextStatementIsMethodBody) {
                $context = Contexts\MethodBody::instance();
                $nextStatementIsMethodBody = false;
            }

            $statementType = $this->typeFinder->for($statement);

            if ($statementType instanceof StatementTypes\ClassDeclaration) {
                $context = Contexts\ClassDeclaration::instance();
                $globalScopeDepth = $statement->block->depth;
                $nextStatementIsClassBody = true;
            }

            // If the statement type is UseClassStatement, but we're already in a class body,
            // then it's a UseTraitStatement. The type finder can't know that, but we can.
            if ($statementType instanceof StatementTypes\UseClassStatement && $context instanceof Contexts\ClassBody) {
                $statement->setType(StatementTypes\UseTraitStatement::instance());
            }

            if ($statementType instanceof StatementTypes\FunctionDeclaration) {
                // In the context of a class body, this is a method declaration; otherwise, it's a function declaration
                $context = ($context instanceof Contexts\ClassBody)
                    ? Contexts\MethodDeclaration::instance()
                    : Contexts\FunctionDeclaration::instance();

                // If it ends in a semicolon, it's an abstract or interface declaration
                // If it ends in a curly bracket open, a body will follow
                if ($statement->lastToken()->is(T_CURLY_BRACKET_OPEN)) {
                    $nextStatementIsMethodBody = true;
                }

                $openParentheses = 0;
            }

            foreach ($statement as $token) {
                $token->context = $context;

                if (!$token->inAttribute && $statementType instanceof StatementTypes\FunctionDeclaration) {
                    // The context of the following tokens may change
                    if ($token->is(T_ROUND_BRACKET_OPEN)) {
                        ++$openParentheses;

                        $context = Contexts\MethodParameters::instance();
                    }

                    if ($token->is(T_ROUND_BRACKET_CLOSE)) {
                        $token->context = Contexts\MethodDeclaration::instance();

                        if (--$openParentheses === 0) {
                            $context = Contexts\MethodReturnType::instance();
                        }
                    }
                }
            }
        }
    }
}
