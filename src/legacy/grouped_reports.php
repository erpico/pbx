<?php

namespace Erpico;

class Grouped_reports {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getAgent_reports($filter, $pos, $count = 20, $onlycount = 0) {
    
    // Here need to add permission checkers and filters

    $utils = new Utils();
    $t1 = date("2018-10-25 15:27:50");
    $t2 = date("2018-10-27 15:27:50");

    if ($onlycount) {      
      $res = $this->db->query("SELECT COUNT(*) FROM queue_cdr");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $sql = "	SELECT 
                SUM(talktime),
                MAX(holdtime),
                GROUP_CONCAT(IF(reason = 'COMPLETEAGENT',agentname,NULL)), 
                GROUP_CONCAT(IF(reason = 'COMPLETECALLER',agentname,NULL)), 
                GROUP_CONCAT(IF(reason = 'TRANSFER',agentname,NULL)),
                count(IF(reason = 'ABANDON',1,NULL)),
                count(IF(reason = 'EXITEMPTY',1,NULL)), 
                count(IF(reason = 'EXITWITHTIMEOUT',1,NULL)), 
                count(IF(reason = 'EXITWITHKEY',1,NULL)), 
                count(IF(reason = 'SYSCOMPAT',1,NULL)),
                count(IF(LENGTH(agentname),1,NULL)), 
                count(IF(reason = 'RINGNOANSWER',1,NULL)), 
                GROUP_CONCAT(DISTINCT agentname)
            FROM queue_cdr 
            WHERE !outgoing ";

//  Time settings
/*
    if(isset($filter['t1']) && isset($filter['t2'])) $sql = $sql."
        AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql = $sql."
        AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";
*/
    $sql = $sql." AND calldate>'".$t1."' AND calldate<'".$t2."' ";

//  Filters
/*
      if($filter['filter'] == 2) $sql.= "
				AND (reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') ";
      else if($filter['filter'] == 3) $sql.= "
				AND (reason = 'ABANDON' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTKEY' OR reason = 'RINGNOANSWER')";
      if(isset($filter['queue']) && $filter['queue']!=0) $sql.= " AND queue=(SELECT name FROM queue WHERE id=".$filter['queue'].") ";
*/

    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues($que);
    $sql.= $queues;
    $sql.= " GROUP BY uniqid ";

//  Limits
/*
      if ($count) {
        $sql .= " LIMIT $pos, $count";
      }
*/
    $result = $this->db->query($sql);
    $num = 0;
    $cdr_report = [];

    while($calls = $result->fetch(\PDO::FETCH_BOTH)) {
      $cdr_report[$num] = $calls;
      $num++;
    };
    $unknown_calls = 0;
    $unknown_abandon = 0;
    $unknown_exitwithtimeout = 0;
    $unknown_exitwithkey = 0;
    $unknown_exitempty = 0;
    $unknown_syscompat = 0;
    $total_serviced_calls_other_agents = 0;

    $total_calls = 0;
    $data_list = [];

    for($i=0; $i<$num; $i++) {
        //CA
      if($cdr_report[$i][2]) {
        $x = $cdr_report[$i][2];
        $total_calls++;
        //$data_list[$x][0]=$x;
        $data_list[$x][3]++;
        $data_list[$x][33] = $x;
        $data_list[$x][1]+=$cdr_report[$i][0];
        $data_list[$x][2]+=$cdr_report[$i][1];
        //max talktime
        if($cdr_report[$i][0] > $data_list[$x][12]) $data_list[$x][12]=$cdr_report[$i][0];
        //max holdtime
        if($cdr_report[$i][1] > $data_list[$x][11]) $data_list[$x][11]=$cdr_report[$i][1];
        //min talktime
        if(!isset($data_list[$x][14])) $data_list[$x][14]=$cdr_report[$i][0];
        elseif($cdr_report[$i][0] < $data_list[$x][14]) $data_list[$x][14]=$cdr_report[$i][0];
        //min holdtime
        if(!isset($data_list[$x][13])) $data_list[$x][13]=$cdr_report[$i][1];
        elseif($cdr_report[$i][1] < $data_list[$x][13]) $data_list[$x][13]=$cdr_report[$i][1];
        //CC
      } elseif($cdr_report[$i][3]) {
        $x = $cdr_report[$i][3];
        $total_calls++;
        //$data_list[$x][0]=$x;
        $data_list[$x][4]++;
        $data_list[$x][33] = $x;
        $data_list[$x][1]+=$cdr_report[$i][0];
        $data_list[$x][2]+=$cdr_report[$i][1];
        //max talktime
        if($cdr_report[$i][0] > $data_list[$x][12]) $data_list[$x][12]=$cdr_report[$i][0];
        //max holdtime
        if($cdr_report[$i][1] > $data_list[$x][11]) $data_list[$x][11]=$cdr_report[$i][1];
        //min talktime
        if(!isset($data_list[$x][14])) $data_list[$x][14]=$cdr_report[$i][0];
        elseif($cdr_report[$i][0] < $data_list[$x][14]) $data_list[$x][14]=$cdr_report[$i][0];
        //min holdtime
        if(!isset($data_list[$x][13])) $data_list[$x][13]=$cdr_report[$i][1];
        elseif($cdr_report[$i][1] < $data_list[$x][13]) $data_list[$x][13]=$cdr_report[$i][1];
        //TR
      } elseif($cdr_report[$i][4]) {
        $x = $cdr_report[$i][4];
        $total_calls++;
        //$data_list[$x][0]=$x;
        $data_list[$x][5]++;
        $data_list[$x][33] = $x;
        $data_list[$x][1]+=$cdr_report[$i][0];
        $data_list[$x][2]+=$cdr_report[$i][1];
        //max talktime
        if($cdr_report[$i][0] > $data_list[$x][12]) $data_list[$x][12]=$cdr_report[$i][0];
        //max holdtime
        if($cdr_report[$i][1] > $data_list[$x][11]) $data_list[$x][11]=$cdr_report[$i][1];
        //min talktime
        if(!isset($data_list[$x][14])) $data_list[$x][14]=$cdr_report[$i][0];
        elseif($cdr_report[$i][0] < $data_list[$x][14]) $data_list[$x][14]=$cdr_report[$i][0];
        //min holdtime
        if(!isset($data_list[$x][13])) $data_list[$x][13]=$cdr_report[$i][1];
        elseif($cdr_report[$i][1] < $data_list[$x][13]) $data_list[$x][13]=$cdr_report[$i][1];
        //AB
      } elseif($cdr_report[$i][5]) {
        //RNA
          if($cdr_report[$i][11]) {
            $total_calls++;
            $as = explode(",",$cdr_report[$i][12]);
            foreach($as as $x) {
                if(!$x) continue;
                //$data_list[$x][0]=$x;
                $data_list[$x][6]++;
                $data_list[$x][33] = $x;
                $data_list[$x][2]+=$cdr_report[$i][1];
                //max holdtime
                if($cdr_report[$i][1] > $data_list[$x][11]) $data_list[$x][11]=$cdr_report[$i][1];
                //min holdtime
                if(!isset($data_list[$x][13])) $data_list[$x][13]=$cdr_report[$i][1];
                elseif($cdr_report[$i][1] < $data_list[$x][13]) $data_list[$x][13]=$cdr_report[$i][1];
            }
            //Unknown call
          } else{
            $unknown_calls++;
            $unknown_abandon++;
          }
        //EE
      } elseif($cdr_report[$i][6]) {
        //RNA
          if($cdr_report[$i][11]) {
            $total_calls++;
            $as = explode(",",$cdr_report[$i][12]);
            foreach($as as $x) {
                if(!$x) continue;
                //$data_list[$x][0]=$x;
                $data_list[$x][7]++;
                $data_list[$x][33] = $x;
                $data_list[$x][2]+=$cdr_report[$i][1];
                //max holdtime
                if($cdr_report[$i][1] > $data_list[$x][11]) $data_list[$x][11]=$cdr_report[$i][1];
                //min holdtime
                if(!isset($data_list[$x][13])) $data_list[$x][13]=$cdr_report[$i][1];
                elseif($cdr_report[$i][1] < $data_list[$x][13]) $data_list[$x][13]=$cdr_report[$i][1];
            }
            //Unknown call
          } else{
            $unknown_calls++;
            $unknown_exitempty++;
          }
        //ET
       }elseif($cdr_report[$i][7]) {
        //RNA
        if($cdr_report[$i][11]) {
            $total_calls++;
            $as = explode(",",$cdr_report[$i][12]);
            foreach($as as $x) {
                if(!$x) continue;
                //$data_list[$x][0]=$x;
                $data_list[$x][8]++;
                $data_list[$x][33] = $x;
                $data_list[$x][2]+=$cdr_report[$i][1];
                //max holdtime
                if($cdr_report[$i][1] > $data_list[$x][11]) $data_list[$x][11]=$cdr_report[$i][1];
                //min holdtime
                if(!isset($data_list[$x][13])) $data_list[$x][13]=$cdr_report[$i][1];
                elseif($cdr_report[$i][1] < $data_list[$x][13]) $data_list[$x][13]=$cdr_report[$i][1];
            }
            //Unknown call
          } else{
            $unknown_calls++;
            $unknown_exitwithtimeout++;
          }
        //EK
      } elseif($cdr_report[$i][8]) {
        //RNA
          if($cdr_report[$i][11]) {
            $total_calls++;
            $as = explode(",",$cdr_report[$i][12]);
            foreach($as as $x) {
                if(!$x) continue;
                //$data_list[$x][0]=$x;
                $data_list[$x][9]++;
                $data_list[$x][33] = $x;
                $data_list[$x][2]+=$cdr_report[$i][1];
                //max holdtime
                if($cdr_report[$i][1] > $data_list[$x][11]) $data_list[$x][11]=$cdr_report[$i][1];
                //min holdtime
                if(!isset($data_list[$x][13])) $data_list[$x][13]=$cdr_report[$i][1];
                elseif($cdr_report[$i][1] < $data_list[$x][13]) $data_list[$x][13]=$cdr_report[$i][1];
            }
            //Unknown call
          } else{
            $unknown_calls++;
            $unknown_exitwithkey++;
          }
        //SC
      } elseif($cdr_report[$i][9]) {
        //RNA
          if($cdr_report[$i][11]) {
            $total_calls++;
            $as = explode(",",$cdr_report[$i][12]);
            foreach($as as $x) {
                if(!$x) continue;
                //$data_list[$x][0]=$x;
                $data_list[$x][10]++;
                $data_list[$x][33] = $x;
                $data_list[$x][2]+=$cdr_report[$i][1];
                //max holdtime
                if($cdr_report[$i][1] > $data_list[$x][11]) $data_list[$x][11]=$cdr_report[$i][1];
                //min holdtime
                if(!isset($data_list[$x][13])) $data_list[$x][13]=$cdr_report[$i][1];
                elseif($cdr_report[$i][1] < $data_list[$x][13]) $data_list[$x][13]=$cdr_report[$i][1];
            }
            //Unknown call
          } else{
            $unknown_calls++;
            $unknown_syscompat++;
          }
        //All RNA
      } else {
        $total_serviced_calls_other_agents++;
      }
    };

    $count_data_list = count($data_list);
     $data_list_arr = [];
     $data_list_result = [];

    $data_list_arr[0] = reset($data_list);
     for($j=1; $j<$count_data_list; $j++) {
      $data_list_arr[$j] = next($data_list);
    };

    $total_served = "";
    $total_unserved = "";
    for($k=0; $k<$count_data_list; $k++) {
      $total_served+= $data_list_arr[$k]['3'] + $data_list_arr[$k]['4'] + $data_list_arr[$k]['5'];
      $total_unserved+= $data_list_arr[$k]['6'] + $data_list_arr[$k]['7'] + $data_list_arr[$k]['8'] + $data_list_arr[$k]['9'] + $data_list_arr[$k]['10'];
    };

    for($k=0; $k<$count_data_list; $k++) {
        $data_list_result[$k]['agent'] = $data_list_arr[$k]['33'];
        $result_agent = $this->db->query(" SELECT fullname FROM acl_user WHERE name='".$data_list_result[$k]['agent']."' LIMIT 1 ");
        $myrow_agent = $result_agent->fetch(\PDO::FETCH_ASSOC);
        $data_list_result[$k]['agent_name'] = $myrow_agent['fullname']!="" ? $myrow_agent['fullname'] : $data_list_result[$k]['agent'];

        $served_call = $data_list_arr[$k]['3'] + $data_list_arr[$k]['4'] + $data_list_arr[$k]['5'];
        $unserved_call = $data_list_arr[$k]['6'] + $data_list_arr[$k]['7'] + $data_list_arr[$k]['8'] + $data_list_arr[$k]['9'] + $data_list_arr[$k]['10'];
        $data_list_result[$k]['total_call'] = $served_call + $unserved_call;
        $data_list_result[$k]['served_call'] = $served_call;
        $data_list_result[$k]['unserved_call'] = $unserved_call;
        $data_list_result[$k]['served_call_per'] = round($served_call*100/$data_list_result[$k]['total_call'],1);
        $data_list_result[$k]['unserved_call_per'] = round($unserved_call*100/$data_list_result[$k]['total_call'],1);
        $data_list_result[$k]['unserved_call_chart'] = '
			<div style="float:right;width:120px;">
				<div class="chart_calls_default_left">'.$data_list_result[$k]['unserved_call'].' ('.$data_list_result[$k]['unserved_call_per'].'%)</div>
				<div class="chart_calls_unserved" style="width:'.$data_list_result[$k]['unserved_call_per'].'%;"></div>
			</div>
		';
        $data_list_result[$k]['served_call_chart'] = '
			<div style="float:left;width:120px;">
				<div class="chart_calls_default_right">'.$data_list_result[$k]['served_call'].' ('.$data_list_result[$k]['served_call_per'].'%)</div>
				<div class="chart_calls_served" style="width:'.$data_list_result[$k]['served_call_per'].'%;"></div>
			</div>
		';
        $data_list_result[$k]['chart_count_call2'] = $data_list_result[$k]['served_call_per']."% - ".$data_list_result[$k]['unserved_call_per']."%";

        $data_list_result[$k]['investment_served'] = round($served_call*100/$total_served, 1);
        $data_list_result[$k]['investment_unserved'] = round($unserved_call*100/$total_unserved, 1);

        $data_list_result[$k]['time_sum_talk'] = $utils->time_format($data_list_arr[$k]['1']);
        //$data_list_result[$k]['time_sum_talk'] = sprintf("%02d:%02d", intval($data_list_arr[$k]['1']/60), intval($data_list_arr[$k]['1']%60));
        $data_list_result[$k]['time_sum_hold'] = $utils->time_format($data_list_arr[$k]['2']);
        //$data_list_result[$k]['time_sum_hold'] = sprintf("%02d:%02d", intval($data_list_arr[$k]['2']/60), intval($data_list_arr[$k]['2']%60));
        $data_list_result[$k]['time_sum_hold_chart'] = '
			<div style="float:left;width:120px;">
				<div class="chart_calls_default_left">'.$data_list_result[$k]['time_sum_hold'].'</div>
				<div class="chart_calls_unserved" style="width:'.round(($data_list_arr[$k]['2']*100/($data_list_arr[$k]['1']+$data_list_arr[$k]['2'])),1).'%;"></div>
			</div>
		';
        $data_list_result[$k]['time_sum_talk_chart'] = '
			<div style="float:right;width:120px;">
				<div class="chart_calls_default_right">'.$data_list_result[$k]['time_sum_talk'].'</div>
				<div class="chart_calls_served" style="width:'.round(($data_list_arr[$k]['1']*100/($data_list_arr[$k]['1']+$data_list_arr[$k]['2'])),1).'%;"></div>
			</div>
		';
        $data_list_result[$k]['chart_time_call2'] = $data_list_result[$k]['time_sum_hold']." - ".$data_list_result[$k]['time_sum_talk'];
        $time_avg_talk = ($served_call!=0) ? $data_list_arr[$k]['1']/$served_call : 0;
        $data_list_result[$k]['time_avg_talk'] = $utils->time_format($time_avg_talk);
        //$data_list_result[$k]['time_avg_talk'] = sprintf("%02d:%02d", intval($time_avg_talk/60), intval($time_avg_talk%60));
        $time_avg_hold = ($served_call+$unserved_call!=0) ? $data_list_arr[$k]['2']/($served_call+$unserved_call) : 0;;
        $data_list_result[$k]['time_avg_hold'] = $utils->time_format($time_avg_hold);
        //$data_list_result[$k]['time_avg_hold'] = sprintf("%02d:%02d", intval($time_avg_hold/60), intval($time_avg_hold%60));
    };

    //$js_obj = json_encode($data_list_result);
    return $data_list_result;
  }


