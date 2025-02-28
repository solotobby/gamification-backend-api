<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('sender_id');
            $table->text('message');
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets');
            $table->foreign('sender_id')->references('id')->on('users');
        });
    }

    public function down() {
        Schema::dropIfExists('ticket_messages');
    }
};
