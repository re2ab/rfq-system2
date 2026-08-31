<?php

namespace App\Services\Mail;

use App\Models\CaseModel;
use App\Models\Contact;
use App\Models\Mail\MailMessage;
use App\Models\Organization;
use Illuminate\Support\Collection;

class MailMatchingService
{
    /**
     * پیشنهادهای لینک برای یک پیام.
     *
     * @return array{
     *   contacts: Collection,
     *   organizations: Collection,
     *   cases: Collection,
     *   case_numbers: string[]
     * }
     */
    public function suggest(MailMessage $message): array
    {
        $emails = $this->extractEmails($message);
        $haystack = mb_strtolower(trim(
            ($message->subject ?? '').' '.strip_tags($message->body_text ?? '').' '.strip_tags($message->body_html ?? '')
        ));

        $contacts = collect();
        if ($emails) {
            $contacts = Contact::query()
                ->where(function ($q) use ($emails) {
                    foreach ($emails as $e) {
                        $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($e)]);
                    }
                })
                ->with('organization')
                ->limit(10)
                ->get();
        }

        $organizations = collect();
        if ($emails) {
            $organizations = Organization::query()
                ->where(function ($q) use ($emails) {
                    foreach ($emails as $e) {
                        $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($e)]);
                    }
                })
                ->limit(10)
                ->get();
        }

        // سازمان از مخاطب‌های پیدا شده
        foreach ($contacts as $c) {
            if ($c->organization && !$organizations->contains('id', $c->organization->id)) {
                $organizations->push($c->organization);
            }
        }

        $caseNumbers = $this->extractCaseNumbers($haystack);
        $cases = collect();
        if ($caseNumbers) {
            $cases = CaseModel::query()
                ->where(function ($q) use ($caseNumbers) {
                    foreach ($caseNumbers as $n) {
                        $q->orWhere('case_number', $n)
                            ->orWhere('customer_request_number', $n);
                    }
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        // پرونده‌های باز مرتبط با مخاطب/سازمان
        if ($cases->isEmpty() && $contacts->isNotEmpty()) {
            $contactIds = $contacts->pluck('id')->all();
            $orgIds = $contacts->pluck('organization_id')->filter()->all();
            $cases = CaseModel::query()
                ->where(function ($q) use ($contactIds, $orgIds) {
                    $q->whereIn('contact_id', $contactIds);
                    if ($orgIds) {
                        $q->orWhereIn('customer_organization_id', $orgIds);
                    }
                })
                ->orderByDesc('id')
                ->limit(8)
                ->get();
        }

        return [
            'contacts' => $contacts,
            'organizations' => $organizations,
            'cases' => $cases,
            'case_numbers' => $caseNumbers,
            'emails' => $emails,
        ];
    }

    public function linkToCase(MailMessage $message, int $caseId, bool $alsoContact = true): MailMessage
    {
        $case = CaseModel::findOrFail($caseId);
        $message->case_id = $case->id;
        if ($alsoContact && $case->contact_id && !$message->contact_id) {
            $message->contact_id = $case->contact_id;
        }
        if ($case->customer_organization_id && !$message->organization_id) {
            $message->organization_id = $case->customer_organization_id;
        }
        $message->save();

        return $message;
    }

    public function unlinkCase(MailMessage $message): MailMessage
    {
        $message->case_id = null;
        $message->save();

        return $message;
    }

    public function linkContact(MailMessage $message, int $contactId): MailMessage
    {
        $contact = Contact::findOrFail($contactId);
        $message->contact_id = $contact->id;
        if ($contact->organization_id) {
            $message->organization_id = $contact->organization_id;
        }
        $message->save();

        return $message;
    }

    /** پیام‌های لینک‌شده به یک پرونده برای تایم‌لاین */
    public function timelineForCase(int $caseId, int $limit = 100)
    {
        return MailMessage::query()
            ->where('case_id', $caseId)
            ->with(['account', 'folder', 'attachments'])
            ->orderByDesc('date_sent')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function unmatchedCount(): int
    {
        return MailMessage::query()->whereNull('case_id')->count();
    }

    protected function extractEmails(MailMessage $message): array
    {
        $set = [];
        if ($message->from_address) {
            $set[mb_strtolower($message->from_address)] = $message->from_address;
        }
        foreach (['to_json', 'cc_json'] as $field) {
            $list = $message->{$field} ?? [];
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                $e = is_array($row) ? ($row['email'] ?? null) : null;
                if ($e) {
                    $set[mb_strtolower($e)] = $e;
                }
            }
        }

        return array_values($set);
    }

    protected function extractCaseNumbers(string $haystack): array
    {
        $found = [];
        // الگوهای رایج: TC-200108-0103 یا حروف/عدد با خط تیره
        if (preg_match_all('/\b([A-Za-z]{1,6}[-_]?\d{2,8}(?:[-_]\d{2,8})+)\b/u', $haystack, $m)) {
            foreach ($m[1] as $n) {
                $found[strtoupper($n)] = strtoupper($n);
            }
        }
        // شماره خالص پرونده اگر در سیستم عددی باشد
        if (preg_match_all('/(?:پرونده|case|rfq)\s*[#:]?\s*(\d{1,8})/ui', $haystack, $m2)) {
            foreach ($m2[1] as $n) {
                $found[$n] = $n;
            }
        }

        // تطبیق با case_numberهای واقعی که در subject آمده
        $candidates = array_values($found);
        if (!$candidates) {
            return [];
        }

        $existing = CaseModel::query()
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $c) {
                    $q->orWhereRaw('UPPER(case_number) = ?', [strtoupper($c)])
                        ->orWhereRaw('UPPER(COALESCE(customer_request_number,\'\')) = ?', [strtoupper($c)]);
                }
            })
            ->pluck('case_number')
            ->filter()
            ->all();

        // اگر در DB پیدا شد همان‌ها؛ وگرنه کاندیدها را برگردان برای جستجوی دستی
        return $existing ?: $candidates;
    }
}
