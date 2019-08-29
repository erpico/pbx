<?php

namespace Erpico;

class users {
  private $container;
  private $db;
  private $auth;

  public function __construct($contaiter) {
    $this->container = $contaiter;
    $this->db = $contaiter['db'];
    $this->auth = $contaiter['auth'];
  }

  public function getUsers($filter, $pos, $count = 20, $onlycount = 0) {
    
    // Here need to add permission checkers and filters
    
    
    return $final_result;
  }
}