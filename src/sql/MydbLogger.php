<?php
/**
 * This file is part of the sshilko/php-sql-mydb package.
 *
 * (c) Sergei Shilko <contact@sshilko.com>
 *
 * MIT License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @license https://opensource.org/licenses/mit-license.php MIT
 */

declare(strict_types = 1);

namespace sql;

use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use sql\MydbException\LoggerException;
use Stringable;
use Throwable;
use function array_diff_key;
use function array_map;
use function fclose;
use function feof;
use function fflush;
use function fopen;
use function fwrite;
use function implode;
use function is_resource;
use function is_scalar;
use function restore_error_handler;
use function set_error_handler;
use function stream_get_meta_data;
use function strlen;
use function strpos;
use function strtolower;
use function strtr;
use function substr;
use function var_export;
use const PHP_EOL;

/**
 * Implementation of PSR-3 Logger that writes to STDERR & STDOUT
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 * @see https://www.php-fig.org/psr/psr-3/
 */
final class MydbLogger implements LoggerInterface
{

    /**
     * Opened resource, STDOUT
     * @see https://www.php.net/manual/en/features.commandline.io-streams.php
     *
     * @var resource|null
     */
    protected $stdout;

    /**
     * Opened resource, STDERR
     * @see https://www.php.net/manual/en/features.commandline.io-streams.php
     *
     * @var resource|null
     */
    protected $stderr;

    /**
     * End of line delimiter
     */
    protected readonly string $stdeol;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     * @psalm-suppress MissingParamType
     */
    public function __construct($stdout = null, $stderr = null, string $stdeol = PHP_EOL)
    {
        $this->stdout = is_resource($stdout) ? $stdout : self::openDefaultStream('php://stdout');
        $this->stderr = is_resource($stderr) ? $stderr : self::openDefaultStream('php://stderr');
        $this->stdeol = $stdeol;
    }

    public function __destruct()
    {
        if (is_resource($this->stdout)) {
            fflush($this->stdout);
            fclose($this->stdout);
        }

        if (is_resource($this->stderr)) {
            fflush($this->stderr);
            fclose($this->stderr);
        }
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param mixed $level
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     * @throws \sql\MydbException\LoggerException
     */
    #[Override]
    public function log($level, $message, array $context = []): void
    {
        $this->writeOutput($this->route((string) $level), $this->render($message, $context));
    }

    /**
     * Pick the target stream for the given PSR-3 log level.
     * Error-like levels go to STDERR, informational levels go to STDOUT.
     *
     * @return resource|null
     */
    protected function route(string $level)
    {
        return match (strtolower($level)) {
            LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL,
            LogLevel::ERROR, LogLevel::WARNING => $this->stderr,
            default => $this->stdout,
        };
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $message
     * @param array<mixed> $context
     */
    protected function render($message, array $context): string
    {
        $lines = [];

        if ([] !== $message && '' !== $message) {
            $interpolated = self::interpolate(self::formatter($message), $context);
            $lines[]      = $interpolated[0];
            $context      = array_diff_key($context, $interpolated[1]);
        }

        if ([] !== $context) {
            $lines[] = var_export($context, true);
        }

        return [] === $lines ? '' : implode($this->stdeol, $lines) . $this->stdeol;
    }

    /**
     * PSR-3 placeholder interpolation: replace {key} with the matching context value.
     *
     * @param array<mixed> $context
     * @return array{0: string, 1: array<array-key, true>}
     */
    protected static function interpolate(string $message, array $context): array
    {
        $replace = [];
        $used    = [];

        $strings = array_map(
            static function (mixed $value): ?string {
                return is_scalar($value) || $value instanceof Stringable
                    ? (string) $value
                    : null;
            },
            $context
        );

        foreach ($strings as $key => $stringValue) {
            if (null === $stringValue) {
                continue;
            }

            $placeholder = '{' . $key . '}';
            if (false !== strpos($message, $placeholder)) {
                $replace[$placeholder] = $stringValue;
                $used[$key]            = true;
            }
        }

        return [
            strtr($message, $replace),
            $used,
        ];
    }

    /**
     * @param resource $stream &fs.file.pointer;
     * @link https://php.net/manual/en/function.fwrite.php
     * @throws \sql\MydbException\LoggerException
     */
    protected function checkStreamResource($stream): void
    {
        /**
         * is_resource checks whether resource was closed with i.e. fclose()
         * @psalm-suppress DocblockTypeContradiction
         * @psalm-suppress RedundantConditionGivenDocblockType
         */
        if (false === is_resource($stream)) {
            throw new LoggerException('Stream resource is not valid or already closed');
        }

        $info = stream_get_meta_data($stream);

        if ($info['timed_out'] || $info['eof'] || feof($stream)) {
            throw new LoggerException();
        }

        if ('' !== $info['mode'] && strtr($info['mode'], 'waxc+', '.....') === $info['mode']) {
            throw new LoggerException('Stream resource is not opened in write mode');
        }
    }

    /**
     * @param resource|null $stream &fs.file.pointer;
     * @link https://php.net/manual/en/function.fwrite.php
     * @throws \sql\MydbException\LoggerException
     * @psalm-suppress MissingParamType
     */
    protected function writeOutput($stream, string $data = ''): void
    {
        if (null === $stream || '' === $data) {
            return;
        }

        $this->checkStreamResource($stream);

        $len     = strlen($data);
        $written = 0;

        while ($written < $len) {
            $writeResult = $this->fwrite($stream, substr($data, $written));
            if (null === $writeResult || 0 === $writeResult) {
                // @codeCoverageIgnoreStart
                throw new LoggerException();
                // @codeCoverageIgnoreEnd
            }

            $written += $writeResult;

            if (false === fflush($stream)) {
                // @codeCoverageIgnoreStart
                throw new LoggerException();
                // @codeCoverageIgnoreEnd
            }
        }
    }

    /**
     * @param resource $stream
     */
    protected function fwrite($stream, string $data): ?int
    {
        $error = null;

        /**
         * @psalm-suppress InvalidArgument
         */
        set_error_handler(
            static function (int $errno, string $errstr) use (&$error): bool {
                // @codeCoverageIgnoreStart
                $error = $errstr . ' (' . $errno . ')';

                return true;
                // @codeCoverageIgnoreEnd
            }
        );

        $sent = fwrite($stream, $data);

        restore_error_handler();

        if (null !== $error || false === $sent) {
            // @codeCoverageIgnoreStart
            return null;
            // @codeCoverageIgnoreEnd
        }

        return $sent;
    }

    /**
     * @param float|int|string|\Stringable|array<mixed> $var
     */
    protected static function formatter($var): string
    {
        if (is_scalar($var)) {
            return (string) $var;
        }

        return var_export($var, true);
    }

    /**
     * Open a non-CLI-safe default stream without crashing outside of the CLI SAPI.
     *
     * @return resource|null
     */
    protected static function openDefaultStream(string $target)
    {
        try {
            $stream = fopen($target, 'wb');
        } catch (Throwable $throwable) {
            unset($throwable);

            return null;
        }

        return false === $stream ? null : $stream;
    }
}
