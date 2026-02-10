<?php

namespace Modules\Client\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Client\Models\Client;

class ClientListAllAction
{
    public function execute(): Collection
    {
        return Client::with('region')->orderBy('name')->get();
    }
}
