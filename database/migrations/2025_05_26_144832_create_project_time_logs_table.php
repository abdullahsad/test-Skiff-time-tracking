<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_time_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id')->references('id')->on('projects');
            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('clients');
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->text('description')->nullable();
            $table->decimal('hours', 5, 2)->nullable();
            $table->enum('tag', ['billable', 'non-billable'])->nullable();
            $table->timestamps();


            $table->index(['user_id', 'start_time', 'end_time'], 'idx_user_start_end');
            $table->index(['project_id', 'client_id'], 'idx_project_client');
            $table->index(['start_time'], 'idx_start_time');
        });

        // MySQL trigger for calculate hours when have both value start_time and end_time
        DB::unprepared('
            CREATE TRIGGER before_time_logs_insert
            BEFORE INSERT ON project_time_logs
            FOR EACH ROW
            BEGIN
                IF NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL THEN
                    SET NEW.hours = ROUND(TIMESTAMPDIFF(MINUTE, NEW.start_time, NEW.end_time) / 60, 2);
                END IF;
            END
        ');

        // MySQL trigger for calculate hours when update row and have both value start_time and end_time
        DB::unprepared('
            CREATE TRIGGER before_time_logs_update
            BEFORE UPDATE ON project_time_logs
            FOR EACH ROW
            BEGIN
                IF NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL THEN
                    SET NEW.hours = ROUND(TIMESTAMPDIFF(MINUTE, NEW.start_time, NEW.end_time) / 60, 2);
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_time_logs');
    }
};
