<?php

namespace Erpico;

class Operators_work_report {
  private $container;
  private $db;
  private $auth;  

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function parse_timestamp($seconds=0) {
        global $debug;
        if($debug) print $seconds." - ";
        $hours   = floor(($seconds) / 3600);
        $minutes = floor(($seconds - ($hours * 3600))/60);
        $seconds = floor(($seconds - ($hours * 3600) - ($minutes*60)));
        return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
  }


  public function getOperators_work_report_list($filter, $pos, $count = 20, $onlycount = 0)
  {
    $utils = new Utils();

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM queue_log");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $demand = "	SELECT *, UNIX_TIMESTAMP(time) as tm FROM queue_log ";
    if(isset($filter['t1']) && isset($filter['t2'])) $demand.= "WHERE time>'".date("Y-m-d H:i:s",strtotime($filter['t1']))."' AND time<'".date("Y-m-d H:i:s",strtotime($filter['t2']))."' ";
    else $demand.= " WHERE time>'".date("Y-m-d H:i:s",time()-86400)."'";

    $que = $this->auth->allowed_queues();
    $queues_log = $utils->sql_allowed_queues_n($que);
    $demand .= $queues_log;

    $demand .= "	AND event in ('PAUSE','UNPAUSE','ADDMEMBER','REMOVEMEMBER','PAUSEALL')
            ORDER BY time ASC ";

    $result = $this->db->query($demand);

    $qagents = [];
    $interfaces = [];

    while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
      $agent = $row['agent'];
      $queue = $row['queuename'];
      $time = $row['tm'];

      switch ($row['event']) {
          case 'ADDMEMBER': {
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
          case 'PAUSE': {
              //User login or pause
              $reason = $row['data1'];
              //Check current state
              if (isset($qagents[$agent]) && isset($qagents[$agent][$queue]) && isset($qagents[$agent][$queue]['state'])) {
                  switch ($qagents[$agent][$queue]['state']) {
                      case 'PAUSE': {
                          if ($qagents[$agent][$queue]['last_pause_reason'] == 'afterwork') {
                              //$qagents[$agent][$queue]['total_afterwork_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                              $qagents[$agent][$queue]['afterwork_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                          } else {
                              //$qagents[$agent][$queue]['total_notready_time'] += $time - $qagents[$agent][$queue]['last_pause'];
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
                          if ($qagents[$agent][$queue]['last_login'] != 0 && $qagents[$agent][$queue]['last_unpause'] != 0) {
                              //$qagents[$agent][$queue]['total_work_time'] += $time - $qagents[$agent][$queue]['last_unpause'];
                              $qagents[$agent][$queue]['work_time'] += $time - $qagents[$agent][$queue]['last_unpause'];
                              //print "Yo-".date('d.m.Y H:i:s',$time)."-".$time."-".$qagents[$agent][$queue]['last_unpause']."-".($time - $qagents[$agent][$queue]['last_unpause'])//print_r($qagents[$agent][$queue],1)."<br>";

                              $qagents[$agent][$queue]['works'][] = [
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
                  if (isset($queue_ifcs)) foreach ($queue_ifcs as $ifc) {
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
          case 'UNPAUSE': {
              // Start working
              if ($qagents[$agent][$queue]['last_login'] != 0 && $qagents[$agent][$queue]['state'] != 'UNPAUSE') $qagents[$agent][$queue]['last_unpause'] = $time;
              if ($qagents[$agent][$queue]['last_login'] != 0 && $qagents[$agent][$queue]['last_pause'] != 0) {
                  if ($qagents[$agent][$queue]['state'] == 'PAUSE' && isset($qagents[$agent][$queue]['last_pause_reason'])) {
                      if ($qagents[$agent][$queue]['last_pause_reason'] == 'afterwork') {
                          $qagents[$agent][$queue]['afterwork_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                      } else {
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
          case 'REMOVEMEMBER': {
              // User logout
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
                                  } else {
                                      $c[$queue]['notready_time'] += $time - $c[$queue]['last_pause'];
                                  }
                                  $c[$queue]['pauses'][] = [
                                      'time' => $c[$queue]['last_pause'],
                                      'reason' => $c[$queue]['last_pause_reason'],
                                      'duration' => $time - $c[$queue]['last_pause']
                                  ];
                                  break;
                              case 'UNPAUSE':
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
                              "notready_time" => $c[$queue]['notready_time']
                          ];
                          $session['works'] = $c[$queue]['works'];
                          $session['pauses'] = $c[$queue]['pauses'];
                          $c[$queue]['sessions'][] = $session;
                          $c[$queue]['total_work_time'] += $c[$queue]['work_time'];
                          $c[$queue]['total_afterwork_time'] += $c[$queue]['afterwork_time'];
                          $c[$queue]['total_notready_time'] += $c[$queue]['notready_time'];
                          $c[$queue]['work_time'] = 0;
                          $c[$queue]['afterwork_time'] = 0;
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

    $qagentEvents = [];
    foreach ($qagents as $name => $agentQueues) {
      if (isset($userEvents)) unset($userEvents);
      $userEvents = [];
      foreach ($agentQueues as $queue => $agentQueue) {
          if (isset($agentQueue['sessions'])) foreach ($agentQueue['sessions'] as $event) {
              $k = $event['time'] . "_0";
              if (!isset($userEvents[$k])) $userEvents[$k] = $event;
              $userEvents[$k]['action'] = 'STARTWORK';
              if ($userEvents[$k]['queues'] == NULL) $userEvents[$k]['queues'][] = $queue; // <--makhmoody
              else if (!in_array($queue, $userEvents[$k]['queues'])) $userEvents[$k]['queues'][] = $queue;
              if ($event['logout']) {
                  $k = $event['logout'] . "_3";
                  if (!isset($userEvents[$k])) $userEvents[$k] = $event;
                  $userEvents[$k]['action'] = 'ENDWORK';
                  if ($userEvents[$k]['queues'] == NULL) $userEvents[$k]['queues'][] = $queue; // <--makhmoody
                  else if (!in_array($queue, $userEvents[$k]['queues'])) $userEvents[$k]['queues'][] = $queue;
              }
              foreach ($event['pauses'] as $pause) {
                  $k = $pause['time'] . "_1";
                  if (!isset($userEvents[$k])) $userEvents[$k] = $pause;
                  $userEvents[$k]['duration'] = max($userEvents[$k]['duration'], $pause['duration']);
                  if ($userEvents[$k]['queues'] == NULL) $userEvents[$k]['queues'][] = $queue; // <--makhmoody
                  else if (!in_array($queue, $userEvents[$k]['queues'])) $userEvents[$k]['queues'][] = $queue;
                  $userEvents[$k]['action'] = 'PAUSE';
              }
              if (isset($event['works'])) /* <--makhmoody */
                  foreach ($event['works'] as $event2) {
                      $k = $event2['time'] . "_2";
                      if (!isset($userEvents[$k])) $userEvents[$k] = $event2;
                      $event2[$k]['duration'] = max($userEvents[$k]['duration'], $event2['duration']);
                      if ($userEvents[$k]['queues'] == NULL) $userEvents[$k]['queues'][] = $queue; // <--makhmoody
                      else if (!in_array($queue, $userEvents[$k]['queues'])) $userEvents[$k]['queues'][] = $queue;
                      $userEvents[$k]['action'] = 'UNPAUSE';
                  }
          }
      }
      ksort($userEvents);
      $qagentEvents[$name] = $userEvents;
    }

      // Calculate total statistics for operators
    $qagentsTotal = [];

    foreach ($qagentEvents as $name => $userEvents) {
      $total = [
          'worktime' => 0,
          'work_cnt' => 0,
          'notready_time' => 0,
          'notready_cnt' => 0,
          'afterwork_time' => 0,
          'afterwork_cnt' => 0,
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
          if ($cState == '') $pTime = $etime + 0;
          switch ($event['action']) {
              case 'PAUSE': {
                  if ($cState == "work" || $cState == '') {
                      if ($cState == "work") {
                          $total['worktime'] += ($etime + 0) - $pTime;
                          $total['work_cnt']++;
                      }
                      if ($event['reason'] == "afterwork") $cState = "afterwork";
                      else $cState = "notready";
                      $pTime = $etime + 0;
                  }

                  $readyinqueues -= count($event['queues']);
                  if ($readyinqueues < 0) $readyinqueues = 0;
                  break;
              }
              case 'UNPAUSE': {
                  if ($cState != "work") {
                      if ($cState == "notready") {
                          $total['notready_time'] += ($etime + 0) - $pTime;//$event['duration'];
                          $total['notready_cnt']++;
                      } else if ($cState == "afterwork") {
                          $total['afterwork_time'] += ($etime + 0) - $pTime;//$event['duration'];
                          $total['afterwork_cnt']++;
                      }
                      $pTime = $etime + 0;
                      $cState = "work";
                  }

                  $readyinqueues += count($event['queues']);
                  break;
              }
              case 'STARTWORK': {
                  //print "Начало смены";
                  $total['sessions']++;
                  if ($total['first_login'] == 0) $total['first_login'] = $etime + 0;
                  foreach ($event['queues'] as $queue) if (!in_array($queue, $total['queues'])) array_push($total['queues'], $queue);
                  $t = $etime + 0;
                  if ($curqueues == 0) $pTime = $etime + 0;
                  $curqueues += count($event['queues']);
                  break;
              }
              case 'ENDWORK': {
                  $total['last_logoff'] = $etime + 0;
                  $curqueues -= count($event['queues']);
                  if ($curqueues == 0) {
                      if ($cState == "work") {
                          $total['worktime'] += ($etime + 0) - $pTime;
                          $total['work_cnt']++;
                      } else if ($cState == "notready") {
                          $total['notready_time'] += ($etime + 0) - $pTime;//$event['duration'];
                          $total['notready_cnt']++;
                      } else if ($cState == "afterwork") {
                          $total['afterwork_time'] += ($etime + 0) - $pTime;//$event['duration'];
                          $total['afterwork_cnt']++;
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

    function revertTimeStamp($timestamp)
    {
      $year = substr($timestamp, 0, 4);
      $month = substr($timestamp, 4, 2);
      $day = substr($timestamp, 6, 2);
      $hour = substr($timestamp, 8, 2);
      $minute = substr($timestamp, 10, 2);
      $second = substr($timestamp, 12, 2);
      $newdate = mktime($hour, $minute, $second, $month, $day, $year);
      return ($newdate);
    }

    $data_of_operator = [];
    $datas_of_operator = [];
    $string_of_operator = [];
    $strings_of_operator = [];
    $qagentEvents_count = count($qagentEvents);

    foreach ($qagentEvents as $name => $userEvents) {
      if (!isset($qagentsTotal[$name])) continue;
      $name_of_operator = $this->auth->fullname_agent($name);

      $qqcount = 0;
      $userEvents_count = count($userEvents);
      $k = 0;
      foreach ($userEvents as $etime => $event) {
          $bgColor = "red";
          $text = "Ошибка";
          switch ($event['action']) {
              case 'PAUSE':
                  $text = ($event['reason'] == "afterwork" ? "Постобработка" : "Не готов");
                  $bgColor = "#fff17b";
                  break;
              case 'UNPAUSE':
                  $text = "Готов";
                  $bgColor = "#94ff7b";
                  break;
              case 'STARTWORK':
                  if ($qqcount > 0) {
                      $text = "Добавление в очереди";
                      $bgColor = "#7bfffd";
                  } else {
                      $text = "Начало смены";
                      $bgColor = "#7badff";
                      $t = $etime + 0;
                      $ts = $t;
                  }
                  $qqcount += count($event['queues']);
                  break;
              case 'ENDWORK':
                  $qqcount -= count($event['queues']);
                  if ($qqcount > 0) {
                      $text = "Исключение из очередей";
                      $bgColor = "#fad4ff";
                  } else {
                      $text = "Завершение смены";
                      $bgColor = "#ffb4c0";
                      $tf = $etime + 0;
                  }
                  break;
          }

          $duration = "";
          if ($event['action'] != 'STARTWORK' && $event['action'] != 'ENDWORK') {
              $duration = $this->parse_timestamp($event['duration']);
              if ($debug) {
                  $t += $event['duration'];
                  $duration .= " / " . date('d.m.Y H:i:s', $t);
              }
          }

          if (isset($data_of_operator)) unset($data_of_operator);
          $data_of_operator['type'] = $text;
          $data_of_operator['calldate'] = date('d.m.Y H:i:s', $etime);
          $data_of_operator['duration'] = $duration;
          $agent_queues = $this->auth->fullname_queue($event['queues']);
          $data_of_operator['queue'] = implode(", ", $agent_queues);
          $datas_of_operator[] = $data_of_operator;
      }

      $string_of_operator['type'] = $name_of_operator;
      $string_of_operator['data'] = $datas_of_operator;
      if (isset($datas_of_operator)) unset($datas_of_operator);
      $strings_of_operator[] = $string_of_operator;
      if (isset($string_of_operator)) unset($string_of_operator);
    }

    return $strings_of_operator;
  }


  public function getOperators_work_report($filter, $pos, $count = 20, $onlycount = 0) {

    $utils = new Utils();

    if ($onlycount) {
      $res = $this->db->query("SELECT COUNT(*) FROM queue_log");
      $row = $res->fetch(\PDO::FETCH_NUM);
      return intval($row[0]);
    }

    $demand = "	SELECT *, UNIX_TIMESTAMP(time) as tm FROM queue_log ";
    if(isset($filter['t1']) && isset($filter['t2'])) $demand.= "WHERE time>'".date("Y-m-d H:i:s",strtotime($filter['t1']))."' AND time<'".date("Y-m-d H:i:s",strtotime($filter['t2']))."' ";
    else $demand.= "WHERE time>'".date("Y-m-d H:i:s",time()-86400)."'";

    $que = $this->auth->allowed_queues();
    $queues_log = $utils->sql_allowed_queues_n($que);
    $demand.= $queues_log;

    $demand.= "	AND event in ('PAUSE','UNPAUSE','ADDMEMBER','REMOVEMEMBER','PAUSEALL')
            ORDER BY time ASC ";

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
                              //$qagents[$agent][$queue]['total_afterwork_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                              $qagents[$agent][$queue]['afterwork_time'] += $time - $qagents[$agent][$queue]['last_pause'];
                          }
                          else {
                              //$qagents[$agent][$queue]['total_notready_time'] += $time - $qagents[$agent][$queue]['last_pause'];
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
                          if ($qagents[$agent][$queue]['last_login'] != 0 && $qagents[$agent][$queue]['last_unpause'] != 0) {
                              //$qagents[$agent][$queue]['total_work_time'] += $time - $qagents[$agent][$queue]['last_unpause'];
                              $qagents[$agent][$queue]['work_time'] += $time - $qagents[$agent][$queue]['last_unpause'];
                              //print "Yo-".date('d.m.Y H:i:s',$time)."-".$time."-".$qagents[$agent][$queue]['last_unpause']."-".($time - $qagents[$agent][$queue]['last_unpause'])//print_r($qagents[$agent][$queue],1)."<br>";

                              $qagents[$agent][$queue]['works'][] = [
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
              if ($qagents[$agent][$queue]['last_login'] != 0 && $qagents[$agent][$queue]['state'] != 'UNPAUSE') $qagents[$agent][$queue]['last_unpause'] = $time;
              if ($qagents[$agent][$queue]['last_login'] != 0 && $qagents[$agent][$queue]['last_pause'] != 0) {
                  if ($qagents[$agent][$queue]['state'] == 'PAUSE' && isset($qagents[$agent][$queue]['last_pause_reason'])) {
                      if ($qagents[$agent][$queue]['last_pause_reason'] == 'afterwork') {
                          $qagents[$agent][$queue]['afterwork_time'] += $time - $qagents[$agent][$queue]['last_pause'];
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
              }
              $qagents[$agent][$queue]['state'] = $row['event'];
          }
              break;
          case 'REMOVEMEMBER':
          {
              // User logout
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
                                  else {
                                      $c[$queue]['notready_time'] += $time - $c[$queue]['last_pause'];
                                  }
                                  $c[$queue]['pauses'][] = [
                                      'time' => $c[$queue]['last_pause'],
                                      'reason' => $c[$queue]['last_pause_reason'],
                                      'duration' => $time - $c[$queue]['last_pause']
                                  ];
                                  break;
                              case 'UNPAUSE':
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
                              "notready_time" => $c[$queue]['notready_time']
                          ];
                          $session['works'] = $c[$queue]['works'];
                          $session['pauses'] = $c[$queue]['pauses'];
                          $c[$queue]['sessions'][] = $session;
                          $c[$queue]['total_work_time'] += $c[$queue]['work_time'];
                          $c[$queue]['total_afterwork_time'] += $c[$queue]['afterwork_time'];
                          $c[$queue]['total_notready_time'] += $c[$queue]['notready_time'];
                          $c[$queue]['work_time'] = 0;
                          $c[$queue]['afterwork_time'] = 0;
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

    $qagentEvents = [];
    foreach($qagents as $name => $agentQueues) {
      if (isset($userEvents)) unset($userEvents);
      $userEvents = [];
      foreach($agentQueues as $queue => $agentQueue) {
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
          'first_login' => 0,
          'last_logoff' => 0,
          'sessions' => 0,
          'queues' => [] ];
      $curqueues = 0;
      $readyinqueues = 0;
      $t = 0;
      $cState = '';
      $pTime = 0;

      foreach ($userEvents as $etime => $event) {
          if ($cState == '') $pTime = $etime+0;
          switch ($event['action']) {
              case 'PAUSE': {
                  if ($cState == "work" || $cState == '') {
                      if ($cState == "work") {
                          $total['worktime'] += ($etime+0)-$pTime;
                          $total['work_cnt']++;
                      }
                      if ($event['reason'] == "afterwork") $cState = "afterwork";
                      else $cState = "notready";
                      $pTime = $etime+0;
                  }

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

    function revertTimeStamp($timestamp) {
      $year=substr($timestamp,0,4);
      $month=substr($timestamp,4,2);
      $day=substr($timestamp,6,2);
      $hour=substr($timestamp,8,2);
      $minute=substr($timestamp,10,2);
      $second=substr($timestamp,12,2);
      $newdate=mktime($hour,$minute,$second,$month,$day, $year);
      return($newdate);
    }

    $cdr_report_res = [];
    $j = -1;

    foreach($qagentsTotal as $name=>$agent) {
        $j++;
        $cdr_report_res[$j]['operator_name'] = $this->auth->fullname_agent($name);
        $cdr_report_res[$j]['time_ready'] = $this->parse_timestamp($agent['worktime']);
        $cdr_report_res[$j]['time_unready'] = $this->parse_timestamp($agent['notready_time']);
        $cdr_report_res[$j]['time_post'] = $this->parse_timestamp($agent['afterwork_time']);
        $cdr_report_res[$j]['time_first'] = date('d.m.Y H:i:s', $agent['first_login']);
        $cdr_report_res[$j]['time_last'] = date('d.m.Y H:i:s', $agent['last_logoff']);
        $cdr_report_res[$j]['include'] = $agent['sessions'];
        $agent_queues = $this->auth->fullname_queue($agent['queues']);
        $cdr_report_res[$j]['take_part'] = implode(", ",$agent_queues);
    };

    return $cdr_report_res;
  }
}