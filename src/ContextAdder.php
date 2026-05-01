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
        $nextStatementIsPropertyHook = false;
        $propertyHookDepth = null;

        foreach ($tree->statements() as $statement) {
            if ($statement->block->depth === $globalScopeDepth) {
                $context = Contexts\GlobalScope::instance();
                $globalScopeDepth = null;
            }

            if ($statement->block->depth === $classBodyDepth) {
                $context = Contexts\ClassBody::instance();
            }

            // Exit property hook context when we return to the depth that contains the declaration
            if ($propertyHookDepth !== null && $statement->block->depth < $propertyHookDepth) {
                $propertyHookDepth = null;
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

            if ($nextStatementIsPropertyHook) {
                $context = Contexts\PropertyHook::instance();
                $propertyHookDepth = $statement->block->depth;
                $nextStatementIsPropertyHook = false;
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
                    // If the token before { is a variable (e.g. "$name {"), the { opens a promoted
                    // property hook block, not the function body itself.
                    if ($statement->getToken(-2)?->is(T_VARIABLE)) {
                        $nextStatementIsPropertyHook = true;
                    }
                    else {
                        $nextStatementIsMethodBody = true;
                    }
                }

                $openParentheses = 0;
            }

            // Detect a property hook declaration: class body statement ending with {, containing a
            // variable, but not a function (which would be a method declaration)
            if ($context instanceof Contexts\ClassBody
                    && $statement->lastToken()->is(T_CURLY_BRACKET_OPEN)
                    && !$statementType instanceof StatementTypes\FunctionDeclaration
                    && !$statementType instanceof StatementTypes\ClassDeclaration
                    && $statement->containsType(T_VARIABLE)) {
                $nextStatementIsPropertyHook = true;
            }

            // Detect a constructor body opener that follows promoted property hook blocks.
            // After all promoted hook blocks close, the remaining ", ) {" opens the method body.
            if ($context instanceof Contexts\ClassBody
                    && $statement->lastToken()->is(T_CURLY_BRACKET_OPEN)
                    && !$statementType instanceof StatementTypes\FunctionDeclaration
                    && !$statementType instanceof StatementTypes\ClassDeclaration
                    && !$statementType instanceof StatementTypes\ClassPropertyDeclaration
                    && $statement->containsType(T_ROUND_BRACKET_CLOSE)
                    && !$statement->containsType(T_VARIABLE)) {
                $nextStatementIsMethodBody = true;
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
                        if (--$openParentheses === 0) {
                            $token->context = Contexts\MethodDeclaration::instance();
                            $context = Contexts\MethodReturnType::instance();
                        }
                    }
                }
            }
        }
    }
}
