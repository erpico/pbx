<?php

class PBXOutgoingCampaign  {
  protected $db;
  const FIELDS = [
    "user_id" => 0,
    "tm_created" => 0,
    "name" => 0,
    "description" => 0,
    "state" => 1,
    "is_locked" => 1,
    "date_start" => 0,
    "time_start"=> 0,
    "date_finish"=> 0,
    "time_finish"=> 0,
    "is_pause_on_day_off"=> 1,
    "is_restart_on_day_start" => 1,
    "answer_timeout"=> 1,
    "call_tries"=> 1,
    "action"=> 1,
    "action_value" => 0,
    "min_call_time"=> 1,
    "concurrent_calls_limit" => 1,
    "max_day_calls_limit" => 0,
    "calls_multiplier" => 0,
    "dial_context" => 0,
    "lead_filters" => 0,
    "lead_status_enabled" => 0,
    "lead_status" => 0,
    "lead_status_user" => 0,
    "lead_status_tries_end" => 0
  ];

  const WEEK_DAYS = [
    "", "Вс", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб"//, "Рабочие дни", "Выходные", "Праздники"
  ];

  const SETTING_FIELDS = [
    "result", "pause", "pause_time", "stop", "postpone_for", "postpone_to", "lead_status_result", "webhook", "lead_status_user_rules"
  ];

  const SETTING_VALUES = [
     "По умолчанию","Успех", "Занят","Не отвечает", "Не верный номер" ,"Не доступен" 
  ];

