<?php

// use Illuminate\Database\Migrations\Migration;
use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionsTable extends BaseMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->create('questions', function (Blueprint $table) {
            $table->id();
            $table->longText('content')->nullable();
            $table->text('asset_url')->nullable();
            $table->string('option_A')->nullable();
            $table->string('option_B')->nullable();
            $table->string('option_C')->nullable();
            $table->string('option_D')->nullable();
            $table->string('correct_answer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       $this->drop('questions');
    }
}
