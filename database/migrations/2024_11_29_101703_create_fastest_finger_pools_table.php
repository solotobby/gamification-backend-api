<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('fastest_finger_pools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('date');
            $table->boolean('is_selected')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('fastest_finger_pools');
    }
};
