<?php

use App\Database\Migrations\BaseMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends BaseMigration {

    public function up(): void
    {
        $this->table('activity_logs', function (Blueprint $table) {
            if (!$this->columnExists('activity_logs', 'client_type')) {
                $table->string('client_type', 50)->nullable()->after('user_type')->index();
            }
            if (!$this->columnExists('activity_logs', 'device')) {
                $table->string('device', 50)->nullable()->after('client_type')->index();
            }
            if (!$this->columnExists('activity_logs', 'platform')) {
                $table->string('platform', 100)->nullable()->after('device');
            }
            if (!$this->columnExists('activity_logs', 'browser')) {
                $table->string('browser', 100)->nullable()->after('platform');
            }
            if (!$this->columnExists('activity_logs', 'device_model')) {
                $table->string('device_model', 150)->nullable()->after('browser');
            }
            if (!$this->columnExists('activity_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('device_model')->index();
            }
            if (!$this->columnExists('activity_logs', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (!$this->columnExists('activity_logs', 'action')) {
                $table->string('action', 255)->nullable()->after('user_agent');
            }
            if (!$this->columnExists('activity_logs', 'properties')) {
                $table->json('properties')->nullable()->after('action');
            }
        });
    }

    public function down(): void
    {
        $this->table('activity_logs', function (Blueprint $table) {
            $columns = [
                'client_type',
                'device',
                'platform',
                'browser',
                'device_model',
                'ip_address',
                'user_agent',
                'action',
                'properties',
            ];

            foreach ($columns as $column) {
                if ($this->columnExists('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