  public function getQueues_table($filter, $pos, $count = 20, $onlycount = 0) {

        // Here need to add permission checkers and filters

    $utils = new Utils();
    $t1 = date("2018-10-25 15:27:50");
    $t2 = date("2018-10-27 15:27:50");

    if ($onlycount) {
        $res = $this->db->query("SELECT COUNT(*) FROM queue_cdr");
        $row = $res->fetch(\PDO::FETCH_NUM);
        return intval($row[0]);
    }

    $sql = "	SELECT
					count(*),
					SUM(talktime),
					SUM(holdtime),
					count(IF(reason = 'COMPLETEAGENT',1,NULL)),
					count(IF(reason = 'COMPLETECALLER',1,NULL)),
					count(IF(reason = 'TRANSFER',1,NULL)),
					count(IF(reason = 'ABANDON',1,NULL)),
					count(IF(reason = 'EXITEMPTY',1,NULL)),
					count(IF(reason = 'EXITWITHTIMEOUT',1,NULL)),
					count(IF(reason = 'EXITWITHKEY',1,NULL)),
					count(IF(reason = 'SYSCOMPAT',1,NULL)),
					MAX(holdtime),
					MAX(talktime),
					MIN(holdtime),
					MIN(IF(talktime,talktime,NULL)),
					queue
				FROM queue_cdr
				WHERE reason != 'RINGNOANSWER' AND !outgoing ".$sql.$sql_agent;

//  Time settings
/*
    if(isset($filter['t1']) && isset($filter['t2'])) $sql = $sql."
				AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql = $sql."
				AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";
*/
    $sql = $sql." AND calldate>'".$t1."' AND calldate<'".$t2."' ";

//  Filters
/*
    if($filter['filter'] == 2) $sql.= "
				AND (reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') ";
    else if($filter['filter'] == 3) $sql.= "
				AND (reason = 'ABANDON' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTKEY') ";
    if(isset($filter['queue']) && $filter['queue']!=0) $sql.= " AND queue=(SELECT name FROM queue WHERE id=".$filter['queue'].") ";
*/
    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues($que);
    $sql.= $queues;
    $sql.= "	GROUP BY queue ORDER BY queue ";

    if ($count) {
        $sql .= " LIMIT $pos, $count";
    }

    $result = $this->db->query($sql);
    return $result;
  }

