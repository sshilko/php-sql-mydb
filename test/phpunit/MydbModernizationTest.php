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
 *
 * @license https://opensource.org/licenses/mit-license.php MIT
 */

declare(strict_types = 1);

namespace phpunit;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use SensitiveParameter;
use sql\Mydb;
use sql\MydbCredentials;
use sql\MydbMysqli;
use sql\MydbMysqli\MydbMysqliEscapeStringInterface;
use sql\MydbMysqli\MydbMysqliResult;
use sql\MydbOptions;
use sql\MydbQueryBuilder;
use Stringable;

/**
 * Acceptance tests for the PHP 8.3 modernization of the library.
 *
 * These tests pin down the language features introduced by the
 * revitalization plan and fail on the pre-modernization source.
 *
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MydbModernizationTest extends TestCase
{

    public function testMydbCredentialsIsReadonlyClass(): void
    {
        $reflection = new ReflectionClass(MydbCredentials::class);
        self::assertTrue($reflection->isReadOnly(), 'MydbCredentials must be a readonly class');
    }

    public function testMydbCredentialsConstructorIsPromoted(): void
    {
        $constructor = (new ReflectionClass(MydbCredentials::class))->getConstructor();
        self::assertNotNull($constructor);
        self::assertCount(7, $constructor->getParameters(), 'MydbCredentials must use constructor property promotion');
        $hasPromotion = $constructor->getParameters();
        self::assertNotEmpty($hasPromotion);
    }

    public function testMydbCredentialsPasswdIsSensitiveParameter(): void
    {
        $constructor = (new ReflectionClass(MydbCredentials::class))->getConstructor();
        self::assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        $passwd     = $parameters[2] ?? null;
        if (null === $passwd) {
            self::fail('MydbCredentials::__construct must have a third parameter passwd');
        }
        self::assertSame('passwd', $passwd->getName());
        $attributes = $passwd->getAttributes(SensitiveParameter::class);
        self::assertNotEmpty($attributes, 'MydbCredentials::passwd must be marked #[SensitiveParameter]');
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function dataProviderTypedClassConstants(): array
    {
        return [
            'MydbOptions::NET_CMD_BUFFER_SIZE_MIN' => [MydbOptions::class, 'NET_CMD_BUFFER_SIZE_MIN'],
            'MydbOptions::NET_CMD_BUFFER_SIZE_MAX' => [MydbOptions::class, 'NET_CMD_BUFFER_SIZE_MAX'],
            'MydbOptions::NET_READ_BUFFER_MIN' => [MydbOptions::class, 'NET_READ_BUFFER_MIN'],
            'MydbOptions::NET_READ_BUFFER_MAX' => [MydbOptions::class, 'NET_READ_BUFFER_MAX'],
            'MydbMysqli::SQL_MODE' => [MydbMysqli::class, 'SQL_MODE'],
            'MydbMysqliResult::MYSQLI_ASSOC' => [MydbMysqliResult::class, 'MYSQLI_ASSOC'],
        ];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('dataProviderTypedClassConstants')]
    public function testClassConstantsHaveNativeTypes(string $className, string $constantName): void
    {
        $reflection = new ReflectionClassConstant($className, $constantName);
        self::assertNotNull($reflection->getType(), "$className::$constantName must have a native type");
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('dataProviderOverrideAnnotatedMethods')]
    public function testInterfaceMethodsAreOverrideAnnotated(string $className, string $methodName): void
    {
        $reflection = new ReflectionMethod($className, $methodName);
        self::assertNotEmpty(
            $reflection->getAttributes(Override::class),
            "$className::$methodName must carry the #[Override] attribute"
        );
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function dataProviderOverrideAnnotatedMethods(): array
    {
        return [
            'MydbCredentials::getHost' => [MydbCredentials::class, 'getHost'],
            'MydbCredentials::getUsername' => [MydbCredentials::class, 'getUsername'],
            'MydbCredentials::getPasswd' => [MydbCredentials::class, 'getPasswd'],
            'MydbCredentials::getDbname' => [MydbCredentials::class, 'getDbname'],
            'MydbCredentials::getPort' => [MydbCredentials::class, 'getPort'],
            'MydbCredentials::getSocket' => [MydbCredentials::class, 'getSocket'],
            'MydbCredentials::getFlags' => [MydbCredentials::class, 'getFlags'],
            'Mydb::query' => [Mydb::class, 'query'],
        ];
    }

    public function testQueryBuilderEscapesHexValuesWithoutQuotes(): void
    {
        $esc = $this->createMock(MydbMysqliEscapeStringInterface::class);
        $esc->method('realEscapeString')->willReturnArgument(0);
        $builder = new MydbQueryBuilder($esc);

        self::assertSame('0xAB', $builder->escape('0xab', "'"), 'Even-length 0x values stay unquoted and uppercased');
        self::assertSame("'0xab1'", $builder->escape('0xab1', "'"), 'Odd-length 0x values behave as plain words');
    }

    public function testQueryBuilderEscapesStringableObjectsWithoutQuotes(): void
    {
        $esc = $this->createMock(MydbMysqliEscapeStringInterface::class);
        $esc->expects(self::never())->method('realEscapeString');
        $builder = new MydbQueryBuilder($esc);

        $stringable = new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return 'NOW(123)';
            }
        };

        self::assertSame('NOW(123)', $builder->escape($stringable, "'"));
    }

    public function testQueryBuilderIdentifiersStayBareAndValidated(): void
    {
        $esc = $this->createMock(MydbMysqliEscapeStringInterface::class);
        $esc->method('realEscapeString')->willReturnArgument(0);
        $builder = new MydbQueryBuilder($esc);

        /**
         * quoteIdentifier() validates but must not alter the identifier,
         * otherwise every previously generated SQL string would change.
         */
        self::assertSame('myusers', $builder->quoteIdentifier('myusers'));
        self::assertSame('db1.myusers', $builder->quoteIdentifier('db1.myusers'));
        self::assertSame(
            "INSERT INTO myusers (id,name) VALUES (1,'user1')",
            $builder->insertOne(['id' => 1, 'name' => 'user1'], 'myusers', 'INSERT'),
            'Identifier hardening must not change the generated SQL for valid input'
        );
    }
}
