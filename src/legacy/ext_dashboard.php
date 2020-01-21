<?php

namespace Erpico;

class Ext_dashboard {
  private $container;
  private $db;
  private $auth;

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function calcTotalWorktime($time1,$time2) {

    $utils = new Utils();

    $demand = "	SELECT
                *,
                UNIX_TIMESTAMP(time) as tm
                FROM queue_log ";

    if(isset($time1) && isset($time2)) $demand.= "
      WHERE DATE_FORMAT(time,'%Y-%m-%d %H:%m:%s')>'".date("Y-m-d H:i:s",strtotime($time1))."' AND DATE_FORMAT(time,'%Y-%m-%d %H:%m:%s')<'".date("Y-m-d H:i:s",strtotime($time2))."' ";
    else $demand.= "
      WHERE DATE_FORMAT(time,'%Y-%m-%d %H:%m:%s')>'".date("Y-m-d H:i:s",time()-86400)."'";

    $que = $this->auth->allowed_queues();
    $queues_log = $utils->sql_allowed_queues_n($que);
    $demand.= $queues_log;

    $demand.= "	AND event in ('PAUSE','UNPAUSE','ADDMEMBER','REMOVEMEMBER','PAUSEALL') 
    ORDER BY time ASC ";
    
    //var_dump($demand);
    $result = $this->db->query($demand);

    $qagents = [];
    $interfaces = [];

    while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
        $agent = $row['agent'];
        $queue = $row['queuename'];
        $time = $row['tm'];

        switch ($row['event']) {
            case 'ADDMEMBER':
            {
                $ifc = $agent;
                $agent = '';
                $interfaces[$queue][$ifc] = [
                    'ifc' => $ifc,
                    'time' => $time,
                    'state' => $row['event']
                ];
                if (strlen($row['data2'])) {
                    $agent = $row['data2'];
                    $qagents[$agent][$queue]['last_login'] = $time;
                    $qagents[$agent][$queue]['logins'][] = $time;
                    $qagents[$agent][$queue]['ifc'] = $ifc;

                    if (!isset($qagents[$agent][$queue]['first_login'])) $qagents[$agent][$queue]['first_login'] = $time;
                }
            }
                break;
            case 'PAUSEALL': // Not interesting event for now
                break;
            case 'PAUSE':
            {
                //User login or pause
                $reason = $row['data1'];
                //Check current state
                if (isset($qagents[$agent]) && isset($qagents[$agent][$queue]) && isset($qagents[$agent][$queue]['state'])) {
                    switch ($qagents[$agent][$queue]['state']) {
                        case 'PAUSE':
                        {
                            if ($qagents[$agent][$queue]['last_pause_reason'] == 'afterwork') {
                                $qagents[$agent][$queue]['afterwork_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                            }
                            else if ($qagents[$agent][$queue]['last_pause_reason'] == 'lunch') {
                                $qagents[$agent][$queue]['lunch_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                            }
                            else if ($qagents[$agent][$queue]['last_pause_reason'] == 'rest') {
                                $qagents[$agent][$queue]['rest_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                            }
                            else if ($qagents[$agent][$queue]['last_pause_reason'] == 'meeting') {
                                $qagents[$agent][$queue]['meeting_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                            }
                            else if ($qagents[$agent][$queue]['last_pause_reason'] == 'tasks') {
                                $qagents[$agent][$queue]['tasks_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                            }
                            else {
                                $qagents[$agent][$queue]['notready_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                            }
                            $qagents[$agent][$queue]['pauses'][] = [
                                'time' => $qagents[$agent][$queue]['last_pause'],
                                'reason' => $qagents[$agent][$queue]['last_pause_reason'],
                                'duration' => $time - $qagents[$agent][$queue]['last_pause']
                            ];
                        }
                            break;
                        case 'UNPAUSE':
                            if (/*$qagents[$agent][$queue]['last_login'] != 0 && */$qagents[$agent][$queue]['last_unpause'] != 0) {
                                //$qagents[$agent][$queue]['total_work_time'] += $time - $qagents[$agent][$queue]['last_unpause'];
                                $qagents[$agent][$queue]['work_time'] += $time - $qagents[$agent][$queue]['last_unpause'];
                                //print "Yo-".date('d.m.Y H:i:s',$time)."-".$time."-".$qagents[$agent][$queue]['last_unpause']."-".($time - $qagents[$agent][$queue]['last_unpause'])//print_r($qagents[$agent][$queue],1)."<br>";

                                $qagents[$agent][$queue]['works'][] =[
                                    'time' => $qagents[$agent][$queue]['last_unpause'],
                                    'duration' => $time - $qagents[$agent][$queue]['last_unpause']
                                ];
                            }
                            break;
                        case 'REMOVEMEMBER':
                            break;
                    }
                }
                $qagents[$agent][$queue]['last_pause_reason'] = $reason;
                $qagents[$agent][$queue]['last_pause'] = $time;
                if (!isset($qagents[$agent][$queue]['ifc']) || $qagents[$agent][$queue]['ifc'] == '') {
                    // New login or event for user, who we don't know when logined
                    $queue_ifcs = $interfaces[$queue];
                    $mintime_ifc = [];
                    if(isset($queue_ifcs)) foreach ($queue_ifcs as $ifc) {
                        if (!isset($mintime_ifc['time']) || ($time - $ifc['time'] < $time - $mintime_ifc['time'])) {
                            $mintime_ifc = $ifc;
                        }
                    }

                    if (isset($mintime_ifc['time']) && ($time - $mintime_ifc['time'] < 2)) {
                        // We found addmember with no more 1 sec time diff. Hope it's we.
                        $qagents[$agent][$queue]['last_login'] = $mintime_ifc['time'];
                        $qagents[$agent][$queue]['logins'][] = $mintime_ifc['time'];
                        $qagents[$agent][$queue]['ifc'] = $mintime_ifc['ifc'];

                        if (!isset($qagents[$agent][$queue]['first_login'])) $qagents[$agent][$queue]['first_login'] = $mintime_ifc['time'];
                    }
                }
                $qagents[$agent][$queue]['state'] = $row['event'];
            }
                break;
            case 'UNPAUSE':
            {
                // Start working
                if (/*$qagents[$agent][$queue]['last_login'] != 0 && */$qagents[$agent][$queue]['state'] != 'UNPAUSE') $qagents[$agent][$queue]['last_unpause'] = $time;
                if (/*$qagents[$agent][$queue]['last_login'] != 0 && */$qagents[$agent][$queue]['last_pause'] != 0) {
                    if ($qagents[$agent][$queue]['state'] == 'PAUSE' && isset($qagents[$agent][$queue]['last_pause_reason'])) {
                        //$qagents[$agent][$queue]['last_unpause'] = $time;
                        if ($qagents[$agent][$queue]['last_pause_reason'] == 'afterwork') {
                            $qagents[$agent][$queue]['afterwork_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                        }
                        else if ($qagents[$agent][$queue]['last_pause_reason'] == 'lunch') {
                            $qagents[$agent][$queue]['lunch_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                        }
                        else if ($qagents[$agent][$queue]['last_pause_reason'] == 'rest') {
                            $qagents[$agent][$queue]['rest_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                        }
                        else if ($qagents[$agent][$queue]['last_pause_reason'] == 'meeting') {
                            $qagents[$agent][$queue]['meeting_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                        }
                        else if ($qagents[$agent][$queue]['last_pause_reason'] == 'tasks') {
                            $qagents[$agent][$queue]['tasks_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                        }
                        else  {
                            $qagents[$agent][$queue]['notready_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                        }
                        $qagents[$agent][$queue]['pauses'][] = [
                            'time' => $qagents[$agent][$queue]['last_pause'],
                            'reason' => $qagents[$agent][$queue]['last_pause_reason'],
                            'duration' => $time - $qagents[$agent][$queue]['last_pause']
                        ];
                    }
                }
                $qagents[$agent][$queue]['state'] = $row['event'];
            }
                break;
            case 'REMOVEMEMBER':
            {
                // User logount
                $ifc = $agent;
                $agent = '';
                // Search for agent
                foreach ($qagents as &$c) {
                    if ($c[$queue]['ifc'] == $ifc) {
                        // Got it!
                        if (isset($c[$queue]) && isset($c[$queue]['state'])) {
                            switch ($c[$queue]['state']) {
                                case 'PAUSE':
                                    if ($c[$queue]['last_pause_reason'] == 'afterwork') {
                                        $c[$queue]['afterwork_time'] += $time - $c[$queue]['last_pause'];
                                    }
                                    else if ($c[$queue]['last_pause_reason'] == 'rest') {
                                        $c[$queue]['rest_time'] += $time - $c[$queue]['last_pause'];
                                    }
                                    else if ($c[$queue]['last_pause_reason'] == 'lunch') {
                                        $c[$queue]['lunch_time'] += $time - $c[$queue]['last_pause'];
                                    }
                                    else if ($c[$queue]['last_pause_reason'] == 'meeting') {
                                        $c[$queue]['meeting_time'] += $time - $c[$queue]['last_pause'];
                                    }
                                    else if ($c[$queue]['last_pause_reason'] == 'tasks') {
                                        $c[$queue]['tasks_time'] += $time - $c[$queue]['last_pause'];
                                    }
                                    else{
                                        $c[$queue]['notready_time'] += $time - $c[$queue]['last_pause'];
                                    }
                                    $c[$queue]['pauses'][] = [
                                        'time' => $c[$queue]['last_pause'],
                                        'reason' => $c[$queue]['last_pause_reason'],
                                        'duration' => $time - $c[$queue]['last_pause']
                                    ];
                                    break;
                                case 'UNPAUSE':
                                    //$c[$queue]['total_work_time'] += $time - $c[$queue]['last_unpause'];
                                    if ($c[$queue]['last_unpause'] == 0) {
                                        $c[$queue]['last_unpause'] = strtotime($filter['t1']);
                                    }
                                    $c[$queue]['work_time'] += $time - $c[$queue]['last_unpause'];

                                    $c[$queue]['works'][] = [
                                        'time' => $c[$queue]['last_unpause'],
                                        'duration' => $time - $c[$queue]['last_unpause']
                                    ];
                                    break;
                            }
                        }

                        $c[$queue]['last_logoff'] = $time;
                        if (isset($c[$queue]['last_login'])) {
                            $c[$queue]['total_login_time'] += $time - $c[$queue]['last_login'];
                            $session = [
                                "time" => $c[$queue]['last_login'],
                                "duration" => $time - $c[$queue]['last_login'],
                                "logout" => $time,
                                "work_time" => $c[$queue]['work_time'],
                                "afterwork_time" => $c[$queue]['afterwork_time'],
                                "rest_time" => $c[$queue]['rest_time'],
                                "lunch_time" => $c[$queue]['lunch_time'],
                                "meeting_time" => $c[$queue]['meeting_time'],
                                "tasks_time" => $c[$queue]['tasks_time'],
                                "notready_time" => $c[$queue]['notready_time']
                            ];
                            $session['works'] = $c[$queue]['works'];
                            $session['pauses'] = $c[$queue]['pauses'];
                            $c[$queue]['sessions'][] = $session;
                            $c[$queue]['total_work_time'] += $c[$queue]['work_time'];
                            $c[$queue]['total_afterwork_time'] += $c[$queue]['afterwork_time'];
                            $c[$queue]['total_lunch_time'] += $c[$queue]['lunch_time'];
                            $c[$queue]['total_rest_time'] += $c[$queue]['rest_time'];
                            $c[$queue]['total_meeting_time'] += $c[$queue]['meeting_time'];
                            $c[$queue]['total_tasks_time'] += $c[$queue]['tasks_time'];
                            $c[$queue]['total_notready_time'] += $c[$queue]['notready_time'];
                            $c[$queue]['work_time'] = 0;
                            $c[$queue]['afterwork_time'] = 0;
                            $c[$queue]['lunch_time'] = 0;
                            $c[$queue]['rest_time'] = 0;
                            $c[$queue]['meeting_time'] = 0;
                            $c[$queue]['notready_time'] = 0;
                            $c[$queue]['works'] = [];
                            $c[$queue]['pauses'] = [];
                        }
                        $c[$queue]['state'] = $row['event'];
                        $c[$queue]['last_pause'] = 0;
                        $c[$queue]['last_unpause'] = 0;
                        $c[$queue]['ifc'] = '';
                    }
                }
            }
                break;
        }
    }

    //header("Content-type: text/plain");
    //print_r($qagents);

    $qagentEvents = [];
    foreach($qagents as $name => $agentQueues) {
        if (isset($userEvents)) unset($userEvents);
        $userEvents = [];
        foreach($agentQueues as $queue => $agentQueue) {
            if ($agentQueue['state'] != 'REMOVEMEMBER') {
                // user does not logout , use search end time as logout time
//              $time = strtotime($filter['t2']);
                $time = strtotime($time2);
                if ($time > time()) $time = time();
                $agentQueue['last_logoff'] = $time;

                //print_r($agentQueue);
                //die();

                if (!isset($agentQueue['last_login'])) $agentQueue['last_login'] = strtotime($filter['t1']);
                switch ($agentQueue['state']) {
                    case 'PAUSE':
                        if ($agentQueue['last_pause_reason'] == 'afterwork') {
                            $agentQueue['afterwork_time'] += $time - $agentQueue['last_pause'];
                        }
                        else if ($agentQueue['last_pause_reason'] == 'rest') {
                            $agentQueue['rest_time'] += $time - $agentQueue['last_pause'];
                        }
                        else if ($agentQueue['last_pause_reason'] == 'lunch') {
                            $agentQueue['lunch_time'] += $time - $agentQueue['last_pause'];
                        }
                        else if ($agentQueue['last_pause_reason'] == 'meeting') {
                            $agentQueue['meeting_time'] += $time - $agentQueue['last_pause'];
                        }
                        else if ($agentQueue['last_pause_reason'] == 'tasks') {
                            $agentQueue['tasks_time'] += $time - $agentQueue['last_pause'];
                        }
                        else{
                            $agentQueue['notready_time'] += $time - $agentQueue['last_pause'];
                        }
                        $agentQueue['pauses'][] = [
                            'time' => $agentQueue['last_pause'],
                            'reason' => $agentQueue['last_pause_reason'],
                            'duration' => $time - $agentQueue['last_pause']
                        ];
                        break;
                    case 'UNPAUSE':
                        //$c[$queue]['total_work_time'] += $time - $c[$queue]['last_unpause'];
                        if ($agentQueue['last_unpause'] == 0) {
                            $agentQueue['last_unpause'] = $agentQueue['last_login'];
                        }
                        $agentQueue['work_time'] += $time - $agentQueue['last_unpause'];

                        $agentQueue['works'][] = [
                            'time' => $agentQueue['last_unpause'],
                            'duration' => $time - $agentQueue['last_unpause']
                        ];
                        break;
                }

                $agentQueue['total_login_time'] += $time - $agentQueue['last_login'];
                $session = [
                    "time" => $agentQueue['last_login'],
                    "duration" => $time - $agentQueue['last_login'],
                    "logout" => $time,
                    "work_time" => $agentQueue['work_time'],
                    "afterwork_time" => $agentQueue['afterwork_time'],
                    "rest_time" => $agentQueue['rest_time'],
                    "lunch_time" => $agentQueue['lunch_time'],
                    "meeting_time" => $agentQueue['meeting_time'],
                    "tasks_time" => $agentQueue['tasks_time'],
                    "notready_time" => $agentQueue['notready_time']
                ];
                $session['works'] = $agentQueue['works'];
                $session['pauses'] = $agentQueue['pauses'];
                $agentQueue['sessions'][] = $session;
                $agentQueue['total_work_time'] += $agentQueue['work_time'];
                $agentQueue['total_afterwork_time'] += $agentQueue['afterwork_time'];
                $agentQueue['total_lunch_time'] += $agentQueue['lunch_time'];
                $agentQueue['total_rest_time'] += $agentQueue['rest_time'];
                $agentQueue['total_meeting_time'] += $agentQueue['meeting_time'];
                $agentQueue['total_tasks_time'] += $agentQueue['tasks_time'];
                $agentQueue['total_notready_time'] += $agentQueue['notready_time'];
            }

            if(isset($agentQueue['sessions'])) foreach($agentQueue['sessions'] as $event) {
                $k = $event['time']."_0";
                if (!isset($userEvents[$k])) $userEvents[$k] = $event;
                $userEvents[$k]['action'] = 'STARTWORK';
                if($userEvents[$k]['queues']==NULL) $userEvents[$k]['queues'][] = $queue; // <--makhmoody
                else if (!in_array($queue, $userEvents[$k]['queues'])) $userEvents[$k]['queues'][] = $queue;
                if ($event['logout']) {
                    $k = $event['logout']."_3";
                    if (!isset($userEvents[$k])) $userEvents[$k] = $event;
                    $userEvents[$k]['action'] = 'ENDWORK';
                    if($userEvents[$k]['queues']==NULL) $userEvents[$k]['queues'][] = $queue; // <--makhmoody
                    else if (!in_array($queue, $userEvents[$k]['queues'])) $userEvents[$k]['queues'][] = $queue;
                }
                foreach($event['pauses'] as $pause) {
                    $k = $pause['time']."_1";
                    if (!isset($userEvents[$k])) $userEvents[$k] = $pause;
                    $userEvents[$k]['duration'] = max($userEvents[$k]['duration'], $pause['duration']);
                    if($userEvents[$k]['queues']==NULL) $userEvents[$k]['queues'][] = $queue; // <--makhmoody
                    else if (!in_array($queue, $userEvents[$k]['queues'])) $userEvents[$k]['queues'][] = $queue;
                    $userEvents[$k]['action'] = 'PAUSE';
                }
                if (isset($event['works'])) /* <--makhmoody */ foreach($event['works'] as $event2) {
                    $k = $event2['time']."_2";
                    if (!isset($userEvents[$k])) $userEvents[$k] = $event2;
                    $event2[$k]['duration'] = max($userEvents[$k]['duration'], $event2['duration']);
                    if($userEvents[$k]['queues']==NULL) $userEvents[$k]['queues'][] = $queue; // <--makhmoody
                    else if (!in_array($queue, $userEvents[$k]['queues'])) $userEvents[$k]['queues'][] = $queue;
                    $userEvents[$k]['action'] = 'UNPAUSE';
                }
            }
        }
        ksort($userEvents);
        $qagentEvents[$name] = $userEvents;
    }

    //header("Content-type: text/plain");

    // Calculate total statistics for operators
    $qagentsTotal = [];

    foreach($qagentEvents as $name => $userEvents) {
        $total = [
            'worktime' => 0,
            'work_cnt' => 0,
            'notready_time' => 0,
            'notready_cnt' => 0,
            'afterwork_time' => 0,
            'afterwork_cnt' => 0,
            'lunch_time' => 0,
            'lunch_cnt' => 0,
            'rest_time' => 0,
            'rest_cnt' => 0,
            'meeting_time' => 0,
            'meeting_cnt' => 0,
            'tasks_time' => 0,
            'tasks_cnt' => 0,
            'first_login' => 0,
            'last_logoff' => 0,
            'sessions' => 0,
            'queues' => []];
        $curqueues = 0;
        $readyinqueues = 0;
        $t = 0;
        $cState = '';
        $pTime = 0;

        foreach ($userEvents as $etime => $event) {
            if ($cState == '') $pTime = $etime+0;
            switch ($event['action']) {
                case 'PAUSE': {
                    if ($cState == "work") {
                        $total['worktime'] += ($etime+0)-$pTime;
                        $total['work_cnt']++;
                    }
                    if ($cState == "notready") {
                        $total['notready_time'] += ($etime+0)-$pTime;//$event['duration'];
                        $total['notready_cnt']++;
                    }
                    else if ($cState == "afterwork") {
                        $total['afterwork_time'] += ($etime+0)-$pTime;//$event['duration'];
                        $total['afterwork_cnt']++;
                    } else if ($cState == "lunch") {
                        $total['lunch_time'] += ($etime+0)-$pTime;//$event['duration'];
                        $total['lunch_cnt']++;
                    }  else if ($cState == "rest") {
                        $total['rest_time'] += ($etime+0)-$pTime;//$event['duration'];
                        $total['rest_cnt']++;
                    } else if ($cState == "meeting") {
                        $total['meeting_time'] += ($etime+0)-$pTime;//$event['duration'];
                        $total['meeting_cnt']++;
                    } else if ($cState == "tasks") {
                        $total['tasks_time'] += ($etime+0)-$pTime;//$event['duration'];
                        $total['tasks_cnt']++;
                    }

                    if ($event['reason'] == "afterwork") $cState = "afterwork";
                    else if ($event['reason'] == "rest") $cState = "rest";
                    else if ($event['reason'] == "meeting") $cState = "meeting";
                    else if ($event['reason'] == "lunch") $cState = "lunch";
                    else $cState = "notready";
                    $pTime = $etime+0;

                    $readyinqueues -= count($event['queues']);
                    if ($readyinqueues < 0) $readyinqueues = 0;
                    break;
                }
                case 'UNPAUSE': {
                    if ($cState != "work") {
                        if ($cState == "notready") {
                            $total['notready_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['notready_cnt']++;
                        }
                        else if ($cState == "afterwork") {
                            $total['afterwork_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['afterwork_cnt']++;
                        } else if ($cState == "lunch") {
                            $total['lunch_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['lunch_cnt']++;
                        }  else if ($cState == "rest") {
                            $total['rest_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['rest_cnt']++;
                        }  else if ($cState == "meeting") {
                            $total['meeting_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['meeting_cnt']++;
                        }  else if ($cState == "tasks") {
                            $total['tasks_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['tasks_cnt']++;
                        }
                        $pTime = $etime+0;
                        $cState = "work";
                    }

                    $readyinqueues += count($event['queues']);
                    break;
                }
                case 'STARTWORK': {
                    //print "Начало смены";
                    $total['sessions']++;
                    if ($total['first_login'] == 0) $total['first_login'] = $etime+0;
                    foreach ($event['queues'] as $queue) if (!in_array($queue, $total['queues'])) array_push($total['queues'], $queue);
                    $t = $etime+0;
                    if ($curqueues == 0) $pTime = $etime + 0;
                    $curqueues += count($event['queues']);

                    /*$total['afterwork_time'] += $event['afterwork_time'];
                    $total['notready_time'] += $event['notready_time'];
                    $total['lunch_time'] += $event['lunch_time'];
                    $total['rest_time'] += $event['rest_time'];
                    $total['meeting_time'] += $event['meeting_time'];
                    $total['worktime'] += $event['worktime'];

                    $total['notready_cnt'] += count($event['works']);
                    $total['work_cnt'] += count($event['pauses']);*/

                    break;
                }
                case 'ENDWORK': {
                    $total['last_logoff'] = $etime+0;
                    $curqueues -= count($event['queues']);
                    if ($curqueues == 0) {
                        if ($cState == "work") {
                            $total['worktime'] += ($etime+0)-$pTime;
                            $total['work_cnt']++;
                        }
                        else if ($cState == "notready") {
                            $total['notready_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['notready_cnt']++;
                        }
                        else if ($cState == "afterwork") {
                            $total['afterwork_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['afterwork_cnt']++;
                        }
                        else if ($cState == "lunch") {
                            $total['lunch_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['lunch_cnt']++;
                        }
                        else if ($cState == "rest") {
                            $total['rest_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['rest_cnt']++;
                        }
                        else if ($cState == "meeting") {
                            $total['meeting_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['meeting_cnt']++;
                        }
                        else if ($cState == "tasks") {
                            $total['tasks_time'] += ($etime+0)-$pTime;//$event['duration'];
                            $total['tasks_cnt']++;
                        }
                        $pTime = 0;
                        $cState = '';
                    }
                    break;
                }
            }
        }
        if ($total['sessions'] > 0) {
            $qagentsTotal[$name] = $total;
        }
    }
    return $qagentsTotal;
}


  public function getExt_dashboard_agentslist($filter, $pos = 0, $count = 0, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    // Here need to add permission checkers and filters

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM queue_agent");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $sql = "SELECT distinct acl_user.id, acl_user.fullname, acl_user.name, cfg_user_setting.val AS salary FROM queue_agent LEFT JOIN acl_user ON (acl_user.id = queue_agent.acl_user_id) LEFT JOIN cfg_user_setting ON (cfg_user_setting.handle = 'cc.salary' AND cfg_user_setting.acl_user_id = acl_user.id) ORDER BY fullname ASC";
    $arr = [];
    $res = $this->db->query($sql);
    while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
      if (!$row['id']) continue;
      if (isset($filter['user_id']) && $filter['user_id'] !=0 && $filter['user_id'] != $row['id']) continue;

      $arr[] = [
          "id" => $row['id'],
          "value" => (strlen($row['fullname']) ? $row['fullname'] : $row['name'])." (".$row['name'].")",
          "salary" => $row['salary']
      ];
    }
    return $arr;
  }


  public function getExt_dashboardfiltertimeopts($filter, $pos, $count = 20, $onlycount = 0) {

    $arr = [];
    for ($i = 0; $i < 24; $i++) {
      $arr[] = [
          "id" => $i,
          "value" => "$i:00-$i-59"
      ];
    }
    return $arr;
  }


  public function getExt_dashboard_saveAgentsSalary($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    // Here need to add permission checkers and filters

    $data = json_decode(filefilter_contents('php://input'), true);
    foreach ($data as $agent) {
      if (isset($agent['salary']) && isset($agent['id'])) {
          $sql = "REPLACE INTO cfg_user_setting SET handle = 'cc.salary', val = '".addslashes($agent['salary'])."', acl_user_id = '".addslashes($agent['id'])."'";
          $this->db->query($sql);
      }
    }
    return "OK";
  }


  public function add_where_operands($filter) {
    $utils = new Utils();
    $sql = "";
    if (isset($filter['queues']) && strlen($filter['queues'])) {
        $sql2 = "SELECT name FROM queue WHERE id IN (".addslashes($filter['queues']).")";
        $res = $this->db->query($sql2);
        $queues = [];
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $queues[] = "'".$row['name']."'";
        }
        $allowed = $utils->sql_allowed_queues($this->auth->allowed_queues());
        $queues = $utils->get_allowed_queues_from_filter($allowed, $queues);
        if (is_array($queues) && COUNT($queues) > 0) {
          $sql = " AND queue IN (".implode(',', $queues).") ";
        } else {						
          $sql = " AND 1 = 0"; // Fail result -should be none 
        }
    } else {
        $que = $this->auth->allowed_queues();
        $queues = $utils->sql_allowed_queues($que);
        $sql = $queues;
    }

    if (isset($filter['agents']) && strlen($filter['agents'])) {
        $sql2 = "SELECT name FROM acl_user WHERE id IN (".addslashes($filter['agents']).")";
        $res = $this->db->query($sql2);
        $agents = [];
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $agents[] = "'".$row['name']."'";
        }
        $sql .= " AND agentname IN (".implode(',', $agents).")";
    }
    $sql .= " ";
    return $sql;
  }


  public function getExt_dashboard_getgages($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    if ($onlycount) {
      $ssql = "COUNT(*)";
    } else {
      $ssql = "
      count(*) AS total,
      SUM(talktime) as talktime_sum,
      SUM(holdtime) as hold_sum,
      count(IF(reason = 'COMPLETEAGENT',1,NULL)) as COMPLETEAGENT_sum,
      count(IF(reason = 'COMPLETECALLER',1,NULL)) as COMPLETECALLER_sum,
      count(IF(reason = 'TRANSFER',1,NULL)) AS transfer,
      count(IF(reason = 'ABANDON',1,NULL)) AS abandon,
      count(IF(reason = 'EXITEMPTY',1,NULL)) AS exitempty,
      count(IF(reason = 'EXITWITHTIMEOUT',1,NULL)) AS exittimeout,
      count(IF(reason = 'EXITWITHKEY',1,NULL)) as EXITWITHKEY_sum,
      count(IF(reason = 'SYSCOMPAT',1,NULL)),
      count(IF(reason = 'RINGNOANSWER',1,NULL)) AS ringnoanswer,
      MAX(holdtime) AS max_holdtime,
      MAX(talktime) AS max_talktime,
      MIN(holdtime) AS min_holdtime,
      MIN(IF(talktime,talktime,NULL)), 
      AVG(IF(talktime>0,ringtime,NULL)) AS avg_answer,										
      AVG(talktime) AS avg_talktime,					
      AVG(holdtime) AS avg_holdtime,					
      MAX(IF(talktime>0,ringtime,NULL)) AS max_answer,										
      count(distinct IF(agentname='',NULL,agentname)) AS agents,
      SUM(IF(holdtime < queue.sl AND talktime > 0,1,0)) AS sl_cnt,
      count(distinct IF(talktime>0,src,NULL)) AS cnt_unique_src,
      GROUP_CONCAT(agentname SEPARATOR ',') AS agents_list,
      UNIX_TIMESTAMP(MIN(calldate)) AS first_day,
      UNIX_TIMESTAMP(MAX(calldate)) AS last_day ";
    }
    $sql = "SELECT ".$ssql." FROM queue_cdr LEFT JOIN queue ON (queue.name = queue_cdr.queue) WHERE reason != 'RINGNOANSWER' AND ";

    if (isset($filter['t1']) && isset($filter['t2'])) $sql = $sql." calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql = $sql." UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";

    $sql .= $this->add_where_operands($filter);
    $result = $this->db->query($sql);
    if ($onlycount) {
      $row = $result->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $gages = [];    

    if($row = $result->fetch(\PDO::FETCH_ASSOC)) {
      $served = $row['COMPLETEAGENT_sum'] + $row['COMPLETECALLER_sum'] + $row['transfer'];
      $unserved = $row['exitempty'] + $row['abandon'] + $row['exittimeout'] + $row['EXITWITHKEY_sum'] + $row['ringnoanswer'];

      $total = $served + $unserved;
      $lcr = $row['abandon'] + $row['exitempty'] + $row['exittimeout'];

      //print $;
      $gages['lcr'] = $total ? round($lcr/$total*100) : 0;
      $gages['sl'] = $total ? round($row['sl_cnt']/$total*100) : 0;
      $gages['aat'] = sprintf("%.2f", $row['avg_holdtime']);
      $gages['mat'] = $row['max_answer'];
      $gages['att'] = round($row['avg_talktime']);
      $gages['mtt'] = $row['max_talktime'];
      $gages['fcr'] = $served ? round($row['cnt_unique_src']/$served*100) : 0;


      $agents = explode(",",$row['agents_list']);
      $phones = [];
      $agents_q = [];
      foreach ($agents as $agent) {
          if (strlen($agent)) {
              $an = "'".addslashes($agent)."'";
              if (!in_array($an, $agents_q)) {
                  $agents_q[] = $an;
              }
          }
      }
      if (count($agents_q)) {
          $sql = "SELECT SUM(cfg_user_setting.val) AS salary FROM acl_user LEFT JOIN cfg_user_setting ON (cfg_user_setting.handle = 'cc.salary' AND cfg_user_setting.acl_user_id = acl_user.id) WHERE acl_user.name IN (".implode(",",$agents_q).")";
          $result = $this->db->query($sql);
          if($row2 = $result->fetch(\PDO::FETCH_ASSOC)) {
              $cost = ($row2['salary']/20)*(($row['last_day']-$row['first_day'])/86400);
              $gages['cost'] = round($cost / $served);
          }
      } else {
          $gages['cost'] = 0;
      }
    }

    return $gages;
  }


  public function getExt_dashboard_agentsstat($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $utils = new Utils();

    if ($onlycount) {
      $ssql = " * FROM( SELECT COUNT(*)  ";
    } else {
      $ssql =" * FROM (SELECT 
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
      count(distinct IF(agentname='',NULL,agentname)) AS agents,
      SUM(IF(holdtime < queue.sl AND talktime > 0,1,0)) AS sl_cnt,
      count(distinct IF(talktime>0,src,NULL)) AS cnt_unique_src,
      agentname,
      count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') and holdtime > queue.sl,1,NULL)) AS served_more20";
    }

    $sql = "SELECT ".$ssql." FROM queue_cdr LEFT JOIN queue ON (queue.name = queue_cdr.queue)
                WHERE (1=1) ".$sql.$sql_agent;

   if(isset($filter['t1']) && isset($filter['t2'])) $sql = $sql."
				AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
   else $sql = $sql."
				AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";

    $sql .= $this->add_where_operands($filter);

    
    $sql.= " AND agentname != '' GROUP BY agentname ORDER BY agentname LIMIT {$pos}, {$count}) a UNION (";

    if (!$onlycount) {
      $sql .= "SELECT 0 AS total, 0, 0, 0, 0, 0 AS transfer, 0 AS abandon, 0 AS exitempty, 0 AS exittimeout, 0, 0, 0 AS ringnoanswer, 0 AS max_holdtime, 0, 0 AS min_holdtime, 0, 0 AS avg_answer,0 AS avg_talktime,0 AS avg_holdtime,0 AS agents, 0 AS sl_cnt, 0 AS cnt_unique_src, acl_user.name AS agentname, 0 AS served_more20 from acl_user LEFT JOIN queue_agent ON (queue_agent.acl_user_id = acl_user.id) LEFT JOIN queue ON (queue.id = queue_agent.queue_id) WHERE (1=1) ";
    } else {
      $sql .= "SELECT COUNT(*) from acl_user LEFT JOIN queue_agent ON (queue_agent.acl_user_id = acl_user.id) LEFT JOIN queue ON (queue.id = queue_agent.queue_id) WHERE (1=1) ";      
    }
      $sql .= str_replace("agentname IN", "acl_user.name IN", $sql_agent);
      $sql .= str_replace("agentname IN", "acl_user.name IN", str_replace("queue IN", "queue.name IN", $this->add_where_operands($filter)));
      $sql .= " ORDER BY acl_user.name ASC LIMIT {$pos}, {$count})";
    
    if ($onlycount) {
      $result = $this->db->query($sql);
      $row = $result->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }
    $result = $this->db->query($sql);
    $cdr_report = [];

    while($list = $result->fetch(\PDO::FETCH_BOTH)) {
        $num = $list['agentname'];
        if (isset($cdr_report[$num])) continue;
        $cdr_report[$num]['agents'] = $list['agents'];

        $cdr_report[$num]['avg_answer'] = $utils->time_format($list['avg_answer']);
        $cdr_report[$num]['avg_duration'] = $utils->time_format($list['avg_talktime']);
        $cdr_report[$num]['avg_wait'] = $utils->time_format($list['avg_holdtime']);
        $cdr_report[$num]['max_wait'] = $utils->time_format($list['max_holdtime']);
        $cdr_report[$num]['min_wait'] = $utils->time_format($list['min_holdtime']);

        $cdr_report[$num]['served'] = $list[3] + $list[4] + $list[5];
        $cdr_report[$num]['unserved'] = $list[6] + $list[8] + $list[7] + $list[9] + $list[10] + $list['ringnoanswer'];

        $total = $cdr_report[$num]['served'] + $cdr_report[$num]['unserved'];
        $lcr = $cdr_report[$num]['unserved'];
        if ($total) {
            $cdr_report[$num]['lcr'] = $lcr." (".round($lcr/$total*100)."%)";
            $cdr_report[$num]['sl'] = $list['sl_cnt']." (".round($list['sl_cnt']/$total*100)."%)";
        }

        $cdr_report[$num]['nwh'] = $list['exitempty'];
        $cdr_report[$num]['total'] = $total;

        if ($total) {
            $cdr_report[$num]['served_call_per'] = round($cdr_report[$num]['served']*100/$cdr_report[$num]['total'], 1);
            $cdr_report[$num]['unserved_call_per'] = round($cdr_report[$num]['unserved']*100/$cdr_report[$num]['total'], 1);

            $cdr_report[$num]['rcr'] = ($cdr_report[$num]['served']-$list['cnt_unique_src'])." (".round(($cdr_report[$num]['served']-$list['cnt_unique_src'])/$cdr_report[$num]['served']*100)."%)";;
            $cdr_report[$num]['fcr'] = $list['cnt_unique_src']." (".round($list['cnt_unique_src']/$cdr_report[$num]['served']*100)."%)";
        }

        $cdr_report[$num]['agent'] = $list['agentname'];
        $cdr_report[$num]['agent_name'] = $this->auth->fullname_agent($list['agentname']);
        $cdr_report[$num]['agent_name_short'] = $this->auth->fullname_agent_short($list['agentname']);

        if ($cdr_report[$num]['served'] !== 0){
            $cdr_report[$num]['transfer'] = $list['transfer']." (".round($list['transfer']/$cdr_report[$num]['served']*100)."%)";
        } 

        $cdr_report[$num]['sum_talktime'] = $utils->time_format($list[1]);
        $cdr_report[$num]['sum_holdtime'] = $utils->time_format($list[2]);

        $cdr_report[$num]['served_more20s'] = $list['served_more20'];

        $cdr_report[$num]['avg_duration_sec'] = $list['avg_talktime'];

        // Get agent phone num
        $agentnum = $this->auth->getAgentPhone($list['agentname']);

        $cdr_report[$num]['agentnum'] = $agentnum;

        // Get outbound stats of agent
        $sql = "SELECT COUNT(id) AS cnt, AVG(billsec) AS avg_talktime FROM cdr WHERE src = '$agentnum' AND LENGTH(dst) > 4 AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
        $res2 = $this->db->query($sql);
        if ($row2 = $res2->fetch(\PDO::FETCH_ASSOC)) {
            $cdr_report[$num]['out_count'] = $row2['cnt'];
//  Added timeformat to out_avg_sec
            $cdr_report[$num]['out_avg_sec'] = $utils->time_format($row2['avg_talktime']);
            $cdr_report[$num]['out_avg'] = $utils->time_format($row2['avg_talktime']);
        }

        // Get internal stats of agent
        $sql = "SELECT COUNT(id) AS cnt, AVG(billsec) AS avg_talktime FROM cdr WHERE ((src = '$agentnum' AND LENGTH(dst) < 5)) AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
        $res2 = $this->db->query($sql);
        if ($row2 = $res2->fetch(\PDO::FETCH_ASSOC)) {
            $cdr_report[$num]['int_count'] = $row2['cnt'];
//  Added timeformat to int_avg_sec
            $cdr_report[$num]['int_avg_sec'] = $utils->time_format($row2['avg_talktime']);
            $cdr_report[$num]['int_avg'] = $utils->time_format($row2['avg_talktime']);
        }
        //$num++;
    };
    ksort($cdr_report);
    // Object to array
    $result = [];
    foreach ($cdr_report as $e) $result[] = $e;

    return $result;
  }


  public function getExt_dashboard_treetable($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $utils = new Utils();

    if ($onlycount) {
      $ssql = " count(*)";
    } else {
      $ssql = " count(*) AS total,
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
      MAX(talktime) AS max_talktime,
      MIN(holdtime) AS min_holdtime,
      MIN(IF(talktime,talktime,NULL)), 
      AVG(IF(talktime>0,ringtime,NULL)) AS avg_ans,										
      AVG(IF(talktime=0,ringtime,NULL)) AS avg_lost,										
      AVG(talktime) AS avg_talktime,					
      AVG(holdtime) AS avg_holdtime,					
      MAX(IF(talktime>0,ringtime,NULL)) AS max_answer,										
      count(distinct IF(agentname='',NULL,agentname)) AS agents,
      SUM(IF(holdtime < queue.sl AND talktime > 0,1,0)) AS sl_cnt,
      count(distinct IF(talktime>0,src,NULL)) AS cnt_unique_src,
      UNIX_TIMESTAMP(calldate) AS calltime,
      count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') and holdtime > queue.sl,1,NULL)) AS served_more20s,
      count(IF((reason = 'ABANDON' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITWITHKEY') and holdtime > queue.sl,1,NULL)) AS notserved_more20s,
      GROUP_CONCAT(agentname SEPARATOR ',') AS agents_list";
    }

    $sql = "SELECT ".$ssql." FROM queue_cdr LEFT JOIN queue ON (queue.name = queue_cdr.queue) WHERE reason != 'RINGNOANSWER' ";

    if (isset($filter['t1']) && isset($filter['t2'])) $sql .= " AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql .= " AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";

    $sql .= $this->add_where_operands($filter);

    if (!isset($filter['parent'])) {
      // By month
      if (!$onlycount) {
        // $sql .= "GROUP BY DATE_FORMAT(calldate,'%Y-%m'), queue_cdr.calldate";
      }
    } else {
      $parent = $filter['parent'];
      list($year,$month,$day) = explode("-", $parent);
      if (strpos($parent, ".") !== false) {
        // Week specified
        $type = "week";
        list($date,$week_number) = explode(".", $parent);
        $week_number--;
        $first_day = date('Y-m-d 00:00:00', $week_number * 7 * 86400 + strtotime('1/1/' . $year) - date('w', strtotime('1/1/' . $year)) * 86400 + 86400);
        $last_day = date('Y-m-d 23:59:59', ($week_number + 1) * 7 * 86400 + strtotime('1/1/' . $year) - date('w', strtotime('1/1/' . $year)) * 86400);
        $sql .= "AND calldate >= '$first_day' AND calldate <= '$last_day' ";
        if (!$onlycount) {
          $sql .= "GROUP BY DATE_FORMAT(calldate,'%Y-%m-%d')";
        }        
      } else if ($day != 0) {
        // Day
        $type = "day";
        $sql .= "AND calldate >= '$year-$month-$day 00:00:00' AND calldate <= '$year-$month-$day 23:59:59' ";
        if (!$onlycount) {
          $sql .= "GROUP BY DATE_FORMAT(calldate,'%Y-%m-%d:%H')";
        }
      } else {
        // Month
        $type = "month";
        $sql .= "AND calldate >= '$year-$month-1 00:00:00' AND calldate <= '$year-$month-31 23:59:59' ";
        if (!$onlycount) {
          $sql .= "GROUP BY DATE_FORMAT(calldate,'%Y-%m.%u')";
        }
      }
    }
    $result = $this->db->query($sql);
    $table = [];
    if ($onlycount) {
      // $row = $result->fetch(\PDO::FETCH_NUM);
      // return intval($row[0]);
    }
    while ($row = $result->fetch(\PDO::FETCH_BOTH)) {
      $served = $row[3] + $row[4] + $row[5];
      $unserved = $row[6] + $row[8] + $row[7] + $row[9] + $row[10] + $row['ringnoanswer'];

      $total = $served + $unserved;
      $lcr = $row['abandon'] + $row['exitempty'] + $row['exittimeout'];

      $tr = [];

      if (isset($type)) {
        switch ($type) {
            case 'month':
                $tr['id'] = date("Y-m.W",$row['calltime']);
                $tr['period'] = strftime("Неделя %W", $row['calltime']);
                $tr['webix_kids'] = true;
                break;
            case 'week':
                $tr['id'] = date("Y-m-d",$row['calltime']);
                $tr['period'] = strftime("%e %A", $row['calltime']);
                $tr['webix_kids'] = true;
                break;
            case 'day':
                $tr['id'] = date("Y-m-d-H",$row['calltime']);
                $tr['period'] = strftime("%H:00-%H:59", $row['calltime']);
                $tr['webix_kids'] = false;
                break;
        }
      } else {
        $tr['id'] = date("Y-m",$row['calltime']);
        $tr['period'] = strftime("%B %Y", $row['calltime']);
        $tr['webix_kids'] = true;
      }

      $tr['total'] = $total;
      $tr['served'] = $served;
      $tr['unserved'] = $unserved;
      if ($total) {
        $tr['lcr'] = round($lcr/$total*100);
        $tr['sl'] = round($row['sl_cnt']/$total*100);
      }
      $tr['aat'] = round($row['avg_answer']);
      $tr['mat'] = $row['max_answer'];
      $tr['att'] = round($row['avg_talktime']);
      $tr['mtt'] = $row['max_talktime'];
      if ($served) {
        $tr['fcr'] = round($row['cnt_unique_src']/$served*100);
      }
      if ($total) {
        $tr['served_call_per'] = round($served*100/$total, 1);
      }
      $tr['agents'] = $row['agents'];
      $tr['avg_ans'] = $utils->time_format($row['avg_holdtime']);
      $tr['avg_lost'] = $utils->time_format($row['avg_lost']);
      $tr['avg_talk'] = $utils->time_format($row['avg_talktime']);
      $tr['max_wait'] = $utils->time_format($row['max_holdtime']);
      $tr['max_talk'] = $utils->time_format($row['max_talktime']);

      $tr['served20'] = $row['served_more20s'];
      $tr['lost20'] = $row['notserved_more20s'];

      $tr['agents_list'] = $row['agents_list'];

      $agents = explode(",",$row['agents_list']);
      $phones = [];
      foreach ($agents as $agent) {
        $phone = $this->auth->getAgentPhone($agent);
        if (strlen($phone)) $phones[] = "'".$phone."'";
      }

      if (count($phones)) {
        $phones_str = implode(",",$phones);
        $sql = "SELECT COUNT(id) AS cnt, AVG(billsec) AS avg_talktime FROM cdr 
        WHERE src IN ($phones_str) AND LENGTH(dst) > 4 ";
        if (isset($filter['t1']) && isset($filter['t2'])) $sql .= " AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
        else $sql.=" AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";
        //print($sql);
        $res2 = $this->db->query($sql);
        if ($row2 = $res2->fetch(\PDO::FETCH_ASSOC)) {
            $tr['out_count'] = $row2['cnt'];
            //  Added timeformat to avg_out_sec
            $tr['out_avg_sec'] = $utils->time_format($row2['avg_talktime']);
            $tr['out_avg'] = $utils->time_format($row2['avg_talktime']);
        }

        // Get internal stats of agent
        $sql = "SELECT COUNT(id) AS cnt, AVG(billsec) AS avg_talktime FROM cdr WHERE ((src IN ($phones_str) 
        AND LENGTH(dst) < 5) OR (dst IN ($phones_str) AND LENGTH(src) < 5)) ";
        if (isset($filter['t1']) && isset($filter['t2'])) $sql .= " AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
        else $sql.=" AND UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";
        $res2 = $this->db->query($sql);
        if ($row2 = $res2->fetch(\PDO::FETCH_ASSOC)) {
            $tr['int_count'] = $row2['cnt'];
            //  Added timeformat to int_avg_sec
            $tr['int_avg_sec'] = $utils->time_format($row2['avg_talktime']);
            $tr['int_avg'] = $utils->time_format($row2['avg_talktime']);
        }
      }

      $table[] = $tr;
    }

    if (isset($parent)) {
      $ant = [ "parent" => $parent,
        "data" => $table];
      return $ant;
    } else {
      return $table;
    }
  }


  public function getExt_dashboard_callschart($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $utils = new Utils();

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
                MAX(talktime) AS max_talktime,
                MIN(holdtime) AS min_holdtime,
                MIN(IF(talktime,talktime,NULL)), 
                AVG(IF(talktime>0,ringtime,NULL)) AS avg_ans,										
                AVG(talktime) AS avg_talktime,					
                AVG(holdtime) AS avg_holdtime,					
                MAX(IF(talktime>0,ringtime,NULL)) AS max_answer,										
                count(distinct IF(agentname='',NULL,agentname)) AS agents,
                SUM(IF(holdtime < 20 AND talktime > 0,1,0)) AS sl_cnt,
                count(distinct IF(talktime>0,src,NULL)) AS cnt_unique_src,
                UNIX_TIMESTAMP(calldate) AS calltime
                FROM queue_cdr WHERE reason != 'RINGNOANSWER' AND ";

//  Time Settings

    if (isset($filter['t1']) && isset($filter['t2'])) $sql = $sql." calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql = $sql." UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";

    $sql .= $this->add_where_operands($filter);

    $sql .= " GROUP BY DATE_FORMAT(calldate,'%w')";

    $result = $this->db->query($sql);
    $table = [];

    $max_sl = 0;
    $max_calls = 0;

    while ($row = $result->fetch(\PDO::FETCH_BOTH)) {
      $served = $row[3] + $row[4] + $row[5];
      $unserved = $row[6] + $row[8] + $row[7] + $row[9] + $row[10] + $row['ringnoanswer'];

      $total = $served + $unserved;
      $lcr = $row['abandon'] + $row['exitempty'] + $row['exittimeout'];

      $tr = [];

      $tr['period'] = strftime("%a", $row['calltime']);
      $tr['calls'] = $total;
      $tr['sl'] = round($row['sl_cnt']/$total*100);
      //$tr['slg'] = round($row['sl_cnt']/$total*$total);

      $max_sl = max($max_sl, $tr['sl']);
      $max_calls = max($max_calls, $tr['calls']);

      $table[] = $tr;
    }
    if ($max_sl) $mult = $max_calls / $max_sl;
    else $mult = 1;
    foreach ($table as $k => $e) {
      $table[$k]['slg'] = round($e['sl']*$mult);
    }
    return $table;
  }


  public function getExt_dashboard_hourschart($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $utils = new Utils();

    // Here need to add permission checkers and filters

    if ($onlycount) {
        $res = $this->db->query("SELECT COUNT(*) FROM queue_cdr");
        $row = $res->fetch(\PDO::FETCH_NUM);
        return intval($row[0]);
    }

    $sql = "SELECT
      count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') and holdtime <= 20,1,NULL)) AS served20,
      count(IF((reason = 'ABANDON' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITWITHKEY') and holdtime <= 20,1,NULL)) AS notserved20,
      count(IF((reason = 'COMPLETEAGENT' OR reason = 'COMPLETECALLER' OR reason = 'TRANSFER') and holdtime > 20,1,NULL)) AS served,
      count(IF((reason = 'ABANDON' OR reason = 'EXITEMPTY' OR reason = 'EXITWITHTIMEOUT' OR reason = 'EXITWITHKEY') and holdtime > 20,1,NULL)) AS notserved,
      UNIX_TIMESTAMP(calldate) AS calltime
      FROM queue_cdr WHERE reason != 'RINGNOANSWER' AND ";


    if (isset($filter['t1']) && isset($filter['t2'])) $sql = $sql." calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
    else $sql = $sql." UNIX_TIMESTAMP(Now())-UNIX_TIMESTAMP(calldate) < 186400 ";

    $sql .= $this->add_where_operands($filter);

    $sql .= "GROUP BY DATE_FORMAT(calldate,'%H')";

    $result = $this->db->query($sql);
    $table = [];

    while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
      $tr = [];
      //Format changed, added :00 after hours (deleted)
      $tr['time'] = strftime("%H", $row['calltime']); //strftime("%H:00", $row['calltime']);
      $tr['served'] = $row['served'];
      $tr['unserved'] = $row['notserved'];
      $tr['served20'] = $row['served20'];
      $tr['unserved20'] = $row['notserved20'];

      $table[] = $tr;
    }
    return $table;
  }


  public function getExt_dashboard_worktimechart($filter, $pos, $count = 20, $onlycount = 0) {

    setlocale(LC_ALL, "ru_RU.UTF-8");
    $this->db->query("SET sql_mode = ''");

    $utils = new Utils();

    $worktime = $this->calcTotalWorktime($filter['t1'],$filter['t2']);

    $table = [];

    $max_wt = 0;

    if (isset($filter['agents']) && strlen($filter['agents'])) {
      $sql2 = "SELECT name FROM acl_user WHERE id IN (".addslashes($filter['agents']).")";
      $res = $this->db->query($sql2);
      $agents = [];
      while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
          $agents[] = $row['name'];
      }
    }

    //var_dump($worktime);

    foreach ($worktime as $k => $v) {

        if (isset($agents) && !in_array($k, $agents)) {
          continue;
        }
        $tr = [];
        $tr['agent_name'] = $this->auth->fullname_agent($k, 8);
        $tr['agent_name_short'] = $this->auth->fullname_agent_short($k);
        $tr['ready'] = round($v['worktime']/3600,2);
        $tr['notready'] = round($v['notready_time']/3600,2);
        $tr['afterwork'] = round($v['afterwork_time']/3600,2);
        $tr['lunch'] = round($v['lunch_time']/3600,2);
        $tr['meeting'] = round($v['meeting_time']/3600,2);
        $tr['rest'] = round($v['rest_time']/3600,2);
        $tr['tasks'] = round($v['tasks_time']/3600,2);

      $sql = "SELECT SUM(talktime) FROM queue_cdr WHERE agentname = '$k' AND  calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";

        $res = $this->db->query($sql);
        $talktime = 0;
        if ($row = $res->fetch(\PDO::FETCH_NUM)) $talktime = $row[0] + 0;

        // Get agent phone num
        $agentnum = $this->auth->getAgentPhone($k);

        $sql = "SELECT SUM(billsec) AS talktime FROM cdr WHERE (src = '$agentnum' OR dst = '$agentnum') AND calldate>'".$filter['t1']."' AND calldate<'".$filter['t2']."' ";
        $res2 = $this->db->query($sql);
        if ($row2 = $res2->fetch(\PDO::FETCH_ASSOC)) {
            $talktime += $row2['talktime'];
        }

        $tr['talktime'] = round($talktime/3600,2);
        $totalworktime = $v['afterwork_time'] + $v['meeting_time'] + $v['worktime'] + $v['notready_time'] + $v['lunch_time'] +
            $v['meeting_time'] + $v['rest_time'];
        if ($totalworktime > 0) {
          $tr['utilization'] = round(100*(($talktime + $v['afterwork_time'] + $v['tasks_time']) /
          $totalworktime),2);
        } else {
          $tr['utilization'] = round(0,2);
        }
        
        $max_wt = max($max_wt,$totalworktime);
        $table[] = $tr;
    }

    if ($max_wt) $mult = ($max_wt/3600) / 100;
    else $mult = 1;
    foreach ($table as $k => $e) {
      $table[$k]['utg'] = $e['utilization']*$mult;
    }
    // header("Content-type: text/plain");
    return $table;
  }

}