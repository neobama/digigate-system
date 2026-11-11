<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateAgentApiTokenCommand extends Command
{
    protected $signature = 'agent-api:token {--show : Show current configured token length only}';

    protected $description = 'Generate a random AGENT_API_TOKEN for Claude Code / MCP access';

    public function handle(): int
    {
        if ($this->option('show')) {
            $current = (string) config('agent_api.token');
            if ($current === '') {
                $this->warn('AGENT_API_TOKEN is empty.');

                return self::FAILURE;
            }

            $this->info('AGENT_API_TOKEN is set (length '.strlen($current).').');

            return self::SUCCESS;
        }

        $token = Str::random(64);
        $this->line('Generated token (copy into .env as AGENT_API_TOKEN):');
        $this->newLine();
        $this->line($token);
        $this->newLine();
        $this->comment('Then set AGENT_API_ENABLED=true and clear config cache.');

        return self::SUCCESS;
    }
}
