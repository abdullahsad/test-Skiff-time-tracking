<?php

namespace App\Http\Controllers\V1;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * Validates the incoming request data and creates a new user record.
     * Returns a JSON response with the created user data or validation errors.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Request parameters:
     * - name: string, required
     * - email: string, required, unique, valid email, max 255 chars
     * - password: string, required, min 8 chars
     *
     * Success Response (201):
     * {
     *   "message": "User created successfully",
     *   "status": 201,
     *   "data": { ...user fields... }
     * }
     *
     * Error Response (422):
     * {
     *   "message": { ...validation errors... },
     *   "status": 422
     * }
     */
    public function register(Request $request)
    {
        if (isset($request->email)) {
            $request->email = strtolower($request->email);
        }

        $rules = [
            'name' => 'required',
            'email' => 'unique:users|required|email|max:255',
            'password' => 'required|min:8',
        ];

        $messages = [
            'name.required' => 'We need to know your name!',
            'email.required' => 'We need to know your email!',
            'email.unique' => 'The email you provided is already in use!',
            'email.email' => 'Please provide a valid email address',
            'email.max' => 'Email should not be more than :max characters',
            'password.required' => 'You need a password!',
            'password.min' => 'Please provide a minimum :min characters password',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->errors()->first()) {
            return response()->json([
                'message' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'status' => 201,
            'data' => $user,
        ], 201);
    }

    /**
     * Authenticate a user and issue an API token.
     *
     * Validates the incoming request data and attempts to authenticate the user.
     * Returns a JSON response with the user data and token on success,
     * or error messages on failure.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Request parameters:
     * - email: string, required, valid email, max 255 chars
     * - password: string, required, min 8 chars
     *
     * Success Response (200):
     * {
     *   "message": "Login successful",
     *   "status": 200,
     *   "data": {
     *     "user": { ...user fields... },
     *     "token": "..."
     *   }
     * }
     *
     * Validation Error Response (422):
     * {
     *   "message": { ...validation errors... },
     *   "status": 422
     * }
     *
     * Authentication Error Response (401):
     * {
     *   "success": false,
     *   "message": "User not found" | "Invalid credentials"
     * }
     */
    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|email|max:255',
            'password' => 'required|min:8',
        ];

        $messages = [
            'email.required' => 'We need to know your email!',
            'email.email' => 'Please provide a valid email address',
            'email.max' => 'Email should not be more than :max characters',
            'password.required' => 'You need a password!',
            'password.min' => 'Please provide a minimum :min characters password',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->errors()->first()) {
            return response()->json([
                'message' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $user = User::where('email', strtolower($request->email))->first();

        if (! $user) {
            return response([
                'message' => 'User not found',
                'status' => 404,
            ], 404);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response([
                'message' => 'Invalid credentials',
                'status' => 401,
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'status' => 200,
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 200);
    }

    /**
     * Log out the authenticated user and revoke all API tokens.
     *
     * Deletes all tokens for the currently authenticated user.
     * Returns a JSON response indicating success or user not found.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Success Response (200):
     * {
     *   "message": "User logged out successfully",
     *   "status": 200
     * }
     *
     * Error Response (404):
     * {
     *   "message": "User not found",
     *   "status": 404
     * }
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->delete();
            return response()->json([
                'message' => 'User logged out successfully',
                'status' => 200,
            ], 200);
        }

        return response()->json([
            'message' => 'User not found',
            'status' => 404,
        ], 404);
    }

}