  public function getQueues_table_chart1($filter, $pos, $count = 20, $onlycount = 0) {
    $result = $this->getQueues_table($filter, $pos, $count = 20, $onlycount = 0);

    $cdr_report = [];
    $num = 0;
    $cdr_report_arr = [];

    while($list = $result->fetch(\PDO::FETCH_NUM)) {
      $cdr_report_arr[$num]['served_call'] = $list[3] + $list[4] + $list[5];
      $cdr_report_arr[$num]['unserved_call'] = $list[6] + $list[8] + $list[7] + $list[9] + $list[10];
      $cdr_report_arr[$num]['calls_total'] = $cdr_report_arr[$num]['served_call'] + $cdr_report_arr[$num]['unserved_call'];
      $cdr_report_arr[$num]['name_queue'] = $this->auth->fullname_queue($list[15]);
      $num++;
    };

    for($j=0; $j<$num; $j++) {
      $served = [
          'sales' => $cdr_report_arr[$j]['calls_total'],
          'month' => $cdr_report_arr[$j]['name_queue'],
          'color' => "#6699CC"
      ];
      $cdr_report[$j] = $served;
    };
    return $cdr_report;
  }

  public function getQueues_table_chart2($filter, $pos, $count = 20, $onlycount = 0) {
    $result = $this->getQueues_table($filter, $pos, $count = 20, $onlycount = 0);

    $cdr_report = [];
    $num = 0;
    $cdr_report_arr = [];

    while($list = $result->fetch(\PDO::FETCH_NUM)) {
      $cdr_report_arr[$num]['transfer_calls'] = $list[5];
      $cdr_report_arr[$num]['completeagent_calls'] = $list[3];
      $cdr_report_arr[$num]['completecaller_calls'] = $list[4];
      $cdr_report_arr[$num]['name_queue'] = $this->auth->fullname_queue($list[15]);
      $num++;
    };

    for($j=0; $j<$num; $j++) {
      $served = [
          'sales' => $cdr_report_arr[$j]['transfer_calls'],
          'sales2' => $cdr_report_arr[$j]['completeagent_calls'],
          'sales3' => $cdr_report_arr[$j]['completecaller_calls'],
          'month' => $cdr_report_arr[$j]['name_queue']
      ];
      $cdr_report[$j] = $served;
    };
    return $cdr_report;
  }

