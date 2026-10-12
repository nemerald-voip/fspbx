<?php

namespace App\Console\Commands;

use App\Services\Pcap\PcapService;
use Illuminate\Console\Command;

class MaintainPcaps extends Command
{
    protected $signature = 'pcap:maintain';
    protected $description = 'Cut finished calls into PCAP files, delete expired PCAPs and clean the capture spool';

    public function handle(PcapService $pcap): int
    {
        $result = $pcap->maintain();

        if ($this->getOutput()->isVerbose()) {
            foreach ($result as $key => $value) {
                $this->line($key . ': ' . var_export($value, true));
            }
        }

        return self::SUCCESS;
    }
}
