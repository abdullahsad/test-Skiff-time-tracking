<?php

namespace App\Http\Controllers\V1;
use App\Http\Controllers\Controller;
use App\Models\ProjectTimeLog;
use App\Models\Project;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;
use PDF;

class ProjectTimeLogController extends Controller
{
    /**
     * Display a paginated list of project time logs for the authenticated user.
     *
     * Filters can be applied via request parameters:
     * - project_id: Filter by specific project.
     * - client_id: Filter by specific client.
     * - start_date: Filter logs with start_time on or after this date (YYYY-MM-DD).
     * - end_date: Filter logs with end_time on or before this date (YYYY-MM-DD).
     *
     * The results include related project and client data, ordered by start_time descending.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user_id = auth()->id();
        $project_time_logs = new ProjectTimeLog();
        $project_time_logs = $project_time_logs->where('user_id', $user_id);
        if ($request->has('project_id') && !empty($request->project_id)) {
            $project_time_logs = $project_time_logs->where('project_id', $request->project_id);
        }
        if ($request->has('client_id') && !empty($request->client_id)) {
            $project_time_logs = $project_time_logs->where('client_id', $request->client_id);
        }
        if ($request->has('start_date') && !empty($request->start_date)) {
            $project_time_logs = $project_time_logs->whereDate('start_time', '>=', $request->start_date);
        }
        if ($request->has('end_date') && !empty($request->end_date)) {
            $project_time_logs = $project_time_logs->whereDate('end_time', '<=', $request->end_date);
        }
        $project_time_logs = $project_time_logs->with(['project', 'client'])
            ->orderBy('start_time', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => $project_time_logs,
            'status' => 200,
        ], 200);
    }

    /**
     * Start a new time log for a given project.
     *
     * This method checks if the authenticated user owns the specified project.
     * If the project exists and there is no ongoing time log (i.e., a log without an end time)
     * for the project, it creates a new time log entry with the current timestamp as the start time.
     * If a time log is already ongoing, it returns an error response.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request containing optional 'description' and 'tag'.
     * @param  int  $project_id  The ID of the project to start logging time for.
     * @return \Illuminate\Http\JsonResponse  JSON response indicating success or failure.
     */
    public function start(Request $request, $project_id){
        $user_id = auth()->id();
        $project = Project::where('id', $project_id)
            ->where('user_id', $user_id)
            ->first();
        if (!$project) {
            return response()->json([
                'message' => 'Project not found.',
                'status' => 404,
            ], 404);
        }

        $existing_log = ProjectTimeLog::where('project_id', $project_id)
            ->whereNull('end_time')
            ->first();

        if ($existing_log) {
            return response()->json([
                'message' => 'You have an ongoing time log for this project. Please end it before starting a new one.',
                'status' => 400,
            ], 400);
        }

        $project_time_log = ProjectTimeLog::create([
            'project_id' => $project_id,
            'user_id' => $user_id,
            'client_id' => $project->client_id,
            'start_time' => now(),
            'description' => isset($request->description) ? $request->description : '',
            'end_time' => null,
            'tag' => isset($request->tag) ? $request->tag : null,
        ]);

        return response()->json([
            'message' => 'Project time log started successfully.',
            'data' => $project_time_log,
            'status' => 201,
        ], 201);
    }

    /**
     * Stops the ongoing time log for a specific project belonging to the authenticated user.
     *
     * This method finds the project by its ID and ensures it belongs to the current user.
     * If the project exists, it searches for an ongoing time log (a log with a null end_time).
     * If such a log is found, it sets the end_time to the current timestamp, effectively stopping the timer.
     * Returns a JSON response indicating success or failure.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request instance.
     * @param  int  $project_id  The ID of the project whose time log should be stopped.
     * @return \Illuminate\Http\JsonResponse  JSON response with status and message.
     */
    public function stop(Request $request, $project_id){
        $user_id = auth()->id();
        $project = Project::where('id', $project_id)
            ->where('user_id', $user_id)
            ->first();
        if (!$project) {
            return response()->json([
                'message' => 'Project not found.',
                'status' => 404,
            ], 404);
        }

        $existing_log = ProjectTimeLog::where('project_id', $project_id)
            ->whereNull('end_time')
            ->first();

        if (!$existing_log) {
            return response()->json([
                'message' => 'No ongoing time log found for this project.',
                'status' => 400,
            ], 400);
        }

        //check any other time log exist after this time log
        $other_time_log = ProjectTimeLog::where('user_id', $user_id)
            ->where('start_time', '>', $existing_log->start_time)
            ->first();
        if ($other_time_log) {
            return response()->json([
                'message' => 'You have another ongoing time log after this one. Please stop this time log manually.',
                'status' => 400,
            ], 400);
        }

        $existing_log->end_time = now();
        $existing_log->save();

        return response()->json([
            'message' => 'Project time log stopped successfully.',
            'data' => $existing_log,
            'status' => 200,
        ], 200);
    }

