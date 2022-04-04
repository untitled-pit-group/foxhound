<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        DB::statement('alter table uploads ' .
            'add column pending_removal_since timestamptz default null');
    }

    public function down()
    {
        DB::statement('alter table uploads drop column pending_removal_since');
    }
};
