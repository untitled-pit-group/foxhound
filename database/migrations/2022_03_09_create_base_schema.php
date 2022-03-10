<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // NOTE[pn]: cba to translate the schema into the Blueprint language.
        // Given that we're targeting only Postgres, there's little value in a
        // cross-database schema building abstraction, especially when it has
        // its own weird syntax that doesn't really map onto Postgres's
        // primitives that well.
        DB::statement(<<<'SQL'
            create table uploads (
                id bigint primary key,
                hash bytea not null,
                length bigint not null,
                gcs_path text not null,
                name text not null,
                upload_start timestamptz not null,
                progress float4 default 0 check (progress between 0 and 1),
                last_progress_report timestamptz default null
            )
        SQL);
        DB::statement(<<<'SQL'
            comment on column uploads.hash is
                'hash of file content, stored in multihash format'
        SQL);
        DB::statement(<<<'SQL'
            create table files (
                id bigint primary key,
                name text not null,
                length bigint not null,
                gcs_path text not null,
                tags text[] not null,
                upload_timestamp timestamptz not null,
                relevance_timestamp timestamptz default null
            )
        SQL);
        DB::statement(<<<'SQL'
            create type indexing_state as enum
                ('queued', 'transform', 'transcribe', 'ingest', 'finished', 'error')
        SQL);
        DB::statement(<<<'SQL'
            create table files_indexing_state (
                id bigint primary key references files (id),
                state indexing_state not null,
                error_context jsonb default null,
                last_activity timestamptz not null,
                check (case
                        when state = 'error' then error_context is not null
                        else error_context is null
                       end)
            )
        SQL);
    }

    public function down()
    {
        DB::statement('drop table if exists files_indexing_state');
        DB::statement('drop type if exists indexing_state');
        DB::statement('drop table if exists files');
        DB::statement('drop table if exists uploads');
    }
};
