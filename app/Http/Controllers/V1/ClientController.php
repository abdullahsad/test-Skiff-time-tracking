<?php

namespace App\Http\Controllers\V1;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;

class ClientController extends Controller
{
    /**
     * Display a paginated list of clients for the authenticated user.
     *
     * Retrieves clients belonging to the currently authenticated user,
     * including related user and projects data.
     * Returns a JSON response with paginated client data.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * Success Response (200):
     * {
     *   "success": true,
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "Client Name",
     *         "user": { ... },
     *         "projects": [ ... ]
     *       },
     *       ...
     *     ],
     *     "first_page_url": "...",
     *     "last_page": 1,
     *     ...
     *   }
     * }
     */
    public function index(Request $request)
    {
        $clients = Client::with(['user', 'projects'])
            ->where('user_id', auth()->id())
            ->paginate(10);

        return response()->json([
            'data' => $clients,
            'status' => 200,
        ], 200);
    }

    /**
     * Store a newly created client for the authenticated user.
     *
     * Validates the incoming request data and creates a new client record
     * if a client with the same email does not already exist for the user.
     * Returns a JSON response with the created client data or validation errors.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Request parameters:
     * - name: string, required
     * - email: string, required, valid email, max 255 chars
     * - contact_person: string, required
     *
     * Success Response (201):
     * {
     *   "message": "Client created successfully",
     *   "status": 201,
     *   "data": { ...client fields... }
     * }
     *
     * Validation/Error Response (422):
     * {
     *   "message": { ...validation errors... } | "Client already exists for this user",
     *   "status": 422
     * }
     */
    public function store(Request $request)
    {
        if (isset($request->email)) {
            $request->email = strtolower($request->email);
        }

        $rules = [
            'name' => 'required',
            'email' => 'required|email|max:255',
            'contact_person' => 'required',
        ];

        $messages = [
            'name.required' => 'We need to know your client name!',
            'email.required' => 'We need to know your client email!',
            'email.email' => 'Please provide a valid email address',
            'email.max' => 'Email should not be more than :max characters',
            'contact_person.required' => 'We need to know the contact person for the client!',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->errors()->first()) {
            return response()->json([
                'message' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $user_id = auth()->id();

        $client = Client::where('user_id', $user_id)
            ->where('email', $request->email)
            ->first();

        if ($client) {
            return response()->json([
                'message' => 'Client already exists for this user',
                'status' => 422,
            ], 422);
        }
        $client = Client::create([
            'user_id' => $user_id,
            'name' => $request->name,
            'email' => $request->email,
            'contact_person' => $request->contact_person,
        ]);
        return response()->json([
            'message' => 'Client created successfully',
            'status' => 201,
            'data' => $client,
        ], 201);
    }

    /**
     * Display the specified client for the authenticated user.
     *
     * Retrieves a single client by ID, including related user and projects data,
     * only if the client belongs to the currently authenticated user.
     * Returns a JSON response with the client data or a not found error.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * Success Response (200):
     * {
     *   "success": true,
     *   "data": { ...client fields... }
     * }
     *
     * Error Response (404):
     * {
     *   "message": "Client not found",
     *   "status": 404
     * }
     */
    public function show(string $id)
    {
        $client = Client::with(['user', 'projects'])
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$client) {
            return response()->json([
                'message' => 'Client not found',
                'status' => 404,
            ], 404);
        }

        return response()->json([
            'data' => $client,
            'status' => 200,
        ], 200);
    }

    /**
     * Update the specified client for the authenticated user.
     *
     * Updates client fields (name, email, contact_person) if provided in the request,
     * ensuring the email is unique among the user's clients.
     * Returns a JSON response with the updated client data, or errors if not found or email conflict.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * Request parameters (any of):
     * - name: string, optional
     * - email: string, optional, valid email, max 255 chars, unique for user
     * - contact_person: string, optional
     *
     * Success Response (200):
     * {
     *   "message": "Client updated successfully",
     *   "status": 200,
     *   "data": { ...client fields... }
     * }
     *
     * Error Response (404):
     * {
     *   "message": "Client not found",
     *   "status": 404
     * }
     *
     * Error Response (422):
     * {
     *   "message": "Email already exists for another client of this user",
     *   "status": 422
     * }
     */
    public function update(Request $request, string $id)
    {
        $user_id = auth()->id();

        $client = Client::where('id', $id)
            ->where('user_id', $user_id)
            ->first();

        if (!$client) {
            return response()->json([
                'message' => 'Client not found',
                'status' => 404,
            ], 404);
        }

        if ($request->has('name') && !empty($request->name)) {
            $client->name = $request->name;
        }

        if ($request->has('email') && !empty($request->email)) {
            $existing_client = Client::where('user_id', $user_id)
                ->where('id', '!=', $id)
                ->where('email', $request->email)
                ->first();
            if ($existing_client) {
                return response()->json([
                    'message' => 'Email already exists for another client of this user',
                    'status' => 422,
                ], 422);
            }
            $client->email = strtolower($request->email);
        }

        if ($request->has('contact_person') && !empty($request->contact_person)) {
            $client->contact_person = $request->contact_person;
        }

        $client->save();

        return response()->json([
            'message' => 'Client updated successfully',
            'status' => 200,
            'data' => $client,
        ], 200);
    }

    /**
     * Delete the specified client for the authenticated user.
     *
     * Deletes a client by ID only if it belongs to the currently authenticated user
     * and has no associated projects. Returns a JSON response indicating success,
     * not found, or a conflict if the client has projects.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * Success Response (200):
     * {
     *   "message": "Client deleted successfully",
     *   "status": 200
     * }
     *
     * Error Response (404):
     * {
     *   "message": "Client not found",
     *   "status": 404
     * }
     *
     * Error Response (422):
     * {
     *   "message": "Cannot delete client with projects. Please delete the projects first.",
     *   "status": 422
     * }
     */
    public function destroy(string $id)
    {
        $user_id = auth()->id();

        $client = Client::where('id', $id)
            ->where('user_id', $user_id)
            ->first();

        if (!$client) {
            return response()->json([
                'message' => 'Client not found',
                'status' => 404,
            ], 404);
        }

        $projects = Project::where('client_id', $client->id)->get();
        if ($projects->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete client with projects. Please delete the projects first.',
                'status' => 422,
            ], 422);
        }

        $client->delete();

        return response()->json([
            'message' => 'Client deleted successfully',
            'status' => 200,
        ], 200);
    }
}
