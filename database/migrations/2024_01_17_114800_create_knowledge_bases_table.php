<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class CreateKnowledgeBasesTable extends BaseMigration
{
    public function up()
    {
        $this->create('knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('question');
            $table->longText('answer');
            $table->string('url')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->drop('knowledge_bases');
    }
}
