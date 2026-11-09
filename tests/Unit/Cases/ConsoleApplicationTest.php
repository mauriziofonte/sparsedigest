<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\Console\Application;
use Mfonte\SparseDigest\FileHasher;
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\Test\Unit\TestCase;

/**
 * Command line front end.
 */
final class ConsoleApplicationTest extends TestCase
{
    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    protected function setUp(): void
    {
        parent::setUp();
        $out = fopen('php://memory', 'w+b');
        $err = fopen('php://memory', 'w+b');
        if ($out === false || $err === false) {
            throw new \RuntimeException('Unable to open php://memory');
        }
        $this->out = $out;
        $this->err = $err;
    }

    protected function tearDown(): void
    {
        fclose($this->out);
        fclose($this->err);
        parent::tearDown();
    }

    /**
     * @param list<string> $argv
     * @return array{int, string, string}
     */
    private function runApp(array $argv): array
    {
        foreach ([$this->out, $this->err] as $stream) {
            ftruncate($stream, 0);
            rewind($stream);
        }

        $code = (new Application($this->out, $this->err))->run($argv);

        rewind($this->out);
        rewind($this->err);

        return [$code, (string) stream_get_contents($this->out), (string) stream_get_contents($this->err)];
    }

    public function testPrintsSparseHashPerFile(): void
    {
        $a = $this->createTempFileOfSize(1024 * 1024);
        $b = $this->createTempFileOfSize(1024 * 1024);

        [$code, $out, $err] = $this->runApp([$a, $b]);

        $this->assertSame(Application::EXIT_SUCCESS, $code);
        $this->assertSame('', $err);
        $this->assertSame(
            SparseHasher::new($a)->hash() . '  ' . $a . "\n" . SparseHasher::new($b)->hash() . '  ' . $b . "\n",
            $out
        );
    }

    public function testFullAlgoAndSamplingOptions(): void
    {
        $file = $this->createTempFileOfSize(1024 * 1024);

        [, $full] = $this->runApp(['--full', '--algo=sha256', $file]);
        $this->assertSame(FileHasher::new($file)->hashAs('sha256') . '  ' . $file . "\n", $full);

        [, $sparse] = $this->runApp(['-a', 'md5', '-c', '20', '-s', '8192', $file]);
        $this->assertSame(SparseHasher::new($file)->hashAs('md5', 20, 8192) . '  ' . $file . "\n", $sparse);
    }

    public function testJsonOutput(): void
    {
        $file = $this->createTempFileOfSize(1024 * 1024);

        [$code, $out] = $this->runApp(['--json', '--algo', 'sha1', $file]);

        $this->assertSame(Application::EXIT_SUCCESS, $code);
        $this->assertSame([[
            'path' => $file,
            'hash' => SparseHasher::new($file)->hashAs('sha1')->toHex(),
            'algorithm' => 'sha1',
            'sparse' => true,
            'size' => 1024 * 1024,
        ]], json_decode($out, true));
    }

    public function testMissingFileReportsErrorAndContinues(): void
    {
        $file = $this->createTempFileOfSize(1000);

        [$code, $out, $err] = $this->runApp(['/path/to/nonexistent/file.bin', $file]);

        $this->assertSame(Application::EXIT_FAILURE, $code);
        $this->assertStringContainsString('does not exist', $err);
        $this->assertStringContainsString($file, $out);
    }

    public function testInvalidParameterIsReportedPerFile(): void
    {
        $file = $this->createTempFileOfSize(1000);

        [$code, , $err] = $this->runApp(['--algo=nope', $file]);

        $this->assertSame(Application::EXIT_FAILURE, $code);
        $this->assertStringContainsString('Invalid hash algorithm: nope', $err);
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public function usageErrorProvider(): array
    {
        return [
            'no files' => [[], 'No file given'],
            'unknown option' => [['--bogus', 'x'], 'Unknown option: --bogus'],
            'missing value' => [['--coverage'], 'requires a value'],
            'non numeric' => [['--coverage=ten', 'x'], 'expects a positive integer'],
        ];
    }

    /**
     * @dataProvider usageErrorProvider
     *
     * @param list<string> $argv
     */
    public function testUsageErrors(array $argv, string $expected): void
    {
        [$code, , $err] = $this->runApp($argv);

        $this->assertSame(Application::EXIT_USAGE, $code);
        $this->assertStringContainsString($expected, $err);
    }

    public function testHelp(): void
    {
        [$code, $out] = $this->runApp(['--help']);

        $this->assertSame(Application::EXIT_SUCCESS, $code);
        $this->assertStringContainsString('Usage: sparsedigest', $out);
    }

    public function testDoubleDashEndsOptions(): void
    {
        $file = $this->createTempFileOfSize(1000);

        [$code, $out] = $this->runApp(['--', $file]);

        $this->assertSame(Application::EXIT_SUCCESS, $code);
        $this->assertStringContainsString($file, $out);
    }
}
