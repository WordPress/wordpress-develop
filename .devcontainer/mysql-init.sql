/**
 * MySQL server init.
 *
 * SQL queries in this file will be executed the first time the MySQL server is started.
 */

GRANT ALL PRIVILEGES ON wordpress_develop_tests.* TO 'exampleuser'@'localhost' IDENTIFIED BY 'examplepass';
GRANT ALL PRIVILEGES ON wordpress_develop_tests.* TO 'exampleuser'@'%' IDENTIFIED BY 'examplepass';
FLUSH PRIVILEGES;
