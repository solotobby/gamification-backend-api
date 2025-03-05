<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'facebook_id',
        'twitter_id',
        'avatar',
        'role',
        'referral_code',
        'source',
        'phone',
        'country',
        'age_range',
        'gender',
        'base_currency',
        'country'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'base_currency',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function hasRole($role = []): bool
    {
        if (is_array($role)) {
            return in_array($this->role, $role);
        }
        return $this->role == $role;
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function accountDetails()
    {
        return $this->hasOne(Wallet::class);
    }

    public function referees()
    {
        return $this->hasMany(Referral::class, 'referee_id', 'referral_code');
    }

    public function referredBy()
    {
        return $this->hasOne(Referral::class, 'user_id', 'id');
    }

    public function usd_referees()
    {
        return $this->belongsToMany(User::class, 'usdverifieds', 'referral_id');
    }

    public function myWorks()
    {
        return $this->hasMany(CampaignWorker::class, 'campaign_id');
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function firstTransaction()
    {
        return $this->transactions()->exists();
    }


    public function myJobs()
    {
        return $this->hasMany(CampaignWorker::class);
    }

    public function myCampaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function myFeedBackList()
    {
        return $this->hasMany(Feedback::class,  'user_id');
    }

    public function myFeedBackReplies()
    {
        return $this->hasMany(FeedbackReplies::class,  'user_id');
    }

    public function interests()
    {
        return $this->belongsToMany(Preference::class, 'user_interest', 'user_id');
    }

    public function accountInfo()
    {
        return $this->hasOne(AccountInformation::class, 'user_id');
    }

    public function bankDetails()
    {
        return $this->hasOne(BankInformation::class, 'user_id');
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    public function USD_verified()
    {
        return $this->hasOne(Usdverified::class, 'user_id');
    }

    public function USD_verifiedList()
    {
        return $this->hasOne(Usdverified::class, 'user_id');
    }
    public function virtualAccount()
    {
        return $this->hasOne(VirtualAccount::class, 'user_id');
    }

    public function myAttemptedJobs()
    {
        return $this->hasMany(CampaignWorker::class, 'user_id');
    }

    public function myRating()
    {
        return $this->hasMany(Rating::class, 'campaign_id');
    }
}
