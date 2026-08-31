<?php

namespace App\Services\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailSendService
{
    /**
     * @param  array{
     *   to:string, cc?:string, bcc?:string, reply_to?:string,
     *   subject:string, body_html:string,
     *   in_reply_to?:string, references?:string,
     *   attachments?: array<int, array{full_path:string,name?:string,mime?:string}>,
     *   case_id?:int|null, contact_id?:int|null
     * }  $data
     */
    public function send(User $user, MailAccount $account, array $data): array
    {
        if (!$account->isReadyToSend()) {
            return ['ok' => false, 'message' => 'اکانت برای ارسال پیکربندی نشده است'];
        }

        $cfg = $account->effectiveConfig();
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $cfg['smtp_host'],
            'port' => (int) $cfg['smtp_port'],
            'encryption' => ($cfg['smtp_encryption'] ?? 'tls') === 'none' ? null : $cfg['smtp_encryption'],
            'username' => $cfg['smtp_username'],
            'password' => $cfg['smtp_password'],
            'timeout' => 30,
        ]);
        Config::set('mail.from', [
            'address' => $cfg['email'] ?: $cfg['smtp_username'],
            'name' => $cfg['display_name'] ?: ($cfg['email'] ?: 'RFQ'),
        ]);

        $to = trim((string) ($data['to'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'گیرنده معتبر نیست'];
        }

        $ccList = $this->splitEmails($data['cc'] ?? '');
        $bccList = $this->splitEmails($data['bcc'] ?? '');
        $attachments = $data['attachments'] ?? [];
        $bodyHtml = (string) ($data['body_html'] ?? '');
        $subject = (string) ($data['subject'] ?? '');

        try {
            $rawCapture = null;
            Mail::send([], [], function ($message) use ($cfg, $to, $ccList, $bccList, $subject, $bodyHtml, $attachments, $data, &$rawCapture) {
                $message->from($cfg['email'] ?: $cfg['smtp_username'], $cfg['display_name'] ?: null);
                $message->to($to);
                if ($ccList) {
                    $message->cc($ccList);
                }
                if ($bccList) {
                    $message->bcc($bccList);
                }
                if (!empty($data['reply_to']) && filter_var($data['reply_to'], FILTER_VALIDATE_EMAIL)) {
                    $message->replyTo($data['reply_to']);
                }
                $message->subject($subject);
                $message->html($bodyHtml !== '' ? $bodyHtml : '<p></p>');

                foreach ($attachments as $att) {
                    if (!empty($att['full_path']) && is_file($att['full_path'])) {
                        $message->attach($att['full_path'], array_filter([
                            'as' => $att['name'] ?? basename($att['full_path']),
                            'mime' => $att['mime'] ?? null,
                        ]));
                    }
                }

                $symfony = $message->getSymfonyMessage();
                if (!empty($data['in_reply_to'])) {
                    $symfony->getHeaders()->addTextHeader('In-Reply-To', '<'.trim($data['in_reply_to'], '<>').'>');
                }
                if (!empty($data['references'])) {
                    $symfony->getHeaders()->addTextHeader('References', $data['references']);
                }

                try {
                    $rawCapture = $symfony->toString();
                } catch (\Throwable $e) {
                    $rawCapture = null;
                }
            });

            // ثبت محلی در فولدر Sent
            $localId = $this->storeOutboundLocal($account, $data, $to, $ccList, $bccList, $bodyHtml, $subject);

            // تلاش برای append روی IMAP Sent (اختیاری)
            if ($rawCapture && $account->isReadyToReceive()) {
                try {
                    $this->appendToSent($account, $rawCapture);
                } catch (\Throwable $e) {
                    Log::info('mail.append_sent_failed', ['error' => $e->getMessage()]);
                }
            }

            return ['ok' => true, 'mail_message_id' => $localId];
        } catch (\Throwable $e) {
            Log::error('mail.send_failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function storeOutboundLocal(MailAccount $account, array $data, string $to, array $cc, array $bcc, string $bodyHtml, string $subject): ?int
    {
        $sentFolder = MailFolder::firstOrCreate(
            [
                'mail_account_id' => $account->id,
                'remote_path' => 'RFQ.Sent',
            ],
            [
                'name' => 'ارسالی (محلی)',
                'role' => 'sent',
                'delimiter' => '.',
            ]
        );

        $messageId = Str::uuid()->toString().'@rfq.local';
        $msg = MailMessage::create([
            'mail_account_id' => $account->id,
            'mail_folder_id' => $sentFolder->id,
            'uid' => (int) (microtime(true) * 1000) % 2000000000,
            'message_id' => $messageId,
            'in_reply_to' => isset($data['in_reply_to']) ? trim($data['in_reply_to'], '<>') : null,
            'references_header' => $data['references'] ?? null,
            'thread_key' => MailMessage::buildThreadKey(
                $messageId,
                $data['in_reply_to'] ?? null,
                $data['references'] ?? null
            ),
            'from_address' => $account->email,
            'from_name' => $account->display_name,
            'to_json' => [['name' => null, 'email' => $to]],
            'cc_json' => $cc ? array_map(fn ($e) => ['name' => null, 'email' => $e], $cc) : null,
            'bcc_json' => $bcc ? array_map(fn ($e) => ['name' => null, 'email' => $e], $bcc) : null,
            'reply_to' => $data['reply_to'] ?? null,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => trim(html_entity_decode(strip_tags($bodyHtml))),
            'date_sent' => now(),
            'is_seen' => true,
            'is_answered' => false,
            'has_attachments' => !empty($data['attachments']),
            'case_id' => $data['case_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'synced_at' => now(),
        ]);

        return $msg->id;
    }

    protected function appendToSent(MailAccount $account, string $raw): void
    {
        if (!function_exists('imap_open')) {
            return;
        }
        $cfg = $account->effectiveConfig();
        $enc = ($cfg['imap_encryption'] ?? 'ssl') === 'none' ? 'notls' : ($cfg['imap_encryption'] ?? 'ssl');
        $folder = $cfg['imap_sent_folder'] ?: 'Sent';
        $mailbox = sprintf('{%s:%d/imap/%s}%s', $cfg['imap_host'], (int) $cfg['imap_port'], $enc, $folder);
        $stream = @imap_open($mailbox, $cfg['imap_username'], $cfg['imap_password'], 0, 1);
        if (!$stream) {
            // تلاش با نام‌های رایج
            foreach (['Sent', 'INBOX.Sent', 'Sent Messages', 'INBOX.Sent Messages'] as $f) {
                $mailbox = sprintf('{%s:%d/imap/%s}%s', $cfg['imap_host'], (int) $cfg['imap_port'], $enc, $f);
                $stream = @imap_open($mailbox, $cfg['imap_username'], $cfg['imap_password'], 0, 1);
                if ($stream) {
                    break;
                }
            }
        }
        if (!$stream) {
            return;
        }
        $normalized = preg_replace("/\r\n|\r|\n/", "\r\n", $raw);
        @imap_append($stream, $mailbox, $normalized);
        imap_close($stream);
    }

    protected function splitEmails($raw): array
    {
        if (!$raw) {
            return [];
        }
        $parts = preg_split('/[,;]+/', (string) $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $e = trim($p);
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = $e;
            }
        }

        return array_values(array_unique($out));
    }
}
