<?php

class PBXCdr {
  protected $db;
  /** @var \Erpico\User $user */
  private $user;

  private string $direction;
  private int $answered;
  private int $missed;
  /** @var Erpico\Utils $utils */
  private $utils;
  private array $mapQueues;
  private array $mapUsers;

  public function __construct(string $direction = '', int $answered = -1, int $missed = 0) {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];

    $this->direction = $direction;
    $this->answered = $answered;
    $this->missed = $missed;

    $this->user = $container['auth'];//new Erpico\User($this->db);
    $this->utils = new Erpico\Utils();
  }
  
  private function normalizePhone(&$_phone) {
    $_phone = preg_replace("/[^\d]/", "", trim($_phone));
    if (isset($_phone[0]) && $_phone[0] == '7' && strlen($_phone) > 8) $_phone = "8" . substr($_phone, 1);
    if (strlen($_phone) == 10) $_phone = "8" . $_phone;
    return $_phone;
  }

  private function getTableName() {
    return 'cdr';
  }

  public function getReportsByUid($id, $main = null) {
    $sql =  "SELECT 
          ".($main ? 'a.' : '')."calldate AS time,
          ".($main ? 'a.' : '')."src, 
          ".($main ? 'a.' : '')."agentdev AS dst,
          ".($main ? 'a.' : '')."queue, 
          ".($main ? 'a.' : '')."reason,
          ".($main ? 'a.' : '')."holdtime AS hold, 
          ".($main ? 'a.' : '')."talktime AS talk,
          ".($main ? 'a.' : '')."uniqid, 
          fullname as agentname,
          ".($main ? 'a.' : '')."queue,
          ".($main ? 'a.' : '')."channel,
          ".($main ? 'a.' : '')."dstchannel,
          ".($main ? 'a.' : '')."userfield 
          FROM queue_cdr ".($main ? 'a ' : '')
        .($main ? 'LEFT OUTER JOIN queue_cdr b ON a.uniqid = b.uniqid AND a.id < b.id ' : '')
        ."LEFT JOIN acl_user on (".($main ? 'a.' : '')."agentname = acl_user.name)
    WHERE ".($main ? 'a.' : '')."uniqid = '{$id}' ".($main ? 'AND b.uniqid IS NULL ' : '')."
     UNION ALL
    SELECT calldate AS time, src, dst, cdr.name, disposition AS reason, duration - billsec AS hold , billsec AS talk, uniqueid AS uniqid, fullname as agentname, '', channel, dstchannel, userfield
    FROM cdr 
    LEFT JOIN acl_user on (SUBSTRING(channel,POSITION('/' IN channel)+1,LENGTH(channel)-POSITION('-' IN REVERSE(channel))-POSITION('/' IN channel)) = acl_user.name)
    WHERE uniqueid = '{$id}'";
    $result_cdr = $this->db->query($sql);
    $cdr = $result_cdr->fetchAll(\PDO::FETCH_ASSOC);

    return $cdr;      
  }

  public function getSyncByUid($id) {
    $sql = "SELECT id, sync_time, u_id, status, call_id, call_time, result FROM phc.btx_call_sync WHERE u_id='".$id."'";
    $res = $this->db->query($sql);

    $syncCalls = [];
    while ($row = $res->fetch()) {
      $syncCalls[] = $row;
    }

    return $syncCalls;
  }

  public function getReport($filter, $start = 0, $limit = 20, $onlyCount = 0, $serverFooter = 0, $_lcd = 0)
  {
    $extens = "";
    $queues = "";

    if (isset($_SERVER['REMOTE_ADDR'])) {
      $ext = $this->user->allow_extens();
      $extens = $this->utils->sql_allow_extens($ext);


      $que = $this->user->allowed_queues();
      $queues = $this->utils->sql_allowed_queues_for_records($que);
    }
    $users_list = $this->user->getUsersList();

    $qwsql = "";
    $cwsql = "";

    $timeisset = 0;
    if ($this->user->getId()) {
      $userPhone = addslashes($this->user->getPhone($this->user->getId()));
      $userName = addslashes($this->user->getInfo()['name']);
     // allow you to see only yours calls
      $isCanSeeOthers = in_array('phc.reports', $this->user->getUserRoles()) || in_array('erpico.admin', $this->user->getUserRoles());
      if (isset($_SERVER['REMOTE_ADDR'])) {
        if (!$isCanSeeOthers) {
          $cwsql .= "	AND (cdr.src = '" . $userPhone . "' OR cdr.dst = '" . $userPhone . "') "; //Ignore CDR
          $qwsql .= "	AND ( a.agentname = '" . $userName . "' OR a.src = '" . $userPhone . "' OR a.agentdev  = '" . $userPhone . "')";
        }
      }
    }

    if ($this->missed) $filter['reason'] = 'ABANDON';

    if (is_array($filter)) {
      if (isset($filter['time']) && strlen($filter['time'])) {
        $dates = json_decode($filter['time'], 1);

        if ($dates['start']) {
          try {
            $d = new DateTime($dates['start']);
            $qwsql .= "AND a.calldate >= '".$d->format("Y-m-d H:i:00")."' ";
            $cwsql .= "AND calldate >= '".$d->format("Y-m-d H:i:00")."' ";
            $timeisset++;
          }catch (\Exception $e) {
            // Just ignore time filter
          }
        }
        if ($dates['end']) {
          try {
            $d = new DateTime($dates['end']);
            if ($d->format("H") == 0 && $d->format("i") == 0) {
              $d->modify('+1 day')->modify('-1 sec');
            }
            $qwsql .= "AND a.calldate <= '".$d->format("Y-m-d H:i:59")."' ";
            $cwsql .= "AND calldate <= '".$d->format("Y-m-d H:i:59")."' ";
            $timeisset++;
          } catch (\Exception $e) {
            // Just ignore time filter
          }
        } /*else {
          $qwsql .= "AND a.calldate <= '".$d->format("Y-m-d 23:59:59")."'";
          $cwsql .= "AND calldate <= '".$d->format("Y-m-d 23:59:59")."'";
          $timeisset++;
        }*/
      }
      if(isset($filter['userfield']) && strlen($filter['userfield'])) {
        
        $qwsql .= "	AND a.userfield LIKE '%".addslashes($filter['userfield'])."%' ";
        $cwsql .= "	AND userfield LIKE '%".addslashes($filter['userfield'])."%' ";
      }
      if(isset($filter['src']) && strlen($filter['src'])) {
        /*if ($isCanSeeOthers) {
          $qwsql .= "	AND (a.src = '".$userPhone."' OR a.agentdev LIKE '".$userPhone."')";
          $cwsql .= "	AND (src = '".$userPhone."' OR dst = '".$userPhone."' )";
        } else {*/
          $qwsql .= "	AND (a.src LIKE '%".addslashes($filter['src'])."%' OR a.agentdev LIKE '%".addslashes($filter['src'])."%')";
          $cwsql .= "	AND (src LIKE '%".addslashes($filter['src'])."%' OR dst LIKE '%".addslashes($filter['src'])."%' )";
        //}
      }
      if(isset($filter['dst']) && strlen($filter['dst'])) {
        $cwsql .= "	AND dst LIKE '%".addslashes($filter['dst'])."%' ";
        $qwsql .= "	AND a.agentdev LIKE '%".addslashes($filter['dst'])."%' ";
      }
      if(isset($filter['agent']) && strlen($filter['agent'])) {
        /*if ($isCanSeeOthers) {
          $cwsql .= "	AND (acl_user.name =  '".addslashes($filter['agent'])."'  OR acl_use.name = '".$userName."')";
          $qwsql .= "	AND (a.agentname = '".addslashes($filter['agent'])."' OR a.agentname = '".$userName."' )";
        } else {*/
          $cwsql .= "	AND acl_user.name=  '".addslashes($filter['agent'])."' ";
          $qwsql .= "	AND a.agentname = '".addslashes($filter['agent'])."' ";
        //}
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
      if ($this->direction === 'in') {
          $qwsql .= " AND LENGTH(a.src) > 4 ";
          $cwsql .= " AND LENGTH(src) > 4 ";
      }
      if ($this->direction === 'out') {
          $qwsql .= " AND LENGTH(a.src) <= 4 ";
          $cwsql .= " AND LENGTH(src) <= 4 ";
      }
        if ($this->answered === 1) {
            $qwsql .= " \nAND a.reason NOT REGEXP 'ABANDON|BUSY|EXITWITHTIMEOUT|FAILED|NO ANSWER|RINGDECLINE|RINGNOANSWER' ";
            $cwsql .= " \nAND disposition NOT REGEXP 'ABANDON|BUSY|EXITWITHTIMEOUT|FAILED|NO ANSWER|RINGDECLINE|RINGNOANSWER' ";
        }
        if ($this->answered === 0) {
            $qwsql .= " \nAND a.reason REGEXP 'ABANDON|BUSY|EXITWITHTIMEOUT|FAILED|NO ANSWER|RINGDECLINE|RINGNOANSWER' ";
            $cwsql .= " \nAND disposition REGEXP 'ABANDON|BUSY|EXITWITHTIMEOUT|FAILED|NO ANSWER|RINGDECLINE|RINGNOANSWER' ";
        }
    }

    if (intval($onlyCount)) {
      if ($timeisset != 2) return 100000; // Return infinite for scrolling
      $sql = "SELECT count(*) FROM (
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
        UNION
        SELECT calldate, src, dst, cdr.name, disposition, duration - billsec, billsec, uniqueid AS uniqid, acl_user.name, userfield        
        FROM cdr 
        LEFT JOIN cfg_user_setting ON (cfg_user_setting.val = SUBSTRING(channel,POSITION('/' IN channel)+1,LENGTH(channel)-POSITION('-' IN REVERSE(channel))-POSITION('/' IN channel)) AND cfg_user_setting.handle = 'cti.ext')
        LEFT JOIN acl_user ON (acl_user.id = cfg_user_setting.acl_user_id)
        WHERE 1=1 $extens $cwsql 
        ) AS c ORDER BY calldate DESC ";
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
              FROM cdr 
              LEFT JOIN cfg_user_setting ON (cfg_user_setting.val = SUBSTRING(channel,POSITION('/' IN channel)+1,LENGTH(channel)-POSITION('-' IN REVERSE(channel))-POSITION('/' IN channel)) AND cfg_user_setting.handle = 'cti.ext')
              LEFT JOIN acl_user ON (acl_user.id = cfg_user_setting.acl_user_id)
              WHERE 1=1 $extens $cwsql
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
        SELECT calldate, src, dst, cdr.name, disposition, duration - billsec, billsec, uniqueid AS uniqid, acl_user.name, userfield        
        FROM cdr 
        LEFT JOIN cfg_user_setting ON (cfg_user_setting.val = SUBSTRING(channel,POSITION('/' IN channel)+1,LENGTH(channel)-POSITION('-' IN REVERSE(channel))-POSITION('/' IN channel)) AND cfg_user_setting.handle = 'cti.ext')
        LEFT JOIN acl_user ON (acl_user.id = cfg_user_setting.acl_user_id)
        WHERE 1=1 $extens $cwsql 
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
    $uid = substr_replace($uid, '.', 10, 0);
    $sql = "SELECT agentname, calldate, uniqid, src FROM queue_cdr WHERE uniqid = '".addslashes($uid)."' order by id desc limit 1";
    $res = $this->db->query($sql);      
    $row = $res->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
        $sql = "SELECT * FROM cdr WHERE uniqueid = '".addslashes($uid)."' order by id desc limit 1";
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

  public function getUnSynchronizedCdrs($start, $end, $dir = null, $parity = null) {
    $cdrs = [];

    $sql = "SELECT calldate as time, src, dst, queue, reason, holdtime as hold, talktime as talk, uniqid, agentname, userfield, status FROM (
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
      FROM queue_cdr a LEFT OUTER JOIN queue_cdr b ON a.uniqid = b.uniqid AND a.id < b.id WHERE b.uniqid IS NULL  AND a.calldate >= '$start' AND a.calldate <= '$end'  
      UNION
      SELECT calldate, src, dst, cdr.name, disposition, duration - billsec, billsec, uniqueid AS uniqid, acl_user.name, userfield        
      FROM cdr 
      LEFT JOIN cfg_user_setting ON (cfg_user_setting.val = SUBSTRING(channel,POSITION('/' IN channel)+1,LENGTH(channel)-POSITION('-' IN REVERSE(channel))-POSITION('/' IN channel)) AND cfg_user_setting.handle = 'cti.ext')
      LEFT JOIN acl_user ON (acl_user.id = cfg_user_setting.acl_user_id)
      WHERE 1=1  AND calldate >= '$start' AND calldate <= '$end'  
      ) AS c 
      LEFT JOIN btx_call_sync ON (uniqid = btx_call_sync.u_id) 
      WHERE status IS NULL 
      " . ($dir ? "AND CHAR_LENGTH($dir) = 11" : "") . "
      AND reason NOT IN ('EXITWITHTIMEOUT', 'RINGNOANSWER', 'RINGDECLINE')
      " . ($parity ? (($parity === "even") ? "AND uniqid %2 < 1" : "AND uniqid %2 >= 1" ) : "") . " 
      ORDER BY calldate DESC";
    $res = $this->db->query($sql);
    while ($row = $res->fetch()) {
      $cdrs[] = $row;
    }
    return $cdrs;
  }

  public function findRecord(string $uid, $stringFormat = 0) {
    $uid = str_replace(".mp3", "", $uid);
    $uid = str_replace(".", "", $uid);

    $cdr = new PBXCdr();
    $row = $cdr->findById($uid);
    if (!is_array($row)) {
      return ['result' => false, 'errorType' => 'database'];
    }

    $filename = "";

    if (isset($row['agentname'])) {
      // Queue
      $date = str_replace(" ", "-", $row['calldate']);

      $agent = str_replace("/", "-", $row['agentname']);

      $uniqid = $row['uniqid'];
      $cid = $row['src'];

      $fname = "$date-$cid-$agent-q-$uniqid.wav";
      $path_parts = pathinfo($fname);

      $filename = "/var/spool/asterisk/monitor/queues/" . substr($fname, 0, 10) . "/" . substr($fname, 11, 2) . "/" . $path_parts['dirname'] . '/' . $path_parts['filename'];

      if (file_exists($filename . ".WAV")) {
        $filename = $filename . ".WAV";
      } else if (file_exists($filename . ".wav")) {
        $filename = $filename . ".wav";
      } else if (file_exists($filename . ".mp3")) {
        $filename = $filename . ".mp3";
      } else {
        $filename = "";
      }
    }

    if ($filename == "") {
      // Regular
      $date = str_replace(" ", "-", $row['calldate']);
      $time = strtotime($row['calldate']);
      $uniqid = $row['uniqueid']; //substr($row['uniqueid'], 0, /*-2*/0);
      if (strlen($uniqid) == 0) $uniqid = $row['uniqid'];
      $src = $row['src'];
      $files = glob("/var/spool/asterisk/monitor/" . date('Y-m-d', $time) . "/" . date('H', $time) . "/*-" . $uniqid . "*");
      if (!is_array($files) || !count($files)) {
        // Last change....
        $files = glob("/var/spool/asterisk/monitor/" . date('Y-m-d', $time) . "/" . date('H', $time) . "/*-$src-*" . substr($uniqid, 0, -2) . "*");
        if (!is_array($files) || !count($files)) {
          $files = glob("/var/spool/asterisk/monitor/queues/" . date('Y-m-d', $time) . "/" . date('H', $time) . "/*-$src-*" . substr($uniqid, 0, -2) . "*");
          if (!is_array($files) || !count($files)) {
            return ['result' => false, 'errorType' => 'filesystem'];
          }
        }
      }
      $filename = $files[0];

      if (!file_exists($filename)) {
        return ['result' => false, 'errorType' => 'filesystem'];
      }
    }

    if ($stringFormat) {
      return [
        'file' => file_get_contents($filename),
        'filename' => $filename
      ];
    }

    $fh = fopen($filename, 'rb');
    $stream = new Slim\Http\Stream($fh);
    return ['result' => true, 'stream' => $stream, 'filename' => $filename];
  }
}
