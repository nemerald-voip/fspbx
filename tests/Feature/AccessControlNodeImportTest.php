<?php

namespace Tests\Feature;

use App\Http\Controllers\AccessControlController;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccessControlNodeImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The endpoint only parses; point the default connection at an empty
        // in-memory database so a test can never reach a real one.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');

        $this->grant(['access_control_node_add']);
    }

    private function grant(array $permissions): void
    {
        Session::put('permissions', array_map(fn ($name) => (object) ['permission_name' => $name], $permissions));
    }

    private function import(string $csv, string $name = 'nodes.csv')
    {
        $request = Request::create('/access-controls/nodes/import', 'POST', [], [], [
            'file' => UploadedFile::fake()->createWithContent($name, $csv),
        ]);

        return app(AccessControlController::class)->importNodes($request, app(AccessControlService::class));
    }

    private function csv(array $lines): string
    {
        return implode("\n", array_merge(['node_type,node_cidr,node_description'], $lines)) . "\n";
    }

    public function test_returns_normalized_rows_for_the_form(): void
    {
        $response = $this->import($this->csv([
            'allow,203.0.113.10,Office',
            'deny,198.51.100.0/24,',
            ',2001:db8::/32,IPv6',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame([
            ['node_type' => 'allow', 'node_cidr' => '203.0.113.10/32', 'node_description' => 'Office'],
            ['node_type' => 'deny', 'node_cidr' => '198.51.100.0/24', 'node_description' => null],
            ['node_type' => 'allow', 'node_cidr' => '2001:db8::/32', 'node_description' => 'IPv6'],
        ], $data['nodes']);
        $this->assertStringContainsString('3 IP rules', $data['messages']['success'][0]);
    }

    public function test_one_invalid_row_rejects_the_whole_file(): void
    {
        $response = $this->import($this->csv([
            'allow,203.0.113.10,ok',
            'allow,not-an-ip,bad',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertArrayNotHasKey('nodes', $data);
        $this->assertStringStartsWith('Row 3:', $data['errors'][0][0]);
    }

    public function test_requires_the_node_add_permission(): void
    {
        $this->grant(['access_control_add']);

        $response = $this->import($this->csv(['allow,203.0.113.10,']));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertArrayNotHasKey('nodes', $response->getData(true));
    }

    public function test_missing_file_is_a_validation_error(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(AccessControlController::class)->importNodes(
            Request::create('/access-controls/nodes/import', 'POST'),
            app(AccessControlService::class)
        );
    }

    public function test_file_with_only_a_header_row_is_rejected(): void
    {
        $this->assertSame(422, $this->import($this->csv([]))->getStatusCode());
    }

    public function test_double_quotes_in_a_description_are_rejected_and_blank_lines_ignored(): void
    {
        $response = $this->import($this->csv([
            'allow,203.0.113.10,"Office, main ""HQ"""',
            '',
            'DENY,198.51.100.7,',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $errors = $response->getData(true)['errors'];
        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('Row 2:', $errors[0][0]);
    }

    public function test_quoted_commas_are_kept_in_descriptions(): void
    {
        $response = $this->import($this->csv(['allow,203.0.113.10,"Office, main"']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Office, main', $response->getData(true)['nodes'][0]['node_description']);
    }

    public function test_template_download_requires_permission(): void
    {
        $this->grant([]);

        $this->expectException(HttpException::class);
        app(AccessControlController::class)->downloadNodesTemplate();
    }

    public function test_the_downloaded_template_can_be_uploaded_unchanged(): void
    {
        $template = app(AccessControlController::class)->downloadNodesTemplate()->getFile()->getContent();

        $headings = str_getcsv(trim(strtok($template, "\n")));
        $this->assertSame(['node_type', 'node_cidr', 'node_description'], $headings);

        $response = $this->import($template);

        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));
        $this->assertCount(3, $response->getData(true)['nodes']);
    }

    public function test_nothing_is_written_to_the_database(): void
    {
        $this->import($this->csv(['allow,203.0.113.10,']));

        $this->assertSame([], DB::select("select name from sqlite_master where type = 'table'"));
    }
}
