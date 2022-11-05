<!---
This file is part of the sshilko/php-sql-mydb package.

(c) Sergei Shilko <contact@sshilko.com>

MIT License

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license https://opensource.org/licenses/mit-license.php MIT
-->
### Solution to 'MySQL server has gone away'

[Solutions for the 2006 MySQL error](https://haydenjames.io/mysql-server-has-gone-away-error-solutions/)

 1. Check values of `wait_timeout` MySQL setting along with `net_read_timeout`, `net_write_timeout`, `interactive_timeout` and `connect_timeout`
 2. Verify PHP MySQL configuration options on php.ini: `mysql.connect_timeout` and `mysql.allow_persistent`
 3. To allow slow/long-running queries, increase the `default_socket_timeout` and `max_input_time`. They'll be applied
 if no `mysqlnd` timeouts are set per (`mysqlnd.net_read_timeout`)
 4. If expect large results, check value of `max_allowed_package` to receive a packet that's too large
 5. As last, increasing `innodb_log_file_size` MySQL variable might help

### Finetuning MySQL

 - `explain extended` and `show warnings`
 - `show index from table`
 - `show table status where Name = 'table'`
 - `show engine innodb status`
 - Rules of Thumb for MySQL [Rick's RoTs](http://mysql.rjweb.org/doc.php/ricksrots#partitioning)
 - MySQL process list `SELECT ID,USER,HOST,DB,COMMAND,TIME,STATE,LEFT(REPLACE(REPLACE(INFO, '\n', ' '), '    ', ' '), 80) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE STATE != '' ORDER BY TIME DESC LIMIT 50`
 - SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_pages_%' ([Values are in blocks of 16 kilobytes](https://www.fromdual.com/de/innodb-variables-and-status-explained))

#### MySQL buffer `innodb_buffer_pool_size` in Gb
`SELECT CONCAT(CEILING(RIBPS/POWER(1024,pw)), SUBSTR(' KMGT',pw+1,1) Recommended_InnoDB_Buffer_Pool_Size FROM
  (
      SELECT RIBPS,FLOOR(LOG(RIBPS)/LOG(1024)) pw
        FROM (SELECT SUM(data_length+index_length)*1.1*growth RIBPS
                FROM information_schema.tables AAA,
             (SELECT 1.25 growth) BBB
               WHERE ENGINE='InnoDB') AA
  ) A;`
#### MySQL table size
`SELECT table_schema,
         table_name,
         round(((data_length + index_length) / 1024 / 1024), 2) 'SizeMB'
    FROM information_schema.TABLES
 ORDER BY (data_length + index_length) DESC LIMIT 10`

#### MySQL table partition sizes
`SELECT table_schema as 'Database',
         table_name AS 'Table',
         PARTITION_NAME,
         SUBPARTITION_NAME,
         round(((data_length + index_length) / 1024 / 1024), 2) 'SizeMB'
    FROM information_schema.PARTITIONS
  ORDER BY (data_length + index_length) DESC, TABLE_NAME LIMIT 10`
#### MySQL Optimizer
`SET SESSION optimizer_trace="enabled=on";
    SELECT ...;
    SELECT * FROM INFORMATION_SCHEMA.OPTIMIZER_TRACE;
    SET SESSION optimizer_trace="enabled=off";`

#### MySQL Profiling
`SET SESSION profiling=1;
    SELECT ...;
    SET SESSION profiling=0;
    SHOW PROFILE;
   `
