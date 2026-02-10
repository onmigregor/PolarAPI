<?php

namespace Modules\Client\Actions;

use Modules\Client\Models\Client;

class ClientDeleteAction
{
    public function execute(Client $client): void
    {
        $client->delete();
    }
}