  public function getQueues_table_chart3($filter, $pos, $count = 20, $onlycount = 0) {
    $result = $this->getQueues_table($filter, $pos, $count = 20, $onlycount = 0);

    $cdr_report = [];
    $num = 0;
    $cdr_report_arr = [];

    while($list = $result->fetch(\PDO::FETCH_NUM)) {
      $cdr_report_arr[$num]['abandon_calls'] = $list[6];
      $cdr_report_arr[$num]['exitwithtimeout_calls'] = $list[8];
      $cdr_report_arr[$num]['exitwithkey_calls'] = $list[9];
      $cdr_report_arr[$num]['exitempty_calls'] = $list[7];
      $cdr_report_arr[$num]['name_queue'] = $this->auth->fullname_queue($list[15]);
      $num++;
    };

    for($j=0; $j<$num; $j++) {
      $served = [
          'sales' => $cdr_report_arr[$j]['abandon_calls'],
          'sales2' => $cdr_report_arr[$j]['exitwithtimeout_calls'],
          'sales3' => $cdr_report_arr[$j]['exitwithkey_calls'],
          'sales4' => $cdr_report_arr[$j]['exitempty_calls'],
          'month' => $cdr_report_arr[$j]['name_queue']
      ];
      $cdr_report[$j] = $served;
    };
    return $cdr_report;
  }

