<?php

namespace Hamlet\Http\Swoole\Writers;

use Hamlet\Http\Writers\ResponseWriter;
use swoole_http_response;

class SwooleResponseWriter implements ResponseWriter
{
    /** @var swoole_http_response */
    private $response;

    public function __construct(swoole_http_response $response)
    {
        $this->response = $response;
    }

    public function status(int $code, string $line = null)
    {
        $this->response->status((string) $code, $line);
    }

    public function header(string $key, string $value)
    {
        // @bug not sure but for whatever reason swoole dislikes this header
        if (strtolower($key) == 'content-length') {
            return;
        }
        $this->response->header($key, $value);
    }

    public function writeAndEnd(string $payload)
    {
        $this->response->end($payload);
    }

    public function end()
    {
        $this->response->end();
    }

    public function cookie(string $name, string $value, int $expires, string $path, string $domain = '', bool $secure = false, bool $httpOnly = false)
    {
        $this->response->cookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
    }
}
