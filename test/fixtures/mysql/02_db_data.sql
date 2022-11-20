-- This file is part of the sshilko/php-sql-mydb package.
--
-- (c) Sergei Shilko <contact@sshilko.com>
--
-- MIT License
--
-- For the full copyright and license information, please view the LICENSE
-- file that was distributed with this source code.
--
-- @license https://opensource.org/licenses/mit-license.php MIT

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
INSERT INTO `myusers_devices` (id_binary, id_user, id_reality, time_saved, time_last_update, device_token, semver, sandbox, handler, provider)
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

TRUNCATE TABLE `mydecimals`;
INSERT INTO `mydecimals` (id, cost) VALUES (1, 1.1), (2, 1.20), (3, '0.3');