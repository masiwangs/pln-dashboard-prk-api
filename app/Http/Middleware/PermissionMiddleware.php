<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $permission)
    {
        $permission = DB::table('tbl_permission')
            ->where('permission', $permission)
            ->first();
        $hasPermission = DB::table('tbl_user_permission')
            ->where('user_id', auth()->id())
            ->where('permission_id', $permission->id)
            ->where('is_deleted', 0)
            ->limit(1)
            ->count();
        if(!$hasPermission) {
            return response([
                'status' => $permission,
                'message' => 'Unauthorized.'
            ], 401);
        }
        return $next($request);
    }
}
