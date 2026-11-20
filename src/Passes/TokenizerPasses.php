<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\Core\CachedImplementorList;
use Medas\PhpTokenizer\Exceptions;

/**
 * Finds every @class(TokenizerPass) service and puts them in an order where each pass runs after the passes it names
 * in ```runsAfter()```. Passes that don't depend on each other keep the alphabetical order of their class names, so
 * the order is the same on every run.
 */
#[Service]
class TokenizerPasses
{
    private CachedImplementorList $implementors;

    /** @var TokenizerPass[]|null */
    private array|null $ordered = null;

    public function __construct()
    {
        $this->implementors = new CachedImplementorList(TokenizerPass::class);
    }

    /**
     * @return TokenizerPass[]
     */
    public function ordered(): array
    {
        return $this->ordered ??= $this->order($this->implementors->get());
    }

    /**
     * @param TokenizerPass[] $passes
     * @return TokenizerPass[]
     */
    public function order(array $passes): array
    {
        $byName = [];

        foreach ($passes as $pass) {
            $byName[$pass::class] = $pass;
        }

        ksort($byName);

        $ordered = [];
        $visiting = [];

        foreach (array_keys($byName) as $name) {
            $this->visit($name, $byName, $ordered, $visiting);
        }

        return array_values($ordered);
    }

    /**
     * @param array<class-string, TokenizerPass> $byName
     * @param array<class-string, TokenizerPass> $ordered
     * @param array<class-string, true> $visiting
     */
    private function visit(string $name, array $byName, array &$ordered, array &$visiting): void
    {
        if (isset($ordered[$name])) {
            return;
        }

        if (isset($visiting[$name])) {
            throw new Exceptions\CircularPassDependency(array_keys($visiting), $name);
        }

        $visiting[$name] = true;

        foreach ($byName[$name]->runsAfter() as $dependency) {
            if (!isset($byName[$dependency])) {
                throw new Exceptions\PassDependencyNotFound($name, $dependency);
            }

            $this->visit($dependency, $byName, $ordered, $visiting);
        }

        unset($visiting[$name]);

        $ordered[$name] = $byName[$name];
    }
}
