<?php

namespace App\Journal;

use PDO;
use App\helpers\QueryBuilder;
use Erpico\User;

class PBXJournal {

  protected $db;

  const
    CREATE_USER = 'CREATE_USER',
    CREATE_PHONE = 'CREATE_PHONE',
    CREATE_QUEUE = 'CREATE_QUEUE',
    CREATE_CHANNEL = 'CREATE_CHANNEL',
    MODIFY_USER = 'MODIFY_USER',
    MODIFY_PHONE = 'MODIFY_PHONE',
    MODIFY_QUEUE = 'MODIFY_QUEUE',
    MODIFY_CHANNEL = 'MODIFY_CHANNEL',
    DELETE_USER = 'DELETE_USER',
    DELETE_PHONE = 'DELETE_PHONE',
    DELETE_QUEUE = 'DELETE_QUEUE',
    DELETE_CHANNEL = 'DELETE_CHANNEL'
  ;

  private $actions = [
    PBXJournal::CREATE_USER => 'Создание пользователя',
    PBXJournal::CREATE_PHONE => 'Создание телефона',
    PBXJournal::CREATE_QUEUE => 'Создание очереди',
    PBXJournal::CREATE_CHANNEL => 'Создание канала',
    PBXJournal::MODIFY_USER => 'Изменение пользователя',
    PBXJournal::MODIFY_PHONE => 'Изменение телефона',
    PBXJournal::MODIFY_QUEUE => 'Изменение очереди',
    PBXJournal::MODIFY_CHANNEL => 'Изменение канала',
    PBXJournal::DELETE_USER => 'Удаление пользователя',
    PBXJournal::DELETE_PHONE => 'Удаление телефона',
    PBXJournal::DELETE_QUEUE => 'Удаление очереди',
    PBXJournal::DELETE_CHANNEL => 'Удаление канала'
  ];

  private $user_id = 0;

  public function __construct($user_id = 0) {
    global $app;
    $container = $app->getContainer();
    $this->db = $container['db'];
    $this->diffs = [];
    $this->user_id = $user_id;
  }

  public function getActions() {
    return $this->actions;
  }

  public function getActionsHandler($action = 0) {
    $refl = new ReflectionClass('PSJournal');
    $constants = $refl->getConstants();
    if ($action) {
      return array_search($action, $constants);
    }
    return $constants;
  }

  private function isModify($action) {
    $handler = $this->getActionsHandler($action);
    if (strpos($handler, "MODIFY")) {
      return true;
    }
    return false;
  }

  public function getArrayDiffs($old, $new, $key, &$diffs, $is_obj = 0) {
    if ($is_obj) {
      if (is_array($old[$key])){
        foreach($old[$key] as $second_key => $second_value) {
          if (array_key_exists($second_key,$new->$key)) {
            $this->getArrayDiffs($old[$key], $new->$key, $second_key, $diffs, is_object($new->$key));
          }
        }
      } else {
        if ($old[$key] != $new->$key ) {
          $diffs[] = ["key" =>$key,"before"=>$old[$key], "after"=>$new->$key];
        }
      }
    } else {
      if (is_array($old[$key])){
        foreach($old[$key] as $second_key => $second_value) {
          if (is_array($new[$key])) {
            if (array_key_exists($second_key,$new[$key])) {
              $this->getArrayDiffs($old[$key], $new[$key], $second_key, $diffs, is_object($new->$key));
            }
          }
        }
      } else if (is_object($old)) {
        foreach($old->$key as $second_key => $second_value) {
          if (isset($second_key,$new->$key)) {
            $this->getArrayDiffs($old->$key, $new->$key, $second_key, $diffs, 1);
          }
        }
      } else {
        if ($old[$key] != $new[$key] ) {
          $diffs[] = ["key" =>$key,"before"=>$old[$key], "after"=>$new[$key]];
        }
      }
    }

  }

  public function getEssenceDiffs($essence, $data) {
    $diffs = [];

    foreach($essence as $key => $value) {

      if (isset($data[$key])) {
        $this->getArrayDiffs($essence, $data, $key, $diffs, is_object($data));
      }
    }
    return $diffs;
  }

