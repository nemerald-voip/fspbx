<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_job_nodes')) {
            Schema::create('scheduled_job_nodes', function (Blueprint $table) {
                $table->uuid('scheduled_job_node_uuid')->primary();
                $table->string('system_identifier', 32)->unique();
                $table->string('host_fingerprint', 64);
                $table->string('registered_on_node_id', 32);
                $table->string('hostname');
                $table->string('endpoint', 2048);
                $table->string('status', 32)->default('approved')->index();
                $table->timestamp('approved_at')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->timestamp('retired_at')->nullable();
                $table->uuid('retired_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('scheduled_job_handoffs')) {
            Schema::create('scheduled_job_handoffs', function (Blueprint $table) {
                $table->uuid('scheduled_job_handoff_uuid')->primary();
                $table->uuid('idempotency_key')->unique();
                $table->string('from_node_id', 32)->nullable()->index();
                $table->string('to_node_id', 32)->index();
                $table->unsignedBigInteger('expected_generation')->default(0);
                $table->string('status', 32)->default('requested')->index();
                $table->boolean('forced')->default(false);
                $table->string('fenced_endpoint', 2048)->nullable();
                $table->uuid('requested_by')->nullable();
                $table->uuid('forced_by')->nullable();
                $table->text('message')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('scheduled_job_executions')) {
            Schema::create('scheduled_job_executions', function (Blueprint $table) {
                $table->uuid('scheduled_job_execution_uuid')->primary();
                $table->string('job_type', 128);
                $table->string('job_key', 255);
                $table->string('node_id', 32)->index();
                $table->unsignedBigInteger('ownership_generation')->default(0);
                $table->string('status', 32)->default('running')->index();
                $table->timestamp('started_at');
                $table->timestamp('expires_at')->index();
                $table->timestamp('finished_at')->nullable();
                $table->text('message')->nullable();
                $table->timestamps();

                $table->index(['job_type', 'job_key', 'status'], 'scheduled_job_execution_lookup');
            });
        }

        if (Schema::hasTable('ldap_sync_runs')) {
            Schema::table('ldap_sync_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('ldap_sync_runs', 'node_name')) {
                    $table->string('node_name')->nullable();
                }
                if (! Schema::hasColumn('ldap_sync_runs', 'scheduled_job_execution_uuid')) {
                    $table->uuid('scheduled_job_execution_uuid')->nullable()->index();
                }
                if (! Schema::hasColumn('ldap_sync_runs', 'node_id')) {
                    $table->string('node_id', 32)->nullable()->index();
                }
                if (! Schema::hasColumn('ldap_sync_runs', 'ownership_generation')) {
                    $table->unsignedBigInteger('ownership_generation')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ldap_sync_runs')) {
            foreach (['node_name', 'scheduled_job_execution_uuid', 'node_id', 'ownership_generation'] as $column) {
                if (Schema::hasColumn('ldap_sync_runs', $column)) {
                    Schema::table('ldap_sync_runs', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('scheduled_job_executions');
        Schema::dropIfExists('scheduled_job_handoffs');
        Schema::dropIfExists('scheduled_job_nodes');
    }
};
