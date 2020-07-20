<?php
/**
 * Created by PhpStorm.
 * User: Drs10
 * Date: 27.05.2020
 * Time: 15:37
 */

namespace App\Middleware;

use Psr\Container\ContainerInterface;
use RoleProvider;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class OnlyAdmin
 * @package App\Middleware
 */
class SetRoles
{
  
  private $roles;
  
  /**
   * OnlyAuthUser constructor.
   *
   * @param $container
   */
  public function __construct($roles)
  {
    $this->roles = $roles;
  }
  
  /**
   * @param Request  $request
   * @param Response $response
   * @param callable $next
   *
   * @return Response
   * @throws \RuntimeException
   */
  public function __invoke(Request $request, Response $response, callable $next)
  {
    $request = $request->withAttribute('allowedRoles', $this->roles);
    $response = $next($request, $response);
    
    return $response;
  }
}
