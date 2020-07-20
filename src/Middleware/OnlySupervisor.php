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
 * Class OnlySupervisor
 * @package App\Middleware
 */
class OnlySupervisor
{
  private $container;
  /**
   * @var RoleProvider
   */
  private $roleProvider;
  
  private $allowedRoles = ['phc.reports'];
  
  /**
   * OnlyAuthUser constructor.
   *
   * @param $container
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
    $this->roleProvider = $container->get('roleProvider');
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
    if ($this->roleProvider->isHaveAllowedRole($this->roleProvider->getRoles($request), $this->allowedRoles)) {
      $response = $next($request, $response);
    } else {
      $response = $response->withJson(['result' => FALSE, 'message' => 'Permission denied by OnlySupervisor'], 403);
    }
    
    return $response;
  }
}
