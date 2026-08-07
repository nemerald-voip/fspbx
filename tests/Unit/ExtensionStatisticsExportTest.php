<?php

namespace Tests\Unit;

use App\Exports\ExtensionStatisticsExport;
use PHPUnit\Framework\TestCase;

class ExtensionStatisticsExportTest extends TestCase
{
    public function test_export_includes_every_statistics_row(): void
    {
        $rows = collect(range(1, 75))->map(fn(int $extension) => [
            'extension_label' => (string) $extension,
            'call_count' => $extension,
            'inbound' => 1,
            'outbound' => 2,
            'missed' => 0,
            'total_talk_time_formatted' => '00:01:00',
            'average_duration_formatted' => '00:00:30',
        ]);

        $exported = (new ExtensionStatisticsExport($rows))->collection();

        $this->assertCount(75, $exported);
        $this->assertSame('1', $exported->first()['extension']);
        $this->assertSame('75', $exported->last()['extension']);
    }
}
