<?php

namespace Mosaic\Async\Adapters\React;

use Psr\Http\Message\ResponseInterface;
use React\Http\Response;

class Emitter
{

    /**
     * @param ResponseInterface $psr7
     * @param Response          $react
     */
    public function emit(ResponseInterface $psr7, Response $react)
    {
        if (!$psr7->hasHeader('Content-Type')) {
            $psr7 = $psr7->withHeader('Content-Type', 'text/html');
        }

        $react->writeHead(
            $psr7->getStatusCode(),
            $psr7->getHeaders()
        );

        $body = $psr7->getBody();
        $body->rewind();

        $react->end($body->getContents());
        $body->close();
    }
}