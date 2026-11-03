"""Opt-in regression using the installed FreeSWITCH parser, without calls or ESL.

Run from the repository root: python3 tests/Unit/fax_originate_parser_test.py
Requires PHP dependencies and libfreeswitch. No application boot or database access.
"""
import ctypes
import ctypes.util
import json
import subprocess

library = ctypes.util.find_library('freeswitch')
if not library:
    raise SystemExit('libfreeswitch is required for this opt-in parser test')
split = ctypes.CDLL(library).switch_separate_string
split.argtypes = [ctypes.c_char_p, ctypes.c_char, ctypes.POINTER(ctypes.c_char_p), ctypes.c_uint]
split.restype = ctypes.c_uint


def tokens(value, delimiter):
    buffer = ctypes.create_string_buffer(value.encode())
    parts = (ctypes.c_char_p * 1024)()
    size = split(buffer, delimiter.encode(), parts, len(parts))
    return [parts[index].decode() for index in range(size)]


blocks = json.loads(subprocess.check_output(['php'], input=b'''<?php
require 'vendor/autoload.php';
$job = new App\\Jobs\\SendFaxJob('fax');
$method = new ReflectionMethod($job, 'buildOriginateVariableBlock');
$blocks = [];
foreach (['Fax, West', 'Office | Fax, West'] as $name) {
    $blocks[] = $method->invoke($job, [
        "fax_uuid=server", "outbound_fax_uuid=fax", "outbound_fax_attempt_uuid=attempt",
        "domain_uuid=account", "fax_header='$name'", "absolute_codec_string='PCMU,PCMA'",
        "api_hangup_hook='lua lua/fax_hangup.lua'", "export_vars='custom_variable,fax_uuid'",
    ]);
}
echo json_encode($blocks);
''', text=False))

for block, name in zip(blocks, ['Fax, West', 'Office | Fax, West']):
    # mod_commands splits API arguments before switch_event_create_brackets
    # splits channel assignments. The first pass removes the single quotes.
    arguments = tokens('{' + block + "}loopback/100/account.test/XML 'txfax:/tmp/fax.tif' inline account.test", ' ')
    assert len(arguments) == 4, arguments
    assignments = tokens(arguments[0][1:arguments[0].index('}')], ',')
    variables = dict(tokens(assignment, '=') for assignment in assignments)
    assert variables['absolute_codec_string'] == 'PCMU,PCMA', variables
    assert variables['fax_header'] == name, variables
    assert variables['api_hangup_hook'] == 'lua lua/fax_hangup.lua', variables
    for key in ['api_hangup_hook', 'fax_uuid', 'outbound_fax_uuid', 'outbound_fax_attempt_uuid', 'domain_uuid']:
        assert key in variables['export_vars'].split(','), variables
    assert variables['export_vars'].split(',').count('fax_uuid') == 1
    assert 'custom_variable' in variables['export_vars'].split(',')

print('PASS 2 fax originate scenarios through the FreeSWITCH parser')
