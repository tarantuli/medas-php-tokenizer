<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
readonly class StatementTypeFinder
{
    public function __construct(
        private TokenGroups $tokenGroups,
    )
    {
    }

    public function for(Statement $statement): StatementTypes\StatementType
    {
        return $statement->type(fn() => $this->determineType($statement));
    }

    private function determineType(Statement $statement): StatementTypes\StatementType
    {
        if ($statement->rootStatement) {
            $statement = $statement->rootStatement;
        }

        $firstToken = $statement->firstToken();
        $secondToken = $statement->getToken(1);

        if (null === $firstToken) {
            return StatementTypes\BlankLine::instance();
        }

        if ($firstToken->is(T_OPEN_TAG)) {
            return StatementTypes\PhpOpenTag::instance();
        }

        if ($firstToken->is(T_DECLARE)) {
            return StatementTypes\DeclareStatement::instance();
        }

        if ($firstToken->is($this->tokenGroups->comments())) {
            return StatementTypes\Comment::instance();
        }

        if ($firstToken->is(T_NAMESPACE)) {
            return StatementTypes\NamespaceDeclaration::instance();
        }

        if ($firstToken->is(T_USE)) {
            if ($secondToken->is(T_FUNCTION)) {
                return StatementTypes\UseFunctionStatement::instance();
            }
            elseif ($secondToken->is(T_CONST)) {
                return StatementTypes\UseConstStatement::instance();
            }

            return StatementTypes\UseClassStatement::instance();
        }

        if ($firstToken->is(T_ATTRIBUTE)) {
            return StatementTypes\AttributeStatement::instance();
        }

        if ($firstToken->is(T_CURLY_BRACKET_CLOSE)) {
            return StatementTypes\BlockCloser::instance();
        }

        if ($statement->containsType(T_CLASS)) {
            return StatementTypes\ClassDeclaration::instance();
        }

        if ($statement->containsType(T_CONST)) {
            return StatementTypes\ClassConstDeclaration::instance();
        }

        if ($statement->containsType(T_FUNCTION)) {
            return StatementTypes\FunctionDeclaration::instance();
        }

        if ($statement->containsType($this->tokenGroups->visibilityKeywords())) {
            // It's not a class const or class method, those were found before
            return StatementTypes\ClassPropertyDeclaration::instance();
        }

        if ($firstToken->is($this->tokenGroups->controlKeywords())) {
            return StatementTypes\ControlStatement::instance();
        }

        if ($firstToken->is(T_CASE)) {
            return StatementTypes\SwitchBranch::instance();
        }

        if ($firstToken->is(T_DEFAULT) && !$secondToken->is(T_DOUBLE_ARROW)) {
            // A default followed by a double arrow is a match branch
            return StatementTypes\SwitchBranch::instance();
        }

        if ($firstToken->is(T_RETURN)) {
            return StatementTypes\ReturnStatement::instance();
        }

        if ($firstToken->is(T_THROW)) {
            return StatementTypes\ThrowStatement::instance();
        }

        return StatementTypes\GenericStatement::instance();
    }
}
