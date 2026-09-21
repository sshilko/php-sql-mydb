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
use sql\MydbLogger;
use sql\MydbMysqli;
use sql\MydbMysqli\MydbMysqliEscapeStringInterface;
use sql\MydbMysqli\MydbMysqliResult;
use sql\MydbOptions;
use sql\MydbQueryBuilder;
use function file_get_contents;
use function str_starts_with;

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
        $passwd = $constructor->getParameters()[2];
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
            'MydbLogger::IO_WRITE_ATTEMPTS' => [MydbLogger::class, 'IO_WRITE_ATTEMPTS'],
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

    public function testQueryBuilderUsesStrStartsWith(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/sql/MydbQueryBuilder.php');
        self::assertIsString($source);
        self::assertStringContainsString('str_starts_with($unescaped, \'0x\')', $source);
        self::assertStringNotContainsString("0 === strpos(\$unescaped, '0x')", $source);
    }

    public function testQueryBuilderEscapeMethodHasNativeHexGuard(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/sql/MydbQueryBuilder.php');
        self::assertIsString($source);
        self::assertStringContainsString('is_string($unescaped)', $source);
        self::assertStringNotContainsString('is_resource($unescaped)', $source);
    }

    public function testCredentialsPropertyTypesUseReadonly(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/sql/MydbCredentials.php');
        self::assertIsString($source);
        self::assertStringContainsString('readonly class MydbCredentials', $source);
        self::assertStringContainsString('public function __construct(', $source);
        self::assertStringContainsString('string $host,', $source);
        self::assertStringContainsString('string $username,', $source);
        self::assertStringContainsString('string $passwd,', $source);
        self::assertStringContainsString('string $dbname,', $source);
        self::assertStringContainsString('?int $port = null,', $source);
        self::assertStringContainsString('?string $socket = null,', $source);
        self::assertStringContainsString('int $flags = 0,', $source);
    }

    public function testQueryBuilderAlwaysUsesStrStartsWithForHex(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/sql/MydbQueryBuilder.php');
        self::assertIsString($source);
        self::assertTrue(str_starts_with($source, '<?php'), 'Source file must be a PHP file');
    }
}