    /**
     * Store a newly created project time log in storage.
     *
     * Validates the incoming request data for required fields and correct formats.
     * Ensures the project exists and belongs to the authenticated user.
     * Checks that the start and end times are not in the future and that the end time is not before the start time.
     * Prevents overlapping time logs for the same user.
     * Creates a new project time log entry if all checks pass.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request containing time log data.
     * @return \Illuminate\Http\JsonResponse      JSON response indicating success or failure with appropriate status code.
     */
    public function store(Request $request)
    {
        $rules = [
            'project_id' => 'required|exists:projects,id',
            'start_time' => 'required|date',
        ];
        $messages = [
            'project_id.required' => 'We need to know the project ID!',
            'project_id.exists' => 'The project ID provided does not exist.',
            'start_time.required' => 'We need to know the start time!',
            'start_time.date' => 'The start time must be a valid date and time.',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->errors()->first()) {
            return response()->json([
                'message' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $user_id = auth()->id();
        $project = Project::where('id', $request->project_id)
            ->where('user_id', $user_id)
            ->first();
        if (!$project) {
            return response()->json([
                'message' => 'Project not found.',
                'status' => 404,
            ], 404);
        }

        if (strtotime($request->start_time) > time()) {
            return response()->json([
                'message' => 'Start time cannot be in the future.',
                'status' => 422,
            ], 422);
        }

        if (isset($request->end_time)) {
            if (strtotime($request->end_time) < strtotime($request->start_time)) {
                return response()->json([
                    'message' => 'End time cannot be before start time.',
                    'status' => 422,
                ], 422);
            }
            if (strtotime($request->end_time) > time()) {
                return response()->json([
                    'message' => 'End time cannot be in the future.',
                    'status' => 422,
                ], 422);
            }

            $overlap = DB::select(
                'SELECT * FROM project_time_logs
                WHERE user_id = '. $user_id .'
                AND start_time < "' . $request->end_time . '"
                AND (end_time > "' . $request->start_time . '" OR end_time IS NULL)
                LIMIT 1'
            );
        }else{
            $overlap = DB::select(
                'SELECT * FROM project_time_logs
                WHERE user_id = ' . $user_id . '
                AND start_time < "' . $request->start_time . '"
                AND (end_time > "' . $request->start_time . '" OR end_time IS NULL)
                LIMIT 1'
            );
        }
        
        if ($overlap) {
            return response()->json([
                'message' => 'Time log overlaps with an existing entry.',
            ], 422);
        }

        $project_time_log = ProjectTimeLog::create([
            'project_id' => $request->project_id,
            'user_id' => $user_id,
            'client_id' => $project->client_id,
            'start_time' => $request->start_time,
            'end_time' => isset($request->end_time) ? $request->end_time : null,
            'description' => isset($request->description) ? $request->description : '',
            'tag' => isset($request->tag) ? $request->tag : null,
        ]);

        return response()->json([
            'message' => 'Project time log created successfully.',
            'data' => $project_time_log,
            'status' => 201,
        ], 201);
    }

    /**
     * Display the specified project time log for the authenticated user.
     *
     * Retrieves a project time log by its ID, including related project, client, and user data,
     * but only if it belongs to the currently authenticated user.
     * Returns a JSON response with the project time log data if found,
     * or a 404 error message if not found.
     *
     * @param string $id The ID of the project time log to retrieve.
     * @return \Illuminate\Http\JsonResponse JSON response containing the project time log data or an error message.
     */
    public function show(string $id)
    {
        $user_id = auth()->id();
        $project_time_log = ProjectTimeLog::with(['project', 'client', 'user'])
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->first();

        if (!$project_time_log) {
            return response()->json([
                'message' => 'Project time log not found.',
                'status' => 404,
            ], 404);
        }

        return response()->json([
            'data' => $project_time_log,
            'status' => 200,
        ], 200);
    }

    /**
     * Update the specified project time log for the authenticated user.
     *
     * Validates and updates the start time, end time, description, and tag of a project time log.
     * Ensures that:
     * - The project time log exists and belongs to the authenticated user.
     * - The start time is not in the future.
     * - The end time is not before the start time or in the future.
     * - The updated time log does not overlap with any existing time logs for the user.
     * - The tag, if provided, is either 'billable' or 'non-billable'.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request containing update data.
     * @param  string  $id  The ID of the project time log to update.
     * @return \Illuminate\Http\JsonResponse  JSON response indicating success or failure.
     */
    public function update(Request $request, string $id)
    {
        $user_id = auth()->id();
        $project_time_log = ProjectTimeLog::where('id', $id)
            ->where('user_id', $user_id)
            ->first();

        if (!$project_time_log) {
            return response()->json([
                'message' => 'Project time log not found.',
                'status' => 404,
            ], 404);
        }

        if ($request->has('start_time') && !empty($request->start_time)) {
            if (strtotime($request->start_time) > time()) {
                return response()->json([
                    'message' => 'Start time cannot be in the future.',
                    'status' => 422,
                ], 422);
            }
            $project_time_log->start_time = $request->start_time;
        }

        if ($request->has('end_time') && !empty($request->end_time)) {
            if (strtotime($request->end_time) < strtotime($project_time_log->start_time)) {
                return response()->json([
                    'message' => 'End time cannot be before start time.',
                    'status' => 422,
                ], 422);
            }
            if (strtotime($request->end_time) > time()) {
                return response()->json([
                    'message' => 'End time cannot be in the future.',
                    'status' => 422,
                ], 422);
            }
        }

        if ($request->has('start_time') && !empty($request->start_time) && $request->has('end_time') && !empty($request->end_time)) {
            $overlap = DB::select(
                'SELECT * FROM project_time_logs
                WHERE user_id = ' . $user_id . '
                AND id != ' . $id . '
                AND start_time < "' . $project_time_log->end_time . '"
                AND (end_time > "' . $project_time_log->start_time . '" OR end_time IS NULL)
                LIMIT 1'
            );
        }elseif ($request->has('start_time') && !empty($request->start_time) && is_null($project_time_log->end_time)) {
            $overlap = DB::select(
                'SELECT * FROM project_time_logs
                WHERE user_id = ' . $user_id . '
                AND id != ' . $id . '
                AND start_time < "' . $request->start_time . '"
                AND (end_time > "' . $request->start_time . '" OR end_time IS NULL)
                LIMIT 1'
            );
        }else{
            $overlap = null;
        }
        
        if($overlap) {
            return response()->json([
                'message' => 'Time log overlaps with an existing entry.',
                'status' => 422,
            ], 422);
        }
        
        if ($request->has('description') && !empty($request->description)) {
            $project_time_log->description = $request->description;
        }

        if ($request->has('tag') && in_array($request->tag, ['billable', 'non-billable'])) {
            $project_time_log->tag = $request->tag;
        }

        $project_time_log->save();

        return response()->json([
            'message' => 'Project time log updated successfully.',
            'data' => $project_time_log,
            'status' => 200,
        ], 200);
    }

    
    /**
     * Remove the specified project time log for the authenticated user.
     *
     * Deletes a project time log entry by its ID, ensuring that the entry belongs to the currently authenticated user.
     * Returns a JSON response indicating success or failure.
     *
     * @param  string  $id  The ID of the project time log to delete.
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $user_id = auth()->id();
        $project_time_log = ProjectTimeLog::where('id', $id)
            ->where('user_id', $user_id)
            ->first();

        if (!$project_time_log) {
            return response()->json([
                'message' => 'Project time log not found.',
                'status' => 404,
            ], 404);
        }

        $project_time_log->delete();

        return response()->json([
            'message' => 'Project time log deleted successfully.',
            'status' => 200,
        ], 200);
    }

    /**
     * Retrieve the total hours logged by the authenticated user, optionally filtered by project, client, and date range.
     *
     * This method calculates the sum of hours from the ProjectTimeLog model for the current user.
     * It supports filtering by project_id, client_id, start_date, and end_date if provided in the request.
     * If the latest time log entry does not have an end_time (i.e., the timer is still running),
     * it adds the duration from the start_time to the current time to the total hours.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request containing optional filters:
     *                                            - project_id: (int) Filter logs by project ID.
     *                                            - client_id: (int) Filter logs by client ID.
     *                                            - start_date: (string, date) Filter logs starting from this date (inclusive).
     *                                            - end_date: (string, date) Filter logs ending up to this date (inclusive).
     * @return \Illuminate\Http\JsonResponse      JSON response containing:
     *                                            - total_hours: (float) The total hours logged, rounded to 2 decimal places.
     *                                            - status: (int) HTTP status code (200).
     */
    public function getTotalHours(Request $request)
    {
        $user_id = auth()->id();
        $query = ProjectTimeLog::where('user_id', $user_id);

        if ($request->has('project_id') && !empty($request->project_id)) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('client_id') && !empty($request->client_id)) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }
        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('end_time', '<=', $request->end_date);
        }

        $total_hours = $query->sum('hours');

        $latest_log = $query->orderBy('start_time', 'desc')->first();
        if ($latest_log && is_null($latest_log->end_time)) {
            $current_time = Carbon::now();
            $start_time = Carbon::parse($latest_log->start_time);
            $total_hours += $current_time->diffInSeconds($start_time) / 3600;
        }

        return response()->json([
            'total_hours' => round($total_hours, 2),
            'status' => 200,
        ], 200);
    }

