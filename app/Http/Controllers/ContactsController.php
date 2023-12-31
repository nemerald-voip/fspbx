<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactPhones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Propaganistas\LaravelPhone\Exceptions\NumberParseException;

class ContactsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("contact_view")) {
            return redirect('/');
        }

        //Check FusionPBX login status
        session_start();
        if (!isset($_SESSION['user'])) {
            return redirect()->route('logout');
        }

        $searchString = $request->get('search');

        // Get all contact phones
        $contactPhones =  ContactPhones::select('contact_phone_uuid','v_contact_phones.contact_uuid', 'phone_number', 'phone_speed_dial')
            ->with(['contact' => function ($query) {
                $query->select('contact_uuid', 'contact_organization');
            }])
            ->join('v_contacts', 'v_contact_phones.contact_uuid', '=', 'v_contacts.contact_uuid');

        if ($searchString) {
            $contactPhones->where(function ($query) use ($searchString) {
                $query->where('v_contacts.contact_organization', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
                        ->orWhere('v_contact_phones.phone_number', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
                        ->orWhere('v_contact_phones.phone_speed_dial', 'ilike', '%' . str_replace('-', '', $searchString) . '%');
            });
        }
        $contactPhones = $contactPhones->orderBy('v_contacts.contact_organization')
            ->paginate(50)
            ->onEachSide(1);


        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        foreach ($contactPhones as $contactPhone) {
            if ($contactPhone->phone_number) {
                try {
                    $phoneNumberObject = $phoneNumberUtil->parse($contactPhone->phone_number, 'US');
                    if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                        $contactPhone->phone_number = $phoneNumberUtil
                            ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
                    }
                } catch (NumberParseException $e) {
                    // Do nothing and leave the numner as is
                }
            }
        }

        // foreach ($extensions as $extension) {
        //     if ($extension['outbound_caller_id_number']) {
        //         try {
        //             $phoneNumberObject = $phoneNumberUtil->parse($extension['outbound_caller_id_number'], 'US');
        //             if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
        //                 $extension->outbound_caller_id_number = $phoneNumberUtil
        //                     ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
        //             }
        //         } catch (NumberParseException $e) {
        //             // Do nothing and leave the numner as is
        //         }
        //     }
        //     //check against registrations and add them to array
        //     $all_regs = [];
        //     foreach ($registrations as $registration) {
        //         if ($registration['sip-auth-user'] == $extension['extension']) {
        //             array_push($all_regs, $registration);
        //         }
        //     }
        //     if (count($all_regs) > 0) {
        //         $extension->setAttribute("registrations", $all_regs);
        //         unset($all_regs);
        //     }
        // }

        $data = array();
        // $domain_uuid=Session::get('domain_uuid');
        $data['searchString'] = $searchString;
        $data['contactPhones'] = $contactPhones;

        //assign permissions
        $permissions['add_new'] = userCheckPermission('contact_add');
        $permissions['delete'] = userCheckPermission('contact_delete');
        $permissions['import'] = userCheckPermission('contact_upload');

        $data['permissions'] = $permissions;

        return view('layouts.contacts.list')
            ->with($data);
        // ->with("conn_params", $conn_params);
    }
}
