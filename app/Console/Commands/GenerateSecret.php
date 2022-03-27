<?php declare(strict_types=1);
namespace App\Console\Commands;
use App\Services\AuthTokenService;
use App\Support\Base64Url;
use Illuminate\Console\Command;

class GenerateSecret extends Command
{
    protected $signature = "secret:generate";
    protected $description = "Generate a new application global secret";

    private function persistSecret(string $secret): void
    {
        $dotenv = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . ".env";
        $data = file_get_contents($dotenv);
        if (preg_match("/^FOXHOUND_GLOBAL_SECRET=/m", $data) !== 1) {
            $data .= "\nFOXHOUND_GLOBAL_SECRET={$secret}\n";
        } else {
            $data = preg_replace("/^FOXHOUND_GLOBAL_SECRET=([^\\n]*)$/m",
                "FOXHOUND_GLOBAL_SECRET={$secret}", $data);
        }
        file_put_contents($dotenv, $data);
    }

    public function handle(AuthTokenService $ats)
    {
        $oldSecret = (string) env('FOXHOUND_GLOBAL_SECRET');
        if ($oldSecret !== "") {
            $this->info("The application secret is: {$oldSecret}");
            $this->warn("If you reset the application secret, all apps that " .
                "are using the old secret will stop working.");
            $res = $this->ask("Do you wish to reset the application secret? [y/N]");
            if ($res !== 'y' && $res !== 'Y') {
                return;
            }
        }
        $secret = Base64Url::encode(random_bytes(24));
        $this->persistSecret($secret);
        $this->info("The " . ($oldSecret === "" ? "" : "new ") .
            "application secret is: {$secret}");
    }
}
