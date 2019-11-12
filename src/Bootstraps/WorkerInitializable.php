<?php

namespace Hamlet\Http\Swoole\Bootstraps;

interface WorkerInitializable
{
    /**
     * @return void
     */
    public function init();
}
