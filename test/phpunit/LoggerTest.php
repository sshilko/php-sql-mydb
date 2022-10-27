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
use resource;
use stdClass;

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
     * @var null|resource
     */
    protected $stdout = null;

    /**
     * @var null|resource
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
            'stderr' => $err
        ];
    }

    /**
     * @return array<array<string, string>>
     * @throws \Exception
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
                'isError' => true
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
                'isError' => true
            ],
            'something' => [
                'message' => $randomString,
                'context' => [],
                'stdout' => $randomString . $eol,
                'stderr' => ''
            ],
            'chars' => [
                'message' => '___-123\'\"&*^!@&#${}AXC__DA',
                'context' => [],
                'stdout' => '___-123\'\"&*^!@&#${}AXC__DA' . $eol,
                'stderr' => ''
            ]
        ];
    }

    /**
     * @dataProvider dataProviderStrings
     * @throws LoggerException
     */
    public function testLoggerWithStrings(string $str, array $ctx, string $stdout, string $stderr, bool $isError = false): void
    {
        $api = $isError ? ['warning', 'emergency', 'alert', 'critical'] : ['debug', 'info', 'notice'];

        foreach ($api as $call) {
            $this->logger->$call($str, $ctx);
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
     * @throws LoggerException
     */
    public function testLoggerWithContext(): void
    {
        $context = ['a' => 'b', 'c' => new stdClass(), 'd' => null];
        $this->logger->log('notice', '', $context);
        $buffers = $this->getBuffers();
        self::assertSame(
            var_export($context, true) . $this->stdeol,
            $buffers['stdout'], 'STDOUT match for log with context'
        );
        self::assertSame('', $buffers['stderr'], 'STDERR match for log with context');
    }

}
