<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\Phonebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Provisioning\Phonebook\PhonebookBuilder;
use App\Services\Provisioning\Phonebook\Formatters\YealinkFormatter;
use App\Services\Provisioning\Phonebook\Formatters\GrandstreamFormatter;

class PhonebookController extends Controller
{
    /**
     * Serve a device's directory XML.
     *
     * Route: /prov/directory/{book}/{path}
     * {book} is a phonebook UUID, or "all" to serve every phonebook assigned to
     * the device merged into one directory (used by Grandstream, which downloads
     * a single phonebook.xml). DigestProvisionAuth resolves and attaches the
     * device from the MAC/serial token in {path} before this runs.
     */
    public function serve(Request $request, string $book, string $path = '')
    {
        /** @var Devices|null $device */
        $device = $request->attributes->get('prov.device');
        if (!$device) {
            return response('', 404);
        }

        $vendor = strtolower((string) $device->device_vendor);
        $formatter = $this->formatterForVendor($vendor);
        if (!$formatter) {
            return response('', 404);
        }

        if (strtolower($book) === 'all') {
            $phonebooks = $this->devicePhonebooks($device);
        } else {
            $phonebooks = Phonebook::query()
                ->where('phonebook_uuid', $book)
                ->where('domain_uuid', $device->domain_uuid)
                ->where('enabled', true)
                ->get();
        }

        if ($phonebooks->isEmpty()) {
            return response('', 404);
        }

        $entries = (new PhonebookBuilder())->buildMany($phonebooks, (string) $device->domain_uuid);
        $body = $formatter->format($entries);

        $etag = '"' . hash('sha256', $body) . '"';
        $ifNoneMatch = $request->headers->get('If-None-Match');

        $headers = [
            'Content-Type'  => $formatter->mime(),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'ETag'          => $etag,
        ];

        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return response('', 304, $headers);
        }

        if ($request->isMethod('HEAD')) {
            return response('', 200, $headers);
        }

        return response($body, 200, $headers);
    }

    private function formatterForVendor(string $vendor)
    {
        return match ($vendor) {
            'grandstream' => new GrandstreamFormatter(),
            'yealink'     => new YealinkFormatter(),
            default       => null,
        };
    }

    /**
     * Resolve every enabled phonebook assigned to the device (per-device
     * assignment first, otherwise the account default set), ordered by slot/name.
     *
     * @return \Illuminate\Support\Collection<int, Phonebook>
     */
    private function devicePhonebooks(Devices $device): \Illuminate\Support\Collection
    {
        $uuids = DB::table('device_phonebook')
            ->where('device_uuid', $device->device_uuid)
            ->orderBy('slot')
            ->pluck('phonebook_uuid')
            ->map(fn ($u) => (string) $u)
            ->all();

        $query = Phonebook::query()
            ->where('domain_uuid', $device->domain_uuid)
            ->where('enabled', true);

        if (!empty($uuids)) {
            return $query->whereIn('phonebook_uuid', $uuids)->get()
                ->sortBy(fn ($pb) => array_search((string) $pb->phonebook_uuid, $uuids, true))
                ->values();
        }

        return $query->where('is_default', true)->orderBy('name')->get();
    }
}
