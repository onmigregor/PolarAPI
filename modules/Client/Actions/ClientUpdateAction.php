<?php

namespace Modules\Client\Actions;

use Modules\Client\DataTransferObjects\ClientData;
use Modules\Client\Models\Client;

class ClientUpdateAction
{
    public function execute(Client $client, ClientData $data): Client
    {
        $client->update($data->toArray());
        return $client->refresh();
    }
}
