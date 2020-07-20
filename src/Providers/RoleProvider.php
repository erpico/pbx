<?php

use Erpico\User;
use Psr\Http\Message\ServerRequestInterface;
use Tkhamez\Slim\RoleAuth\RoleProviderInterface;

class RoleProvider implements RoleProviderInterface
{
  
  private $container;
  
  public function __construct($container)
  {
    $this->container = $container;
  }
  
  
  /**
   * @param ServerRequestInterface $request
   *
   * @return array
   */
  public function getRoles(ServerRequestInterface $request)
  {
    $result = ['guest'];
    
    /** @var User $user */
    $user = new User($this->container->get('db'));
    if ($user && $user->isAuth()) {
      $userRoles = $user->getUserRoles();
      $result = $userRoles ? $userRoles : ['user'];
    }
    
    return $result;
  }
  
  public static function isHaveAllowedRole(array $roles, array $allowedRoles)
  {
    foreach ($roles as $role) {
      if (in_array($role, $allowedRoles)) {
        return TRUE;
      }
    }
    
    return FALSE;
  }
}
