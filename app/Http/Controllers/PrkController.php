<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PrkController extends Controller
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
        $prks = DB::table('tbl_prk')
            ->where('is_deleted', 0);
        
        if($request->basket && in_array($request->basket, [1, 2, 3])) {
            $prks = $prks->where('basket', $request->basket);
        }
        $prks = $prks->get();

        foreach ($prks as $key => $prk) {
            $prks[$key]->rab_jasa = DB::table('tbl_prk_jasa')
                ->select(DB::raw('sum(harga) as rab_jasa'))
                ->where('prk_id', $prk->id)
                ->where('is_deleted', 0)
                ->groupBy('prk_id')
                ->first();

            $prks[$key]->rab_material = DB::table('tbl_prk_material')
                ->select(DB::raw('sum(harga * jumlah) as rab_material'))
                ->where('prk_id', $prk->id)
                ->where('is_deleted', 0)
                ->groupBy('prk_id')
                ->first();
        }
        
        return response()->json([
            'success' => true,
            'data' => $prks
        ], 200);
    }

    public function store(Request $request) {
        $id = DB::table('tbl_prk')->insertGetId([
            'nomor_prk' => $request->nomor_prk,
            'nama_project' => $request->nama_project,
            'nomor_lot' => $request->nomor_lot,
            'prioritas' => $request->prioritas,
            'basket' => $request->basket,
            'is_deleted' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        $prk = DB::table('tbl_prk')->find($id);

        return response()->json([
            'success' => true,
            'data' => $prk
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

            $prk_data = [];
            $row = 2;
            while ($worksheet->getCell('A'.$row)->getValue()) {
                array_push($prk_data, [
                    'nama_project' => $worksheet->getCell('A'.$row)->getValue(),
                    'nomor_prk' => $worksheet->getCell('B'.$row)->getValue(),
                    'nomor_lot' => $worksheet->getCell('C'.$row)->getValue(),
                    'prioritas' => $worksheet->getCell('D'.$row)->getValue(),
                    'basket' => $worksheet->getCell('E'.$row)->getValue(),
                    'updated_at' => Carbon::now()
                ]);

                $row++;
            }
            DB::table('tbl_prk')->upsert(
                $prk_data, 
                ['nomor_prk'], 
                ['nama_project', 'nomor_lot', 'prioritas', 'basket', 'updated_at']
            );

            $this->destroyTempUpload($upload['file_path']);
            return response()->json($prk_data);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function show($prk_id) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $prk->rab_jasa = DB::table('tbl_prk_jasa')
            ->select(DB::raw('sum(harga) as rab_jasa'))
            ->where('prk_id', $prk->id)
            ->where('is_deleted', 0)
            ->groupBy('prk_id')
            ->first();

        $prk->rab_material = DB::table('tbl_prk_material')
            ->select(DB::raw('sum(harga * jumlah) as rab_material'))
            ->where('prk_id', $prk->id)
            ->where('is_deleted', 0)
            ->groupBy('prk_id')
            ->first();
        
        return response()->json([
            'success' => true,
            'data' => $prk
        ], 200);
    }

    public function update($prk_id, Request $request) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_prk')
            ->where('id', $prk->id)
            ->update([
                'nomor_prk' => $request->nomor_prk,
                'nama_project' => $request->nama_project,
                'nomor_lot' => $request->nomor_lot,
                'prioritas' => $request->prioritas,
            ]);

        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
                'data' => $prk
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil disimpan',
            'data' => $prk
        ], 200);
    }

    public function destroy($prk_id) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_prk')
            ->where('id', $prk->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);
    }

    /**
     * ========= CATATAN ==============
     */

    public function indexCatatan($prk_id) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $catatans = DB::table('tbl_prk_catatan')
            ->where('prk_id', $prk->id)
            ->where('is_deleted', 0)
            ->orderBy('id', 'asc')
            ->get();
        
        foreach ($catatans as $key => $catatan) {
            $user = DB::table('tbl_user')->select('id', 'nama', 'avatar')->find($catatan->user_id);
            if(!$user->avatar) {
                $user->avatar = env('APP_URL').'/images/default-user.png';
            }

            $catatans[$key]->user = $user;
        }

        return response()->json([
            'success' => true,
            'data' => $catatans
        ], 200);
    }

    public function storeCatatan($prk_id, Request $request) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $catatan_id = DB::table('tbl_prk_catatan')->insertGetId([
            'catatan' => $request->catatan,
            'prk_id' => $prk->id,
            'user_id' => auth()->id(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        $catatan = DB::table('tbl_prk_catatan')->find($catatan_id);
        $catatan->updated_at = date('d-m-Y H:i:s', strtotime($catatan->updated_at));
        $user = DB::table('tbl_user')->select('id', 'nama', 'avatar')->find($catatan->user_id);
        if(!$user->avatar) {
            $user->avatar = 'http://localhost:8000/images/default-user.png';
        }
        $catatan->user = $user;

        return response()->json([
            'success' => true,
            'data' => $catatan
        ], 200);
    }
    
    /**
     * ========= JASA ==============
     */

    public function indexJasa($prk_id) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $jasas = DB::table('tbl_prk_jasa')
            ->where('prk_id', $prk->id)
            ->where('is_deleted', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jasas
        ], 200);
    }

    public function storeJasa($prk_id, Request $request) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $jasas = $request->all();

        $result = [];
        foreach ($jasas as $jasa) {
            if($jasa['nama_jasa'] && $jasa['harga']) {
                $jasa_id = DB::table('tbl_prk_jasa')->insertGetId([
                    'nama_jasa' => $jasa['nama_jasa'],
                    'harga' => (int)$jasa['harga'],
                    'prk_id' => $prk->id
                ]);

                $jasa = DB::table('tbl_prk_jasa')->find($jasa_id);
                array_push($result, $jasa);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    public function updateJasa($prk_id, $jasa_id, Request $request) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $jasa = DB::table('tbl_prk_jasa')
            ->where('prk_id', $prk->id)
            ->where('id', $jasa_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$jasa) {
            return response()->json([
                'success' => false,
                'message' => 'Jasa tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_prk_jasa')
            ->where('id', $jasa->id)
            ->update([
                'nama_jasa' => $request->nama_jasa,
                'harga' => $request->harga,
            ]);

        $jasa = DB::table('tbl_prk_jasa')->find($jasa->id);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
                'data' => $prk
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil disimpan',
            'data' => $jasa
        ], 200);
    }

    public function destroyJasa($prk_id, $jasa_id) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $jasa = DB::table('tbl_prk_jasa')
            ->where('prk_id', $prk->id)
            ->where('id', $jasa_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$jasa) {
            return response()->json([
                'success' => false,
                'message' => 'Jasa tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_prk_jasa')
            ->where('id', $jasa->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil dihapus',
        ], 200);
    }

    /**
     * ========= MATERIAL ============
     */
    public function indexMaterial($prk_id) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $materials = DB::table('tbl_prk_material')
            ->where('prk_id', $prk->id)
            ->where('is_deleted', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $materials
        ], 200);
    }

    public function storeMaterial($prk_id, Request $request) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $materials = $request->all();

        $result = [];
        foreach ($materials as $material) {
            if($material['id'] && $material['jumlah']) {
                $material_base = DB::table('tbl_material')->find($material['id']);
                if($material_base) {
                    $material_id = DB::table('tbl_prk_material')->insertGetId([
                        'kode_normalisasi' => $material_base->kode_normalisasi,
                        'nama_material' => $material_base->nama_material,
                        'satuan' => $material_base->satuan,
                        'harga' => $material_base->harga,
                        'jumlah' => $material['jumlah'],
                        'prk_id' => $prk->id
                    ]);
    
                    $material = DB::table('tbl_prk_material')->find($material_id);
                    array_push($result, $material);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    public function updateMaterial($prk_id, $material_id, Request $request) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $material = DB::table('tbl_prk_material')
            ->where('prk_id', $prk->id)
            ->where('id', $material_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_prk_material')
            ->where('id', $material->id)
            ->update([
                'jumlah' => (int)$request->jumlah,
            ]);

        $material = DB::table('tbl_prk_material')->find($material->id);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil disimpan',
            'data' => $material
        ], 200);
    }

    public function destroyMaterial($prk_id, $material_id) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $material = DB::table('tbl_prk_material')
            ->where('prk_id', $prk->id)
            ->where('id', $material_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_prk_material')
            ->where('id', $material->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil dihapus',
        ], 200);
    }

    /**
     * ========= LAMPIRAN ============
     */

    public function indexLampiran($prk_id, Request $request) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success'   => false,
                'message'   => 'PRK tidak ditemukan'
            ], 200);
        }

        $page = $request->page;
        if($page) {
            $page = preg_replace('/[^0-9]/i', '', $page);
            if(!$page) {
                $page = 1;
            }
        } else {
            $page = 1;
        }
        $offset = env('PER_PAGE') * ($page - 1);

        $lampirans = DB::table('tbl_prk_lampiran');
        if($request->search) {
            $lampirans = $lampirans->where('nama', 'like', '%'.$request->search.'%');
        }
        $lampirans = $lampirans->where('prk_id', $prk->id)
            ->where('is_deleted', 0)
            ->skip($offset)
            ->take(env('PER_PAGE'))
            ->get();

        foreach ($lampirans as $key => $lampiran) {
            switch ($lampiran->type) {
                case 'png':
                    $thumbnail = env('APP_URL').'/images/extensions/image.png';
                    break;
                case 'xlsx':
                    $thumbnail = env('APP_URL').'/images/extensions/xls.png';
                    break;
                case 'docx':
                    $thumbnail = env('APP_URL').'/images/extensions/doc.png';
                    break;
                case 'pptx':
                    $thumbnail = env('APP_URL').'/images/extensions/ppt.png';
                    break;
                case 'pdf':
                    $thumbnail = env('APP_URL').'/images/extensions/pdf.png';
                    break;
                
                default:
                    $thumbnail = env('APP_URL').'/images/extensions/image.png';
                    break;
            }
            $lampirans[$key]->thumbnail = $thumbnail;
            $lampirans[$key]->uri = env('APP_URL').'/uploads/'.$lampiran->uri;
        }

        return response()->json([
            'success'   => true,
            'data'      => $lampirans
        ], 200);
    }

    public function storeLampiran($prk_id, Request $request) {
        try {
            $prk = DB::table('tbl_prk')->find($prk_id);

            if(!$prk) {
                return response()->json([
                    'success'   => false,
                    'message'   => 'PRK tidak ditemukan'
                ], 200);
            }

            $lampirans = [];
            foreach ($request->lampirans as $key => $value) {
                $file_name = strtotime(Carbon::now()).'-'.$value->getClientOriginalName();
                $value->move('uploads', $file_name);
                array_push($lampirans, [
                    'nama'  => $value->getClientOriginalName(),
                    'uri'   => $file_name,
                    'type'  => $value->getClientOriginalExtension(),
                    'prk_id'=> $prk->id
                ]);
            }

            DB::table('tbl_prk_lampiran')->insert($lampirans);

            return response()->json([
                'success' => true
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'files' => $th->getMessage()
            ], 500);
        }
    }

    public function destroyLampiran($prk_id, $lampiran_id, Request $request) {
        $prk = DB::table('tbl_prk')->find($prk_id);

        if(!$prk) {
            return response()->json([
                'success'   => false,
                'message'   => 'PRK tidak ditemukan'
            ], 200);
        }

        $lampiran = DB::table('tbl_prk_lampiran')->find($lampiran_id);

        if(!$lampiran) {
            return response()->json([
                'success'   => false,
                'message'   => 'Lampiran tidak ditemukan'
            ], 200);
        }

        $lampirans = DB::table('tbl_prk_lampiran')
            ->where('id', $lampiran->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);

        return response()->json([
            'success'   => true,
            'data'      => $lampirans
        ], 200);
    }
}
