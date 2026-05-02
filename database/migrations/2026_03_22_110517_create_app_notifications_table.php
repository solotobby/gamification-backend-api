<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            $table->string('title');
            $table->text('body');
            $table->string('type')->default('general'); // general|wallet|verification|withdrawal
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_broadcast')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('app_notifications');
    }
};
