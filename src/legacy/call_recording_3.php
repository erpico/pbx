<?php

namespace Erpico;

class Call_recording {
    private $container;
    private $db;
    private $auth;

    public function __construct($contaiter) {
        $this->container = $contaiter;
        $this->db = $contaiter['db'];
        $this->auth = $contaiter['auth'];
    }

    public function getCall_recording_3($filter, $pos, $count = 20, $stat = 0)
    {

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

      if(is_array($filter) && isset($filter['dst']) && strlen($filter['dst'])) {
        $wsql = $wsql."	AND dst LIKE '%".addslashes($filter['dst'])."%' ";
      }

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

      $utils = new Utils();

      $demand_cdr = "
        SELECT calldate,src,dst,duration AS ratesec,disposition,B.fullname AS fullname
        FROM cdr		
        LEFT JOIN acl_user AS B ON (B.id = (SELECT MAX(A.acl_user_id) FROM cfg_user_setting AS A WHERE ((A.val=src OR A.val=dst) AND A.handle = 'cti.ext')))
        WHERE 1=1 $wsql";

      $ext = $this->auth->allow_extens();
      $extens = $utils->sql_allow_extens($ext);
      $demand_cdr.= $extens;

      $demand_cdr = $demand_cdr.
        " ORDER BY calldate DESC ";

      if ($count) {
        $demand_cdr .= " LIMIT $pos, $count";
      }

//    $users_list = $this->auth->getUsersList();

      $result_cdr = $this->db->query($demand_cdr);
      $cdr_arr = [];
      $i = 0;
      while($cdr = $result_cdr->fetch(\PDO::FETCH_ASSOC)) {
        $cdr_arr[$i] = $cdr;
        $cdr_arr[$i]['user'] = empty($cdr_arr[$i]['fullname']) ? "" : $cdr_arr[$i]['fullname'];;
        $cdr_arr[$i]['calldate2'] = date('d.m.Y H:i:s',strtotime($cdr_arr[$i]['calldate']));
        $i++;
      };
      return $cdr_arr;
    }
}