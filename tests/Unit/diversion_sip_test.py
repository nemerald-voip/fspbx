"""Opt-in SIP integration test. Starts its own FreeSWITCH in /tmp.

Run: python3 tests/Unit/diversion_sip_test.py
Uses ephemeral loopback SIP ports, a local rejecting carrier, fixture ring-group
records, and the real ring-group/diversion Lua. Never connects to the app DB or
an external carrier. Logs/captured INVITEs are retained in the printed lab path.
Native XML fixtures also cover direct forwarding, extension Call Forward All,
and an external timeout destination without a ring-group eligibility marker.
"""
import argparse
import json
import os
from pathlib import Path
import re
import socket
import struct
import subprocess
import tempfile
import threading
import time
import uuid

TARGETS = ('7000', '7001', '7002', '7003', '7004', '7005', '7006', '7007', '7008', '7009', '7100', '7200', '7201', '7202', '7203', '7204', '9000')
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--targets', nargs='+', choices=TARGETS, default=TARGETS,
                    help='Run only selected local dialplan scenarios.')
args = parser.parse_args()

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / 'resources/freeswitch_scripts'
LAB = Path(tempfile.mkdtemp(prefix='fspbx-diversion-sip-'))
ACCOUNT = '11111111-1111-4111-8111-111111111111'
RECEIVED = '"Smith, O\'Brien" <sip:+442079460958@previous.test>;reason=user-busy'
OLDER = '<sip:+61293744000@older.test>;reason=no-answer;counter=1'
SAVED = '<sip:+33142345678@saved.test>;reason=unconditional'
LOCAL = '<sip:+12025550100@lab.test>;reason=unconditional;counter=1'


def udp():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(('127.0.0.1', 0))
    sock.settimeout(0.2)
    return sock


def headers(message, name):
    return re.findall(r'^' + re.escape(name) + r':\s*(.*)\r?$', message, re.I | re.M)


def reply(message, code='486 Busy Here'):
    fields = [f'SIP/2.0 {code}']
    for name in ('Via', 'From', 'To', 'Call-ID', 'CSeq'):
        for value in headers(message, name):
            value = value.strip()
            if name == 'To' and ';tag=' not in value:
                value += ';tag=local-carrier'
            fields.append(f'{name}: {value}')
    return ('\r\n'.join(fields) + '\r\nContent-Length: 0\r\n\r\n').encode()


def send_media(sock, address, stopped):
    # Supply the PCMU media advertised by our caller. Without RTP, ringback
    # processing can stall after several failed bridges on the same channel.
    sequence, timestamp = 0, 0
    while not stopped.is_set():
        packet = struct.pack('!BBHII', 0x80, 0, sequence, timestamp, 1) + b'\xff' * 160
        sock.sendto(packet, address)
        sequence, timestamp = (sequence + 1) % 65536, (timestamp + 160) % (2 ** 32)
        stopped.wait(0.02)


carrier, probe = udp(), udp()
sip_port = probe.getsockname()[1]
probe.close()
carrier_port = carrier.getsockname()[1]
captures = []
done = threading.Event()


def receive():
    while not done.is_set():
        try:
            data, address = carrier.recvfrom(65535)
        except socket.timeout:
            continue
        message = data.decode(errors='replace')
        if message.startswith('INVITE '):
            captures.append(message)
            (LAB / f'invite-{len(captures):02}.sip').write_text(message)
            carrier.sendto(reply(message), address)
        elif message.startswith('OPTIONS '):
            carrier.sendto(reply(message, '200 OK'), address)


