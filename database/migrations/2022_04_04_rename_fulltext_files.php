<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        DB::statement('drop table if exists uploads_fulltext');
        DB::statement(<<<'SQL'
            create table files_fulltext (
                id bigint primary key references files (id),
                content text not null,
                locators bytea not null
            )
        SQL);
        DB::statement(<<<'SQL'
            create index files_fulltext_idx on files_fulltext
                using gin (to_tsvector('english', content))
        SQL);
    }

    public function down()
    {
        DB::statement('drop table if exists files_fulltext');
        DB::statement('create table uploads_fulltext (dummy text)');
    }
};
