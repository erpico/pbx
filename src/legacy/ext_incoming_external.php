<?php

namespace Erpico;

class Ext_incoming_external {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getExt_incoming_external_total($filter, $pos, $count = 20, $onlycount = 0) {
    
    $utils = new Utils();



    $sql = "SELECT 
					count(*) AS total,
					SUM(talktime),
					SUM(holdtime),
					count(IF(reason = 'COMPLETEAGENT',1,NULL)),
					count(IF(reason = 'COMPLETECALLER',1,NULL)),
					count(IF(reason = 'TRANSFER',1,NULL)) AS transfer,
					count(IF(reason = 'ABANDON',1,NULL)) AS abandon,
					count(IF(reason = 'EXITEMPTY',1,NULL)) AS exitempty,
					count(IF(reason = 'EXITWITHTIMEOUT',1,NULL)) AS exittimeout,
					count(IF(reason = 'EXITWITHKEY',1,NULL)),
					count(IF(reason = 'SYSCOMPAT',1,NULL)),
					count(IF(reason = 'RINGNOANSWER',1,NULL)) AS ringnoanswer,
					MAX(holdtime) AS max_holdtime,
					MAX(talktime),
					MIN(holdtime) AS min_holdtime,
					MIN(IF(talktime,talktime,NULL)), 
					AVG(IF(talktime>0,ringtime,NULL)) AS avg_answer,										
					AVG(talktime) AS avg_talktime,					
					AVG(holdtime) AS avg_holdtime,					
					queue,
					count(distinct IF(agentname='',NULL,agentname)) AS agents,
					SUM(IF(holdtime < 20 AND talktime > 0,1,0)) AS sl_cnt,
          count(distinct IF(talktime>0,src,NULL)) AS cnt_unique_src";

    if (isset($filter['queue']) && strlen($filter['queue'])) {
      $sql .= ", agentname ";
    }
    if ($onlycount) {
      $sql = "SELECT COUNT(*) ";
    }
    $sql .= " FROM queue_cdr WHERE (1=1) ";


    if(isset($filter['t1']) && isset($filter['t2'])) {
      $sql = $sql." AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    } else {
      // $sql = $sql." AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";
    }

    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues($que);
    $sql.= $queues;

    if (!isset($filter['queue'])) {
      $sql.= "AND reason != 'RINGNOANSWER' AND !outgoing	GROUP BY queue ORDER BY queue ";
    } else {
      $sql.= "AND queue = '{$filter['queue']}' AND agentname != '' GROUP BY agentname ORDER BY agentname ";
    }

    if ($count) {
        $sql .= " LIMIT $pos, $count";
    }
    
    

    $result = [];
    if ($onlycount) {
      $second_sql = "SELECT COUNT(*) FROM (".$sql.") a";
      $res = $this->db->query($second_sql);
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $res = $this->db->query($sql);

    while($list = $res->fetch(\PDO::FETCH_BOTH)) {
      $cdr_report = [];
      $cdr_report['agents'] = $list['agents'];
      $cdr_report['queue'] = $list['queue'];
      $cdr_report['queue_name'] = $this->auth->fullname_queue($list['queue']);

      $cdr_report['avg_answer'] = $utils->time_format($list['avg_answer']);
      $cdr_report['avg_duration'] = $utils->time_format($list['avg_talktime']);
      $cdr_report['avg_wait'] = $utils->time_format($list['avg_holdtime']);
      $cdr_report['max_wait'] = $utils->time_format($list['max_holdtime']);
      $cdr_report['min_wait'] = $utils->time_format($list['min_holdtime']);

      $cdr_report['served'] = $list[3] + $list[4] + $list[5];
      $cdr_report['unserved'] = $list[6] + $list[8] + $list[7] + $list[9] + $list[10] + $list['ringnoanswer'];

      $total = $cdr_report['served'] + $cdr_report['unserved'];
      $lcr = $list['abandon'] + $list['exitempty'] + $list['exittimeout'];
      $cdr_report['lcr'] = $lcr." (".round($lcr/$total*100)."%)";
      $cdr_report['sl'] = $list['sl_cnt']." (".round($list['sl_cnt']/$total*100)."%)";

      $cdr_report['nwh'] = $list['exitempty'];
      $cdr_report['total'] = $list[0];

      $cdr_report['cnt_unique_src'] = $list['cnt_unique_src'];

      $cdr_report['rcr'] = ($cdr_report['served']-$list['cnt_unique_src'])." (".round(($cdr_report['served']-$list['cnt_unique_src'])/$cdr_report['served']*100)."%)";
      $cdr_report['fcr'] = $list['cnt_unique_src']." (".round($list['cnt_unique_src']/$cdr_report['served']*100)."%)";

      $cdr_report['agent'] = $list['agentname'];
      $cdr_report['agent_name'] = $this->auth->fullname_agent($list['agentname']);

      $cdr_report['transfer'] = $list['transfer']." (".round($list['transfer']/$cdr_report['served']*100)."%)";

      $cdr_report['sum_talktime'] = $utils->time_format($list[1]);

      $cdr_report['sum_holdtime'] = $utils->time_format($list[2]);
      $result[] = $cdr_report;
    };

    return $result;
  }

