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
    private function __construct()
    {
    }

    /**
     * @param string $host
     * @param int $port
     * @param AbstractApplication $application
     * @param callable|null $onWorkerStart
     * @return void
     */
    public static function run(string $host, int $port, AbstractApplication $application, callable $onWorkerStart = null)
    {
        $server = new swoole_http_server($host, $port, SWOOLE_BASE);
        $server->set([
            'worker_num' => swoole_cpu_num()
        ]);

        if ($onWorkerStart) {
            $server->on('WorkerStart', function (swoole_http_server $server) use ($onWorkerStart) {
                $onWorkerStart();
            });
        }

        $server->on('request', function (swoole_http_request $swooleRequest, swoole_http_response $swooleResponse) use ($application) {
            $request  = new SwooleRequest($swooleRequest);
            $writer   = new SwooleResponseWriter($swooleResponse);
            $response = $application->run($request);

            $application->output($request, $response, $writer);
        });

        $server->start();
    }
}
