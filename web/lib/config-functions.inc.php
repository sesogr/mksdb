<?php
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require_once __DIR__ . '/types.inc.php';
require_once __DIR__ . '/functions.inc.php';
function handleCustomRequest(string $operation, string $tableName, ServerRequestInterface $request, $environment)
{
    if ($operation === 'list' && $tableName === 'search') {
        $environment->search = $request->getQueryParams();
    }
}

function handleCustomResponse(string $operation, string $tableName, ResponseInterface $response, $environment)
{
    if (isset($environment->search['q'])) {
        $factory = new Psr17Factory();
        $config = include __DIR__ . '/../config.inc.php';
        $db = new PDO(
            sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $config['address'],
                $config['port'],
                $config['database'],
                'utf8mb4'
            ),
            $config['username'],
            $config['password']
        );
        $content = json_encode(
            ['records' => array_map(
                function ($x) {
                    $x->id = intval($x->id);
                    return $x;
                },
                gatherSearchResults($environment->search['q'], $db)
            )],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $stream = $factory->createStream($content);
        $stream->rewind();
        return $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withHeader('Content-Length', strlen($content))
            ->withBody($stream);
    }
    return $response;
}

function preventMutationOperations(string $operation, string $tableName)
{
    return $operation === 'list' || $operation === 'read';
}
