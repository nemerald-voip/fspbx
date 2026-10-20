<?php

namespace Tests\Unit;

use Tests\TestCase;

class LineDisplayNameFormatTest extends TestCase
{
    private const DAVID = [
        'extension' => '100',
        'effective_caller_id_name' => 'David Duck',
        'directory_first_name' => 'David',
        'directory_last_name' => 'Duck',
    ];

    public function test_formats_supported_tokens(): void
    {
        $this->assertSame('100', fspbx_format_line_display_name('{extension}', self::DAVID));
        $this->assertSame('David Duck', fspbx_format_line_display_name('{name}', self::DAVID));
        $this->assertSame('100 David Duck', fspbx_format_line_display_name('{extension} {name}', self::DAVID));
        $this->assertSame('David Duck (100)', fspbx_format_line_display_name('{name} ({extension})', self::DAVID));
        $this->assertSame('David 100', fspbx_format_line_display_name('{first_name} {extension}', self::DAVID));
        $this->assertSame('Duck, David', fspbx_format_line_display_name('{last_name}, {first_name}', self::DAVID));
    }

    public function test_blank_format_keeps_the_extension_number(): void
    {
        $this->assertSame('100', fspbx_format_line_display_name(null, self::DAVID));
        $this->assertSame('100', fspbx_format_line_display_name('   ', self::DAVID));
    }

    public function test_name_falls_back_to_directory_names_then_the_extension_number(): void
    {
        $directoryOnly = ['effective_caller_id_name' => ''] + self::DAVID;
        $nameless = ['extension' => '101'];

        $this->assertSame('David Duck', fspbx_format_line_display_name('{name}', $directoryOnly));
        $this->assertSame('101', fspbx_format_line_display_name('{name}', $nameless));
        $this->assertSame('101', fspbx_format_line_display_name('{name} ({extension})', $nameless));
        $this->assertSame('101', fspbx_format_line_display_name('{first_name} {extension}', $nameless));
    }

    public function test_partial_names_collapse_extra_spaces(): void
    {
        $firstOnly = ['extension' => '102', 'directory_first_name' => 'Ann'];

        $this->assertSame('Ann', fspbx_format_line_display_name('{first_name}  {last_name}', $firstOnly));
    }

    public function test_only_blank_non_external_lines_are_automatic(): void
    {
        $this->assertTrue(fspbx_line_display_name_is_automatic(['display_name' => null, 'auth_id' => '100']));
        $this->assertTrue(fspbx_line_display_name_is_automatic(['display_name' => '  ', 'auth_id' => '100', 'external_line' => false]));

        // Stored names, including older lines that hold the number, always win.
        $this->assertFalse(fspbx_line_display_name_is_automatic(['display_name' => '100', 'auth_id' => '100']));
        $this->assertFalse(fspbx_line_display_name_is_automatic(['display_name' => 'Front Desk', 'auth_id' => '100']));

        $this->assertFalse(fspbx_line_display_name_is_automatic(['display_name' => '', 'auth_id' => '100', 'external_line' => true]));
        $this->assertFalse(fspbx_line_display_name_is_automatic(['display_name' => '', 'auth_id' => '100', 'external_line' => 't']));
        $this->assertFalse(fspbx_line_display_name_is_automatic(['display_name' => '', 'auth_id' => '']));
    }
}
