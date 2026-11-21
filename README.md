# medas-php-tokenizer

Part of the [Medas framework](https://github.com/tarantuli/medas-core).

## Description

A structured PHP tokenizer that wraps PHP's native `PhpToken::tokenize()` and enriches the output with structural information: statement boundaries, block nesting and block types, matching brackets, token roles, type declarations, declarations, and linked-list navigation between tokens.

**Architecture:**

| Class                 | Role                                                                                      |
|-----------------------|-------------------------------------------------------------------------------------------|
| `Tokenizer`           | Calls `PhpToken::tokenize()` and returns a `TokenCollection`                              |
| `TokenCollection`     | Iterable, countable collection of `Token` objects with linked-list links                  |
| `Token`               | Extends `PhpToken`; adds block/statement membership, brackets, roles and other flags      |
| `TreeBuilder`         | Runs every `TokenizerPass`, in dependency order, to produce a `TokenTree`                 |
| `TokenTree`           | Root container; iterate over tokens or walk the `Block` tree of `Statement`s              |
| `Block`               | An ordered list of `Statement`s and nested `Block`s, with a depth and a `BlockType`       |
| `BlockType`           | What a block is the body of: `ClassLike`, `Function`, `Closure`, `Control`, `Match`, …    |
| `Statement`           | A logical line of `Token`s terminated by `;`, `{`, `}`, or a match/switch colon           |
| `StatementTypeFinder` | Classifies each `Statement` (ClassDeclaration, FunctionDeclaration, UseClassStatement, …) |
| `BlockDumper`         | Debug utility; pretty-prints a `Block` or `Statement` to the console                      |
| `TreeDumper`          | Writes a `TokenTree` as plain text, for snapshot tests                                    |
| `TokenGroups`         | Categorised lists of token ids (keywords, operators, brackets, …)                         |
| `TokenType`           | The ids of single-character tokens, like `TokenType::SEMICOLON`                           |

**Token flags:**

| Property            | Type          | Meaning                                           |
|---------------------|---------------|---------------------------------------------------|
| `inString`          | `bool`        | Token is inside a double-quoted string or heredoc |
| `inAttribute`       | `bool`        | Token is inside a `#[…]` attribute                |
| `previous` / `next` | `Token\|null` | Doubly-linked list navigation                     |
| `spaceAfter`        | `bool`        | Formatting hint: a space follows this token       |
| `lineBreakAfter`    | `bool`        | Formatting hint: a line break follows this token  |
| `matchingBracket`   | `Token\|null` | The other half of `( )`, `[ ]`, `{ }`, `#[ ]`, `${ }` and `{$ }` |
| `bracketDepth`      | `int`         | Open brackets around the token, not counting block braces |
| `opensBlock`        | `BlockType\|null` | Set on a `{`, or an alternative syntax `:`, that opens a block |
| `closesBlock`       | `bool`        | Set on a `}` that closes a block, and on `endif`, `else:` etc. |
| `originalLineBreaksAfter` / `originalSpacesAfter` | `int` | The whitespace after the token in the source |
| `typeDeclaration`   | `TypeDeclarationKind\|null` | Part of a parameter, return, property, constant or catch type, including DNF groups |
| `role`              | `TokenRole\|null` | What `?`, `:`, `&` or `...` means here: ternary, nullable, named argument, by reference, … |
| `isClassReference`  | `bool`        | A name that refers to a class, interface, trait or enum |

**Statement data:** `Statement::$declaration` is a `Declaration` with the kind (`Class_`, `Method`, `Property`, `Constant`, `EnumCase`, `TraitUse`, …), name and modifiers of what the statement declares; `Statement::$attributes` holds the `#[` of each attribute before it. For formatters, `blankLineAfter`, `additionalDepth` and `extraSpacesBefore` say how the statement is laid out.

**Tokenizer passes:**

`TreeBuilder` runs every `#[Service]` that implements `Passes\TokenizerPass`. Each pass names the passes it reads from in `runsAfter()`, and `Passes\TokenizerPasses` sorts them on those dependencies. Other packages can add passes the same way.

| Pass                          | Runs after                    | Sets                                                              |
|-------------------------------|-------------------------------|-------------------------------------------------------------------|
| `Lexer`                       | –                             | Drops whitespace into `originalLineBreaksAfter`/`originalSpacesAfter`; links `previous`/`next` |
| `BracketMatcher`              | `Lexer`                       | `matchingBracket`, `inString`, `inAttribute`, `closesAttribute`   |
| `BlockTypeResolver`           | `BracketMatcher`              | `opensBlock`, `closesBlock`, `bracketDepth`                       |
| `StatementBuilder`            | `BlockTypeResolver`           | The statements and blocks of the `TokenTree`                      |
| `TypeDeclarationMarker`       | `StatementBuilder`            | `typeDeclaration`                                                 |
| `RoleAssigner`                | `TypeDeclarationMarker`       | `role`                                                            |
| `ClassReferenceMarker`        | `TypeDeclarationMarker`       | `isClassReference`                                                |
| `DeclarationReader`           | `TypeDeclarationMarker`       | `Statement::$declaration`, `Statement::$attributes`               |

`Block` and `Statement` keep the tokens linked when statements or tokens are inserted, moved or removed, and throw `StatementNotFoundInBlock` or `TokenNotFoundInStatementException` for a statement or token that isn't there. `TokenTree::assertConsistent()` checks the links, for use in tests.

## Usage

### Package developer context

Register the package:

```php
use Medas\PhpTokenizer\PhpTokenizerPackage;

PhpTokenizerPackage::instance();
```

**Tokenizing only — flat `TokenCollection`:**

```php
use Medas\PhpTokenizer\Tokenizer;
use Medas\Core\Attributes\Service;

#[Service]
readonly class SourceInspector
{
    public function __construct(
        private Tokenizer $tokenizer,
    ) {}

    public function inspect(string $phpSource): void
    {
        $collection = $this->tokenizer->tokenize($phpSource);

        echo count($collection) . " tokens\n";

        foreach ($collection as $token) {
            echo $token->getTokenName() . ' => ' . $token->text . "\n";
        }
    }
}
```

**Building a full token tree:**

```php
use Medas\PhpTokenizer\TreeBuilder;
use Medas\Core\Attributes\Service;

#[Service]
readonly class CodeAnalyser
{
    public function __construct(
        private TreeBuilder $treeBuilder,
    ) {}

    public function analyse(string $phpSource): void
    {
        $tree = $this->treeBuilder->fromCode($phpSource);

        // Iterate every token in source order
        foreach ($tree as $token) {
            echo $token->text;
        }

        // Walk statements
        foreach ($tree->statements() as $statement) {
            $depth = $statement->block->depth;
            $type = $statement->block->type->name;

            echo "depth=$depth block=$type\n";
        }
    }
}
```

**Working with statement types:**

```php
use Medas\PhpTokenizer\{StatementTypeFinder, StatementTypes};

foreach ($tree->statements() as $statement) {
    $type = $statementTypeFinder->for($statement);

    if ($type instanceof StatementTypes\FunctionDeclaration) {
        echo 'Method/function: ' . $statement->firstToken()->text . "\n";
    }

    if ($type instanceof StatementTypes\ClassPropertyDeclaration) {
        echo 'Property declaration' . "\n";
    }

    if ($type instanceof StatementTypes\UseClassStatement) {
        echo 'Use statement' . "\n";
    }
}
```

Full list of `StatementType` implementations: `AttributeStatement`, `BlankLine`, `BlockCloser`, `ClassConstDeclaration`, `ClassDeclaration`, `ClassPropertyDeclaration`, `Comment`, `ControlStatement`, `DeclareStatement`, `EnumCase`, `FunctionDeclaration`, `GenericStatement`, `NamespaceDeclaration`, `PhpOpenTag`, `ReturnStatement`, `SwitchBranch`, `ThrowStatement`, `UseClassStatement`, `UseConstStatement`, `UseFunctionStatement`, `UseTraitStatement`.

**Using `TokenGroups` for membership tests:**

```php
use Medas\PhpTokenizer\TokenGroups;

foreach ($tree as $token) {
    if ($token->is($tokenGroups->keywords())) {
        echo "Keyword: {$token->text}\n";
    }

    if ($token->is($tokenGroups->comparisonOperators())) {
        echo "Comparison: {$token->text}\n";
    }

    if ($token->is($tokenGroups->brackets())) {
        echo "Bracket: {$token->text}\n";
    }
}
```

**Linked-list navigation:**

```php
// Walk forward/backward from any token
$token = $collection->first();

while ($token !== null) {
    // Check a single type
    if ($token->is(\T_FUNCTION)) {
        $nameToken = $token->next;      // typically T_STRING (function name)
        $prev      = $token->previous;  // whatever came before
    }

    $token = $token->next;
}
```

**Filtering on structure:**

```php
use Medas\PhpTokenizer\{BlockType, TokenType};

foreach ($tree as $token) {
    if ($token->block->type === BlockType::Function) {
        // Only tokens directly inside a function or method body
    }

    if ($token->inAttribute) {
        // Token is inside a #[...] attribute
    }

    if ($token->typeDeclaration !== null) {
        // Token is part of a type declaration
    }

    if ($token->is(TokenType::COMMA) && $token->bracketDepth === 0) {
        // A comma outside any brackets
    }
}
```

**Debugging — dumping the block tree:**

```php
use Medas\PhpTokenizer\BlockDumper;

$blockDumper->dump($tree->block());

// Or via global helpers (registered by PhpTokenizerPackage):
dumpBlock($tree->block());
dumpStatement($statement);
```

### Backend user context

This package has no CLI commands and no configuration options. It is consumed as a library by `medas-php-formatter`, `medas-php-class-analysis`, and any custom tooling that needs structured access to PHP token streams.

**`TokenType`** — PHP's tokenizer has a `T_*` constant for every multi-character token (e.g. `T_FUNCTION`, `T_STRING`), but a single-character token has its character code as its id and no constant. `TokenType` names those ids, for `$token->is(TokenType::COMMA)`. Being ids, they match only the token itself, not a token that only looks the same (the `{` of `"{$a}"`, for example). `&` is never a token of its own, so `TokenType::AMPERSANDS` lists PHP's two ampersand tokens, and `TokenType::BACKSLASH` is `T_NS_SEPARATOR`.

## Upgrading from 4.0

4.1 works out the structure of the code once, in passes, and stores it on the tokens, statements and blocks. Several parts of the old API are removed for it.

**Removed:**

| Removed                                                                           | Instead                                                                                                         |
|-----------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| `Contexts\*`, `Token::$context`, `Token::context()`                               | `$token->block->type` (a `BlockType`) and `Block::isInside()`; in signatures, `Token::$typeDeclaration`         |
| `ContextAdder`                                                                    | Nothing: the passes set everything it did                                                                       |
| `StructureFinder` and `StructureFinder\Job`, `MatchState`, `TypeDeclarationState` | The passes in `Passes\`, which `TreeBuilder` runs; add your own with `TokenizerPass`                            |
| `Token::$inTypeDeclaration`                                                       | `Token::$typeDeclaration`, a `TypeDeclarationKind`, which also covers DNF types, constants and catch            |
| `AdditionalTokensDefiner` and the global `T_COMMA`-style constants                | `TokenType::COMMA` and so on; `T_AMPERSAND` is `TokenType::AMPERSANDS`, `T_BACKSLASH` is `TokenType::BACKSLASH` |
| The `AdditionalTokensDefiner` parameter of `Tokenizer` and `TokenGroups`          | Nothing                                                                                                         |

**Changed:**

- `Token::is()` is `PhpToken::is()` again. The `TokenType` constants are ids, so they only match the token itself: the `{` of `"{$a}"` is `T_CURLY_OPEN`, not `TokenType::CURLY_BRACKET_OPEN`, although a plain `}` closes it. `TokenGroups::brackets()` lists both.
- `Statement::getIndex()` returns an `int`, and throws `TokenNotFoundInStatementException` for a token that isn't in the statement. The `Block` methods that move, insert or remove a statement throw `StatementNotFoundInBlock` when the statement they're given isn't in the block, instead of using the wrong position.
- Make a new token with `Token::fromText($text, $nearby)`, which sets the right id. Changing the text of a copy of another token leaves its id wrong.
- If you used `TokenRole::Elvis` from an early 4.1 release: it is now `TokenRole::ShortTernary`.

**Added:** on `Token`, `matchingBracket`, `bracketDepth`, `role`, `typeDeclaration`, `isClassReference`, `opensBlock`, `closesBlock`, `originalLineBreaksAfter` and `originalSpacesAfter`; on `Block`, `type` and `isInside()`; on `Statement`, `declaration`, `attributes` and `extraSpacesBefore`; `TokenTree::assertConsistent()`, and the passes. The sections above describe them.
