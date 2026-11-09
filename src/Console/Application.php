<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Console;

use Mfonte\SparseDigest\Exception\SparseDigestException;
use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\FileHasher;
use Mfonte\SparseDigest\HashResult;
use Mfonte\SparseDigest\SparseHasher;

/**
 * Command line front end, used by bin/sparsedigest.
 *
 * Prints one "<hash>  <path>" line per file (the same layout as sha256sum),
 * or a JSON array with --json.
 *
 * Exit codes: 0 on success, 1 if at least one file could not be hashed, 2 on usage errors.
 */
final class Application
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;
    public const EXIT_USAGE = 2;

    private const USAGE = <<<'TXT'
Usage: sparsedigest [options] <file>...

Computes a sparse (sampled) hash of each file. Text files and files too small
to be sampled are hashed in full.

Options:
  -f, --full               Hash the whole file instead of sampling it
  -a, --algo=ALGO          Hash algorithm (default: fastest available, see hash_algos())
  -c, --coverage=N         Sampling coverage in percent, 1-100 (default: 10)
  -s, --sample-size=N      Block size in bytes, positive multiple of 64 (default: 4096)
      --json               Print a JSON array with hash, algorithm, mode and size
  -h, --help               Show this help

Exit codes: 0 success, 1 at least one file failed, 2 invalid usage.

TXT;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout Defaults to STDOUT.
     * @param resource|null $stderr Defaults to STDERR.
     */
    public function __construct($stdout = null, $stderr = null)
    {
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    /**
     * Runs the command.
     *
     * @param list<string> $argv Arguments, without the script name.
     * @return int Exit code.
     */
    public function run(array $argv): int
    {
        $options = ['full' => false, 'json' => false, 'algo' => null, 'coverage' => 10, 'sampleSize' => 4096];
        $files = [];

        for ($i = 0, $count = count($argv); $i < $count; $i++) {
            $arg = $argv[$i];

            if ($arg === '--') {
                $files = array_merge($files, array_slice($argv, $i + 1));

                break;
            }
            if ($arg === '' || $arg[0] !== '-' || $arg === '-') {
                $files[] = $arg;

                continue;
            }

            $value = null;
            if (strpos($arg, '=') !== false) {
                [$arg, $value] = explode('=', $arg, 2);
            }

            switch ($arg) {
                case '-h':
                case '--help':
                    fwrite($this->stdout, self::USAGE);

                    return self::EXIT_SUCCESS;
                case '-f':
                case '--full':
                    $options['full'] = true;

                    break;
                case '--json':
                    $options['json'] = true;

                    break;
                case '-a':
                case '--algo':
                case '-c':
                case '--coverage':
                case '-s':
                case '--sample-size':
                    if ($value === null) {
                        if (! isset($argv[$i + 1])) {
                            return $this->usageError("Option {$arg} requires a value.");
                        }
                        $value = $argv[++$i];
                    }
                    if ($arg === '-a' || $arg === '--algo') {
                        $options['algo'] = $value;

                        break;
                    }
                    if (! ctype_digit($value)) {
                        return $this->usageError("Option {$arg} expects a positive integer, '{$value}' given.");
                    }
                    $options[($arg === '-c' || $arg === '--coverage') ? 'coverage' : 'sampleSize'] = (int) $value;

                    break;
                default:
                    return $this->usageError("Unknown option: {$arg}");
            }
        }

        if ($files === []) {
            return $this->usageError('No file given.');
        }

        $exitCode = self::EXIT_SUCCESS;
        $results = [];
        foreach ($files as $path) {
            try {
                $hash = $this->hash($path, $options['full'], $options['algo'], $options['coverage'], $options['sampleSize']);
            } catch (SparseDigestException $e) {
                fwrite($this->stderr, "sparsedigest: {$path}: {$e->getMessage()}\n");
                $exitCode = self::EXIT_FAILURE;

                continue;
            }

            if ($options['json']) {
                $results[] = [
                    'path' => $path,
                    'hash' => $hash->toHex(),
                    'algorithm' => $hash->getAlgorithm(),
                    'sparse' => $hash->isSparse(),
                    'size' => File::new($path)->getSize(),
                ];
            } else {
                fwrite($this->stdout, $hash->toHex() . '  ' . $path . "\n");
            }
        }

        if ($options['json']) {
            fwrite($this->stdout, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }

        return $exitCode;
    }

    /**
     * @throws SparseDigestException
     */
    private function hash(string $path, bool $full, ?string $algo, int $coverage, int $sampleSize): HashResult
    {
        if ($full) {
            $hasher = FileHasher::new($path);

            return $algo === null ? $hasher->hash() : $hasher->hashAs($algo);
        }

        $hasher = SparseHasher::new($path);

        return $algo === null ? $hasher->hash($coverage, $sampleSize) : $hasher->hashAs($algo, $coverage, $sampleSize);
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "sparsedigest: {$message}\nTry 'sparsedigest --help' for more information.\n");

        return self::EXIT_USAGE;
    }
}
