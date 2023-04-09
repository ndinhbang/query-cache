<?php

namespace Ndinhbang\QueryCache\Console\Commands\QueryCache;

use function array_map;
use function explode;
use Illuminate\Console\Command;
use Ndinhbang\QueryCache\QueryCache;

class Forget extends Command
{
    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'query-cache:forget
                            {keys : The key names of the queries to forget from the cache, comma separated}
                            {--store= : The cache store to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes a cached query from the cache store.';

    /**
     * Execute the console command.
     *
     * @param \Ndinhbang\QueryCache\QueryCache $QueryCache
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle(QueryCache $QueryCache): void
    {
        $store = $this->option('store') ?: config('query-cache.store') ?? cache()->getDefaultDriver();

        $keys = array_map('trim', explode(',', $this->argument('keys')) ?: []);

        $count = count($keys);

        if ($QueryCache->store($store)->forget(...$keys)) {
            if ($count > 1) {
                $this->info("Successfully removed [$count] keys from the [$store] cache store.");
            } else {
                $this->info("Successfully removed [$keys[0]] key from the [$store] cache store.");
            }
        } else {
            if ($count > 1) {
                $this->warn("Removed [$count] keys, but some were not found in [$store] cache store.");
            } else {
                $this->warn("The [$keys[0]] key was not found in [$store] cache store.");
            }
        }
    }
}
