<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\ClassAnalyser;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{StatementTypeFinder, StatementTypes\UseClassStatement, Token, TokenTree};

#[Service]
class ImportsFinder
{
    public function __construct(
        private readonly FqnProperties       $fqnProperties,
        private readonly StatementTypeFinder $typeFinder,
    )
    {
    }

    public function find(TokenTree $tree, ClassAnalysis $results): void
    {
        foreach ($tree as $token) {
            if ($token->is(T_USE) && $this->typeFinder->for($token->statement) instanceof UseClassStatement) {
                $this->processImport($token->next, $results);
            }
        }
    }

    private function processImport(Token $token, ClassAnalysis $results): void
    {
        $path = $token->text;

        if ($token->next->is(T_AS)) {
            $reference = $token->next->next->text;
        }
        else {
            // The path does not start with a \, so it isn't a FQN, but that's no problem for getLastPart()
            $reference = $this->fqnProperties->getLastPart($path);
        }

        $results->imports[] = new ClassReference($reference, '\\' . $path);
    }
}
