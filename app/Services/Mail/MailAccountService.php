<?php

namespace App\Services\Mail;

use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class MailAccountService
{
    public function create(array $data, ?User $actor = null): MailAccount
    {
        return DB::transaction(function () use ($data, $actor) {
            $account = new MailAccount();
            $this->fillAccount($account, $data);
            $account->created_by = $actor?->id;
            $account->save();

            if (!empty($data['user_ids']) && is_array($data['user_ids'])) {
                $this->syncUsers($account, $data['user_ids'], $data['default_user_id'] ?? null);
            }

            if ($actor && class_exists(AuditLogger::class)) {
                try {
                    AuditLogger::log('mail_account_created', 'mail_account', $account->id);
                } catch (\Throwable $e) {
                }
            }

            return $account->fresh(['users']);
        });
    }

    public function update(MailAccount $account, array $data, ?User $actor = null): MailAccount
    {
        return DB::transaction(function () use ($account, $data, $actor) {
            $this->fillAccount($account, $data);
            $account->save();

            if (array_key_exists('user_ids', $data) && is_array($data['user_ids'])) {
                $this->syncUsers($account, $data['user_ids'], $data['default_user_id'] ?? null);
            }

            if ($actor && class_exists(AuditLogger::class)) {
                try {
                    AuditLogger::log('mail_account_updated', 'mail_account', $account->id);
                } catch (\Throwable $e) {
                }
            }

            return $account->fresh(['users']);
        });
    }

    public function syncUsers(MailAccount $account, array $userIds, $defaultUserId = null): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $sync = [];
        foreach ($userIds as $uid) {
            $sync[$uid] = [
                'can_read' => true,
                'can_send' => true,
                'is_default' => $defaultUserId && (int) $defaultUserId === (int) $uid,
            ];
        }
        $account->users()->sync($sync);
    }

    public function accountsForUser(User $user, bool $onlyActive = true)
    {
        $q = MailAccount::query()
            ->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id)->where('mail_account_user.can_read', true);
            });
        if ($onlyActive) {
            $q->where('is_active', true);
        }

        return $q->orderByDesc('is_shared')->orderBy('name')->get();
    }

    public function userCanAccess(User $user, MailAccount $account, string $ability = 'read'): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }
        $pivot = $account->users()->where('users.id', $user->id)->first();
        if (!$pivot) {
            return false;
        }
        if ($ability === 'send') {
            return (bool) $pivot->pivot->can_send;
        }

        return (bool) $pivot->pivot->can_read;
    }

    protected function fillAccount(MailAccount $account, array $data): void
    {
        foreach ([
            'name', 'email', 'display_name', 'smtp_host', 'smtp_encryption', 'smtp_username',
            'imap_host', 'imap_encryption', 'imap_username', 'imap_sent_folder',
        ] as $f) {
            if (array_key_exists($f, $data)) {
                $account->{$f} = $data[$f];
            }
        }
        if (array_key_exists('smtp_port', $data)) {
            $account->smtp_port = (int) $data['smtp_port'];
        }
        if (array_key_exists('imap_port', $data)) {
            $account->imap_port = (int) $data['imap_port'];
        }
        if (array_key_exists('is_shared', $data)) {
            $account->is_shared = (bool) $data['is_shared'];
        }
        if (array_key_exists('is_active', $data)) {
            $account->is_active = (bool) $data['is_active'];
        }
        // رمز فقط اگر مقدار غیرخالی آمده باشد
        if (!empty($data['smtp_password'])) {
            $account->smtp_password = $data['smtp_password'];
        }
        if (!empty($data['imap_password'])) {
            $account->imap_password = $data['imap_password'];
        } elseif (!empty($data['smtp_password']) && !$account->imapPasswordPlain()) {
            $account->imap_password = $data['smtp_password'];
        }
    }
}
