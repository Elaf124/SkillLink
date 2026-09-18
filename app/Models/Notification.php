<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'link',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create an in-app notification for a user. Accepts a User model or an id.
     * Silently does nothing when there is no recipient (e.g. an unassigned side).
     */
    public static function notify($user, string $type, string $title, string $message, ?string $link = null): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        if (! $userId) {
            return;
        }

        static::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
            'is_read' => false,
        ]);
    }
}
