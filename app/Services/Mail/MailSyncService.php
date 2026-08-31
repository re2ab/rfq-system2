<?php

namespace App\Services\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailMessageAttachment;
use Illuminate\Support\Facades\Log;

/**
 * همگام‌سازی IMAP → جداول محلی mail_*.
 * از ext-imap استفاده می‌کند (همان وابستگی فعلی پروژه).
 * پیام از سرور میل حذف نمی‌شود.
 */
class MailSyncService
{
    /** حداکثر پیام تازه‌ای که در هر فولدر در یک اجرای sync خوانده می‌شود */
    protected int $maxPerFolder = 100;

    public function syncAccount(MailAccount $account, bool $withBodies = true): array
    {
        if (!$account->isReadyToReceive()) {
            return ['ok' => false, 'message' => 'اکانت برای دریافت پیکربندی نشده یا غیرفعال است'];
        }
        if (!function_exists('imap_open')) {
            return ['ok' => false, 'message' => 'افزونه php-imap روی سرور نصب نیست'];
        }

        $stats = ['folders' => 0, 'messages' => 0, 'errors' => []];

        try {
            $folders = $this->discoverFolders($account);
            $stats['folders'] = count($folders);

            foreach ($folders as $folder) {
                try {
                    $n = $this->syncFolder($account, $folder, $withBodies);
                    $stats['messages'] += $n;
                } catch (\Throwable $e) {
                    $stats['errors'][] = $folder->remote_path.': '.$e->getMessage();
                    Log::warning('mail.sync.folder_failed', [
                        'account_id' => $account->id,
                        'folder' => $folder->remote_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $account->last_synced_at = now();
            $account->last_sync_error = $stats['errors'] ? implode("\n", array_slice($stats['errors'], 0, 5)) : null;
            $account->save();

            return ['ok' => true, 'stats' => $stats];
        } catch (\Throwable $e) {
            $account->last_sync_error = $e->getMessage();
            $account->save();
            Log::error('mail.sync.account_failed', ['account_id' => $account->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage(), 'stats' => $stats];
        }
    }

    public function testConnection(MailAccount $account): array
    {
        if (!function_exists('imap_open')) {
            return ['ok' => false, 'message' => 'php-imap نصب نیست'];
        }
        $stream = $this->open($account, 'INBOX');
        if (!$stream) {
            return ['ok' => false, 'message' => function_exists('imap_last_error') ? (imap_last_error() ?: 'اتصال ناموفق') : 'اتصال ناموفق'];
        }
        $n = @imap_num_msg($stream);
        imap_close($stream);

        return ['ok' => true, 'message' => 'اتصال موفق — تعداد پیام INBOX: '.(int) $n];
    }

    /** کشف و upsert فولدرهای IMAP */
    public function discoverFolders(MailAccount $account): array
    {
        $cfg = $account->effectiveConfig();
        $ref = $this->mailboxRef($cfg, '');
        $stream = @imap_open($ref.'INBOX', $cfg['imap_username'], $cfg['imap_password'], OP_HALFOPEN, 1);
        if (!$stream) {
            throw new \RuntimeException(function_exists('imap_last_error') ? (imap_last_error() ?: 'باز کردن IMAP ناموفق') : 'باز کردن IMAP ناموفق');
        }

        $list = @imap_list($stream, $ref, '*') ?: [];
        imap_close($stream);

        $out = [];
        foreach ($list as $full) {
            $remote = $this->stripRef($full, $ref);
            if ($remote === '') {
                continue;
            }
            $role = MailFolder::guessRole($remote);
            $name = $this->displayName($remote);
            $folder = MailFolder::updateOrCreate(
                [
                    'mail_account_id' => $account->id,
                    'remote_path' => $remote,
                ],
                [
                    'name' => $name,
                    'role' => $role,
                    'delimiter' => str_contains($remote, '/') ? '/' : '.',
                ]
            );
            $out[] = $folder;
        }

        // اگر سرور لیست نداد، حداقل INBOX
        if (!$out) {
            $out[] = MailFolder::updateOrCreate(
                ['mail_account_id' => $account->id, 'remote_path' => 'INBOX'],
                ['name' => 'صندوق ورودی', 'role' => 'inbox', 'delimiter' => '.']
            );
        }

        return $out;
    }

    public function syncFolder(MailAccount $account, MailFolder $folder, bool $withBodies = true): int
    {
        $stream = $this->open($account, $folder->remote_path);
        if (!$stream) {
            throw new \RuntimeException('باز کردن فولدر '.$folder->remote_path.' ناموفق');
        }

        $check = @imap_check($stream);
        $uidValidity = isset($check->Uidvalidity) ? (int) $check->Uidvalidity : null;
        $total = (int) @imap_num_msg($stream);

        // اگر uidvalidity عوض شده، UIDهای قبلی برای این فولدر بی‌اعتبارند
        if ($uidValidity && $folder->uidvalidity && (int) $folder->uidvalidity !== $uidValidity) {
            MailMessage::where('mail_folder_id', $folder->id)->delete();
        }

        $folder->uidvalidity = $uidValidity;
        $folder->message_count = $total;

        $unseenIds = @imap_search($stream, 'UNSEEN', SE_UID);
        $folder->unseen_count = is_array($unseenIds) ? count($unseenIds) : 0;

        $imported = 0;
        if ($total > 0) {
            $start = max(1, $total - $this->maxPerFolder + 1);
            $overview = @imap_fetch_overview($stream, "{$start}:{$total}", 0) ?: [];

            foreach ($overview as $o) {
                $uid = (int) ($o->uid ?? 0);
                if ($uid < 1) {
                    continue;
                }

                $exists = MailMessage::where('mail_folder_id', $folder->id)->where('uid', $uid)->first();
                if ($exists && $exists->body_html === null && $exists->body_text === null && $withBodies) {
                    // فقط بدنه را تکمیل کن
                    $this->hydrateBody($stream, $exists);
                    $imported++;
                    continue;
                }
                if ($exists) {
                    // به‌روزرسانی فلگ‌ها
                    $exists->is_seen = (bool) ($o->seen ?? $exists->is_seen);
                    $exists->is_flagged = (bool) ($o->flagged ?? $exists->is_flagged);
                    $exists->is_answered = (bool) ($o->answered ?? $exists->is_answered);
                    $exists->save();
                    continue;
                }

                $messageId = isset($o->message_id) ? $this->cleanMsgId($o->message_id) : null;
                $subject = isset($o->subject) ? $this->mimeDecode($o->subject) : null;
                $fromRaw = isset($o->from) ? $this->mimeDecode($o->from) : '';
                [$fromName, $fromAddr] = $this->parseFrom($fromRaw);
                $dateSent = null;
                if (!empty($o->date)) {
                    try {
                        $dateSent = \Carbon\Carbon::parse($o->date);
                    } catch (\Throwable $e) {
                        $dateSent = null;
                    }
                }

                $msg = MailMessage::create([
                    'mail_account_id' => $account->id,
                    'mail_folder_id' => $folder->id,
                    'uid' => $uid,
                    'message_id' => $messageId,
                    'thread_key' => MailMessage::buildThreadKey($messageId, null, null),
                    'from_address' => $fromAddr,
                    'from_name' => $fromName,
                    'subject' => $subject,
                    'date_sent' => $dateSent,
                    'is_seen' => (bool) ($o->seen ?? false),
                    'is_flagged' => (bool) ($o->flagged ?? false),
                    'is_answered' => (bool) ($o->answered ?? false),
                    'is_draft' => (bool) ($o->draft ?? false),
                    'size' => (int) ($o->size ?? 0),
                    'synced_at' => now(),
                ]);

                if ($withBodies) {
                    $this->hydrateBody($stream, $msg);
                }
                $imported++;
            }
        }

        $folder->last_synced_at = now();
        $folder->save();
        imap_close($stream);

        return $imported;
    }

    protected function hydrateBody($stream, MailMessage $msg): void
    {
        $uid = (int) $msg->uid;
        $structure = @imap_fetchstructure($stream, $uid, FT_UID);
        $headers = @imap_fetchheader($stream, $uid, FT_UID) ?: '';

        $inReplyTo = null;
        $references = null;
        $replyTo = null;
        $toList = [];
        $ccList = [];
        if (preg_match('/^In-Reply-To:\s*(.+)$/mi', $headers, $m)) {
            $inReplyTo = $this->cleanMsgId(trim($m[1]));
        }
        if (preg_match('/^References:\s*(.+)$/mi', $headers, $m)) {
            $references = trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        if (preg_match('/^Reply-To:\s*(.+)$/mi', $headers, $m)) {
            $replyTo = trim($m[1]);
        }
        if (preg_match('/^To:\s*(.+)$/mi', $headers, $m)) {
            $toList = $this->parseAddressList($this->mimeDecode(trim($m[1])));
        }
        if (preg_match('/^Cc:\s*(.+)$/mi', $headers, $m)) {
            $ccList = $this->parseAddressList($this->mimeDecode(trim($m[1])));
        }

        $text = '';
        $html = '';
        $attachmentsMeta = [];
        if ($structure) {
            $this->walkStructure($stream, $uid, $structure, '', $text, $html, $attachmentsMeta);
        } else {
            $raw = @imap_body($stream, $uid, FT_UID) ?: '';
            $text = $raw;
        }

        $msg->body_text = $text !== '' ? $text : null;
        $msg->body_html = $html !== '' ? $html : null;
        $msg->raw_headers = mb_substr($headers, 0, 20000);
        $msg->in_reply_to = $inReplyTo;
        $msg->references_header = $references;
        $msg->reply_to = $replyTo ? mb_substr($replyTo, 0, 255) : null;
        $msg->to_json = $toList ?: null;
        $msg->cc_json = $ccList ?: null;
        $msg->thread_key = MailMessage::buildThreadKey($msg->message_id, $inReplyTo, $references);
        $msg->has_attachments = count($attachmentsMeta) > 0;
        $msg->synced_at = now();
        $msg->save();

        // متادیتای پیوست (بدون دانلود فایل در فاز A — دانلود در فاز B/C)
        if ($attachmentsMeta) {
            MailMessageAttachment::where('mail_message_id', $msg->id)->delete();
            foreach ($attachmentsMeta as $att) {
                MailMessageAttachment::create([
                    'mail_message_id' => $msg->id,
                    'part_number' => $att['part'],
                    'filename' => $att['filename'],
                    'mime' => $att['mime'],
                    'size' => $att['size'],
                    'content_id' => $att['content_id'],
                    'is_inline' => $att['inline'],
                ]);
            }
        }
    }

    protected function walkStructure($stream, int $uid, $structure, string $part, string &$text, string &$html, array &$attachments): void
    {
        $type = (int) ($structure->type ?? 0);
        $subtype = strtoupper($structure->subtype ?? '');
        $disposition = strtolower($structure->disposition ?? '');
        $filename = $this->partFilename($structure);
        $isAttachment = $disposition === 'attachment' || ($filename && $disposition === 'inline' && $type !== 0 && $type !== 1);

        if (!empty($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $i => $sub) {
                $subPart = $part === '' ? (string) ($i + 1) : $part.'.'.($i + 1);
                $this->walkStructure($stream, $uid, $sub, $subPart, $text, $html, $attachments);
            }

            return;
        }

        $partNum = $part === '' ? '1' : $part;
        $raw = @imap_fetchbody($stream, $uid, $partNum, FT_UID | FT_PEEK);
        if ($raw === false || $raw === null) {
            $raw = '';
        }
        $decoded = $this->decodePart($raw, (int) ($structure->encoding ?? 0));
        $charset = $this->partCharset($structure);
        if ($charset && strtoupper($charset) !== 'UTF-8') {
            $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);
            if ($converted !== false) {
                $decoded = $converted;
            }
        }

        if ($isAttachment || ($filename && $type !== 0 /* text */)) {
            $attachments[] = [
                'part' => $partNum,
                'filename' => $filename ?: ('part-'.$partNum),
                'mime' => $this->mimeOf($structure),
                'size' => (int) ($structure->bytes ?? strlen($decoded)),
                'content_id' => isset($structure->id) ? trim($structure->id, '<>') : null,
                'inline' => $disposition === 'inline',
            ];

            return;
        }

        if ($type === 0 && $subtype === 'HTML') {
            if ($html === '') {
                $html = $decoded;
            }

            return;
        }
        if ($type === 0) {
            if ($text === '') {
                $text = $decoded;
            }
        }
    }

    protected function open(MailAccount $account, string $folderPath)
    {
        $cfg = $account->effectiveConfig();
        $ref = $this->mailboxRef($cfg, $folderPath);
        $stream = @imap_open($ref, $cfg['imap_username'], $cfg['imap_password'], 0, 1);

        return $stream ?: null;
    }

    protected function mailboxRef(array $cfg, string $folderPath): string
    {
        $enc = ($cfg['imap_encryption'] ?? 'ssl') === 'none' ? 'notls' : ($cfg['imap_encryption'] ?? 'ssl');
        $base = sprintf('{%s:%d/imap/%s}', $cfg['imap_host'], (int) $cfg['imap_port'], $enc);

        return $folderPath === '' ? $base : $base.$folderPath;
    }

    protected function stripRef(string $full, string $ref): string
    {
        if (str_starts_with($full, $ref)) {
            return substr($full, strlen($ref));
        }
        // بعضی سرورها {host}path می‌دهند
        if (preg_match('/\}(.+)$/', $full, $m)) {
            return $m[1];
        }

        return $full;
    }

    protected function displayName(string $remote): string
    {
        $map = [
            'INBOX' => 'صندوق ورودی',
            'Sent' => 'ارسالی',
            'Sent Messages' => 'ارسالی',
            'Drafts' => 'پیش‌نویس',
            'Trash' => 'سطل زباله',
            'Junk' => 'هرزنامه',
            'Spam' => 'هرزنامه',
            'Archive' => 'آرشیو',
        ];
        $base = $remote;
        if (str_contains($remote, '.')) {
            $parts = explode('.', $remote);
            $base = end($parts);
        } elseif (str_contains($remote, '/')) {
            $parts = explode('/', $remote);
            $base = end($parts);
        }

        return $map[$base] ?? $map[$remote] ?? $base;
    }

    protected function mimeDecode(string $str): string
    {
        $decoded = @imap_mime_header_decode($str);
        if (!$decoded) {
            return $str;
        }
        $out = '';
        foreach ($decoded as $part) {
            $charset = ($part->charset === 'default') ? 'UTF-8' : $part->charset;
            $text = $part->text;
            if (strtoupper($charset) !== 'UTF-8') {
                $conv = @mb_convert_encoding($text, 'UTF-8', $charset);
                $text = $conv !== false ? $conv : $text;
            }
            $out .= $text;
        }

        return $out;
    }

    protected function parseFrom(string $raw): array
    {
        if (preg_match('/^(.*)<([^>]+)>$/', trim($raw), $m)) {
            return [trim($m[1], " \t\"'"), trim($m[2])];
        }
        if (filter_var(trim($raw), FILTER_VALIDATE_EMAIL)) {
            return [null, trim($raw)];
        }

        return [$raw ?: null, null];
    }

    protected function parseAddressList(string $raw): array
    {
        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $raw) ?: [];
        $list = [];
        foreach ($parts as $p) {
            [$n, $a] = $this->parseFrom(trim($p));
            if ($a || $n) {
                $list[] = ['name' => $n, 'email' => $a];
            }
        }

        return $list;
    }

    protected function cleanMsgId(?string $id): ?string
    {
        if (!$id) {
            return null;
        }
        $id = trim($id);
        // ممکن است چند مقدار باشد
        if (preg_match('/<([^>]+)>/', $id, $m)) {
            return $m[1];
        }

        return trim($id, '<>');
    }

    protected function partFilename($structure): ?string
    {
        if (!empty($structure->dparameters)) {
            foreach ($structure->dparameters as $p) {
                if (strtolower($p->attribute ?? '') === 'filename') {
                    return $this->mimeDecode($p->value ?? '');
                }
            }
        }
        if (!empty($structure->parameters)) {
            foreach ($structure->parameters as $p) {
                if (strtolower($p->attribute ?? '') === 'name') {
                    return $this->mimeDecode($p->value ?? '');
                }
            }
        }

        return null;
    }

    protected function partCharset($structure): ?string
    {
        if (!empty($structure->parameters)) {
            foreach ($structure->parameters as $p) {
                if (strtolower($p->attribute ?? '') === 'charset') {
                    return $p->value ?? null;
                }
            }
        }

        return null;
    }

    protected function mimeOf($structure): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
        $t = $types[(int) ($structure->type ?? 7)] ?? 'application';
        $sub = strtolower($structure->subtype ?? 'octet-stream');

        return $t.'/'.$sub;
    }

    protected function decodePart(string $raw, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($raw) ?: $raw,       // BASE64
            4 => quoted_printable_decode($raw),     // QUOTED-PRINTABLE
            default => $raw,
        };
    }
}
