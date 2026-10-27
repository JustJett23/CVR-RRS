<?php

use App\Mcp\Servers\RrsHelpServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/rrs-help', RrsHelpServer::class)
    ->middleware('throttle:30,1');
