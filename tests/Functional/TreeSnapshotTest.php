<?php

declare(strict_types=1);

namespace Medas\PhpTokenizerTest\Functional;

use Medas\PhpTokenizer\{TreeBuilder, TreeDumper};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tokenizes each `tests/Snapshots/<name>.php` and compares the dumped tree with `tests/Snapshots/<name>.txt`.
 *
 * An input whose code contains a comment like `// requires PHP 8.5` is skipped on older PHP versions.
 *
 * A missing `.txt` file is written and the test is marked incomplete, so it can be reviewed before it is committed.
 * Run with the environment variable `UPDATE_SNAPSHOTS=1` to rewrite every `.txt` file after an intended change, and
 * review the diff in git.
 */
class TreeSnapshotTest extends TestCase
{
    private const string DIRECTORY = __DIR__ . '/../Snapshots';

    /**
     * @return array<string, array{string}>
     */
    public static function snapshots(): array
    {
        $cases = [];

        foreach (glob(self::DIRECTORY . '/*.php') as $file) {
            $cases[basename($file, '.php')] = [basename($file, '.php')];
        }

        return $cases;
    }

    #[DataProvider('snapshots')]
    public function testSnapshot(string $name): void
    {
        $code = $this->normaliseLineEndings(file_get_contents(self::DIRECTORY . '/' . $name . '.php'));

        if (preg_match('/requires PHP (\d+)\.(\d+)/', $code, $version)
                && PHP_VERSION_ID < $version[1] * 10000 + $version[2] * 100) {
            self::markTestSkipped(sprintf('%s needs PHP %s.%s', $name, $version[1], $version[2]));
        }

        $actual = service(TreeDumper::class)->dump(service(TreeBuilder::class)->fromCode($code));
        $expectedFile = self::DIRECTORY . '/' . $name . '.txt';

        if (!is_file($expectedFile) || getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($expectedFile, $actual);

            self::markTestIncomplete(sprintf('Snapshot %s.txt written, review it', $name));
        }

        self::assertSame($this->normaliseLineEndings(file_get_contents($expectedFile)), $actual);
    }

    private function normaliseLineEndings(string $text): string
    {
        // Git on Windows may check out the files with \r\n
        return str_replace("\r\n", "\n", $text);
    }
}
