<?php
  
  /**
   * Class PhoneModel
   */
  class PhoneModel
  {
    const
      TABLE_NAME = "acl_user_phone",
      GROUP_TABLE_NAME = "acl_group_phone",
      GROUP_HAS_TABLE_NAME = "acl_group_has_phone",
      FIELDS = [
      "phone" => "name",
      "model" => "model",
      "login" => "auth",
      "password" => "secret",
      "rules" => "context",
      "default_phone" => "name",
//      "provider" => "provider"
    ];
    private $db;
    private $channel;
    
    /**
     * PhoneModel constructor.
     */
    public function __construct ()
    {
      global $app;
      $container = $app->getContainer();
      $this->db = $container['db'];
      $this->phone = new PBXPhone();
    }
    
    /**
     * @param string $field
     * @param array  $options
     */
    public function getFieldValue ($field, $options)
    {
      foreach ($options as $key => $value) {
        if ($key == $field) {
          return $value;
        }
      }
      return NULL;
    }
    
    public function compareChannels (&$channels)
    {
      $templates = [];
      foreach ($channels as $chanel1) {
        $template = [];
        foreach ($chanel1["options"] as $optionKey1 => &$optionValue1) {
          foreach ($channels as &$chanel2) {
            foreach ($chanel2["options"] as $optionKey2 => &$optionValue2) {
              if ($optionKey1 == $optionKey2 && $optionValue1 == $optionValue2) {
                if ($optionKey1 != "secret" && $optionKey1 != "context") {
                  $template["name"] = PBXConfigHelper::generatePatternName($chanel1['patterns'][0] ? $chanel1['patterns'][0] : $chanel1['name'] . "_tpl");
                  $template["code"] = $chanel1['patterns'][0] ? $chanel1['patterns'][0] : $chanel1['name'] . "_tpl";
                  $template["options"][$optionKey1] = $optionValue1;
                  unset($chanel2["options"][$optionKey2]);
                  $chanel2["provider"] = $template["code"];
                }
              }
            }
          }
        }
        
        $templates[] = $template;
      }
      return $templates;
    }

    public function updateItem($item, $isNew = false)
    {
      if ($isNew) {
        $sql = "INSERT INTO ".SELF::TABLE_NAME;
      } else {
        $sql = "UPDATE ".SELF::TABLE_NAME;
        $endSql = " WHERE id = ".intval($item["id"]);
      }

      $sql .= " SET ";
      $sql .= strlen($item["phone"]) ? "phone='".$item["phone"]."'," : "";
      $sql .= strlen($item["default_phone"]) ? "default_phone='".$item["default_phone"]."'," : "";
      $sql .= strlen($item["fullname"]) ? "fullname='".$item["fullname"]."'," : "";
      $sql .= strlen($item["rules"]) ? "rules='".$item["rules"]."'" : "";

      $sql = trim($sql);

      if (substr($sql, strlen($sql)-1, strlen($sql)) == ",") {
        $sql = (substr($sql, 0, strlen($sql)-1));
      }

      if (isset($endSql)) {
        $sql .= $endSql;        
      }
      $res = $this->db->query($sql);
      return $res;
    }

    public static function getValuesFromGroups($name, $groups) {
      foreach ($groups as $group) {
        if ($group["code"] == $name ) {
          return $group;
        }
      }
      return [];
    }
  
    /**
     * @param $provider
     *
     * @return int
     */
    public function getPhoneGroup($provider)
    {
      $sql = "SELECT id FROM ".self::GROUP_TABLE_NAME." WHERE code = '{$provider}' LIMIT 1";
      $res = $this->db->query($sql);
      $row = $res->fetch();
      if (intval($row['id'])) {
        return intval($row['id']);
      }
      return 0;
    }
    
    public function setPhoneGroupValues($phone, $groups)
    {
      $phone["pattern"] = "";
      if (isset($phone["patterns"])) {
        if (isset($phone["patterns"][0])) {
          $phone["pattern"] = $phone["patterns"][0];
        }
      }
      if (isset($phone["provider"])) {
        $group = $this->getValuesFromGroups($phone["provider"], $groups);
        if ($group) {
          $sql = "DELETE FROM ".self::GROUP_TABLE_NAME." WHERE `code` = '".$group["code"]."'";
          $this->db->query($sql);
          
          $sql = "INSERT INTO ".self::GROUP_TABLE_NAME." SET
          name='".trim(addslashes($group["name"]))."',
          code='".trim(addslashes($group["code"]))."',
          pattern='".trim(addslashes($phone["pattern"]))."',
          rules='".trim(addslashes($phone["options"]["context"]?$phone["options"]["context"]:""))."'
          ";
          $this->db->query($sql);
          return $this->db->lastInsertId();
        }
      }
      return 0;
    }
    /**
     * @param array $array
     */
    public function setPhoneValues ($array, $groupId = 0)
    {
      $values = [];
      if (array_key_exists("name", $array)) {
        $array["options"]["name"] = $array["name"];
      }
      if (array_key_exists("provider", $array)) {
        $array["options"]["provider"] = $array["provider"];
      }
      
      if (array_key_exists("options", $array)) {
        foreach (self::FIELDS as $dbField => $sipOption) {
          $value = $this->getFieldValue($sipOption, $array["options"]);
          
          if (isset($value)) {
            $values[$dbField] = $value;
          }
        }
      }
//      if (!isset($values["code"])) {
//        $values["code"] = $this->channel->getCode($values["name"]);
//      }
      return $this->insert($values, $groupId);
    }
    
    /**
     * @param array $values
     *
     * @return bool
     */
    public function insert ($values, $groupId = 0)
    {
      $this->delete($values["phone"]);
      $sql = "INSERT INTO";
      $valuesSql = "";
      foreach (self::FIELDS as $dbField => $sipOption) {
        if ($values[$dbField]) {
          if (strlen($valuesSql))
            $valuesSql .= ",";
          $valuesSql .= "`{$dbField}` = '" . trim(addslashes($values[$dbField])) . "'";
        }
      }
      if (strlen($valuesSql)) {
        $sql .= " " . self::TABLE_NAME . " SET " . $valuesSql;
      } else {
        return 0;
      }
      $userId = $this->getUserByPhone($values['phone']);
      if ($userId) {        
        $sql .= ",`user_id`=".$userId;
      }

      try {
        $res = $this->db->query($sql);
        $id = intval($this->db->lastInsertId());
        if ($groupId) {
          $sql = "insert into ".self::GROUP_HAS_TABLE_NAME." set group_id = '".intval($groupId)."', phone_id = '".$id ."'";
          $this->db->query($sql);
        }
      } catch (Throwable $exception) {
        Throw new Exception($exception->getMessage()."SQL = $sql");
      }
      return $id;
    }

    public function getUserByPhone($phone)
    {
      $sql = "SELECT acl_user_id FROM cfg_user_setting WHERE handle='cti.ext' AND val='".$phone."'";
      $res = $this->db->query($sql);
      $row = $res->fetch();
      if ($row) {
        if ($row['acl_user_id']) {
          return $row['acl_user_id'];
        }
      }
      return null;
    }

    public function deleteById ($id)
    {
      $sql = "DELETE FROM " . SELF::TABLE_NAME . " WHERE `id`='" . $id . "'";
      $this->db->query($sql);
    }

    public function delete ($name)
    {
        $sql = "SELECT id FROM " . SELF::TABLE_NAME . " WHERE `phone`='" . $name . "' ";
        $res = $this->db->query($sql);
        while ($row = $res->fetch()) {
          if ($row["id"]) {
            $sql = "DELETE FROM ".self::GROUP_HAS_TABLE_NAME." WHERE phone_id = '".$row["id"]."'";
            $this->db->query($sql);
          }
        }
      
      $sql = "DELETE FROM " . SELF::TABLE_NAME . " WHERE `phone`='" . $name . "'";
      $this->db->query($sql);
    }

    public function getPhone($id)
    {
      if (intval($id)) {
        $sql = "SELECT id, login, phone, model, mac, rules, default_phone FROM ".SELF::TABLE_NAME." WHERE id = {$id}";
        $res = $this->db->query($sql); 
        $row = $res->fetch();
        $row["type"] = "phone";
        return $row;
      }
      return false;
    }
  }