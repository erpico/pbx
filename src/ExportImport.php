<?php

namespace App;

use Erpico\PBXRules;

class ExportImport
{
  // здесь хранятся модели
  protected $objects;

  // название файла
  private $url;

  //
  private $db;

  //
  const ALIASES = [
    "User" => "user_groups",
    "PBXBlacklist" => "blacklist",
    "PBXPhone" => "phones",
    "PBXChannel" => "channels",
    "PBXAliases" => "aliases",
    "PBXRules" => "rules",
    "PBXQueue" => "queues"
  ];

  public function __construct($url = null)
  {
    global $app;
    $container = $app->getContainer();
    // объекты для импорта и экспорта
    $this->objects = [
      new \Erpico\User(),
      new \PBXQueue(),
      new \PBXPhone(),
      new \PBXChannel(),
      new \PBXAliases(),
      //new PBXRules(),
      new \PBXContactGroups(),
      new \PBXBlacklist($container)
    ];
    $this->url = $url ?: "epbx.".(new \DateTime())->format('Ymd').".ecfg";
    $this->db = $container['db'];
  }

  public function export() {
    $export = [];
    foreach ($this->objects as $object) {
      $path = explode('\\', get_class($object));
      $class = self::ALIASES[array_pop($path)];
      $export = array_merge($export, $object->export($object, $class));
    }

    return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }

  public function import($delete = false) {
    $result = true;
    $url = $this->url;
    $data = json_decode(file_get_contents($url));

    foreach ($this->objects as $object) {
      $result *= $object->import($data, $delete);
    }

    return $result;
  }

  public function truncateTables($array) {
    foreach ($array as $item) {
      $this->db->query("TRUNCATE {$item}");
    }
  }

  public function importAction
  (
    $data, // данные
    $fields, // поля
    $tableName // таблица
  ) {

    $usedData = [];
    foreach ($fields as $field) {
      if (is_array($data)) {
        $usedData[] = $data[$field] === null ? "NULL" : "'" . trim($data[$field]) . "'";
      } else {
        $usedData[] = $data->$field === null ? "NULL" : "'" . trim($data->$field) . "'";
      }
    }


    $implodeFields = implode(",", $fields);
    $implodeData = implode(",", $usedData);

    $sql = htmlspecialchars_decode("INSERT INTO {$tableName} ({$implodeFields}) VALUES ({$implodeData})");

    try {
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      $result = $this->db->lastInsertId();
    } catch (\Exception $exception) {
      $result = null;
    } finally {
      return $result;
    }
  }
}