<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

class Block implements \IteratorAggregate
{
    /** @var Statement[]|Block[] */
    private array $elements = [];

    public function __construct(
        public int            $depth,
        public Statement|null $opener,
        public BlockType      $type = BlockType::Plain,
    )
    {
    }

    /**
     * Whether this block, or a block around it, is of one of the types. For example ```isInside(BlockType::HookBody)```
     * for a statement anywhere in the body of a property hook, also inside an if in that body.
     */
    public function isInside(BlockType ...$types): bool
    {
        $block = $this;

        while (!in_array($block->type, $types, true)) {
            $parent = $block->opener?->block;

            // The opener of the file block is the open tag, which is in the file block itself
            if ($parent === null || $parent === $block) {
                return false;
            }

            $block = $parent;
        }

        return true;
    }

    public function __debugInfo()
    {
        $elements = [];

        foreach ($this->elements as $element) {
            $elements[] = sprintf('%s', $element::class);
        }

        return $elements;
    }

    /**
     * foreach ($block) returns the statements in this block, recursively.
     * Be careful to use $statement->block and not $block itself.
     *
     * @return Statement[]|\Generator
     * @noinspection PhpDocSignatureInspection
     */
    public function getIterator(): \Generator
    {
        foreach ($this->elements as $element) {
            if ($element instanceof Block) {
                yield from $element;
            }
            else {
                yield $element;
            }
        }
    }

    public function appendNewStatement(): Statement
    {
        return $this->elements[] = new Statement($this);
    }

    public function appendBlock(Block $block): void
    {
        $this->elements[] = $block;
    }

    public function getPreviousStatement(Statement $statement): Statement|null
    {
        $index = $this->getIndex($statement);
        $previous = $this->elements[$index - 1] ?? null;

        if ($previous instanceof Block) {
            return $previous->lastStatement();
        }

        return $previous;
    }

    public function getNextStatement(Statement $statement): Statement|null
    {
        $index = $this->getIndex($statement);
        $return = $this->elements[$index + 1] ?? null;

        return $return instanceof Statement ? $return : null;
    }

    public function mergeWithPrevious(Statement $secondStatement): void
    {
        $second = $this->getIndex($secondStatement);
        $first = $second - 1;
        $firstStatement = $this->elements[$first];

        foreach ($secondStatement as $token) {
            $firstStatement->appendToken($token);
        }

        $firstStatement->blankLineAfter = $secondStatement->blankLineAfter;

        array_splice($this->elements, $second, 1);

        unset($secondStatement);
    }

    public function lastStatement(): Statement|Block|null
    {
        return $this->elements[count($this->elements) - 1] ?? null;
    }

    public function moveStatementAfter(Statement $statement, Statement $after): void
    {
        $this->removeStatement($statement);
        $this->insertStatementAfter($statement, $after);
    }

    /**
     * Takes a statement or block out of this block. Its tokens are unlinked from the tokens around it; with
     * ```$deleteTokens```, they are removed from the statement too.
     *
     * @throws Exceptions\StatementNotFoundInBlock
     */
    public function removeStatement(Statement|Block $statement, bool $deleteTokens = false): void
    {
        $index = $this->getIndex($statement);

        array_splice($this->elements, $index, 1);

        if ($deleteTokens) {
            foreach ($statement as $token) {
                $token->statement->removeToken($token);
            }

            return;
        }

        $this->unlink($statement);
    }

    /**
     * @throws Exceptions\StatementNotFoundInBlock
     */
    public function insertStatementAfter(Statement|Block $statement, Statement $after): void
    {
        $index = $this->getIndex($after);

        array_splice($this->elements, $index + 1, 0, [$statement]);

        $this->link($statement, $index + 1);
    }

    /**
     * @throws Exceptions\StatementNotFoundInBlock
     */
    public function insertStatementBefore(Statement|Block $statement, Statement $before): void
    {
        $index = $this->getIndex($before);

        array_splice($this->elements, $index, 0, [$statement]);

        $this->link($statement, $index);
    }

    /**
     * Links the tokens of a statement in this block to the tokens before and after it. ```Statement``` calls this when
     * a token is added to it while it was empty. A statement that isn't in the block yet is linked when it's inserted.
     */
    public function linkStatement(Statement $statement): void
    {
        $index = array_search($statement, $this->elements, true);

        if ($index !== false) {
            $this->link($statement, $index);
        }
    }

    /**
     * The first token of a statement or block, looking into the blocks inside it.
     */
    public static function firstTokenOf(Statement|Block $element): Token|null
    {
        foreach ($element instanceof Block ? $element : [$element] as $statement) {
            if (null !== $token = $statement->firstToken()) {
                return $token;
            }
        }

        return null;
    }

    /**
     * The last token of a statement or block, looking into the blocks inside it.
     */
    public static function lastTokenOf(Statement|Block $element): Token|null
    {
        $last = null;

        foreach ($element instanceof Block ? $element : [$element] as $statement) {
            $last = $statement->lastToken() ?? $last;
        }

        return $last;
    }

    /**
     * Links the tokens of the element at the given index to the tokens before and after it in this block. The tokens
     * inside the element are already linked to each other.
     */
    private function link(Statement|Block $element, int $index): void
    {
        $first = self::firstTokenOf($element);
        $last = self::lastTokenOf($element);

        if ($first === null) {
            return;
        }

        $before = $this->lastTokenBefore($index);
        $after = $before !== null ? $before->next : $this->firstTokenAfter($index);

        $first->previous = $before;
        $last->next = $after;

        if ($before !== null) {
            $before->next = $first;
        }

        if ($after !== null) {
            $after->previous = $last;
        }
    }

    private function unlink(Statement|Block $element): void
    {
        $first = self::firstTokenOf($element);
        $last = self::lastTokenOf($element);

        if ($first === null) {
            return;
        }

        if ($first->previous?->next === $first) {
            $first->previous->next = $last->next;
        }

        if ($last->next?->previous === $last) {
            $last->next->previous = $first->previous;
        }

        $first->previous = null;
        $last->next = null;
    }

    private function lastTokenBefore(int $index): Token|null
    {
        for ($i = $index - 1; $i >= 0; --$i) {
            if (null !== $token = self::lastTokenOf($this->elements[$i])) {
                return $token;
            }
        }

        // Nothing before it in this block: the token before the block is the last token of its opener
        return $this->opener?->block !== $this ? $this->opener?->lastToken() : null;
    }

    private function firstTokenAfter(int $index): Token|null
    {
        for ($i = $index + 1; $i < count($this->elements); ++$i) {
            if (null !== $token = self::firstTokenOf($this->elements[$i])) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @throws Exceptions\StatementNotFoundInBlock
     */
    private function getIndex(Statement|Block $statement): int
    {
        $index = array_search($statement, $this->elements, true);

        if ($index === false) {
            throw new Exceptions\StatementNotFoundInBlock($statement, $this);
        }

        return $index;
    }
}
