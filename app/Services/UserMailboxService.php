<?php
namespace App\Services;

use App\Models\AppSetting;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\User;
use App\Models\UserMailAccount;
use App\Support\ModuleGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class UserMailboxService
{
    public function companyDefaults(): array
    {
        return [
            'smtp_host' => AppSetting::get('company_smtp_host', AppSetting::get('mail_smtp_host', '')),
            'smtp_port' => (int) AppSetting::get('company_smtp_port', AppSetting::get('mail_smtp_port', '587')),
            'smtp_encryption' => AppSetting::get('company_smtp_encryption', AppSetting::get('mail_smtp_encryption', 'tls')),
            'imap_host' => AppSetting::get('company_imap_host', AppSetting::get('mail_imap_host', '')),
            'imap_port' => (int) AppSetting::get('company_imap_port', AppSetting::get('mail_imap_port', '993')),
            'imap_encryption' => AppSetting::get('company_imap_encryption', AppSetting::get('mail_imap_encryption', 'ssl')),
            'imap_sent_folder' => AppSetting::get('company_imap_sent_folder', ''),
            'pop3_host' => AppSetting::get('company_pop3_host', ''),
            'pop3_port' => (int) AppSetting::get('company_pop3_port', '995'),
            'pop3_encryption' => AppSetting::get('company_pop3_encryption', 'ssl'),
        ];
    }

    public function forUser(User|int $user): ?UserMailAccount
    {
        if (!ModuleGate::enabled('user_mailbox') || !Schema::hasTable('user_mail_accounts')) {
            return null;
        }
        $id = $user instanceof User ? $user->id : $user;
        return UserMailAccount::where('user_id', $id)->first();
    }

    /** Merge company server defaults into account for runtime use */
    public function effective(UserMailAccount $acc): array
    {
        $c = $this->companyDefaults();
        return [
            'email' => $acc->email ?: $acc->smtp_username,
            'display_name' => $acc->display_name,
            'smtp_host' => $c['smtp_host'] ?: $acc->smtp_host,
            'smtp_port' => $c['smtp_port'] ?: (int) $acc->smtp_port,
            'smtp_encryption' => $c['smtp_encryption'] ?: $acc->smtp_encryption,
            'smtp_username' => $acc->smtp_username ?: $acc->email,
            'smtp_password' => $acc->smtpPasswordPlain(),
            'imap_host' => $c['imap_host'] ?: $acc->imap_host,
            'imap_port' => $c['imap_port'] ?: (int) $acc->imap_port,
            'imap_encryption' => $c['imap_encryption'] ?: $acc->imap_encryption,
            'imap_username' => $acc->imap_username ?: $acc->email,
            'imap_password' => $acc->imapPasswordPlain(),
            'pop3_host' => $c['pop3_host'] ?: $acc->pop3_host,
            'pop3_port' => $c['pop3_port'] ?: (int) $acc->pop3_port,
            'pop3_encryption' => $c['pop3_encryption'] ?: $acc->pop3_encryption,
            'is_active' => $acc->is_active,
        ];
    }

    public function save(User $user, array $data): UserMailAccount
    {
        $acc = UserMailAccount::firstOrNew(['user_id' => $user->id]);
        // User-facing: credentials only (servers come from company settings)
        foreach (['email', 'display_name', 'smtp_username', 'imap_username', 'pop3_username'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null) {
                $acc->{$k} = $data[$k];
            }
        }
        // If only email given, use as usernames
        if (!empty($data['email'])) {
            if (empty($acc->smtp_username)) {
                $acc->smtp_username = $data['email'];
            }
            if (empty($acc->imap_username)) {
                $acc->imap_username = $data['email'];
            }
        }
        if (!empty($data['smtp_password'])) {
            $acc->smtp_password = $data['smtp_password'];
        }
        if (!empty($data['imap_password'])) {
            $acc->imap_password = $data['imap_password'];
        }
        if (!empty($data['pop3_password'])) {
            $acc->pop3_password = $data['pop3_password'];
        }
        // Optional: same password for imap if only one provided
        if (!empty($data['smtp_password']) && empty($data['imap_password']) && empty($acc->imapPasswordPlain())) {
            $acc->imap_password = $data['smtp_password'];
        }
        if (array_key_exists('is_active', $data)) {
            $acc->is_active = (bool) $data['is_active'];
        } else {
            $acc->is_active = true;
        }
        // Clear per-user hosts so company defaults always win (unless admin later needs override)
        $acc->user_id = $user->id;
        $acc->save();
        Cache::forget('mailbox_unread_'.$user->id);
        return $acc;
    }

    public function isReadyToSend(UserMailAccount $acc): bool
    {
        $e = $this->effective($acc);
        return $acc->is_active && $e['smtp_host'] && $e['smtp_username'] && $e['smtp_password'];
    }

    public function isReadyToReceive(UserMailAccount $acc): bool
    {
        $e = $this->effective($acc);
        return $acc->is_active && $e['imap_host'] && $e['imap_username'] && $e['imap_password'];
    }

    public function applySmtp(UserMailAccount $acc): void
    {
        $e = $this->effective($acc);
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $e['smtp_host'],
            'port' => (int) $e['smtp_port'],
            'encryption' => $e['smtp_encryption'] === 'none' ? null : $e['smtp_encryption'],
            'username' => $e['smtp_username'],
            'password' => $e['smtp_password'],
            'timeout' => 30,
        ]);
        Config::set('mail.from', [
            'address' => $e['email'] ?: $e['smtp_username'],
            'name' => $e['display_name'] ?: ($e['email'] ?: 'User'),
        ]);
    }

    /** ارسال ساده‌ی متنی — فقط برای دکمه‌ی «تست ارسال» در تنظیمات صندوق. برای ارسال واقعی از کلاینت از sendCompose() استفاده کنید. */
    public function send(User $user, string $to, string $subject, string $body): array
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            return ['ok' => false, 'message' => 'ماژول صندوق کاربر غیرفعال است'];
        }
        $acc = $this->forUser($user);
        if (!$acc || !$this->isReadyToSend($acc)) {
            return ['ok' => false, 'message' => 'صندوق فعال نیست یا سرور شرکت/رمز ناقص است'];
        }
        $this->applySmtp($acc);
        $e = $this->effective($acc);
        try {
            Mail::raw($body, function ($m) use ($to, $subject, $e) {
                $m->to($to)->subject($subject)
                    ->from($e['email'] ?: $e['smtp_username'], $e['display_name']);
            });
            return ['ok' => true];
        } catch (\Throwable $ex) {
            return ['ok' => false, 'message' => $ex->getMessage()];
        }
    }

    /**
     * ارسال کامل از کلاینت ایمیل شخصی: پیوست (فایل آپلودی یا سند سیستم)، هدرهای
     * In-Reply-To/References برای پاسخ (thread واقعی در جیمیل/Outlook گیرنده)، ثبت محلی در
     * جدول emails (هم برای پوشه‌ی «ارسالی» داخل سیستم)، و — طبق تصمیم کاربر — تلاش برای
     * ذخیره‌ی واقعی کپی پیام در پوشه‌ی Sent همان سرور IMAP (imap_append) تا در هر کلاینت
     * دیگری هم دیده شود. اگر append ناموفق بود، ارسال SMTP و ثبت محلی همچنان معتبر می‌مانند
     * (best-effort — دقیقاً هم‌روح با بقیه‌ی سیستم که ویژگی‌های جانبی هرگز مسیر اصلی را نمی‌شکنند).
     *
     * $data: to, cc?, subject, body, in_reply_to?, references?, mark_answered_uid?,
     *        attachments: [['full_path','stored_path','name','mime','size'], ...]
     */
    public function sendCompose(User $user, array $data): array
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            return ['ok' => false, 'message' => 'ماژول صندوق کاربر غیرفعال است'];
        }
        $acc = $this->forUser($user);
        if (!$acc || !$this->isReadyToSend($acc)) {
            return ['ok' => false, 'message' => 'صندوق فعال نیست یا سرور شرکت/رمز ناقص است'];
        }
        $this->applySmtp($acc);
        $e = $this->effective($acc);
        $attachments = $data['attachments'] ?? [];

        $rawCapture = null;
        $sent = false;
        $error = null;
        $ccList = [];
        if (!empty($data['cc'])) {
            $ccList = array_values(array_filter(array_map('trim', explode(',', $data['cc']))));
        }

        try {
            Mail::send([], [], function ($message) use ($data, $e, $attachments, $ccList, &$rawCapture) {
                $message->from($e['email'] ?: $e['smtp_username'], $e['display_name']);
                $message->to($data['to']);
                if ($ccList) {
                    $message->cc($ccList);
                }
                $message->subject($data['subject']);
                $message->html(nl2br(e($data['body'])));
                foreach ($attachments as $att) {
                    if (!empty($att['full_path']) && is_file($att['full_path'])) {
                        $message->attach($att['full_path'], array_filter([
                            'as' => $att['name'] ?? null,
                            'mime' => $att['mime'] ?? null,
                        ]));
                    }
                }
                $symfony = $message->getSymfonyMessage();
                if (!empty($data['in_reply_to'])) {
                    $symfony->getHeaders()->addTextHeader('In-Reply-To', $data['in_reply_to']);
                }
                if (!empty($data['references'])) {
                    $symfony->getHeaders()->addTextHeader('References', $data['references']);
                }
                // متن خام کامل پیام (هدر + بدنه + پیوست‌ها) — برای imap_append در پوشه‌ی Sent،
                // پایین‌تر بعد از تلاش ارسال واقعی. باید همین‌جا (داخل closure، بعد از تکمیل
                // پیام) گرفته شود چون بیرون از این closure دیگر به شیء واقعی دسترسی نداریم.
                $rawCapture = $symfony->toString();
            });
            $sent = true;
        } catch (\Throwable $ex) {
            $error = $ex->getMessage();
        }

        // ثبت محلی — چه ارسال SMTP موفق بود چه نه، تا در فهرست «ارسالی» صندوق قابل‌پیگیری باشد.
        $email = EmailMessage::create([
            'user_id' => $user->id,
            'direction' => 'outbound',
            'from_address' => $e['email'] ?: $e['smtp_username'],
            'to_address' => $data['to'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_linked' => false,
        ]);
        foreach ($attachments as $att) {
            EmailAttachment::create([
                'email_message_id' => $email->id,
                'file_name' => $att['name'] ?? 'file',
                'file_path' => $att['stored_path'] ?? '',
                'mime_type' => $att['mime'] ?? null,
                'file_size' => $att['size'] ?? 0,
            ]);
        }

        $appended = false;
        if ($sent && $rawCapture) {
            try {
                $appended = $this->appendToSent($acc, $rawCapture);
            } catch (\Throwable $ex) {
                // best-effort — ارسال واقعی موفق بوده، فقط کپی سرور شکست خورده
            }
        }

        if ($sent && !empty($data['mark_answered_uid'])) {
            try {
                $this->setAnswered($acc, (int) $data['mark_answered_uid']);
            } catch (\Throwable $ex) {
            }
        }

        if (!$sent) {
            return ['ok' => false, 'message' => $error ?: 'ارسال ناموفق بود', 'email' => $email];
        }

        return [
            'ok' => true,
            'message' => $appended
                ? 'ایمیل ارسال شد و در پوشه‌ی Sent سرور هم ذخیره شد.'
                : 'ایمیل ارسال شد (ذخیره‌ی کپی در پوشه‌ی Sent سرور ناموفق بود — فقط داخل سیستم ثبت شد).',
            'email' => $email,
        ];
    }

    protected function openImap(UserMailAccount $acc)
    {
        if (!function_exists('imap_open')) {
            return null;
        }
        $e = $this->effective($acc);
        if (!$e['imap_host'] || !$e['imap_username'] || !$e['imap_password']) {
            return null;
        }
        $enc = $e['imap_encryption'] === 'none' ? 'notls' : $e['imap_encryption'];
        $mailbox = sprintf('{%s:%d/imap/%s}INBOX', $e['imap_host'], (int) $e['imap_port'], $enc);
        return @imap_open($mailbox, $e['imap_username'], $e['imap_password'], 0, 1);
    }

    public function testImap(UserMailAccount $acc): array
    {
        if (!function_exists('imap_open')) {
            return ['ok' => false, 'message' => 'php-imap روی سرور نصب نیست'];
        }
        if (!$this->isReadyToReceive($acc)) {
            return ['ok' => false, 'message' => 'سرور IMAP شرکت یا یوزر/رمز شما کامل نیست'];
        }
        $inbox = $this->openImap($acc);
        if (!$inbox) {
            return ['ok' => false, 'message' => function_exists('imap_last_error') ? (imap_last_error() ?: 'اتصال ناموفق') : 'اتصال ناموفق'];
        }
        $n = @imap_num_msg($inbox);
        $unseen = $this->countUnseenFromStream($inbox);
        imap_close($inbox);
        $acc->last_synced_at = now();
        $acc->save();
        Cache::put('mailbox_unread_'.$acc->user_id, $unseen, now()->addMinutes(5));
        return ['ok' => true, 'message' => "اتصال موفق — پیام‌ها: ".(int) $n." — نخوانده: {$unseen}"];
    }

    protected function countUnseenFromStream($inbox): int
    {
        $ids = @imap_search($inbox, 'UNSEEN');
        if ($ids === false || $ids === null) {
            return 0;
        }
        return count($ids);
    }

    public function unreadCount(User $user): int
    {
        if (!ModuleGate::enabled('user_mailbox')) {
            return 0;
        }
        return (int) Cache::remember('mailbox_unread_'.$user->id, now()->addMinutes(3), function () use ($user) {
            $acc = $this->forUser($user);
            if (!$acc || !$this->isReadyToReceive($acc)) {
                return 0;
            }
            $inbox = $this->openImap($acc);
            if (!$inbox) {
                return 0;
            }
            $n = $this->countUnseenFromStream($inbox);
            imap_close($inbox);
            try {
                $acc->last_synced_at = now();
                $acc->save();
            } catch (\Throwable $e) {
            }
            return $n;
        });
    }

    /** فهرست صفحه‌بندی‌شده‌ی صندوق ورودی — جدیدترین‌ها اول. برمی‌گرداند ['items' => [...], 'total' => N]. */
    public function fetchRecent(UserMailAccount $acc, int $limit = 20, int $offset = 0): array
    {
        $inbox = $this->openImap($acc);
        if (!$inbox) {
            return ['items' => [], 'total' => 0];
        }
        $total = (int) @imap_num_msg($inbox);
        $end = $total - $offset;
        $start = max(1, $end - $limit + 1);
        $items = [];
        if ($total > 0 && $end >= 1 && $start <= $end) {
            $overview = @imap_fetch_overview($inbox, "{$start}:{$end}") ?: [];
            usort($overview, fn ($a, $b) => ($b->msgno ?? 0) <=> ($a->msgno ?? 0));
            foreach ($overview as $o) {
                $items[] = [
                    'uid' => (int) ($o->uid ?? 0),
                    'subject' => isset($o->subject) && $o->subject !== '' ? $this->mimeDecode($o->subject) : '(بدون موضوع)',
                    'from' => isset($o->from) ? $this->mimeDecode($o->from) : '',
                    'date' => $o->date ?? '',
                    'seen' => (bool) ($o->seen ?? 0),
                    'answered' => (bool) ($o->answered ?? 0),
                ];
            }
        }
        imap_close($inbox);
        $acc->last_synced_at = now();
        $acc->save();
        return ['items' => $items, 'total' => $total];
    }

    /**
     * خواندن کامل یک پیام با UID: هدرها + بدنه‌ی HTML/متنی (با پیمایش بازگشتی ساختار MIME) +
     * فهرست پیوست‌ها. توجه: خودِ imap_fetchbody طبق پروتکل IMAP پیام را Seen علامت می‌زند —
     * دقیقاً همان رفتاری که در جیمیل/Outlook هم با باز کردن یک نامه اتفاق می‌افتد.
     */
    public function fetchMessage(UserMailAccount $acc, int $uid): ?array
    {
        $inbox = $this->openImap($acc);
        if (!$inbox) {
            return null;
        }
        $overview = @imap_fetch_overview($inbox, (string) $uid, FT_UID);
        if (!$overview || !isset($overview[0])) {
            imap_close($inbox);
            return null;
        }
        $ov = $overview[0];
        $structure = @imap_fetchstructure($inbox, $uid, FT_UID);
        $out = ['html' => null, 'plain' => null, 'attachments' => []];
        if ($structure) {
            $this->parseStructure($inbox, $uid, $structure, '', $out);
        }
        $rawHeader = (string) @imap_fetchheader($inbox, $uid, FT_UID);
        $references = '';
        if ($rawHeader && preg_match('/^References:\s*(.+?)(?:\r?\n\S|\r?\n\r?\n)/ims', $rawHeader."\n\n", $m)) {
            $references = trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        imap_close($inbox);

        return [
            'uid' => $uid,
            'subject' => isset($ov->subject) && $ov->subject !== '' ? $this->mimeDecode($ov->subject) : '(بدون موضوع)',
            'from' => isset($ov->from) ? $this->mimeDecode($ov->from) : '',
            'to' => isset($ov->to) ? $this->mimeDecode($ov->to) : '',
            'date' => $ov->date ?? '',
            'message_id' => $ov->message_id ?? '',
            'references' => $references,
            'html' => $out['html'],
            'plain' => $out['plain'],
            'attachments' => $out['attachments'],
        ];
    }

    /** محتوای خام (decode شده) یک پیوست مشخص — برای دانلود مستقیم (بدون ذخیره‌ی موقت روی دیسک). */
    public function fetchAttachmentPart(UserMailAccount $acc, int $uid, string $part): ?array
    {
        $inbox = $this->openImap($acc);
        if (!$inbox) {
            return null;
        }
        $structure = @imap_fetchstructure($inbox, $uid, FT_UID);
        if (!$structure) {
            imap_close($inbox);
            return null;
        }
        $leaf = $this->findPart($structure, '', $part);
        if (!$leaf) {
            imap_close($inbox);
            return null;
        }
        $raw = (string) @imap_fetchbody($inbox, $uid, $part, FT_UID);
        $bytes = $this->decodePart($raw, (int) ($leaf->encoding ?? 0));
        $mime = $this->mimeOf($leaf);
        $filename = $this->partFilename($leaf);
        imap_close($inbox);

        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'filename' => $filename ? $this->mimeDecode($filename) : ('attachment-'.$part),
        ];
    }

    /** پیام را روی سرور «پاسخ‌داده‌شده» علامت می‌زند (\Answered) — بعد از ارسال موفق یک Reply. */
    public function setAnswered(UserMailAccount $acc, int $uid): bool
    {
        $inbox = $this->openImap($acc);
        if (!$inbox) {
            return false;
        }
        $ok = (bool) @imap_setflag_full($inbox, (string) $uid, '\\Answered', ST_UID);
        imap_close($inbox);
        return $ok;
    }

    /**
     * پوشه‌ی Sent را روی سرور پیدا می‌کند: اول تنظیمات دستی ادمین (company_imap_sent_folder)،
     * وگرنه یک تلاش برای شناسایی خودکار با فهرست‌کردن پوشه‌های واقعی سرور، وگرنه «Sent» به‌عنوان
     * معقول‌ترین پیش‌فرض. ⚠️ نام دقیق پوشه بین سرویس‌دهنده‌های ایمیل خیلی فرق می‌کند (Sent /
     * INBOX.Sent / Sent Items / [Gmail]/Sent Mail) و در این sandbox قابل تست واقعی نیست.
     */
    public function resolveSentFolder(UserMailAccount $acc): string
    {
        $configured = trim((string) AppSetting::get('company_imap_sent_folder', ''));
        if ($configured !== '') {
            return $configured;
        }
        $e = $this->effective($acc);
        $inbox = $this->openImap($acc);
        if ($inbox) {
            $enc = $e['imap_encryption'] === 'none' ? 'notls' : $e['imap_encryption'];
            $root = sprintf('{%s:%d/imap/%s}', $e['imap_host'], (int) $e['imap_port'], $enc);
            $list = @imap_getmailboxes($inbox, $root, '*');
            imap_close($inbox);
            if ($list) {
                $candidates = ['Sent', 'INBOX.Sent', 'Sent Items', 'Sent Messages', '[Gmail]/Sent Mail'];
                $shorts = [];
                foreach ($list as $mb) {
                    $shorts[] = str_replace($root, '', (string) @imap_utf7_decode($mb->name));
                }
                foreach ($candidates as $cand) {
                    if (in_array($cand, $shorts, true)) {
                        return $cand;
                    }
                }
                foreach ($shorts as $short) {
                    if (stripos($short, 'sent') !== false) {
                        return $short;
                    }
                }
            }
        }
        return 'Sent';
    }

    /**
     * کپی واقعی یک پیام ارسال‌شده را در پوشه‌ی Sent همان سرور IMAP ذخیره می‌کند (imap_append)
     * تا در هر کلاینت ایمیل دیگری هم (Outlook، جیمیل واقعی کاربر و غیره) دیده شود — دقیقاً طبق
     * تصمیم صریح کاربر برای این ویژگی. اگر پوشه هنوز وجود نداشته باشد یک تلاش برای ساختنش هم
     * می‌شود. Best-effort: شکست این متد هرگز نباید ارسال واقعی ایمیل را ناموفق نشان دهد.
     */
    public function appendToSent(UserMailAccount $acc, string $rawMessage): bool
    {
        $inbox = $this->openImap($acc);
        if (!$inbox) {
            return false;
        }
        $e = $this->effective($acc);
        $enc = $e['imap_encryption'] === 'none' ? 'notls' : $e['imap_encryption'];
        $folder = $this->resolveSentFolder($acc);
        $mailbox = sprintf('{%s:%d/imap/%s}%s', $e['imap_host'], (int) $e['imap_port'], $enc, $folder);
        $normalized = preg_replace('/\r\n|\r|\n/', "\r\n", $rawMessage);

        $ok = @imap_append($inbox, $mailbox, $normalized, '\\Seen');
        if (!$ok) {
            // شاید پوشه هنوز وجود نداشته — یک‌بار تلاش برای ساختنش و append دوباره
            if (@imap_createmailbox($inbox, (string) @imap_utf7_encode($mailbox))) {
                $ok = @imap_append($inbox, $mailbox, $normalized, '\\Seen');
            }
        }
        imap_close($inbox);
        return (bool) $ok;
    }

    /** پیمایش بازگشتی ساختار MIME یک پیام (imap_fetchstructure) — بدنه‌ی HTML/متنی را در $out جمع می‌کند و بقیه را به‌عنوان پیوست فهرست می‌کند. */
    protected function parseStructure($stream, int $uid, $structure, string $partNumber, array &$out): void
    {
        if (!empty($structure->parts)) {
            foreach ($structure->parts as $index => $subPart) {
                $subPartNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber.'.'.($index + 1);
                $this->parseStructure($stream, $uid, $subPart, $subPartNumber, $out);
            }
            return;
        }

        $partNumber = $partNumber !== '' ? $partNumber : '1';
        $subtype = strtolower($structure->subtype ?? '');
        $primaryType = $this->typeNameOf($structure);
        $filename = $this->partFilename($structure);
        $isAttachment = !empty($structure->ifdisposition) && strtolower($structure->disposition ?? '') === 'attachment';

        if (!$isAttachment && $filename === null && $primaryType === 'text' && in_array($subtype, ['plain', 'html'], true)) {
            $raw = (string) @imap_fetchbody($stream, $uid, $partNumber, FT_UID);
            $decoded = $this->decodePart($raw, (int) ($structure->encoding ?? 0));
            $charset = $this->partCharset($structure);
            if ($charset && strtolower($charset) !== 'utf-8') {
                $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);
                if ($converted !== false) {
                    $decoded = $converted;
                }
            }
            if ($subtype === 'html') {
                $out['html'] = ($out['html'] ?? '').$decoded;
            } else {
                $out['plain'] = ($out['plain'] ?? '').$decoded;
            }
            return;
        }

        $out['attachments'][] = [
            'part' => $partNumber,
            'filename' => $filename ? $this->mimeDecode($filename) : ('پیوست-'.$partNumber),
            'mime' => $this->mimeOf($structure),
            'size' => (int) ($structure->bytes ?? 0),
        ];
    }

    /** همان مسیر parseStructure را طی می‌کند تا ساختار (structure) دقیق یک part-number مشخص را برگرداند — برای دانلود مطمئن (encoding واقعی را خودِ سرور تعیین می‌کند، نه چیزی که کلاینت فرستاده). */
    protected function findPart($structure, string $currentPart, string $targetPart)
    {
        if (!empty($structure->parts)) {
            foreach ($structure->parts as $index => $subPart) {
                $subPartNumber = $currentPart === '' ? (string) ($index + 1) : $currentPart.'.'.($index + 1);
                $found = $this->findPart($subPart, $subPartNumber, $targetPart);
                if ($found) {
                    return $found;
                }
            }
            return null;
        }
        $currentPart = $currentPart !== '' ? $currentPart : '1';
        return $currentPart === $targetPart ? $structure : null;
    }

    protected function typeNameOf($structure): string
    {
        $map = [0 => 'text', 1 => 'multipart', 2 => 'message', 3 => 'application', 4 => 'audio', 5 => 'image', 6 => 'video', 7 => 'other'];
        return $map[$structure->type ?? 7] ?? 'other';
    }

    protected function mimeOf($structure): string
    {
        $subtype = strtolower($structure->subtype ?? '');
        return $this->typeNameOf($structure).'/'.($subtype ?: 'octet-stream');
    }

    protected function partFilename($structure): ?string
    {
        if (!empty($structure->ifdparameters)) {
            foreach ($structure->dparameters as $p) {
                if (strtolower($p->attribute ?? '') === 'filename') {
                    return $p->value;
                }
            }
        }
        if (!empty($structure->ifparameters)) {
            foreach ($structure->parameters as $p) {
                if (strtolower($p->attribute ?? '') === 'name') {
                    return $p->value;
                }
            }
        }
        return null;
    }

    protected function partCharset($structure): ?string
    {
        if (!empty($structure->ifparameters)) {
            foreach ($structure->parameters as $p) {
                if (strtolower($p->attribute ?? '') === 'charset') {
                    return $p->value;
                }
            }
        }
        return null;
    }

    protected function decodePart(string $raw, int $encoding): string
    {
        return match ($encoding) {
            3 => (string) @imap_base64($raw),
            4 => (string) @imap_qprint($raw),
            default => $raw,
        };
    }

    protected function mimeDecode(string $str): string
    {
        $r = @imap_mime_header_decode($str);
        if (!$r) {
            return $str;
        }
        $out = '';
        foreach ($r as $p) {
            $out .= $p->text;
        }
        return $out;
    }
}
