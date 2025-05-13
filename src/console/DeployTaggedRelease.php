<?php

namespace Adexyme\DeployTaggedRelease\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DeployTaggedRelease extends Command
{
    protected $signature = 'deploy:tagged-release
                            {tag       : Git tag to deploy}
                            {--schema= : Semicolon-delimited table:col:type lists}';

    protected $description = 'Deploy a git tag, install dependencies, optionally create tables/columns using tagged-release.';

    public function handle(): int
    {
        // Load settings from config
        $config = Config::get('deploy-tagged-release');
        $repo   = $config['repo'] ?? null;
        $path   = $config['path'] ?? base_path();
        $db     = $config['db'] ?? null;
        $token  = $config['token'] ?? null;

        if (! $repo) {
            $this->error('Config must define "repo".');
            return 1;
        }

        // Inject token for private repos
        if ($token && preg_match('/^https:\/\//', $repo)) {
            $repo = preg_replace('#^https://#', "https://{$token}@", $repo);
        }

        // CLI inputs
        $tag    = $this->argument('tag');
        $schema = $this->option('schema');

        // 1. Repo operations
        if (! is_dir("{$path}/.git")) {
            $this->info("Cloning {$repo} into {$path}...");
            exec(sprintf('git clone %s %s', escapeshellarg($repo), escapeshellarg($path)), $out, $status);
        } else {
            $this->info("Fetching updates in {$path}...");
            if ($token) {
                exec(sprintf('cd %s && git remote set-url origin %s', escapeshellarg($path), escapeshellarg($repo)), $out, $status);
            }
            exec(sprintf('cd %s && git fetch --all', escapeshellarg($path)), $out, $status);
        }
        if ($status !== 0) {
            $this->error('Git operation failed.');
            return 1;
        }

        // 2. Checkout tag
        $this->info("Checking out tag {$tag}...");
        exec(sprintf('cd %s && git checkout %s', escapeshellarg($path), escapeshellarg($tag)), $out, $status);
        if ($status !== 0) {
            $this->error("Failed to checkout tag {$tag}.");
            return 1;
        }

        // 3. Composer install
        $this->info('Running composer install...');
        exec(sprintf('cd %s && composer install --no-interaction --prefer-dist --optimize-autoloader', escapeshellarg($path)), $out, $status);
        if ($status !== 0) {
            $this->error('Composer install failed.');
            return 1;
        }

        // 4. Database connection
        if ($db) {
            $this->info("Switching database connection to {$db}...");
            Config::set('database.connections.mysql.database', $db);
            DB::purge('mysql');
            DB::reconnect('mysql');
        } else {
            $db = Config::get('database.connections.mysql.database');
            $this->info("Using existing database connection ({$db}).");
        }

        // 5. Schema creation without altering existing
        if ($schema) {
            $this->info('Processing schema definitions...');
            foreach (explode(';', $schema) as $part) {
                $part = trim($part);
                if (! $part) continue;

                [$table, $cols] = explode(':', $part, 2);

                if (! Schema::hasTable($table)) {
                    $this->info("Creating table {$table} in DB {$db}...");
                    Schema::create($table, function (Blueprint $tableB) use ($cols) {
                        foreach (explode(',', $cols) as $colDef) {
                            [$name, $type] = explode(':', $colDef, 2);
                            $this->addColumn($tableB, $name, $type);
                        }
                        $tableB->timestamps();
                    });
                    $this->info("Table {$table} created.");
                } else {
                    $this->info("Table {$table} already exists. Checking columns...");
                    Schema::table($table, function (Blueprint $tableB) use ($table, $cols) {
                        foreach (explode(',', $cols) as $colDef) {
                            [$name, $type] = explode(':', $colDef, 2);
                            if (! Schema::hasColumn($table, $name)) {
                                $this->info("Adding column {$name} to {$table}...");
                                $this->addColumn($tableB, $name, $type);
                            } else {
                                $this->info("Column {$name} already exists on {$table}; skipping.");
                            }
                        }
                    });
                }
            }
        }

        $this->info('Deployment completed successfully.');
        return 0;
    }

    /**
     * Add a column to a Blueprint based on type
     */
    protected function addColumn(Blueprint $table, string $name, string $type)
    {
        switch (strtolower($type)) {
            case 'string':   $table->string($name);   break;
            case 'integer':  $table->integer($name);  break;
            case 'text':     $table->text($name);     break;
            case 'boolean':  $table->boolean($name);  break;
            case 'timestamp':$table->timestamp($name);break;
            default:         $table->text($name);
        }
    }
}

// In config/deploy.php, ensure signature:
// 'providers' => [
//     YourVendor\DeployRelease\DeployTaggedReleaseServiceProvider::class
// ],
// and rename namespace accordingly.