  public function getQueues_table_chart4($filter, $pos, $count = 20, $onlycount = 0) {
    $result = $this->getQueues_table($filter, $pos, $count = 20, $onlycount = 0);

    $cdr_report = [];
    $num = 0;
    $cdr_report_arr = [];

    while($list = $result->fetch(\PDO::FETCH_NUM)) {
      $cdr_report[$num]['served_call'] = $list[3] + $list[4] + $list[5];
      $cdr_report[$num]['calls_total'] = $cdr_report[$num]['served_call'];
      $cdr_report[$num]['sum_talktime'] = $list[1];

      $cdr_report_arr[$num]['max'] = $list[12];
      $cdr_report_arr[$num]['min'] = $list[14];
      $cdr_report_arr[$num]['avg'] = $cdr_report[$num]['calls_total'] ? round($cdr_report[$num]['sum_talktime']/$cdr_report[$num]['calls_total']) : 0;
      $cdr_report_arr[$num]['name_queue'] = $this->auth->fullname_queue($list[15]);
      $num++;
    };

    for($j=0; $j<$num; $j++) {
      $served = [
          'sales' => $cdr_report_arr[$j]['min'],
          'sales2' => $cdr_report_arr[$j]['avg'],
          'sales3' => $cdr_report_arr[$j]['max'],
          'month' => $cdr_report_arr[$j]['name_queue']
      ];
      $cdr_report[$j] = $served;
    };
    return $cdr_report;
  }

  public function getQueues_table_chart5($filter, $pos, $count = 20, $onlycount = 0) {
    $result = $this->getQueues_table($filter, $pos, $count = 20, $onlycount = 0);

    $cdr_report = [];
    $num = 0;
    $cdr_report_arr = [];

    while($list = $result->fetch(\PDO::FETCH_NUM)) {
      $cdr_report[$num]['served_call'] = $list[3] + $list[4] + $list[5];
      $cdr_report[$num]['unserved_call'] = $list[6] + $list[8] + $list[7] + $list[9] + $list[10];
      $cdr_report[$num]['calls_total'] = $cdr_report[$num]['served_call'] + $cdr_report[$num]['unserved_call'];
      $cdr_report[$num]['sum_holdtime'] = $list[2];

      $cdr_report_arr[$num]['min'] = $list[13];
      $cdr_report_arr[$num]['max'] = $list[11];
      $cdr_report_arr[$num]['avg'] = $cdr_report[$num]['calls_total'] ? round($cdr_report[$num]['sum_holdtime']/$cdr_report[$num]['calls_total']) : 0;
      $cdr_report_arr[$num]['name_queue'] = $this->auth->fullname_queue($list[15]);
      $num++;
    };

    for($j=0; $j<$num; $j++) {
      $served = [
          'sales' => $cdr_report_arr[$j]['min'],
          'sales2' => $cdr_report_arr[$j]['avg'],
          'sales3' => $cdr_report_arr[$j]['max'],
          'month' => $cdr_report_arr[$j]['name_queue']
      ];
      $cdr_report[$j] = $served;
    };
    return $cdr_report;
  }

