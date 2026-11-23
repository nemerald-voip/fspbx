<?php

// Bridge for the isolated Lua/PostgreSQL test. Never boots the application.
$lab = getenv('FSPBX_INBOUND_LAB');
if (! $lab || ! preg_match('~^/tmp/fspbx-inbound\.[A-Za-z0-9]+$~D', $lab)) {
    throw new RuntimeException('A disposable inbound lab is required.');
}
$input = isset($argv[2]) ? json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR) : [];
if ($argv[1] === 'regex') {
    $delimiter = $input[2];
    [$number, $expression] = explode($delimiter, substr($input, 3), 2);
    echo json_encode(preg_match($delimiter.$expression.$delimiter, $number) === 1 ? 'true' : 'false');
    exit;
}
$db = new PDO("pgsql:host=$lab;port=16544;dbname=postgres", 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($db->query('SHOW data_directory')->fetchColumn() !== $lab.'/db') {
    throw new RuntimeException('Refusing a non-lab database.');
}
if ($argv[1] === 'query') {
    // The production Lua database wrapper ignores parameters absent from SQL.
    preg_match_all('/:([a-z_][a-z_0-9]*)/i', $input['sql'], $names);
    $params = array_intersect_key($input['params'], array_flip($names[1]));
    $statement = $db->prepare($input['sql']);
    $statement->execute($params);
    echo json_encode($statement->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR);
    exit;
}
if ($argv[1] !== 'setup') throw new RuntimeException('Unknown probe operation.');
require dirname(__DIR__, 2).'/vendor/autoload.php';
$db->exec("CREATE TABLE v_domains (domain_uuid uuid PRIMARY KEY, domain_enabled text);
    CREATE TABLE v_dialplans (dialplan_uuid uuid PRIMARY KEY, domain_uuid uuid, dialplan_context text,
        hostname text, dialplan_enabled text, dialplan_order integer, dialplan_xml text);
    CREATE TABLE v_destinations (dialplan_uuid uuid, domain_uuid uuid, destination_number text,
        destination_prefix text, destination_area_code text, destination_trunk_prefix text,
        destination_number_regex text, destination_enabled text)");
$uuid = fn (int $index) => sprintf('00000000-0000-4000-8000-%012d', $index);
$account = $uuid(900);
$disabled = $uuid(901);
$db->exec("INSERT INTO v_domains VALUES ('$account', 'true'), ('$disabled', 'false')");
$insertPlan = $db->prepare('INSERT INTO v_dialplans VALUES (?, ?, ?, ?, ?, ?, ?)');
$insertDestination = $db->prepare('INSERT INTO v_destinations VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$fixtures = [];
$expressions = [];
$candidates = ['invalid', 'unknown', 'wrong', 'other'];
$index = 0;
foreach (require dirname(__DIR__).'/Fixtures/phone_number_matches.php' as $name => $fixture) {
    [$country, $prefix, $number, $canonical, $accepted, $rejected] = $fixture;
    $id = $uuid(++$index);
    $context = 'public@case'.$index;
    $service = new class($country) extends \App\Services\PhoneNumberService {
        public function __construct(private string $country) {}
        public function countryCodeForDomain(?string $domainUuid = null): string { return $this->country; }
    };
    $destination = new \App\Models\Destinations(['destination_number' => $number, 'destination_prefix' => $prefix, 'domain_uuid' => $account]);
    $expression = $service->dialplanMatchForDestination($destination)['expression'];
    $expressions[] = $expression;
    $candidates = array_merge($candidates, $accepted, $rejected);
    $insertPlan->execute([$id, $account, $context, null, 'true', 100, '<extension name="case'.$index.'"><condition/></extension>']);
    $insertDestination->execute([$id, $account, $number, $prefix, null, null, $expression, 'true']);
    $fixtures[] = compact('name', 'id', 'context', 'accepted', 'rejected');
}
// Independent scope traps: none may be selected by an inbound call.
foreach ([
    ['disabled-account', $disabled, $disabled, 'public', null, 'true', 'true'],
    ['foreign-host', $account, $account, 'public', 'elsewhere', 'true', 'true'],
    ['disabled-plan', $account, $account, 'public', null, 'false', 'true'],
    ['disabled-number', $account, $account, 'public', null, 'true', 'false'],
    ['foreign-account-link', $account, $disabled, 'public', null, 'true', 'true'],
    ['internal-context', $account, $account, 'account.test', null, 'true', 'true'],
] as $offset => [$name, $planAccount, $numberAccount, $context, $host, $enabled, $numberEnabled]) {
    $id = $uuid(100 + $offset);
    $number = '1999555990'.$offset;
    $expressions[] = '^'.$number.'$';
    $candidates[] = $number;
    $insertPlan->execute([$id, $planAccount, $context, $host, $enabled, 50, '<extension name="'.$name.'"><condition/></extension>']);
    $insertDestination->execute([$id, $numberAccount, $number, null, null, null, '^'.$number.'$', $numberEnabled]);
}
foreach (['public', 'public@carrier', 'carrier.public', 'republic'] as $offset => $context) {
    $insertPlan->execute([$uuid(200 + $offset), null, $context, null, 'true', 10, '<extension name="global-'.$offset.'"><condition/></extension>']);
}
file_put_contents($lab.'/fixtures.json', json_encode($fixtures, JSON_THROW_ON_ERROR));
// Precompute PCRE evaluations in one process; avoid hundreds of PHP startups.
// Expected acceptance remains the independently specified fixture list above.
$matches = [];
foreach (array_unique($candidates) as $candidate) {
    foreach (array_unique($expressions) as $expression) {
        $matches['m:~'.$candidate.'~'.$expression] = preg_match('~'.$expression.'~', $candidate) === 1 ? 'true' : 'false';
    }
}
file_put_contents($lab.'/regex.json', json_encode($matches, JSON_THROW_ON_ERROR));
echo 'Prepared '.count($fixtures)." independent number cases and scope traps.\n";
