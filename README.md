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
| `TreeBuilder`             | Orchestrates `Tokenizer → StructureFinder → ContextAdder` to produce a `TokenTree`        |
| `TokenTree`               | Root container; iterate over tokens or walk the `Block` tree of `Statement`s              |
| `Block`                   | An ordered list of `Statement`s and nested `Block`s at a given brace depth                |
| `Statement`               | A logical line of `Token`s terminated by `;`, `{`, `}`, or a match/switch colon           |
| `StructureFinder`         | Groups tokens into statements and blocks; resolves string/attribute/heredoc regions       |
| `ContextAdder`            | Labels every token with a `Context` (GlobalScope, ClassBody, MethodDeclaration, …)        |
| `StatementTypeFinder`     | Classifies each `Statement` (ClassDeclaration, FunctionDeclaration, UseClassStatement, …) |
| `BlockDumper`             | Debug utility; pretty-prints a `Block` or `Statement` to the console                      |
| `TokenGroups`             | Categorised lists of token-type constants (keywords, operators, brackets, …)              |
| `AdditionalTokensDefiner` | Defines single-character token constants absent from PHP's tokenizer (e.g. `T_SEMICOLON`) |

**Token flags:**

| Property            | Type          | Meaning                                           |
|---------------------|---------------|---------------------------------------------------|
| `inString`          | `bool`        | Token is inside a double-quoted string or heredoc |
| `inAttribute`       | `bool`        | Token is inside a `#[…]` attribute                |
| `inTypeDeclaration` | `bool`        | Token is part of a type hint                      |
| `previous` / `next` | `Token\|null` | Doubly-linked list navigation                     |
| `spaceAfter`        | `bool`        | Formatting hint: a space follows this token       |
| `lineBreakAfter`    | `bool`        | Formatting hint: a line break follows this token  |

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

**`AdditionalTokensDefiner`** — PHP's tokenizer assigns integer IDs to multi-character tokens (e.g. `T_FUNCTION`, `T_STRING`) but represents single-character tokens as their ASCII code without a named constant. This class defines named constants for the most common ones: `T_SEMICOLON`, `T_COMMA`, `T_DOT`, `T_OPEN_PAREN`, `T_CLOSE_PAREN`, `T_OPEN_BRACKET`, `T_CLOSE_BRACKET`, `T_OPEN_BRACE`, `T_CLOSE_BRACE`, `T_EQUALS`, `T_COLON`, `T_PIPE`, `T_AMPERSAND`, and others. These constants are defined globally on package initialization.
