<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Returns the fixed sample guide for general Room Reservation System use. Does not access live accounts, rooms, availability, or reservations.')]
#[IsReadOnly]
#[IsIdempotent]
class RrsSampleGuidanceTool extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'GUIDE'
Room Reservation System sample help guide (general instructions only):

- To request a room, open Reservations, choose a room and time, fill in the request form, and submit it. The system checks for conflicts before accepting the request.
- Check Room Availability before submitting. Availability can change, so check again before making a request.
- Use Schedule Timeline to review scheduled activity by room and date.
- A request is not confirmed until its status shows Approved. Review Reservations for your own request status.
- If a request is rejected, read the feedback provided. Contact campus support if the reason is unclear.
- Available changes or cancellation actions depend on request status and account permissions.
- Room Directory lists general room location, floor, capacity, and equipment information. The floor plan helps locate rooms.
- A scheduling conflict means the room is already reserved or unavailable for at least part of the requested period. Try another time or room.
- Account access and actions depend on campus account permissions. Contact campus support for sign-in or access problems.
- Do not share names, student numbers, contact details, credentials, or reservation-specific information with the help assistant.

This guide is synthetic sample content. It does not represent current campus policy or live system data. For information not covered here, direct the user to campus support.
GUIDE);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
