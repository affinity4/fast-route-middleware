<?php
declare(strict_types=1);

namespace Affinity4\Middleware\FastRoute;

use FastRoute\Dispatcher;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FastRouteMiddleware implements MiddlewareInterface
{
    /**
     * @var \FastRoute\Dispatcher
     */
    private $router;

    /**
     * @var string
     */
    private $attribute = 'request-handler';

    /**
     * Constructor
     * 
     * Set the Dispatcher instance and optionally the response factory to return the error responses
     * 
     * @param \FastRoute\Dispatcher
     * @param \Psr\Http\Message\ResponseFactoryInterface
     */
    public function __construct(Dispatcher $router, ResponseFactoryInterface $HttpFactory)
    {
        $this->router = $router;
        $this->HttpFactory = $HttpFactory;
    }

    /**
     * Attribute
     * 
     * Set the attribute name to store handler reference
     * 
     * @param string $attribute
     * 
     * @return self
     */
    public function attribute(string $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * Process
     * 
     * Process a server request and return a response
     * 
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * 
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->router->dispatch($request->getMethod(), rawurldecode($request->getUri()->getPath()));

        switch ($route[0]) {
            case Dispatcher::NOT_FOUND:
                $Response = $handler->handle($request);
        
                return $Response->withStatus(404);

            break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $route[1];

                $Response = $handler->handle($request);
        
                return $Response->withStatus(405)->withHeader('Allow', implode(', ', $route[1]));
            break;
            case Dispatcher::FOUND:
                foreach ($route[2] as $name => $value) {
                    $request = $request->withAttribute($name, $value);
                }

                $request = $this->setHandler($request, $route[1]);

                $Response = $handler->handle($request);
        
                return $Response->withBody($this->HttpFactory->createStream($Response->getBody() . $route[1]($request)->getBody()));
            break;
        }
    }

    /**
     * Set Handler
     * 
     * Set the handler reference on the request
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param mixed $handler
     * 
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function setHandler(ServerRequestInterface $request, $handler): ServerRequestInterface
    {
        return $request->withAttribute($this->attribute, $handler);
    }
}
