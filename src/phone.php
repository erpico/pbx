<?php

class PBXPhone {
  protected $db;
  private $cfgSettings;
  const FIELDS = [
    "phone" => 0,
    "model" => 0,
    "mac" => 0,
    "user_id" => 1,
    "login" => 0,
    "password" => 0,
    "rules" => 0,
    "default_phone" => 1
  ];

  public function __construct() {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];
    $this->server_host = $container['server_host'];
    
    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();
  }

  private function getTableName() {
    return "acl_user_phone";
  }

  private function setCfgSettings($server, $login, $password) {
    $this->cfgSettings = [
    "sipphone.integrated" => 1,
    "sipphone.server" => $server,
    "sipphone.user" => $login,
    "sipphone.password" => $password
    ];
  }

  public function getCfgSettings() {
    return $this->cfgSettings;
  }

  public function fetchList($filter = "", $start = 0, $end = 20, $onlyCount = 0) {
    $sql = "SELECT ";
    if (intval($onlyCount)) {
      $ssql = " COUNT(*) ";  
    } else {
      $ssql = "`id`";
      foreach (self::FIELDS as $field => $isInt) {        
        if (strlen($ssql)) $ssql .= ",";
        $ssql .= "`".$field."`";        
      }
    }

    $sql .= $ssql." FROM ".self::getTableName();
    $wsql = "";
    if (is_array($filter)) {
      $fields = self::FIELDS;
      $fields["id"] = 1;
      $wsql = "";
      foreach ($filter as $key => $value) {
        if (isset($fields[$key])) {
          if (array_key_exists($key,$fields) && (intval($fields[$key]) ? intval($value) : strlen($value) )) {
            if (strlen($wsql)) $wsql .= " AND ";
            $wsql .= "`".$key."` ".(intval($fields[$key]) ? "='" : "LIKE '%")."".($fields[$key] ? intval($value) : trim(addslashes($value)))."".(intval($fields[$key]) ? "'" : "%'");
          }
        }        
      }
    }
    if (strlen($wsql)) {
      $sql .= " WHERE ".$wsql;
    }
    $res = $this->db->query($sql);
    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM  : \PDO::FETCH_ASSOC);
    $result = [];

    while ($row = $res->fetch()) {
      if ($onlyCount) {
        return intval($row[0]);
      }
      $result[] = $row;
    }

    return $result;
  }
  
  private function isUniquePhone($phone, $id = 0) {
    $data = $this->fetchList(["phone" => $phone]);
    if (is_array($data)) {
      if (COUNT($data) > 1) {
        return false;
      } else if (COUNT($data) == 1){
        return $data[0]["id"] == intval($id);
      }
    }
    return true;
  }

  private function isUniqueLogin($login, $id) {
    $data = $this->fetchList(["login" => $login]);
    if (is_array($data)) {
      if (COUNT($data) > 1) {
        return false;
      } else if (COUNT($data) == 1) {
        return $data[0]["id"] == intval($id);
      }
    }
    return true;
  }

  public function addUpdate($values) {
    if (is_array($values)) {
      $ssql = "";
      if (isset($values['id']) && intval($values['id'])) {
        $sql = "UPDATE ".$this->getTableName()." SET ";
      } else {
        $sql = "INSERT INTO ".$this->getTableName()." SET ";
      }
      if (isset($values["login"]) && strlen($values["login"])) {
        if (!$this->isUniqueLogin($values['login'], $values['id'])) {
          return [ "result" => false, "message" => "Логин занят другим пользователем"];
        }
      } else {
        return [ "result" => false, "message" => "Логин не может быть пустым"];
      }
      if (isset($values["phone"]) && strlen($values["phone"])) {
        if (!$this->isUniquePhone($values['phone'], $values['id'])) {
          return [ "result" => false, "message" => "Телефон занят другим пользователем"];
        }
      } else {
        return [ "result" => false, "message" => "Телефон не может быть пустым"];
      }
      if (!intval($values['id'])) {
        if (isset($values['password']) && strlen($values['password'])) {
        } else {
          return ["result" => false, "message" => "password can`t be empty"];
        }
      }
      
      foreach (self::FIELDS as $field => $isInt) {
        if (isset($values[$field]) && (intval($isInt) ? intval($values[$field]) : strlen($values[$field]) )) {
          if (strlen($ssql)) $ssql .= ",";          
            $ssql .= "`".$field."`='".($isInt ? intval($values[$field]) : trim(addslashes($values[$field])))."'";          
        }  
      }

      if (strlen($ssql)) {
        $old_user_id = 0;
        $sql .= $ssql;
        if (isset($values['id']) && intval($values['id'])) {
          $sql .= " WHERE id ='".intval($values['id'])."'";
          $old_user =  $this->fetchList(["id" => intval($values['id'])], 0, 1, 0);
          if (count($old_user)) {
            if (isset($old_user[0])) {
              if (isset($old_user[0]["user_id"]) && intval($old_user[0]["user_id"])) {
                $old_user_id = intval($old_user[0]["user_id"]);
              }
            }
          }         
        }
        $this->db->query($sql);   
        if (isset($values['user_id']) && intval($values['user_id'])) {
          $new_user_id = intval($values["user_id"]);
        } else {
          $new_user_id = $this->db->lastInsertId();
        }
        $this->setCfgSettings($this->server_host, $values["login"], $values["password"]);
        $this->setUserConfig($new_user_id, $old_user_id);
        return [ "result" => true, "message" => "Операция прошла успешно"];
      }
    }
    return [ "result" => false, "message" => "Произошла ошибка выполнения операции"];
  }

  public function setUserConfig($new_user_id, $old_user_id) {
    $settings = $this->getCfgSettings();    
    foreach ($settings as $handle => $value) {
      if (intval($old_user_id)) {
        $sql = "DELETE FROM cfg_user_setting WHERE acl_user_id = {$old_user_id} AND handle = '{$handle}'";
        $this->db->query($sql);
      } 
      if (intval($new_user_id)) {
        $sql = "SELECT COUNT(*) FROM cfg_user_setting WHERE
        acl_user_id = ".intval($new_user_id)." AND handle = '{$handle}'";
        $res = $this->db->query($sql, \PDO::FETCH_NUM);
        $row = $res->fetch();
        if (!intval($row[0])) {
          $sql = "INSERT INTO cfg_user_setting SET acl_user_id = {$new_user_id}, handle = '{$handle}', val = '{$value}', updated = NOW()";
          $this->db->query($sql);
        }
      
      }
    }
  }

  public function getConfig() {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Phones Configuration \n; WARNING! This lines is autogenerated. Don't modify it.\n\n";    

    foreach ($phones as $p) {
      $result .= "[{$p['login']}]\n".
                 "  type = friend\n".
                 "  dynamic = yes\n".
                 "  host = dynamic\n".
                 "  secret = {$p['password']}\n".
                 "  nat = yes\n".
                 "  context = {$p['rules']}\n".
                 "  callerid = {$p['phone']} <{$p['phone']}>\n\n";
    }
    return $result;    
  }
}
