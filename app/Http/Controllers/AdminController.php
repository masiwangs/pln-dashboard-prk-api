<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
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

    public function index(Request $request) {
        $admins = DB::table('tbl_user');
        if($request->search) {
            $admins = $admins->where('nama', 'like', '%'.$request->search.'%');
        }
        $admins = $admins->where('is_deleted', 0)
            ->get();

        foreach ($admins as $key => $admin) {
            $admins[$key]->permissions = DB::table('tbl_user_permission')
                ->where('user_id', $admin->id)
                ->where('tbl_user_permission.is_deleted', 0)
                ->leftJoin('tbl_permission', 'tbl_user_permission.permission_id', '=', 'tbl_permission.id')
                ->select('tbl_permission.permission')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $admins
        ], 200);
    }

    public function store(Request $request) {
        $this->validate($request, [
            'nama' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'permissions' => 'required'
        ]);

        $adminExist = DB::table('tbl_user')->where('email', $request->email)->first();
        if($adminExist) {
            if($adminExist->is_deleted == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email telah terdaftar sebagai admin terhapus.'
                ], 400);
            }
            return response()->json([
                'success' => false,
                'message' => 'Email telah terdaftar sebagai admin.'
            ], 400);
        }

        $adminId = DB::table('tbl_user')->insertGetId([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nama' => $request->nama
        ]);

        $permissions = [];
        foreach ($request->permissions as $value) {
            $permission = DB::table('tbl_permission')->where('permission', $value)->first();
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

    public function update($admin_id, Request $request) {
        $admin = DB::table('tbl_user')->find($admin_id);

        if(!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin tidak ditemukan.'
            ], 404);
        }

        // delete existing permissions
        DB::table('tbl_user_permission')
            ->where('user_id', $admin->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);
        foreach ($request->permissions as $value) {
            $permission = DB::table('tbl_permission')->where('permission', $value)->first();
            DB::table('tbl_user_permission')
                ->insert([
                    'user_id' => $admin->id,
                    'permission_id' => $permission->id
                ]);
        }

        return response()->json([
            'success' => true,
        ], 200);
    }

    public function destroy($admin_id) {
        $admin = DB::table('tbl_user')->find($admin_id);

        if(!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin tidak ditemukan'
            ], 404);
        }

        $delete = DB::table('tbl_user')
            ->where('id', $admin->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);

        if(!$delete) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil dihapus.'
        ], 200);
    }

    public function updatePassword($admin_id, Request $request) {
        $this->validate($request, [
            'password'          => 'required|min:8',
        ]);

        $userExist = DB::table('tbl_user')->find($admin_id);
        if(!$userExist) {
            return response()->json([
                'status' => false,
                'message' => 'Admin tidak ditemukan'
            ], 404);
        }

        $user = DB::table('tbl_user')
            ->where('id', $admin_id)
            ->update([
                'password' => Hash::make($request->password)
            ]);

        return response()->json([
            'success' => true
        ], 200);
    }
}
