<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected function respondWithToken($token) {
        return response()->json([
            'token' => $token,
            'token_type'    => 'bearer',
            'expires_in'    => Auth::factory()->getTTL() * 60
        ], 200);
    }

    public function uploadToTemp(Request $request) {
        $this->validate($request, [
            'upload' => 'required|mimes:xlsx',
        ]);

        $file = $request->upload;
        $file_name = strtotime(Carbon::now()).$file->getClientOriginalName();
        try {
            $file->move('temp', $file_name);
            return [
                'success' => true,
                'file_path' => public_path('temp'.DIRECTORY_SEPARATOR.$file_name)
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'messae' => $th->getMessage()
            ];
        }
    }

    public function destroyTempUpload($file_path) {
        try {
            unlink($file_path);
            return true;
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }
}
