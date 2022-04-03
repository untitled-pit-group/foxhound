<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        DB::statement('comment on column files.hash is null');
        DB::statement('comment on column uploads.hash is null');
    }

    public function down()
    {
        DB::statement(<<<'SQL'
            comment on column files.hash is
                'hash of file content, stored in multihash format'
        SQL);
        DB::statement(<<<'SQL'
            comment on column uploads.hash is
                'hash of file content, stored in multihash format'
        SQL);
    }
};
