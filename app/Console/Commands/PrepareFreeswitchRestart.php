<?php

namespace App\Console\Commands;

use App\Models\FusionCache;
use App\Services\SwitchVariableService;
use Illuminate\Console\Command;
use Throwable;

class PrepareFreeswitchRestart extends Command
{
    protected $signature = 'freeswitch:prepare-restart {--preserve-vars : Keep the installed vars.xml unchanged}';

    protected $description = 'Rebuild FreeSWITCH variables and flush generated XML before a service restart';

    public function handle(SwitchVariableService $variables): int
    {
        try {
            if (! $this->option('preserve-vars') && ! $variables->syncVarsXml(false)) {
                $this->error('Unable to rebuild vars.xml. Check the switch configuration directory setting.');

                return self::FAILURE;
            }

            $this->info($this->option('preserve-vars')
                ? 'Preserved the installed vars.xml.'
                : 'Rebuilt vars.xml from the FS PBX database.');

            if (! FusionCache::flushForRestart()) {
                $this->error('Unable to flush the FreeSWITCH XML cache.');

                return self::FAILURE;
            }

            $this->info('Flushed the FreeSWITCH XML cache.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Unable to prepare the FreeSWITCH restart: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
