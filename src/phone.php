<?php

use App\ExportImport;
use App\Journal\PBXJournal;
use Erpico\User;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\SIPPeersAction;

class PBXPhone
{
  protected $db;

  protected $ami;
  private $cfgSettings;
  private $journal;
  const FIELDS = [
    "phone" => 0,
    "model" => 0,
    "mac" => 0,
    "user_id" => 1,
    "login" => 0,
    "password" => 0,
    "rules" => 0,
    "default_phone" => 1,
    "channel_driver" => 0,
    "remote_config_phone_addresses" => 0,
    "active" => 0,
    "deleted" => 0
  ],
    GROUPS_FIELDS = [
    "name" => 0,
    "code" => 0,
    "pattern" => 0,
    "rules" => 0,
    "outgoing_phone" => 0,
    "remote_config_phone_addresses" => 0
  ],
    ERPICO_MODEL = 'erpico';

  public function __construct()
  {
    global $app;
    global $user;
    $container = $app->getContainer();
    $this->db = $container['db'];
    $this->ami = $container['ami'];
    $this->server_host = $container['server_host'];
    $this->logger = $container['logger'];
    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();
    if ($user) $this->journal = new PBXJournal($user->getId() || 0);
  }

  private function getTableName()
  {
    return "acl_user_phone";
  }

  private function getGroupsTableName()
  {
    return "acl_group_phone";
  }

  private function setCfgSettings($server, $login, $password, $number, $enabled = 0)
  {
    $this->cfgSettings = [
      "sipphone.integrated" => $enabled,
      "sipphone.server" => $server,
      "sipphone.user" => $login,
      "sipphone.password" => $password,
      "cti.ext" => $number
    ];
  }

  public function getCfgSettings()
  {
    return $this->cfgSettings;
  }

  public function fetchList($filter = "", $start = 0, $end = 20, $onlyCount = 0, $likeStringValues = true, $sort = "")
  {
    $sql = "SELECT ";
    if (intval($onlyCount)) {
      $ssql = " COUNT(*) ";
    } else {
      $ssql = self::getTableName() . ".`id`";
      foreach (self::FIELDS as $field => $isInt) {
        if (strlen($ssql)) $ssql .= ",";
        $ssql .= self::getTableName() . ".`" . $field . "`";
      }
    }

    $sql .= $ssql . ", acl_user.fullname AS user_name, 
                     acl_group_phone.pattern AS group_pattern,
                     acl_group_phone.rules AS group_rules,
                     acl_group_phone.id AS group_id,
                     SP.val as 'sipphone.server'
                     FROM " . self::getTableName();
    $sql .= " LEFT JOIN acl_user ON (acl_user.id = acl_user_phone.user_id) ";
    $sql .= " LEFT JOIN acl_group_has_phone ON (acl_group_has_phone.phone_id = acl_user_phone.id) ";
    $sql .= " LEFT JOIN acl_group_phone ON (acl_group_has_phone.group_id = acl_group_phone.id) ";
    $sql .= " LEFT JOIN cfg_user_setting AS SP ON (SP.acl_user_id = acl_user.id AND SP.handle = 'sipphone.server')";
    $wsql = "";
    if (is_array($filter)) {
      $fields = self::FIELDS;
      $fields["id"] = 1;
      $wsql = "";
      foreach ($filter as $key => $value) {
        if (isset($fields[$key])) {
          if (array_key_exists($key, $fields) && (intval($fields[$key]) ? intval($value) : strlen($value))) {
            if (strlen($wsql)) $wsql .= " AND ";
            $wsql .= self::getTableName() . ".`" . $key . "` " . (intval($fields[$key]) ? "='" : ($likeStringValues ? "LIKE '%" : "='")) . "" . ($fields[$key] ? intval($value) : trim(addslashes($value))) . "" . (intval($fields[$key]) ? "'" : ($likeStringValues ? "%'" : "'"));
          }
        }
      }
    }

    if (strlen($wsql)) {
      $sql .= " WHERE " . $wsql;
    }

    if (is_array($sort)) {
      $osql = "";
      foreach (self::FIELDS as $field => $isInt) {
        if ($sort[$field]) {
          $sqlField = self::getTableName() . ".`$field`";
          $osql .= ($isInt ? $sqlField : $sqlField . " + 0 ") . $sort[$field];
        }
      }
      $sql .= empty($osql) ? "" : " ORDER BY " . $osql;
    } else {
      $sql .= " ORDER BY " . self::getTableName() . ".`phone` + 0";
    }

    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM : \PDO::FETCH_ASSOC);
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

