# medas-php-tokenizer

Part of the [Medas framework](https://github.com/tarantuli/medas-core).

A structured PHP tokenizer that wraps PHP's native `token_get_all()` / `PhpToken::tokenize()` and enriches the output with contextual information: statement boundaries, block nesting, context labels (global scope, class body, method parameters, return types), type-declaration flags, and linked-list navigation between tokens.

## Requirements

- PHP 8.4+
- [`morphp/medas-core`](https://packagist.org/packages/morphp/medas-core) ^3
- [`morphp/medas-console`](https://packagist.org/packages/morphp/medas-console) ^3

## Installation

```bash
composer require morphp/medas-php-tokenizer
```

## Architecture overview

| Class                     | Role                                                                                      |
|---------------------------|-------------------------------------------------------------------------------------------|
| `Tokenizer`               | Calls `PhpToken::tokenize()` and returns a `TokenCollection`                              |
| `TokenCollection`         | Iterable, countable collection of `Token` objects with linked-list links                  |
| `Token`                   | Extends `PhpToken`; adds block/statement membership, context, string/attribute flags      |
| `TreeBuilder`             | Orchestrates `Tokenizer → StructureFinder → ContextAdder` to produce a `TokenTree`        |
| `TokenTree`               | Root container; iterate over tokens or walk the `Block` tree of `Statement`s              |
| `Block`                   | An ordered list of `Statement`s and nested `Block`s at a given brace depth                |
| `Statement`               | A logical line of `Token`s terminated by `;`, `{`, `}`, or a match/switch colon           |
| `StructureFinder`         | Groups tokens into statements and blocks, resolves string/attribute/heredoc regions       |
| `ContextAdder`            | Labels every token with a `Context` (GlobalScope, ClassBody, MethodDeclaration, …)        |
| `StatementTypeFinder`     | Classifies each `Statement` (ClassDeclaration, FunctionDeclaration, UseClassStatement, …) |
| `BlockDumper`             | Debug utility; pretty-prints a `Block` or `Statement` to the console                      |
| `TokenGroups`             | Returns categorised lists of token-type constants (keywords, operators, brackets, …)      |
| `AdditionalTokensDefiner` | Defines single-character token constants absent from PHP's tokenizer (e.g. `T_SEMICOLON`) |

## Usage

### Tokenize only

```php
use Medas\PhpTokenizer\Tokenizer;

$tokenizer = /* obtain via your DI container, e.g., service(Tokenizer::class) */;

$collection = $tokenizer->tokenize('<?php echo "hello";');

foreach ($collection as $token) {
    echo $token->getTokenName() . ' => ' . $token->text . PHP_EOL;
}

// Count tokens
echo count($collection); // implements Countable
```

### Build a full token tree (with contexts and statement types)

```php
use Medas\PhpTokenizer\TreeBuilder;

$builder = /* service(TreeBuilder::class) */;

$code = <<<'PHP'
<?php
class Foo {
    public function bar(int $x): string {
        return (string) $x;
    }
}
PHP;

$tree = $builder->fromCode($code);

// Iterate every token in order
foreach ($tree as $token) {
    echo $token->text;
}

// Walk statements
foreach ($tree->statements() as $statement) {
    // $statement->block->depth  → brace nesting depth
    // $statement->firstToken()  → first Token in the statement
    echo (string) $statement->firstToken()?->context . PHP_EOL;
}
```

### Statement types

Every `Statement` carries a lazy-resolved `StatementType`. The built-in types are:

`AttributeStatement`, `BlankLine`, `BlockCloser`, `ClassConstDeclaration`, `ClassDeclaration`, `ClassPropertyDeclaration`, `Comment`, `ControlStatement`, `DeclareStatement`, `EnumCase`, `FunctionDeclaration`, `GenericStatement`, `NamespaceDeclaration`, `PhpOpenTag`, `ReturnStatement`, `SwitchBranch`, `ThrowStatement`, `UseClassStatement`, `UseConstStatement`, `UseFunctionStatement`, `UseTraitStatement`.

```php
use Medas\PhpTokenizer\{StatementTypeFinder, StatementTypes};

$finder = /* service(StatementTypeFinder::class) */;

foreach ($tree->statements() as $statement) {
    $type = $finder->for($statement);

    if ($type instanceof StatementTypes\FunctionDeclaration) {
        echo 'Found a function/method declaration' . PHP_EOL;
    }
}
```

### Contexts

Each token has a `context` property set to one of:

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

### Token flags

| Property            | Type          | Meaning                                           |
|---------------------|---------------|---------------------------------------------------|
| `inString`          | `bool`        | Token is inside a double-quoted string or heredoc |
| `inAttribute`       | `bool`        | Token is inside a `#[…]` attribute                |
| `inTypeDeclaration` | `bool`        | Token is part of a type hint                      |
| `previous` / `next` | `Token\|null` | Doubly-linked list navigation                     |
| `spaceAfter`        | `bool`        | Formatting hint: a space follows                  |
| `lineBreakAfter`    | `bool`        | Formatting hint: a line break follows             |

### Token groups

`TokenGroups` provides categorised arrays of token-type constants useful for membership tests:

```php
$groups = /* service(TokenGroups::class) */;

$token->is($groups->keywords());        // is it a keyword?
$token->is($groups->comparisonOperators()); // is it == !== <=> etc?
$token->is($groups->brackets());        // is it ( ) [ ] { }?
```

### Debug dumping

When a console printer is available:

```php
use Medas\PhpTokenizer\BlockDumper;

$dumper = /* service(BlockDumper::class) */;
$dumper->dump($tree->block());
```

Or via the global helper (requires `PhpTokenizerPackage` to be loaded):

```php
dumpBlock($tree->block());
dumpStatement($statement);
```

### Service container integration

The package ships with `PhpTokenizerPackage`, which registers all services and global helpers when using the Medas service manager:

```php
use Medas\PhpTokenizer\PhpTokenizerPackage;

PhpTokenizerPackage::instance()->initialize($config);
```
