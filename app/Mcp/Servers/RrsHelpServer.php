<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('RRS Sample Help')]
#[Version('1.0.0')]
#[Instructions('This server only provides general, synthetic guidance about using the Room Reservation System. It has no access to user accounts, room inventory, live availability, or reservation records.')]
class RrsHelpServer extends Server
{
    protected array $tools = [
        RrsSampleGuidanceTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
