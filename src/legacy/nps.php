<?php

namespace Erpico;

class Nps {
  private $container;
  private $db;
  private $auth;  
  private $pdOptions = [
    "Ассортимент",
    "Цены",
    "Персонал",
    "Скидки/акции",
    "Прошлый опыт",
    "Рекомендации",
    "Доступность магазина",
    "Доставка",
    "качество товара",
    "Сайт",
    "Затрудняюсь ответить"
  ];
  private $fields = ["id", "phone", "name",  "channel", "motivational_p", "buyer_status", "lastbuy_date", "city", "filial", "created", "state", "updated", "updated", "called", "callid" ];

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  function getFilter($column, $filter = ''){
    if ($column === "reasonPlus") {
      return $this->pdOptions;
    } else if ($column === "recomendations") {
      $res = $this->db->query("SELECT DISTINCT answer FROM nps_answers left join nps on nps_id = nps.id WHERE question_id = 91 GROUP BY answer ORDER BY answer");
      $result=[];
      while($row = $res->fetch()){
          if ($row['answer']){
              $buf['id'] = $row['answer'];
              $result[] = $buf;
          }
      }
      return $result;
    }
  
    $sqlcitylList = "SELECT DISTINCT nps.id, nps.$column as `value` FROM nps_answers
    LEFT JOIN nps ON nps_id = nps.id
    ";
    if (isset($filter)){
        if (is_array($filter)){
            if (array_key_exists("value", $filter)){
                if ($filter["value"] != "") $sqlcitylList .= " WHERE nps.$column  LIKE '%".addslashes($filter["value"])."%'";
            }
        }else if (is_string($filter)){
            $filter = json_decode($filter);
            if ($filter->value){
                if ($filter->value != "") $sqlcitylList .= " WHERE nps.$column  LIKE '%".addslashes($filter->value)."%'";
            }
        }
    }
    $sqlcitylList .= "GROUP BY `value`,id ORDER BY `value`";
    $resultcitylList = [];
    $sqlcitylListRes = $this->db->query($sqlcitylList);
    while ($rowcitylList = $sqlcitylListRes->fetch()){
        if (is_null($rowcitylList['value'])) continue;
        $buf['id'] = $rowcitylList['value'];
        $buf['value'] = $rowcitylList['value'];
        
        $resultcitylList[] = $buf;
    }
    return $resultcitylList;
  }
  function getCount($filter = "", $byCities = "") {
    $filialSql = "SELECT nps.filial, nps.city FROM nps_answers
    LEFT JOIN nps ON nps_id = nps.id
    ";
    $filterSql = "";
    if (strlen($filter)){
        $filter = json_decode($filter);                
        if ($filter->city && $filter->city != ""){
            if (strlen($filterSql)) $filterSql .= " AND ";
            if (strpos($filter->city, ",") === FALSE){ 
                $filterSql .=  " nps.city = '".$filter->city."' ";
            }else{                        
                $value = delSpaces($filter->city);
                $filterSql .=  "  nps.city IN ('".implode("','",$value)."') ";
            }
        }
        if ($filter->filial && $filter->filial != ""){
            if (intval(byCities)){
                $sqlFilial = "SELECT `city` FROM nps WHERE ";
                $value = delSpaces($filter->filial);
                $sqlFilial .=  "  `filial` IN ('".implode("','",$value)."') ";                        
                $sqlFilial .= ' GROUP BY city';
                $cityByFilialSqlRes = $this->db->query($sqlFilial);                        
                while ($cityByFilialSqlRow = $cityByFilialSqlRes->fetch()){
                    if (is_array($cityByFilialSqlRow)){
                        if (array_key_exists("city", $cityByFilialSqlRow)){
                            if (strlen($cityIn)) $cityIn .= ",";
                            $cityIn .= "'{$cityByFilialSqlRow['city']}'";
                        }
                    }
                }
                if (strlen($cityIn)){
                    if (strlen($filterSql)) $filterSql .= " AND ";
                    $filterSql .= " nps.city IN ($cityIn) ";
                }                        
            }else{
                if (strlen($filterSql)) $filterSql .= " AND ";                        
                $value = delSpaces($filter->filial);
                $filterSql .=  "  `filial` IN ('".implode("','",$value)."') ";
            }        
        }
        if ($filter->start && $filter->end){
            if (strlen($filterSql)) $filterSql .= " AND ";
            $filterSql .= " nps_answers.time BETWEEN  '$filter->start' AND  '".date("Y-m-d 23:59:59", strtotime($filter->end))."'";
        }
        if ($filter->channel){
            if (strlen($filterSql)) $filterSql .= " AND ";                    
            $value = delSpaces($filter->channel);
            $filterSql .=  "nps.channel IN ('".implode("','",$value)."') ";                    
        }
        if (strlen($filterSql)) $filialSql .= " WHERE ".$filterSql;
    }
    if (intval($byCities)) {
      $filialSql .= " GROUP BY city,filial";
    } else {
      $filialSql .= " GROUP BY filial,city";
    }
    $filialSqlRes = $this->db->query($filialSql);
    if (!$filialSqlRes) die(mysql_error());
    $filResult = [];
    $filBuf = [];
    $listIdF = [];

    $totalD = 0;
    $totalN = 0;
    $totalP = 0;
    while ($filialRow = $filialSqlRes->fetch()){
        $filSumD = 0;
        $filSumN = 0;
        $filSumP = 0;
        $filSumNPS = 0;
        $sqlD = "SELECT count(*) as sales FROM nps_answers LEFT JOIN nps ON nps_id = nps.id
            WHERE question_id = 87 AND answer_type = 1 AND answer IN (1,2,3,4,5,6)
            ".(isset($_GET['byCities']) ? " AND nps.city = '{$filialRow['city']}' " : " AND nps.filial = '{$filialRow['filial']}' ").
            (strlen($filterSql) ? " AND $filterSql " : "");                    
        $sqlResD = $this->db->query($sqlD);
        while ($sqlRowD = $sqlResD->fetch()){
            if ($sqlRowD["sales"] != NULL) $filSumD += $sqlRowD["sales"];
        }
        $sqlN = "SELECT count(*) as sales FROM nps_answers LEFT JOIN nps ON nps_id = nps.id
            WHERE question_id = 87 AND answer_type = 1 AND answer IN (7,8)
            ".(isset($_GET['byCities']) ? " AND nps.city = '{$filialRow['city']}' " : " AND nps.filial = '{$filialRow['filial']}' ").
            (strlen($filterSql) ? " AND $filterSql " : "");
        $sqlResN = $this->db->query($sqlN);
        while ($sqlRowN = $sqlResN->fetch()){
            if ($sqlRowN["sales"] != NULL) $filSumN += $sqlRowN["sales"];
        }
        $sqlP = "SELECT count(*) as sales FROM nps_answers LEFT JOIN nps ON nps_id = nps.id
            WHERE question_id = 87 AND answer_type = 1 AND answer IN (9,10)
            ".(isset($_GET['byCities']) ? " AND nps.city = '{$filialRow['city']}' " : " AND nps.filial = '{$filialRow['filial']}' ").
            (strlen($filterSql) ? " AND $filterSql " : "");
        $sqlResP = $this->db->query($sqlP);
        while ($sqlRowP = $sqlResP->fetch()){
            if ($sqlRowP["sales"] != NULL) $filSumP += $sqlRowP["sales"];
        }                
        $allClients = $filSumD + $filSumP + $filSumN;
        $percentD = $filSumD * 100 / ($allClients == 0 ? 1 : $allClients);
        $percentP = $filSumP * 100 / ($allClients == 0 ? 1 : $allClients);
        $percentN = $filSumN * 100 / ($allClients == 0 ? 1 : $allClients);
        
        $totalD += $filSumD;
        $totalN += $filSumN;
        $totalP += $filSumP;

        $filBuf["name"] = (isset($_GET['byCities']) ? $filialRow['city'] : $filialRow['filial']);   

        if (is_null($filBuf["name"])) continue;

        $filBuf["nps"] = ceil($percentP - $percentD); 
        $filBuf["detractors"] = ceil($percentD);
        $filBuf["neutrals"] = ceil($percentN);
        $filBuf["promouters"] = ceil($percentP);
        $filBuf['allClients'] = intval($allClients);
        $filBuf['numberNps'] = intval($filBuf["nps"] * $filBuf['allClients'] /100);
        array_push($filResult, $filBuf);
    }
    $allClients = $totalD + $totalN + $totalP;
    $percentD = $totalD * 100 / ($allClients == 0 ? 1 : $allClients);
    $percentP = $totalP * 100 / ($allClients == 0 ? 1 : $allClients);
    $percentN = $totalN * 100 / ($allClients == 0 ? 1 : $allClients);

    $filBuf["name"] = "Всего";
    $filBuf["nps"] = ceil($percentP - $percentD); 
    $filBuf["detractors"] = ceil($percentD);
    $filBuf["neutrals"] = ceil($percentN);
    $filBuf["promouters"] = ceil($percentP);
    $filBuf['allClients'] = intval($allClients);
    $filBuf['numberNps'] = intval($filBuf["nps"] * $filBuf['allClients'] /100);
    array_unshift($filResult, $filBuf);

    try {
        return $filResult;
    } catch (\Throwable $th) {
        print_r($th);
    }
  }
  public function getPromoters($filter = "") {
    $sqlpromotersListId = "SELECT  nps_id FROM nps_answers
      LEFT JOIN nps ON nps_id = nps.id
      WHERE question_id = 87 AND answer_type = 1 AND answer IN (9,10)";
    if (strlen($filter)){
      $filter = json_decode($filter);
      $filialFilterSql = "";
      if ($filter->city && $filter->city != ""){
        if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";
        $value = explode(",",$filter->city);
        $filialFilterSql .=  "nps.city IN ('".implode("','",$value)."') ";
      }
      if ($filter->filial && $filter->filial != ""){
        if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";                    
        $value = delSpaces($filter->filial);
        $filialFilterSql .=  "nps.filial IN ('".implode("','",$value)."') ";                    
      }
      if ($filter->start && $filter->end){
        if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";
        $filialFilterSql .= " created Between  '$filter->start' AND  '".date("Y-m-d 23:59:59", strtotime($filter->end))."'";
      } else if ($filter->start != "" && $filter->end == "") {
        if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";
        $filialFilterSql .= " time =  '$filter->start'";
      }
      if ($filter->channel){
        if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";                    
        $value = delSpaces($filter->channel);
        $filialFilterSql .=  "nps.channel IN ('".implode("','",$value)."') ";
      }
      if (strlen($filialFilterSql)) $sqlpromotersListId .= " AND ".$filialFilterSql;
    }

    $res = $this->db->query($sqlpromotersListId);            

    $listIdP = [];
    while ($rowpromotersList = $res->fetch()){
      array_push($listIdP, $rowpromotersList['nps_id']);
    }
    if (!COUNT($listIdP)) {
      return [];
    }
    $sql = "SELECT COUNT(*) as cnt, answer, GROUP_CONCAT(nps_id) AS nps_ids FROM nps_answers
    WHERE nps_id IN (".implode(",",$listIdP).") AND question_id = 88 AND answer_type = 1 GROUP BY answer";                            
    $respromotersListRes = $this->db->query($sql);            

    $promouters = [];

    $sum = 0;

    while ($rowDestructors = $respromotersListRes->fetch()){
      // Get comments
      $sql = "SELECT GROUP_CONCAT(answer SEPARATOR ', ') AS answers FROM nps_answers WHERE nps_id IN ({$rowDestructors['nps_ids']}) AND question_id = '91' AND answer_type = 1";                
      $res = $this->db->query($sql);
      $row = $res->fetch();

      $answers = explode(",", $rowDestructors['answer']);
      foreach ($answers as $answer) {
        if (isset($promouters[$answer])) {
          // include
          $promouters[$answer]['sales'] += $rowDestructors['cnt'];
          $promouters[$answer]['comments'] = trim($row ? $promouters[$answer]['comments'].",".$row['answers'] : $promouters[$answer]['comments'], ", ");                            
        } else {
          // new
          $promouters[$answer] =  [
            "comments" => $row ? $row['answers'] : '',
            "id" => $rowDestructors['id'],
            "label" => $answer,
            "sales" => $rowDestructors['cnt']
          ];
        }
        $sum += $rowDestructors['cnt'];
      }                               
    }

    $result = [];

    foreach ($this->pdOptions as $v) {
      if (isset($promouters[$v])) {
        $p = $promouters[$v];
        $p['sales'] = floor($p['sales']*100/$sum);
      } else {
        $p = [
          "comments" => '',
          "id" => null,
          "label" => $v,
          "sales" => 0                        
        ];
      }
      $result[] = $p;
    }
    return $result;
  }
  public function getDetractors($filter = "") {
    $sqldetractorsListId = "SELECT  nps_id FROM nps_answers
    LEFT JOIN nps ON nps_id = nps.id
    WHERE question_id = 87 AND answer_type = 1 AND answer IN (0,1,2,3,4,5,6)
    ";
    if (strlen($filter)){
        $filter = json_decode($filter);
        $filialFilterSql = "";
        if ($filter->city && $filter->city != ""){
            if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";                    
            $value = delSpaces($filter->city);
            $filialFilterSql .=  "nps.city IN ('".implode("','",$value)."') ";
        }
        if ($filter->filial && $filter->filial != ""){
            if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";                    
            $value = delSpaces($filter->filial);
            $filialFilterSql .=  "nps.filial IN ('".implode("','",$value)."') ";                    
        }
        if ($filter->start && $filter->end){
            if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";
            $filialFilterSql .= " created Between  '$filter->start' AND  '".date("Y-m-d 23:59:59", strtotime($filter->end))."'";
        }
        if ($filter->channel){
            if (strlen($filialFilterSql)) $filialFilterSql .= " AND ";
            $value = delSpaces($filter->channel);
            $filialFilterSql .=  "nps.channel IN ('".implode("','",$value)."') ";                    
        }
        if (strlen($filialFilterSql)) $sqldetractorsListId .= " AND ".$filialFilterSql;
    }
    $resultdetractorsList = [];
    
    $resdetractorsList = $this->db->query($sqldetractorsListId);
    $listIdD = [];
    while ($rowdetractorsList = $resdetractorsList->fetch()){
        array_push($listIdD, $rowdetractorsList['nps_id']);
    }            
    if (!COUNT($listIdP)) {
      return [];
    }
    $resdetractorsListRes = $this->db->query("SELECT 
      COUNT(*) as cnt, GROUP_CONCAT(nps_id) AS nps_ids, answer 
      FROM nps_answers
      WHERE nps_id IN (".implode(",",$listIdD).") AND question_id = 88 AND answer_type = 1 GROUP BY answer
    ");        
    $detractors = [];

    $sum = 0;
    
    while ($rowDestructors = $resdetractorsListRes->fetch()){
        //91
        $sql = "SELECT GROUP_CONCAT(answer SEPARATOR ', ') AS answers FROM nps_answers WHERE nps_id IN ({$rowDestructors['nps_ids']}) AND question_id = '91' AND answer_type = 1";                
        $res = $this->db->query($sql);
        $row = $res->fetch();

        $answers = explode(",", $rowDestructors['answer']);
        foreach ($answers as $answer) {
            if (isset($detractors[$answer])) {
                // include
                $detractors[$answer]['sales'] += $rowDestructors['cnt'];
                $detractors[$answer]['comments'] = trim($row ? $detractors[$answer]['comments'].", ".$row['answers'] : $detractors[$answer]['comments'], ", ");                            
            } else {
                // new
                $detractors[$answer] =  [
                    "comments" => $row ? $row['answers'] : '',
                    "id" => $rowDestructors['id'],
                    "label" => $answer,
                    "sales" => $rowDestructors['cnt']
                ];
            }
            $sum += $rowDestructors['cnt'];
        }            
    }

    $result = [];
    foreach ($this->pdOptions as $v) {
        if (isset($detractors[$v])) {
            $p = $detractors[$v];
            $p['sales'] = floor($p['sales']*100/$sum);
        } else {
            $p = [
                "comments" => '',
                "id" => $v,
                "label" => $v,
                "sales" => 0
            ];
        }
        $result[] = $p;
    }
    return $result;
  }
  public function getReport($filter, $start = 0, $count = 0) {
    $firstSql = "SELECT 
      nps_id,
      nps.id as id,
      nps.phone  as phone,
      nps.motivational_p as motivational_p,
      nps.buyer_status,
      nps.city, nps.filial, 
      nps.name,
      nps.channel as channel,
      lastbuy_date
      FROM nps_answers left join nps on nps_id = nps.id";

    $sqlFilter = "SELECT nps_id FROM nps_answers left join nps on nps_id = nps.id";
    $fsql = "";
    if (isset($filter)){
        if (is_array($filter)){
            foreach ($filter as $key => $value) {
                if ($value != ""){
                  if ($key == 'lastbuy_date'){
                    $value = json_decode($value);
                    if ($value->start != NULL){
                      if (strlen($fsql)) $fsql .=" AND ";
                      $startDate = date("d.m.Y", strtotime($value->start));
                      if ($value->end){
                        $endDate = date("d.m.Y 23:59:59", strtotime($value->end));
                      }else{
                        $endDate = date("d.m.Y 23:59:59", strtotime($value->start));
                      }
                      $fsql .=  "nps.lastbuy_date Between '$startDate' AND '$endDate' ";
                    }								
                  }else if ($key == 'called'){
                    $value = json_decode($value);
                    if ($value->start != NULL && $value->end != NULL){
                      if (strlen($fsql)) $fsql .=" AND ";
                      $fsql .=  "nps.".$key." Between '$value->start' AND '".date("Y-m-d 23:59:59", strtotime($value->end))."' ";
                    }
                  }else if ($key == 'date'){
                    $value = json_decode($value);
                    if ($value->start != NULL && $value->end != NULL){
                      if (strlen($fsql)) $fsql .=" AND ";
                      $fsql .=  "nps_answers.question_id = 87 AND nps_answers.answer_type = 1 AND nps_answers.time 
                      Between '$value->start' AND '".date("Y-m-d 23:59:59", strtotime($value->end))."' ";
                    }
                  }else if ($key == 'mark'){
                    $value = json_decode($value);
                    if ($value->from != "" && $value->to != ""){
                      if (strlen($fsql)) $fsql .=" AND ";
                      $fsql .=  "nps_answers.answer Between '$value->from' AND  '$value->to' AND question_id = 87";
                    } else if ($value->from != "" && $value->to == "") {
                      $fsql .=  "nps_answers.answer >= '$value->from' AND  question_id = 87";
                    } else if ($value->from == "" && $value->to != "") {
                      $fsql .=  "nps_answers.answer <= '$value->to' AND  question_id = 87";
                    }
                  }else if ($key == 'markFrom'){
                    $bufSql = $sqlFilter;
                    $bufFSql = $fsql;
                    if (strlen($bufFSql)) $bufFSql .=" AND ";
                    $bufFSql .= " question_id = 87  AND answer_type = 1 ";
                    if (strlen($fsql)) {
                      $bufSql .=  " WHERE ".$bufFSql;
                    }
                    $bufSql .= " GROUP BY nps_id";
                    $bufRes = $this->db->query($bufSql);
                    $bufFId = [];
                    while ($bufRows = $bufRes->fetch()) {
                      array_push($bufFId,$bufRows["nps_id"]);
                    }
                    if (!empty($bufFId)){
                      $secondBufSql = $sqlFilter . " WHERE nps_answers.nps_id IN (".implode(",",$bufFId).") AND nps_answers.answer >= $value AND  question_id = 87 AND answer_type = 1 ";
                      $secondBufRes = $this->db->query($secondBufSql);
                      $secondBufFId = [];
                      while ($SecondBufRows = $secondBufRes->fetch()) {
                        array_push($secondBufFId,$SecondBufRows["nps_id"]);
                      }   
                      if (strlen($fsql)) $fsql .= " AND ";
                      $fsql .=  " nps_id in (".implode(",",$secondBufFId).")";
                    }
                  }else if ($key == 'markTo'){
                    $bufSql = $sqlFilter;
                    $bufFSql = $fsql;
                    if (strlen($bufFSql)) $bufFSql .=" AND ";
                    $bufFSql .= " question_id = 87  AND answer_type = 1 ";
                    if (strlen($fsql)) {
                      $bufSql .=  " WHERE ".$bufFSql;
                    }
                    $bufSql .= " GROUP BY nps_id";
                    $bufRes = $this->db->query($bufSql);
                    $bufFId = [];
                    while ($bufRows = $bufRes->fetch()) {
                      array_push($bufFId,$bufRows["nps_id"]);
                    }
                    if (!empty($bufFId)){
                      $secondBufSql = $sqlFilter . " WHERE nps_answers.nps_id IN (".implode(",",$bufFId).") AND nps_answers.answer <= $value AND  question_id = 87 AND answer_type = 1 ";
                      $secondBufRes = $this->db->query($secondBufSql);
                      $secondBufFId = [];
                      while ($SecondBufRows = $secondBufRes->fetch()) {
                        array_push($secondBufFId,$SecondBufRows["nps_id"]);
                      }   
                      if (strlen($fsql)) $fsql .= " AND ";
                      $fsql .=  " nps_id in (".implode(",",$secondBufFId).")";
                    }                                
                  }else if ($key == 'recomendations_mark'){
                    if (strlen($fsql)) $fsql .=" AND ";
                    $fsql .=  "nps_answers.answer = $value AND question_id = 87 AND answer_type = 1 ";
                  }else if ($key == 'recomendations'){
                    if (strlen($fsql)) $fsql .=" AND ";
                    if (strpos($value, ",") === FALSE){ 
                      $fsql .=  "nps_answers.answer LIKE '%".addslashes($value)."%' AND question_id = 91";
                    }else{
                      $value = explode(",",$value);
                      $fsql .=  "nps_answers.answer IN ('".implode("','",$value)."')  AND question_id = 91";
                    }
                  }else if ($key == 'reason_plus'){
                    $reasonPlus = true;
                    if (strlen($fsql)) $fsql .=" AND ";
                    $bufSql = $sqlFilter;
                    $bufFSql = $fsql;
                    $bufFSql .= " question_id = 87  AND answer_type = 2 AND answer = 88 ";
                    if (strlen($fsql)) {
                      $bufSql .=  " WHERE ".$bufFSql;
                    }
                    $bufSql .= " GROUP BY nps_id";
                    $bufRes = $this->db->query($bufSql);
                    $bufFId = [];
                    while ($bufRows = $bufRes->fetch()) {
                      array_push($bufFId,$bufRows["nps_id"]);
                    }
                    if (!empty($bufFId)){
                      $secondBufSql = $sqlFilter . " WHERE nps_answers.nps_id IN (".implode(",",$bufFId).") AND question_id = 88  AND answer_type = 1  ";
                      if (strpos($value, ",") === FALSE){
                        $secondBufSql .=  "AND nps_answers.answer LIKE '%".addslashes($value)."%'";
                      }else{
                        $value = explode(",",$value);
                        $secondBufSql .=  "AND nps_answers.answer IN ('".implode("','",$value)."')";
                      }
                      $secondBufRes = $this->db->query($secondBufSql);
                      $secondBufFId = [];
                      while ($SecondBufRows = $secondBufRes->fetch()) {
                        array_push($secondBufFId,$SecondBufRows["nps_id"]);
                      }   
                      if (strlen($fsql)) $fsql .= " AND ";
                      $fsql .=  " nps_id in (".implode(",",$secondBufFId).")";
                    }
                  }else if ($key == 'reason_minus'){
                    $reasonPlus = true;
                    if (strlen($fsql)) $fsql .=" AND ";
                    $bufSql = $sqlFilter;
                    $bufFSql = $fsql;
                    $bufFSql .= " question_id = 88  AND answer_type = 2 AND answer = 89 ";
                    if (strlen($bufFSql)) {
                      $bufSql .=  " WHERE ".$bufFSql;
                    }
                    $bufSql .= " GROUP BY nps_id";
                    $bufRes = $this->db->query($bufSql);
                    $bufFId = [];
                    echo 'bufSql='.$bufSql;
                    while ($bufRows = $bufRes->fetch()) {
                      array_push($bufFId,$bufRows["nps_id"]);
                    }
                    if (!empty($bufFId)){
                      $secondBufSql = $sqlFilter . " WHERE nps_answers.nps_id IN (".implode(",",$bufFId).")  AND question_id = 88  AND answer_type = 1  ";
                      if (strpos($value, ",") === FALSE){
                        $secondBufSql .=  " AND nps_answers.answer LIKE '%".addslashes($value)."%'";
                      }else{
                        $value = explode(",",$value);
                        $secondBufSql .=  " AND nps_answers.answer IN ('".implode("','",$value)."')";
                      }
                      $secondBufRes = $this->db->query($secondBufSql);
                      $secondBufFId = [];
                      while ($SecondBufRows = $secondBufRes->fetch()) {
                        array_push($secondBufFId,$SecondBufRows["nps_id"]);
                      }   
                      if (strlen($fsql)) $fsql .= " AND ";
                      $fsql .=  " nps_id in (".implode(",",$secondBufFId).")";
                    }
                  } else{
                    if (strlen($fsql)) $fsql .=" AND ";
                    if (strpos($value, ",") === FALSE){
                      $fsql .=  "nps.".$key ." LIKE '%".addslashes($value)."%'";
                    }else{
                      $value = explode(",",$value);
                      $fsql .=  "nps.".$key ." IN ('".implode("','",$value)."')";
                    }
                  }
                }
              }
            }
          }
      if (strlen($fsql)){
        $sqlFilter .= " WHERE ".$fsql;
      }
      $sqlFilter .= " GROUP BY nps_id ";              
      $result = [];
      $bufferForSelect = [];
      if (strlen($fsql)){
        $firstSql .= " WHERE ".$fsql;
      }
      $firstSql .= " GROUP BY nps_id ";                          
      $nps = $this->db->query($firstSql);
      while ($rowNps = $nps->fetch()){
        $sql = "SELECT nps_answers.id as id, nps.id as nps_id, 
                        nps_answers.question_id,
                        nps_answers.answer, 
                        nps_answers.answer_type                                   
                FROM nps_answers
                left join nps on nps_answers.id = nps.id
                WHERE nps_id = '$rowNps[nps_id]' ";                    
        $answerRows = $this->db->query($sql);
        $dataRow = [];

        $dataRow['id']             = $rowNps['nps_id'];
        $dataRow['buyer_status']   = $rowNps['buyer_status'];
        $dataRow['city']           = $rowNps['city'];
        $dataRow['filial']         = $rowNps['filial'];
        $dataRow['called']         = $rowNps['called'];
        $dataRow['phone']          = $rowNps['phone'];
        $dataRow['name']           = $rowNps['name'];
        $dataRow['lastbuy_date']   = $rowNps['lastbuy_date'];
        $dataRow['channel']        = $rowNps['channel'];
        $dataRow['motivational_p'] = $rowNps['motivational_p'];

        $answers = [];

        while ($row = $answerRows->fetch()){
          $answers[$row['question_id'].":".$row['answer_type']] = $row;
        }
              
        $dataRow['recomendations_mark'] = (isset($answers["87:1"]) ? $answers["87:1"]['answer'] : "");
        $dataRow['reason_plus'] = (isset($answers["87:2"]) && $answers["87:2"]['answer'] == '88' && isset($answers["88:1"]) ? $answers["88:1"]['answer'] : "");
        $dataRow['reason_minus'] = (isset($answers["87:2"]) && $answers["87:2"]['answer'] == '89' && isset($answers["89:1"]) ? $answers["89:1"]['answer'] : "");
        $dataRow['recomendations'] = (isset($answers["91:1"]) ? $answers["91:1"]['answer'] : "");

        $dataRow['answers'] = $answers;
        $result[] = $dataRow;
      }
      return $result;
  }
  public function getClients($filter = "", $start = 0, $count = 20) {
    $sql = "SELECT ".implode(',', $this->fields)." FROM nps ";
    $countSql = "SELECT COUNT(*) as `count` FROM nps ";
    if (isset($filter)){
      if (is_array($filter)){
        $fsql = "";
        foreach ($filter as $key => $value) {
          if ($value != ""){
            if ($key == 'created'){
              $value = json_decode($value);
              if ($value->start != NULL && $value->end != NULL){
                if (strlen($fsql)) $fsql .=" AND ";
                $fsql .=  $key." Between '$value->start' AND '$value->end' ";
              }
            }else if ($key == 'called'){
              $value = json_decode($value);
              if ($value->start != NULL){
                if (strlen($fsql)) $fsql .=" AND ";
                $startDate = date("Y.m.d", strtotime($value->start));
                if ($value->end){
                  $endDate = date("Y.m.d 23:59:59", strtotime($value->end));
                }else{
                  $endDate = date("Y.m.d 23:59:59", strtotime($value->start));
                }
                $fsql .=  "called Between '$startDate' AND '$endDate' ";
              }
            }else if ($key == 'lastbuy_date'){
              $value = json_decode($value);
              if ($value->start != NULL && $value->end != NULL){
                if (strlen($fsql)) $fsql .=" AND ";
                $fsql .=  "lastbuy_date Between '".date("Y-m-d", strtotime($value->start))."' AND '".date("Y-m-d", strtotime($value->end))."' ";
              }
            }else{
              if (strlen($fsql)) $fsql .=" AND ";
              if (strpos($value, ",") === FALSE){
                $fsql .=  $key ." LIKE '%".addslashes($value)."%'";
              }else{
                $value = explode(",",$value);
                $fsql .=  $key ." IN ('".implode("','",$value)."')";
              }
            }
          }
        }
      }
    }
    if (strlen($fsql)){
      $sql .=  "WHERE ".$fsql;
      $countSql .= "WHERE ".$fsql;
    }
    $sql .= "ORDER BY id LIMIT $start, $count";
    $res = $this->db->query($sql);
    $result = [];
    $resultBuffer = [];
    $bufferForSelect = [];
    $countRes = $this->db->query($countSql)->fetch();
    $totalCount = intval($countRes['count']);
    while ($row = $res->fetch())
    {
      foreach($this->fields as $field)
      {
        $bufferForSelect[$field] = $row[$field];
      }
      array_push($resultBuffer, $bufferForSelect);
    }
    $result = [
      "data" => $resultBuffer,
      "pos" => $start,
      "total_count" => $totalCount
    ];
    
    return $result;
  }

  public function importClients($data = null) {
    $result = [];
    if (isset($data))
    {
      $clients = json_decode($data);
      $i = 0;				
      foreach ($clients as  $client)
      {
        $sql = " INSERT INTO nps SET created = Now(), state = 0, ";
        $fsql = "";
        foreach($this->fields as $field){
          if (isset($client->$field)){
            if ($client->$field != "" && $client->$field != NULL)
            {
              if (strlen($fsql)) $fsql .=",";
              $fsql .= "`$field`='".addslashes($client->$field)."'";
            }
          }
        }
        $sql .= $fsql;
        try 
        {
          $res = $this->db->query($sql);
          if ($res)
          {
            $result[] = [ "count" => $i, "msg" => "Внутряняя ошибка сервера", "data" => $res];
          }
        } 
        catch (\Throwable $th)
        {
          $result[] = ["error" => 3, "count" => $i, "msg" => "Внутряняя ошибка сервера", "data" => $th];
        }
        $i++;
      }
    }
    $result['result'] = true;
    return $result;
  } 
  public function removeClient($id = null) {
    if (isset($id)  && !empty($id))
    {
      try
      {
        $this->db->query("DELETE FROM nps WHERE id = '".intval($id)."'");        
        $query = $this->db->query("SELECT id FROM nps_answers WHERE nps_id =  '".intval($id)."'");
        $listIdD = [];
        while ($rows = $query->fetch()){
          array_push($listIdD, $rows['id']);
        }
        if (COUNT($listIdD)){
          $this->db->query("DELETE FROM nps_answers WHERE id  IN (".implode(",",$listIdD).")");
        }
        $result = [ "result" => true, "msg" => "Клиент успешно удален", "data" => []];
      } 
      catch (\Throwable $th)
      {
        $result = [ "result" => false, "msg" => "Внутряняя ошибка сервера", "data" => $th];
      }
      return $result;
    }
  }
  public function setClientState($npsId = null, $state = null) {
    $result = [ "resut" => false, "msg" => "Внутряняя ошибка сервера", "data" => [$npsId, $state]];
    if (isset($npsId) && (isset($state)))
		{
		  try
			{	
			  //$date = $_GET['state'] != 0 ? date("Y-m-d H:m:s") : '';
				$this->db->query("UPDATE nps SET `state` = $_GET[state], `called` = Now() WHERE id = $_GET[npsId]");
				$result = [ "result" => true, "msg" => "Статус звонка успешно изменен", "data" => []];
			} 
			catch (\Throwable $th)
			{
				$result = [ "resut" => false, "msg" => "Внутряняя ошибка сервера", "data" => $th];
			}			
    }
    return $result;
  }
}