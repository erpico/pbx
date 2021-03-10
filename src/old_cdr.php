<?php

class PBXOldCdr {
  protected $db;

  public function __construct() {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];

    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();
  }


  public function translate($word) {
    if(isset($_COOKIE['language'])) $library = "../helpers/i18n/".str_replace('"', "", $_COOKIE['language']).".php";
    else $library = "../helpers/i18n/en.php";
  
    include($library);
    
    $translation = (isset($translation_table[$word]) && $translation_table[$word]!="") ? $translation_table[$word] : $word;
    return $translation;
  }
  public function fetchList($filter = []) {
    $ext = $this->user->allow_extens();
    $extens = $this->utils->sql_allow_extens($ext);

    $que = $this->user->allowed_queues();
    $queues = $this->utils->sql_allowed_queues_for_records($que);

    // $users_list = $this->user->getUsersList();

    $qwsql = "";
    $cwsql = "";

    $timeisset = 0;

    if (intval($onlyCount)) {
      if ($timeisset != 2) return 100000; // Return infinite for scrolling
      $sql = "SELECT SUM(n) FROM (SELECT SUM(n) AS n FROM (SELECT COUNT(*) AS n FROM queue_cdr a LEFT OUTER JOIN queue_cdr b ON a.uniqid = b.uniqid AND a.id < b.id WHERE b.uniqid IS NULL  $queues $qwsql) as u UNION SELECT COUNT(uniqueid) AS n FROM cdr WHERE 1=1 $extens $cwsql) as c";                              
      $res = $this->db->query($sql);      
      $row = $res->fetch(PDO::FETCH_NUM);      
      return $row[0]; 
    }

    
    if (!isset($start) or (isset($start) && !is_numeric($start))) $start = 0;
    if (!isset($count) or (isset($count) && !is_numeric($count))) $count = 50;
    $demand_cdr = "
      SELECT calldate,src,dst,duration AS ratesec,disposition,userfield,department,currency,cost,B.fullname AS fullname, cdr.channel, cdr.dstchannel 
      FROM cdr		
      LEFT JOIN acl_user AS B ON (B.id = (SELECT MAX(A.acl_user_id) FROM cfg_user_setting AS A WHERE ((A.val=src OR A.val=dst) AND A.handle = 'cti.ext')))";
    if(isset($filter['t1']) && isset($filter['t2'])&&($filter['t1']!="") && ($filter['t2']!="")) $demand_cdr = $demand_cdr.
            "	WHERE calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $demand_cdr = $demand_cdr.
            "	WHERE UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";
    if(isset($filter['src'])) $demand_cdr = $demand_cdr.
            "	AND src LIKE '%".$filter['src']."%' ";
    if(isset($filter['dst'])) $demand_cdr = $demand_cdr.
            "	AND dst LIKE '%".$filter['dst']."%' ";
    $demand_cdr.= $extens;

    $demand_cdr = $demand_cdr.
            "	ORDER BY calldate DESC ";
            // die($demand_cdr);
    $result_cdr = $this->db->query($demand_cdr);
    $cdr_arr = array();
    $i = 0;
    while($cdr = $result_cdr->fetch()) {
      $cdr_arr['data'][$i] = $cdr;
      $cdr_arr['data'][$i]['ratesec'] = $this->utils->time_format($cdr_arr['data'][$i]['ratesec']);
      $cdr_arr['data'][$i]['fullname'] = empty($cdr_arr['data'][$i]['fullname']) ? "" : $cdr_arr['data'][$i]['fullname'];
      //$cdr_arr[$i]['ratesec'] = sprintf("%02d:%02d",intval($ratesec/60),intval($ratesec%60));
      if($cdr_arr['data'][$i]['cost']>0) $cdr_arr['data'][$i]['cost'] = $cdr_arr['data'][$i]['cost']." ".$cdr_arr['data'][$i]['currency'];
        else $cdr_arr['data'][$i]['cost'] = "0.00";
      $cdr_arr['data'][$i]['status'] = $this->translate($cdr_arr['data'][$i]['disposition']);
      if($this->translate($cdr_arr['data'][$i]['disposition'])!=$this->translate("ANSWERED")) $cdr_arr['data'][$i]['ratesec'] = $this->utils->time_format(0);
      $cdr_arr['data'][$i]['calldate2'] = date('d.m.Y H:i:s',strtotime($cdr_arr['data'][$i]['calldate']));
      //$cdr_arr['data'][$i] = array("id"=>$i);
      $i++;                
    };
	  return $cdr_arr;
  }

  public function getTrafic($filter = []) {
    $ext = $this->user->allow_extens();
    $extens = $this->utils->sql_allow_extens($ext);

    $que = $this->user->allowed_queues();
    $queues = $this->utils->sql_allowed_queues_for_records($que);

    // $users_list = $this->user->getUsersList();

    $demand_cdr = "	SELECT SUM(billsec),SUM(IF(disposition = 'ANSWERED',duration,0)) AS sum_duration,count(*),count(IF(disposition = 'ANSWERED',1,NULL)), 
    sum(IF(disposition = 'ANSWERED', (duration-billsec),0)) 
      FROM cdr ";
    if(isset($filter['t1']) && isset($filter['t2'])&&($filter['t1']!="") && ($filter['t2']!="")) $demand_cdr = $demand_cdr.
    "	WHERE calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $demand_cdr = $demand_cdr.
    "	WHERE UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";
    if(isset($filter['src'])) $demand_cdr = $demand_cdr.
      "	AND src LIKE '%".$filter['src']."%' ";
    if(isset($filter['dst'])) $demand_cdr = $demand_cdr.
      "	AND dst LIKE '%".$filter['dst']."%' ";
    $demand_cdr.= $extens;



    $result_cdr = $this->db->query($demand_cdr);
    $cdr = $result_cdr->fetch();
    $cdr_arr = array();

    $cdr_arr['1'] = $this->utils->time_format($cdr['sum_duration']);

    $cdr_arr['2'] = $cdr['count(*)'];	
    if ($cdr[3]) $tmc = $cdr[1]/$cdr[3]; else $tmc=0;
    $cdr_arr['3'] = sprintf("%02d:%02d",intval($tmc/60),intval($tmc%60));


    if ($cdr[2]!=0) $asr = $cdr[3]/$cdr[2]*100; else $asr=0;
    $cdr_arr['4'] = round($asr,1);
    if ($cdr[3]!=0) $pdd = $cdr[4]/$cdr[3]; else $pdd=0;
    $cdr_arr['5'] = round($pdd,1);


    if($max_minutes<$dailyreport_sql['sum_duration']) $max_minutes = $dailyreport_sql['sum_duration'];
    //$js_obj = json_encode($cdr_arr);
    return $cdr_arr;
  }

}
