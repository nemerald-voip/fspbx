<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use App\Services\Provisioning\{VendorRouter, TemplateResolver, VarBuilder, DeviceLocator, Mime};

class ProvisioningController extends Controller
{
    public function handle(Request $req, string $path = '')
    {
        $path = ltrim($path, '/');
        $ctx  = app(VendorRouter::class)->analyze($path, $req->userAgent() ?? '');
        $dev  = app(DeviceLocator::class)->find($ctx);

        // PUT logs (now authenticated via middleware)
        if ($req->isMethod('PUT') && preg_match('/-(app|boot)\.log$/', $ctx->filename)) {
            Storage::disk('provision')->put("logs/{$ctx->filename}", $req->getContent() ?? '');
            return response('', 200);
        }

        // STATIC first (firmware/images/tones)
        if ($rel = app(TemplateResolver::class)->resolveStatic($ctx, $dev)) {
            if ($req->isMethod('HEAD')) return response('', 200, ['Content-Type' => Mime::from($ctx->filename)]);
            return response()->stream(fn() => print Storage::disk('provision')->get($rel), 200,
                ['Content-Type' => Mime::from($ctx->filename)]);
        }

        // Then BLADE templates
        if ($tpl = app(TemplateResolver::class)->resolveBlade($ctx, $dev)) {
            if ($req->isMethod('HEAD')) return response('', 200, ['Content-Type' => Mime::from($ctx->filename)]);
            $vars = app(VarBuilder::class)->for($dev, $ctx);
            $body = Blade::render(Storage::disk('provision')->get($tpl), $vars);
            return response($body, 200, [
                'Content-Type'  => Mime::from($ctx->filename),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]);
        }

        return response('', 404);
    }
}
