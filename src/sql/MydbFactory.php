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

/**
 * Initializes library defaults and assembles Mydb instances
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 * @see https://github.com/sshilko/php-sql-mydb
 */
final readonly class MydbFactory
{
    /**
     * Create options initialized with library defaults
     *
     * @throws MydbException\OptionException
     */
    public function createOptions(): MydbOptionsInterface
    {
        $options = new MydbOptions();
        $options->setServerSideSelectTimeout(MydbOptions::DEFAULT_SERVER_SELECT_TIMEOUT);
        $options->setConnectTimeout(MydbOptions::DEFAULT_CONNECT_TIMEOUT);
        $options->setErrorReporting(MydbOptions::DEFAULT_ERROR_REPORTING);
        $options->setReadTimeout(MydbOptions::DEFAULT_READ_TIMEOUT);
        $options->setNetworkBufferSize(MydbOptions::DEFAULT_NETWORK_BUFFER_SIZE);
        $options->setNetworkReadBuffer(MydbOptions::DEFAULT_NETWORK_READ_BUFFER);
        $options->setClientErrorLevel(MydbOptions::DEFAULT_CLIENT_ERROR_LEVEL);
        $options->setTimeZone(MydbOptions::DEFAULT_TIMEZONE);
        $options->setNonInteractiveTimeout(MydbOptions::DEFAULT_NON_INTERACTIVE_TIMEOUT);
        $options->setAutocommit(MydbOptions::DEFAULT_AUTOCOMMIT);
        $options->setCharset(MydbOptions::DEFAULT_CHARSET);
        $options->setPersistent(MydbOptions::DEFAULT_PERSISTENT);
        $options->setReadonly(MydbOptions::DEFAULT_READONLY);

        return $options;
    }

    /**
     * Create options preconfigured for a readonly connection
     *
     * @throws MydbException\OptionException
     */
    public function createReadonlyOptions(): MydbOptionsInterface
    {
        $options = $this->createOptions();
        $options->setReadonly(true);
        $options->setAutocommit(true);
        $options->setTransactionIsolationLevel(MydbOptions::TRANSACTION_ISOLATION_LEVEL_READ_COMMITTED);

        return $options;
    }

    /**
     * Assemble a Mydb instance, initializing defaults where not provided
     *
     * @param \sql\MydbCredentialsInterface $credentials database credentials
     * @param \Psr\Log\LoggerInterface $logger PSR-3 logger
     * @param \sql\MydbOptionsInterface|null $options options, defaults when omitted
     * @param \sql\MydbMysqliInterface|null $mysqli mysqli facade, default when omitted
     * @param \sql\MydbEnvironmentInterface|null $environment environment facade, default when omitted
     * @param \sql\MydbQueryBuilderInterface|null $queryBuilder SQL builder, default when omitted
     * @param \sql\MydbListenerInterface|null $listener event listener, default when omitted
     *
     * @throws MydbException\OptionException
     */
    public function create(
        MydbCredentialsInterface $credentials,
        LoggerInterface $logger = new MydbLogger(),
        ?MydbOptionsInterface $options = null,
        ?MydbMysqliInterface $mysqli = null,
        ?MydbEnvironmentInterface $environment = null,
        ?MydbQueryBuilderInterface $queryBuilder = null,
        ?MydbListenerInterface $listener = null,
    ): Mydb {
        return new Mydb(
            $credentials,
            $logger,
            $options ?? $this->createOptions(),
            $mysqli,
            $environment,
            $queryBuilder,
            $listener,
        );
    }
}
