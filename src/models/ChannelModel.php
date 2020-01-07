<?php
  
  /**
   * Class ChannelModel
   */
  class ChannelModel
  {
    const
      TABLE_NAME = "peers", FIELDS = ["provider" => "provider", "login" => "defaultuser", "password" => "remotesecret", "phone" => "name", "rules" => "context", "name" => "name", "fullname" => "name", "host" => "host", "port" => "port"];
    private $db;
    private $channel;
    
    /**
     * ChannelModel constructor.
     */
    public function __construct ()
    {
      global $app;
      $container = $app->getContainer();
      $this->db = $container['db'];
      $this->channel = new PBXChannel();
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
                  $template["name"] = PBXConfigHelper::generatePatternName($chanel1['name']."_tpl");
                  $template["code"] = $chanel1['name']."_tpl";
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
      $sql .= strlen($item["name"]) ? "name='".$item["name"]."'," : "";
      $sql .= strlen($item["provider"]) ? "provider='".$item["provider"]."'," : "";
      $sql .= strlen($item["fullname"]) ? "fullname='".$item["fullname"]."'," : "";
      $sql .= strlen($item["host"]) ? "host='".$item["host"]."'," : "";
      $sql .= strlen($item["port"]) ? "port='".$item["port"]."'," : "";
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

    /**
     * @param array $array
     */
    public function setChannelValues ($array)
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
      if (!isset($values["code"])) {
        $values["code"] = $this->channel->getCode($values["name"]);
      }
      return $this->insert($values);
    }
    
    /**
     * @param array $values
     *
     * @return bool
     */
    public function insert ($values)
    {
//      print_r($values);
//      return;
      $this->delete($values["name"]);
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
      }
      try {
        $res = $this->db->query($sql);
        $id = intval($this->db->lastInsertId());
      } catch (Throwable $exception) {
        Throw new Exception($exception->getMessage()."SQL = $sql");
      }
      return $id;
    }
    public function deleteById ($id)
    {
      $sql = "DELETE FROM " . SELF::TABLE_NAME . " WHERE `id`='" . $id . "'";
      $this->db->query($sql);
    }

    public function delete ($name)
    {
      $sql = "DELETE FROM " . SELF::TABLE_NAME . " WHERE `name`='" . $name . "'";
      $this->db->query($sql);
    }

    public function getChannel($id)
    {
      if (intval($id)) {
        $sql = "SELECT id,`provider`, phone, name, fullname, host, port, rules  FROM ".SELF::TABLE_NAME." WHERE id = {$id}";
        $res = $this->db->query($sql); 
        $row = $res->fetch();
        $row["type"] = "channel";
        return $row;
      }
      return false;
    }    
  }