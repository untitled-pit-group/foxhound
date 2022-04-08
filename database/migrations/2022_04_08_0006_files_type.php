<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
            create type file_type as enum ('plain', 'document', 'media')
        SQL);
        DB::statement(
            'alter table files add column type file_type default null');
    }

    public function down()
    {
        DB::statement('alter table files drop column if exists type');
        DB::statement('drop type if exists file_type');
    }
};
