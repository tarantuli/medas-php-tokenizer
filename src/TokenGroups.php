<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
class TokenGroups
{
    public function __construct(
        AdditionalTokensDefiner $additionalTokensDefiner,
    )
    {
        $additionalTokensDefiner->define();
    }

    public function texts(): array
    {
        return array_merge($this->keywords(), $this->casts(), $this->languageConstructs());
    }

    public function keywords(): array
    {
        return array_merge(
            $this->controlKeywords(),
            $this->typeOperators(),
            $this->visibilityKeywords(),
            [
                T_ABSTRACT,
                T_AS,
                T_BREAK,
                T_CALLABLE,
                T_CASE,
                T_CLASS,
                T_CLONE,
                T_CONST,
                T_CONTINUE,
                T_DEFAULT,
                T_ECHO,
                T_ENDDECLARE,
                T_ENDFOR,
                T_ENDFOREACH,
                T_ENDIF,
                T_ENDSWITCH,
                T_ENDWHILE,
                T_ENUM,
                T_EXIT,
                T_EXTENDS,
                T_FINAL,
                T_FINALLY,
                T_FUNCTION,
                T_GLOBAL,
                T_GOTO,
                T_IMPLEMENTS,
                T_INCLUDE,
                T_INCLUDE_ONCE,
                T_INSTEADOF,
                T_INTERFACE,
                T_NAMESPACE,
                T_NEW,
                T_READONLY,
                T_REQUIRE,
                T_REQUIRE_ONCE,
                T_RETURN,
                T_STATIC,
                T_THROW,
                T_TRAIT,
                T_USE,
                T_VAR,
                T_YIELD,
                T_YIELD_FROM,
                T_LOGICAL_AND,
                T_LOGICAL_OR,
                T_LOGICAL_XOR,
            ],
        );
    }

    public function controlKeywords(): array
    {
        return [
            T_CATCH,
            T_DO,
            T_ELSE,
            T_ELSEIF,
            T_FOR,
            T_FOREACH,
            T_IF,
            T_MATCH,
            T_SWITCH,
            T_TRY,
            T_WHILE,
        ];
    }

    public function typeOperators(): array
    {
        return [
            T_INSTANCEOF,
        ];
    }

    public function visibilityKeywords(): array
    {
        return [
            T_PRIVATE,
            T_PRIVATE_SET,
            T_PROTECTED,
            T_PROTECTED_SET,
            T_PUBLIC,
            T_PUBLIC_SET,
        ];
    }

    public function casts(): array
    {
        return [
            T_ARRAY_CAST,
            T_BOOL_CAST,
            T_DOUBLE_CAST,
            T_INT_CAST,
            T_OBJECT_CAST,
            T_STRING_CAST,
            T_UNSET_CAST,
        ];
    }

    public function languageConstructs(): array
    {
        return [
            T_ARRAY,
            T_DECLARE,
            T_EMPTY,
            T_EVAL,
            T_FN,
            T_HALT_COMPILER,
            T_ISSET,
            T_LIST,
            T_PRINT,
            T_UNSET,
        ];
    }

    public function symbolOperators(): array
    {
        return array_merge(
            $this->arithmicOperators(),
            $this->assignmentOperators(),
            $this->bitwiseOperators(),
            $this->comparisonOperators(),
            $this->logicalOperators(),
            $this->typeOperators(),
            [
                T_AMPERSAND,
                T_ASSIGNMENT,
                T_AT,
                T_COLON,
                T_PAAMAYIM_NEKUDOTAYIM,
                T_COMMA,
                T_DOLLAR,
                T_DOUBLE_ARROW,
                T_ELLIPSIS,
                T_EXCLAMATION_POINT,
                T_NS_SEPARATOR,
                T_QUESTION_MARK,
                T_SEMICOLON,
            ],
        );
    }

    public function arithmicOperators(): array
    {
        return [
            T_POW,
            T_MINUS,
            T_MOD,
            T_ASTERISK,
            T_CONCATENATOR,
            T_SLASH,
            T_PLUS,
        ];
    }

