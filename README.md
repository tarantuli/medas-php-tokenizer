# medas-php-tokenizer

Part of the [Medas framework](https://github.com/tarantuli/medas-core).

## Description

A structured PHP tokenizer that wraps PHP's native `PhpToken::tokenize()` and enriches the output with contextual information: statement boundaries, block nesting, context labels, type-declaration flags, and linked-list navigation between tokens.

**Architecture:**

| Class                     | Role                                                                                      |
|---------------------------|-------------------------------------------------------------------------------------------|
| `Tokenizer`               | Calls `PhpToken::tokenize()` and returns a `TokenCollection`                              |
| `TokenCollection`         | Iterable, countable collection of `Token` objects with linked-list links                  |
| `Token`                   | Extends `PhpToken`; adds block/statement membership, context, string/attribute flags      |
| `TreeBuilder`             | Runs every `TokenizerPass`, in dependency order, to produce a `TokenTree`                 |
| `TokenTree`               | Root container; iterate over tokens or walk the `Block` tree of `Statement`s              |
| `Block`                   | An ordered list of `Statement`s and nested `Block`s, with a depth and a `BlockType`       |
| `BlockType`               | What a block is the body of: `ClassLike`, `Function`, `Closure`, `Control`, `Match`, …    |
| `Statement`               | A logical line of `Token`s terminated by `;`, `{`, `}`, or a match/switch colon           |
| `ContextAdder`            | Labels every token with a `Context` (GlobalScope, ClassBody, MethodDeclaration, …)        |
| `StatementTypeFinder`     | Classifies each `Statement` (ClassDeclaration, FunctionDeclaration, UseClassStatement, …) |
| `BlockDumper`             | Debug utility; pretty-prints a `Block` or `Statement` to the console                      |
| `TreeDumper`              | Writes a `TokenTree` as plain text, for snapshot tests                                    |
| `TokenGroups`             | Categorised lists of token-type constants (keywords, operators, brackets, …)              |
| `AdditionalTokensDefiner` | Defines single-character token constants absent from PHP's tokenizer (e.g. `T_SEMICOLON`) |
| `TokenType`               | The ids of single-character tokens (e.g. `TokenType::SEMICOLON`), replacing those constants |

**Token flags:**

| Property            | Type          | Meaning                                           |
|---------------------|---------------|---------------------------------------------------|
| `inString`          | `bool`        | Token is inside a double-quoted string or heredoc |
| `inAttribute`       | `bool`        | Token is inside a `#[…]` attribute                |
| `inTypeDeclaration` | `bool`        | Token is part of a type hint                      |
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

`inTypeDeclaration` is deprecated: it is set on the same tokens as in 3.x, for compatibility. Use `typeDeclaration`.

**Statement data:** `Statement::$declaration` is a `Declaration` with the kind (`Class_`, `Method`, `Property`, `Constant`, `EnumCase`, `TraitUse`, …), name and modifiers of what the statement declares; `Statement::$attributes` holds the `#[` of each attribute before it.

**Tokenizer passes:**

`TreeBuilder` runs every `#[Service]` that implements `Passes\TokenizerPass`. Each pass names the passes it reads from in `runsAfter()`, and `Passes\TokenizerPasses` sorts them on those dependencies. Other packages can add passes the same way.

| Pass                          | Runs after                    | Sets                                                              |
|-------------------------------|-------------------------------|-------------------------------------------------------------------|
| `Lexer`                       | –                             | Drops whitespace into `originalLineBreaksAfter`/`originalSpacesAfter`; links `previous`/`next` |
| `BracketMatcher`              | `Lexer`                       | `matchingBracket`, `inString`, `inAttribute`, `closesAttribute`   |
| `BlockTypeResolver`           | `BracketMatcher`              | `opensBlock`, `closesBlock`, `bracketDepth`                       |
| `StatementBuilder`            | `BlockTypeResolver`           | The statements and blocks of the `TokenTree`                      |
| `TypeDeclarationMarker`       | `StatementBuilder`            | `typeDeclaration`, and the deprecated `inTypeDeclaration`         |
| `RoleAssigner`                | `TypeDeclarationMarker`       | `role`                                                            |
| `ClassReferenceMarker`        | `TypeDeclarationMarker`       | `isClassReference`                                                |
| `DeclarationReader`           | `TypeDeclarationMarker`       | `Statement::$declaration`, `Statement::$attributes`               |
| `ContextAdder`                | `StatementBuilder`            | `context`                                                         |

`Block` and `Statement` keep the tokens linked when statements or tokens are inserted, moved or removed, and throw `StatementNotFoundInBlock` or `TokenNotFoundInStatementException` for a statement or token that isn't there. `TokenTree::assertConsistent()` checks the links, for use in tests.

**Contexts:**

| Context class         | `__toString()` | Meaning                                        |
|-----------------------|----------------|------------------------------------------------|
| `GlobalScope`         | `G`            | Top-level code                                 |
| `ClassDeclaration`    | `CD`           | The `class Foo extends Bar` line               |
| `ClassBody`           | `C`            | Inside `{ … }` of a class/interface/trait/enum |
| `FunctionDeclaration` | `FD`           | A global function declaration line             |
| `MethodDeclaration`   | `MD`           | A method declaration line                      |
| `MethodParameters`    | `MP`           | Inside `(…)` of a function/method signature    |
| `MethodReturnType`    | `MR`           | After `:` in a function/method signature       |
| `MethodBody`          | `MB`           | Inside `{ … }` of a method                     |

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

**Building a full token tree (with contexts and statement types):**

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
            $depth   = $statement->block->depth;
            $context = (string) $statement->firstToken()?->context;

            echo "depth=$depth context=$context\n";
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

**Context-based filtering:**

```php
use Medas\PhpTokenizer\Contexts\MethodBody;

foreach ($tree as $token) {
    if ($token->context instanceof MethodBody) {
        // Only tokens inside method bodies
    }

    if ($token->inAttribute) {
        // Token is inside a #[...] attribute
    }

    if ($token->inTypeDeclaration) {
        // Token is part of a type hint
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

**`TokenType`** — The ids of single-character tokens, for `$token->is(TokenType::COMMA)`. Unlike the global constants of `AdditionalTokensDefiner`, which are text and so also match tokens that only look the same (the `{` of `"{$a}"`, for example), these are ids and match only the token itself. `&` is never a token of its own, so `TokenType::AMPERSANDS` lists PHP's two ampersand tokens, and `TokenType::BACKSLASH` is `T_NS_SEPARATOR`. The global constants are removed in 4.0.

**`AdditionalTokensDefiner`** — PHP's tokenizer assigns integer IDs to multi-character tokens (e.g. `T_FUNCTION`, `T_STRING`) but represents single-character tokens as their ASCII code without a named constant. This class defines named constants for the most common ones: `T_SEMICOLON`, `T_COMMA`, `T_DOT`, `T_OPEN_PAREN`, `T_CLOSE_PAREN`, `T_OPEN_BRACKET`, `T_CLOSE_BRACKET`, `T_OPEN_BRACE`, `T_CLOSE_BRACE`, `T_EQUALS`, `T_COLON`, `T_PIPE`, `T_AMPERSAND`, and others. These constants are defined globally in the package's `ready()`, which runs on every request. They have to exist before the first tokenizer pass or `TokenGroups` is created, because class constants use them; with lazily injected services, the constructors that also define them may not have run by then.
