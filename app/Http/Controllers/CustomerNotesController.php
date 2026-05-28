<?php

namespace App\Http\Controllers;

use App\Models\CustomerNote;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerNotesController extends Controller
{
    private const LEVEL_PERMISSIONS = [
        1 => 'customer_notes_level_1',
        2 => 'customer_notes_level_2',
        3 => 'customer_notes_level_3',
    ];

    private const EDIT_PERMISSION = 'customer_notes_edit';

    public static function permissionFlags(): array
    {
        return [
            'customer_notes_level_1' => userCheckPermission(self::LEVEL_PERMISSIONS[1]),
            'customer_notes_level_2' => userCheckPermission(self::LEVEL_PERMISSIONS[2]),
            'customer_notes_level_3' => userCheckPermission(self::LEVEL_PERMISSIONS[3]),
            'customer_notes_edit' => userCheckPermission(self::EDIT_PERMISSION),
        ];
    }

    public function show(): array
    {
        $this->authorizeCustomerNotesAccess();

        return $this->payload();
    }

    public function update(Request $request): array
    {
        $levels = $this->authorizeCustomerNotesAccess();

        abort_if(
            ! userCheckPermission(self::EDIT_PERMISSION),
            403,
            'You do not have permission to edit customer notes.'
        );

        $validated = $request->validate([
            'notes' => ['nullable', 'array'],
            'notes.level_1' => ['nullable', 'string', 'max:20000'],
            'notes.level_2' => ['nullable', 'string', 'max:20000'],
            'notes.level_3' => ['nullable', 'string', 'max:20000'],
        ]);

        $notes = $validated['notes'] ?? [];
        $visibleLevels = array_flip($levels);

        foreach ([1, 2, 3] as $level) {
            if (
                ! isset($visibleLevels[$level])
                && array_key_exists("level_{$level}", $notes)
                && trim(strip_tags((string) $notes["level_{$level}"])) !== ''
            ) {
                abort(403, 'You do not have permission to update that customer notes level.');
            }
        }

        $domainUuid = session('domain_uuid');
        $userUuid = Auth::id();

        foreach ($levels as $level) {
            $content = $this->sanitizeContent($notes["level_{$level}"] ?? null);

            $note = CustomerNote::query()->firstOrNew([
                'domain_uuid' => $domainUuid,
                'note_level' => $level,
            ]);

            if (! $note->exists) {
                $note->created_by = $userUuid;
            }

            $note->content = $content;
            $note->updated_by = $userUuid;
            $note->save();
        }

        return [
            'messages' => ['success' => ['Customer notes updated.']],
            'customer_notes' => $this->payload(),
        ];
    }

    private function authorizeCustomerNotesAccess(): array
    {
        $levels = $this->visibleLevels();

        abort_if($levels === [], 403, 'You do not have permission to view customer notes.');

        return $levels;
    }

    private function payload(): array
    {
        $levels = $this->visibleLevels();

        if ($levels === []) {
            return [
                'visible' => false,
                'levels' => [],
                'notes' => [],
            ];
        }

        $notes = CustomerNote::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('note_level', $levels)
            ->orderBy('note_level')
            ->get()
            ->mapWithKeys(fn (CustomerNote $note) => [
                "level_{$note->note_level}" => $note->content,
            ])
            ->all();

        foreach ($levels as $level) {
            $notes["level_{$level}"] = $notes["level_{$level}"] ?? null;
        }

        return [
            'visible' => true,
            'levels' => $levels,
            'notes' => $notes,
        ];
    }

    private function visibleLevels(): array
    {
        $levels = [];

        foreach (self::LEVEL_PERMISSIONS as $level => $permission) {
            if (userCheckPermission($permission)) {
                $levels[] = $level;
            }
        }

        return $levels;
    }

    private function sanitizeContent(?string $content): ?string
    {
        if ($content === null || trim(strip_tags($content)) === '') {
            return null;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,div,br,strong,b,em,i,u,s,ul,ol,li,blockquote,pre,code,a[href],h3,h4');
        $config->set('AutoFormat.AutoParagraph', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        return (new HTMLPurifier($config))->purify($content);
    }
}
