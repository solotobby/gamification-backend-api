<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\Request;

class ReferralController extends Controller
{

    protected $referral;
    public function __construct(ReferralService $referral)
    {
        $this->referral = $referral;
    }

    public function referralList()
    {
        return $this->referral->referralList();
    }

    public function referralStat(){
        return $this->referral->referralStat();
    }
}