driver = LAB / 'ring_fixture.lua'
driver.write_text('''
package.path = ''' + json.dumps(str(SCRIPTS / '?.lua') + ';') + ''' .. package.path
debug.sql = false
database = { type = 'pgsql' }
scripts_dir = ''' + json.dumps(str(SCRIPTS)) + '''
local extension = session:getVariable('destination_number')
local strategies = { ['7000']='simultaneous', ['7001']='enterprise', ['7002']='sequence', ['7003']='random', ['7004']='rollover' }
local config = {
 domain_uuid=''' + json.dumps(ACCOUNT) + ''', domain_name='lab.test', ring_group_name='Lab', ring_group_extension=extension,
 ring_group_call_timeout='4', ring_group_strategy=strategies[extension] or 'simultaneous',
 ring_group_forward_enabled=extension == '7005' and 'true' or 'false', ring_group_forward_destination='8001',
 ring_group_timeout_app='transfer', ring_group_timeout_data=extension == '7009' and '8001 XML lab.test' or '9998 XML lab.test',
}
session:setVariable('ring_group_uuid', 'lab')
session:setVariable('recordings_dir', ''' + json.dumps(str(LAB)) + ''')
package.loaded['resources.functions.database'] = { new=function() return {
 release=function() end,
 query=function(_, sql, params, callback)
  if sql:find('v_ring_group_users',1,true) then return end
  if sql:find('SELECT d.domain_name, r.*',1,true) then callback(config)
  elseif sql:find('v_ring_group_destinations',1,true) then
   local row = {}; for k,v in pairs(config) do row[k]=v end
   row.destination_number=extension == '7006' and '8002' or extension == '7007' and '7000' or extension == '7008' and '8003' or extension == '7009' and '9998' or '8001'
   row.destination_timeout='2'; row.destination_delay='0'; row.destination_prompt='0'
   callback(row)
  elseif sql:find('ring_group_strategy',1,true) then callback(config)
  else error('Unexpected fixture query: '..sql) end
 end
} end }
package.loaded['resources.functions.channel_utils'] = true
package.loaded['resources.functions.route_to_bridge'] = { preload_dialplan=function() return {} end }
package.loaded['resources.functions.play_file'] = function() end
package.loaded['resources.functions.send_presence'] = true
dofile(scripts_dir .. '/app/ring_groups/index.lua')
''')

saved_bridge = LAB / 'saved_bridge_fixture.lua'
saved_bridge.write_text('''
package.path = ''' + json.dumps(str(SCRIPTS / '?.lua') + ';') + ''' .. package.path
package.loaded['resources.functions.database'] = { new=function() return {
 connected=function() return true end, release=function() end,
 query=function(_, sql, params, callback)
  callback({ bridge_destination='sofia/gateway/diversion-primary/18005550100', header_name='Diversion', header_value=''' + json.dumps(SAVED) + ''' })
  callback({ bridge_destination='sofia/gateway/diversion-primary/18005550100', header_name='X-Lab-Test', header_value='saved-bridge' })
 end
} end }
argv = { '33333333-3333-4333-8333-333333333333' }
dofile(''' + json.dumps(str(SCRIPTS / 'bridge.lua')) + ''')
''')

for folder in ('conf', 'log', 'run', 'db', 'temp', 'certs', 'cache', 'storage'):
    (LAB / folder).mkdir()
modules = ''.join(f'<load module="{name}"/>' for name in (
    'mod_console', 'mod_logfile', 'mod_commands', 'mod_dptools', 'mod_dialplan_xml',
    'mod_sofia', 'mod_loopback', 'mod_lua', 'mod_tone_stream'))
