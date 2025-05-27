<?php

namespace App\Http\Controllers\V1;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project;
use App\Models\Client;
use App\Models\User;
use App\Models\ProjectTimeLog;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a paginated list of projects for the authenticated user.
     *
     * Retrieves projects belonging to the currently authenticated user,
     * optionally filtered by client_id, including related user and client data.
     * Returns a JSON response with paginated project data.
     *
     * Query parameters:
     * - client_id: integer, optional (filter projects by client)
     *
     * @param  \Illuminate\Http\Request  $request
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
     *         "title": "Project Title",
     *         "user": { ... },
     *         "client": { ... }
     *       },
     *       ...
     *     ],
     *     ...
     *   }
     * }
     */
    public function index(Request $request)
    {
        $user_id = auth()->id();

        $projects = new Project();

        $projects = $projects->where('user_id', $user_id);

        if(isset($request->client_id)) {
            $projects = $projects->where('client_id', $request->client_id);
        }

        $projects = $projects->with(['user', 'client'])
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ], 200);
    }

    /**
     * Store a newly created project for the authenticated user.
     *
     * Validates the incoming request data and creates a new project record
     * if the specified client exists and belongs to the user.
     * Returns a JSON response with the created project data or validation errors.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Request parameters:
     * - title: string, required, max 255 chars
     * - description: string, optional
     * - status: string, required, must be 'active' or 'completed'
     * - deadline: date, optional
     * - client_id: integer, required, must exist and belong to user
     *
     * Success Response (201):
     * {
     *   "message": "Project created successfully",
     *   "status": 201,
     *   "data": { ...project fields... }
     * }
     *
     * Validation/Error Response (422):
     * {
     *   "message": { ...validation errors... } | "Client does not exist for this user.",
     *   "status": 422
     * }
     */
    public function store(Request $request)
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'string|nullable',
            'status' => 'required|in:active,completed',
            'deadline' => 'date|nullable',
            'client_id' => 'required|exists:clients,id',
        ];
        $messages = [
            'title.required' => 'We need to know the project title!',
            'title.string' => 'Project title must be a string.',
            'title.max' => 'Project title should not be more than :max characters.',
            'description.string' => 'Project description must be a string.',
            'status.required' => 'Project status is required.',
            'status.in' => 'Project status must be either active or completed.',
            'deadline.date' => 'Project deadline must be a valid date.',
            'client_id.required' => 'Client ID is required.',
            'client_id.exists' => 'The selected client does not exist.',
            'client_id.integer' => 'Client ID must be an integer.',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->errors()->first()) {
            return response()->json([
                'message' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $user_id = auth()->id();

        $existing_client = Client::where('id', $request->client_id)
            ->where('user_id', $user_id)
            ->first();

        if (!$existing_client) {
            return response()->json([
                'message' => 'Client does not exist for this user.',
                'status' => 422,
            ], 422);
        }

        $project = Project::create([
            'user_id' => $user_id,
            'client_id' => $request->client_id,
            'title' => $request->title,
            'description' => isset($request->description) ? $request->description : null,
            'status' => $request->status,
            'deadline' => isset($request->deadline) ? $request->deadline : null,
        ]);

        return response()->json([
            'message' => 'Project created successfully',
            'status' => 201,
            'data' => $project,
        ], 201);
    }

    /**
     * Display the specified project for the authenticated user.
     *
     * Retrieves a single project by ID, including related user and client data,
     * only if the project belongs to the currently authenticated user.
     * Returns a JSON response with the project data or a not found error.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * Success Response (200):
     * {
     *   "data": { ...project fields... },
     *   "status": 200
     * }
     *
     * Error Response (404):
     * {
     *   "message": "Project not found.",
     *   "status": 404
     * }
     */
    public function show(string $id)
    {
        $user_id = auth()->id();

        $project = Project::where('id', $id)
            ->where('user_id', $user_id)
            ->with(['user', 'client'])
            ->first();

        if (!$project) {
            return response()->json([
                'message' => 'Project not found.',
                'status' => 404,
            ], 404);
        }

        return response()->json([
            'data' => $project,
            'status' => 200,
        ], 200);
    }

    /**
     * Update the specified project for the authenticated user.
     *
     * Updates project fields (title, description, status, deadline, client_id) if provided in the request,
     * ensuring the client exists and belongs to the user if client_id is changed.
     * Returns a JSON response with the updated project data, or errors if not found or client conflict.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * Request parameters (any of):
     * - title: string, optional
     * - description: string, optional
     * - status: string, optional, must be 'active' or 'completed'
     * - deadline: date, optional
     * - client_id: integer, optional, must exist and belong to user
     *
     * Success Response (200):
     * {
     *   "message": "Project updated successfully",
     *   "status": 200,
     *   "data": { ...project fields... }
     * }
     *
     * Error Response (404):
     * {
     *   "message": "Project not found.",
     *   "status": 404
     * }
     *
     * Error Response (422):
     * {
     *   "message": "Client does not exist for this user.",
     *   "status": 422
     * }
     */
    public function update(Request $request, string $id)
    {
        $user_id = auth()->id();

        $project = Project::where('id', $id)
            ->where('user_id', $user_id)
            ->first();

        if (!$project) {
            return response()->json([
                'message' => 'Project not found.',
                'status' => 404,
            ], 404);
        }

        if ($request->has('title') && !empty($request->title)) {
            $project->title = $request->title;
        }
        if ($request->has('description') && !empty($request->description)) {
            $project->description = $request->description;
        }
        if ($request->has('status') && in_array($request->status, ['active', 'completed'])) {
            $project->status = $request->status;
        }
        if ($request->has('deadline') && !empty($request->deadline)) {
            $project->deadline = $request->deadline;
        }
        if ($request->has('client_id') && !empty($request->client_id)) {
            $existing_client = Client::where('id', $request->client_id)
                ->where('user_id', $user_id)
                ->first();
            if (!$existing_client) {
                return response()->json([
                    'message' => 'Client does not exist for this user.',
                    'status' => 422,
                ], 422);
            }
            $project->client_id = $request->client_id;
        }

        $project->save();

        return response()->json([
            'message' => 'Project updated successfully',
            'status' => 200,
            'data' => $project,
        ], 200);
    }

    /**
     * Delete the specified project for the authenticated user.
     *
     * Deletes a project by ID only if it belongs to the currently authenticated user.
     * Also deletes all associated project time logs.
     * Returns a JSON response indicating success, not found, or error on failure.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     *
     * Success Response (200):
     * {
     *   "message": "Project deleted successfully",
     *   "status": 200
     * }
     *
     * Error Response (404):
     * {
     *   "message": "Project not found.",
     *   "status": 404
     * }
     *
     * Error Response (500):
     * {
     *   "message": "Error deleting project: ...",
     *   "status": 500
     * }
     */
    public function destroy(string $id)
    {
        $user_id = auth()->id();

        $project = Project::where('id', $id)
            ->where('user_id', $user_id)
            ->first();

        if (!$project) {
            return response()->json([
                'message' => 'Project not found.',
                'status' => 404,
            ], 404);
        }

        try {
            DB::beginTransaction();
            $project_time_logs = ProjectTimeLog::where('project_id', $id)->delete();
            $project->delete();
            DB::commit();
            return response()->json([
                'message' => 'Project deleted successfully',
                'status' => 200,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error deleting project: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }
}
