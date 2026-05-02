<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->addColumn('market_place_products', 'product_id', function (Blueprint $table) {
            $table->string('product_id');
        });

        $this->addColumn('market_place_products', 'views', function (Blueprint $table) {
            $table->integer('views');
        });

        $this->addColumn('market_place_products', 'description', function (Blueprint $table) {
            $table->longText('description');
        });
    }

    public function down(): void
    {
        $this->dropColumn('market_place_products', 'product_id');
        $this->dropColumn('market_place_products', 'views');
        $this->dropColumn('market_place_products', 'description');
    }
};
