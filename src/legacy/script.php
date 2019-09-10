<?php
namespace Erpico;

class Script {

  CONST ANSWER_FIELDS = [ "nps_id", "question_id", "answer", "time", "answer_type"];

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }
  
  public function setAnswer($data = NULL) {
    if (isset($data))
    {
      $answer = json_decode($data);
      $sql = "SELECT id FROM nps_answers 
        WHERE nps_id = '$answer->nps_id' AND question_id = '$answer->question_id' AND answer_type = '$answer->answer_type'";
      // die($sql);
      $res = $this->db->query($sql);
      $id = $res->fetch(\PDO::FETCH_ASSOC)['id'];
      $id = intval($id);
      if ($id)
      {
        $sql = " UPDATE nps_answers SET ";
        $sqlEnd = " WHERE id = ".$id;
      }
      else
      {
        $sql = " INSERT INTO nps_answers SET ";
        $sqlEnd = "";
      }
      
      $fsql = "";
      if ($answer->question_id != NULL)
      {
        foreach(self::ANSWER_FIELDS as $field)
        {
          if (isset($answer->$field))
          {
            if ($answer->$field != "" && $answer->$field != NULL)
            {
              if (strlen($fsql)) $fsql .= ",";
              $fsql .= "`$field`='".addslashes($answer-> $field).
              "'";
            }
          }
        }
        $fsql .= " ,`time` = NOW() " ;
        $sql .= $fsql;
        $sql .= $sqlEnd;
        try
        {
          $this->db->query($sql);
        }
        catch (\Throwable $th)
        {
          $result = ["error" => 3, "msg" => "Внутряняя ошибка сервера", "data" => $th];
        }		
      }
        
    }
    $result['result'] = true;
    return $result;
  }

  public function getFirstStageId($id = 0) {
    if (!intval($id)) {
      return false;
    }
    $sql = "SELECT id FROM scripts_stages WHERE script_id = '$id' and parent_id = '0'";
    $res = $this->db->query($sql);
    $row = $res->fetch();
    return $row['id'];
  }

  public function getScriptInfo($id = 0, $sid = 0, $nps_id = NULL) {
    if (!intval($id)) {
      return false;
    }
    if ($sid == 0) {
      $sql = "SELECT * FROM scripts_stages WHERE script_id = '$id' and parent_id = '0'";
      $res = $this->db->query($sql);
      $row = $res->fetch();
      if (!$row) return false;
    } else {
      // fetch stage config
      $sql = "SELECT * FROM scripts_stages WHERE id = '$sid'";
      $res = $this->db->query($sql);
      $stage = $res->fetch(\PDO::FETCH_ASSOC);
      if (!$stage) die("Stage not found");
      // fetch stage elements
      $sql = "SELECT * FROM scripts_stages_elements WHERE script_stage_id = '$sid' AND deleted IS NULL";
      $res = $this->db->query($sql);
      $els = [];
      while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
        $el = [];
        switch ($row['type']) {
          case "button":
            if (isset($nps_id)){
              $takeAnswerBtn = $this->db->query(" SELECT answer FROM nps_answers WHERE question_id = '$sid' AND nps_id = '$nps_id' AND answer_type = 2 ");
              $takeAnswerBtn = $takeAnswerBtn->fetch(\PDO::FETCH_ASSOC);
            }
            $goUsed = 0;
            if (array_key_exists('action_block',$row) && array_key_exists('answer', $takeAnswerBtn)){
              if ($row['action_block'] == $takeAnswerBtn['answer']){
                $goUsed = 1;
              }
            }
            $el = [
              "view" => "button",
              "text" => $row['text'],
              "label" => $row['label'],
              "epbx_action" => $row['action'],
              "epbx_action_script" => $row['action_script'],
              "epbx_action_block" => $row['action_block'],
              "epbx_action_text" => $row['action_text'],
              "epbx_action_transfer" => $row['action_transfer'],
              "epbx_poll" => $row['poll'],
              "epbx_variant" => $row['variant'],
              "epbx_textarea" => $row['textarea'],
              "click" => "epbxAction",
              "used" => $goUsed ? true : false
            ];
            break;      
          case "webpage":
            $el = [
              "view" => "iframe",
              "src" => $row['url'],
              "height" => 800
            ];
            break;
          case "transfer":
            $el = [
              "container" => "layout_div",
              "type" => "space",
              "css"  => "transfer-layout",     
              "id" => "layout",     
              "rows" => [
                [
                    "type" => "wide",
                    "view" => "search",
                    "placeholder" => "Поиск сотрудника",
                    "height" => 35,        
                    "keyPressTimeout" => 100,    
                    "on" => [
                      "onTimedKeyPress" => "filterUsers",
                      "onEnter" => "filterUsers"
                    ]
                ],
                [
                    "view" => "dataview",
                    "id" => "userslist",          
                    "xCount" => 4,
                    "height" => 400,
                    "type" => [
                        "height" => 102,
                        "width" => 232,
                        "css" => "transfer-block"
                    ],
                    "template" =>"<div class='transfer-name'>#fullname#</div>".
                    "<div class='transfer-info'>#group#</div>".
                    "<div class='transfer-status #class#'>#icon# #statusinfo#<div class='transfer-number'>#phone#</div></div>",
                    "url" => "users.php?json",
                    "on" => [   
                      "onItemDblClick" => "transferCall",
                    ],      
                ]
    
                /*
                * #name# - имя сотрудника,
                * #department# - отдел
                * #class# - status-avail - Доступен,
                *            status-talk - Разговаривает,
                 *           status-unavail - Не доступен,
                 *           status-call - Звонок
                * #icon# -  <i class='fas fa-phone' data-fa-transform='flip-h'></i> - Доступен
                *            <i class='fas fa-phone-volume'></i> - Разговаривает
                *            <i class='fas fa-phone-slash' data-fa-transform='flip-h'></i> - Не доступен
                *            <i class=\"fas fa-phone-volume\"></i> - Звонок
                * #status# - статус
                * #number# - номер сотрудника
                * */
              ]          
            ];
            break;
          case "sms":
          case "email":
            $el = [
              "view" => "form",
              "epbx_type" => $row['type'],
              "elements" => [
                [ "view" => "textarea",
                  "height" => 80,
                  "name" => "text",
                  "label" => "Текст сообщения",
                  "value" => $row['text']
                ],
                [ "cols" => [
                    [ "view" => "text",              
                      "name" => "dst",
                      "label" => $row['type'] == "sms" ? "Номер телефона получателя" : "Email получателя",                  
                      "value" => ""
                    ],
                    [
                      "view" => "button",
                      "label" => "Отправить сообщение",           
                      "click" => "sendSmsOrEmail"     
                    ]
                  ]
                ]
              ],
              "elementsConfig" => [
                "labelWidth" => 250
              ]
            ];
            break;
          case "form":
            $formLines = json_decode($row['form'], true);
            $elements = [];
            foreach ($formLines as $l) {
              if (!strlen($l['name'])) continue;
              $elements[] = [
                "view" => "text",
                "label" => $l['name'],
                "name" => $l['name'],
                "placeholder" => $l['placeholder']
              ];
            }
            $elements[] = [ "cols" => [
              [
                "width" => 250
              ],
              [
              "view" => "button",
              "label" => "Отправить сообщение",
              "click" => "sendEmail"     
              ]
             ]
              ];
            $el = [
              "view" => "form",
              "epbx_rcpt" => $row['form_to'],
              "epbx_subject" => "Сообщение из скрипта",
              "elements" => $elements,
              "elementsConfig" => [
              "labelWidth" => 250
              ]
            ];        
            break;
          case "text":
              $el = [
                  "view" => "label",
                  "label" => $row['text'],
                  "css" => "green-block"
              ];
              break;
          case "poll":
            if (isset($nps_id)){
              $takeAnswerRadio = $this->db->query(" SELECT answer FROM nps_answers WHERE question_id = '$sid' AND nps_id = '$nps_id' AND answer_type = 1 ");
              $rowAnswerRadio = $takeAnswerRadio->fetch(\PDO::FETCH_ASSOC);
            }
            $el = [
                "view" => "radio",
                "options" => ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10"],
                "label" => "Значения",
                //"width" => "200",
                "vertical" => "true",
                "name" => "poll",
                "value" => array_key_exists('answer',$rowAnswerRadio) ? $rowAnswerRadio['answer'] : ""
            ];
            break;
          case "textarea":
            if (isset($nps_id)){
              $takeAnswerArea = $this->db->query(" SELECT answer FROM nps_answers WHERE question_id = '$sid' AND nps_id = '$nps_id' AND answer_type = 1 ");
              $takeAnswerArea = $takeAnswerArea->fetch(\PDO::FETCH_ASSOC);  
            }
              $el = [
                  "view" => "textarea",
                  "label" => "",
                  "name" => "textarea",
                  "height" => "100",
                  "value" => array_key_exists('answer',$takeAnswerArea) ? $takeAnswerArea['answer'] : ""
              ];
            break;
          case "variant":
            if (isset($nps_id)){
              $takeAnswerMulti = $this->db->query(" SELECT answer FROM nps_answers WHERE question_id = '$sid' AND nps_id = '$nps_id' AND answer_type = 1 ");
              $rowAnswerMulti = $takeAnswerMulti->fetch(\PDO::FETCH_ASSOC);  
            }
              $el = [
                "view" => "multiselect",
                "options" => ["Ассортимент", "Цены", "Персонал", "Скидки/акции", "Прошлый опыт", "Рекомендации", "Доступность магазина", "Доставка", "качество товара", "Сайт", "Затрудняюсь ответить"],
                "value" => array_key_exists('answer',$rowAnswerMulti) ? $rowAnswerMulti['answer'] : ""
              ];
              break;
          default:
              $el = [
                "view" => "template",
                "template" => $row['text'],
                "autoheight" => true,
                "height" => 150
              ];
            break;
        }
        $els[] = $el;
      }
      header("Content-type: application/json");
      $stage['elements'] = $els;
      // Fetch substages
      if (isset($nps_id)){
        $takeAnswer = $this->db->query(" SELECT answer FROM nps_answers WHERE question_id = '$sid' AND nps_id = '$nps_id' ");
        $rowAnswer = $takeAnswer->fetch(\PDO::FETCH_ASSOC);  
      }
      $sql = "SELECT * FROM scripts_stages WHERE parent_id = '$sid' AND deleted IS NULL";
      $res = $this->db->query($sql);
      $ss = [];
      while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
        $row['used'] = false;
        if ($rowAnswer){
          if (array_key_exists('answer',$rowAnswer)){
            if ($rowAnswer['answer'] == $row[id]){
              $row['used'] = true;
            }
          }
        }
        $ss[] = $row;
      }
      $stage['substages'] = $ss;
      // var_dump($stage);
      return $stage;
    }
  }
}

