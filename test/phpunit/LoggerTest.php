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

namespace phpunit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use sql\MydbException\LoggerException;
use sql\MydbLogger;
use stdClass;
use function bin2hex;
use function fclose;
use function feof;
use function fgets;
use function fopen;
use function fseek;
use function ftruncate;
use function random_bytes;
use function random_int;
use function rewind;
use function stream_get_contents;
use function var_export;
use const PHP_EOL;
use const SEEK_END;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class LoggerTest extends TestCase
{

    protected ?LoggerInterface $logger;

    /**
     * @var resource|null
     */
    protected $stdout = null;

    /**
     * @var resource|null
     */
    protected $stderr = null;

    private string $stdeol = PHP_EOL;

    public function setUp(): void
    {
        $stderr = fopen("php://memory", "rw");
        fseek($stderr, 0);

        $stdout = fopen("php://memory", "rw");
        fseek($stdout, 0);

        $this->stderr = $stderr;
        $this->stdout = $stdout;

        $this->logger = new MydbLogger($this->stdout, $this->stderr, $this->stdeol);
    }

    /**
     * @return array<array<string, string>>
     * @throws \phpunit\Exception
     */
    public function dataProviderStrings(): array
    {
        $eol = $this->stdeol;
        $randomString = bin2hex(random_bytes(random_int(2, 20)));

        return [
            'nothing' => [
                'message' => '',
                'context' => [],
                'stdout' => '',
                'stderr' => '',
                'isError' => true,
            ],
            'nothing-array' => [
                'message' => [],
                'context' => [],
                'stdout' => '',
                'stderr' => '',
                'isError' => true,
            ],
            'nothing-error' => [
                'message' => '',
                'context' => [],
                'stdout' => '',
                'stderr' => '',
            ],
            'something' => [
                'message' => $randomString,
                'context' => [],
                'stdout' => '',
                'stderr' => $randomString . $eol,
                'isError' => true,
            ],
            'something' => [
                'message' => $randomString,
                'context' => [],
                'stdout' => $randomString . $eol,
                'stderr' => '',
            ],
            'chars' => [
                'message' => '___-123\'\"&*^!@&#${}AXC__DA',
                'context' => [],
                'stdout' => '___-123\'\"&*^!@&#${}AXC__DA' . $eol,
                'stderr' => '',
            ],
        ];
    }

    /**
     * @throws \sql\MydbException\LoggerException
     */
    public function testLoggerError(): void
    {
        $string = 'hello-world';
        $context = ['a' => 'b'];
        $this->logger->warning($string, $context);
        $buffers = $this->getBuffers();

        self::assertSame('', $buffers['stdout'], 'STDOUT match');

        self::assertSame(
            $string . $this->stdeol . var_export($context, true) . $this->stdeol,
            $buffers['stderr'],
            'STDERR match'
        );
    }

    /**
     * @throws \sql\MydbException\LoggerException
     */
    public function testLoggerConstructorOut(): void
    {
        $stderr = fopen("php://memory", "rw");
        $stdout = null;

        $this->expectException(LoggerException::class);
        new MydbLogger($stdout, $stderr, $this->stdeol);
    }

    /**
     * @throws \sql\MydbException\LoggerException
     */
    public function testLoggerConstructorErr(): void
    {
        $stderr = null;
        $stdout = fopen("php://memory", "rw");


        $this->expectException(LoggerException::class);
        new MydbLogger($stdout, $stderr, $this->stdeol);
    }

    /**
     * @throws \sql\MydbException\LoggerException
     */
    public function testLoggerStreamErrEnd(): void
    {
        $stdout = fopen("php://memory", "r");
        $stderr = fopen("/etc/hosts", "r");

        while (!feof($stderr)) {
            fgets($stderr);
        }

        $logger = new MydbLogger($stdout, $stderr, $this->stdeol);

        $this->expectException(LoggerException::class);
        $logger->warning('hello');
    }

    public function testLoggerStreamOutClosed(): void
    {
        $stdout = fopen("php://memory", "r");
        $stderr = fopen("php://memory", "r");

        $logger = new MydbLogger($stdout, $stderr, $this->stdeol);

        fclose($stdout);

        $this->expectException(LoggerException::class);
        $logger->info('hello');
    }

    public function testLoggerStreamErrClosed(): void
    {
        $stdout = fopen("php://memory", "r");
        $stderr = fopen("php://memory", "r");

        $logger = new MydbLogger($stdout, $stderr, $this->stdeol);

        fclose($stderr);

        $this->expectException(LoggerException::class);
        $logger->warning('hello');
    }

    public function testLoggerStreamOutBadMode(): void
    {
        $stdout = fopen("php://memory", "r");
        $stderr = fopen("php://memory", "rw");

        $logger = new MydbLogger($stdout, $stderr, $this->stdeol);

        $this->expectException(LoggerException::class);
        $logger->info('hello');
    }

    public function testLoggerStreamErrBadMode(): void
    {
        $stdout = fopen("php://memory", "rw");
        $stderr = fopen("php://memory", "r");

        $logger = new MydbLogger($stdout, $stderr, $this->stdeol);

        $this->expectException(LoggerException::class);
        $logger->warning('hello');
    }

    /**
     * @throws \sql\MydbException\LoggerException
     */
    public function testLoggerStreamOutEnd(): void
    {
        $stderr = fopen("php://memory", "r");
        $stdout = fopen("/etc/hosts", "r");

        while (!feof($stdout)) {
            fgets($stdout);
        }

        $logger = new MydbLogger($stdout, $stderr, $this->stdeol);

        $this->expectException(LoggerException::class);
        $logger->info('hello');
    }

    /**
     * @dataProvider dataProviderStrings
     * @throws \sql\MydbException\LoggerException
     */
    public function testLoggerWithStrings(
        $strOrArray,
        array $ctx,
        string $stdout,
        string $stderr,
        bool $isError = false,
    ): void {
        $api = $isError
            ? ['warning', 'emergency', 'alert', 'critical']
            : ['debug', 'info', 'notice'];

        foreach ($api as $call) {
            $this->logger->$call($strOrArray, $ctx);
            $buffers = $this->getBuffers();

            self::assertSame($stdout, $buffers['stdout'], 'STDOUT match for ' . $call);
            self::assertSame($stderr, $buffers['stderr'], 'STDERR match for ' . $call);
        }

        $this->logger->log('notice', '', []);
        $buffers = $this->getBuffers();
        self::assertSame('', $buffers['stdout'], 'STDOUT match for log');
        self::assertSame('', $buffers['stderr'], 'STDERR match for log');

        $this->logger->log('notice', 'notice-message', []);
        $buffers = $this->getBuffers();
        self::assertSame('notice-message' . $this->stdeol, $buffers['stdout'], 'STDOUT match for log');
        self::assertSame('', $buffers['stderr'], 'STDERR match for log');

        $this->logger->log('warning', 'warning-message', []);
        $buffers = $this->getBuffers();
        self::assertSame('warning-message' . $this->stdeol, $buffers['stdout'], 'STDOUT match for log');
        self::assertSame('', $buffers['stderr'], 'STDERR match for log');
    }

    /**
     * @throws \sql\MydbException\LoggerException
     */
    public function testLoggerWithContext(): void
    {
        $context = ['a' => 'b', 'c' => new stdClass(), 'd' => null];
        $this->logger->log('notice', '', $context);
        $buffers = $this->getBuffers();
        self::assertSame(
            var_export($context, true) . $this->stdeol,
            $buffers['stdout'],
            'STDOUT match for log with context'
        );
        self::assertSame('', $buffers['stderr'], 'STDERR match for log with context');
    }

    protected function tearDown(): void
    {
        $this->logger = null;
    }

    protected function getBuffers(): array
    {
        rewind($this->stdout);
        $out = stream_get_contents($this->stdout);
        fseek($this->stdout, 0, SEEK_END);
        ftruncate($this->stdout, 0);

        rewind($this->stderr);
        $err = stream_get_contents($this->stderr);
        fseek($this->stderr, 0, SEEK_END);
        ftruncate($this->stderr, 0);

        return [
            'stdout' => $out,
            'stderr' => $err,
        ];
    }
}
