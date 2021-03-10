<?php
/**
 * Created by PhpStorm.
 * User: Drs10
 * Date: 27.05.2020
 * Time: 15:37
 */

namespace App\Middleware;

use App\Services\RequestTypeService;
use Psr\Container\ContainerInterface;
use RoleProvider;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class OnlyAdmin
 * @package App\Middleware
 */
class CheckDBMiddleware
{
  private $container;
  /**
   * @var RoleProvider
   */
  private $roleProvider;
  
  /**
   * OnlyAuthUser constructor.
   *
   * @param $container
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
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
    if (!$this->container['db'] instanceof \PDO) {
      
      if ($this->container[RequestTypeService::class]->getType($request) == RequestTypeService::API_TYPE) {
        $response = $response->withJson(['result' => FALSE, 'message' => 'Bad database connection'], 500);
      } else {
        $response = new \Slim\Http\Response(500);
        return $this->container['renderer']->render($response ,'PDOException.phtml',[]);
      }
    } else {
      $PBXUser = new \Erpico\User();
      $response = $next($request, $response);
    }
    
    return $response;
  }
}
