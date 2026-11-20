<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

/**
 * Sets the context of every token: global scope, class declaration, class body, method declaration, parameters,
 * return type, method body or property hook.
 */
#[Service]
readonly class ContextAdder implements Passes\TokenizerPass
{
    public function __construct(
        private StatementTypeFinder $typeFinder,
    )
    {
    }

    public function runsAfter(): array
    {
        return [Passes\StatementBuilder::class];
    }

    public function run(Passes\TokenizerJob $job): void
    {
        $this->add($job->tree);
    }

    public function add(TokenTree $tree): void
    {
        foreach ($tree->statements() as $statement) {
            $context = $this->contextOfBlock($statement->block);
            $statementType = $this->typeFinder->for($statement);

            if ($statementType instanceof StatementTypes\FunctionDeclaration) {
                $this->addToFunctionDeclaration($statement, $context instanceof Contexts\ClassBody);

                continue;
            }

            if ($statementType instanceof StatementTypes\ClassDeclaration) {
                $context = Contexts\ClassDeclaration::instance();
            }

            foreach ($statement as $token) {
                $token->context = $context;
            }
        }
    }

    /**
     * The block a statement is in tells its context. A control structure, closure or match has the context of the
     * block around it.
     */
    private function contextOfBlock(Block $block): Contexts\Context
    {
        return match ($block->type) {
            BlockType::ClassLike, BlockType::TraitAdaptation => Contexts\ClassBody::instance(),
            BlockType::Function => Contexts\MethodBody::instance(),
            BlockType::HookList, BlockType::HookBody => Contexts\PropertyHook::instance(),
            BlockType::File, BlockType::Namespace => Contexts\GlobalScope::instance(),
            default => $block->opener === null
                ? Contexts\GlobalScope::instance()
                : $this->contextOfBlock($block->opener->block),
        };
    }

    /**
     * The keyword and name of a function or method have its declaration context, the parameters between the outer
     * parentheses the parameter context, and what follows them the return type context.
     */
    private function addToFunctionDeclaration(Statement $statement, bool $isMethod): void
    {
        $context = $isMethod ? Contexts\MethodDeclaration::instance() : Contexts\FunctionDeclaration::instance();
        $openParentheses = 0;

        foreach ($statement as $token) {
            $token->context = $context;

            if ($token->inAttribute) {
                continue;
            }

            if ($token->is(T_ROUND_BRACKET_OPEN)) {
                ++$openParentheses;

                $context = Contexts\MethodParameters::instance();
            }
            elseif ($token->is(T_ROUND_BRACKET_CLOSE) && --$openParentheses === 0) {
                $token->context = Contexts\MethodDeclaration::instance();
                $context = Contexts\MethodReturnType::instance();
            }
        }
    }
}
