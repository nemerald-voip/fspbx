<?php


namespace App\Services;

class LineKeyTypesService
{
    public static function getYealinkKeyTypes()
    {
        return [
            ['value' => 0, 'name' => 'N/A'],
            ['value' => 1, 'name' => 'Conference'],
            ['value' => 2, 'name' => 'Forward'],
            ['value' => 3, 'name' => 'Transfer'],
            ['value' => 4, 'name' => 'Hold'],
            ['value' => 5, 'name' => 'DND'],
            ['value' => 7, 'name' => 'Recall'],
            ['value' => 8, 'name' => 'SMS'],
            ['value' => 9, 'name' => 'Pickup(pick up/Direct Pickup)'],
            ['value' => 10, 'name' => 'Call Park'],
            ['value' => 11, 'name' => 'DTMF'],
            ['value' => 12, 'name' => 'Voice Mail'],
            ['value' => 13, 'name' => 'SpeedDial(Speed Dial)'],
            ['value' => 14, 'name' => 'Intercom'],
            ['value' => 15, 'name' => 'Line'],
            ['value' => 16, 'name' => 'BLF'],
            ['value' => 17, 'name' => 'URL'],
            ['value' => 18, 'name' => 'Group Listening'],
            ['value' => 20, 'name' => 'Private Hold'],
            ['value' => 22, 'name' => 'XML Group'],
            ['value' => 23, 'name' => 'Group Pickup'],
            ['value' => 24, 'name' => 'Paging(Multicast Paging)'],
            ['value' => 25, 'name' => 'Record'],
            ['value' => 27, 'name' => 'XML Browser'],
            ['value' => 34, 'name' => 'Hot Desking'],
            ['value' => 35, 'name' => 'URL Record'],
            ['value' => 38, 'name' => 'LDAP (only appear when “ldap.enable=1”)'],
            ['value' => 39, 'name' => 'BLF List'],
            ['value' => 40, 'name' => 'Prefix'],
            ['value' => 41, 'name' => 'Zero Touch'],
            ['value' => 42, 'name' => 'ACD'],
            ['value' => 45, 'name' => 'Local Group'],
            ['value' => 50, 'name' => 'Phone Lock'],
            ['value' => 56, 'name' => 'Retrieve Park'],
            ['value' => 61, 'name' => 'Directory'],
            ['value' => 66, 'name' => 'Paging List'],
            ['value' => 73, 'name' => 'Custom Key'],
        ];
    }
}
