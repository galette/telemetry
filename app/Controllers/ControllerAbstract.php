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
    protected $container;

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
   * Get Slim Container
   *
   * @return ContainerInterface
   */
    protected function getContainer()
    {
        return $this->container;
    }

   /**
   * Get Service From Container
   *
   * @param string $service
   * @return mixed
   */
    protected function getService($service)
    {
        return $this->container->get($service);
    }

   /**
   * Get Request
   *
   * @return Request
   */
    protected function getRequest()
    {
        return $this->container->request;
    }

   /**
   * Get Response
   *
   * @return Response
   */
    protected function getResponse()
    {
        return $this->container->get('response');
    }

   /**
   * Get Twig Engine
   *
   * @return Twig
   */
    /*protected function getView()
    {
        return $this->container->get(Twig::class);
    }*/

   /**
   * Render view
   *
   * @param string $template
   * @param array $data
   * @return string
   */
    /*protected function render($template, $data = [])
    {
        return $this->getView()->render($this->getResponse(), $template, $data);
    }*/

    /**
     * Get a JSON response
     *
     * @param Response $response Response instance
     * @param array    $data     Data to send
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
