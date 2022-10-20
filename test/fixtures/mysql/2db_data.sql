/**
 * This file is part of the sshilko/php-sql-mydb package.
 *
 * (c) Sergei Shilko <contact@sshilko.com>
 *
 * MIT License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.

 @license https://opensource.org/licenses/mit-license.php MIT
 */

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
truncate table `countries`;
INSERT INTO `countries`
VALUES (1, 1, 'US', 'UNITED STATES', '', 'officially assigned', 'UNITED STATES'),
       (242, 2, 'RU', 'RUSSIAN FEDERATION', '', 'officially assigned', 'RUSSIAN FEDERATION'),
       (65, 2, 'DE', 'GERMANY', '', 'officially assigned', 'GERMANY');

truncate table `myusers`;
INSERT INTO `myusers`
VALUES (1, 'user1'),
       (2, 'user2'),
       (3, 'user3');

truncate table `myusers_devices`;
INSERT INTO `myusers_devices`
VALUES (0x6578, 1, 1, '2011-01-01 00:00:00', '2011-01-02 00:00:00',
        'd3ab006bf3b4583b254370efd1ffe77fd4cace70457a78256d21e84c5df7a5d8',
        '1.0.0',
        0,
        '1',
        'Sany'),
       (0x657861, 1, 1, '2011-01-02 00:00:00', '2011-01-03 00:00:00',
        'd4ab006bf3b4583b254370efd1ffe77fd4cace70457a78256d21e84c5df7a5d8',
        '1.0.0',
        1,
        '2',
        'Sany'),
       (0x6578616d, 1, 1, '2011-01-03 00:00:00', '2011-01-04 00:00:00',
        'aR82sLWgCH8:APA91bF4ieeDMqQaq4O38YszPGlrrmQsc-uDwa4-TeI2TkQVURMsAXw3C-jeEIxQ-_uroAR8zvLHzgCJ9gsYy9FcHe7Uh-NgqHdNgn7_cZ1_Vu9Ne3qL_2z-B8BvfgKHbiieiwtC-ZVi',
        '2.2.1',
        0,
        '3',
        'Sany'),
       (0x6578616d70, 2, 1, '2011-01-04 00:00:00', '2011-01-05 00:00:00',
        'd3ab002bf314585b2543706fd1ffe77fd4cace70457a78256d21e84c5df7a5d8',
        '1.0.0',
        0,
        '1',
        'Sany');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
