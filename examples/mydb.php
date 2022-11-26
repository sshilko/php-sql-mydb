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

include_once __DIR__ . '/../vendor/autoload.php';

$registry = new \sql\MydbRegistry();
$mylogger = new \sql\MydbLogger();

$auth = new \sql\MydbCredentials('127.0.0.1', 'root', 'root', 'mydb', 3306);
$opts = new \sql\MydbOptions();
$opts->setTransactionIsolationLevel(\sql\MydbOptions::TRANSACTION_ISOLATION_LEVEL_READ_COMMITTED);
$mydb = new \sql\Mydb($auth, $mylogger, $opts);

$mydb->beginTransaction();

$array1 = $mydb->select("SELECT 123");
$array2 = $mydb->query("SELECT 123");

assert($array1 === $array2);

$mydb->command('CREATE TEMPORARY TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `myenum` enum ("e1","e2")  NOT NULL DEFAULT "e1",
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4');

$user10 = $mydb->insert("INSERT INTO users (id, name) VALUES (10, 'user10')");
$user20 = $mydb->insertOne(['id' => 20, 'name' => 'user20'], 'users');
$mydb->insertMany([[30, 'user30'], [40, 'user40']], ['id', 'name'], 'users');

assert(['10', '20', '30', '40'] === array_column($mydb->query("SELECT id, name FROM users ORDER BY id ASC"), 'id'));

$mydb->delete('DELETE FROM users WHERE id = 40');

assert(['10', '20', '30'] === array_column($mydb->select("SELECT id, name FROM users ORDER BY id ASC"), 'id'));

$enum = $mydb->getEnumValues('users', 'myenum');
assert(['e1', 'e2'] === $enum);

$prim = $mydb->getPrimaryKeys('users');
assert(['id'] === $prim);

$mydb->deleteWhere(['id' => '30'], 'users');

assert(['10', '20'] === array_column($mydb->select("SELECT id, name FROM users ORDER BY id ASC"), 'id'));

$mydb->updateWhere(['id' => 99], ['id' => 10], 'users');
assert(['20', '99'] === array_column($mydb->select("SELECT id, name FROM users ORDER BY id ASC"), 'id'));

$mydb->rollbackTransaction();

$db10 = new \sql\Mydb($auth, $mylogger, $opts);
$db10->open();

$db20 = new \sql\Mydb($auth, $mylogger, $opts);
$registry['db1'] = $mydb;
$registry['db2'] = $db10;
$registry['db3'] = $db20;

$db10->close();
$db20->close();

echo 'OK';