gateway = ''.join(f'''<gateway name="{name}"><param name="proxy" value="127.0.0.1:{carrier_port}"/>
<param name="register" value="false"/><param name="username" value="lab"/><param name="password" value="lab"/>
<param name="caller-id-in-from" value="true"/></gateway>''' for name in ('diversion-primary', 'diversion-fallback'))
(LAB / 'conf/freeswitch.xml').write_text(f'''<document type="freeswitch/xml">
<section name="configuration">
 <configuration name="console.conf" description="Lab"><mappings><map name="all" value="debug,info,notice,warning,err,crit,alert"/></mappings></configuration>
 <configuration name="switch.conf" description="Lab"><settings>
  <param name="max-sessions" value="20"/><param name="sessions-per-second" value="100"/>
  <param name="rtp-start-port" value="40000"/><param name="rtp-end-port" value="40100"/>
 </settings></configuration>
 <configuration name="modules.conf" description="Lab"><modules>{modules}</modules></configuration>
 <configuration name="lua.conf" description="Lab"><settings><param name="script-directory" value="{SCRIPTS}/?.lua"/></settings></configuration>
 <configuration name="sofia.conf" description="Lab"><global_settings/><profiles><profile name="diversion-lab">
  <gateways>{gateway}</gateways><settings>
   <param name="sip-ip" value="127.0.0.1"/><param name="rtp-ip" value="127.0.0.1"/>
   <param name="sip-port" value="{sip_port}"/><param name="context" value="public"/>
   <param name="dialplan" value="XML"/><param name="auth-calls" value="false"/>
   <param name="inbound-codec-prefs" value="PCMU"/><param name="outbound-codec-prefs" value="PCMU"/>
   <param name="inbound-late-negotiation" value="false"/><param name="disable-transcoding" value="true"/>
   <param name="manage-presence" value="false"/><param name="disable-register" value="true"/>
   <param name="parse-all-invite-headers" value="true"/>
  </settings></profile></profiles></configuration>
</section>
<section name="dialplan"><context name="public">
 <extension name="business-number"><condition field="destination_number" expression="^(700[0-9]|720[0-4])$">
  <action application="set" data="domain_uuid={ACCOUNT}" inline="true"/>
  <action application="set" data="domain_name=lab.test" inline="true"/>
  <action application="export" data="call_direction=inbound" inline="true"/>
  <action application="export" data="original_did=+12025550100"/>
  <action application="transfer" data="$1 XML lab.test"/>
 </condition></extension>
 <extension name="other-business-number"><condition field="destination_number" expression="^7100$">
  <action application="set" data="domain_uuid={ACCOUNT}" inline="true"/>
  <action application="set" data="domain_name=lab.test" inline="true"/>
  <action application="export" data="call_direction=inbound" inline="true"/>
  <action application="export" data="original_did=+442079460958"/>
  <action application="transfer" data="7000 XML lab.test"/>
 </condition></extension>
 <extension name="outbound-test"><condition field="destination_number" expression="^9000$">
  <action application="transfer" data="9000 XML lab.test"/>
 </condition></extension>
</context><context name="lab.test">
 <extension name="end-test"><condition field="destination_number" expression="^9998$">
  <action application="hangup" data="USER_BUSY"/>
 </condition></extension>
 <extension name="saved-bridge"><condition field="destination_number" expression="^8002$">
  <action application="set" data="hangup_after_bridge=false"/>
  <action application="set" data="continue_on_fail=true"/>
  <action application="lua" data="diversion.lua"/>
  <action application="lua" data="{saved_bridge}"/>
  <action application="lua" data="diversion.lua"/>
  <action application="bridge" data="sofia/gateway/diversion-fallback/18005550100"/>
  <action application="hangup" data="USER_BUSY"/>
 </condition></extension>
 <extension name="ring-group"><condition field="destination_number" expression="^700[0-9]$">
  <action application="set" data="sip_copy_custom_headers=false"/>
  <action application="lua" data="{driver}"/>
  <action application="hangup" data="NORMAL_CLEARING"/>
 </condition></extension>
 <extension name="direct-forward"><condition field="destination_number" expression="^7200$">
  <action application="transfer" data="8001 XML lab.test"/>
 </condition></extension>
 <extension name="extension-forward-fixture"><condition field="destination_number" expression="^7201$">
  <action application="set" data="forward_all_enabled=true"/>
  <action application="set" data="forward_all_destination=8001"/>
  <action application="transfer" data="1000 XML lab.test"/>
 </condition></extension>
 <!-- Enabled inbound actions from the standard call-forward-all dialplan. -->
 <extension name="extension-forward">
  <condition field="destination_number" expression="^1000$"/>
  <condition field="${{forward_all_enabled}}" expression="true"/>
  <condition field="${{call_direction}}" expression="^inbound$">
   <action application="set" data="outbound_caller_id_name=${{caller_id_name}}" inline="true"/>
   <action application="set" data="outbound_caller_id_number=${{caller_id_number}}" inline="true"/>
   <action application="transfer" data="${{forward_all_destination}} XML ${{domain_name}}"/>
  </condition>
 </extension>
 <extension name="internal-only"><condition field="destination_number" expression="^7202$">
  <action application="lua" data="diversion.lua clear"/>
  <action application="bridge" data="sofia/diversion-lab/18005550101@127.0.0.1:{carrier_port}"/>
 </condition></extension>
 <!-- Disabled editor rows are absent from stored executable XML. -->
 <extension name="existing-gateway-opted-out"><condition field="destination_number" expression="^7203$">
  <action application="set" data="sip_h_Diversion={SAVED.replace('<', '&lt;').replace('>', '&gt;')}"/>
  <action application="bridge" data="sofia/gateway/diversion-primary/18005550100"/>
 </condition></extension>
 <extension name="existing-saved-bridge-opted-out"><condition field="destination_number" expression="^7204$">
  <action application="lua" data="{saved_bridge}"/>
 </condition></extension>
 <extension name="carrier"><condition field="destination_number" expression="^8001$">
  <action application="set" data="hangup_after_bridge=true"/>
  <action application="set" data="continue_on_fail=true"/>
  <action application="lua" data="diversion.lua"/>
  <action application="bridge" data="sofia/gateway/diversion-primary/18005550100"/>
  <action application="bridge" data="sofia/gateway/diversion-fallback/18005550100"/>
  <action application="hangup" data="USER_BUSY"/>
 </condition></extension>
 <extension name="mixed-destinations"><condition field="destination_number" expression="^8003$">
  <action application="set" data="hangup_after_bridge=false"/>
  <action application="set" data="continue_on_fail=true"/>
  <action application="lua" data="diversion.lua"/>
  <action application="bridge" data="sofia/gateway/diversion-primary/18005550100"/>
  <action application="lua" data="diversion.lua clear"/>
  <action application="bridge" data="sofia/diversion-lab/18005550101@127.0.0.1:{carrier_port}"/>
  <action application="hangup" data="USER_BUSY"/>
 </condition></extension>
 <extension name="ordinary-outbound"><condition field="destination_number" expression="^9000$">
  <action application="set" data="domain_uuid={ACCOUNT}"/><action application="set" data="domain_name=lab.test"/>
  <action application="lua" data="diversion.lua"/>
  <action application="bridge" data="sofia/gateway/diversion-primary/18005550100"/>
 </condition></extension>
</context></section>
<section name="directory"><domain name="lab.test"><groups/></domain></section>
</document>''')

