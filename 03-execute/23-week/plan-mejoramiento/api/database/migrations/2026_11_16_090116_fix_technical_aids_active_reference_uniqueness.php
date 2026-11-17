<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE technical_aids
                 DROP INDEX technical_aids_reference_unique'
            );

            DB::statement(
                'ALTER TABLE technical_aids
                 ADD COLUMN active_reference VARCHAR(255)
                 GENERATED ALWAYS AS (
                     CASE
                         WHEN is_active = 1 THEN reference
                         ELSE NULL
                     END
                 ) STORED'
            );

            DB::statement(
                'CREATE UNIQUE INDEX technical_aids_active_reference_unique
                 ON technical_aids (active_reference)'
            );

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(
                'DROP INDEX technical_aids_reference_unique'
            );

            DB::statement(
                'CREATE UNIQUE INDEX technical_aids_active_reference_unique
                 ON technical_aids (reference)
                 WHERE is_active = 1'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'DROP INDEX technical_aids_active_reference_unique
                 ON technical_aids'
            );

            DB::statement(
                'ALTER TABLE technical_aids
                 DROP COLUMN active_reference'
            );

            DB::statement(
                'CREATE UNIQUE INDEX technical_aids_reference_unique
                 ON technical_aids (reference)'
            );

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(
                'DROP INDEX technical_aids_active_reference_unique'
            );

            DB::statement(
                'CREATE UNIQUE INDEX technical_aids_reference_unique
                 ON technical_aids (reference)'
            );
        }
    }
};