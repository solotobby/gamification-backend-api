<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration
{
    public function up(): void
    {
        $this->create('manual_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('payment_method'); // bank_transfer|crypto|paystack|korapay
            $table->string('reference')->nullable();
            $table->string('proof_image')->nullable(); // uploaded image path

            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);

            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('manual_verifications');
    }
};
