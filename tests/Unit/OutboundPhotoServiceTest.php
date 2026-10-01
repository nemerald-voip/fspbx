<?php

namespace Tests\Unit;

use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\OutboundPhotoService;
use App\Services\Messaging\PhotoCompressionBusy;
use App\Services\Messaging\PhotoCompressionService;
use App\Services\Messaging\PhotoCompressionSettings;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class OutboundPhotoServiceTest extends TestCase
{
    private Container $previousContainer;
    private $previousFacadeApplication;
    private string $directory;
    private PhotoCompressionService $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $app = new Application(dirname(__DIR__, 2));
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstance(\Illuminate\Process\Factory::class);
        $app->instance('config', new Repository());
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));
        config(['message_photos' => require config_path('message_photos.php')]);
        $this->directory = sys_get_temp_dir().'/fspbx-photo-test-'.Str::uuid();
        mkdir($this->directory, 0700);
        config(['message_photos.spool' => $this->directory.'/spool']);
        $this->processor = new PhotoCompressionService();
        if (!$this->processor->available()) {
            $this->markTestSkipped('These tests require libvips-tools 8.13+ with photo loaders, prlimit, and ImageMagick for fixtures.');
        }
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            if (isset($this->directory)) (new Filesystem())->deleteDirectory($this->directory);
            Container::setInstance($this->previousContainer);
            Facade::setFacadeApplication($this->previousFacadeApplication);
            Facade::clearResolvedInstance(\Illuminate\Process\Factory::class);
            parent::tearDown();
        }
    }

    /** @dataProvider convertibleFormats */
    public function test_converts_supported_formats_without_resizing_when_compression_is_disabled(string $format): void
    {
        $id = $this->request($this->fixture($format, '4000x3000'));
        $result = $this->processor->convert($id);
        $info = getimagesize(config('message_photos.spool').'/'.$id.'/output.jpg');

        $this->assertSame([4000, 3000, IMAGETYPE_JPEG], array_slice($info, 0, 3));
        $this->assertSame(1, $result['encodes']);
    }

    public static function convertibleFormats(): array
    {
        return [['webp'], ['heic'], ['avif'], ['tiff']];
    }

    /** @dataProvider convertibleFormats */
    public function test_compresses_each_converted_format_without_upscaling(string $format): void
    {
        $id = $this->request($this->fixture($format));
        $result = $this->processor->compress($id, 650000);
        $info = getimagesize(config('message_photos.spool').'/'.$id.'/output.jpg');

        $this->assertSame([80, 48, IMAGETYPE_JPEG], array_slice($info, 0, 3));
        $this->assertLessThanOrEqual(650000, $result['bytes']);
        $this->assertSame(1, $result['encodes']);
    }

    /** @dataProvider photoColourSpaces */
    public function test_normalizes_colour_and_removes_embedded_metadata(string $space, string $depth): void
    {
        $path = $this->directory.'/colour.tiff';
        $this->fixtureCommand(['-size', '80x48', 'xc:red', '-colorspace', $space,
            '-depth', $depth, '-set', 'comment', 'PRIVATE PHOTO METADATA', $path]);
        $id = $this->request($path);
        $this->processor->convert($id);
        $output = config('message_photos.spool').'/'.$id.'/output.jpg';

        $this->assertSame(3, getimagesize($output)['channels']);
        $this->assertStringNotContainsString('PRIVATE PHOTO METADATA', file_get_contents($output));
    }

    public static function photoColourSpaces(): array
    {
        return [['CMYK', '8'], ['Gray', '16'], ['sRGB', '16']];
    }

    public function test_conversion_leaves_png_bytes_unchanged(): void
    {
        $fixture = $this->fixture('png');
        $id = $this->request($fixture);
        $this->assertTrue($this->processor->convert($id)['unchanged']);
        $this->assertSame(file_get_contents($fixture), file_get_contents(config('message_photos.spool').'/'.$id.'/input'));
        $this->assertFileDoesNotExist(config('message_photos.spool').'/'.$id.'/output.jpg');
    }

    /** @dataProvider oversizedDimensions */
    public function test_rejects_oversized_dimensions_before_decoding_pixels(int $width, int $height): void
    {
        $path = $this->fixture('png');
        $png = file_get_contents($path);
        $ihdr = pack('NN', $width, $height).substr($png, 24, 5);
        file_put_contents($path, substr($png, 0, 16).$ihdr.pack('N', crc32('IHDR'.$ihdr)).substr($png, 33));
        $id = $this->request($path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Photo dimensions exceed');
        $this->processor->compress($id, 650000);
    }

    public static function oversizedDimensions(): array
    {
        return [[30001, 1], [10001, 10000]];
    }

    public function test_compresses_and_resizes_png_with_a_white_background(): void
    {
        $id = $this->request($this->fixture('png', '2000x100', 'none'));
        $result = $this->processor->compress($id, 650000);
        $path = config('message_photos.spool').'/'.$id.'/output.jpg';
        $info = getimagesize($path);
        $image = imagecreatefromjpeg($path);
        $pixel = imagecolorsforindex($image, imagecolorat($image, 0, 0));
        imagedestroy($image);

        $this->assertSame([1600, 80, IMAGETYPE_JPEG], array_slice($info, 0, 3));
        $this->assertLessThanOrEqual(650000, $result['bytes']);
        $this->assertGreaterThanOrEqual(250, min($pixel['red'], $pixel['green'], $pixel['blue']));
    }

    public function test_second_encode_fits_a_small_attachment_budget(): void
    {
        $path = $this->directory.'/noise.png';
        $this->fixtureCommand(['-size', '700x700', 'xc:', '-seed', '42', '+noise', 'Random', $path]);
        $id = $this->request($path);
        $result = $this->processor->compress($id, 16000);

        $this->assertSame(2, $result['encodes']);
        $this->assertLessThanOrEqual(16000, $result['bytes']);
    }

    public function test_compresses_a_twelve_megapixel_photo(): void
    {
        $id = $this->request($this->fixture('png', '4000x3000'));
        $result = $this->processor->compress($id, 650000);
        $info = getimagesize(config('message_photos.spool').'/'.$id.'/output.jpg');

        $this->assertSame([1600, 1200, IMAGETYPE_JPEG], array_slice($info, 0, 3));
        $this->assertLessThanOrEqual(650000, $result['bytes']);
    }

    public function test_jpeg_already_within_budget_keeps_original_bytes(): void
    {
        $fixture = $this->fixture('jpg');
        $id = $this->request($fixture);
        $result = $this->processor->compress($id, 650000);

        $this->assertTrue($result['unchanged']);
        $this->assertSame(0, $result['encodes']);
        $this->assertSame(file_get_contents($fixture), file_get_contents(config('message_photos.spool').'/'.$id.'/input'));
        $this->assertFileDoesNotExist(config('message_photos.spool').'/'.$id.'/output.jpg');
    }

    /** @dataProvider multipleFrameFormats */
    public function test_rejects_multiple_frames(string $format): void
    {
        $path = $this->directory.'/frames.'.$format;
        $this->fixtureCommand(['-size', '48x48', 'xc:red', 'xc:blue', $path]);
        $id = $this->request($path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Animated or multipage images');
        $this->processor->convert($id);
    }

    public static function multipleFrameFormats(): array
    {
        return [['tiff'], ['webp']];
    }

    public function test_rejects_a_disguised_non_photo(): void
    {
        $path = $this->directory.'/disguised.jpg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>');
        $id = $this->request($path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This photo format is not supported');
        $this->processor->convert($id);
    }

    public function test_applies_orientation_before_removing_metadata(): void
    {
        $path = $this->directory.'/rotated.tiff';
        $this->fixtureCommand(['-size', '80x48', 'xc:red', '-depth', '8', '-orient', 'RightTop', $path]);
        $id = $this->request($path);
        $this->processor->convert($id);
        $info = getimagesize(config('message_photos.spool').'/'.$id.'/output.jpg');

        $this->assertSame([48, 80, IMAGETYPE_JPEG], array_slice($info, 0, 3));
    }

    public function test_rejects_input_over_fifty_megabytes_before_decoding(): void
    {
        $id = $this->request($this->fixture('png'));
        $file = fopen(config('message_photos.spool').'/'.$id.'/input', 'r+');
        ftruncate($file, 50 * 1024 * 1024 + 1);
        fclose($file);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('50 MB input limit');
        $this->processor->convert($id);
    }

    public function test_native_allocation_limit_fails_cleanly(): void
    {
        $id = $this->request($this->fixture('png'));
        config(['message_photos.process_memory_bytes' => 1024 * 1024]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed or exceeded its memory limit');
        $this->processor->convert($id);
    }

    public function test_rejects_a_symlink_input(): void
    {
        $fixture = $this->fixture('jpg');
        $id = $this->request($fixture);
        $input = config('message_photos.spool').'/'.$id.'/input';
        unlink($input);
        symlink($fixture, $input);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to start photo compression.');
        $this->processor->convert($id);
    }

    public function test_rejects_an_existing_output_symlink_without_changing_its_target(): void
    {
        $target = $this->directory.'/unrelated.txt';
        file_put_contents($target, 'keep me');
        $id = $this->request($this->fixture('png'));
        symlink($target, config('message_photos.spool').'/'.$id.'/output.jpg');
        try {
            $this->processor->compress($id, 650000);
            $this->fail('The output symlink must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unable to start', $exception->getMessage());
        }
        $this->assertSame('keep me', file_get_contents($target));
    }

    public function test_spool_path_with_shell_characters_is_passed_literally(): void
    {
        config(['message_photos.spool' => $this->directory.'/photos with spaces;literal']);
        $id = $this->request($this->fixture('png'));
        $this->processor->compress($id, 650000);
        $this->assertSame(IMAGETYPE_JPEG, getimagesize(config('message_photos.spool').'/'.$id.'/output.jpg')[2]);
    }

    public function test_native_guard_blocks_non_photo_decoders_even_if_called_directly(): void
    {
        $path = $this->directory.'/blocked.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>');
        $process = new Process([config('message_photos.vips_binary'), 'svgload', $path, $this->directory.'/blocked.jpg'],
            null, ['VIPS_BLOCK_UNTRUSTED' => '1'], null, 5);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('blocked', $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->directory.'/blocked.jpg');
    }

    public function test_timeout_stops_the_native_process(): void
    {
        $script = $this->directory.'/slow-vipsheader';
        file_put_contents($script, "#!/bin/sh\necho $$ > process.pid\nexec /usr/bin/sleep 10\n");
        chmod($script, 0700);
        config(['message_photos.vipsheader_binary' => $script, 'message_photos.process_timeout' => 0.2]);
        $id = $this->request($this->fixture('png'));
        $started = microtime(true);
        try {
            $this->processor->convert($id);
            $this->fail('The process should time out.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('time limit', $exception->getMessage());
        }

        $pid = (int) file_get_contents(config('message_photos.spool').'/'.$id.'/process.pid');
        $this->assertGreaterThan(0, $pid);
        $this->assertFalse(posix_kill($pid, 0));
        $this->assertLessThan(3, microtime(true) - $started);
    }

    public function test_subprocesses_share_one_deadline(): void
    {
        foreach (['vipsheader', 'vips'] as $binary) {
            $script = $this->directory.'/slow-'.$binary;
            file_put_contents($script, "#!/bin/sh\nsleep 0.3\nexec ".escapeshellarg(config('message_photos.'.$binary.'_binary'))." \"\$@\"\n");
            chmod($script, 0700);
            config(['message_photos.'.$binary.'_binary' => $script]);
        }
        config(['message_photos.process_timeout' => 0.5]);
        $id = $this->request($this->fixture('png'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('time limit');
        $this->processor->compress($id, 650000);
    }

    public function test_excessive_native_output_stops_the_process(): void
    {
        $script = $this->directory.'/noisy-vipsheader';
        file_put_contents($script, "#!/bin/sh\necho $$ > process.pid\nhead -c 70000 /dev/zero\nexec sleep 10\n");
        chmod($script, 0700);
        config(['message_photos.vipsheader_binary' => $script]);
        $id = $this->request($this->fixture('png'));
        $started = microtime(true);
        try {
            $this->processor->compress($id, 650000);
            $this->fail('Excessive subprocess output must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Photo compression failed', $exception->getMessage());
        }
        $pid = (int) file_get_contents(config('message_photos.spool').'/'.$id.'/process.pid');
        $this->assertGreaterThan(0, $pid);
        $this->assertFalse(posix_kill($pid, 0));
        $this->assertLessThan(3, microtime(true) - $started);
    }

    public function test_outbound_pipeline_preserves_original_and_reuses_saved_derivative(): void
    {
        $fixture = $this->fixture('webp');
        $original = $this->item('photo.webp', 'image/webp', filesize($fixture));
        $message = $this->message([$original]);
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $storage->shouldReceive('getObjectForDomain')->once()->with('domain-uuid', 'messages', 'original/photo.webp')
            ->andReturn(['body' => Utils::streamFor(file_get_contents($fixture))]);
        $storage->shouldReceive('storeBinary')->once()->withArgs(function ($domain, $bytes, $name, $provider, $mime) {
            $this->assertSame('domain-uuid', $domain);
            $this->assertSame('photo.jpg', $name);
            $this->assertSame('test', $provider);
            $this->assertSame('image/jpeg', $mime);
            $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($bytes)[2]);
            return true;
        })->andReturn(['bucket' => 'messages', 'object_key' => 'prepared/photo.jpg', 'stored_name' => 'photo.jpg',
            'original_name' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'size' => 500]);
        $service = $this->outbound($storage, false);
        $service->prepare($message);
        $service->prepare($message);

        $photo = $message->media[0];
        $this->assertSame('convert', $photo['photo_compression']['mode']);
        $this->assertSame(array_intersect_key($original, array_flip(['bucket', 'object_key', 'original_name', 'mime_type'])), $photo['photo_compression']['original']);
        $this->assertSame('/messages/media/message-uuid/0/photo.jpg', $photo['access_path']);
        $this->assertSame(1, $message->saves);
        $this->assertSame(['client.lock'], array_values(array_diff(scandir(config('message_photos.spool')), ['.', '..'])));
    }

    public function test_reuses_prepared_derivative_and_repairs_missing_access_path(): void
    {
        $photo = $this->item('photo.jpg', 'image/jpeg', 300000);
        $photo['photo_compression'] = ['version' => 1, 'mode' => 'compress'];
        $message = $this->message([$photo]);
        $service = $this->outbound(Mockery::mock(MessageMediaObjectStorageService::class), true);
        $service->prepare($message);

        $this->assertSame('/messages/media/message-uuid/0/photo.jpg', $message->media[0]['access_path']);
        $this->assertSame(1, $message->saves);
    }

    public function test_shares_remaining_budget_and_keeps_non_photo_attachments(): void
    {
        $fixture = $this->fixture('png');
        $photo = $this->item('photo.png', 'image/png', filesize($fixture));
        $gif = $this->item('animation.gif', 'image/gif', 600000);
        $message = $this->message([$photo, $photo, $gif]);
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $storage->shouldReceive('getObjectForDomain')->twice()->andReturnUsing(fn () => ['body' => Utils::streamFor(file_get_contents($fixture))]);
        $processor = Mockery::mock(PhotoCompressionService::class);
        $processor->shouldReceive('compress')->twice()->with(Mockery::type('string'), 25000)->andReturn(['unchanged' => true]);
        $this->outbound($storage, true, $processor)->prepare($message);

        $this->assertSame($gif, $message->media[2]);
        $this->assertSame(2, $message->saves);
    }

    public function test_error_cleans_temporary_files_and_preserves_original_message(): void
    {
        $photo = $this->item('photo.png', 'image/png', 100);
        $message = $this->message([$photo]);
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $storage->shouldReceive('getObjectForDomain')->once()->andReturn(['body' => Utils::streamFor("\x89PNG\r\n\x1a\ninvalid")]);
        try {
            $this->outbound($storage, true)->prepare($message);
            $this->fail('The broken image must fail conversion.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Photo compression failed', $exception->getMessage());
        }

        $this->assertSame([$photo], $message->media);
        $this->assertSame(0, $message->saves);
        $this->assertSame(['client.lock'], array_values(array_diff(scandir(config('message_photos.spool')), ['.', '..'])));
    }

    public function test_cleans_abandoned_requests_on_demand_without_following_symlinks(): void
    {
        $spool = config('message_photos.spool');
        mkdir($spool, 0700);
        $stale = $spool.'/'.Str::uuid();
        mkdir($stale, 0700);
        file_put_contents($stale.'/input', 'abandoned input');
        file_put_contents($stale.'/vips-scratch', 'abandoned cache');
        touch($stale, time() - 7200);
        $unrelated = $this->directory.'/unrelated';
        mkdir($unrelated, 0700);
        file_put_contents($unrelated.'/input', 'must remain');
        symlink($unrelated, $spool.'/'.Str::uuid());
        $fresh = $spool.'/'.Str::uuid();
        mkdir($fresh, 0700);

        $fixture = $this->fixture('jpg');
        $photo = $this->item('photo.jpg', 'image/jpeg', filesize($fixture));
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $storage->shouldReceive('getObjectForDomain')->once()->andReturn(['body' => Utils::streamFor(file_get_contents($fixture))]);
        $this->outbound($storage, true)->prepare($this->message([$photo]));

        $this->assertDirectoryDoesNotExist($stale);
        $this->assertDirectoryExists($fresh);
        $this->assertSame('must remain', file_get_contents($unrelated.'/input'));
    }

    public function test_busy_processor_leaves_message_for_existing_queue_retry(): void
    {
        mkdir(config('message_photos.spool'), 0700);
        $lock = fopen(config('message_photos.spool').'/client.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->expectException(PhotoCompressionBusy::class);
            $this->outbound(Mockery::mock(MessageMediaObjectStorageService::class), true)
                ->prepare($this->message([$this->item('photo.png', 'image/png', 100)]));
        } finally {
            fclose($lock);
        }
    }

    public function test_ordinary_messages_do_not_create_a_photo_spool(): void
    {
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $this->outbound($storage, true)->prepare($this->message([]));
        $this->outbound($storage, false)->prepare($this->message([$this->item('photo.jpg', 'image/jpeg', 100)]));
        $this->outbound($storage, true)->prepare($this->message([$this->item('animation.gif', 'image/gif', 100)]));

        $this->assertDirectoryDoesNotExist(config('message_photos.spool'));
    }

    private function fixture(string $format, string $size = '80x48', string $color = 'red'): string
    {
        $path = $this->directory.'/fixture.'.$format;
        $this->fixtureCommand(['-size', $size, 'xc:'.$color, '-depth', '8', $path]);
        return $path;
    }

    private function fixtureCommand(array $arguments): void
    {
        (new Process(['/usr/bin/convert', ...$arguments], null, ['OMP_NUM_THREADS' => '1'], null, 20))->mustRun();
    }

    private function request(string $fixture): string
    {
        $id = (string) Str::uuid();
        $directory = config('message_photos.spool').'/'.$id;
        mkdir($directory, 0700, true);
        copy($fixture, $directory.'/input');
        return $id;
    }

    private function item(string $name, string $mime, int $bytes): array
    {
        return ['bucket' => 'messages', 'object_key' => 'original/'.$name, 'original_name' => $name,
            'mime_type' => $mime, 'size' => $bytes, 'provider' => 'test'];
    }

    private function message(array $media): Messages
    {
        return new class(['message_uuid' => 'message-uuid', 'domain_uuid' => 'domain-uuid', 'direction' => 'out', 'media' => $media]) extends Messages
        {
            public int $saves = 0;

            public function save(array $options = [])
            {
                $this->saves++;
                return true;
            }
        };
    }

    private function outbound(MessageMediaObjectStorageService $storage, bool $compress, ?PhotoCompressionService $processor = null): OutboundPhotoService
    {
        $settings = Mockery::mock(PhotoCompressionSettings::class);
        $settings->shouldReceive('enabled')->with('domain-uuid')->andReturn($compress);
        return new OutboundPhotoService($settings, $processor ?? $this->processor, $storage);
    }
}
