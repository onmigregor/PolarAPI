<?php

use Illuminate\Support\Facades\Route;
use Modules\MasterInvoice\Http\Controllers\MasterInvoiceController;

Route::middleware('internal_key')->post('/master-invoices/sync', [MasterInvoiceController::class, 'syncFromAdmin'])->middleware('internal_key');
