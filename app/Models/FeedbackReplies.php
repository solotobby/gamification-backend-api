<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackReplies extends Model
{
    use HasFactory;

    protected $table = "feedback_replies";

    protected $fillable = [
        'feedback_id',
        'user_id',
        'message',
        'text_message',
        'status',
        'is_image',
        'image_url',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function feedback()
    {
        return $this->belongsTo(Feedback::class);
    }

    public function hasBoth(): bool
    {
        return !empty($this->text_message) && !empty($this->image_url);
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }
}
