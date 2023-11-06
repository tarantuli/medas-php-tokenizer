<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

class Block implements \IteratorAggregate
{
    /** @var Statement[]|Block[] */
    private array $elements = [];

    public function __construct(public int $depth, public Statement|null $opener)
    {
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

    public function getPreviousStatement(Statement $statement): ?Statement
    {
        $index = $this->getIndex($statement);

        $previous = $this->elements[$index - 1] ?? null;

        if ($previous instanceof Block) {
            return $previous->lastStatement();
        }

        return $previous;
    }

    private function getIndex(Statement $statement): int|null
    {
        $index = array_search($statement, $this->elements, true);

        return false === $index ? null : $index;
    }

    public function getNextStatement(Statement $statement): ?Statement
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

    public function lastStatement(): Statement
    {
        return $this->elements[count($this->elements) - 1];
    }

    public function __debugInfo()
    {
        $elements = [];

        foreach ($this->elements as $element) {
            $elements[] = sprintf('%s', $element::class);
        }

        return $elements;
    }

    public function moveStatementAfter(Statement $statement, Statement $after): void
    {
        $this->removeStatement($statement);
        $this->insertStatementAfter($statement, $after);
    }

    public function removeStatement(Statement $statement, bool $deleteTokens = false): void
    {
        $index = $this->getIndex($statement);
        array_splice($this->elements, $index, 1);

        if ($deleteTokens) {
            foreach ($statement as $token) {
                $statement->removeToken($token);
            }
        }
    }

    public function insertStatementAfter(Statement $statement, Statement $after): void
    {
        $index = $this->getIndex($after);
        array_splice($this->elements, $index + 1, 0, [$statement]);
    }

    public function insertStatementBefore(Statement $statement, Statement $before): void
    {
        $index = $this->getIndex($before);
        array_splice($this->elements, $index, 0, [$statement]);
    }
}
