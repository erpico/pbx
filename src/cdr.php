<?php

class PBXCdr {
  protected $db;

  public function __construct() {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];

    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();
  }
  
  private function normalizePhone(&$_phone) {
    $_phone = preg_replace("/[^\d]/", "", trim($_phone));
    if ($_phone[0] == '7' && strlen($phone) > 8) $_phone = "8".substr($_phone, 1);
    if (strlen($_phone) == 10) $_phone = "8".$_phone;
    return $_phone;
  }

  private function getTableName() {
    return 'cdr';
  }

  public function getReportsByUid($id) {
    $sql =  "SELECT 
          calldate AS time, 
          src, 
          agentdev AS dst, 
          queue, 
          reason, 
          holdtime AS hold, 
          talktime AS talk, 
          uniqid, 
          agentname,
          queue,
          channel,
          dstchannel
    FROM queue_cdr WHERE uniqid = '{$id}' 
     UNION ALL
    SELECT calldate AS time, src, dst, name, disposition AS reason, duration - billsec AS hold , billsec AS talk, uniqueid AS uniqid, '', '', channel, dstchannel
    FROM cdr WHERE uniqueid = '{$id}'";
    $result_cdr = $this->db->query($sql);      
    $cdr = $result_cdr->fetchAll(\PDO::FETCH_ASSOC);

    return $cdr;      
  }

  public function getReport($filter, $start = 0, $limit = 20, $onlyCount = 0, $serverFooter = 0, $_lcd = 0) {
    $ext = $this->user->allow_extens();
    $extens = $this->utils->sql_allow_extens($ext);

    $que = $this->user->allowed_queues();
    $queues = $this->utils->sql_allowed_queues_for_records($que);

    $users_list = $this->user->getUsersList();

    $qwsql = "";
    $cwsql = "";

    $timeisset = 0;
        
    if (is_array($filter)) {
      if (isset($filter['time']) && strlen($filter['time'])) {
        $dates = json_decode($filter['time'], 1);
        if ($dates['start']) {
          $d = strtotime($dates['start']);            
          $qwsql .= "AND a.calldate >= '".date("Y-m-d H:i:00", $d)."' ";
          $cwsql .= "AND calldate >= '".date("Y-m-d H:i:00", $d)."' ";
          $timeisset++;
        }
        if ($dates['end']) {
          $d = strtotime($dates['end']);
          if (date("H", $d) == 0 && date("i",$d) == 0) $d += 86399;
          $qwsql .= "AND a.calldate <= '".date("Y-m-d H:i:59", $d)."' ";
          $cwsql .= "AND calldate <= '".date("Y-m-d H:i:59", $d)."' ";
          $timeisset++;
        }
      }
      if(isset($filter['userfield']) && strlen($filter['userfield'])) {
        
        $qwsql .= "	AND a.userfield LIKE '%".addslashes($filter['userfield'])."%' ";
        $cwsql .= "	AND userfield LIKE '%".addslashes($filter['userfield'])."%' ";
      }
      if(isset($filter['src']) && strlen($filter['src'])) {
        $qwsql .= "	AND (a.src LIKE '%".addslashes($filter['src'])."%' OR a.agentdev LIKE '%".addslashes($filter['src'])."%')";
        $cwsql .= "	AND (src LIKE '%".addslashes($filter['src'])."%' OR dst LIKE '%".addslashes($filter['src'])."%' )";
      }
      if(isset($filter['dst']) && strlen($filter['dst'])) {
        $cwsql .= "	AND dst LIKE '%".addslashes($filter['dst'])."%' ";
        $qwsql .= "	AND a.agentdev LIKE '%".addslashes($filter['dst'])."%' ";
      }
      if(isset($filter['agent']) && strlen($filter['agent'])) {
        $cwsql .= "	AND 1 = 0 "; //Ignore CDR
        $qwsql .= "	AND a.agentname = '".addslashes($filter['agent'])."' ";
      }
      if(isset($filter['queue']) && strlen($filter['queue'])) {
        $cwsql .= "	AND 1 = 0 "; //Ignore CDR
        $qwsql .="	AND a.queue = '".addslashes($filter['queue'])."' ";
      }
      if(isset($filter['reason']) && strlen($filter['reason'])) {
        // RP: it's more complex query ....
        $qwsql = $qwsql."	AND a.reason LIKE '%".addslashes($filter['reason'])."%' ";
        $cwsql = $cwsql."	AND disposition LIKE '%".addslashes($filter['reason'])."%' ";
      }
      if (isset($filter['talk']) && strlen($filter['talk'])) {
        $talk = json_decode($filter['talk'], 1);
        if ($talk['from']) {
          $qwsql .= "AND a.talktime >= '".intval($talk['from'])."' ";
          $cwsql .= "AND billsec >= '".intval($talk['from'])."' ";
        }
        if ($talk['to']) {
          $qwsql .= "AND a.talktime <= '".intval($talk['to'])."' ";
          $cwsql .= "AND billsec <= '".intval($talk['to'])."' ";
        }
      }
      if (isset($filter['hold']) && strlen($filter['hold'])) {
        $hold = json_decode($filter['hold'], 1);
        if ($hold['from']) {
          $qwsql .= "AND a.holdtime >= '".intval($hold['from'])."' ";
          $cwsql .= "AND duration - billsec >= '".intval($hold['from'])."' ";
        }
        if ($hold['to']) {
          $qwsql .= "AND a.holdtime <= '".intval($hold['to'])."' ";
          $cwsql .= "AND duration - billsec <= '".intval($hold['to'])."' ";
        }
      }      
    }
    /*$sql = "(SELECT id, calldate, src, agentdev AS dst, queue, reason, holdtime, talktime, uniqid, agentname 
      FROM queue_cdr WHERE 1=1 ";
      $sql .= $queues."
      UNION
      SELECT id, calldate, src, dst, name, disposition, duration - billsec, billsec, uniqueid AS uniqid, '' 
      FROM cdr WHERE 1=1 ";
    $sql .= $extens.") as a";
    if (strlen($wsql)) {
      $sql .= " WHERE 1=1 $wsql";
    }*/

    if (intval($onlyCount)) {
      if ($timeisset != 2) return 4000000; // Return infinite for scrolling
      $sql = "SELECT SUM(n) FROM (SELECT SUM(n) AS n FROM (SELECT COUNT(*) AS n FROM queue_cdr a LEFT OUTER JOIN queue_cdr b ON a.uniqid = b.uniqid AND a.id < b.id WHERE b.uniqid IS NULL  $queues $qwsql) as u UNION SELECT COUNT(uniqueid) AS n FROM cdr WHERE 1=1 $extens $cwsql) as c";                              
      $res = $this->db->query($sql);      
      $row = $res->fetch(PDO::FETCH_NUM);      
      return $row[0]; 
    }

    
    if (intval($serverFooter)) {
      if ($timeisset != 2) return []; // only with selected date
      $sql = "SELECT        
        SUM(sum_billsec) AS sum_billsec,
        SUM(sum_duration) AS sum_duration,
        SUM(count_answered) AS count_answered,
        SUM(sum_answered) AS sum_answered
        FROM 
          (
          SELECT               
              SUM(holdtime) AS sum_billsec,
              SUM(IF(reason = 'ANSWERED',talktime,0)) AS sum_duration,
              COUNT(IF(reason = 'ANSWERED',1,NULL)) AS count_answered,
              SUM(IF(reason = 'ANSWERED', (talktime),0)) AS sum_answered
              FROM queue_cdr a WHERE 1=1 $queues $qwsql GROUP BY uniqid
          UNION 
          SELECT               
              SUM(duration - billsec) AS sum_billsec,
              SUM(IF(disposition = 'ANSWERED',billsec,0)) AS sum_duration,
              COUNT(IF(disposition = 'ANSWERED',1,NULL)) AS count_answered,
              SUM(IF(disposition = 'ANSWERED', billsec,0)) AS sum_answered
              FROM cdr WHERE 1=1 $extens $cwsql
          ) as c      
      ";            
      $result_cdr = $this->db->query($sql);      
      $cdr = $result_cdr->fetch(\PDO::FETCH_ASSOC);      
      return $cdr;      
    }
    
    $lcd = 0;
    if (strlen($_lcd)) $lcd = strtotime($_lcd);    
    if (!$lcd) $lcd = time();
    else $lcd -= 1;

    $mintime = strtotime("2000-01-01 00:00:00");
    
    do {

      $fcd = $lcd - 3600*24;

      $sql = "SELECT * FROM (
              SELECT 
                  a.calldate, 
                  a.src, 
                  a.agentdev AS dst, 
                  a.queue, 
                  a.reason, 
                  a.holdtime, 
                  a.talktime, 
                  a.uniqid, 
                  a.agentname,
                  a.userfield
        FROM queue_cdr a LEFT OUTER JOIN queue_cdr b ON a.uniqid = b.uniqid AND a.id < b.id WHERE b.uniqid IS NULL $queues $qwsql 
        ".($limit != 1000000 ? "AND a.calldate >= '".date('Y-m-d H:i:s', $fcd)."' AND a.calldate <= '".date('Y-m-d H:i:s', $lcd)."' " : "")."
        UNION
        SELECT calldate, src, dst, name, disposition, duration - billsec, billsec, uniqueid AS uniqid, '', userfield
        FROM cdr WHERE 1=1 $extens $cwsql 
        ".($limit != 1000000 ? "AND calldate >= '".date('Y-m-d H:i:s', $fcd)."' AND calldate <= '".date('Y-m-d H:i:s', $lcd)."' " : "")."
        ) AS c ORDER BY calldate DESC ";        
      
      /*if (isset($start) && isset($limit)){
        $sql .= " LIMIT ".intval($start).", ".intval($limit);
      }*/
      //$sql .= " LIMIT 100"; // No more for now
      $cdr = [];                

      $res = $this->db->query($sql);
      //die(var_dump($res));
      $lcd -= 3600*24;

    } while ($lcd > $mintime && $res->rowCount() == 0);

    while ($row = $res->fetch()) {
        $src = $row['src'];
        $dst = $row['dst'];    
        if (preg_match("/Local\/(\d+)@.*/", $dst, $matches) == 1) {
          $dst = $matches[1];      
        }
        if (preg_match("/SIP\/(\d+)/", $dst, $matches) == 1) {
          $dst = $matches[1];            
        }    
        if (strlen($dst) == 0 && strlen($row['userfield']) > 0) {
          $dst = $row['userfield'];
        }
        $queue = $row['queue'];
        if ($queue == 'Unknown') $queue = '';
        
        $reason = $row['reason'];
    
        switch ($reason) {
          case 'ABANDON':
          case 'BUSY':
          case 'EXITWITHTIMEOUT':
          case 'FAILED':
          case 'NO ANSWER':
          case 'RINGDECLINE':
          case 'RINGNOANSWER':
            $answered = false;
            break;
          default:
            $answered = true;
            break;
        }
        
        $agent = $row['agentname'];
        $hold = $row['holdtime'];
        $talk = $row['talktime'];
        $uid  = $row['uniqid'];
    
        $calldate = $row['calldate'];
    
        if (strlen($src) <= 4) {
          $direction = "out";
        } else {
          $direction = "in";
        }
    
        $src = $this->normalizePhone($src);
        $dst = $this->normalizePhone($dst);
    
        $cv = [          
          'uid' => $uid,
          'time' => $calldate,
          'src' => $src,
          'dst' => $dst,
          'queue' => $this->getQueueName($queue),
          'reason' => $reason,
          'answered' => $answered,
          'direction' => $direction,
          'agent' => $this->getAgentName($agent),
          'hold' => intval($hold),
          'talk' => intval($talk),
          'userfield' => $row['userfield']
        ];
    
        // Before insert compare with last line in array
        /*
        $li = count($cdr) - 1;
        if ($li >= 0) {
          $ll = $cdr[$li]; // Get last line
          if ($ll['time'] == $cv['time'] && $ll['src'] == $cv['src']) {
            // In one time from one number - it's same call, we will select call with max holdtime and with queue value (if persis)
            if ($cv['queue'] != '' && $ll['queue'] == '') {
              // Should replace with current line, 'cos it's have queuename (queue cdr value)
              $cdr[$li] = $cv;
            } else if ($cv['hold'] > $ll['hold']) {
              // Should replace with current line, 'cos it's have bigger hold time
              $cdr[$li] = $cv;
            } // In other cases - leave current line
            continue;
          } else if ($ll['src'] == $cv['src'] && (abs(strtotime($ll['time']) - strtotime($cv['time'])) < 120)) {
            // Should replace with current line, 'cos it's have queuename (queue cdr value)
            if ($cv['queue'] != '' && $ll['queue'] == '') {
                $cdr[$li] = $cv;
            } // in other cases just skip this value
            continue;
          }
        }*/
    
        // Just add new line
        $cdr[] = $cv;
    }
    return $cdr;
  }

  public function findById($uid) {
    $sql = "SELECT * FROM queue_cdr WHERE REPLACE(uniqid, '.', '') = '".addslashes($uid)."' order by id desc limit 1";
    $res = $this->db->query($sql);      
    $row = $res->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
        $sql = "SELECT * FROM cdr WHERE REPLACE(uniqueid, '.', '') = '".addslashes($uid)."' order by id desc limit 1";
        $res = $this->db->query($sql);      
        $row = $res->fetch(\PDO::FETCH_ASSOC);
    
        if (!$row) {
            return 0;
        }
    }
    return $row;
  }

  public function getQueueName($name) {
    if (!strlen($name)) return $name;
    if (!isset($this->mapQueues)) {
      $pq = new PBXQueue();
      $l = $pq->fetchList("", 0, 1000);
      $this->mapQueues = [];
      foreach ($l as $e) {  
        $this->mapQueues[$e['name']] = $e['fullname'];
      }
    }
    if (isset($this->mapQueues[$name])) {
      return $this->mapQueues[$name];
    } else {
      return $name;
    }
  }

  public function getAgentName($name) {
    if (!strlen($name)) return $name;
    if (!isset($this->mapUsers)) {
      $pq = new Erpico\User();
      $l = $pq->fetchList("", 0, 1000);
      $this->mapUsers = [];
      foreach ($l as $e) {  
        $this->mapUsers[$e['name']] = $e['fullname'];
      }
    }
    if (isset($this->mapUsers[$name])) {
      return $this->mapUsers[$name];
    } else {
      return $name;
    }
  }
}
