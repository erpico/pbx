<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RoleProvider;
use Slim\Interfaces\RouteInterface;

/**
 * Denies access to a route if the required role is missing.
 *
 * Sends a HTTP status 403
 *
 * All routes are *allowed* if the "route" attributes is missing in the request object!
 */
class SecureRouteMiddleware
{

  private $container;
  /**
   * @var RoleProvider
   */
  private $roleProvider;
  
    public function __construct(RoleProvider $roleProvider)
    {
      $this->roleProvider = $roleProvider;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $route = $request->getAttribute('route');
        if (! $route instanceof RouteInterface) {
            return $next($request, $response);
        }

        $roles = $this->roleProvider->getRoles($request);
        $allowedRoles = $request->getAttribute('allowedRoles')?? [];
        
        $allowed = true;
        if (is_array($allowedRoles) && $allowedRoles) {
          $allowed = false;
          if (RoleProvider::isHaveAllowedRole($roles, $allowedRoles)) {
            $allowed = true;
          }
        }

        if ($allowed === false) {
          $response = $response->withJson(['result' => FALSE, 'message' => 'Permission denied'], 403);
        }

        return $next($request, $response);
    }
}
