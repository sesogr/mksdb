<?php
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

function interceptSearchRequest(string $operation, string $tableName, ResponseInterface $response, $environment)
{
    if ($tableName === 'search') {
        $factory = new Psr17Factory();
        $content = 'abc';
        $stream = $factory->createStream($content);
        $stream->rewind();
        return $factory->createResponse(200)
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withHeader('Content-Length', strlen($content))
            ->withBody($stream)
        ;
    }
    return $response;
}

function preventMutationOperations(string $operation, string $tableName)
{
    return $operation === 'list' || $operation === 'read';
}
