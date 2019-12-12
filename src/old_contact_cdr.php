<?php

class PBXOldContactCdr {
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

    $demand = "	SELECT 
    A.id AS id,
    A.calldate AS calldate,
    A.queue AS queue,
    A.agentid AS agentid,
    A.agentname AS agentname,
    A.agentdev AS agentdev,
    A.agentcalls AS agentcalls,
    A.holdtime AS holdtime,
    A.talktime AS talktime,
    A.ringtime AS ringtime,
    A.position AS position,
    A.origposition AS origposition,
    A.callerid AS callerid,
    A.src AS src,
    A.reason AS reason,
    A.record_file AS record_file,
    B.fullname AS fullname_queue,
    C.fullname AS fullname_agent,
    channel,
    dstchannel,
    uniqid AS uid
    FROM queue_cdr AS A
    LEFT JOIN queue AS B ON (A.queue=B.name)
    LEFT JOIN acl_user AS C ON (A.agentname=C.name)";
  if(isset($filter['t1']) && isset($filter['t2'])) $demand = $demand."
    WHERE calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
  else $demand = $demand."
    WHERE UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";
  if(isset($filter['filter'])) {
  if($filter['filter']==2) $demand = $demand." 
    AND (reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND !outgoing ";
  else if($filter['filter']==3) $demand = $demand."
    AND (reason = 'ABANDON' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTKEY' OR reason = 'RINGNOANSWER') AND !outgoing ";
  else if($filter['filter']==4) $demand = $demand."
    AND outgoing=1 ";
  else $demand = $demand."
    AND !outgoing ";
  };
  if(isset($filter['src'])) $demand = $demand.
  "	AND src LIKE '%".$filter['src']."%' ";
  $demand.= $queues;
  $demand = $demand." 
    ORDER BY calldate DESC";

  // die($demand);
  $result = $this->db->query($demand);
  $cdr_report = array();
  $i = -1;
  while($myrow = $result->fetch()) {
  $i++;
  $cdr_report[$i] = $myrow;
  };

  for($j=0; $j<=$i; $j++) {
  if($cdr_report[$j]['agentname']) {
  $cdr_report[$j]['agent'] = $cdr_report[$j]['agentname'];
  }
  else if($cdr_report[$j]['agentid']) {
  $cdr_report[$j]['agent'] = $cdr_report[$j]['agentid'];
  }
  else if($cdr_report[$j]['agentdev']){
  $cdr_report[$j]['agent'] = $cdr_report[$j]['agentdev'];
  };
  $cdr_report[$j]['agent'] = $cdr_report[$j]['fullname_agent']!="" ? $cdr_report[$j]['fullname_agent'] : $cdr_report[$j]['agent'];
  $cdr_report[$j]['queue'] = $cdr_report[$j]['fullname_queue']!="" ? $cdr_report[$j]['fullname_queue'] : $cdr_report[$j]['queue'];
  $cdr_report[$j]['holdtime'] = $this->utils->time_format($cdr_report[$j]['holdtime']);
  //$cdr_report[$j]['holdtime'] = sprintf("%02d:%02d",intval($cdr_report[$j]['holdtime']/60),intval($cdr_report[$j]['holdtime']%60));
  $cdr_report[$j]['talktime'] = $this->utils->time_format($cdr_report[$j]['talktime']);
  $cdr_report[$j]['ringtime'] = $this->utils->time_format($cdr_report[$j]['ringtime']);
  //$cdr_report[$j]['talktime'] = sprintf("%02d:%02d",intval($cdr_report[$j]['talktime']/60),intval($cdr_report[$j]['talktime']%60));
  $cdr_report[$j]['position'] = $cdr_report[$j]['origposition']."/".$cdr_report[$j]['position'];
  $cdr_report[$j]['status'] = $this->translate($cdr_report[$j]['reason']);
  $cdr_report[$j]['calldate2'] = date('d.m.Y H:i:s',strtotime($cdr_report[$j]['calldate']));

  if (strpos($cdr_report[$j]['channel'], "Local/s@mail_dummy") !== FALSE) {
  // get from
  $mailid = substr($cdr_report[$j]['src'], 6);		
  $sql = "SELECT `from` FROM mail_messages WHERE id = '$mailid'";
  $res = mysql_query($sql);
  if ($res) {
    $row = mysql_fetch_row($res);
    if ($row) {
      $cdr_report[$j]['src'] = $row[0];
    }
  }
  }
  };
  return $cdr_report;
    }

}
