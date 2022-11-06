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

use sql\MydbException\OptionException;
use sql\MydbOptions;
use const E_ALL;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class OptionsTest extends includes\BaseTestCase
{
    public function testNonInteractiveTimeouts(): void
    {
        $options = new MydbOptions();
        $options->setNonInteractiveTimeout(10);
        self::assertSame(10, $options->getNonInteractiveTimeout());
    }

    public function testServerSideSelectTimeouts(): void
    {
        $options = new MydbOptions();
        $options->setServerSideSelectTimeout(10);
        self::assertSame(10, $options->getServerSideSelectTimeout());
    }

    public function testErrorReporting(): void
    {
        $options = new MydbOptions();
        $options->setErrorReporting(E_ALL);
        self::assertSame(E_ALL, $options->getErrorReporting());
    }

    public function testReadTimeout(): void
    {
        $options = new MydbOptions();
        $options->setReadTimeout(5);
        self::assertSame(5, $options->getReadTimeout());
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testNetworkBuffer(): void
    {
        $options = new MydbOptions();
        $options->setNetworkBufferSize(4097);
        self::assertSame(4097, $options->getNetworkBufferSize());
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testNetworkBufferMin(): void
    {
        $options = new MydbOptions();
        $this->expectException(OptionException::class);
        $options->setNetworkBufferSize(4095);
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testNetworkBufferMax(): void
    {
        $options = new MydbOptions();
        $this->expectException(OptionException::class);
        $options->setNetworkBufferSize(16385);
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testSetReadBuffer(): void
    {
        $options = new MydbOptions();
        $options->setNetworkReadBuffer(12000);
        self::assertSame(12000, $options->getNetworkReadBuffer());
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testSetReadBufferMin(): void
    {
        $options = new MydbOptions();
        $this->expectException(OptionException::class);
        $options->setNetworkReadBuffer(8191);
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testSetReadBufferMax(): void
    {
        $options = new MydbOptions();
        $this->expectException(OptionException::class);
        $options->setNetworkReadBuffer(131073);
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testMysqliReport(): void
    {
        $options = new MydbOptions();
        $options->setClientErrorLevel(0);
        self::assertSame(0, $options->getClientErrorLevel());
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testMysqliReportMin(): void
    {
        $options = new MydbOptions();
        $this->expectException(OptionException::class);
        $options->setClientErrorLevel(-1);
    }

    /**
     * @throws \sql\MydbException\OptionException
     */
    public function testMysqliReportMax(): void
    {
        $options = new MydbOptions();
        $this->expectException(OptionException::class);
        $options->setClientErrorLevel(256);
    }

    public function testSetTimeZone(): void
    {
        $options = new MydbOptions();
        $options->setTimeZone('Europe/Helsinki');
        self::assertSame('Europe/Helsinki', $options->getTimeZone());
    }

    public function testAutocommit(): void
    {
        $options = new MydbOptions();
        $options->setAutocommit(true);
        self::assertTrue($options->isAutocommit());
        $options->setAutocommit(false);
        self::assertFalse($options->isAutocommit());
    }

    public function testCharset(): void
    {
        $options = new MydbOptions();
        $options->setCharset("utf8mb4");
        self::assertSame('utf8mb4', $options->getCharset());
    }

    public function testPersistent(): void
    {
        $options = new MydbOptions();
        $options->setPersistent(true);
        self::assertTrue($options->isPersistent());
        $options->setPersistent(false);
        self::assertFalse($options->isPersistent());
    }

    public function testReadonly(): void
    {
        $options = new MydbOptions();
        $options->setReadonly(true);
        self::assertTrue($options->isReadonly());
        $options->setReadonly(false);
        self::assertFalse($options->isReadonly());
    }
}
