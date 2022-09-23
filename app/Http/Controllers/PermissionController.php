<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PermissionController extends Controller
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
        $permissions = DB::table('tbl_permission')
            ->where('is_deleted', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $permissions
        ], 200);
    }
}
