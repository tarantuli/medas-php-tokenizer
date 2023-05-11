<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Console\Printer;
use Medas\ConsolePrinter\Printer\BashFormat;
use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\Exceptions\NoConsolePrinterFoundException;

#[Service]
class BlockDumper
{
    private int $line;

    public function __construct(
        private readonly Printer|null        $printer,
        private readonly StatementTypeFinder $typeFinder)
    {
    }

    public function dump(Block $block): void
    {
        if (!$this->printer) {
            throw new NoConsolePrinterFoundException();
        }

        $this->line = 0;
        $this->printBlock($block);
        $this->printer->printText("\n");
    }

    private function printBlock(Block $block): void
    {
        foreach ($block as $statement) {
            // Start of line
            $this->printer->printText("\n")
                ->printText(sprintf('%3s', $this->line++), BashFormat::COLOR256 . '208')
                ->printText(str_repeat('·', $statement->block->depth), BashFormat::LIGHT_GRAY);

            // Print tokens on this line
            foreach ($statement as $index => $token) {
                $this->printToken($index, $token);
            }

            // Print statement type
            $this->printer->printText('  ' . $this->typeFinder->for($statement), BashFormat::BLUE);

            if ($statement->blankLineAfter) {
                $this->printer->printText(' ⇊', BashFormat::COLOR256 . '170');
            }
        }
    }

    private function printToken(int $index, Token $token): void
    {
        $this->printer->printText('|')
            ->printText((string) $index, BashFormat::BLUE)
            ->printText(':');

        $this->printer->printText((string) $token->context, BashFormat::COLOR256 . '100')
            ->printText(':');

        if ($token->inAttribute) {
            $this->printer->printText('A', BashFormat::COLOR256 . '184')
                ->printText(':');
        }

        if ($token->inString) {
            $this->printer->printText('S', BashFormat::COLOR256 . '160')
                ->printText(':');
        }

        $this->printer->printText($token->getTokenName(), BashFormat::COLOR256 . '28');

        if ($token->getTokenName() !== $token->text) {
            $this->printer->printText('=');
            preg_match('/^(\s*)(.*?)(\s*)$/ms', $token->text, $parts);

            if (strlen($parts[1])) {
                $this->printer->printText($parts[1], BashFormat::LIGHT_GRAY_BG);
            }
            if (strlen($parts[2])) {
                $this->printer->printText($parts[2], BashFormat::LIGHT_GRAY);
            }
            if (strlen($parts[3])) {
                $this->printer->printText($parts[3], BashFormat::LIGHT_GRAY_BG);
            }
        }

        if ($token->lineBreakAfter) {
            $this->printer->printText('↩', BashFormat::COLOR256 . '170');
        }
        elseif ($token->spaceAfter) {
            $this->printer->printText('‿', BashFormat::COLOR256 . '170');
        }
    }
}
