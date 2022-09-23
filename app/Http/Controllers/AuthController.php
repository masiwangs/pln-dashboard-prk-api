<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function login(Request $request) {
        $this->validate($request, [
            'email' => 'required|email',
            'password'  => 'required'
        ]);

        $user = DB::table('tbl_user')->where('email', $request->email)->first();

        if($user && $user->is_deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan.'
            ], 401);
        }

        $credentials = $request->only(['email', 'password']);

        if(!$token = Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $permissions = DB::table('tbl_user_permission')
            ->leftJoin('tbl_permission', 'tbl_user_permission.permission_id', '=', 'tbl_permission.id')
            ->where([
                'user_id' => auth()->id(),
                'tbl_user_permission.is_deleted' => 0,
                'tbl_permission.is_deleted' => 0
            ])->get();

        $user = Auth::user();
        $user_permissions = [];
        foreach ($permissions as $value) {
            array_push($user_permissions, $value->permission);
        }
        $user->permissions = $user_permissions;

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60
        ]);
    }

    public function signup(Request $request) {
        $this->validate($request, [
            'nama' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'permissions' => 'required'
        ]);

        $adminId = DB::table('tbl_user')->insertGetId([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nama' => $request->nama
        ]);

        $permissions = [];
        foreach ($request->permissions as $value) {
            $permission = DD::table('tbl_permission')->where('permission', $value)->first();
            array_push($permissions, [
                'user_id' => $adminId,
                'permission_id' => $permission->id
            ]);
        }
        DB::table('tbl_user_permission')->insert($permissions);

        return response()->json([
            'success' => true
        ]);
    }

    public function user() {
        $user = Auth::user();
        // get avatar
        $user->avatar = $user->avatar ? env('APP_URL').'/images/avatar/'.$user->avatar : env('APP_URL').'/images/default-user.png';
        // get permissions
        $permissions = DB::table('tbl_user_permission')
            ->leftJoin('tbl_permission', 'tbl_user_permission.permission_id', '=', 'tbl_permission.id')
            ->where([
                'user_id' => $user->id,
                'tbl_user_permission.is_deleted' => 0,
                'tbl_permission.is_deleted' => 0
            ])->get();
        $user_permissions = [];
        foreach ($permissions as $value) {
            array_push($user_permissions, $value->permission);
        }
        $user->permissions = $user_permissions;

        return response()->json($user);
    }

    public function logout() {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function updateNama(Request $request) {
        $user = DB::table('tbl_user')
            ->where('id', auth()->id())
            ->update([
                'nama' => $request->nama
            ]);

        return response()->json([
            'success' => true
        ], 200);
    }

    public function updatePassword(Request $request) {
        $this->validate($request, [
            'password'          => 'required|min:6',
            'confirm_password'  => 'same:password'
        ]);

        $user = DB::table('tbl_user')
            ->where('id', auth()->id())
            ->update([
                'password' => Hash::make($request->password)
            ]);

        return response()->json([
            'success' => true
        ], 200);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }
}
