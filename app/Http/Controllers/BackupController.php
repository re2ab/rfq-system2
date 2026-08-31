<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function download(): StreamedResponse
    {
        $tables = [
            'users','organizations','contacts','cases','case_status_histories','case_activities',
            'tasks','documents','document_revisions','deliveries','receivables','payments',
            'emails','templates','modules','number_sequences','attachments','app_notifications',
        ];

        $payload = ['exported_at' => now()->toIso8601String(), 'tables' => []];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $payload['tables'][$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $filename = 'rfq-backup-'.date('Ymd-His').'.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function importForm()
    {
        return view('backup.import');
    }

    public function import(\Illuminate\Http\Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:json,txt|max:51200']);
        $json = json_decode(file_get_contents($request->file('file')->getRealPath()), true);
        if (!is_array($json) || empty($json['tables'])) {
            return back()->withErrors(['file' => 'فایل پشتیبان نامعتبر است.']);
        }
        // Only restore non-auth critical lookup tables safely
        $allowed = ['templates','modules','number_sequences','custom_field_definitions','app_settings'];
        $restored = 0;
        foreach ($allowed as $table) {
            if (empty($json['tables'][$table])) continue;
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) continue;
            foreach ($json['tables'][$table] as $row) {
                unset($row['id']);
                try {
                    \Illuminate\Support\Facades\DB::table($table)->insert($row);
                    $restored++;
                } catch (\Throwable $e) {
                    // skip duplicates
                }
            }
        }
        return back()->with('success', "تعداد $restored ردیف بازیابی شد (جداول امن: قالب، ماژول، تنظیمات، فیلد سفارشی).");
    }
}
