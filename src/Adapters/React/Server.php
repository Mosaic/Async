<?php

namespace Mosaic\Async\Adapters\React;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Relay\RelayBuilder;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class Server implements \Mosaic\Async\Server
{
    /**
     * @var
     */
    protected $resolver;

    /**
     * @var array
     */
    protected $pipes = [];

    /**
     * @var Emitter
     */
    private $emitter;

    /**
     * @param Emitter $emitter
     */
    public function __construct(Emitter $emitter = null)
    {
        $this->emitter = $emitter ?: new Emitter();

        $this->resolver = function ($class) {
            return new $class;
        };
    }

    /**
     * @param               $port
     * @param string        $host
     * @param callable|null $terminate
     * @return mixed
     */
    public function serve($port, $host = '127.0.0.1', callable $terminate = null)
    {
        $app = function ($request, $response) {

            try {

                //$this->pipe(function ($psr7Request, $psr7Response) use ($response) {
                //    return (new Emitter)->emit($psr7Response, $response);
                //});

                $this->pipe(function (RequestInterface $psr7Request, $psr7Response) {

                    $resolver = $this->resolver;

                    /** @var \Mosaic\Http\Request $mosaicRequest */
                    $mosaicRequest = $resolver(\Mosaic\Http\Request::class);

                    return new Response\JsonResponse([
                        'message' => 'Hello world <3',
                        'bla' => $psr7Request->getParsedBody(),
                        'email' => $mosaicRequest->all()
                    ]);
                });

                $psr7 = new \Mosaic\Http\Adapters\Psr7\Request(new ServerRequest(
                    $_SERVER,
                    $request->getFiles(),
                    $request->getUrl(),
                    $request->getMethod(),
                    $this->createBodyStream($request),
                    $request->getHeaders(),
                    [],
                    $request->getQuery(),
                    $request->getPost(),
                    $request->getHttpVersion()
                ));

                $psr7Response = $this->relay($psr7);

                return $this->emitter->emit($psr7Response, $response);
            } catch (\Throwable $e) {
                var_dump($e);
            }
        };

        $loop   = \React\EventLoop\Factory::create();
        $socket = new \React\Socket\Server($loop);
        $http   = new \React\Http\Server($socket, $loop);

        $http->on('request', $app);
        echo "Server running at http://{$host}:{$port}\n";

        $socket->listen($port);
        $loop->run();
    }

    /**
     * @param array ...$pipes
     * @return \Mosaic\Async\Server
     */
    public function pipe(...$pipes)
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * @param  callable $resolver
     * @return \Mosaic\Async\Server
     */
    public function setResolver(callable $resolver)
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * @param  \Mosaic\Http\Request $request
     * @return ResponseInterface
     */
    protected function relay(\Mosaic\Http\Request $request)
    {
        $relay = (new RelayBuilder($this->resolver))->newInstance($this->pipes);

        $response = $relay(
            $request->toPsr7(),
            $request->prepareResponse()
        );

        return $response;
    }

    /**
     * @param \React\Http\Request $request
     * @return resource
     */
    private function createBodyStream(\React\Http\Request $request)
    {
        $body = fopen('php://temp', 'w+');
        fwrite($body, $request->getBody());
        fseek($body, 0);

        return $body;
    }
}