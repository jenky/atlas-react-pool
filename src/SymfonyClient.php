<?php

declare(strict_types=1);

namespace Jenky\Atlas\Pool\React;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use React\Async;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Response\StreamableInterface;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyResponseInterface;

final class SymfonyClient implements ClientInterface
{
    private HttpClientInterface $client;

    private ResponseFactoryInterface $responseFactory;

    private StreamFactoryInterface $streamFactory;

    private LoopInterface $loop;

    public function __construct(
        ?HttpClientInterface $client = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoopInterface $loop = null
    ) {
        $this->client = $client ?? HttpClient::create();
        $this->responseFactory = $responseFactory ?? Psr17FactoryDiscovery::findResponseFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->loop = $loop ?? Loop::get();
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $body = $request->getBody();

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            $options = [
                'headers' => $request->getHeaders(),
                'body' => $body->getContents(),
            ];

            if ('1.0' === $request->getProtocolVersion()) {
                $options['http_version'] = '1.0';
            }

            return Async\await($this->createResponse(
                $this->client->request($request->getMethod(), (string) $request->getUri(), $options)
            ));
        } catch (TransportExceptionInterface $e) {
            throw $e;
            /* if ($e instanceof \InvalidArgumentException) {
                throw new Psr18RequestException($e, $request);
            }

            throw new Psr18NetworkException($e, $request); */
        }
    }

    public function createResponse(SymfonyResponseInterface $response): PromiseInterface
    {
        $defer = new Deferred();

        $this->loop->futureTick(function () use ($defer, $response) {
            $psrResponse = $this->responseFactory->createResponse($response->getStatusCode());

            foreach ($response->getHeaders(false) as $name => $values) {
                foreach ($values as $value) {
                    try {
                        $psrResponse = $psrResponse->withAddedHeader($name, $value);
                    } catch (\InvalidArgumentException $e) {
                        // ignore invalid header
                    }
                }
            }

            $body = $response instanceof StreamableInterface ? $response->toStream(false) : StreamWrapper::createResource($response, $this->client);
            $body = $this->streamFactory->createStreamFromResource($body);

            // if ($body->isSeekable()) {
            //     try {
            //         $body->seek(0);
            //     } catch (\Throwable $e) {
            //         $defer->reject($e);
            //     }
            // }

            $defer->resolve($psrResponse->withBody($body));
        });

        return $defer->promise();
    }
}
