<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $table = "Withrawals";
    protected $fillable = [
        'user_id',
        'amount',
        'next_payment_date',
        'status',
        'currency',
        'channel',
        'paypal_email',
        'is_usd',
        'base_currency'
    ];

    public function user()
    {
        return  $this->belongsTo(User::class);
    }

    public function accountDetails()
    {
        return $this->belongsTo(BankInformation::class);
    }
}