    /**
     * Generate a report of project time logs for the authenticated user.
     *
     * Retrieves report data based on the authenticated user's ID and optional start and end dates
     * provided in the request. Returns the report data as a JSON response.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request containing optional 'start_date' and 'end_date' parameters.
     * @return \Illuminate\Http\JsonResponse      The JSON response containing the report data, a message, and status code.
     */
    public function report(Request $request)
    {
        $user_id = auth()->id();

        $data = $this->getReportData($user_id, isset($request->start_date) ? $request->start_date : null, isset($request->end_date) ? $request->end_date : null);

        return response()->json([
            'data' => $data,
            'message' => 'Report generated successfully.',
            'status' => 200,
        ]);
    }

    /**
     * Exports a report as a PDF file for the authenticated user.
     *
     * Retrieves report data based on the authenticated user's ID and optional start and end dates
     * provided in the request. Generates a PDF using the 'report' view and returns it as a downloadable file.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request instance containing optional 'start_date' and 'end_date'.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse  The response containing the downloadable PDF file.
     */
    public function reportExport(Request $request)
    {
        $user_id = auth()->id();

        $data = $this->getReportData($user_id, isset($request->start_date) ? $request->start_date : null, isset($request->end_date) ? $request->end_date : null);

        $pdf = PDF::loadView('report', ['report' => $data]);
        return $pdf->download('report.pdf');
    }

