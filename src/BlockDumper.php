<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Console\{Formats\BgColor, Formats\Color, Formats\HexColor, Printer};
use Medas\Core\Attributes\Service;

#[Service]
class BlockDumper
{
    private int $line = 0;

    public function __construct(
        private readonly Printer|null        $printer,
        private readonly StatementTypeFinder $typeFinder,
    )
    {
    }

    public function dump(Block $block): void
    {
        if (!$this->printer) {
            throw new Exceptions\NoConsolePrinterFoundException();
        }

        $this->line = 0;

        $this->printBlock($block);
        $this->printer->printEol();
    }

    public function dumpStatement(Statement $statement): void
    {
        if (!$this->printer) {
            throw new Exceptions\NoConsolePrinterFoundException();
        }

        $this->line = 0;

        $this->printStatement($statement);
        $this->printer->printEol();
    }

    private function printBlock(Block $block): void
    {
        foreach ($block as $statement) {
            $this->printStatement($statement);
        }
    }

    public function printStatement(Statement $statement): void
    {
        // Start of line
        $this->printer->printEol()
            ->printText(sprintf('%3s', $this->line++), new HexColor('#ff8700'))
            ->printText(' ')
            ->printText(str_repeat(' ', $statement->block->depth), Color::LightGray);

        if ($statement->additionalDepth) {
            $this->printer->printText(str_repeat('🡒', $statement->additionalDepth), new HexColor('#d75fd7'));
        }

        // Print tokens on this line
        foreach ($statement as $index => $token) {
            $this->printToken($index, $token);
            $this->printer->printText('  ');
        }

        // Print statement type
        $statementType = $this->typeFinder->for($statement);

        if (!$statementType instanceof StatementTypes\GenericStatement) {
            if ($statement->rootStatement !== null) {
                $this->printer->printText('↩ ', Color::Blue);
            }
            else {
                $this->printer->printText('«' . $statementType . '» ', Color::Blue);
            }
        }

        if ($statement->blankLineAfter) {
            $this->printer->printText('⇊ ', new HexColor('#d75fd7'));
        }
    }

    private function printToken(int $index, Token $token): void
    {
        $this->printer->printText((string) $index, Color::Blue)
            ->printText('·');

        if (isset($token->context)) {
            $this->printer->printText((string) $token->context, new HexColor('#878700'))
                ->printText('·');
        }

        if ($token->inAttribute) {
            $this->printer->printText('A', new HexColor('#d7d700'))
                ->printText('·');
        }

        if ($token->inString) {
            $this->printer->printText('S', new HexColor('#d70000'))
                ->printText('·');
        }

        if ($token->inTypeDeclaration) {
            $this->printer->printText('T', new HexColor('#d70000'))
                ->printText('·');
        }

        $tokenName = $token->getTokenName();

        $this->printer->printText(
            str_starts_with($tokenName, 'T_') ? substr($tokenName, 2) : $tokenName,
            new HexColor('#008700')
        );

        if ($tokenName !== 'T_' . strtoupper($token->text) && $tokenName !== $token->text) {
            $this->printer->printText('=');

            preg_match('/^(\s*)(.*?)(\s*)$/s', $token->text, $parts);

            if (strlen($parts[1])) {
                $this->printer->printText($parts[1], BgColor::LightGray);
            }

            if (strlen($parts[2])) {
                if (preg_match('/^(.+?)[\r\n]/', $parts[2], $prefix)) {
                    $this->printer->printText($prefix[1], Color::LightGray);
                    $this->printer->printText('⋯', Color::White);
                }
                else {
                    $this->printer->printText($parts[2], Color::LightGray);
                }
            }

            if (strlen($parts[3])) {
                $this->printer->printText($parts[3], BgColor::LightGray);
            }
        }

        if ($token->lineBreakAfter) {
            $this->printer->printText('↩', new HexColor('#d75fd7'));
        }
        elseif ($token->spaceAfter) {
            $this->printer->printText('‿', new HexColor('#d75fd7'));
        }
    }
}
