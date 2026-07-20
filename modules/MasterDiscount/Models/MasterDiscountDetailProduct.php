<?php

namespace Modules\MasterDiscount\Models;

use Illuminate\Database\Eloquent\Model;

class MasterDiscountDetailProduct extends Model
{
    protected $table = 'discount_detail_products';
    protected $primaryKey = 'dlp_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'dlp_code',
        'dis_code',
        'did_code',
        'pro_code',
        'cl1code',
        'cl2code',
        'cl3code',
        'cl4code',
        'pro_code_ingredient',
        'quo_code',
        'con_code',
        'unt_code',
        'dlp_required',
        'dlp_discount',
        'dlp_discount_percentage',
        'dlp_discount_amount',
        'dlp_required_quantity',
        'dlp_required_quantity_amount',
        'dlp_base_from_taken_for_discou',
        'dlp_pallet_discount',
        'dlp_minimum',
        'dlp_quantity1',
        'dlp_min_discount1',
        'dlp_quantity2',
        'dlp_min_discount2',
        'dlp_quantity3',
        'dlp_min_discount3',
        'dlp_quantity4',
        'dlp_min_discount4',
        'dlp_quantity5',
        'dlp_min_discount5',
        'dlp_max_discount1',
        'dlp_max_discount2',
        'dlp_max_discount3',
        'dlp_max_discount4',
        'dlp_max_discount5',
        'dlp_max_discount6',
        'dlp_global_discount_amount',
        'source_file',
        'saved_at',
    ];
}