  public function getExt_incoming_external_personal($filter, $pos, $count = 20, $onlycount = 0) {

    $utils = new Utils();
    $queue = $this->getExt_incoming_external_total($filter, $pos, $count = 20, $onlycount = 0)[0]['queue'];

    if ($onlycount) {
        $res = $this->db->query("SELECT COUNT(*) FROM queue_cdr");
        $row = $res->fetch(\PDO::FETCH_NUM);
        return intval($row[0]);
    }

    $sql = "SELECT 
                count(*) AS total,
                SUM(talktime),
                SUM(holdtime),
                count(IF(reason = 'COMPLETEAGENT',1,NULL)),
                count(IF(reason = 'COMPLETECALLER',1,NULL)),
                count(IF(reason = 'TRANSFER',1,NULL)) AS transfer,
                count(IF(reason = 'ABANDON',1,NULL)) AS abandon,
                count(IF(reason = 'EXITEMPTY',1,NULL)) AS exitempty,
                count(IF(reason = 'EXITWITHTIMEOUT',1,NULL)) AS exittimeout,
                count(IF(reason = 'EXITWITHKEY',1,NULL)),
                count(IF(reason = 'SYSCOMPAT',1,NULL)),
                count(IF(reason = 'RINGNOANSWER',1,NULL)) AS ringnoanswer,
                MAX(holdtime) AS max_holdtime,
                MAX(talktime),
                MIN(holdtime) AS min_holdtime,
                MIN(IF(talktime,talktime,NULL)), 
                AVG(IF(talktime>0,ringtime,NULL)) AS avg_answer,										
                AVG(talktime) AS avg_talktime,					
                AVG(holdtime) AS avg_holdtime,					
                queue,
                count(distinct IF(agentname='',NULL,agentname)) AS agents,
                SUM(IF(holdtime < 20 AND talktime > 0,1,0)) AS sl_cnt,
                count(distinct IF(talktime>0,src,NULL)) AS cnt_unique_src,
                agentname
            FROM queue_cdr 
            WHERE (1=1) ";

    if(isset($filter['t1']) && isset($filter['t2'])) {
      $sql = $sql." AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    } else {
      $sql = $sql." AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";
    }

    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues($que);
    $sql.= $queues;

    if (!isset($filter['queue'])) {
      $sql.= "AND reason != 'RINGNOANSWER' AND !outgoing	GROUP BY queue ORDER BY queue ";
    } else {
        $sql.= "AND queue = '{$filter['queue']}' AND agentname != '' GROUP BY agentname ORDER BY agentname ";
    }    

    if ($count) {
        $sql .= " LIMIT $pos, $count";
    }

    $res = $this->db->query($sql);
    
    $result = [];
    $num = 0;

    while($list = $res->fetch(\PDO::FETCH_BOTH)) {
        $cdr_report = [];
        $cdr_report['agents'] = $list['agents'];
        $cdr_report['queue'] = $list['queue'];
        $cdr_report['queue_name'] = $this->auth->fullname_queue($list['queue']);

        $cdr_report['avg_answer'] = $utils->time_format($list['avg_answer']);
        $cdr_report['avg_duration'] = $utils->time_format($list['avg_talktime']);
        $cdr_report['avg_wait'] = $utils->time_format($list['avg_holdtime']);
        $cdr_report['max_wait'] = $utils->time_format($list['max_holdtime']);
        $cdr_report['min_wait'] = $utils->time_format($list['min_holdtime']);

        $cdr_report['served'] = $list[3] + $list[4] + $list[5];
        $cdr_report['unserved'] = $list[6] + $list[8] + $list[7] + $list[9] + $list[10] + $list['ringnoanswer'];

        $total = $cdr_report['served'] + $cdr_report['unserved'];
        $lcr = $list['abandon'] + $list['exitempty'] + $list['exittimeout'];
        $cdr_report['lcr'] = $lcr." (".round($lcr/$total*100)."%)";
        $cdr_report['sl'] = $list['sl_cnt']." (".round($list['sl_cnt']/$total*100)."%)";

        $cdr_report['nwh'] = $list['exitempty'];
        $cdr_report['total'] = $total;

        $cdr_report['cnt_unique_src'] = $list['cnt_unique_src'];


        $cdr_report['rcr'] = ($cdr_report['served']-$list['cnt_unique_src'])." (".round(($cdr_report['served']-$list['cnt_unique_src'])/$cdr_report['served']*100)."%)";
        $cdr_report['fcr'] = $list['cnt_unique_src']." (".round($list['cnt_unique_src']/$cdr_report['served']*100)."%)";

        $cdr_report['agent'] = $list['agentname'];
        $cdr_report['agent_name'] = $this->auth->fullname_agent($list['agentname']);

        $cdr_report['transfer'] = $list['transfer']." (".round($list['transfer']/$cdr_report['served']*100)."%)";

        $cdr_report['sum_talktime'] = $utils->time_format($list[1]);

        $cdr_report['sum_holdtime'] = $utils->time_format($list[2]);

        array_push($result, $cdr_report);
    };

    return $result;
  }

}