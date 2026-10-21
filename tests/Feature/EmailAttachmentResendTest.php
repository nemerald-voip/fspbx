<?php

namespace Tests\Feature;

use App\Jobs\RetryLoggedEmail;
use App\Mail\BaseMailable;
use App\Mail\FaxReceived;
use App\Mail\FaxSent;
use App\Mail\VoicemailNotification;
use App\Models\EmailLog;
use App\Services\EmailTemplateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\Compiler;
use ReflectionProperty;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailAttachmentResendTest extends TestCase
{
    private string $testDirectory;
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDirectory = sys_get_temp_dir().'/fspbx-email-attachments-'.Str::uuid();
        File::ensureDirectoryExists($this->testDirectory.'/views');
        $this->originalConnection = config('database.default');

        config([
            'cache.default' => 'array',
            'database.default' => 'email_attachment_resend_test',
            'database.connections.email_attachment_resend_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'filesystems.disks.voicemail' => [
                'driver' => 'local',
                'root' => $this->testDirectory.'/voicemail',
            ],
            'mail.default' => 'array',
            'mail.mailers.array' => ['transport' => 'array'],
            'mail.from.address' => 'noreply@example.test',
            'mail.from.name' => 'FS PBX',
            'view.compiled' => $this->testDirectory.'/views',
        ]);

        DB::purge('email_attachment_resend_test');
        Storage::forgetDisk('voicemail');
        Mail::purge('array');
        (new ReflectionProperty(Compiler::class, 'cachePath'))
            ->setValue(app('blade.compiler'), $this->testDirectory.'/views');

        $this->createSchema();
        $this->mock(EmailTemplateService::class, function ($mock) {
            $mock->shouldReceive('render')->andReturn([
                'available' => true,
                'subject' => 'Test notification',
                'html' => '<p>Test notification</p>',
                'text' => 'Test notification',
            ]);
        });
    }

    protected function tearDown(): void
    {
        DB::purge('email_attachment_resend_test');
        config(['database.default' => $this->originalConnection]);
        Storage::forgetDisk('voicemail');
        File::deleteDirectory($this->testDirectory);

        parent::tearDown();
    }

    /** @dataProvider attachmentCases */
    public function test_original_and_repeated_resends_attach_files_using_only_logged_references(
        string $mailableClass,
        string $extension,
        string $mime,
        ?string $disk
    ): void {
        [$mail, $reference, $contents] = $this->notification($mailableClass, $extension, $mime, $disk);

        Mail::to('recipient@example.test')->send($mail);

        $log = EmailLog::query()->sole();
        $this->assertSame([$reference], $log->attachments);
        $this->assertAttachment($reference, $contents);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            (new RetryLoggedEmail($log->uuid))->handle();

            $this->assertAttachment($reference, $contents);
            $this->assertSame('sent', $log->refresh()->status);
            $this->assertSame([$reference], $log->attachments);
            $this->assertSame(1, EmailLog::query()->count());
        }

        $storedReferences = DB::table('email_log')->where('uuid', $log->uuid)->value('attachments');
        $this->assertSame([$reference], json_decode($storedReferences, true));
        $this->assertStringNotContainsString($contents, $storedReferences);
        $this->assertStringNotContainsString(base64_encode($contents), $storedReferences);
    }

    /** @dataProvider attachmentCases */
    public function test_resend_silently_skips_deleted_files_and_preserves_their_references(
        string $mailableClass,
        string $extension,
        string $mime,
        ?string $disk
    ): void {
        [$mail, $reference] = $this->notification($mailableClass, $extension, $mime, $disk);
        Mail::to('recipient@example.test')->send($mail);
        $log = EmailLog::query()->sole();

        $this->deleteAttachment($reference);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            (new RetryLoggedEmail($log->uuid))->handle();

            $this->assertSame([], $this->lastEmail()->getAttachments());
            $this->assertSame('Test notification', $this->lastEmail()->getSubject());
            $this->assertSame('<p>Test notification</p>', $this->lastEmail()->getHtmlBody());
            $this->assertSame('sent', $log->refresh()->status);
            $this->assertSame([$reference], $log->attachments);
        }
    }

    /** @dataProvider attachmentCases */
    public function test_an_original_notification_with_no_available_file_logs_no_attachment(
        string $mailableClass,
        string $extension,
        string $mime,
        ?string $disk
    ): void {
        [$mail, $reference] = $this->notification($mailableClass, $extension, $mime, $disk);
        $this->deleteAttachment($reference);

        Mail::to('recipient@example.test')->send($mail);

        $log = EmailLog::query()->sole();
        $this->assertSame([], $log->attachments);
        $this->assertSame([], $this->lastEmail()->getAttachments());
        $this->assertSame('sent', $log->status);
    }

    public function test_a_voicemail_without_attachment_mode_does_not_log_or_resend_audio(): void
    {
        [$mail] = $this->notification(VoicemailNotification::class, 'wav', 'audio/wav', 'voicemail');
        $mail->attributes['attachment_path'] = null;
        $mail->attributes['voicemail_file_mode'] = 'link';

        Mail::to('recipient@example.test')->send($mail);
        $log = EmailLog::query()->sole();
        (new RetryLoggedEmail($log->uuid))->handle();

        $this->assertSame([], $log->refresh()->attachments);
        $this->assertSame([], $this->lastEmail()->getAttachments());
    }

    public static function attachmentCases(): array
    {
        return [
            'received PDF fax' => [FaxReceived::class, 'pdf', 'application/pdf', null],
            'received TIFF fax' => [FaxReceived::class, 'tif', 'image/tiff', null],
            'sent PDF fax' => [FaxSent::class, 'pdf', 'application/pdf', null],
            'WAV voicemail' => [VoicemailNotification::class, 'wav', 'audio/wav', 'voicemail'],
            'MP3 voicemail' => [VoicemailNotification::class, 'mp3', 'audio/mpeg', 'voicemail'],
        ];
    }

    private function notification(string $mailableClass, string $extension, string $mime, ?string $disk): array
    {
        $path = $disk
            ? 'account.example.test/1001/msg_'.Str::uuid().'.'.$extension
            : $this->testDirectory.'/fax.'.$extension;
        $contents = 'Attachment bytes '.Str::uuid();
        $name = ($disk ? 'voicemail.' : 'received-fax.').$extension;
        $reference = ['path' => $path, 'name' => $name, 'mime' => $mime];

        if ($disk) {
            Storage::disk($disk)->put($path, $contents);
            $reference = ['disk' => $disk] + $reference;
        } else {
            file_put_contents($path, $contents);
        }

        /** @var BaseMailable $mail */
        $mail = new $mailableClass([
            'logId' => (string) Str::uuid(),
            'domain_uuid' => (string) Str::uuid(),
            'caller_id_number' => '12025550100',
            'fax_pages' => '1',
            'attachment_path' => $path,
            'attachment_name' => $name,
            'attachment_mime' => $mime,
        ]);

        return [$mail, $reference, $contents];
    }

    private function deleteAttachment(array $reference): void
    {
        if (isset($reference['disk'])) {
            Storage::disk($reference['disk'])->delete($reference['path']);
        } else {
            unlink($reference['path']);
        }
    }

    private function lastEmail(): Email
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }

    private function assertAttachment(array $reference, string $contents): void
    {
        $attachments = $this->lastEmail()->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame($reference['name'], $attachments[0]->getFilename());
        $this->assertSame($reference['mime'], $attachments[0]->getContentType());
        $this->assertSame($contents, $attachments[0]->getBody());
    }

    private function createSchema(): void
    {
        foreach (['default', 'domain'] as $scope) {
            Schema::create('v_'.$scope.'_settings', function (Blueprint $table) use ($scope) {
                $table->uuid($scope.'_setting_uuid')->primary();
                $table->uuid('domain_uuid')->nullable();
                foreach (['category', 'subcategory', 'value', 'enabled'] as $column) {
                    $table->string($scope.'_setting_'.$column)->nullable();
                }
            });
        }

        Schema::create('email_log', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            foreach (['from', 'to', 'cc', 'bcc', 'subject', 'status', 'provider', 'provider_message_stream', 'provider_message_id'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['html_body', 'text_body', 'raw_body', 'sent_debug_info'] as $column) {
                $table->text($column)->nullable();
            }
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }
}
