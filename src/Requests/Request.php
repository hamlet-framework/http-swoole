<?php

namespace Hamlet\Http\Swoole\Requests;

use Hamlet\Http\Message\Stream;
use Hamlet\Http\Message\Uri;
use Hamlet\Http\Requests\RequestTrait;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use SessionHandlerInterface;
use swoole_http_request;

class Request extends \Hamlet\Http\Requests\Request
{
    use RequestTrait;

    public static function fromSwooleRequest(swoole_http_request $request, ?SessionHandlerInterface $sessionHandler = null): self
    {
        $instance = new static;

        $instance->properties['method']          = strtoupper($request->server['request_method'] ?? 'GET');
        $instance->properties['cookieParams']    = &$request->cookie;
        $instance->properties['queryParams']     = &$request->get;
        $instance->properties['parsedBody']      = &$request->post;
        $instance->properties['path']            = strtok((string) $request->server['request_uri'], '?') ?: null;

        $instance->generators['serverParams']    = [[&$instance, 'readServerParamsFromRequest'], &$request];
        $instance->generators['protocolVersion'] = [[&$instance, 'readProtocolVersionFromRequest'], &$request];
        $instance->generators['body']            = [[&$instance, 'readBodyFromRequest'], &$request];
        $instance->generators['headers']         = [[&$instance, 'readHeadersFromServerParams'], &$request];
        $instance->generators['uri']             = [[&$instance, 'readUriFromRequest'], &$request];
        $instance->generators['sessionParams']   = [[&$instance, 'readSessionParamsFromRequest'], &$request, &$sessionHandler];

        // @todo
        $instance->generators['uploadedFiles']   = [[&$instance, 'readUploadedFiles'], &$request];

        return $instance;
    }

    protected function readHeadersFromRequest(swoole_http_request $request)
    {
        // @todo $swooleRequest->header;
        return [];
    }

    protected function readBodyFromRequest(swoole_http_request $request): StreamInterface
    {
        return Stream::fromString($request->rawcontent());
    }

    protected function readProtocolVersionFromRequest(swoole_http_request $request): ?string
    {
        return $swooleRequest->server['server_protocol'] ?? null;
    }

    protected function readSessionParamsFromRequest(swoole_http_request $request, ?SessionHandlerInterface $sessionHandler): ?array
    {
        if ($sessionHandler === null) {
            return null;
        }
        $sessionName = session_name();
        if (isset($swooleRequest->cookie[$sessionName])) {
            $sessionId = $request->cookie[session_name()];
            $data = $sessionHandler->read($sessionId);
            if (!empty($data)) {
                return unserialize($data);
            }
        }
        return [];
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
