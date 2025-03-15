<?php

namespace App\Http\Controllers;

use Throwable;
use Inertia\Inertia;
use App\Models\Contact;
use Illuminate\Http\Request;
use App\Models\ContactPhones;
use App\Imports\ContactsImport;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\HeadingRowImport;
use Propaganistas\LaravelPhone\Exceptions\NumberParseException;

class ContactsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Contacts';
    protected $searchable = ['contact_name', 'contact_organization', 'phone_number'];

    public function __construct()
    {
        $this->model = new Contact();
    }

    public function index()
    {
        if (!userCheckPermission("contact_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'routes' => [
                    'current_page' => route('contacts.index'),
                    'store' => route('contacts.store'),
                    'select_all' => route('contacts.select.all'),
                    'bulk_delete' => route('contacts.bulk.delete')
                ]
            ]
        );
    }

    public function getData($paginate = 5)
    {
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        $this->sortField = request()->get('sortField', 'contact_organization');
        $this->sortOrder = request()->get('sortOrder', 'asc');

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        logger($data);

        return $data;
    }

    public function builder(array $filters = [])
    {
        $data = $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data->where('domain_uuid', $domainUuid);
        $data->with(['primaryPhone' => function ($query) {
            $query->select('contact_phone_uuid', 'contact_uuid', 'phone_number', 'phone_speed_dial');
        }]);
        $data->with(['contact_users' => function ($query) {
            $query->select('contact_user_uuid', 'contact_uuid', 'user_uuid')->with(['user' => function ($query) {
                $query->select('user_uuid', 'username');
            }]);
        }]);
        
        $data->select(
            'contact_uuid',
            'contact_organization',

        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        return $data->orderBy($this->sortField, $this->sortOrder);
    }

    protected function filterSearch($query, $value)
    {
        $query->where(function ($query) use ($value) {
            foreach ($this->searchable as $field) {
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
        });
    }

    public function store(Request $request)
    {
        $inputs = $request->validate([
            'contact_name' => 'required|string|max:255',
            'contact_organization' => 'nullable|string|max:255',
            'contact_enabled' => 'required|boolean'
        ]);

        try {
            $instance = $this->model;
            $instance->fill($inputs);
            $instance->domain_uuid = session('domain_uuid');
            $instance->save();

            return response()->json(['messages' => ['success' => ['Contact created']]], 201);
        } catch (\Exception $e) {
            logger($e);
            return response()->json(['errors' => ['server' => ['Failed to create contact']]], 500);
        }
    }

    public function update(Request $request)
    {
        $inputs = $request->validate([
            'contact_uuid' => 'required|uuid',
            'contact_name' => 'required|string|max:255',
            'contact_organization' => 'nullable|string|max:255',
            'contact_enabled' => 'required|boolean'
        ]);

        try {
            $instance = $this->model::where('contact_uuid', $inputs['contact_uuid'])->firstOrFail();
            $instance->fill($inputs);
            $instance->save();

            return response()->json(['messages' => ['success' => ['Contact updated']]], 200);
        } catch (\Exception $e) {
            logger($e);
            return response()->json(['errors' => ['server' => ['Failed to update contact']]], 500);
        }
    }

    public function destroy(Contact $contact)
    {
        try {
            DB::beginTransaction();
            $contact->delete();
            DB::commit();
            return response()->json(['messages' => ['success' => ['Contact deleted']]], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);
            return response()->json(['errors' => ['server' => ['Failed to delete contact']]], 500);
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
