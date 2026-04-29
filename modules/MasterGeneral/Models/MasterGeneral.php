<?php

namespace Modules\MasterGeneral\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterGeneral extends Model
{
    use SoftDeletes;

    protected $table = 'master_generals';
    protected $primaryKey = 'reaCode';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'reaCode',
        'reaName',
        'reaNoVisit',
        'reaNoSale',
        'reaNoCollect',
        'reaNoDelivery',
        'reaNoReturnPickUp',
        'reaDeliveryDifference',
        'reaReturn',
        'reaDamageReturn',
        'reaNoInventory',
        'reaPercentageAcknoledgment',
        'reaStatus',
        'reaAsset',
        'reaBouncedCheck',
        'reaNoCollectionArdocument',
        'reaNoBarCodeReading',
        'reaHeader',
        'reaCancelInvoice',
        'reaHos',
        'deleted',
    ];

    protected $casts = [
        'reaNoVisit' => 'boolean',
        'reaNoSale' => 'boolean',
        'reaNoCollect' => 'boolean',
        'reaNoDelivery' => 'boolean',
        'reaNoReturnPickUp' => 'boolean',
        'reaDeliveryDifference' => 'boolean',
        'reaReturn' => 'boolean',
        'reaDamageReturn' => 'boolean',
        'reaNoInventory' => 'boolean',
        'reaStatus' => 'boolean',
        'reaAsset' => 'boolean',
        'reaBouncedCheck' => 'boolean',
        'reaNoCollectionArdocument' => 'boolean',
        'reaNoBarCodeReading' => 'boolean',
        'reaCancelInvoice' => 'boolean',
        'reaHos' => 'boolean',
        'deleted' => 'boolean',
    ];
}
