<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Models\Messages;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\Http;
use App\Services\SynchMessageProvider;
use App\Services\CommioMessageProvider;
use Illuminate\Support\Facades\Session;
use App\Jobs\SendSmsNotificationToSlack;

class ReportsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Reports';
    protected $searchable = [];

    // public function __construct()
    // {
    //     //
    // }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        return Inertia::render(
            $this->viewName,
            [
                // 'data' => function () {
                //     return $this->getData();
                // },
                // 'showGlobal' => function () {
                //     return request('filterData.showGlobal') === 'true';
                // },

                'routes' => [
                    'current_page' => route('reports.index'),
                ]
            ]
        );
    }


}