log = open(LAB / 'console.log', 'w')
process = None
worker = threading.Thread(target=receive, daemon=True)
worker.start()
print('SIP lab:', LAB, flush=True)
try:
    process = subprocess.Popen([
        'freeswitch', '-nf', '-nonat', '-nocal', '-np', '-conf', str(LAB / 'conf'),
        '-log', str(LAB / 'log'), '-run', str(LAB / 'run'), '-db', str(LAB / 'db'),
        '-temp', str(LAB / 'temp'), '-certs', str(LAB / 'certs'), '-cache', str(LAB / 'cache'),
        '-storage', str(LAB / 'storage'), '-scripts', str(SCRIPTS),
    ], stdout=log, stderr=subprocess.STDOUT, stdin=subprocess.DEVNULL)
    readiness = udp()
    readiness_port = readiness.getsockname()[1]
    options = (f'OPTIONS sip:lab@127.0.0.1:{sip_port} SIP/2.0\r\n'
        f'Via: SIP/2.0/UDP 127.0.0.1:{readiness_port};branch=z9hG4bKready\r\n'
        'From: <sip:probe@lab.test>;tag=probe\r\nTo: <sip:lab@lab.test>\r\n'
        'Call-ID: readiness\r\nCSeq: 1 OPTIONS\r\nMax-Forwards: 20\r\nContent-Length: 0\r\n\r\n')
    deadline = time.monotonic() + 20
    while time.monotonic() < deadline:
        if process.poll() is not None:
            raise RuntimeError('Lab FreeSWITCH exited; inspect console.log')
        readiness.sendto(options.encode(), ('127.0.0.1', sip_port))
        try:
            packet, _ = readiness.recvfrom(65535)
            if packet.startswith(b'SIP/2.0 200') and 'Started Profile diversion-lab' in (LAB / 'console.log').read_text(errors='replace'):
                break
        except socket.timeout:
            pass
    else:
        raise RuntimeError('Lab profile did not start; inspect console.log')
    readiness.close()
    # Sofia accepts OPTIONS while the remaining modules/core are still loading.
    time.sleep(2)

    for target in args.targets:
        client = udp()
        media = udp()
        media_stopped = threading.Event()
        media_worker = None
        port = client.getsockname()[1]
        call_id = str(uuid.uuid4())
        sdp = f'v=0\r\no=lab 1 1 IN IP4 127.0.0.1\r\ns=lab\r\nc=IN IP4 127.0.0.1\r\nt=0 0\r\nm=audio {media.getsockname()[1]} RTP/AVP 0\r\na=rtpmap:0 PCMU/8000\r\n'
        # SIP To deliberately differs from the route's captured business DID.
        request = (f'INVITE sip:{target}@127.0.0.1:{sip_port} SIP/2.0\r\n'
            f'Via: SIP/2.0/UDP 127.0.0.1:{port};branch=z9hG4bK{call_id};rport\r\n'
            f'From: "Caller" <sip:12025550123@lab.test>;tag=caller\r\n'
            f'To: <sip:9999@lab.test>\r\nCall-ID: {call_id}\r\nCSeq: 1 INVITE\r\n'
            f'Contact: <sip:caller@127.0.0.1:{port}>\r\nMax-Forwards: 20\r\n'
            + (f'Diversion: {RECEIVED}\r\nDiversion: {OLDER}\r\n' if target != '9000' else '')
            + f'Content-Type: application/sdp\r\nContent-Length: {len(sdp)}\r\n\r\n{sdp}')
        start = len(captures)
        client.sendto(request.encode(), ('127.0.0.1', sip_port))
        # A failed member followed by primary/fallback can each spend five
        # seconds in FreeSWITCH's originate cleanup before the final response.
        deadline = time.monotonic() + 25
        final = None
        while time.monotonic() < deadline:
            try:
                packet, address = client.recvfrom(65535)
            except socket.timeout:
                continue
            response = packet.decode(errors='replace')
            media_port = re.search(r'^m=audio (\d+) RTP/', response, re.M)
            if media_port and media_worker is None:
                media_worker = threading.Thread(target=send_media,
                    args=(media, ('127.0.0.1', int(media_port[1])), media_stopped), daemon=True)
                media_worker.start()
            if response.startswith('SIP/2.0 ') and int(response.split()[1]) >= 300:
                final = response
                ack = request.split('\r\n\r\n')[0].replace('INVITE ', 'ACK ', 1).replace('CSeq: 1 INVITE', 'CSeq: 1 ACK')
                ack = re.sub(r'Content-Length: \d+', 'Content-Length: 0', ack)
                client.sendto((ack + '\r\n\r\n').encode(), address)
                break
        client.close()
        media_stopped.set()
        if media_worker:
            media_worker.join(timeout=1)
        media.close()
        invites = captures[start:]
        assert final, f'{target}: no final call response'
        assert invites, f'{target}: no outgoing INVITE ({final.splitlines()[0]}); inspect console.log'
        for invite in invites:
            values = [v.strip() for v in headers(invite, 'Diversion')]
            if target == '7202' or (target == '7008' and 'INVITE sip:18005550101@' in invite):
                assert LOCAL not in ', '.join(values), 'Generated Diversion leaked onto a non-gateway leg'
            elif target == '9000':
                assert values == [], values
            elif target in ('7203', '7204'):
                combined = ', '.join(values)
                assert LOCAL not in combined, 'An existing route generated Diversion without opting in'
                assert SAVED in combined, 'The existing custom Diversion header changed'
            else:
                combined = ', '.join(values)
                expected = LOCAL if target != '7100' else '<sip:+442079460958@lab.test>;reason=unconditional;counter=1'
                assert combined.count(expected) == 1, combined
                if target == '7100':
                    assert LOCAL not in combined, 'The shared ring group did not retain this call\'s business DID'
                assert RECEIVED in combined, combined
                assert OLDER in combined, combined
                assert '12025550123' in headers(invite, 'From')[0], 'Caller ID changed'
                if target == '7006' and 'gw+diversion-primary@' in invite:
                    assert SAVED in combined, combined
                    assert headers(invite, 'X-Lab-Test')[0].strip() == 'saved-bridge'
                else:
                    assert SAVED not in combined, 'Saved Bridge header leaked into another gateway'
        if target not in ('7202', '7203', '7204', '9000'):
            assert len(invites) >= 2, f'{target}: fallback gateway was not reached'
        print(f'{target}: {len(invites)} outgoing INVITE(s) verified', flush=True)
    print('Isolated SIP validation passed', flush=True)
finally:
    if process:
        process.terminate()
        try:
            process.wait(timeout=10)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()
    done.set()
    worker.join(timeout=1)
    carrier.close()
    log.close()
