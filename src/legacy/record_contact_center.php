<?php

namespace Erpico;

class Record_contact_center {
  private $container;
  private $db;
  private $auth;

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getRecord_contact_center($filter, $pos, $count = 20, $stat = 0) {

      $wsql = "";

      if (is_array($filter) && isset($filter['calldate']) && strlen($filter['calldate'])) {
          $dates = json_decode($filter['calldate'], 1);
          if ($dates['start']) {
              $d = strtotime($dates['start']);
              $wsql .= "AND calldate >= '".date("Y-m-d 00:00:00", $d)."' ";
          }
          if ($dates['end']) {
              $d = strtotime($dates['end']);
              $wsql .= "AND calldate <= '".date("Y-m-d 23:59:59", $d)."' ";
          }
      }

    $utils = new Utils();

    $sql = "	SELECT 
					A.id AS id,
					A.calldate AS calldate,
					A.queue AS queue,
					A.agentid AS agentid,
					A.agentname AS agentname,
					A.agentdev AS agentdev,
					A.agentcalls AS agentcalls,
					A.talktime AS talktime,
					A.callerid AS callerid,
					A.src AS src,
					A.reason AS reason,
					A.record_file AS record_file,
					A.exten AS exten,
					B.fullname AS fullname_queue,
					C.fullname AS fullname_agent
				FROM queue_cdr AS A
				LEFT JOIN queue AS B ON (A.queue=B.`name`)
 				LEFT JOIN acl_user AS C ON (A.agentname=C.`name`)
 				WHERE 1=1 $wsql";

    $sql.= "	AND !outgoing AND LENGTH(record_file) ";

    $deny_num = $this->auth->deny_numbers();
    $deny = $utils->sql_deny_numbers_for_records($deny_num);
    $sql .= $deny;

    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues_for_records($que);
    $sql .= $queues;

    $sql .= "	ORDER BY calldate DESC ";

    if ($count) {
      $sql .= " LIMIT $pos, $count";
    }

    $call_recording = [];
    $res = $this->db->query($sql);
    $i = -1;
    while($row = $res->fetch(\PDO::FETCH_ASSOC)) {
        $i++;
        $call_recording[$i]["download"] = '<i class="fa fa-download" style="color:#666666;margin-top:10px;"></i>';
        $call_recording[$i]['calldate'] = $row['calldate'];
        $call_recording[$i]['calldate2'] = date('d.m.Y H:i:s',strtotime($row['calldate']));
        $call_recording[$i]['name'] = str_replace(".wav49",".WAV",substr($row['record_file'], 49));
        $call_recording[$i]['queue'] = $row['fullname_queue']!=null ? $row['fullname_queue'] : $row['queue'];
        $call_recording[$i]['src'] = $row['src'];
        $call_recording[$i]['dst'] = $row['fullname_agent']!="" ? $row['fullname_agent'] : $row['agentname'];
        $call_recording[$i]['duration'] = $utils->time_format($row['talktime']);
    };

    return $call_recording;
  }
}