<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Industry;
use App\Models\Organization;
use App\Models\Contact;
use App\Models\CaseModel;
use App\Models\Task;
use App\Models\Tag;
use Spatie\Permission\Models\Role;

/**
 * داده نمونه صنعتی برای تست و نمایش سیستم.
 * اجرا:
 *   php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder --force
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('در حال ایجاد داده‌های نمونه...');

        // —— صنایع ——
        $industries = [
            ['name' => 'نفت و گاز', 'code' => 'oil_gas', 'sort_order' => 10],
            ['name' => 'پتروشیمی', 'code' => 'petro', 'sort_order' => 20],
            ['name' => 'فولاد', 'code' => 'steel', 'sort_order' => 30],
            ['name' => 'آب و فاضلاب', 'code' => 'water', 'sort_order' => 40],
            ['name' => 'پالایشگاهی', 'code' => 'refinery', 'sort_order' => 50],
            ['name' => 'متفرقه', 'code' => 'other', 'sort_order' => 90],
        ];
        $indMap = [];
        foreach ($industries as $row) {
            $ind = Industry::firstOrCreate(
                ['code' => $row['code']],
                array_merge($row, ['is_active' => true])
            );
            $indMap[$row['code']] = $ind->id;
        }

        // —— کاربران نمونه (اگر نیستند) ——
        $users = [
            ['email' => 'admin@example.com', 'name' => 'ادمین سیستم', 'role' => 'admin', 'pass' => 'password'],
            ['email' => 'tech.manager@example.com', 'name' => 'رضا مدیر فنی', 'role' => 'technical_manager', 'pass' => 'password'],
            ['email' => 'fin.manager@example.com', 'name' => 'سارا مدیر مالی', 'role' => 'financial_manager', 'pass' => 'password'],
            ['email' => 'tech1@example.com', 'name' => 'علی کارشناس فنی', 'role' => 'technical_expert', 'pass' => 'password'],
            ['email' => 'tech2@example.com', 'name' => 'مریم کارشناس فنی', 'role' => 'technical_expert', 'pass' => 'password'],
            ['email' => 'fin1@example.com', 'name' => 'حسین کارشناس مالی', 'role' => 'financial_expert', 'pass' => 'password'],
        ];
        $userIds = [];
        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['pass']),
                    'email_verified_at' => now(),
                ]
            );
            if (method_exists($user, 'syncRoles')) {
                try {
                    Role::findOrCreate($u['role']);
                    $user->syncRoles([$u['role']]);
                } catch (\Throwable $e) {
                }
            }
            $userIds[$u['email']] = $user->id;
        }

        // —— تگ‌ها ——
        $tagHot = Tag::firstOrCreate(['name' => 'فوری'], ['color' => '#dc2626']);
        $tagVip = Tag::firstOrCreate(['name' => 'VIP'], ['color' => '#7c3aed']);
        $tagExport = Tag::firstOrCreate(['name' => 'صادراتی'], ['color' => '#0f766e']);

        // —— سازمان‌ها ——
        $orgDefs = [
            ['name' => 'شرکت پتروشیمی بندر امام', 'type' => 'customer', 'ind' => 'petro', 'email' => 'info@bipc.example', 'phone' => '061-521-0000'],
            ['name' => 'فولاد مبارکه اصفهان', 'type' => 'customer', 'ind' => 'steel', 'email' => 'purchase@msc.example', 'phone' => '031-520-0000'],
            ['name' => 'نفت فلات قاره ایران', 'type' => 'customer', 'ind' => 'oil_gas', 'email' => 'rfq@iooc.example', 'phone' => '021-880-0000'],
            ['name' => 'آب و فاضلاب مشهد', 'type' => 'customer', 'ind' => 'water', 'email' => 'eng@abfa.example', 'phone' => '051-370-0000'],
            ['name' => 'پالایشگاه تهران', 'type' => 'customer', 'ind' => 'refinery', 'email' => 'procurement@tehranref.example', 'phone' => '021-550-0000'],
            ['name' => 'Siemens Energy GmbH', 'type' => 'supplier', 'ind' => 'other', 'email' => 'sales@siemens-energy.example', 'phone' => '+49-89-0000'],
            ['name' => 'ABB Industrial', 'type' => 'supplier', 'ind' => 'other', 'email' => 'offer@abb.example', 'phone' => '+41-43-0000'],
            ['name' => 'مجتمع فولاد خوزستان', 'type' => 'both', 'ind' => 'steel', 'email' => 'commerce@ksc.example', 'phone' => '061-330-0000'],
        ];
        $orgs = [];
        foreach ($orgDefs as $o) {
            $org = Organization::firstOrCreate(
                ['name' => $o['name']],
                [
                    'type' => $o['type'],
                    'industry_id' => $indMap[$o['ind']] ?? null,
                    'email' => $o['email'],
                    'phone' => $o['phone'],
                    'notes' => 'سازمان نمونه برای دمو',
                ]
            );
            $orgs[] = $org;
        }

        // —— مخاطبان ——
        $contactDefs = [
            [0, 'احمد', 'کریمی', 'مدیر خرید', 'karimi@bipc.example', '0912-111-0001'],
            [0, 'نرگس', 'محمدی', 'کارشناس تدارکات', 'mohammadi@bipc.example', '0912-111-0002'],
            [1, 'بهروز', 'صادقی', 'رئیس مهندسی', 'sadeghi@msc.example', '0913-222-0001'],
            [2, 'فرهاد', 'نوری', 'مدیر پروژه', 'nouri@iooc.example', '0912-333-0001'],
            [3, 'لیلا', 'حسینی', 'کارشناس فنی', 'hoseini@abfa.example', '0915-444-0001'],
            [4, 'کامران', 'یوسفی', 'خرید تجهیزات', 'yousefi@tehranref.example', '0912-555-0001'],
            [5, 'Thomas', 'Weber', 'Sales Manager', 't.weber@siemens.example', '+49-170-0001'],
            [7, 'مهدی', 'رضایی', 'بازرگانی', 'rezaei@ksc.example', '0916-777-0001'],
        ];
        $contacts = [];
        foreach ($contactDefs as $c) {
            $org = $orgs[$c[0]];
            $ct = Contact::firstOrCreate(
                ['email' => $c[4]],
                [
                    'organization_id' => $org->id,
                    'first_name' => $c[1],
                    'last_name' => $c[2],
                    'position' => $c[3],
                    'mobile' => $c[5],
                    'phone' => $org->phone,
                ]
            );
            $contacts[] = $ct;
        }

        $tech1 = $userIds['tech1@example.com'] ?? null;
        $tech2 = $userIds['tech2@example.com'] ?? null;
        $fin1 = $userIds['fin1@example.com'] ?? null;
        $adminId = $userIds['admin@example.com'] ?? 1;
        $tmId = $userIds['tech.manager@example.com'] ?? $adminId;

        // —— پرونده‌ها در وضعیت‌های مختلف ——
        $caseDefs = [
            ['CASE-100001', 'تأمین پمپ سانتریفیوژ API 610', 'received', 'high', 'EUR', 'CPT', 0, $tech1],
            ['CASE-100002', 'شیرهای کنترلی و اکچویتر', 'waiting_technical', 'urgent', 'EUR', 'CFR', 1, $tech1],
            ['CASE-100003', 'مبدل حرارتی پوسته و لوله', 'technical_sent', 'medium', 'EUR', 'CPT', 2, $tech2],
            ['CASE-100004', 'الکتروموتور و درایو فشارقوی', 'waiting_financial', 'high', 'EUR', 'DDP', 1, $tech2],
            ['CASE-100005', 'سیستم اندازه‌گیری جریان آب', 'financial_sent', 'medium', 'IRR', 'DDP', 3, $tech1],
            ['CASE-100006', 'کمپرسور اسکرو هوای فشرده', 'won', 'high', 'EUR', 'DDP', 4, $tech1],
            ['CASE-100007', 'تجهیزات ابزار دقیق پالایشگاه', 'purchasing', 'medium', 'EUR', 'CPT', 4, $tech2],
            ['CASE-100008', 'قطعات یدکی توربین گاز', 'receivables', 'high', 'EUR', 'CFR', 2, $tech1],
            ['CASE-100009', 'فیلترهای صنعتی و سپراتور', 'lost', 'low', 'EUR', 'CPT', 7, $tech2],
            ['CASE-100010', 'پکیج تصفیه پساب', 'waiting_info', 'medium', 'EUR', 'CPT', 3, $tech1],
            ['CASE-100011', 'ترانسفورماتور قدرت', 'stopped', 'low', 'EUR', 'EXW', 1, $tech2],
            ['CASE-100012', 'گیربکس صنعتی سنگین', 'received', 'medium', 'EUR', 'CPT', 7, $tech1],
        ];

        $cases = [];
        foreach ($caseDefs as $i => $cd) {
            $org = $orgs[$cd[6]] ?? $orgs[0];
            $contact = null;
            foreach ($contacts as $ct) {
                if ((int) $ct->organization_id === (int) $org->id) {
                    $contact = $ct;
                    break;
                }
            }
            $amount = null;
            $vat = null;
            $gross = null;
            if (in_array($cd[2], ['financial_sent', 'won', 'purchasing', 'receivables'], true)) {
                $amount = [45000, 128000, 87500, 210000, 3500000][$i % 5];
                $vat = ($cd[5] === 'DDP') ? 10 : 0;
                $gross = $amount * (1 + $vat / 100);
            }
            $payload = [
                    'title' => $cd[1],
                    'description' => "شرح نمونه درخواست: {$cd[1]}. مشتری نیاز به پیشنهاد فنی و مالی دارد. مهلت پاسخ حدود دو هفته.",
                    'current_status' => $cd[2],
                    'priority' => $cd[3],
                    'currency' => $cd[4],
                    'incoterm' => $cd[5],
                    'customer_organization_id' => $org->id,
                    'assigned_expert_id' => $cd[7],
                    'created_at' => now()->subDays(20 - $i),
                    'updated_at' => now()->subDays(max(0, 10 - $i)),
                ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('cases', 'contact_id') && $contact) {
                $payload['contact_id'] = $contact->id;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('cases', 'proposal_amount') && $amount !== null) {
                $payload['proposal_amount'] = $amount;
                $payload['vat_percent'] = $vat;
                $payload['proposal_gross'] = $gross;
            }
            $case = CaseModel::firstOrCreate(
                ['case_number' => $cd[0]],
                $payload
            );
            $cases[] = $case;
            try {
                if (method_exists($case, 'tags')) {
                    if ($cd[3] === 'urgent') {
                        $case->tags()->syncWithoutDetaching([$tagHot->id]);
                    }
                    if ($i % 4 === 0) {
                        $case->tags()->syncWithoutDetaching([$tagVip->id]);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // —— فعالیت نمونه روی چند پرونده ——
        if (\Illuminate\Support\Facades\Schema::hasTable('case_activities')) {
            foreach (array_slice($cases, 0, 5) as $idx => $case) {
                $exists = DB::table('case_activities')->where('case_id', $case->id)->exists();
                if ($exists) {
                    continue;
                }
                DB::table('case_activities')->insert([
                    [
                        'case_id' => $case->id,
                        'user_id' => $tech1 ?? $adminId,
                        'type' => 'comment',
                        'body' => 'پرونده بررسی اولیه شد. مدارک فنی مشتری ناقص است.',
                        'created_at' => now()->subDays(5),
                        'updated_at' => now()->subDays(5),
                    ],
                    [
                        'case_id' => $case->id,
                        'user_id' => $tmId,
                        'type' => 'phone_call_report',
                        'body' => 'تماس تلفنی با مشتری: مهلت پیشنهاد تا پایان هفته تمدید شد.',
                        'created_at' => now()->subDays(3),
                        'updated_at' => now()->subDays(3),
                    ],
                ]);
            }
        }

        // —— وظایف ——
        $taskDefs = [
            ['بررسی مشخصات فنی پمپ', 'open', 'high', $tech1, $cases[0]->id ?? null],
            ['تهیه پیشنهاد فنی شیر کنترلی', 'in_progress', 'urgent', $tech1, $cases[1]->id ?? null],
            ['هماهنگی قیمت با تأمین‌کننده اروپایی', 'open', 'high', $fin1, $cases[3]->id ?? null],
            ['پیگیری وصول پیش‌پرداخت', 'open', 'medium', $fin1, $cases[7]->id ?? null],
            ['تکمیل مدارک حمل و ترخیص', 'in_progress', 'medium', $tech2, $cases[6]->id ?? null],
            ['تماس با مشتری برای اطلاعات تکمیلی', 'open', 'medium', $tech1, $cases[9]->id ?? null],
            ['بازبینی نقشه ابعادی گیربکس', 'done', 'low', $tech2, $cases[11]->id ?? null],
        ];
        foreach ($taskDefs as $td) {
            Task::firstOrCreate(
                [
                    'title' => $td[0],
                    'case_id' => $td[4],
                ],
                [
                    'description' => 'وظیفه نمونه دمو — '.$td[0],
                    'status' => $td[1],
                    'priority' => $td[2],
                    'assigned_to' => $td[3],
                    'created_by' => $tmId,
                    'due_at' => now()->addDays(rand(1, 14)),
                    'task_type' => 'general',
                ]
            );
        }

        $this->command?->info('داده‌های نمونه آماده شد.');
        $this->command?->table(
            ['بخش', 'تعداد تقریبی'],
            [
                ['صنایع', count($industries)],
                ['سازمان‌ها', count($orgs)],
                ['مخاطبان', count($contacts)],
                ['پرونده‌ها', count($cases)],
                ['کاربران دمو', count($users)],
            ]
        );
        $this->command?->info('ورود نمونه: admin@example.com / password');
        $this->command?->info('سایر: tech1@example.com ، fin1@example.com / password');
    }
}
