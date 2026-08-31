<?php
namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function notify(User|int $user, string $title, ?string $body = null, ?string $link = null): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'is_read' => false,
        ]);
    }

    public function notifyRole(string $role, string $title, ?string $body = null, ?string $link = null): void
    {
        try {
            $users = User::role($role)->get();
            foreach ($users as $u) {
                $this->notify($u, $title, $body, $link);
            }
        } catch (\Throwable $e) {
            // ignore if roles not ready
        }
    }
}
