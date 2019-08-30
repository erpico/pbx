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
    if ($_phone[0] == '7') $_phone = "8".substr($_phone, 1);
    return $_phone;
  }

  private function getTableName() {
    return 'cdr';
  }

  public function getReport($filter, $start = 0, $limit = 20, $onlyCount = 0, $serverFooter = 0, $_lcd = 0) {
    $ext = $this->user->allow_extens();
    $extens = $this->utils->sql_allow_extens($ext);

    $que = $this->user->allowed_queues();
    $queues = $this->utils->sql_allowed_queues_for_records($que);

    $users_list = $this->user->getUsersList();

    $qwsql = "";
    $cwsql = "";
        
    if (is_array($filter)) {
      if (isset($filter['time']) && strlen($filter['time'])) {
        $dates = json_decode($filter['time'], 1);
        if ($dates['start']) {
          $d = strtotime($dates['start']);            
          $qwsql .= "AND calldate >= '".date("Y-m-d 00:00:00", $d)."' ";
          $cwsql .= "AND calldate >= '".date("Y-m-d 00:00:00", $d)."' ";
        }
        if ($dates['end']) {
          $d = strtotime($dates['end']);            
          $qwsql .= "AND calldate <= '".date("Y-m-d 23:59:59", $d)."' ";
          $cwsql .= "AND calldate <= '".date("Y-m-d 23:59:59", $d)."' ";
        }
      }  
      if(isset($filter['src']) && strlen($filter['src'])) {
        $qwsql .= "	AND src LIKE '%".addslashes($filter['src'])."%' ";
        $cwsql .= "	AND src LIKE '%".addslashes($filter['src'])."%' ";
      }
      if(isset($filter['dst']) && strlen($filter['dst'])) {
        $cwsql .= "	AND dst LIKE '%".addslashes($filter['dst'])."%' ";
        $qwsql .= "	AND agentdev LIKE '%".addslashes($filter['dst'])."%' ";
      }
      if(isset($filter['agent']) && strlen($filter['agent'])) {
        $cwsql .= "	AND 1 = 0 "; //Ignore CDR
        $qwsql .= "	AND agentname = '".addslashes($filter['agent'])."' ";
      }
      if(isset($filter['queue']) && strlen($filter['queue'])) {
        $cwsql .= "	AND 1 = 0 "; //Ignore CDR
        $qwsql .="	AND queue = '".addslashes($filter['queue'])."' ";
      }
      if(isset($filter['reason']) && strlen($filter['reason'])) {
        // RP: it's more complex query ....
        $wsql = $wsql."	AND reason LIKE '%".addslashes($filter['reason'])."%' ";
      }
      if (isset($filter['talk']) && strlen($filter['talk'])) {
        $talk = json_decode($filter['talk'], 1);
        if ($talk['from']) {
          $qwsql .= "AND talktime >= '".intval($talk['from'])."' ";
          $cwsql .= "AND billsec >= '".intval($talk['from'])."' ";
        }
        if ($talk['to']) {
          $qwsql .= "AND talktime <= '".intval($talk['to'])."' ";
          $cwsql .= "AND billsec <= '".intval($talk['to'])."' ";
        }
      }
      if (isset($filter['hold']) && strlen($filter['hold'])) {
        $hold = json_decode($filter['hold'], 1);
        if ($hold['from']) {
          $qwsql .= "AND holdtime >= '".intval($hold['from'])."' ";
          $cwsql .= "AND duration - billsec >= '".intval($hold['from'])."' ";
        }
        if ($hold['to']) {
          $qwsql .= "AND holdtime <= '".intval($hold['to'])."' ";
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
      $sql = "SELECT SUM(n) FROM (SELECT SUM(n) AS n FROM (SELECT COUNT(uniqid) AS n FROM queue_cdr WHERE 1=1 $queues $qwsql GROUP BY uniqid) as u UNION SELECT COUNT(uniqueid) AS n FROM cdr WHERE 1=1 $extens $cwsql) as a";            
      $res = $this->db->query($sql);      
      $row = $res->fetch(PDO::FETCH_NUM);
      //die($sql);
      return $row[0]; 
    }

    
    if (intval($serverFooter)) {
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
              FROM queue_cdr WHERE 1=1 $queues $qwsql GROUP BY uniqid
          UNION 
          SELECT               
              SUM(duration - billsec) AS sum_billsec,
              SUM(IF(disposition = 'ANSWERED',billsec,0)) AS sum_duration,
              COUNT(IF(disposition = 'ANSWERED',1,NULL)) AS count_answered,
              SUM(IF(disposition = 'ANSWERED', billsec,0)) AS sum_answered
              FROM cdr WHERE 1=1 $extens $cwsql
          ) as a      
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
                  ANY_VALUE(calldate) AS calldate, 
                  ANY_VALUE(src) AS src, 
                  ANY_VALUE(agentdev) AS dst, 
                  ANY_VALUE(queue) AS queue, 
                  ANY_VALUE(reason) AS reason, 
                  MAX(holdtime) AS holdtime, 
                  MAX(talktime) AS talktime, 
                  uniqid, 
                  ANY_VALUE(agentname) AS agentname
        FROM queue_cdr WHERE 1=1 $queues $qwsql AND calldate >= '".date('Y-m-d H:i:s', $fcd)."' AND calldate <= '".date('Y-m-d H:i:s', $lcd)."' GROUP BY uniqid HAVING MAX(id)
        UNION
        SELECT calldate, src, dst, name, disposition, duration - billsec, billsec, uniqueid AS uniqid, '' 
        FROM cdr WHERE 1=1 $extens $cwsql AND calldate >= '".date('Y-m-d H:i:s', $fcd)."' AND calldate <= '".date('Y-m-d H:i:s', $lcd)."' 
        ) AS a ORDER BY calldate DESC ";

        die ($sql);
      
      /*if (isset($start) && isset($limit)){
        $sql .= " LIMIT ".intval($start).", ".intval($limit);
      }*/
      //$sql .= " LIMIT 100"; // No more for now
      $cdr = [];                

      $res = $this->db->query($sql);
      //die($sql);
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
          'queue' => $queue,
          'reason' => $reason,
          'answered' => $answered,
          'direction' => $direction,
          'agent' => $agent,
          'hold' => intval($hold),
          'talk' => intval($talk)
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
}
