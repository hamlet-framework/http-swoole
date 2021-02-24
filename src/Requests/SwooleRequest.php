<?php

namespace Hamlet\Http\Swoole\Requests;

use Hamlet\Http\Message\ServerRequest;
use Hamlet\Http\Message\Stream;
use Hamlet\Http\Message\Uri;
use Hamlet\Http\Requests\Request;
use Hamlet\Http\Requests\RequestTrait;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use swoole_http_request;

class SwooleRequest extends ServerRequest implements Request
{
    use RequestTrait;

    /** @var string|null */
    protected $path;

    public function __construct(swoole_http_request $request)
    {
        parent::__construct();

        $this->method          = strtoupper($request->server['request_method'] ?? 'GET');
        $this->cookieParams    = $request->cookie ?? [];
        $this->queryParams     = $request->get ?? [];
        $this->parsedBody      = $request->post ?? [];
        $this->path            = strtok((string) $request->server['request_uri'], '?') ?: null;

        $this->serverParamsGenerator = function () use ($request) {
            return $this->readServerParametersFromRequest($request);
        };
        $this->protocolVersionGenerator = function () use ($request) {
            return $this->readProtocolVersionFromRequest($request);
        };
        $this->bodyGenerator = function () use ($request) {
            return $this->readBodyFromRequest($request);
        };
        $this->headersGenerator = function () use ($request) {
            return $this->readHeadersFromRequest($request);
        };
        $this->uriGenerator = function () use ($request) {
            return $this->readUriFromRequest($request);
        };
        $this->uploadedFilesGenerator = function () use ($request) {
            return $this->readUploadedFilesFromRequest($request);
        };
    }

    public function getPath(): string
    {
        if ($this->path === null) {
            $this->path = $this->getUri()->getPath();
        }
        return $this->path;
    }

    protected function readHeadersFromRequest(swoole_http_request $request)
    {
        return $request->header;
    }

    protected function readUploadedFilesFromRequest(swoole_http_request $request): array
    {
        // @todo normalize files
        return [];
    }

    protected function readBodyFromRequest(swoole_http_request $request): StreamInterface
    {
        print_r($request->rawContent());
        return Stream::fromString($request->rawContent());
    }

    protected function readProtocolVersionFromRequest(swoole_http_request $request): ?string
    {
        return $swooleRequest->server['server_protocol'] ?? null;
    }

    protected function readServerParametersFromRequest(swoole_http_request $request): array
    {
        return array_filter([
            'SERVER_SOFTWARE'       => $request->server['server_software']      ?? null,
            'SERVER_PROTOCOL'       => $request->server['server_protocol']      ?? null,
            'REQUEST_METHOD'        => $request->server['request_method']       ?? null,
            'REQUEST_TIME'          => $request->server['request_time']         ?? null,
            'REQUEST_TIME_FLOAT'    => $request->server['request_time_float']   ?? null,
            'QUERY_STRING'          => $request->server['query_string']         ?? null,
            'HTTP_ACCEPT'           => $request->header['accept']               ?? null,
            'HTTP_ACCEPT_CHARSET'   => $request->header['accept-charset']       ?? null,
            'HTTP_ACCEPT_ENCODING'  => $request->header['accept-encoding']      ?? null,
            'HTTP_ACCEPT_LANGUAGE'  => $request->header['accept-language']      ?? null,
            'HTTP_CONNECTION'       => $request->header['connection']           ?? null,
            'HTTP_HOST'             => $request->header['host']                 ?? null,
            'HTTP_REFERER'          => $request->header['referer']              ?? null,
            'HTTP_USER_AGENT'       => $request->header['user-agent']           ?? null,
            'REMOTE_ADDR'           => $request->server['remote_addr']          ?? null,
            'REMOTE_HOST'           => $request->server['remote_host']          ?? null,
            'REMOTE_PORT'           => $request->server['remote_port']          ?? null,
            'SERVER_PORT'           => $request->server['server_port']          ?? null,
            'REQUEST_URI'           => $request->server['request_uri']          ?? null,
            'PATH_INFO'             => $request->server['path_info']            ?? null,
            'ORIG_PATH_INFO'        => $request->server['path_info']            ?? null
        ]);
    }

    protected function readUriFromRequest(swoole_http_request $request): UriInterface
    {
        $serverParams = (array) $request->server;

        $builder = Uri::nonValidatingBuilder();
        $builder->withScheme(!empty($serverParams['HTTPS']) && ((string) $serverParams['HTTPS']) !== 'off' ? 'https' : 'http');

        $hasPort = false;
        $hostHeaderParts = explode(':', (string) ($serverParams['host'] ?? 'localhost'));
        $builder->withHost($hostHeaderParts[0]);
        if (isset($hostHeaderParts[1])) {
            $hasPort = true;
            $builder->withPort((int) $hostHeaderParts[1]);
        }

        if (!$hasPort && isset($serverParams['server_port'])) {
            $builder->withPort((int) $serverParams['server_port']);
        }

        $hasQuery = false;
        if (isset($serverParams['request_uri'])) {
            $requestUriParts = explode('?', (string) $serverParams['request_uri']);
            /** @var Uri $uri */
            $builder->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $builder->withQuery($requestUriParts[1]);
            }
        }

        if (!$hasQuery && isset($serverParams['query_string'])) {
            $builder->withQuery((string) $serverParams['query_string']);
        }

        return $builder->build();
    }
}
