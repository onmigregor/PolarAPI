<?php

namespace Modules\Client\Actions;

use Modules\Client\DataTransferObjects\ClientData;
use Modules\Client\Models\Client;

class ClientStoreAction
{
    public function execute(ClientData $data): Client
    {
        return Client::create($data->toArray());
    }
}
