<?php
namespace App\Http\Controllers;

use App\Services\AccountingExportService;

class AccountingExportController extends Controller
{
    public function payments(AccountingExportService $svc)
    {
        return $svc->paymentsCsv();
    }
}
