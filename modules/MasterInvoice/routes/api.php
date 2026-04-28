<?php

use Illuminate\Support\Facades\Route;
use Modules\MasterInvoice\Http\Controllers\MasterInvoiceController;

Route::post('/master-invoices/sync', [MasterInvoiceController::class, 'syncFromAdmin'])->middleware('auth:sanctum');