  public function getQueues_table_total($filter, $pos, $count = 20, $onlycount = 0) {
    $result = $this->getQueues_table($filter, $pos, $count = 20, $onlycount = 0);

    $utils = new Utils();
    $cdr_report = [];
    $num = 0;
    $cdr_report_arr = [];

    while($list = $result->fetch(\PDO::FETCH_NUM)) {
      $cdr_report[$num]['name_queue'] = $this->auth->fullname_queue($list[15]);
      $cdr_report[$num]['served_call'] = $list[3] + $list[4] + $list[5];
      $cdr_report[$num]['unserved_call'] = $list[6] + $list[8] + $list[7] + $list[9] + $list[10];
      $cdr_report[$num]['calls_total'] = $cdr_report[$num]['served_call'] + $cdr_report[$num]['unserved_call'];
      $cdr_report[$num]['served_call_per'] = round($cdr_report[$num]['served_call']*100/$cdr_report[$num]['calls_total'], 1);
      $cdr_report[$num]['unserved_call_per'] = round($cdr_report[$num]['unserved_call']*100/$cdr_report[$num]['calls_total'], 1);
      $cdr_report[$num]['unserved_call_chart'] = '
            <div style="float:right;width:120px;">
                <div class="chart_calls_default_left">'.$cdr_report[$num]['unserved_call'].' ('.$cdr_report[$num]['unserved_call_per'].'%)</div>
                <div class="chart_calls_unserved" style="width:'.$cdr_report[$num]['unserved_call_per'].'%;"></div>
            </div>
        ';
      $cdr_report[$num]['served_call_chart'] = '
            <div style="float:left;width:120px;">
                <div class="chart_calls_default_right">'.$cdr_report[$num]['served_call'].' ('.$cdr_report[$num]['served_call_per'].'%)</div>
                <div class="chart_calls_served" style="width:'.$cdr_report[$num]['served_call_per'].'%;"></div>
            </div>
        ';
      $cdr_report[$num]['chart_count_call2'] = $cdr_report[$num]['served_call_per']."% - ".$cdr_report[$num]['unserved_call_per']."%";
      $cdr_report[$num]['sum_talktime'] = $utils->time_format($list[1]);
      //$cdr_report[$num]['sum_talktime'] = sprintf("%02d:%02d:%02d", intval($list[1]/3600), intval(($list[1]%3600)/60), intval($list[1]%60));
      $cdr_report[$num]['sum_holdtime'] = $utils->time_format($list[2]);
      //$cdr_report[$num]['sum_holdtime'] = sprintf("%02d:%02d:%02d", intval($list[2]/3600), intval(($list[2]%3600)/60), intval($list[2]%60));
      $cdr_report[$num]['sum_holdtime_chart'] = '
            <div style="float:left;width:120px;">
                <div class="chart_calls_default_left">'.$cdr_report[$num]['sum_holdtime'].'</div>
                <div class="chart_calls_unserved" style="width:'.round(($list[2]*100/($list[1]+$list[2])),1).'%;"></div>
            </div>
        ';
      $cdr_report[$num]['sum_talktime_chart'] = '
            <div style="float:right;width:120px;">
                <div class="chart_calls_default_right">'.$cdr_report[$num]['sum_talktime'].'</div>
                <div class="chart_calls_served" style="width:'.round(($list[1]*100/($list[1]+$list[2])),1).'%;"></div>
            </div>
        ';
      $cdr_report[$num]['chart_call_time2'] = $cdr_report[$num]['sum_holdtime']." - ".$cdr_report[$num]['sum_talktime'];
      $num++;
    };
    return $cdr_report;
  }


  public function getQueues_name($filter, $pos, $count = 0, $onlycount = 0) {
    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM queue");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $sql = "SELECT id,fullname as value FROM queue";
    if ($count) {
      $sql .= " LIMIT $pos, $count";
    }

    $result = $this->db->query($sql);
    $res = [];
    $res[] = ['id' => 0, 'value' => 'All'];
    while($row = $result->fetch(\PDO::FETCH_ASSOC)) {
      if (intval($row['id'])) {
        $res[] = $row;
      }        
    };
    return $res;
  }


