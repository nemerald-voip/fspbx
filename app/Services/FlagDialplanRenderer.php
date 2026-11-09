<?php

namespace App\Services;

use App\Models\IvrMenus;
use App\Models\RingGroups;
use DOMDocument;

class FlagDialplanRenderer
{
    public function ringGroup(RingGroups $ringGroup): string
    {
        // Preserve the existing stored XML's lack of a trailing newline.
        return rtrim(view('layouts.xml.ring-group-dial-plan-template', ['ring_group' => $ringGroup])->render(), "\r\n");
    }

    public function ivr(IvrMenus $ivr): string
    {
        $xml = trim(view('layouts.xml.ivr-dial-plan-template', [
            'ivr' => $ivr,
            'dialplan_continue' => 'false',
        ])->render());

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $dom->formatOutput = true;

        return $dom->saveXML($dom->documentElement);
    }
}
