<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
            create table uploads_fulltext (
                id bigint primary key references uploads (id),
                content text not null,
                locators bytea not null
            )
        SQL);
        DB::statement(<<<'SQL'
            comment on column uploads_fulltext.locators is
                'internal format to be finalized later'
        SQL);
        DB::statement(<<<'SQL'
            create index uploads_fulltext_idx on uploads_fulltext
                using gin (to_tsvector(content))
        SQL);
        DB::statement(<<<'SQL'
            comment on index uploads_fulltext_idx is
                'currently uses server-default (i.e., probably English) ' ||
                'normalization rules, so results might be off for ' ||
                'non-English texts'
        SQL);
    }

    public function down()
    {
        DB::statement('drop index if exists uploads_fulltext_idx');
        DB::statement('drop table if exists uploads_fulltext');
    }
};
