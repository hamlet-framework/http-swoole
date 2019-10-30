<?php

namespace Hamlet\Http\Swoole\Bootstraps;

use Hamlet\Http\Applications\AbstractApplication;
use Hamlet\Http\Swoole\Requests\SwooleRequest;
use Hamlet\Http\Swoole\Writers\SwooleResponseWriter;
use swoole_http_request;
use swoole_http_response;
use swoole_http_server;

final class SwooleBootstrap
{
    /** @var AbstractApplication|null */
    private static $application;

    private function __construct()
    {
    }

    /**
     * @param string $host
     * @param int $port
     * @param callable $generator
     * @psalm-param callable():AbstractApplication $generator
     * @return void
     */
    public static function run(string $host, int $port, callable $generator)
    {
        $server = new swoole_http_server($host, $port, SWOOLE_BASE);
        $server->set([
            'worker_num' => swoole_cpu_num()
        ]);

        $server->on('workerStart', function () use ($generator) {
            self::$application = $generator();
        });

        $server->on('request', function (swoole_http_request $swooleRequest, swoole_http_response $swooleResponse) {
            if (self::$application === null) {
                return;
            }
            $request  = new SwooleRequest($swooleRequest);
            $writer   = new SwooleResponseWriter($swooleResponse);
            $response = self::$application->run($request);

            self::$application->output($request, $response, $writer);
        });

        $server->start();
    }
}
