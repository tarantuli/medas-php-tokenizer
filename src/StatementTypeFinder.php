<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
readonly class StatementTypeFinder
{
    private const VALID_FUNCTION_LEADERS = [
        T_ATTRIBUTE,
        T_COMMENT,
        T_DOC_COMMENT,
        T_FINAL,
        T_ABSTRACT,
        T_PROTECTED,
        T_PRIVATE,
        T_PUBLIC,
        T_STATIC,
    ];

    private array $structureTypes;
    private array $visibilityKeywords;
    private array $controlKeywords;

    public function __construct(
        private TokenGroups $tokenGroups,
    )
    {
        $this->structureTypes = $this->tokenGroups->structureTypes();
        $this->visibilityKeywords = $this->tokenGroups->visibilityKeywords();
        $this->controlKeywords = $this->tokenGroups->controlKeywords();
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

        $index = 0;

        do {
            $firstToken = $statement->getToken($index);

            ++$index;
        } while ($firstToken && (
            $firstToken->is(T_ATTRIBUTE)
            || $firstToken->is(T_DOC_COMMENT)
            || $firstToken->is(T_COMMENT)
        ));

        $secondToken = $statement->getToken($index);

        if (null === $firstToken) {
            if ($statement->getToken(0)) {
                return StatementTypes\Comment::instance();
            }

            return StatementTypes\BlankLine::instance();
        }

        if ($firstToken->is(T_OPEN_TAG)) {
            return StatementTypes\PhpOpenTag::instance();
        }

        if ($firstToken->is(T_DECLARE)) {
            return StatementTypes\DeclareStatement::instance();
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

        if ($statement->containsType($this->structureTypes)) {
            return StatementTypes\ClassDeclaration::instance();
        }

        if ($statement->containsType(T_CONST)) {
            return StatementTypes\ClassConstDeclaration::instance();
        }

        foreach ($statement as $token) {
            if ($token->is(self::VALID_FUNCTION_LEADERS) || $token->inAttribute) {
                continue;
            }

            if ($token->is(T_FUNCTION)) {
                return StatementTypes\FunctionDeclaration::instance();
            }

            break;
        }

        if ($statement->containsType($this->visibilityKeywords)) {
            // It's not a class const or class method, those were found before
            return StatementTypes\ClassPropertyDeclaration::instance();
        }

        if ($firstToken->is($this->controlKeywords)) {
            return StatementTypes\ControlStatement::instance();
        }

        if ($firstToken->is(T_CASE)) {
            foreach ($statement as $token) {
                if ($token->is(T_COLON)) {
                    return StatementTypes\SwitchBranch::instance();
                }
            }

            return StatementTypes\EnumCase::instance();
        }

        if ($firstToken->is(T_DEFAULT) && $secondToken && !$secondToken->is(T_DOUBLE_ARROW)) {
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