    public function assignmentOperators(): array
    {
        return [
            T_ASSIGNMENT,
            T_AND_EQUAL,
            T_COALESCE_EQUAL,
            T_CONCAT_EQUAL,
            T_DIV_EQUAL,
            T_MINUS_EQUAL,
            T_MOD_EQUAL,
            T_MUL_EQUAL,
            T_OR_EQUAL,
            T_PLUS_EQUAL,
            T_POW_EQUAL,
            T_SL_EQUAL,
            T_SR_EQUAL,
            T_XOR_EQUAL,
        ];
    }

    public function bitwiseOperators(): array
    {
        return [
            T_SL,
            T_SR,
        ];
    }

    public function brackets(): array
    {
        return [
            T_ROUND_BRACKET_OPEN,
            T_ROUND_BRACKET_CLOSE,
            T_SQUARE_BRACKET_OPEN,
            T_SQUARE_BRACKET_CLOSE,
            T_CURLY_BRACKET_OPEN,
            T_CURLY_BRACKET_CLOSE,
        ];
    }

    public function comparisonOperators(): array
    {
        return [
            T_COALESCE,
            T_IS_EQUAL,
            T_IS_GREATER_OR_EQUAL,
            T_IS_IDENTICAL,
            T_IS_NOT_EQUAL,
            T_IS_NOT_IDENTICAL,
            T_IS_SMALLER_OR_EQUAL,
            T_SPACESHIP,
            T_LESS_THAN,
            T_MORE_THAN,
        ];
    }

    public function logicalOperators(): array
    {
        return [
            T_BOOLEAN_AND,
            T_BOOLEAN_OR,
            T_LOGICAL_AND,
            T_LOGICAL_OR,
            T_LOGICAL_XOR,
        ];
    }

    public function incDecOperators(): array
    {
        return [
            T_DEC,
            T_INC,
        ];
    }

    public function literals(): array
    {
        $literals = [
            T_CONSTANT_ENCAPSED_STRING,
            T_ENCAPSED_AND_WHITESPACE,
            T_NAME_FULLY_QUALIFIED,
            T_NAME_RELATIVE,
            T_NAME_QUALIFIED,
            T_LNUMBER,
            T_DNUMBER,
            T_STRING,
            T_STRING_VARNAME,
        ];

        if (defined('T_BAD_CHARACTER')) {
            $literals[] = T_BAD_CHARACTER;
        }

        return $literals;
    }

    public function objectOperators(): array
    {
        return [
            T_DOUBLE_COLON,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_OBJECT_OPERATOR,
        ];
    }

    public function magicConstants(): array
    {
        return [
            T_CLASS_C,
            T_DIR,
            T_FILE,
            T_FUNC_C,
            T_LINE,
            T_METHOD_C,
            T_NS_C,
            T_TRAIT_C,
        ];
    }

    public function htmlTags(): array
    {
        return [
            T_CLOSE_TAG,
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
        ];
    }

    public function comments(): array
    {
        return [
            T_ATTRIBUTE,
            T_COMMENT,
            T_DOC_COMMENT,
        ];
    }

    public function variables(): array
    {
        return [
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
            T_NUM_STRING,
            T_PROPERTY_C,
            T_VARIABLE,
        ];
    }

    public function otherTokens(): array
    {
        return [
            T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
            T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
            T_END_HEREDOC,
            T_INLINE_HTML,
            T_START_HEREDOC,
            T_WHITESPACE,
        ];
    }

    public function structureTypes(): array
    {
        return [
            T_CLASS,
            T_ENUM,
            T_INTERFACE,
            T_TRAIT,
        ];
    }

    public function typeDeclarationTypes(): array
    {
        return [
            T_ARRAY,
            T_CALLABLE,
            T_NAME_FULLY_QUALIFIED,
            T_NAME_QUALIFIED,
            T_PIPE,
            T_QUESTION_MARK,
            T_STRING,
        ];
    }
}
