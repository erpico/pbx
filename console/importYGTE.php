<?php


require __DIR__ . '/../vendor/autoload.php';
session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

if (!$container['db'] instanceof \PDO) {
  printf("No database connection");
  exit(2);
}

$db = $container['db'];

set_time_limit(3600);

require __DIR__.'/../vendor/autoload.php';

echo "Создание таблицы...\n";

$res = $db->query("
CREATE TABLE IF NOT EXISTS `reestr` (
`id` INT NOT NULL AUTO_INCREMENT,
  `account` BIGINT UNSIGNED NULL DEFAULT NULL,
  `address` VARCHAR(128) NULL DEFAULT NULL,
  `number` INT UNSIGNED NULL DEFAULT NULL,
  `debt` FLOAT(8,2) NULL DEFAULT NULL,
  PRIMARY KEY (`id`));
");
$res->execute();

echo "Таблица создана.\n";

$ftp_server = "disp2.ddns.net";
$port = 13400;
$local_file = 'ygteData.txt';
$server_file = 'out_1C/reestr_out_1C.txt';
$ftp_user = 'teplo\erpico';
$ftp_pass = '12345';

$ftp = ftp_connect($ftp_server, $port);

if (@ftp_login($ftp, $ftp_user, $ftp_pass)) {
  echo "Произведён вход на $ftp_server под именем $ftp_user\n";
  ftp_pasv($ftp, true);

  if (ftp_get($ftp, $local_file, $server_file)) {
    echo "Произведена запись в $local_file\n";
  } else {
    echo "Не удалось завершить операцию\n";
  }
} else {
  echo "Не удалось войти под именем $ftp_user\n";
}

ftp_close($ftp);

echo "Открытие файла\n";

$f = fopen($local_file, 'rt');

while ($s = fgets($f)) {
  $s = explode('#', $s);
  $account = (int)preg_replace('/[^0-9]/', '', $s[0]);
  $address = $s[1];
  $number = $s[2];
  $dept = str_replace(",",".",trim($s[3]));

  $sql = "SELECT id FROM `reestr` WHERE `account` = $account";
  $res = $db->query($sql);
  $row = $res->fetch();

  $ssql = $row ? "UPDATE" : "INSERT INTO";
  $ssql .= " `reestr` SET `account` = $account, `address` = '$address', `number` = $number, `debt` = $dept";
  if ($row) $ssql .= " WHERE id = ".$row['id'];
  $db->query($ssql);
}
echo "done.\n";
fclose($f);
