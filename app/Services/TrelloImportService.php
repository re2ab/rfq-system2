<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Import a Trello board JSON export into RFQ cases (best-effort mapping).
 * Export from Trello: board menu → More → Print and export → Export as JSON
 */
class TrelloImportService
{
    public function importFromJson(string $json, ?int $userId = null): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['ok' => false, 'message' => 'JSON نامعتبر'];
        }

        $lists = collect($data['lists'] ?? [])->keyBy('id');
        $cards = $data['cards'] ?? [];
        $created = 0;
        $skipped = 0;

        if (!Schema::hasTable('cases')) {
            return ['ok' => false, 'message' => 'جدول پرونده وجود ندارد'];
        }

        foreach ($cards as $card) {
            if (!empty($card['closed'])) {
                $skipped++;
                continue;
            }
            $listName = $lists[$card['idList'] ?? '']['name'] ?? 'imported';
            $title = $card['name'] ?? 'Trello card';
            $desc = $card['desc'] ?? '';
            $row = [
                'title' => Str::limit($title, 190),
                'description' => $desc."\n\n[ورود از ترلو — لیست: {$listName}]",
                'status' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            // optional columns
            if (Schema::hasColumn('cases', 'source')) {
                $row['source'] = 'trello';
            }
            if (Schema::hasColumn('cases', 'case_number')) {
                $row['case_number'] = 'TR-'.strtoupper(Str::random(6));
            }
            if (Schema::hasColumn('cases', 'created_by') && $userId) {
                $row['created_by'] = $userId;
            }
            try {
                DB::table('cases')->insert($row);
                $created++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'message' => "از ترلو: {$created} پرونده ایجاد شد، {$skipped} رد شد.",
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
}
