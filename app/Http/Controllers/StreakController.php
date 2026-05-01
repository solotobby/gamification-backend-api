<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use App\Services\StreakService;
use Illuminate\Http\Request;

class StreakController extends Controller
{
    public function __construct(protected StreakService $streakService) {}

    public function progress()
    {

        $user     = auth()->user();

        // $bonus    = Bonus::where('user_id', $user->id)->first();
        $progress = $this->streakService->getStreakProgress($user);

          return response()->json([
            'status' => true,
            'message' => 'Streaks details retrieved successfully',
            'data' => [
                'bonus_balance'   => auth()->user()->wallet?->bonus ?? 0,
                'streak_redeemed' => $user->streak_redeemed,
                'criteria'        => $progress['criteria'],
                'any_met'         => $progress['any_met'],
            ]
        ], 200);
    }
}
