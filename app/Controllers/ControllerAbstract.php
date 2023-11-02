<?php namespace GaletteTelemetry\Controllers;

use Psr\Container\ContainerInterface;
use Slim\Psr7\Response;
use Slim\Routing\RouteParser;
use Slim\Views\Twig;

abstract class ControllerAbstract
{

   /**
   * Slim Container
   *
   * @var ContainerInterface
   */
    protected ContainerInterface $container;

    protected Twig $view;

    protected RouteParser $routeparser;

   /**
   * Controller constructor
   *
   * @param ContainerInterface $container
   */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->view = $container->get(Twig::class);
        $this->routeparser = $container->get(RouteParser::class);

        unset($container);
    }

    /**
     * Get a JSON response
     *
     * @param Response $response Response instance
     * @param array<mixed, mixed>    $data     Data to send
     * @param int      $status   HTTP status code
     *
     * @return Response
     */
    protected function withJson(Response $response, array $data, int $status = 200): Response
    {
        $response = $response->withStatus($status);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data));
        return $response;
    }
}
