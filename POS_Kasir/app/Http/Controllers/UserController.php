<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Support\ApiResponse;

class UserController extends Controller
{
    /**
     * Show the user management page (only for admin).
     */
    public function index(Request $request)
    {
        // Only admin can view this page – the route should be protected by admin middleware.
        return view('owner.users');
    }

    /**
     * Return a JSON list of all users (admin view).
     */
    public function list(Request $request): JsonResponse
    {
        $users = User::orderBy('id')->get();
        return ApiResponse::success(['users' => $users]);
    }

    /**
     * Store a newly created staff (cashier/owner) account.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users,username',
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,owner_makanan,owner_minuman,dapur',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid input', $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return ApiResponse::success(['user' => $user], 'User created');
    }

    /**
     * Update a staff account. Password is optional – if supplied it will be re‑hashed.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'username' => "sometimes|required|string|unique:users,username,{$id}",
            'name'     => 'sometimes|required|string',
            'email'    => "sometimes|required|email|unique:users,email,{$id}",
            'password' => 'sometimes|nullable|string|min:6',
            'role'     => 'sometimes|required|in:admin,owner_makanan,owner_minuman,dapur',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid input', $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        if (array_key_exists('password', $data) && $data['password'] !== null && $data['password'] !== '') {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']); // keep existing hash
        }

        $user->update($data);

        return ApiResponse::success(['user' => $user], 'User updated');
    }

    /**
     * Delete a staff account.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();
        return ApiResponse::success(null, 'User deleted');
    }
}
