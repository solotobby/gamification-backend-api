<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id');
            $table->string('subject');
            $table->text('message');
            $table->string('proof_url');

            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])
                ->default('open');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('tickets');
    }
};
