<?php

namespace App\Console\Commands;

use App\Models\FusionCache;
use App\Services\SwitchVariableService;
use Illuminate\Console\Command;
use Throwable;

class PrepareFreeswitchRestart extends Command
{
    protected $signature = 'freeswitch:prepare-restart';

    protected $description = 'Rebuild FreeSWITCH variables and flush generated XML before a service restart';

    public function handle(SwitchVariableService $variables): int
    {
        try {
            if (! $variables->syncVarsXml(false)) {
                $this->error('Unable to rebuild vars.xml. Check the switch configuration directory setting.');

                return self::FAILURE;
            }

            $this->info('Rebuilt vars.xml from the FS PBX database.');

            if (! FusionCache::flushAll()) {
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