  public function log($action, $data) {
    $sql = 'INSERT INTO journal SET user_id = :user_id, time = Now(), action = :action, data = :data';

    $sth = $this->db->prepare($sql, [ PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
    return $sth->execute([
      ':user_id' => $this->user_id,
      ':action'  => $action,
      ':data'    => json_encode($data)
    ]);
  }

  public function getList($filter = "", $start = 0, $end = 20, $onlyCount = 0) {
    $query = (new QueryBuilder());

    $query->select(
      intval($onlyCount)
        ? 'COUNT(*)'
        : 'J.id, J.user_id as admin_id, J.time, J.action, J.data
       ')->from('journal', 'J')
      ->where('1', '=', '1')
      ->order("J.id DESC");

    if (is_array($filter)) {

      // дата
      if (isset($filter['time']) && strlen($filter['time'])) {
        $date = json_decode($filter['time']);
        if (is_object($date) && isset($date->start) && !isset($date->end)) {
          $query->where('J.time', '>=', $date->start);
          $query->where('J.time', '<=', date("Y-m-d 23:59:59", strtotime($date->start)));
        } else if (is_object($date) && isset($date->start) && isset($date->end)) {
          $query->where('J.time', '>=', $date->start);
          $query->where('J.time', '<=', date("Y-m-d 23:59:59", strtotime($date->end)));
        }
      }

      // действие
      if (isset($filter['action']) && strlen($filter['action'])) {
        $query->whereInFromArray('J.action', explode(',', $filter['action']), 'string');
      }

      // админ
      if (
        isset($filter['admin_name'])
        && strlen($filter['admin_name'])
        && $admin = $this->fieldExcelFilter($filter['admin_name'])
      ) {
        $query = $this->setWhereSqlWhenExcelFilter($query, $admin, 'acl_user');
      }
    }

    $query->limit((int)$start, (int)$end);
    $sth = $this->db->prepare($query,
      [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
    );
    foreach ($query->getBinds() as $pos => $value) {
      $sth->bindValue($pos + 1, $value);
    }
    $sth->execute();

    if ($onlyCount) {
      return $sth->fetchColumn();
    } else {
      $rows = [];
      $actions = $this->getActions();
      while($row = $sth->fetch(PDO::FETCH_ASSOC)){

        $row['action_name'] = $actions[$row['action']];

        if (intval($row['admin_id'])) {
          $user = new User($this->db, intval($row['admin_id']));
          $row['admin_name'] = $user->getInfoForTable();
        }
        $row['jdata'] = json_decode($row['data']);
        $row['client_id'] = preg_replace('#\D#', '', $row['client_id']);
//        $row['client_id1'] = preg_replace('#\D#', '', $row['client_id1']);
//        $row['client_id2'] = preg_replace('#\D#', '', $row['client_id2']);
//        $row['client3'] = preg_replace('#\D#', '', $row['client3']);

//        if ($row['client_id']) {
//
//        } else if ($row['client_id1']) {
//          $row['client_id'] = $row['client_id1'];
//        } else if ($row['client_id2']) {
//          $row['client_id'] = $row['client_id2'];
//        } else if ($row['client3']) {
//          $row['client_id'] = $row['client3'];
//        }
//
//        if (intval($row['client_id'])) {
//          $user = new User(intval($row['client_id']));
//          $row['client_id'] = intval($row['client_id']);
//          $row['client_name'] = ''; // $user->getInfoForTable();
//        }

        $rows[] = $row;
      }
    }

    return $rows;
  }

  public function fieldExcelFilter($fieldValue) {
    $fieldValue = "{\"condition\":{\"filter\":\"{$fieldValue}\",\"type\":\"contains\"},\"includes\":null}";
    $ans = array_values(json_decode($fieldValue, true));
    $ans = $ans[0]; // filter, condition

    return isset($ans['filter']) && isset($ans['type']) ? $ans : null;
  }

  public function setWhereSqlWhenExcelFilter(QueryBuilder $query, array $field, string $tableName) {

    if (!empty($field['filter'])) {

      $props = $this->getSqlProperties($field);

      if ($tableName === "acl_user") {
        $query = $this->setQueryForClient(
          $query,
          $props['value'],
          $props['sqlOperation'],
          $props['boolOperation'],
          $tableName
        );
      }
    }

    return $query;
  }

  public function setQueryForClient(
    QueryBuilder $query,
    string $value,
    string $sqlOperation,
    string $boolOperation,
//    string $boolOperation = "OR",
//    string $tableName = 'clients'
    string $tableName
  ) {

    $query->whereAltogether(
      [$tableName.'.name', $tableName.'.fullname'], // то, с чем сравниваем
      $sqlOperation, // операция
      [$value, $value], // значения
      'string', // всегда передается строка
      [$boolOperation] // операция между значениями
    )->join(
      'LEFT',
      'acl_user',
      $tableName, // это алиас
      $tableName === 'clients' // подразумевается, что есть 2 колонки - клиент и админ
        ? 'clients.id = data->"$.client_id" '
        : 'acl_user.id = J.user_id');

    return $query;
  }

  private function getSqlProperties(array $field) {

    $value = $field['filter'];
    $sqlOperation = "=";
    $boolOperation = "OR";

    if (!empty($field) && !empty($field['type']) && !empty($field['filter'])) {
      switch ($field['type']) {
        case "beginsWith":
          $value .= "%";
          $sqlOperation = "LIKE";
          break;
        case "notBeginsWith":
          $value .= "%";
          $sqlOperation = "NOT LIKE";
          $boolOperation = "AND";
          break;
        case "endsWith":
          $value = "%" . $value;
          $sqlOperation = "LIKE";
          break;
        case "notEndsWith":
          $value = "%" . $value;
          $sqlOperation = "NOT LIKE";
          $boolOperation = "AND";
          break;
        case "contains":
          $sqlOperation = "LIKE";
          break;
        case "notContains":
          $sqlOperation = "NOT LIKE";
          break;
        case "equal":
          break;
        case "notEqual":
          $sqlOperation = "<>";
          break;
      }
    }

    return [
      "value" => $value,
      "sqlOperation" => $sqlOperation,
      "boolOperation" => $boolOperation
    ];
  }
}
