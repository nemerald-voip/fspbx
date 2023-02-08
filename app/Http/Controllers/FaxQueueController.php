<?php

namespace App\Http\Controllers;

use App\Models\FaxQueues;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class FaxQueueController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_queue_all")) {
            return redirect('/');
        }
        // Get local Time Zone
        $time_zone = get_local_time_zone(Session::get('domain_uuid'));

        $domain_uuid = Session::get('domain_uuid');
        $faxqueues = FaxQueues::where('domain_uuid', $domain_uuid)->orderBy('fax_date', 'asc')->paginate(10)->onEachSide(1);

        foreach ($faxqueues as $i => $faxqueue){
            $faxqueues[$i]['fax_date'] = Carbon::parse($faxqueue['fax_date'])->setTimezone($time_zone);
            $faxqueues[$i]['fax_notify_date'] = Carbon::parse($faxqueue['fax_notify_date'])->setTimezone($time_zone);
            $faxqueues[$i]['fax_retry_date'] = Carbon::parse($faxqueue['fax_retry_date'])->setTimezone($time_zone);
        }

        $data = array();
        $data['faxqueues'] = $faxqueues;

        $permissions['add_new'] = userCheckPermission('fax_queue_add');
        $permissions['edit'] = userCheckPermission('fax_queue_edit');
        $permissions['delete'] = userCheckPermission('fax_queue_delete');
        $permissions['view'] = userCheckPermission('fax_queue_view');

        return view('layouts.faxqueue.list')
            ->with($data)
            ->with('permissions', $permissions);
    }

    /**
     * Show the create voicemail form.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function create(){
        if (!userCheckPermission('fax_queue_add') || !userCheckPermission('fax_queue_edit')) {
            return redirect('/');
        }

        $faxqueues = new FaxQueues();

        // Check FusionPBX login status
        session_start();
        if(session_status() === PHP_SESSION_NONE) {
            return redirect()->route('logout');
        }

        $data = [];
        $data['voicemail'] = $faxqueues;
        return view('layouts.faxqueue.createOrUpdate')->with($data);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $faxQueue = FaxQueues::findOrFail($id);

        if(isset($faxQueue)){
            $deleted = $faxQueue->delete();
            if ($deleted){
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected entries have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected entries extensions'
                    ]
                ]);
            }
        }
    }
}
