<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveLetsEncryptSettingsRequest;
use App\Services\LetsEncryptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class LetsEncryptController extends Controller
{
    public function __construct(protected LetsEncryptService $service) {}

    /**
     * Current TLS configuration + on-disk certificate status.
     */
    public function status(): JsonResponse
    {
        if (! $this->canManage()) {
            return response()->json(['errors' => ['auth' => ['Access denied.']]], 403);
        }

        return response()->json($this->service->status());
    }

    /**
     * Persist the Let's Encrypt configuration (without issuing).
     */
    public function saveConfig(SaveLetsEncryptSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->persistSettings($validated);

        return response()->json([
            'messages' => ['success' => ['Settings saved.']],
            'status' => $this->service->status(),
        ]);
    }

    /**
     * Issue or renew the certificate now, install it and reload FreeSWITCH.
     */
    public function issue(SaveLetsEncryptSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Persist the complete form before ACME work begins. Configuration
        // changes remain saved even when issuance or peer validation fails.
        $this->persistSettings($validated);

        try {
            $result = $this->service->issue(
                $validated['domain'],
                $validated['account_email'],
                (bool) $validated['staging'],
            );

            $validTo = $result['valid_to'] ? date('M j, Y', strtotime($result['valid_to'])) : 'unknown';
            $env = $result['staging'] ? ' (staging)' : '';

            return response()->json([
                'messages' => ['success' => [
                    "Certificate issued for {$validated['domain']}{$env}; valid until {$validTo}. FreeSWITCH reloaded.",
                ]],
                'status' => $this->service->status(),
            ]);
        } catch (Throwable $e) {
            $this->service->recordError($e);

            logger('LetsEncryptController@issue error: '.$e->getMessage()
                .' at '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'errors' => ['certificate' => [$e->getMessage() ?: 'Unable to issue certificate.']],
            ], 500);
        }
    }

    /**
     * Revoke the installed certificate at the CA and replace it with a
     * self-signed cert so FreeSWITCH keeps serving TLS.
     */
    public function revoke(): JsonResponse
    {
        if (! $this->canManage()) {
            return response()->json(['errors' => ['auth' => ['Access denied.']]], 403);
        }

        try {
            $result = $this->service->revoke();
            $env = $result['staging'] ? ' (staging)' : '';

            return response()->json([
                'messages' => ['success' => [
                    "Certificate revoked{$env} and replaced with a self-signed certificate. FreeSWITCH reloaded.",
                ]],
                'status' => $this->service->status(),
            ]);
        } catch (Throwable $e) {
            $this->service->recordError($e);

            logger('LetsEncryptController@revoke error: '.$e->getMessage()
                .' at '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'errors' => ['certificate' => [$e->getMessage() ?: 'Unable to revoke certificate.']],
            ], 500);
        }
    }

    /**
     * Public endpoint: install a certificate pushed from a peer node. Not
     * session-authenticated — authorized by the shared push secret instead, so
     * the active node can replicate a renewed cert to standby nodes.
     */
    public function receiveCertificate(Request $request): JsonResponse
    {
        $secret = $request->header('X-FsPbx-Cert-Secret') ?: $request->input('secret');

        if (! $this->service->verifyPushSecret(is_string($secret) ? $secret : null)) {
            return response()->json(['errors' => ['auth' => ['Invalid or missing push secret.']]], 403);
        }

        $validated = $request->validate([
            'certificate' => ['required', 'string'],
            'polycom_ca' => ['nullable', 'string'],
        ]);

        try {
            $changed = $this->service->installBundle($validated['certificate']);
            $reload = $changed ? $this->service->reloadFreeswitch() : 'unchanged';

            if (! empty($validated['polycom_ca'])) {
                $this->service->setPolycomCaCert($validated['polycom_ca']);
            }

            logger('LetsEncryptController@receiveCertificate: '
                .($changed ? 'installed pushed certificate' : 'certificate already current')
                .' from '.$request->ip().'.');

            return response()->json([
                'messages' => ['success' => [$changed ? 'Certificate installed.' : 'Certificate already current.']],
                'reload' => $reload,
            ]);
        } catch (Throwable $e) {
            logger('LetsEncryptController@receiveCertificate error: '.$e->getMessage()
                .' at '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'errors' => ['certificate' => [$e->getMessage() ?: 'Unable to install pushed certificate.']],
            ], 500);
        }
    }

    /**
     * Present or clean up an HTTP-01 token pushed by the active cluster node.
     */
    public function receiveChallenge(Request $request): JsonResponse
    {
        $secret = $request->header('X-FsPbx-Cert-Secret') ?: $request->input('secret');

        if (! $this->service->verifyPushSecret(is_string($secret) ? $secret : null)) {
            return response()->json(['errors' => ['auth' => ['Invalid or missing push secret.']]], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:present,cleanup'],
            'token' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'value' => ['required_if:action,present', 'nullable', 'string', 'max:4096'],
        ]);

        try {
            if ($validated['action'] === 'present') {
                $this->service->storeChallengeToken($validated['token'], $validated['value']);
            } else {
                $this->service->removeChallengeToken($validated['token']);
            }

            return response()->json([
                'messages' => ['success' => [
                    $validated['action'] === 'present'
                        ? 'Challenge token stored.'
                        : 'Challenge token removed.',
                ]],
            ]);
        } catch (Throwable $exception) {
            logger('LetsEncryptController@receiveChallenge error: '.$exception->getMessage()
                .' at '.$exception->getFile().':'.$exception->getLine());

            return response()->json([
                'errors' => ['challenge' => [$exception->getMessage() ?: 'Unable to update challenge token.']],
            ], 500);
        }
    }

    /**
     * Generate and immediately persist a strong peer push secret.
     */
    public function generateSecret(): JsonResponse
    {
        if (! $this->canManage()) {
            return response()->json(['errors' => ['auth' => ['Access denied.']]], 403);
        }

        $secret = Str::random(40);
        $this->service->saveSetting('push_secret', $secret);

        return response()->json([
            'secret' => $secret,
            'messages' => ['success' => ['Peer push secret rotated and saved.']],
        ]);
    }

    protected function persistSettings(array $validated): void
    {
        $this->service->saveSetting('domain', implode(' ', $this->service->parseDomains($validated['domain'])));
        $this->service->saveSetting('account_email', $validated['account_email']);
        $this->service->saveSetting('webroot', $validated['webroot']);
        $this->service->saveSetting('staging', $validated['staging'] ? 'true' : 'false');
        $this->service->saveSetting('auto_renew', $validated['auto_renew'] ? 'true' : 'false');
        $this->service->saveSetting('push_secret', $validated['push_secret'] ?? '');
        $this->service->saveScheduledJobToggle((bool) $validated['auto_renew']);
    }

    protected function canManage(): bool
    {
        return isSuperAdmin();
    }
}
