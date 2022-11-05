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

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use sql\MydbException\LoggerException;
use function clearstatcache;
use function count;
use function fclose;
use function feof;
use function fflush;
use function fwrite;
use function is_resource;
use function is_scalar;
use function restore_error_handler;
use function set_error_handler;
use function stream_get_meta_data;
use function strlen;
use function strtr;
use function substr;
use function var_export;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Implementation of PSR-3 Logger that will output to STDERR & STDOUT
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 * @see https://www.php-fig.org/psr/psr-3/
 */
class MydbLogger implements LoggerInterface
{
    protected const IO_WRITE_ATTEMPTS = 3;

    /**
     * Opened resource, STDOUT
     * @see https://www.php.net/manual/en/features.commandline.io-streams.php
     *
     * @var resource
     */
    protected $stdout;

    /**
     * Opened resource, STDERR
     * @see https://www.php.net/manual/en/features.commandline.io-streams.php
     *
     * @var resource
     */
    protected $stderr;

    /**
     * End of line delimiter
     */
    protected string $stdeol = PHP_EOL;

    /**
     * @param resource $stdout
     * @param resource $stderr
     * @psalm-suppress MissingParamType
     * @throws LoggerException
     */
    public function __construct($stdout = STDOUT, $stderr = STDERR, string $stdeol = PHP_EOL)
    {
        /**
         * @psalm-suppress DocblockTypeContradiction
         */
        if (!is_resource($stdout) || !is_resource($stderr)) {
            throw new LoggerException();
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->stdeol = $stdeol;
    }

    public function __destruct()
    {
        /**
         * @psalm-suppress RedundantConditionGivenDocblockType
         */
        if (is_resource($this->stdout)) {
            fflush($this->stdout);
            fclose($this->stdout);
        }

        /**
         * @psalm-suppress RedundantConditionGivenDocblockType
         */
        if (is_resource($this->stderr)) {
            fflush($this->stderr);
            fclose($this->stderr);
        }
        clearstatcache();
    }

    /**
     * @throws LoggerException
     * @param array|string $message
     */
    public function error($message, array $context = []): void
    {
        if ([] !== $message && '' !== $message) {
            $this->writeOutput($this->stderr, static::formatter($message) . $this->stdeol);
        }

        if (!count($context)) {
            return;
        }

        $this->writeOutput($this->stderr, static::formatter($context) . $this->stdeol);
    }

    /**
     * @param mixed $level
     * @param array|string $message
     * @throws LoggerException
     * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function log($level, $message, array $context = []): void
    {
        if ([] !== $message && '' !== $message) {
            $this->writeOutput($this->stdout, static::formatter($message) . $this->stdeol);
        }

        if (!count($context)) {
            return;
        }

        $this->writeOutput($this->stdout, static::formatter($context) . $this->stdeol);
    }

    /**
     * @param array|string $message
     * @throws LoggerException
     */
    public function warning($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    /**
     * @param array|string $message
     * @throws LoggerException
     */
    public function emergency($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    /**
     * @param array|string $message
     * @throws LoggerException
     */
    public function alert($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    /**
     * @param array|string $message
     * @throws LoggerException
     */
    public function critical($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    /**
     * @param array|string $message
     * @throws LoggerException
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param array|string $message
     * @throws LoggerException
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param array|string $message
     * @throws LoggerException
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param resource $stream &fs.file.pointer;
     * @link https://php.net/manual/en/function.fwrite.php
     * @throws LoggerException
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
     * @param resource $stream &fs.file.pointer;
     * @link https://php.net/manual/en/function.fwrite.php
     * @throws LoggerException
     * @psalm-suppress MissingParamType
     * @phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    protected function writeOutput($stream, string $data = ''): void
    {
        $this->checkStreamResource($stream);

        $tries = self::IO_WRITE_ATTEMPTS;
        $len = strlen($data);

        /** @phan-suppress-next-line PhanNoopConstant */
        for ($written = 0; $written < $len; true) {
            $chunk = substr($data, $written);
            if ('' === $chunk) {
                // @codeCoverageIgnoreStart
                throw new LoggerException();
                // @codeCoverageIgnoreEnd
            }

            $writeResult = $this->fwrite($stream, $chunk);

            if (null === $writeResult || feof($stream)) {
                // @codeCoverageIgnoreStart
                throw new LoggerException();
                // @codeCoverageIgnoreEnd
            }

            if (false === fflush($stream)) {
                // @codeCoverageIgnoreStart
                throw new LoggerException();
                // @codeCoverageIgnoreEnd
            }

            $written += $writeResult;

            if ($written < $len) {
                // @codeCoverageIgnoreStart
                throw new LoggerException();
                // @codeCoverageIgnoreEnd
            }

            if (0 === $writeResult) {
                // @codeCoverageIgnoreStart
                --$tries;
                // @codeCoverageIgnoreEnd
            }

            if ($tries <= 0) {
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
            static function ($_, string $errstr) use (&$error): bool {
                // @codeCoverageIgnoreStart
                $error = $errstr;

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
     * @param string|array $var
     */
    protected static function formatter($var): string
    {
        if (is_scalar($var)) {
            return $var;
        }

        return var_export($var, true);
    }
}
