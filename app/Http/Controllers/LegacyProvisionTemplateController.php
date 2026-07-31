<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLegacyProvisionTemplateRequest;
use App\Services\LegacyProvisionTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class LegacyProvisionTemplateController extends Controller
{
    public function __construct(
        private readonly LegacyProvisionTemplateService $templates
    ) {
    }

    public function index(): Response|RedirectResponse
    {
        if (! userCheckPermission('provision_editor_view')) {
            return redirect('/');
        }

        $files = [];
        $root = null;
        $loadError = null;

        try {
            $root = $this->templates->root();
            $files = $this->templates->files();
        } catch (Throwable $exception) {
            report($exception);
            $loadError = __('The legacy provisioning template directory could not be loaded.');
        }

        return Inertia::render('LegacyProvisionTemplates', [
            'files' => $files,
            'template_root' => $root,
            'load_error' => $loadError,
            'routes' => [
                'show' => route('legacy-provision-templates.show'),
                'update' => route('legacy-provision-templates.update'),
            ],
            'permissions' => [
                'save' => userCheckPermission('provision_editor_save'),
            ],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorizeView();

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
        ]);

        try {
            return response()->json([
                'file' => $this->templates->read($validated['path']),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'messages' => ['path' => [__($exception->getMessage())]],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'messages' => ['file' => [__($exception->getMessage())]],
            ], 422);
        }
    }

    public function update(UpdateLegacyProvisionTemplateRequest $request): JsonResponse
    {
        try {
            $file = $this->templates->write(
                $request->validated('path'),
                $request->validated('content')
            );

            session(['reload_xml' => true]);

            return response()->json([
                'file' => $file,
                'messages' => ['success' => [__('Provisioning template saved.')]],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'messages' => ['path' => [__($exception->getMessage())]],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'messages' => ['file' => [__($exception->getMessage())]],
            ], 422);
        }
    }

    private function authorizeView(): void
    {
        abort_unless(userCheckPermission('provision_editor_view'), 403);
    }
}
