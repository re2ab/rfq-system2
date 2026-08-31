<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function casesCsv(): StreamedResponse
    {
        $filename = 'cases-'.date('Ymd-His').'.csv';
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($out, ['case_number','title','status','priority','customer','expert','currency','incoterm','created_at']);
            CaseModel::with(['customer','expert'])->orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->case_number, $c->title, $c->current_status, $c->priority,
                        $c->customer?->name, $c->expert?->name, $c->currency, $c->incoterm,
                        $c->created_at,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