  public function getGrouped_reports_total($filter, $pos, $count = 20, $onlycount = 0) {

    $utils = new Utils();

    if ($onlycount) {
        $res = $this->db->query("SELECT COUNT(*) FROM queue_cdr");
        $row = $res->fetch(\PDO::FETCH_NUM);
        return intval($row[0]);
    }

    $sql = "    SELECT
					count(*),
					SUM(talktime) AS sum_talktime,
					SUM(holdtime) AS sum_holdtime,
					count(IF(reason = 'COMPLETEAGENT',1,NULL)) AS completeagent_calls,
					count(IF(reason = 'COMPLETECALLER',1,NULL)) AS completecaller_calls,
					count(IF(reason = 'TRANSFER',1,NULL)) AS transfer_calls,
					count(IF(reason = 'ABANDON',1,NULL)) AS abandon_calls,
					count(IF(reason = 'EXITEMPTY',1,NULL)) AS exitempty_calls,
					count(IF(reason = 'EXITWITHTIMEOUT',1,NULL)) AS exitwithtimeout_calls,
					count(IF(reason = 'EXITWITHKEY',1,NULL)) AS exitwithkey_calls,
					count(IF(reason = 'SYSCOMPAT',1,NULL)) AS syscompat_calls,
					MAX(holdtime) AS max_holdtime,
					MAX(talktime) AS max_talktime,
					MIN(holdtime) AS min_holdtime,
					MIN(IF(talktime,talktime,NULL)) AS min_talktime,
					count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND holdtime BETWEEN 0 AND 15,1,NULL)) AS total_answered_calls_15,
					count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND holdtime BETWEEN 16 AND 30,1,NULL)) AS total_answered_calls_30,
					count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND holdtime BETWEEN 31 AND 45,1,NULL)) AS total_answered_calls_45,
					count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND holdtime BETWEEN 46 AND 60,1,NULL)) AS total_answered_calls_60,
					count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND holdtime BETWEEN 61 AND 75,1,NULL)) AS total_answered_calls_75,
					count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND holdtime BETWEEN 76 AND 90,1,NULL)) AS total_answered_calls_90,
					count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') AND holdtime > 90,1,NULL)) AS total_answered_calls_over90   ";

    $sql.= "	FROM queue_cdr WHERE reason != 'RINGNOANSWER' AND !outgoing ";