    /**
     * Retrieves aggregated time log report data for a specific user within an optional date range.
     *
     * This method queries the `project_time_logs` table to calculate the total hours logged by the user,
     * grouped by date, project, and client. The results are returned as arrays grouped by date, project, and client.
     *
     * @param int $user_id The ID of the user whose time logs are to be retrieved.
     * @param string|null $start_date (optional) The start date for filtering logs (inclusive). Format: Y-m-d or any Carbon-parsable date string.
     * @param string|null $end_date (optional) The end date for filtering logs (inclusive). Format: Y-m-d or any Carbon-parsable date string.
     * @return array{
     *     by_date: array<int, array{date: string, total_hours: float}>,
     *     by_project: array<int, array{project_id: int, hours: float}>,
     *     by_client: array<int, array{client_id: int, hours: float}>
     * }
     */
    protected function getReportData($user_id, $start_date = null, $end_date = null){
        if(!empty($start_date)) {
            $start_date = Carbon::parse($start_date)->startOfDay();
        } 

        if(!empty($end_date)) {
            $end_date = Carbon::parse($end_date)->endOfDay();
        }
        
        $query = "
            SELECT 
                DATE(start_time) as date,
                SUM(hours) as total_hours,
                project_id,
                client_id
            FROM project_time_logs
            WHERE user_id = " . $user_id;
        if (isset($start_date)) {
            $query .= ' AND start_time >= "'.$start_date.'" ';
        }
        if (isset($end_date)) {
            $query .= ' AND start_time <= "'.$end_date.'" ';
        }
        $query .= "
            AND end_time IS NOT NULL
            GROUP BY DATE(start_time), project_id, client_id
            ORDER BY DATE(start_time) DESC
        ";
        $data = DB::select($query);
        
        $by_date = [];
        $by_project = [];
        $by_client = [];

        foreach ($data as $row) {
            $date = $row->date;
            if (!isset($by_date[$date])) {
                $by_date[$date] = [
                    'date' => $date,
                    'total_hours' => 0,
                ];
            }
            $by_date[$date]['total_hours'] += $row->total_hours;

            if (!isset($by_project[$row->project_id])) {
                $by_project[$row->project_id] = [
                    'project_id' => $row->project_id,
                    'hours' => 0,
                ];
            }
            $by_project[$row->project_id]['hours'] += $row->total_hours;

            if (!isset($by_client[$row->client_id])) {
                $by_client[$row->client_id] = [
                    'client_id' => $row->client_id,
                    'hours' => 0,
                ];
            }
            $by_client[$row->client_id]['hours'] += $row->total_hours;
        }
        return [
            'by_date' => array_values($by_date),
            'by_project' => array_values($by_project),
            'by_client' => array_values($by_client),
        ];
    }
}
