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
  ],
  GROUPS_FIELDS = [
    "name" => 0,
    "code" => 0,
    "pattern" => 0,
    "rules" => 0,
    "outgoing_phone" => 0
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
  
  private function getGroupsTableName() {
    return "acl_group_phone";
  }

  private function setCfgSettings($server, $login, $password, $number, $enabled = 0) {
    $this->cfgSettings = [
    "sipphone.integrated" => $enabled,
    "sipphone.server" => $server,
    "sipphone.user" => $login,
    "sipphone.password" => $password,
    "cti.ext"           => $number
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
      $row["group_id"] = $this->getPhoneGroup($row["id"]);
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
        $id = intval($values['id']);
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
        if (!isset($id)) {
          $id = $this->db->lastInsertId();
        }
        if (isset($values["group_id"]) && intval($values["group_id"])) {
          $this->deletePhoneFromGroup($id);
          $this->setPhoneToGroup(intval($values["group_id"]), $id);
        } else {
          $this->deletePhoneFromGroup($id);
        } 

        $this->setCfgSettings($this->server_host, $values["login"], $values["password"], $values["phone"], $values["model"] == "erpico" ? 1 : 0);        
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
        /*$sql = "SELECT COUNT(*) FROM cfg_user_setting WHERE
        acl_user_id = ".intval($new_user_id)." AND handle = '{$handle}'";
        $res = $this->db->query($sql, \PDO::FETCH_NUM);
        $row = $res->fetch();
        if (!intval($row[0])) {*/
          $sql = "REPLACE INTO cfg_user_setting SET acl_user_id = {$new_user_id}, handle = '{$handle}', val = '{$value}', updated = NOW()";
          $this->db->query($sql);
        //}      
      }
    }
  }

  public function fetchGroupsList($filter = "", $start = 0, $end = 20, $onlyCount = 0) {
    $sql = "SELECT ";
    if (intval($onlyCount)) {
      $ssql = " COUNT(*) ";  
    } else {
      $ssql = "`id`";
      foreach (self::GROUPS_FIELDS as $field => $isInt) {        
        if (strlen($ssql)) $ssql .= ",";
        $ssql .= "`".$field."`";        
      }
    }

    $sql .= $ssql." FROM ".self::getGroupsTableName();
    $wsql = "";
    if (is_array($filter)) {
      $fields = self::GROUPS_FIELDS;
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
      $row["phones"] = $this->getGroupPhones($row["id"])["id"];
      $row["phones_names"] = $this->getGroupPhones($row["id"])["phone"];
      
      $result[] = $row;
    }

    return $result;
  }

  public function addUpdatePhoneGroup($values) {
    if (is_array($values)) {
      $ssql = "";
      if (isset($values['id']) && intval($values['id'])) {
        $id = intval($values["id"]);
        $sql = "UPDATE ".$this->getGroupsTableName()." SET ";
      } else {
        $sql = "INSERT INTO ".$this->getGroupsTableName()." SET ";
      }
      if (isset($values["name"]) && strlen($values["name"])) {
        if (!$this->isUniqueGroupName($values['name'], $values['id'])) {
          return [ "result" => false, "message" => "Название занято другой группой"];
        }
      } else {
        return [ "result" => false, "message" => "Название не может быть пустым"];
      }
      
      foreach (self::GROUPS_FIELDS as $field => $isInt) {
        if (isset($values[$field]) && (intval($isInt) ? intval($values[$field]) : strlen($values[$field]) )) {
          if (strlen($ssql)) $ssql .= ",";          
            $ssql .= "`".$field."`='".($isInt ? intval($values[$field]) : trim(addslashes($values[$field])))."'";          
        }  
      }

      if (strlen($ssql)) {
        $sql .= $ssql;
        if (isset($id) && intval($id)) {
          $sql .= " WHERE id = ".intval($id);
        }
        if ($this->db->query($sql)) {
          if (!isset($id)) {
            $id = $this->db->lastInsertId();
          }
          if (isset($values["phones"]) && strlen($values["phones"])) {
            $groupPhones = $this->getGroupPhones($id)["id"];
            $newPhones = explode(",",$values["phones"]);
            $checkedPhones = [];
            if (count($newPhones)) {
              foreach ($newPhones as $phone) {
                if (intval($phone)) {
                  if (!in_array(intval($phone), $groupPhones)) {
                    $this->setPhoneToGroup($id, intval($phone));                    
                  }
                  $checkedPhones[] = intval($phone);
                }
              }
              if (count($checkedPhones)) {
                $this->deleteOtherPhonesFromGroup($id, $checkedPhones);
              }
            } else {
              $this->deleteOtherPhonesFromGroup($id);
            }
          } else {
            $this->deleteOtherPhonesFromGroup($id);
          }  
          return [ "result" => true, "message" => "Операция прошла успешно"];
        }
      }
    }
    return [ "result" => false, "message" => "Произошла ошибка выполнения операции"];
  }

  private function isUniqueGroupName($name, $id) {
    $data = $this->fetchGroupsList(["name" => $name]);
    if (is_array($data)) {
      if (COUNT($data) > 1) {
        if (!intval($id)) {
          return COUNT($data);
        }        
        return false;
      } else {
        if (!COUNT($data)) {
          return true;
        }
        if (intval($id)) {
          if (isset($data[0]) && isset($data[0]["id"]) && intval($data[0]["id"])) {
            return $data[0]["id"] == intval($id);
          }
          return true;
        } else {
          if (COUNT($data)) {
            return COUNT($data);
            var_dump($data);
          } else {
            return true;
          }
        }
      }
    }
    return true;
  }

  private function deletePhoneFromGroup($phone_id) {
    $sql = "DELETE FROM acl_group_has_phone WHERE phone_id = ".intval($phone_id);
    $res = $this->db->query($sql);
    if ($res) {
      return true;
    }
    return false;      
  }

  private function deleteOtherPhonesFromGroup($group_id, $phones = []) {
    if (is_array($phones)) {
      $sql = "DELETE FROM acl_group_has_phone WHERE group_id = ".intval($group_id);
      if (COUNT($phones)) {
        $sql .= " AND phone_id NOT IN (".implode(",", $phones).")";
      }
       
      $res = $this->db->query($sql);
      if ($res) {
        return true;
      }
    }
    return false;
  }

  private function getGroupPhones($group_id) {
    if (!intval($group_id)) return [];
    $sql = "SELECT acl_group_has_phone.phone_id, acl_user_phone.phone FROM  acl_group_has_phone
    LEFT JOIN acl_user_phone ON (acl_user_phone.id = acl_group_has_phone.phone_id)
    WHERE group_id = ".intval($group_id);
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $ids = [];
    $phones = [];
    while ($row = $res->fetch()) {
      $ids[] = $row[0];
      $phones[] = $row[1];
    }
    return ["id" => $ids, "phone" => $phones];
  }

  private function getPhoneGroup($phone_id) {
    if (!intval($phone_id)) return [];
    $sql = "SELECT group_id FROM  acl_group_has_phone WHERE phone_id = ".intval($phone_id)." LIMIT 1";
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $row = $res->fetch();
    if (is_array($row) && isset($row[0]) && intval($row[0])) {
      return intval($row[0]);
    }

    return 0;
  }

  private function setPhoneToGroup($group_id, $phone_id) {
    $sql = "INSERT INTO acl_group_has_phone SET group_id = ".intval($group_id).",
    phone_id = ".intval($phone_id);
    $res = $this->db->query($sql);
    if ($res) {
      return true;
    }
    return false;
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

  public function getPjsipConfig() {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Phones Configuration \n; WARNING! This lines is autogenerated. Don't modify it.\n\n";    

    foreach ($phones as $p) {
      $result .= "[{$p['login']}]\n".
                 "  type=aor\n".
                 "  max_contacts=5\n".
                 "  remove_existing=yes\n".
                 "[{$p['login']}]\n".
                 "  type=auth\n".
                 "  auth_type=userpass\n".
                 "  username={$p['login']}\n".
                 "  password={$p['password']}\n".
                 "[{$p['login']}](webrtc_endpoint)\n".
                 "  aors={$p['login']}\n".
                 "  auth={$p['login']}\n".
                 "  context={$p['rules']}\n\n";
    }
    return $result;    
  }

  public function getGroupCode($name) {
    $translator = new Erpico\Translator($name);
    $value = $translator->translate();
    $test_result = $this->isUniqueGroupName($value, 0);
    
    while (!is_bool($test_result)  || !$test_result) {
      if (is_numeric($test_result)) {
        $value .= $test_result;
      }
      $test_result = $this->isUniqueGroupName($value, 0);      
    }    
    return $value;
  }
}
