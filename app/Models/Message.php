<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message',
        'type',
        'meta',
        'attachment',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'meta'    => 'array',
    ];

    /** Bump the parent conversation's updated_at whenever a message is written. */
    protected $touches = ['conversation'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
   
}