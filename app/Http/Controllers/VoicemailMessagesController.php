<?php

namespace App\Http\Controllers;

use App\Models\VoicemailMessages;
use App\Services\FreeswitchEslService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VoicemailMessagesController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'VoicemailMessages';
    protected $searchable = ['caller_id_name', 'caller_id_number'];

    public function __construct()
    {
        $this->model = new VoicemailMessages();
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("voicemail_message_view")) {
            return redirect('/');
        }

        $voicemail_uuid = request()->route('voicemail');

        $domain_uuid = session('domain_uuid');
        $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfDay()->setTimeZone('UTC');
        $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))->endOfDay()->setTimeZone('UTC');

        return Inertia::render(
            $this->viewName,
            [
                'voicemail_uuid' => $voicemail_uuid,

                'startPeriod' => function () use ($startPeriod) {
                    return $startPeriod;
                },
                'endPeriod' => function ()  use ($endPeriod) {
                    return $endPeriod;
                },
                'timezone' => function () use ($domain_uuid) {
                    return get_local_time_zone($domain_uuid);
                },

                'routes' => [
                    'current_page' => route('voicemails.messages.index', request()->route('voicemail')),
                    'get_message_url' => route('voicemail.message.url'),
                    'select_all' => route('voicemails.messages.select.all'),
                    'bulk_delete' => route('voicemails.messages.bulk.delete'),
                    'data_route' => route('voicemails.messages.data'),
                    'update_status' => route('voicemails.messages.update-status'),
                    'recording_route' => route('voicemails.messages.recording'),

                ],
                'permissions' => function () {
                    return $this->getUserPermissions();
                },
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData()
    {
        $params = request()->all();
        $params['paginate'] = 50;
        $params['domain_uuid'] = session('domain_uuid');

        if (!empty(data_get($params, 'filter.dateRange'))) {
            $params['filter']['startPeriod'] = Carbon::parse(data_get($params, 'filter.dateRange.0'))->getTimestamp();
            $params['filter']['endPeriod']   = Carbon::parse(data_get($params, 'filter.dateRange.1'))->getTimestamp();
            unset($params['filter']['dateRange']);
        }

        $data = QueryBuilder::for(VoicemailMessages::class, request()->merge($params))
            ->select([
                'voicemail_message_uuid',
                'voicemail_uuid',
                'created_epoch',
                'caller_id_name',
                'caller_id_number',
                'message_length',
                'message_status',
                'message_priority',
                'message_transcription',
            ])
            ->allowedFilters([
                AllowedFilter::exact('voicemail_uuid'),

                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('created_epoch', '>=', (int) $value);
                }),

                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('created_epoch', '<=', (int) $value);
                }),

                AllowedFilter::callback('search', function ($query, $value) {
                    if (blank($value)) {
                        return;
                    }

                    $query->where(function ($q) use ($value) {
                        $q->where('caller_id_name', 'ILIKE', "%{$value}%")
                            ->orWhere('caller_id_number', 'ILIKE', "%{$value}%")
                            ->orWhere('message_transcription', 'ILIKE', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_epoch'])
            ->defaultSort('-created_epoch')
            ->paginate($params['paginate']);

        return response()->json($data);
    }



    /**
     * Get voicemail message.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVoicemailMessage(VoicemailMessages $message)
    {
        $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';

        if (!Storage::disk('voicemail')->exists($path)) {
            $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            if (!Storage::disk('voicemail')->exists($path)) {
                abort(404);
            }
        }

        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);

        $response = Response::make(file_get_contents($file), 200);
        $response->header("Content-Type", $type);
        return $response;
    }

    /**
     * Download voicemail message.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadVoicemailMessage(VoicemailMessages $message)
    {

        $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';

        if (!Storage::disk('voicemail')->exists($path)) {
            $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            if (!Storage::disk('voicemail')->exists($path)) {
                abort(404);
            }
        }

        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);
        $headers = array(
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, basename($file), $headers);

        return $response;
    }


    public function getVoicemailMessageUrl()
    {
        try {
            // Step 1: Get the voicemail_message_uuid from the request
            $message = VoicemailMessages::with(['voicemail' => function ($query) {
                $query->select('voicemail_uuid', 'voicemail_id');
            }])
                ->find(request('voicemail_message_uuid'));

            // Check if the greeting exists
            if (!$message) {
                throw new \Exception('File not found');
            }


            // Step 2: Check for the existence of .wav and .mp3 files
            $domainName = session('domain_name');
            $voicemailId = $message->voicemail->voicemail_id;
            $messageUuid = $message->voicemail_message_uuid;

            $wavPath = $domainName . '/' . $voicemailId . '/msg_' . $messageUuid . '.wav';
            $mp3Path = $domainName . '/' . $voicemailId . '/msg_' . $messageUuid . '.mp3';

            if (Storage::disk('voicemail')->exists($wavPath)) {
                $fileName = 'msg_' . $messageUuid . '.wav';
            } elseif (Storage::disk('voicemail')->exists($mp3Path)) {
                $fileName = 'msg_' . $messageUuid . '.mp3';
            } else {
                throw new \Exception('No file found');
            }

            // Generate the file URL using the defined route
            $fileUrl = route('voicemail.file.serve', [
                'domain' => session('domain_name'),
                'voicemail_id' => $message->voicemail->voicemail_id,
                'file' => $fileName,
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function updateStatus(Request $request)
    {
        try {
            DB::beginTransaction();

            $domainName = session('domain_name');
            $domainUuid = session('domain_uuid');
            $status = $request->get('status'); // 'saved' or null
            $itemUuids = $request->get('items', []);

            // 1. Fetch messages to identify affected mailboxes and verify domain ownership
            $messages = VoicemailMessages::whereIn('voicemail_message_uuid', $itemUuids)
                ->where('domain_uuid', $domainUuid)
                ->with([
                    'voicemail' => function ($query) {
                        $query->select('voicemail_uuid', 'voicemail_id');
                    }
                ])
                ->get([
                    'voicemail_message_uuid',
                    'voicemail_uuid',
                ]);

            $mailboxesToUpdate = [];

            foreach ($messages as $message) {
                if ($message->voicemail) {
                    $voicemailId = $message->voicemail->voicemail_id;
                    $mailboxesToUpdate[$voicemailId] = $voicemailId;
                }
            }

            // 2. Perform the update
            VoicemailMessages::whereIn('voicemail_message_uuid', $messages->pluck('voicemail_message_uuid'))
                ->update(['message_status' => $status]);

            DB::commit();

            // 3. Trigger WMI Update via ESL
            $freeSwitchService = new FreeswitchEslService();

            foreach ($mailboxesToUpdate as $voicemailId) {
                $command = sprintf(
                    "bgapi luarun app.lua voicemail mwi '%s'@'%s'",
                    $voicemailId,
                    $domainName
                );

                $freeSwitchService->executeCommand($command);
            }

            return response()->json([
                'success' => true,
                'messages' => ['server' => ['Status updated successfully.']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            logger('VoicemailMessagesController@updateStatus error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while updating status.']]
            ], 500);
        }
    }


    public function getRecording(Request $request)
    {
        $uuid = $request->query('item_uuid');

        $message = VoicemailMessages::where('voicemail_message_uuid', $uuid)
            ->where('domain_uuid', session('domain_uuid'))
            ->with('voicemail')
            ->firstOrFail();

        $fileUrl = route('voicemails.messages.file', ['uuid' => $uuid]);

        return response()->json([
            'item' => [
                'xml_cdr_uuid' => $message->voicemail_message_uuid,
                'start_date' => $message->created_epoch_formatted,
                'caller_id_name' => $message->caller_id_name,
                'caller_id_number_formatted' => $message->caller_id_number,
                'caller_destination_formatted' => $message->voicemail->voicemail_id,
                'transcription' => $message->message_transcription,
            ],
            'audio_url' => $fileUrl,
            'download_url' => $fileUrl . '?download=true',
            'filename' => 'voicemail.wav',
        ]);
    }

    public function getFile($uuid)
    {
        $message = VoicemailMessages::where('voicemail_message_uuid', $uuid)
            ->where('domain_uuid', session('domain_uuid'))
            ->with('voicemail')
            ->firstOrFail();

        $path = session('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $uuid . '.wav';

        $disk = Storage::disk('voicemail');

        if (!$disk->exists($path)) {
            abort(404);
        }

        // Determine if we are downloading or playing
        $isDownload = request()->has('download');

        // If your storage is LOCAL, this is the most robust way to support seeking
        if (config("filesystems.disks.voicemail.driver") === 'local') {
            $fullPath = $disk->path($path);

            $response = new BinaryFileResponse($fullPath);

            if ($isDownload) {
                $response->setContentDisposition('attachment', "msg_$uuid.wav");
            } else {
                $response->setContentDisposition('inline');
            }

            return $response;
        }

        // If your storage is S3 or other remote drivers, use this streamed approach:
        $size = $disk->size($path);
        $type = $disk->mimeType($path);
        $stream = $disk->readStream($path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            "Content-Type" => $type,
            "Content-Length" => $size,
            "Content-Disposition" => ($isDownload ? 'attachment' : 'inline') . "; filename=\"msg_$uuid.wav\"",
            "Accept-Ranges" => "bytes", 
        ]);
    }


    public function bulkDelete()
    {
        try {
            DB::beginTransaction();

            $domainName = session('domain_name');
            $disk = Storage::disk('voicemail');

            $messages = VoicemailMessages::whereIn('voicemail_message_uuid', request('items', []))
                ->where('domain_uuid', session('domain_uuid'))
                ->with([
                    'voicemail' => function ($query) {
                        $query->select('voicemail_uuid', 'voicemail_id');
                    }
                ])
                ->get([
                    'voicemail_message_uuid',
                    'voicemail_uuid',
                ]);

            $mailboxesToUpdate = [];

            foreach ($messages as $message) {
                if (!$message->voicemail) {
                    continue;
                }

                $voicemailId = $message->voicemail->voicemail_id;
                $mailboxesToUpdate[$voicemailId] = $voicemailId;

                foreach (['wav', 'mp3'] as $ext) {
                    $path = $domainName . '/' . $voicemailId . '/msg_' . $message->voicemail_message_uuid . '.' . $ext;

                    if ($disk->exists($path)) {
                        $disk->delete($path);
                    }
                }

                $message->delete();
            }

            DB::commit();

            $freeSwitchService = new FreeswitchEslService();

            foreach ($mailboxesToUpdate as $voicemailId) {
                $command = sprintf(
                    "bgapi luarun app.lua voicemail mwi '%s'@'%s'",
                    $voicemailId,
                    $domainName
                );

                $freeSwitchService->executeCommand($command);
            }

            return response()->json([
                'messages' => ['server' => ['All selected items have been deleted successfully.']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            logger('VoicemailMessagesController@bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500);
        }
    }


    public function selectAll()
    {
        try {
            $params = request()->all();
            $params['domain_uuid'] = session('domain_uuid');

            if (!empty(data_get($params, 'filter.dateRange'))) {
                $params['filter']['startPeriod'] = Carbon::parse(data_get($params, 'filter.dateRange.0'))->getTimestamp();
                $params['filter']['endPeriod']   = Carbon::parse(data_get($params, 'filter.dateRange.1'))->getTimestamp();

                unset($params['filter']['dateRange']);
            }

            $data = QueryBuilder::for(VoicemailMessages::class, request()->merge($params))
                ->select([
                    'voicemail_message_uuid',
                    'domain_uuid',
                    'voicemail_uuid',
                    'created_epoch',
                    'caller_id_name',
                    'caller_id_number',
                    'message_transcription',
                ])
                ->allowedFilters([
                    AllowedFilter::exact('voicemail_uuid'),

                    AllowedFilter::callback('startPeriod', function ($query, $value) {
                        $query->where('created_epoch', '>=', (int) $value);
                    }),

                    AllowedFilter::callback('endPeriod', function ($query, $value) {
                        $query->where('created_epoch', '<=', (int) $value);
                    }),

                    AllowedFilter::callback('search', function ($query, $value) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        $query->where(function ($q) use ($value) {
                            $q->where('caller_id_name', 'ILIKE', "%{$value}%")
                                ->orWhere('caller_id_number', 'ILIKE', "%{$value}%")
                                ->orWhere('message_transcription', 'ILIKE', "%{$value}%");
                        });
                    }),
                ])
                ->pluck('voicemail_message_uuid');

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $data,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500);
        }
    }


    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['voicemail_message_destroy'] = userCheckPermission('voicemail_message_delete');
        $permissions['voicemail_message_update'] = userCheckPermission('voicemail_message_update');

        return $permissions;
    }
}
