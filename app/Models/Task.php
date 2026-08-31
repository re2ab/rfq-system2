<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'description', 'task_type', 'priority', 'status',
        'due_at', 'assigned_to', 'created_by', 'case_id', 'contact_id',
        'is_team', 'requires_approval', 'completed_at', 'completion_note',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_team' => 'boolean',
        'requires_approval' => 'boolean',
    ];

    /** مقادیر ذخیره‌شده در دیتابیس (انگلیسی) => برچسب فارسی */
    /** @deprecated از task_priorities() استفاده کنید؛ این ثابت فقط fallback است */
    public const PRIORITIES = [
        'low' => 'پایین',
        'medium' => 'متوسط',
        'high' => 'بالا',
        'urgent' => 'فوری',
    ];

    public static function priorityOptions(): array
    {
        return function_exists('task_priorities') ? task_priorities() : self::PRIORITIES;
    }

    public const STATUSES = [
        'open' => 'باز',
        'in_progress' => 'در حال انجام',
        'done' => 'انجام‌شده',
        'cancelled' => 'لغو شده',
        'overdue' => 'معوق',
    ];

public function getPriorityLabelAttribute(): string
{
$priorityValue = $this->priority;
// اطمینان از اینکه کلید جستجو معتبر است
if (!is_string($priorityValue) && !is_numeric($priorityValue)) {
return 'نامشخص';
}

$label = self::priorityOptions()[$priorityValue] ?? $priorityValue;

// اگر خروجی به جای متن، یک آرایه بود، آن را مدیریت می‌کنیم تا سایت کرش نکند
if (is_array($label)) {
return $label['label'] ?? $label['name'] ?? 'نامشخص';
}

return (string) $label;
}

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id')->withTimestamps();
    }

    public function allAssignees()
    {
        $this->loadMissing(['assignee', 'assignees']);
        $list = collect();
        if ($this->assignee) {
            $list->push($this->assignee);
        }
        foreach ($this->assignees as $u) {
            if (!$list->contains('id', $u->id)) {
                $list->push($u);
            }
        }
        return $list;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class);
    }

    public function scopeVisibleTo($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('assigned_to', $userId)
              ->orWhere('is_team', true);
        });
    }
}