  public function __construct() {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];

    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();
  }

  private function getTableName() {
    return "outgouing_company ";
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
    $sql .= " WHERE deleted = 0 OR deleted is null";
    if (strlen($wsql)) {
      $sql.= " AND ".$wsql;
    }
    $sql .= " order by id";

    $res = $this->db->query($sql, $onlyCount ? \PDO::FETCH_NUM  : \PDO::FETCH_ASSOC);
    $result = [];

    while ($row = $res->fetch()) {
      if ($onlyCount) {
        return intval($row[0]);
      }
      $row['days'] = $this->getDays($row['id']);
      $row['settings'] = $this->getSettings($row['id']);
      $result[] = $row;
    }

    return $result;
  }

  private function getDays($id) {
    $sql = " SELECT weekday_id FROM outgouing_company_weekdays WHERE outgouing_company_id = {$id}";
    $res = $this->db->query($sql, \PDO::FETCH_NUM);
    $days = [];
    while ($row = $res->fetch()) {
      $days[$row[0]] = self::WEEK_DAYS[$row[0]];
    }
    return $days;
  }

  public function getSettings($id, $needTime = 0) {
    $sql = "SELECT ".implode(",",self::SETTING_FIELDS)." FROM outgoing_campaign_dial_result_setting WHERE campaign_id = {$id}
      ORDER BY `campaign_id`, `result`";
    $res = $this->db->query($sql);
    $result = [];
    while ($row = $res->fetch()) {
      if (isset($row['postpone_for'])) {
        $row['postpone_for_radio'] = 1;
      }
      if (isset($row['postpone_to'])) {
        $row['postpone_to_date'] = $row['postpone_to'];
        $row['postpone_to'] = 1;        
      }
      $row['value'] = self::SETTING_VALUES[$row['result']];
      $row['id'] = $row['result'];
      $result[] = $row;
    }
    if ($needTime) {
      $sql = "SELECT min_call_time, concurrent_calls_limit, max_day_calls_limit, calls_multiplier FROM outgouing_company WHERE id={$id}";
      $res = $this->db->query($sql);
      while ($row = $res->fetch()) {
        $result['min_call_time'] = $row['min_call_time'];
        $result['concurrent_calls_limit'] = $row['concurrent_calls_limit'];
        $result['max_day_calls_limit'] = $row['max_day_calls_limit'];
        $result['calls_multiplier'] = $row['calls_multiplier'];
      }
    }
      return $result;
  }

  public function getContactById($id) {
    $sql = "SELECT id, updated, outgouing_company_id, `order`, phone, name, description, state, tries, last_call, dial_result, scheduled_time FROM outgouing_company_contacts WHERE id = '".intval($id)."'";
    $res = $this->db->query($sql);
    $row = $res->fetch();

    return $row;
  }

  public function getContactsResults($id) {
    $sql = "SELECT id, outgouing_company_id, `order`, phone, name, description,
    state,tries,last_call,dial_result, UNIX_TIMESTAMP(scheduled_time) FROM outgouing_company_contacts WHERE outgouing_company_id = {$id} AND `state` IN (3,4,6,7)";
    $res = $this->db->query($sql);
    $result = [];
    while ($row = $res->fetch()) {
      $result[] = $row;
    }
    return $result;
  }

  public function getContactCalls($id) {
    $sql = "SELECT id, asteriskid, userId, type, state, time, 
    tm_created, tm_rbt, tm_bridged, tm_done, caller, called, 
    rbt_duration, duration, fn_mixmonitor, hangup_cause, hangup_code, q850_code, 
    q850_reason, sip_call_id, qos, dialstatus FROM calls 
    JOIN outgouing_company_contacts_calls ON calls.id=outgouing_company_contacts_calls.call_id WHERE outgouing_company_contacts_calls.contact_id = {$id}";
    $res = $this->db->query($sql);
    $result = [];
    while ($row = $res->fetch()) {
      $row['status_text'] = $this->getSipStatusText($row['hangup_code']);
      $result[] = $row;
    }
    return $result;
  }

  public function setState($id, $state) {
    if (!intval($id) || !intval($state)) {
      return ["result" => false, "message" => "Ошибка получения данных"];
    }
    $sql = " UPDATE outgouing_company SET state = {$state} WHERE id = {$id}";
    if ($this->db->query($sql)) {
      return ["result" => true, "message" => "Операция прошла успешно"];
    }
    return ["result" => false, "message" => "Ошибка получения данных"];
  }
  
  public function getContacts($id) {
    $sql = "SELECT id, outgouing_company_id, `order`, phone, name, description,
    state,tries,last_call,dial_result, UNIX_TIMESTAMP(scheduled_time) FROM outgouing_company_contacts WHERE outgouing_company_id = {$id} 
    AND state NOT IN (3,4,6,7) ORDER BY id";
    $res = $this->db->query($sql);
    $result = [];
    while ($row = $res->fetch()) {
      $result[] = $row;
    }
    return $result;
  }

    public function getContactByPhone($phone, $outgoing_company_id) {
        $sql = "SELECT id, outgouing_company_id, `order`, phone, name, description,
    state,tries,last_call,dial_result, UNIX_TIMESTAMP(scheduled_time) 
    FROM outgouing_company_contacts 
    WHERE phone = '{$phone}' 
    AND outgouing_company_id = {$outgoing_company_id}";
        $res = $this->db->query($sql);
        return $res->fetch();
    }

  private function getSipStatusText($code) {
    $status_list[100] = "100 - Запрос обрабатывается"; // Trying

    $status_list[180] = "180 - Местоположение вызываемого пользователя определено."
    ." Выдан сигнал о входящем вызове";  // Ringing

    $status_list[181] = "181 - Прокси,сервер переадресует вызов к другому пользователю";  // Call Is Being Forwarded
    $status_list[182] = "182 - вызываемый абонент временно не доступен, вызов поставлен в очередь";  // Queued

    $status_list[183] = "183 - Используется для того, чтобы заранее получить описание"
    ." сеанса информационного обмена от шлюзов на пути к вызываемому пользователю";  // Session Progress

  

    $status_list[200] = "200 - Успешное завершение";  // OK
    $status_list[202] = "202 - Запрос принят для обработки Используется для справки о состоянии обработки";  // Accepted
    $status_list[300] = "300 - Указывает несколько SIP-адресов, по которым можно найти вызываемого пользователя";  // Multiple Choices
    $status_list[301] = "301 - Вызываемый пользователь больше не находится по адресу, указанному в запросе";  // Moved Permanently
    $status_list[302] = "302 - Пользователь временно сменил местоположение";  // Moved Temporarily
    $status_list[305] = "305 - Вызываемый пользователь не доступен непосредственно, входящий вызов должен пройти через прокси-сервер";  // Use Proxy
    $status_list[380] = "380 - Запрошенная услуга недоступна, но доступны альтернативные услуги";  // Alternative Service

  
  
    $status_list[400] = "400 - Запрос не понят из-за синтаксических ошибок в нем, ошибка"
    ." в сигнализации, скорее всего что-то с настройками оборудования";  // Bad Request

    $status_list[401] = "401 - Нормальный ответ сервера о том, что пользователь еще не"
    ." авторизировался; обычно после этого абонентское оборудование отправляет"
    ." на сервер новый запрос, содержащий логин и пароль";  // Unauthorized

    $status_list[402] = "402 - Требуется оплата (зарезервирован для использования в будущем)";  // Payment Required
    $status_list[403] = "403 - Абонент не зарегистрирован";  // Forbidden
    $status_list[404] = "404 - Вызываемый абонент не найден, нет такого SIP-номера";  // Not Found
  
    $status_list[405] = "405 - Метод не поддерживается, может возникать если пользователь"
    ." пытается отправлять голосовую почту и т.п.";  // Method Not Allowed
  
    $status_list[406] = "406 - Пользователь не доступен";  // Not Acceptable
    $status_list[407] = "407 - Необходима аутентификация на прокси-сервере";  // Proxy Authentication Required
    $status_list[408] = "408 - Время обработки запроса истекло";  // Request Timeout
    $status_list[410] = "410 - Gone";  // Gone
    $status_list[413] = "413 - Размер запроса слишком велик для обработки на сервере";  // Request Entity Too Large
    $status_list[414] = "414 - Request URI Too Long";  // Request URI Too Long
    $status_list[415] = "415 - Звонок совершается неподдерживаемым кодеком";  // Unsupported Media Type
  
    $status_list[416] = "416 - Сервер не может обработать запрос из-за того, что схема"
    ." адреса получателя ему непонятна";  // Unsupported URI Scheme
  
    $status_list[420] = "420 - Неизвестное расширение: Сервер не понял расширение протокола SIP";  // Bad Extension
  
    $status_list[421] = "421 - В заголовке запроса не указано, какое расширение сервер"
    ." должен применить для его обработки";  // Extension Required
  
    $status_list[422] = "422 - Session Timer Too Small";  // Session Timer Too Small
    $status_list[423] = "423 - Сервер отклоняет запрос, так как время действия ресурса короткое";  // Interval Too Brief
    $status_list[480] = "480 - Временно недоступное направление попробуйте позвонить позже";  // Temporarily Unavailable
    $status_list[481] = "481 - Действие не выполнено, нормальный ответ при поступлении дублирующего пакета";  // Call/Transaction Does Not Exist
    $status_list[482] = "482 - Обнаружен замкнутый маршрут передачи запроса";  // Loop Detected
    $status_list[483] = "483 - Запрос на своем пути прошел через большее число прокси-серверов, чем разрешено";  // Too Many Hops
    $status_list[484] = "484 - Принят запрос с неполным адресом";  // Address Incompleted
    $status_list[485] = "485 - Адрес вызываемого пользователя не однозначен";  // Ambiguous
    $status_list[486] = "486 - Абонент занят";  // Busy Here
    $status_list[487] = "487 - Запрос отменен, обычно приходит при отмене вызова";  // Request Terminated
    $status_list[488] = "488 - Not Acceptable Here";  // Not Acceptable Here
    $status_list[489] = "489 - Bad Event";  // Bad Event
    $status_list[490] = "490 - Запрос обновлен";  // Request Updated
  
    $status_list[491] = "491 - Запрос поступил в то время, когда сервер еще не закончил"
    ." обработку другого запроса, относящегося к тому же диалогу";  // Request Pending
  
    $status_list[493] = "493 - Сервер не в состоянии подобрать ключ дешифрования:"
    ." невозможно декодировать тело S/MIME сообщения";  // Undecipherable
  
    $status_list[494] = "494 - Security Agreement Required";  // Security Agreement Required
  


    $status_list[500] = "500 - Внутренняя ошибка сервера";  // Internal Server Error
  
    $status_list[501] = "501 - В сервере не реализованы какие-либо функции, необходимые"
    ." для обслуживания запроса: Метод запроса SIP не поддерживается";  // Not Implemented
  
    $status_list[502] = "502 - Сервер, функционирующий в качестве шлюза или прокси-сервера"
    .", принимает некорректный ответ от сервера, к которому он направил запрос";  // Bad Gateway
  
    $status_list[503] = "503 - Сервер не может в данный момент обслужить вызов вследствие"
    ." перегрузки или проведения технического обслуживания";  // Service Unavailable
  
    $status_list[504] = "504 - Сервер не получил ответа в течение установленного промежутка"
    ." времени от сервера, к которому он обратился для завершения вызова";  // Server Timeout
  
    $status_list[505] = "505 - Версия не поддерживается: Сервер не поддерживает эту версию протокола SIP";  // Version Not Supported
    $status_list[513] = "513 - Сервер не в состоянии обработать запрос из-за большой длины сообщения";  // Message Too Large
    $status_list[580] = "580 - Precondition Failure";  // Precondition Failure

    $status_list[600] = "600 - Вызываемый пользователь занят и не желает принимать вызов в данный момент";  // Busy Everywhere
    $status_list[603] = "603 - Вызываемый пользователь не желает принимать входящие вызовы, не указывая причину отказа";  // Decline
    $status_list[604] = "604 - Вызываемого пользователя не существует";  // Does Not Exist Anywhere
  
    $status_list[606] = "606 - Соединение с сервером было установлено, но отдельные параметры"
    .", такие как тип запрашиваемой информации, полоса пропускания, вид адресации не доступны";  // Not Acceptable



    $status_list[701] = "701 - No response from destination server";  // No response from destination server
    $status_list[702] = "702 - Unable to resolve destination server";  // Unable to resolve destination server
    $status_list[703] = "703 - Error sending message to destination server";  // Error sending message to destination server
    if ($code == 0) {
      return "Нормальное завершение";
    }
    if (array_key_exists($code, $status_list)) {
      return $status_list[$code];
    }
    return "Неизвестный код завершения";
  }

  private function savePhone($id, $campaning_id, $phone, $name, $description, $state) {
      $contact = $this->getContactById($id);
      if (!$contact) {
        $sql = " INSERT INTO outgouing_company_contacts SET ";
      } else {
        $sql = " UPDATE outgouing_company_contacts SET ";
        $sql_end = " WHERE id = '" . intval($id) . "'";
      }
      $sql .= "`phone` = '" . trim(addslashes($phone)) . "',
      `name` = '" . trim(addslashes($name)) . "',
      `description` = '" . trim(addslashes($description)) . "',
      `updated` = NOW(),
      `outgouing_company_id` = '" . intval($campaning_id) . "',
      `state` = '" . intval($state) . "'
      ";
      if (isset($sql_end)) $sql .= $sql_end;

      $this->db->query($sql);
  }

  public function addUpdate($values) {
    $errors = [];    
    if (isset($values['id']) && intval($values['id'])) {
      $sql = "UPDATE outgouing_company SET ";
    } else {
      $sql = "INSERT INTO outgouing_company SET ";
    }
    $ssql = "";
    if (!isset($values['user_id'])) {
      $values['user_id'] = 0;
    }
    foreach (self::FIELDS as $field => $isInt) {
      if (isset($values[$field]) && (intval($isInt) ? intval($values[$field]) : strlen($values[$field]) )) {
        if (strlen($ssql)) $ssql .= ",";
        $ssql .= "`".$field."`='".($isInt ? intval($values[$field]) : trim(addslashes($values[$field])))."'";          
      }  else {
          if (($field == "lead_status" || $field == "lead_status_user" || $field == "lead_status_tries_end") && isset($values[$field]) && $values[$field] == "") {
              if (strlen($ssql)) $ssql .= ",";
              $ssql .= "`".$field."`=null";
              continue;
          }
      }
    }
    if (!isset($values['id']) || $values['id'] == 0) {
      $ssql .= ", `tm_created` = NOW() ";
    } else {
      $ssql .= ", `updated` = NOW() ";
    }
    $sql .= $ssql;
    if (isset($values['id']) && intval($values['id'])) {
      $sql .= " WHERE id = '".$values['id']."'";
    }
    if ($this->db->query($sql)) {
      if (isset($values['id']) && intval($values['id'])) {
        $id = intval($values['id']);
      } else {
        $id = $this->db->lastInsertId();
      }
      if (isset($values['phones']) && $values['phones'] !== "[]") {
        $phones = json_decode($values['phones']);
        foreach ($phones as $phone) {
          if ($phone->id && (!$phone->phone || $phone == "")) {
            $this->deleteContact($phone->id);
            continue;
          }
          $res = false;
          if ($phone->phone) $res = $this->getContactByPhone($phone->phone, $id);
          if ($res && $res['id'] != $phone->id) continue;
          $resPhone = $this->savePhone($phone->id, $id, $phone->phone, $phone->name, $phone->description, $phone->state);
          if (isset($resPhone)) {
            $errors[] = $resPhone;
          }
        }
      } else {
        $this->truncateQueues($id);
      }
      
      if (isset($values['days'])) {
        $this->updateWeekDays($id, $values['days']);
      }
      if (!isset($values['settings']) || $values['settings'] == "[]") {
          $this->bindDefaultSettings($id);
      }
      return [ "result" => true, "message" => "Операция прошла успешно", "errors"=>$errors];
    }
    return [ "result" => false, "message" => "Ошибка выполнения операции", "errors" => $errors];    
  }

  public function updateSettings($id, $settings, $concurrent_calls_limit = 1, $min_call_time = 1, $max_day_calls_limit = 0, $calls_multiplier = 1) {
      // if (is_string($settings) && strlen($settings)) {
      $js = json_decode($settings);
      $this->deleteAllSettings($id);
      foreach ($js as $result) {
        $ssql = "";
        foreach (self::SETTING_FIELDS as $field) {
          if (isset($result->$field)) {
            if (strlen($ssql)) $ssql .= ",";          
            $ssql .= "`".$field."`='".trim(addslashes($result->$field))."'";          
          }  
        }
        if (strlen($ssql)) {
          $ssql .= ", `campaign_id` = {$id}";
          $sql = " INSERT INTO outgoing_campaign_dial_result_setting SET ".$ssql;
          $this->db->query($sql);
        }
      }
      if ($min_call_time == 0) $min_call_time = 1;
      if ($concurrent_calls_limit == 0) $concurrent_calls_limit = 1;
      if ($calls_multiplier < 1) $calls_multiplier = 1;
      $sql = "UPDATE outgouing_company SET 
      `min_call_time`= $min_call_time,
      `concurrent_calls_limit`= $concurrent_calls_limit,
      `max_day_calls_limit`= $max_day_calls_limit,
      `calls_multiplier`= $calls_multiplier
       WHERE id = $id";
      $this->db->query($sql);

      return true;
    // }
    // return false;
  }

  public function copy(int $id, ?int $queues = 0): int
  {
      $sql = "SELECT * FROM outgouing_company WHERE id = $id";
      $res = $this->db->query($sql);
      $row = $res->fetch();

      $sql = "INSERT INTO outgouing_company SET ";
      foreach ($row as $k => $v) {
          if ($k === 'id' || $v == null) continue;
          if ( in_array($k, ['updated', 'tm_created']) ) {
              $sql .= "`$k` = NOW(), ";
              continue;
          }
          $sql .= "`$k` = '$v', ";
      }
      $sql = rtrim($sql, ", ");
      $res = $this->db->query($sql);
      $newCampaignId = $this->db->lastInsertId();

      if ($res) {
          $sql = "SELECT * FROM outgoing_campaign_dial_result_setting WHERE campaign_id = $id";
          $res = $this->db->query($sql);
          while ($row = $res->fetch()) {
              $sql = "INSERT INTO outgoing_campaign_dial_result_setting SET ";
              foreach ($row as $k => $v) {
                  if ($k === 'campaign_id') {
                      $sql .= "`$k` = '$newCampaignId', ";
                      continue;
                  }
                  if ($v == null) continue;
                  $sql .= "`$k` = '$v', ";
              }
              $sql = rtrim($sql, ", ");
              $this->db->query($sql);
          }

          $sql = "SELECT * FROM outgouing_company_weekdays WHERE outgouing_company_id = $id";
          $res = $this->db->query($sql);
          while ($row = $res->fetch()) {
              $sql = "INSERT INTO outgouing_company_weekdays SET ";
              foreach ($row as $k => $v) {
                  if ($k === 'outgouing_company_id') {
                      $sql .= "`$k` = '$newCampaignId', ";
                      continue;
                  }
                  if ($v == null) continue;
                  $sql .= "`$k` = '$v', ";
              }
              $sql = rtrim($sql, ", ");
              $this->db->query($sql);
          }

          if ($queues) {
              $sql = "SELECT * FROM outgouing_company_contacts 
                      WHERE outgouing_company_id = $id 
                      AND state NOT IN (3,4,6,7) 
                      ORDER BY id";
              $res = $this->db->query($sql);
              while ($row = $res->fetch()) {
                  $sql = "INSERT INTO outgouing_company_contacts SET ";
                  foreach ($row as $k => $v) {
                      if ($k === 'outgouing_company_id') {
                          $sql .= "`$k` = '$newCampaignId', ";
                          continue;
                      }
                      if ($v == null) continue;
                      $sql .= "`$k` = '$v', ";
                  }
                  $sql = rtrim($sql, ", ");
                  $this->db->query($sql);
              }
          }
      }

      return $newCampaignId;
  }

  public function remove($id) {
    $result = $this->db->query("UPDATE outgouing_company SET deleted = 1 WHERE id = {$id}");
    return $result ? 1 : 0;
  }

  public function truncateQueues($id) {
      $result = $this->db->query("DELETE FROM outgouing_company_contacts WHERE state NOT IN (3,4,6,7) AND `outgouing_company_id` = $id");
      return $result ? 1 : 0;
  }

  private function bindDefaultSettings($id) {
    $settings = $this->getSettings(1);
    foreach ($settings as $setting) {
      $ssql = "";
      foreach (self::SETTING_FIELDS as $field) {
        if (isset($setting[$field])) {
          if (strlen($ssql)) $ssql .= ",";          
          $ssql .= "`".$field."`='".trim(addslashes($setting[$field]))."'";          
        }  
      }
      if (strlen($ssql)) {
        $ssql .= ", `campaign_id` = {$id}";
        $this->db->query(" INSERT INTO outgoing_campaign_dial_result_setting SET ".$ssql);
        
      }
    }
  }

  private function deleteAllSettings($id) {
    $sql = "DELETE FROM outgoing_campaign_dial_result_setting WHERE campaign_id = '{$id}'";
    $this->db->query($sql);
    return true;    
  }

  private function updateWeekDays($id, $days_str) {
    $days_arr = explode(",", $days_str);
    if (is_array($days_arr) && COUNT($days_arr)) {
      $this->deleteAllWeekDays($id);
      foreach ($days_arr as $key => $value) {
        if (intval($value)) {
          ++$key;
          $sql = "INSERT INTO outgouing_company_weekdays SET
          `outgouing_company_id` = '{$id}', `weekday_id` = '{$value}'";
          $this->db->query($sql);
        }      
      }
    }    
  }
  
  private function deleteAllWeekDays($id) {
    $sql = "DELETE FROM outgouing_company_weekdays WHERE outgouing_company_id = '{$id}'";
    $this->db->query($sql);
    return true;    
  }

  private function deleteContact(int $id)
  {
    $sql = "DELETE FROM outgouing_company_contacts WHERE id = '{$id}'";
    $this->db->query($sql);
  }
}
