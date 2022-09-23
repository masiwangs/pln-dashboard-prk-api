<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SkkiController extends Controller
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
        $skkis = DB::table('tbl_skki')
            ->where('is_deleted', 0);
        
        if($request->basket && in_array($request->basket, [1, 2, 3])) {
            $skkis = $skkis->where('basket', $request->basket);
        }
        $skkis = $skkis->get();

        foreach ($skkis as $key => $skki) {
            $skki_prks = DB::table('tbl_skki_prk')
                ->where('skki_id', $skki->id)
                ->where('is_deleted', 0)
                ->get();

            $rab_jasa_total = 0;
            $rab_material_total = 0;
            foreach ($skki_prks as $skki_prk) {
                $rab_jasa = DB::table('tbl_prk_jasa')
                    ->select(DB::raw('sum(harga) as total'))
                    ->where('prk_id', $skki_prk->prk_id)
                    ->where('is_deleted', 0)
                    ->groupBy('prk_id')
                    ->first();
                    
                $rab_material = DB::table('tbl_prk_material')
                    ->select(DB::raw('sum(harga * jumlah) as total'))
                    ->where('prk_id', $skki_prk->prk_id)
                    ->where('is_deleted', 0)
                    ->groupBy('prk_id')
                    ->first();

                if($rab_jasa) {
                    $rab_jasa_total += $rab_jasa->total;
                }

                if($rab_material) {
                    $rab_material_total += $rab_material->total;
                }
            }
            $skkis[$key]->rab_jasa['total'] = $rab_jasa_total;
            $skkis[$key]->rab_material['total'] = $rab_material_total;

        }
        
        return response()->json([
            'success' => true,
            'data' => $skkis
        ], 200);
    }

    public function store(Request $request) {
        $this->validate($request, [
            'nomor_prk_skki' => 'required|unique:tbl_skki'
        ]);

        $id = DB::table('tbl_skki')->insertGetId([
            'nomor_skki'            => $request->nomor_skki,
            'nomor_prk_skki'        => $request->nomor_prk_skki,
            'nama_project'          => $request->nama_project,
            'nomor_wbs_material'    => $request->nomor_wbs_material,
            'nomor_wbs_jasa'        => $request->nomor_wbs_jasa,
            'type'                  => $request->type,
            'basket'                => $request->basket,
            'is_deleted'            => 0,
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now()
        ]);

        $skki = DB::table('tbl_skki')->find($id);

        return response()->json([
            'success' => true,
            'data' => $skki
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

            $skki_data = [];
            $row = 2;
            while ($worksheet->getCell('A'.$row)->getValue()) {
                if(!$worksheet->getCell('A'.$row)->getValue()) {
                    continue;
                }

                // upsert skki data
                DB::table('tbl_skki')->upsert([
                        'nomor_skki'            => $worksheet->getCell('A'.$row)->getValue(),
                        'nomor_prk_skki'        => $worksheet->getCell('B'.$row)->getValue(),
                        'nama_project'          => $worksheet->getCell('C'.$row)->getValue() ?? 'Untitled',
                        'nomor_wbs_jasa'        => $worksheet->getCell('D'.$row)->getValue(),
                        'nomor_wbs_material'    => $worksheet->getCell('E'.$row)->getValue(),
                        'type'                  => $worksheet->getCell('F'.$row)->getValue(),
                        'basket'                => $worksheet->getCell('G'.$row)->getValue(),
                        'is_deleted'            => 0,
                        'created_at'            => Carbon::now(),
                        'updated_at'            => Carbon::now()
                    ], 
                    ['nomor_prk_skki'], 
                    ['nomor_skki', 'nama_project', 'nomor_wbs_jasa', 'nomor_wbs_material', 'basket', 'updated_at']
                );

                // get id
                $skki = DB::table('tbl_skki')
                    ->where('nomor_prk_skki', $worksheet->getCell('B'.$row)->getValue())
                    ->first();
                
                array_push($skki_data, $skki);
                
                // remove prk data
                DB::table('tbl_skki_prk')
                    ->where('skki_id', $skki->id)
                    ->update([
                        'is_deleted' => 1,
                        'deleted_at' => Carbon::now()
                    ]);

                // rewrite prk data
                $prks = explode(';', $worksheet->getCell('H'.$row)->getValue());
                
                foreach ($prks as $value) {
                    // clean nomor prk
                    $nomor_prk = trim($value);
                    if(!$nomor_prk) {
                        continue;
                    }
                    // cek apakah prk ada
                    $prk = DB::table('tbl_prk')
                        ->where('nomor_prk', $nomor_prk)
                        ->first();

                    if($prk) {
                        DB::table('tbl_skki_prk')
                            ->insert([
                                'skki_id' => $skki->id,
                                'prk_id' => $prk->id,
                                'is_deleted' => 0
                            ]);
                    }
                }

                $row++;
            }
            

            $this->destroyTempUpload($upload['file_path']);
            return response()->json($skki_data);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function show($skki_id) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'SKKI tidak ditemukan'
            ], 404);
        }

        $skki_prks = DB::table('tbl_skki_prk')
                ->where('skki_id', $skki->id)
                ->where('is_deleted', 0)
                ->get();

        $rab_jasa_total = 0;
        $rab_material_total = 0;

        foreach ($skki_prks as $skki_prk) {
            $rab_jasa = DB::table('tbl_prk_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('prk_id', $skki_prk->prk_id)
                ->where('is_deleted', 0)
                ->groupBy('prk_id')
                ->first();
                
            $rab_material = DB::table('tbl_prk_material')
                ->select(DB::raw('sum(harga * jumlah) as total'))
                ->where('prk_id', $skki_prk->prk_id)
                ->where('is_deleted', 0)
                ->groupBy('prk_id')
                ->first();
            
            if($rab_jasa) {
                $rab_jasa_total += $rab_jasa->total;
            }

            if($rab_material) {
                $rab_material_total += $rab_material->total;
            }
        }
        $skki->rab_jasa['total'] = $rab_jasa_total;
        $skki->rab_material['total'] = $rab_material_total;
        
        return response()->json([
            'success' => true,
            'data' => $skki
        ], 200);
    }

    public function update($skki_id, Request $request) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_skki')
            ->where('id', $skki->id)
            ->update([
                'nomor_skki'            => $request->nomor_skki,
                'nomor_prk_skki'        => $request->nomor_prk_skki,
                'nama_project'          => $request->nama_project,
                'nomor_wbs_material'    => $request->nomor_wbs_material,
                'nomor_wbs_jasa'        => $request->nomor_wbs_jasa,
                'type'                  => $request->type,
            ]);

        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
                'data' => $skki
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil disimpan',
            'data' => $skki
        ], 200);
    }

    public function destroy($skki_id) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'SKKI tidak ditemukan'
            ], 404);
        }

        // delete skki
        $update = DB::table('tbl_skki')
            ->where('id', $skki->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);
        
        // delete skki_prk
        $skki_prks = DB::table('tbl_skki_prk')
            ->where('skki_id', $skki->id)
            ->where('is_deleted', 0)
            ->get();

        foreach ($skki_prks as $skki_prk) {
            DB::table('tbl_skki_prk')
                ->where('id', $skki_prk->id)
                ->update([
                    'is_deleted' => 1,
                    'deleted_at' => Carbon::now()
                ]);
        }

        // delete catatan
        $catatans = DB::table('tbl_skki_catatan')
            ->where('skki_id', $skki->id)
            ->where('is_deleted', 0)
            ->get();
        
        foreach ($catatans as $catatan) {
            DB::table('tbl_skki_catatan')
                ->where('id', $catatan->id)
                ->update([
                    'is_deleted' => 1,
                    'deleted_at' => Carbon::now()
                ]);
        }

        return response()->json([
            'success' => true,
        ], 200);
    }

    /**
     * ========= CATATAN ==============
     */

    public function indexCatatan($skki_id) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'SKKI tidak ditemukan'
            ], 404);
        }

        $catatans = DB::table('tbl_skki_catatan')
            ->where('skki_id', $skki->id)
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

    public function storeCatatan($skki_id, Request $request) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'SKKI tidak ditemukan'
            ], 404);
        }

        $catatan_id = DB::table('tbl_skki_catatan')->insertGetId([
            'catatan' => $request->catatan,
            'skki_id' => $skki->id,
            'user_id' => auth()->id(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        $catatan = DB::table('tbl_skki_catatan')->find($catatan_id);
        $catatan->updated_at = date('d-m-Y H:i:s', strtotime($catatan->updated_at));
        $user = DB::table('tbl_user')->select('id', 'nama', 'avatar')->find($catatan->user_id);
        if(!$user->avatar) {
            $user->avatar = env('APP_URL').'/images/default-user.png';
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

    public function indexJasa($skki_id) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'SKKI tidak ditemukan'
            ], 404);
        }
        $skki_jasas = [];
        $skki_prks = DB::table('tbl_skki_prk')
            ->where('skki_id', $skki->id)
            ->where('is_deleted', 0)
            ->get();
        foreach ($skki_prks as $key => $skki_prk) {
            $jasas = DB::table('tbl_prk_jasa')
            ->where('prk_id', $skki_prk->prk_id)
            ->where('is_deleted', 0)
            ->get();
            foreach ($jasas as $key => $jasa) {
                $jasa->prk = DB::table('tbl_prk')->find($skki_prk->prk_id);
                array_push($skki_jasas, $jasa);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $skki_jasas
        ], 200);
    }

    /**
     * ========= MATERIAL ============
     */
    public function indexMaterial($skki_id) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $skki_prks = DB::table('tbl_skki_prk')
            ->where('skki_id', $skki->id)
            ->where('is_deleted', 0)
            ->get();
        
        $skki_materials = [];
        foreach ($skki_prks as $key => $skki_prk) {
            $materials = DB::table('tbl_prk_material')
                ->where('prk_id', $skki_prk->prk_id)
                ->where('is_deleted', 0)
                ->get();
            foreach ($materials as $material) {
                $material->prk = DB::table('tbl_prk')->find($skki_prk->prk_id);
                array_push($skki_materials, $material);
            }
        }


        return response()->json([
            'success' => true,
            'data' => $skki_materials
        ], 200);
    }

    /**
     * ========= LAMPIRAN ============
     */

    public function indexLampiran($skki_id, Request $request) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success'   => false,
                'message'   => 'SKKI tidak ditemukan'
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

        $lampirans = DB::table('tbl_skki_lampiran');
        if($request->search) {
            $lampirans = $lampirans->where('nama', 'like', '%'.$request->search.'%');
        }
        $lampirans = $lampirans->where('skki_id', $skki->id)
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

    public function storeLampiran($skki_id, Request $request) {
        try {
            $skki = DB::table('tbl_skki')->find($skki_id);

            if(!$skki) {
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
                    'skki_id'=> $skki->id
                ]);
            }

            DB::table('tbl_skki_lampiran')->insert($lampirans);

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

    public function destroyLampiran($skki_id, $lampiran_id, Request $request) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success'   => false,
                'message'   => 'SKKI tidak ditemukan'
            ], 200);
        }

        $lampiran = DB::table('tbl_skki_lampiran')->find($lampiran_id);

        if(!$lampiran) {
            return response()->json([
                'success'   => false,
                'message'   => 'Lampiran tidak ditemukan'
            ], 200);
        }

        $lampirans = DB::table('tbl_skki_lampiran')
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

    // ============ PRK ===============
    public function indexPrk($skki_id) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'SKKI tidak ditemukan'
            ], 404);
        }

        $skki_prks = DB::table('tbl_skki_prk')
            ->where('skki_id', $skki->id)
            ->where('is_deleted', 0)
            ->get();

        foreach ($skki_prks as $key => $skki_prk) {
            $skki_prks[$key]->prk = DB::table('tbl_prk')->find($skki_prk->prk_id);
            $skki_prks[$key]->prk->rab_jasa = DB::table('tbl_prk_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('prk_id', $skki_prk->prk_id)
                ->where('is_deleted', 0)
                ->groupBy('prk_id')
                ->first();

            $skki_prks[$key]->prk->rab_material = DB::table('tbl_prk_material')
                ->select(DB::raw('sum(harga * jumlah) as total'))
                ->where('prk_id', $skki_prk->prk_id)
                ->where('is_deleted', 0)
                ->groupBy('prk_id')
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => $skki_prks
        ], 200);
    }

    public function storePrk($skki_id, Request $request) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'SKKI tidak ditemukan'
            ], 404);
        }

        $prks = $request->all();

        $result = [];
        foreach ($prks as $prk) {
            if($prk['prk_id']) {
                $prk = DB::table('tbl_prk')->find($prk['prk_id']);
                if($prk) {
                    $skki_prk_id = DB::table('tbl_skki_prk')->insertGetId([
                        'skki_id' => $skki->id,
                        'prk_id' => $prk->id
                    ]);
    
                    $skki_prk = DB::table('tbl_skki_prk')->find($skki_prk_id);
                    array_push($result, $skki_prk);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    public function destroyPrk($skki_id, $skki_prk_id) {
        $skki = DB::table('tbl_skki')->find($skki_id);

        if(!$skki) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $skki_prk = DB::table('tbl_skki_prk')
            ->where('skki_id', $skki->id)
            ->where('id', $skki_prk_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$skki_prk) {
            return response()->json([
                'success' => false,
                'message' => 'SKKI PRK tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_skki_prk')
            ->where('id', $skki_prk->id)
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
}