  private function isUniqueColumn($column, $code, $id)
  {
    if (in_array($column, SELF::FIELDS)) {
      $data = $this->fetchList([$column => $code], 0, 3, 0, 0);
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
    } else {
      throw new Exception("Undefined column " . $column . " given", 1);
    }

  }

  public function addUpdate($values)
  {
    if (is_array($values)) {
      $ssql = "";
      if (isset($values['id']) && intval($values['id'])) {
        $id = intval($values['id']);
        $sql = "UPDATE " . $this->getTableName() . " SET ";
      } else {
        $sql = "INSERT INTO " . $this->getTableName() . " SET ";
      }
      // TO DO create phone service with validate and insert data
      if (isset($values["login"]) && strlen($values["login"])) {
        if (!$this->isUniqueColumn("login", $values['login'], $values['id'])) {
          return ["result" => false, "message" => "Логин занят другим пользователем"];
        }
      } else {
        return ["result" => false, "message" => "Логин не может быть пустым"];
      }
      if (isset($values["phone"]) && strlen($values["phone"])) {
        if (!$this->isUniqueColumn("phone", $values['phone'], $values['id'])) {
          return ["result" => false, "message" => "Телефон занят другим пользователем"];
        }
      } else {
        return ["result" => false, "message" => "Телефон не может быть пустым"];
      }

      if (isset($values['mac'])) {
        $mac = preg_replace("/[^0123456789ABCDEF]/", '', strtoupper($values['mac']));

        if (strlen($mac) == 12) {
          // Add dashes
          $nmac = "";
          for ($i = 0; $i < strlen($mac); $i++) {
            $nmac .= $mac[$i];
            if ($i % 2) $nmac .= ":";
          }
          $mac = trim($nmac, ":");
        }

        $values['mac'] = $mac;
      }
      if ($values['model'] === self::ERPICO_MODEL && $values['user_id']) {
        $phonesWithThisUsers = $this->fetchList(["user_id" => intval($values['user_id'])], 0, 2, 0);
        foreach ($phonesWithThisUsers as $phone) {
          if ($phone['id'] != $values['id']) {
            return ["result" => false, "message" => "У данного пользователя уже есть телефон с такой же моделью"];
          }
        }
      }
      foreach (self::FIELDS as $field => $isInt) {
        if ((isset($values[$field]) && (intval($isInt) ? intval($values[$field]) : strlen($values[$field]))) || !strlen($values[$field])) {
          if (strlen($ssql)) $ssql .= ",";
          if ($field == 'password' && trim($values['password']) == "") {
            $ssql .= "`password`=LEFT (sha1(md5(concat(md5(md5(RAND())), ';Ej>]sjkip'))), 16) ";
            continue;
          };
          $ssql .= "`" . $field . "`=" . (strlen($values[$field]) ? "'" . ($isInt ? intval($values[$field]) . "'" : trim(addslashes($values[$field])) . "'") : 'NULL') . "";
        }
      }

      if (strlen($ssql)) {
        $old_user_id = 0;
        $sql .= $ssql;
        if (isset($values['id']) && intval($values['id'])) {
          $sql .= " WHERE id ='" . intval($values['id']) . "'";
          $old_user = $this->fetchList(["id" => intval($values['id'])], 0, 1, 0);
          if (count($old_user)) {
            if (isset($old_user[0])) {
              if (isset($old_user[0]["user_id"]) && intval($old_user[0]["user_id"])) {
                $old_user_id = intval($old_user[0]["user_id"]);
              }
            }
          }
        }

        if ($values['id']) {
          $this->journal->log(PBXJournal::MODIFY_PHONE,
            ["phone" => $values['id'], "changes" => $this->getPhoneChanges($values)]
          );
        }

        $this->db->query($sql);
        if (isset($values['user_id']) && intval($values['user_id'])) {
          $new_user_id = intval($values["user_id"]);
        }
        if (!isset($id)) {
          $id = $this->db->lastInsertId();
          $this->journal->log(PBXJournal::CREATE_PHONE,
            ["phone" => $id, "data" => $values]
          );
        }
        if (isset($values["group_id"]) && intval($values["group_id"])) {
          $this->deletePhoneFromGroup($id);
          $this->setPhoneToGroup(intval($values["group_id"]), $id);
        } else {
          $this->deletePhoneFromGroup($id);
        }
        if (isset($values['sipphone_server'])) {
          $this->server_host = $values['sipphone_server'];
        }
        $this->setCfgSettings($this->server_host, $values["login"], $values["password"], $values["phone"], $values["model"] == "erpico" ? 1 : 0);
        if (isset($new_user_id)) $this->setUserConfig($new_user_id, $old_user_id);
        return ["result" => true, "message" => "Операция прошла успешно"];
      }
    }
    return ["result" => false, "message" => "Произошла ошибка выполнения операции"];
  }

