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
use sql\MydbEnvironment;
use function getmypid;
use function posix_kill;
use const SIGHUP;
use const SIGINT;
use const SIGTERM;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MydbEnvironmentTest extends TestCase
{
    /**
     * @return array<array<string, string>>
     * @throws Exception
     */
    public function dataProviderSignals(): array
    {
        return [
            'SIGHUP' => [
                'signals' => [SIGHUP],
                'expect' => [SIGHUP],
            ],
            'SIGTERM' => [
                'signals' => [SIGTERM],
                'expect' => [SIGTERM],
            ],
            'SIGINT' => [
                'signals' => [SIGINT],
                'expect' => [SIGINT],
            ],
            'SIGINT,SIGTERM,SIGHUP' => [
                'signals' => [SIGINT,SIGHUP,SIGTERM],
                'expect' => [SIGINT,SIGHUP,SIGTERM],
            ],
        ];
    }

    /**
     * @dataProvider dataProviderSignals
     * @throws EnvironmentException
     */
    public function testSignalSighupTrap(array $signals, array $expect): void
    {
        $env = new MydbEnvironment();
        $env->startSignalsTrap();
        $pid = getmypid();
        foreach ($signals as $signal) {
            posix_kill($pid, $signal);
        }
        $signals = $env->endSignalsTrap();
        self::assertSame($expect, $signals);
    }
}
