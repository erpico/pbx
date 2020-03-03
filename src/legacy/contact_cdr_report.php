<?php

namespace Erpico;

class Contact_cdr_report {
  private $container;
  private $db;
  private $auth;

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getPlainContact_cdr_report($filter, $pos, $count = 20, $stat = 0) {

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

    if(is_array($filter) && isset($filter['src']) && strlen($filter['src'])) {
      $wsql = $wsql."	AND src LIKE '%".addslashes($filter['src'])."%' ";
    }
/*
    if ($stat) {
      $sql = "SELECT COUNT(*) AS total,
          SUM(billsec) AS sum_billsec, SUM(IF(disposition = 'ANSWERED',duration,0)) AS sum_duration,
          count(IF(disposition = 'ANSWERED',1,NULL)) AS count_answered,
          sum(IF(disposition = 'ANSWERED', (duration-billsec),0)) AS sum_answered FROM cdr";
      if (strlen($wsql)) {
          $sql .= " WHERE 1=1 $wsql";
      }
      $result_cdr = $this->db->query($sql);
      $cdr = $result_cdr->fetch(\PDO::FETCH_ASSOC);
      return $cdr;
    }
*/
    $utils = new Utils();

    $sql = "	SELECT 
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
			LEFT JOIN acl_user AS C ON (A.agentname=C.name)
			WHERE 1=1 $wsql";

// Filters
/*
    if(isset($request['filter'])) {
	if($request['filter']==2) $sql = $sql."
			AND (reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND !outgoing ";
	else if($request['filter']==3) $sql = $sql."
			AND (reason = 'ABANDON' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTKEY' OR reason = 'RINGNOANSWER') AND !outgoing ";
	else if($request['filter']==4) $sql = $sql."
			AND outgoing=1 ";
	else $sql = $sql."
			AND !outgoing ";
    };
*/

    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues_for_records($que);
    $sql.= $queues;

    $sql = $sql." ORDER BY calldate DESC";

    if ($count) {
      $sql .= " LIMIT $pos, $count";
    }

    $cdr_report = [];
    $res = $this->db->query($sql);
    $i = -1;
    while($row = $res->fetch(\PDO::FETCH_ASSOC)) {
        $i++;
        $cdr_report[$i] = $row;
    };

    for($j=0; $j<=$i; $j++) {
        if ($cdr_report[$j]['agentname']) {
            $cdr_report[$j]['agent'] = $cdr_report[$j]['agentname'];
        } else if ($cdr_report[$j]['agentid']) {
            $cdr_report[$j]['agent'] = $cdr_report[$j]['agentid'];
        } else if ($cdr_report[$j]['agentdev']) {
            $cdr_report[$j]['agent'] = $cdr_report[$j]['agentdev'];
        };
        $cdr_report[$j]['agent'] = $cdr_report[$j]['fullname_agent'] != "" ? $cdr_report[$j]['fullname_agent'] : $cdr_report[$j]['agent'];
        $cdr_report[$j]['queue'] = $cdr_report[$j]['fullname_queue'] != "" ? $cdr_report[$j]['fullname_queue'] : $cdr_report[$j]['queue'];
        $cdr_report[$j]['holdtime'] = $utils->time_format($cdr_report[$j]['holdtime']);
        $cdr_report[$j]['talktime'] = $utils->time_format($cdr_report[$j]['talktime']);
        $cdr_report[$j]['ringtime'] = $utils->time_format($cdr_report[$j]['ringtime']);
        $cdr_report[$j]['position'] = $cdr_report[$j]['origposition']."/".$cdr_report[$j]['position'];
        $cdr_report[$j]['status'] = $cdr_report[$j]['reason'];
        $cdr_report[$j]['calldate2'] = date('d.m.Y H:i:s',strtotime($cdr_report[$j]['calldate']));
    };
/*
    if (strpos($cdr_report[$j]['channel'], "Local/s@mail_dummy") !== FALSE) {
      // get from
      $mailid = substr($cdr_report[$j]['src'], 6);
      $mysql = "SELECT `from` FROM mail_messages WHERE id = '$mailid'";
      $res = $this->db->query($mysql);
      if ($res) {
          $row = $res->fetch(\PDO::FETCH_BOTH);
          if ($row) {
              $cdr_report[$j]['src'] = $row[0];
          }
      }
    }
*/
    return $cdr_report;
  }
}