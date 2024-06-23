<?php
namespace App\Services;

use Carbon\Carbon;
use App\Models\CDR;
use App\Models\Extensions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class CdrDataService
{
    private $domain_uuid;
    private $filters = [];
    private $sortField;
    private $sortOrder;
    private $model;
    private $domains;
    private $permissions;
    private $searchable;

    public function getData($params = [])
    {
        $this->domain_uuid = $params['domain_uuid'] ?? null;
        $this->domains = $params['domains'] ?? null;
        $this->permissions = $params['permissions'] ?? [];
        $this->searchable = $params['searchable'] ?? [];

        $this->filters = [
            'startPeriod' => $params['filterData']['startPeriod'] ?? null,
            'endPeriod' => $params['filterData']['endPeriod'] ?? null,
            'showGlobal' => $params['filterData']['showGlobal'] ?? null,
            'direction' => $params['filterData']['direction'] ?? null,
            'search' => $params['filterData']['search'] ?? null,
            'entity' => $params['filterData']['entity'] ?? null,
            'entityType' => $params['filterData']['entityType'] ?? null
        ];


        $this->sortField = $params['filterData']['sortField'] ?? 'start_epoch';
        $this->sortOrder = $params['filterData']['sortOrder'] ?? 'desc';

        $cdrs = $this->builder($this->filters);

        if ($params['paginate']) {
            $cdrs = $cdrs->paginate($params['paginate']);
        } else {
            $cdrs = $cdrs->cursor();
        }

        return $cdrs;
    }

    public function builder($filters = [])
    {
        $this->model = new CDR();
        $data =  $this->model::query();

        if ($filters['showGlobal']== 'true') {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter by those domains
            $domainUuids = $this->domains;
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter by the session's domain_uuid
            $domainUuid = $this->domain_uuid;
            $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        }

        $data->select(
            'xml_cdr_uuid',
            'direction',
            'caller_id_name',
            'caller_id_number',
            'caller_destination',
            'destination_number',
            'domain_uuid',
            'extension_uuid',
            'sip_call_id',
            'source_number',
            // 'start_stamp',
            'start_epoch',
            // 'answer_stamp',
            // 'answer_epoch',
            'end_epoch',
            // 'end_stamp',
            'duration',
            'record_path',
            'record_name',
            // 'leg',
            'voicemail_message',
            'missed_call',
            // 'call_center_queue_uuid',
            // 'cc_side',
            // 'cc_queue_joined_epoch',
            // 'cc_queue',
            // 'cc_agent',
            // 'cc_agent_bridged',
            // 'cc_queue_answered_epoch',
            // 'cc_queue_terminated_epoch',
            // 'cc_queue_canceled_epoch',
            'cc_cancel_reason',
            'cc_cause',
            'waitsec',
            'hangup_cause',
            'hangup_cause_q850',
            'sip_hangup_disposition',
            'status'
        );

        //exclude legs that were not answered
        if (!$this->permissions['xml_cdr_lose_race']) {
            $data->where('hangup_cause', '!=', 'LOSE_RACE');
        }

        foreach ($filters as $field => $value) {
            if (method_exists($this, $method = "filter" . ucfirst($field))) {
                $this->$method($data, $value);
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    protected function filterStartPeriod($query, $value)
    {
        $query->where('start_epoch', '>=', $value->getTimestamp());
    }

    protected function filterEndPeriod($query, $value)
    {
        $query->where('start_epoch', '<=', $value->getTimestamp());
    }

    protected function filterDirection($query, $value)
    {
        $query->where('direction', 'ilike', '%' . $value . '%');
    }

    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
        });
    }

    protected function filterEntity($query, $value)
    {
        if (!isset($this->filters['entityType'])) {
            return;
        }
        switch ($this->filters['entityType']) {
            case 'queue':
                $query->where('call_center_queue_uuid', 'ilike', '%' . $value . '%');
                break;
            case 'extension':
                $extention = Extensions::find($value);
                $query->where(function ($query) use ($extention) {
                    $query->where('extension_uuid', 'ilike', '%' . $extention->extension_uuid . '%')
                        ->orWhere('caller_id_number', $extention->extension)
                        ->orWhere('caller_destination', $extention->extension)
                        ->orWhere('source_number', $extention->extension)
                        ->orWhere('destination_number', $extention->extension);
                });
                break;
        }
    }

}