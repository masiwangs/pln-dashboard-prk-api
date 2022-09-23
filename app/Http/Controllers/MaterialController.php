<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MaterialController extends Controller
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
        $page = (int)$request->page;
        $perpage = (int)$request->perpage;
        $sortby = $request->sortby;
        $sortdesc = $request->sortdesc;
        $search = $request->search;
        
        $offset = $perpage * ($page - 1);
        $materials = DB::table('tbl_material')->where('is_deleted', 0);
        $materials_count = DB::table('tbl_material')->where('is_deleted', 0);

        if($sortby && in_array($sortby, ['kode_normalisasi', 'nama_material', 'satuan', 'harga'])) {
            if($sortdesc == false) {
                $materials = $materials->orderBy($sortby, 'asc');
            } else {
                $materials = $materials->orderBy($sortby, 'desc');
            }
        }

        if($search) {
            $materials = $materials->where(function($query) use($search) {
                $query->where('nama_material', 'like', '%'.$search.'%')
                      ->orWhere('kode_normalisasi', 'like', '%'.$search.'%');
            });

            $materials_count = $materials_count->where(function($query) use($search) {
                $query->where('nama_material', 'like', '%'.$search.'%')
                      ->orWhere('kode_normalisasi', 'like', '%'.$search.'%');
            });
        }

        if($page && $perpage) {
            $materials = $materials->skip((int)$offset)->take((int)$perpage);
        }

        $materials =  $materials->get();

        $materials_count = $materials_count->count();


        return response()->json([
            'success' => true,
            'data' => $materials,
            'count' => $materials_count
        ]);
    }

    public function store(Request $request) {
        foreach ($request->all() as $request_material) {
            if($request_material['kode_normalisasi']) {
                $material = DB::table('tbl_material')->insert([
                    'kode_normalisasi' => $request_material['kode_normalisasi'],
                    'nama_material' => $request_material['nama_material'],
                    'satuan' => $request_material['satuan'],
                    'harga' => $request_material['harga'],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $request->all()
        ]);
    }

    public function update($material_id, Request $request) {
        $material = DB::table('tbl_material')->find($material_id);

        if(!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan'
            ], 404);
        }

        $updated = DB::table('tbl_material')
            ->where('id', $material->id)
            ->update([
                'harga' => (int)$request->harga
            ]);

        if(!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan'
            ], 500);
        }

        $material = DB::table('tbl_material')->find($material->id);

        return response()->json([
            'success' => true,
            'data' => $material
        ]);
    }

    public function destroy($material_id) {
        $material = DB::table('tbl_material')->find($material_id);

        if(!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan'
            ], 404);
        }

        $deleted = DB::table('tbl_material')
            ->where('id', $material->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);

        if(!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan'
            ], 500);
        }

        return response()->json([
            'success' => true,
        ], 200);
    }

    public function import(Request $request) {
        try {
            $upload = $this->uploadToTemp($request);
            if(!$upload['success']) {
                return response()->json($upload, 400);
            }
            $spreadsheet = IOFactory::load($upload['file_path']);
            $worksheet = $spreadsheet->getActiveSheet();

            $material_data = [];
            $row = 2;
            while ($worksheet->getCell('A'.$row)->getValue()) {
                array_push($material_data, [
                    'kode_normalisasi' => $worksheet->getCell('A'.$row)->getValue(),
                    'nama_material' => $worksheet->getCell('B'.$row)->getValue(),
                    'deskripsi' => $worksheet->getCell('C'.$row)->getValue(),
                    'satuan' => $worksheet->getCell('D'.$row)->getValue(),
                    'harga' => $worksheet->getCell('E'.$row)->getValue(),
                    'updated_at' => Carbon::now()
                ]);

                $row++;
            }
            DB::table('tbl_material')->upsert(
                $material_data, 
                ['kode_normalisasi'], 
                ['nama_material', 'deskripsi', 'satuan', 'harga']
            );

            $this->destroyTempUpload($upload['file_path']);
            return response()->json($material_data);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return response()->json($e->getMessage());
        }
    }
}
