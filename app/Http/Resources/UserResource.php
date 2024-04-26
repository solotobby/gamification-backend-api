<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Vanguard\Services\User\FormatUserList;

class UserResource extends JsonResource
{
     /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            // 'user_id' => $this->user_id,
            'name' => $this->name,
           
            'referral_code' => $this->referral_code,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'email' => $this->email,
            'is_verified' => $this->is_verified,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // 'password' => $this->password,
            'avatar' => $this->avatar,
        ];
    }
}