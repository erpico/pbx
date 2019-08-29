<?php

namespace Erpico;

class Ext_scripts {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }


  public function getExt_scripts_list($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    // Here need to add permission checkers and filters

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM scripts");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $sql = "SELECT id, name AS value, name FROM scripts WHERE deleted IS NULL ORDER BY name ASC";

    $arr = [];
    $res = $this->db->query($sql);
    while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
      if (!$row['id']) continue;
// added permission to delete empty 'values'
      if ($row['name']== "") continue;
      $arr[] = $row;
    }

  return $arr;
  }


  public function scripts_save($params) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    // Here need to add permission checkers and filters

    $id = intval($params['id']);
    $name = $params['name'];

    if ($id) {
      $sql = "UPDATE ";
    } else {
      $sql = "INSERT INTO ";
    }

    $sql .= " scripts SET ";

    if (isset($params['delete']) && $id) {
      $sql .= "deleted = 1";
    } else {
      $sql .= "name = '".addslashes($name)."' ";
    }

    if ($id) {
      $sql .= " WHERE id = '$id' LIMIT 1";
    }

    $res = $this->db->query($sql);
    if (!$res) {
      return [ "result" => 0, "message" => "Error" ];
    }

    if (!$id) {
      $id = $this->db->lastInsertId(); //PDO::lastInsertId();
    }

    return [ "result" => 1, "message" => "OK", "id" => $id ];
  }


  public function scripts_save_stage($params) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $id = intval($params['id']);
    $script_id = intval($params['script_id']);
    $parent_id = intval($params['parent_id']);
    $name = $params['name'];
    $show_name = intval($params['show_name']);
    $show_back_next = intval($params['show_back_next']);
    $autobuttons = intval($params['autobuttons']);

    if ($id) {
      $sql = "UPDATE ";
    } else {
      $sql = "INSERT INTO ";
    }

    $sql .= "scripts_stages SET ";

    if ($id && isset($params['delete'])) {
      $sql .= " deleted = 1";
    } else {
      $sql .= " name = '".addslashes($name)."', 
      show_name = '$show_name',
      show_prevnext = '$show_back_next',
      autobuttons = '$autobuttons',
      script_id = '$script_id', parent_id = '$parent_id' ";
    }

    if ($id) {
      $sql .= " WHERE id = '$id' LIMIT 1";
    }

    $res = $this->db->query($sql);
    if (!$res) {
      return [ "result" => 0, "message" => "Error" ];
    }

    if (!$id) {
      $id = $this->db->lastInsertId(); //PDO::lastInsertId();
    }

    return [ "result" => 1, "message" => "OK", "id" => $id ];
  }


  public function getScriptStages($script_id, $parent_id) {
    $sql = "SELECT id, name AS value, name, show_name, show_prevnext AS show_back_next, parent_id, autobuttons FROM scripts_stages WHERE script_id = '$script_id' AND parent_id = '$parent_id' AND deleted IS NULL ORDER BY name ASC";

    $arr = [];
    $res = $this->db->query($sql);
    while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
      if (!$row['id']) continue;
      $cs = $this->getScriptStages($script_id, $row['id']);

      if (count($cs)) {
        $row['data'] = $cs;
      }

      $arr[] = $row;
    }

    return $arr;
  }

  public function getScriptAllStages($script_id) {
    $sql = "SELECT id, name AS value, name, show_name, show_prevnext AS show_back_next, parent_id, autobuttons FROM scripts_stages WHERE script_id = '$script_id' AND parent_id = '0' AND deleted IS NULL ORDER BY name ASC";

    $arr = [];
    $res = $this->db->query($sql);
    while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
      if (!$row['id']) continue;

      $cs = $this->getScriptStages($script_id, $row['id']);

      if (count($cs)) {
          $row['data'] = $cs;
      }

      $arr[] = $row;
    }

    return $arr;
  }


  public function getExt_scripts_list_stages($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    // Here need to add permission checkers and filters

    $script_id = intval($filter['list_stages']);
    $parent_id = intval($filter['parent_id']);

    $cl = $this->getScriptStages($script_id, $parent_id);

    return $cl;
  }


  public function scripts_save_stage_elements($params) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $id = intval($params['id']);
    $stage_id = intval($params['stage_id']);

    $fields = [ 'type',
      'label',
      'text',
      'url',
      'form',
      'form_to',
      'action',
      'action_script',
      'action_block',
      'action_text',
      'action_transfer' ];

    if ($id) {
      $sql = "UPDATE ";
    } else {
      $sql = "INSERT INTO ";
    }

    $sql .= " scripts_stages_elements SET ";

    if ($id && isset($params['delete'])) {
      $sql .= "deleted = 1";
    } else {
      $sql .= "script_stage_id = '$stage_id' ";
      foreach ($fields as $f) {
          if (isset($params[$f])) {
              $v = addslashes(trim($params[$f]));
              $sql .= ", $f = '$v'";
          }
      }
    }

    if ($id) {
      $sql .= " WHERE id = '$id' LIMIT 1";
    }

    $res = $this->db->query($sql);
    if (!$res) {
      return [ "result" => 0, "message" => "Error" ];
    }

    if (!$id) {
    $id = $this->db->lastInsertId(); //PDO::lastInsertId();
    }

    return [ "result" => 1, "message" => "OK", "id" => $id ];
  }


  public function scripts_list_stage_elements($filter) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $stage_id = intval($filter['list_stage_elements']);
    $sql = "SELECT * FROM scripts_stages_elements WHERE script_stage_id = '$stage_id' AND deleted IS NULL";

    $arr = [];
    $res = $this->db->query($sql);
    while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
      if (!$row['id']) continue;
      $arr[] = $row;
    }

    return $arr;
  }

}