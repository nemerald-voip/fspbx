<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Contact;
use Illuminate\Http\Request;
use App\Models\ContactPhones;
use App\Imports\ContactsImport;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\HeadingRowImport;
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

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\ContactPhones $contact_phone_uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($contact_phone_uuid)
    {
        $contactPhone = ContactPhones::findOrFail($contact_phone_uuid);


        if (isset($contactPhone)) {
            if (isset($contactPhone->contact)) {

                // Delete all contact users assosiated with this contact
                if (isset($contactPhone->contact->contact_users)) {
                    $contactPhone->contact->contact_users->each(function($contactUser) {
                        $contactUser->delete();
                    });
                }

                // Delete contact
                $contactPhone->contact->delete();
            }

            // Delete contact phone
            $deleted =  $contactPhone->delete();


            if ($deleted) {

                return response()->json([
                    'status' => 200,
                    'id' => $contact_phone_uuid,
                    'success' => [
                        'message' => 'Selected contacts have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected contacts'
                    ],

                ]);
            }
        }
    }

        /**
     * Import the specified resource
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        try {

            $headings = (new HeadingRowImport)->toArray(request()->file('file'));

            $import = new ContactsImport;
            $import->import(request()->file('file'));

            // Get array of failures and combine into html
            if ($import->failures()->isNotEmpty()) {
                $errormessage = 'Some errors were detected. Please, check the details: <ul>';
                foreach ($import->failures() as $failure) {
                    foreach ($failure->errors() as $error) {
                        $value = (isset($failure->values()[$failure->attribute()]) ? $failure->values()[$failure->attribute()] : "NULL");
                        $errormessage .= "<li>Skipping row <strong>" . $failure->row() . "</strong>. Invalid value <strong>'" . $value . "'</strong> for field <strong>'" . $failure->attribute() . "'</strong>. " . $error . "</li>";
                    }
                }
                $errormessage .= '</ul>';

                // Send response in format that Dropzone understands
                return response()->json([
                    'error' => $errormessage,
                ], 400);
            }
        } catch (Throwable $e) {
            // Log::alert($e);
            // Send response in format that Dropzone understands
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }


        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Extensions were successfully uploaded'
            ]
        ]);
    }

}