  public function setPhoneUser(array $values)
  {
    try {
      $settings = new PBXSettings();
      $server = $settings->getSettingByHandle('sipphone.server')['val'];
      if ($this->db->query("UPDATE " . self::getTableName() . " SET user_id =" . $values['user_id'] . ", model = 'erpico' WHERE id = " . $values['id'])) {
        $this->setCfgSettings($server, $values["login"], $values["password"], $values["phone"], $values["model"] == "erpico" ? 1 : 0);
        $this->setUserConfig($values['user_id'], $values['user_id']);
        return ["result" => true, "message" => "Телефон успешно привязался"];
      }
    } catch (Exception $ex) {
      return ["result" => false, "message" => "Произошла ошибка привязки телефона"];
    }
  }

  /**
   * @param $id
   *
   * @return array
   */
  public function remove($id)
  {
    try {
      if (!intval($id)) {
        return ["result" => false, "message" => "# телефона не может быть пустым"];
      }
      $res = $this->deleteOtherPhonesFromGroup(intval($id)) &&
        $this->db->query("DELETE FROM " . self::getTableName() . " WHERE id = " . intval($id));
      if ($res) {
        $this->journal->log(PBXJournal::DELETE_PHONE, ['phone' => $id]);
        return ["result" => true, "message" => "Удаление прошло успешно"];
      }
    } catch (Exception $ex) {
      $this->logger->error($ex->getMessage() . " ON LINE " . $ex->getLine());
      return ["result" => false, "message" => "Произошла ошибка удаления"];
    }
  }

  /**
   * @param $id
   *
   * @return array
   */
  public function removePhoneFroup($id)
  {
    try {
      if (!intval($id)) {
        return ["result" => false, "message" => "# группы не может быть пустым"];
      }
      if ($this->deleteOtherPhonesFromGroup(intval($id)) && $this->db->query("DELETE FROM " . self::getGroupsTableName()
          . " WHERE id = " . intval($id))) {
        return ["result" => true, "message" => "Удаление прошло успешно"];
      }
    } catch (Exception $ex) {
      $this->logger->error($ex->getMessage() . " ON LINE " . $ex->getLine());
      return ["result" => false, "message" => "Произошла ошибка удаления"];
    }
  }