//  Time settings
    if(isset($filter['t1']) && isset($filter['t2'])) $sql = $sql."
				AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql = $sql."
				AND calldate>'".date("Y-m-d H:i:s",time()-86400)."' AND calldate<'".date("Y-m-d H:i:s")."' ";//UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 86400 ";

    if($filter['filter'] == 2) $sql.= "
				AND (reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') ";
    else if($filter['filter'] == 3) $sql.= "
				AND (reason = 'ABANDON' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTKEY') ";
    if(isset($filter['queue']) && $filter['queue']!=0) $sql.= " AND queue=(SELECT name FROM queue WHERE id=".$filter['queue'].") ";
    
    $que = $this->auth->allowed_queues();
    $queues = $utils->sql_allowed_queues($que);
    $sql.= $queues;

    if ($count) {
        $sql .= " LIMIT $pos, $count";
    }

    $result = $this->db->query($sql);
    $cdr_report = [];
    $cdr_report = $result->fetch(\PDO::FETCH_ASSOC);


    $cdr_report['total_notserviced_calls'] = $cdr_report['abandon_calls'] + $cdr_report['exitwithtimeout_calls'] + $cdr_report['exitempty_calls'] + $cdr_report['exitwithkey_calls'] + $cdr_report['syscompat_calls'];
    $cdr_report['total_serviced_calls'] = $cdr_report['completeagent_calls'] + $cdr_report['completecaller_calls'] + $cdr_report['transfer_calls'];
    $cdr_report['total_calls'] = $cdr_report['total_serviced_calls'] + $cdr_report['total_notserviced_calls'];
    $cdr_report['avg_holdtime'] = $cdr_report['total_calls'] ? $cdr_report['sum_holdtime']/$cdr_report['total_calls'] : 0;
    $cdr_report['avg_talktime'] = $cdr_report['total_serviced_calls'] ? $cdr_report['sum_talktime']/$cdr_report['total_serviced_calls'] : 0;

    return $cdr_report;
  }

  public function getGrouped_reports_total_chart1($filter, $pos, $count = 20, $onlycount = 0) {
    $cdr_report = [];
    $cdr_report = $this->getGrouped_reports_total($filter, $pos, $count = 20, $onlycount = 0);

    $served = [
        'sales' => $cdr_report['total_serviced_calls'],
        'month' => "Обслуженные",
        'color' => "#6699CC"
    ];
    $unserved = [
        'sales' => $cdr_report['total_notserviced_calls'],
        'month' => "Необслуженные",
        'color' => "#FFCC33"
    ];
    $total = [
        'sales' => $cdr_report['total_calls'],
        'month' => "Всего звонков",
        'color' => "#33CC99"
    ];
    $calls = [$served,$unserved,$total];
    return $calls;
  }

  public function getGrouped_reports_total_chart2($filter, $pos, $count = 20, $onlycount = 0) {
    $cdr_report = [];
    $cdr_report = $this->getGrouped_reports_total($filter, $pos, $count = 20, $onlycount = 0);

    $talk_time = [
      'sales' => round($cdr_report['sum_talktime']/60),
      'month' => "Время разговора",
      'color' => "#FFCC33"
    ];
    $hold_time = [
      'sales' => round($cdr_report['sum_holdtime']/60),
      'month' => "Время ожидания",
      'color' => "#33CC99"
    ];
    $calls = [$talk_time,$hold_time];
    return $calls;
  }

  public function getGrouped_reports_total_chart3($filter, $pos, $count = 20, $onlycount = 0) {
    $cdr_report = [];
    $cdr_report = $this->getGrouped_reports_total($filter, $pos, $count = 20, $onlycount = 0);

    $served_agent = [
      'sales' => $cdr_report['completeagent_calls'],
      'month' => "Агент",
      'color' => "#FF3333"
    ];
    $served_client = [
      'sales' => $cdr_report['completecaller_calls'],
      'month' => "Клиент",
      'color' => "#3333CC"
    ];
    $served_transfer = [
      'sales' => $cdr_report['transfer_calls'],
      'month' => "Трансфер",
      'color' => "#339900"
    ];
    $calls = [$served_agent,$served_client,$served_transfer];
    return $calls;
  }

  public function getGrouped_reports_total_chart4($filter, $pos, $count = 20, $onlycount = 0) {
    $cdr_report = [];
    $cdr_report = $this->getGrouped_reports_total($filter, $pos, $count = 20, $onlycount = 0);

    $unserved_abandon = [
      'sales' => $cdr_report['abandon_calls'],
      'month' => "Прерванные клиентом",
      'color' => "#6699CC"
    ];
    $unserved_exit_with_timeout = [
      'sales' => $cdr_report['exitwithtimeout_calls'],
      'month' => "Таймаут вызова клиента",
      'color' => "#777777"
    ];
    $unserved_exit_with_tkey = [
      'sales' => $cdr_report['exitwithkey_calls'],
      'month' => "По нажатию кнопки клиентом",
      'color' => "#999999"
    ];
    $unserved_exit_empty = [
      'sales' => $cdr_report['exitempty_calls'],
      'month' => "По причине пустой очереди",
      'color' => "#777777"
    ];
    $unserved_ring_no_answer = [
      'sales' => $cdr_report['syscompat_calls'],
      'month' => "Несовместимость каналов",
      'color' => "#999999"
    ];
    $calls = [$unserved_abandon,$unserved_exit_with_timeout,$unserved_exit_with_tkey,$unserved_exit_empty,$unserved_ring_no_answer];
    return $calls;
  }

  public function getGrouped_reports_total_chart5($filter, $pos, $count = 20, $onlycount = 0) {
    $cdr_report = [];
    $cdr_report = $this->getGrouped_reports_total($filter, $pos, $count = 20, $onlycount = 0);

    $time_call_min = [
      'sales' => $cdr_report['min_talktime'],
      'month' => "Минимум",
      'color' => "#66CCFF"
    ];
    $time_call_average = [
      'sales' => round($cdr_report['avg_talktime']),
      'month' => "Среднее",
      'color' => "#CCFF66"
    ];
    $time_call_max = [
      'sales' => $cdr_report['max_talktime'],
      'month' => "Максимум",
      'color' => "#FF6666"
    ];
    $calls = [$time_call_min,$time_call_average,$time_call_max];
    return $calls;
  }

  public function getGrouped_reports_total_chart6($filter, $pos, $count = 20, $onlycount = 0) {
    $cdr_report = [];
    $cdr_report = $this->getGrouped_reports_total($filter, $pos, $count = 20, $onlycount = 0);

    $time_hold_min = [
      'sales' => $cdr_report['min_holdtime'],
      'month' => "Минимум",
      'color' => "#66CCFF"
    ];
    $time_hold_average = [
      'sales' => round($cdr_report['avg_holdtime']),
      'month' => "Среднее",
      'color' => "#CCFF66"
    ];
    $time_hold_max = [
      'sales' => $cdr_report['max_holdtime'],
      'month' => "Максимум",
      'color' => "#FF6666"
    ];
    $calls = [$time_hold_min,$time_hold_average,$time_hold_max];
    return $calls;
  }

  public function getGrouped_reports_total_chart7($filter, $pos, $count = 20, $onlycount = 0) {
    $cdr_report = [];
    $cdr_report = $this->getGrouped_reports_total($filter, $pos, $count = 20, $onlycount = 0);

    $hold15 = [
      'sales' => $cdr_report['total_answered_calls_15'],
      'month' => "0-15 сек.",
      'color' => "#99CCFF"
    ];
    $hold30 = [
      'sales' => $cdr_report['total_answered_calls_30'],
      'month' => "16-30 сек.",
      'color' => "#FFFF99"
    ];
    $hold45 = [
      'sales' => $cdr_report['total_answered_calls_45'],
      'month' => "31-45 сек.",
      'color' => "#66CC66"
    ];
    $hold60 = [
      'sales' => $cdr_report['total_answered_calls_60'],
      'month' => "46-60 сек.",
      'color' => "#FF9900"
    ];
    $hold75 = [
      'sales' => $cdr_report['total_answered_calls_75'],
      'month' => "61-75 сек.",
      'color' => "#009999"
    ];
    $hold90 = [
      'sales' => $cdr_report['total_answered_calls_90'],
      'month' => "76-90 сек.",
      'color' => "#FF6633"
    ];
    $hold91 = [
      'sales' => $cdr_report['total_answered_calls_over90'],
      'month' => "Больше 90 сек.",
      'color' => "#9966CC"
    ];
    $calls = [$hold15,$hold30,$hold45,$hold60,$hold75,$hold90,$hold91];
    return $calls;
  }
}