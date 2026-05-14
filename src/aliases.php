<?php

use App\ExportImport;

class PBXAliases
{
  const FIELDS = [
    "number" => 0,
    "type" => 0,
    "phone" => 0,
    "users_id" => 0,
    "queues_id" => 0,
    "deleted" => 0
  ],
    TYPE_USER = "user",
    TYPE_QUEUES = "queue",
    TYPE_PHONE = "phone";

  private $typeVariants = [
    [
      "id" => "user",
      "value" => "Пользователь"
    ],
    [
      "id" => "queue",
      "value" => "Очередь"
    ],
    [
      "id" => "phone",
      "value" => "Номер"
    ]
  ];

  public function __construct()
  {
    global $app;
    $container = $app->getContainer();
    $this->db = $container['db'];
    $this->logger = $container['logger'];
    $this->user = $container['auth'];
  }

  private function getTableName()
  {
    return "aliases";
  }

  public function fetchList($filter = "", $start = 0, $end = 20, $onlyCount = 0, $fullnameAsValue = 0, $likeStringValues = true)
  {
    $sql = "SELECT ";
    if (intval($onlyCount)) {
      $ssql = " COUNT(*) ";
    } else {
      $ssql = "`id`";
      foreach (self::FIELDS as $field => $isInt) {
        if (strlen($ssql)) $ssql .= ",";
        $ssql .= "`" . $field . "`";
      }
    }
    $sql .= $ssql . " FROM " . self::getTableName();
    $wsql = "";

    $filter["deleted"] = 0;

    if (is_array($filter)) {
      $fields = self::FIELDS;
      $fields["id"] = 1;
      $wsql = "";
      foreach ($filter as $key => $value) {
        if (isset($fields[$key])) {
          if (array_key_exists($key, $fields) && (intval($fields[$key]) ? intval($value) : strlen($value))) {
            if (strlen($wsql)) $wsql .= " AND ";
            $wsql .= "`" . $key . "` " . (intval($fields[$key]) ? "='" : ($likeStringValues ? "LIKE '%" : "='")) . "" . ($fields[$key] ? intval($value) : trim(addslashes($value))) . "" . (intval($fields[$key]) ? "'" : ($likeStringValues ? "%'" : "'"));
          }
        }
      }
    }

    if (strlen($wsql)) {
      $sql .= " WHERE " . $wsql;
    }

    //$res = $this->db->query($sql);
    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM : \PDO::FETCH_ASSOC);
    $result = [];

    while ($row = $res->fetch()) {
      if ($onlyCount) {
        return intval($row[0]);
      }

      $result[] = $row;
    }
    return $result;
  }

  public function addUpdate($values)
  {
    if (is_array($values)) {
      if (isset($values['id']) && intval($values['id'])) {
        $sql = "UPDATE " . $this->getTableName() . " SET ";
      } else {
        $sql = "INSERT INTO " . $this->getTableName() . " SET ";
      }
    }
    if (isset($values["number"]) && strlen($values["number"])) {
      if (!$this->isUniqueColumn("number", $values['number'], $values['id'])) {
        return ["result" => false, "message" => "Номер занят другим алиасом"];
      }
      $pattern = '/[0-9]/';
      $matches = preg_replace($pattern, "", $values["phone"]);
      if (strlen($matches)) {
        return ["result" => false, "message" => "Номер может содержать только цифры"];
      }
    }
    if (isset($values["phone"]) && strlen($values["phone"])) {
      $pattern = '/[0-9]/';
      $matches = preg_replace($pattern, "", $values["phone"]);
      if (strlen($matches)) {
        return ["result" => false, "message" => "Номер может содержать только цифры"];
      }
    }
    $ssql = "number ='" . trim(addslashes($values["number"])) . "',";
    $ssql .= "type ='" . trim(addslashes($values["type"])) . "', ";
    if ($values['type'] == self::TYPE_USER) {
      $ssql .= "users_id =" . intval($values["users_id"]) . ", 
                     queues_id = null,
                     phone = null";
    } else if ($values['type'] == self::TYPE_QUEUES) {
      $ssql .= "queues_id =" . intval($values["queues_id"]) . ", 
                      users_id = null,
                      phone = null";
    } else {
      $ssql .= "phone ='" . trim(addslashes($values["phone"])) . "', 
                      users_id = null, 
                      queues_id = null";
    }

    if (strlen($ssql)) {
      $sql .= $ssql;
      if (isset($values['id']) && intval($values['id'])) {
        $sql .= " WHERE id ='" . intval($values['id']) . "'";
      }
      //die($sql);
      $res = $this->db->query($sql);
      if ($res) {
        return ["result" => true, "message" => "Операция прошла успешно"];
      } else {
        return ["result" => false, "message" => "Произошла ошибка выполнения операции"];
      }
    }
  }

  public function remove($id) {
    $sql = "UPDATE {$this->getTableName()} SET deleted=1 WHERE id=:id";
    $stmt = $this->db->prepare($sql);
    $stmt->bindParam('id',$id);
    return $stmt->execute()?true:false;
  }

  public function getType()
  {
    return $this->typeVariants;
  }

  private function isUniqueColumn($column, $name, $id)
  {
    if (in_array($column, SELF::FIELDS)) {
      $data = $this->fetchList([$column => $name], 0, 3, 0, 0, 0);
      if (is_array($data)) {
        if (COUNT($data) > 1) {
          return false;
        } else if (COUNT($data) == 1) {
          if (intval($id)) {
            return $data[0]["id"] == intval($id);
          } else {
            return false;
          }
        }
      }
      return true;
    }
  }


  public function export(){
    $res = [];
    foreach ($this->fetchList(null, 0, null, 0) as $item) {
      $alias = [];
      $alias['number'] = $item['number'];
      $alias['type'] = $item['type'];
      $alias['deleted'] = $item['deleted'];

      $res['aliases'][] = $alias;
    }

    return $res;
  }

  public function import($data, $delete = false) {
    $result = true;
    $exportImport = new ExportImport();

    if ($delete) {
      $exportImport->truncateTables([
        $this->getTableName()
      ]);
    }

    $aliases = $data->aliases;
    foreach ($aliases as $item) {
      $exportImport->importAction($item, ["number", "type", "deleted"], $this->getTableName());
    }

    return $result;
  }
}