  public function setUserConfig($new_user_id, $old_user_id)
  {
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

  public function fetchGroupsList($filter = "", $start = 0, $end = 20, $onlyCount = 0, $likeStringValues = true)
  {
    $sql = "SELECT ";
    if (intval($onlyCount)) {
      $ssql = " COUNT(*) ";
    } else {
      $ssql = "`id`";
      foreach (self::GROUPS_FIELDS as $field => $isInt) {
        if (strlen($ssql)) $ssql .= ",";
        $ssql .= "`" . $field . "`";
      }
    }

    $sql .= $ssql . " FROM " . self::getGroupsTableName();
    $wsql = "";
    if (is_array($filter)) {
      $fields = self::GROUPS_FIELDS;
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
    $res = $this->db->query($sql);
    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM : \PDO::FETCH_ASSOC);
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

  public function addUpdatePhoneGroup($values)
  {
    if (is_array($values)) {
      $ssql = "";
      if (isset($values['id']) && intval($values['id'])) {
        $id = intval($values["id"]);
        $sql = "UPDATE " . $this->getGroupsTableName() . " SET ";
      } else {
        $sql = "INSERT INTO " . $this->getGroupsTableName() . " SET ";
      }
      if (isset($values["code"]) && strlen($values["code"])) {
        if (!$this->isUniqueGroupColumn("code", $values['code'], $values['id'])) {
          return ["result" => false, "message" => "Код занят другой группой"];
        }
      } else {
        return ["result" => false, "message" => "Код не может быть пустым"];
      }
      if (isset($values["name"]) && strlen($values["name"])) {
        if (!$this->isUniqueGroupColumn("name", $values['name'], $values['id'])) {
          return ["result" => false, "message" => "Название занято другой группой"];
        }
      } else {
        return ["result" => false, "message" => "Название не может быть пустым"];
      }

      foreach (self::GROUPS_FIELDS as $field => $isInt) {
        if (isset($values[$field]) && (intval($isInt) ? intval($values[$field]) : strlen($values[$field]))) {
          if (strlen($ssql)) $ssql .= ",";
          $ssql .= "`" . $field . "`='" . ($isInt ? intval($values[$field]) : trim(addslashes($values[$field]))) . "'";
        }
      }

      if (strlen($ssql)) {
        $sql .= $ssql;
        if (isset($id) && intval($id)) {
          $sql .= " WHERE id = " . intval($id);
        }
        if ($this->db->query($sql)) {
          if (!isset($id)) {
            $id = $this->db->lastInsertId();
          }
          if (isset($values["phones"]) && strlen($values["phones"])) {
            $groupPhones = $this->getGroupPhones($id)["id"];
            $newPhones = explode(",", $values["phones"]);
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
          return ["result" => true, "message" => "Операция прошла успешно"];
        }
      }
    }
    return ["result" => false, "message" => "Произошла ошибка выполнения операции"];
  }

  private function isUniqueGroupColumn($column, $code, $id)
  {
    if (in_array($column, SELF::GROUPS_FIELDS)) {
      $data = $this->fetchGroupsList([$column => $code], 0, 3, 0, 0);
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
    } else {
      throw new Exception("Undefined column " . $column . " given", 1);
    }

  }

  private function deletePhoneFromGroup($phone_id)
  {
    $sql = "DELETE FROM acl_group_has_phone WHERE phone_id = " . intval($phone_id);
    $res = $this->db->query($sql);
    if ($res) {
      return true;
    }
    return false;
  }

  private function deleteOtherPhonesFromGroup($group_id, $phones = [])
  {
    if (is_array($phones)) {
      $sql = "DELETE FROM acl_group_has_phone WHERE group_id = " . intval($group_id);
      if (COUNT($phones)) {
        $sql .= " AND phone_id NOT IN (" . implode(",", $phones) . ")";
      }

      $res = $this->db->query($sql);
      if ($res) {
        return true;
      }
    }
    return false;
  }

  private function getGroupPhones($group_id)
  {
    if (!intval($group_id)) return [];
    $sql = "SELECT acl_group_has_phone.phone_id, acl_user_phone.phone FROM  acl_group_has_phone
    LEFT JOIN acl_user_phone ON (acl_user_phone.id = acl_group_has_phone.phone_id)
    WHERE group_id = " . intval($group_id);
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $ids = [];
    $phones = [];
    while ($row = $res->fetch()) {
      $ids[] = $row[0];
      $phones[] = $row[1];
    }
    return ["id" => $ids, "phone" => $phones];
  }

  private function getPhoneGroup($phone_id)
  {
    if (!intval($phone_id)) return [];
    $sql = "SELECT group_id FROM  acl_group_has_phone WHERE phone_id = " . intval($phone_id) . " LIMIT 1";
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $row = $res->fetch();
    if (is_array($row) && isset($row[0]) && intval($row[0])) {
      return intval($row[0]);
    }

    return 0;
  }

  private function setPhoneToGroup($group_id, $phone_id)
  {
    $sql = "INSERT INTO acl_group_has_phone SET group_id = " . intval($group_id) . ",
    phone_id = " . intval($phone_id);
    $res = $this->db->query($sql);
    if ($res) {
      return true;
    }
    return false;
  }

  private function setRemoteConfigPhoneAddresses(&$p) {
    if (isset($p['group_id']) && $p['group_id'] != 0) {
      $g = $this->fetchGroupsList(['id' => $p['group_id']]);
      $g = $g[0];
      if (strlen($g['remote_config_phone_addresses'])) {
        $g['remote_config_phone_addresses'] = explode(",", $g['remote_config_phone_addresses']);
        $p['remote_config_phone_addresses'] = explode(",", $p['remote_config_phone_addresses']);

        $p['remote_config_phone_addresses'] = array_unique(array_merge($g['remote_config_phone_addresses'], $p['remote_config_phone_addresses']));
      }
    }

    if (gettype($p['remote_config_phone_addresses']) === 'string') {
      $p['remote_config_phone_addresses'] = explode(",", $p['remote_config_phone_addresses']);
    }

    return isset($p['remote_config_phone_addresses']) && count($p['remote_config_phone_addresses']) > 0;
  }

  public function getConfig()
  {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Phones Configuration \n; WARNING! This lines is autogenerated. Don't modify it.\n\n";

    $nat = strpos(exec("asterisk -V"), "18") ? "force_rport,comedia" : "yes";

    foreach ($phones as $p) {
      $remote_config_phone_addresses = $this->setRemoteConfigPhoneAddresses($p);

      if ($p['deleted'] || !$p['active'] || $p['channel_driver'] === 'chan_pjsip') {
        continue;
      }

      if (!strlen($p['rules']) && strlen($p['group_rules'])) {
        $p['rules'] = $p['group_rules'];
      }
      $result .= "[{$p['login']}]" . (strlen($p['group_pattern']) ? "(" . $p['group_pattern'] . ")" : "") . "\n" .
        "  type = friend\n" .
        "  dynamic = yes\n" .
        "  host = dynamic\n" .
        "  secret = {$p['password']}\n" .
        "  nat = $nat\n" .
        (strlen($p['rules']) ? "  context = {$p['rules']}\n" : "") .
        (strlen($p['group_id']) ? "  callgroup = {$p['group_id']}\n  pickupgroup = {$p['group_id']}\n" : "") .
        "  callerid = {$p['user_name']} <{$p['phone']}>\n" .
        ($remote_config_phone_addresses ? "  deny = 0.0.0.0/0.0.0.0\n" : "") .
        ($remote_config_phone_addresses ? "  contactdeny = 0.0.0.0/0.0.0.0\n" : "");

      if ($remote_config_phone_addresses) {
        foreach ($p['remote_config_phone_addresses'] as $address) {
          if ($address !== '') {
            $result .= "  permit = " . $address . "\n";
            $result .= "  contactpermit = " . $address . "\n";
          }
        }
      }

      $result .= "\n";
    }
    return $result;
  }

  public function getPjsipConfig()
  {
    $phones = $this->fetchList(0, 0, 1000000, 0);

    $result = "; ErpicoPBX Phones Configuration \n; WARNING! This lines is autogenerated. Don't modify it.\n\n";

    foreach ($phones as $p) {
      $remote_config_phone_addresses = $this->setRemoteConfigPhoneAddresses($p);

      if ($p['deleted'] || !$p['active'] || $p['channel_driver'] === 'chan_sip') {
        continue;
      }

      $result .= "[{$p['login']}]\n" .
        "  type=aor\n" .
        "  max_contacts=5\n" .
        "  remove_existing=yes\n" .
        "[{$p['login']}]\n" .
        "  type=auth\n" .
        "  auth_type=userpass\n" .
        "  username={$p['login']}\n" .
        "  password={$p['password']}\n" .
        "[{$p['login']}](webrtc_endpoint)\n" .
        "  aors={$p['login']}\n" .
        "  auth={$p['login']}\n" .
        "  context={$p['rules']}\n" .
        ($remote_config_phone_addresses ? "  acl={$p['login']}\n" : "");

      if ($remote_config_phone_addresses) {
        $result .= "[{$p['login']}]\n" .
        "  type=acl\n" .
        "  deny = 0.0.0.0/0.0.0.0\n" .
        "  contact_deny = 0.0.0.0/0.0.0.0\n";

        foreach ($p['remote_config_phone_addresses'] as $address) {
          if ($address !== '') {
            $result .= "  permit = " . $address . "\n";
            $result .= "  contact_permit = " . $address . "\n";
          }
        }
      }

      $result .= "\n";
    }
    return $result;
  }

  public function getGroupCode($name)
  {
    $translator = new Erpico\Translator($name);
    $value = $translator->translate();
    $test_result = $this->isUniqueGroupColumn("code", $value, 0);
    $i = 0;
    while (!is_bool($test_result) || !$test_result) {
      $safeValue = $value;
      $safeValue .= ++$i;
      $test_result = $this->isUniqueGroupColumn("code", $safeValue, 0);

    }
    return $value .= $i ? $i : "";
  }

  public function getPhoneIdByName($name)
  {
    $sql = "SELECT id from acl_user_phone WHERE phone = '{$name}'";
    $result = $this->db->query($sql);

    return $result->fetchColumn();
  }

  public function export()
  {

    $result = [];

    // phones
    foreach ($this->fetchList(null, 0, null, 0) as $item) {

      //$item["group_code"] = $this->getGroupCodeById($item['group_id']);
      unset($item['id']);
      unset($item['user_id']);
      unset($item['group_pattern']);
      unset($item['group_id']);
      unset($item['group_rules']);

      $result['phones'][] = $item;
    }

    // groups
    foreach ($this->fetchGroupsList(null, 0, null, 0) as $group) {
      unset($group['phones']);
      unset($group['id']);
      $result['phones_groups'][] = $group;
    }

    return $result;
  }

  public function import($data, $delete = false)
  {

    $result = true;
    $exportImport = new ExportImport();

    //acl_user_phone - телефоны
    //acl_group_phone - группы
    //acl_group_has_phone - связь

    if ($delete) {
      $exportImport->truncateTables([
        "acl_user_phone",
        "acl_group_phone",
        "acl_group_has_phone"
      ]);
    }

    $phones_groups = $data->phones_groups;
    $phones = $data->phones;

    // phones
    foreach ($phones as $phone) {
      $phone['user_id'] = intval($this->user->getIdByName($phone['user_name'])) ?: null;
      $exportImport->importAction($phone, [
        "phone",
        "model",
        "mac",
        "login",
        "password",
        "rules",
        "default_phone",
        "active",
        "deleted",
        "user_id"
      ], 'acl_user_phone');
    }

    // groups
    $connectData = [];
    foreach ($phones_groups as $group) {

      $groupId = $exportImport->importAction($group, [
        "name",
        "code",
        "pattern",
        "rules",
        "outgoing_phone"
      ], 'acl_group_phone');

      foreach ($group->phones_names as $phones_name) {
        $connectData[] = [
          "group_id" => $groupId,
          "phone_id" => $this->getPhoneIdByName($phones_name)
        ];
      }
    }

    // connection
    foreach ($connectData as $connect) {
      $exportImport->importAction($connect, ["group_id", "phone_id"], 'acl_group_has_phone');
    }

    return $result;
  }

  public function appendRealtime(&$arr)
  {
    if ($this->ami) {
      try {
        $this->ami->open();
      } catch (Exception $e) {
        return;
      }
      $response = $this->ami->send(new SIPPeersAction());
      $pmap = [];
      $peers = $response->getEvents();
      foreach ($peers as $p) {
        if (get_class($p) != "PAMI\Message\Event\PeerEntryEvent") continue;
        $pmap[$p->getObjectName()] = $p;
      }
      $this->ami->close();

      for ($i = 0; $i < count($arr); $i++) {
        $phone = $arr[$i]['phone'];
        if (isset($pmap[$phone])) {
          $arr[$i]['s_ip'] = $pmap[$phone]->getIPAddress();
          if ($arr[$i]['s_ip'] == '-none-') $arr[$i]['s_ip'] = "";
          $arr[$i]['s_port'] = $pmap[$phone]->getIPPort();
          if ($arr[$i]['s_port'] == '0') $arr[$i]['s_port'] = "";
          $arr[$i]['s_status'] = $pmap[$phone]->getStatus();
        }
      }
    }
  }

  private function getPhoneChanges($newData) {
    $phone = new PBXPhone();
    $oldData = $phone->fetchList(['id' => $newData['id']])[0];

    return $this->journal->getEssenceDiffs($oldData, $newData);
  }
}
