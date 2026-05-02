<?php

namespace App\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

abstract class BaseMigration extends Migration
{
    /* ---------------------------------
     | TABLE HELPERS
     |---------------------------------*/

    protected function create(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) {
            Schema::create($table, $callback);
        }
    }

    protected function table(string $table, \Closure $callback): void
    {
        if (Schema::hasTable($table)) {
            Schema::table($table, $callback);
        }
    }

    protected function drop(string $table): void
    {
        if (Schema::hasTable($table)) {
            Schema::drop($table);
        }
    }

    /* ---------------------------------
     | COLUMN HELPERS
     |---------------------------------*/

    protected function addColumn(string $table, string $column, \Closure $callback): void
    {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
            Schema::table($table, $callback);
        }
    }

    protected function dropColumn(string $table, string $column): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    protected function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /* ---------------------------------
     | INDEX HELPERS (MySQL)
     |---------------------------------*/

    protected function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        return collect($indexes)->pluck('Key_name')->contains($indexName);
    }

    protected function addIndex(string $table, string $indexName, \Closure $callback): void
    {
        if (Schema::hasTable($table) && !$this->hasIndex($table, $indexName)) {
            Schema::table($table, $callback);
        }
    }

    protected function dropIndex(string $table, string $indexName): void
    {
        if (Schema::hasTable($table) && $this->hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }
}
