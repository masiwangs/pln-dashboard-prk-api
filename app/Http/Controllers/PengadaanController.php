<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PengadaanController extends Controller
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
        $pengadaans = DB::table('tbl_pengadaan')
            ->where('is_deleted', 0);
        
        if($request->basket && in_array($request->basket, [1, 2, 3])) {
            $pengadaans = $pengadaans->where('basket', $request->basket);
        }
        $pengadaans = $pengadaans->get();

        foreach ($pengadaans as $key => $pengadaan) {
            $pengadaans[$key]->wbs_jasa = DB::table('tbl_pengadaan_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('pengadaan_id', $pengadaan->id)
                ->where('is_deleted', 0)
                ->groupBy('pengadaan_id')
                ->first();

            $pengadaans[$key]->wbs_material = DB::table('tbl_pengadaan_material')
                ->select(DB::raw('sum(harga * jumlah) as total'))
                ->where('pengadaan_id', $pengadaan->id)
                ->where('is_deleted', 0)
                ->groupBy('pengadaan_id')
                ->first();
        }
        
        return response()->json([
            'success' => true,
            'data' => $pengadaans
        ], 200);
    }

    public function store(Request $request) {
        $id = DB::table('tbl_pengadaan')->insertGetId([
            'nodin' => $request->nodin,
            'tanggal_nodin' => $request->tanggal_nodin,
            'nama_project' => $request->nama_project,
            'nomor_pr_jasa' => $request->nomor_pr_jasa,
            'type' => $request->type,
            'basket' => $request->basket,
            'is_deleted' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        $pengadaan = DB::table('tbl_pengadaan')->find($id);

        foreach ($request->skkis as $request_skki) {
            if($request_skki) {
                DB::table('tbl_pengadaan_skki')
                    ->insert([
                        'pengadaan_id' => $pengadaan->id,
                        'skki_id' => $request_skki
                    ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $pengadaan
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

            $pengadaan_data = [];
            $row = 2;
            while ($worksheet->getCell('A'.$row)->getValue()) {
                if(!$worksheet->getCell('A'.$row)->getValue()) {
                    continue;
                }

                if(strtoupper($worksheet->getCell('E'.$row)->getValue()) == 'PROSES') {
                    $status = 1;
                } else {
                    $status = 2;
                }

                // upsert pengadaan data
                DB::table('tbl_pengadaan')->upsert([
                        'nodin'                 => $worksheet->getCell('A'.$row)->getValue(),
                        'tanggal_nodin'         => $worksheet->getCell('B'.$row)->getValue(),
                        'nomor_pr_jasa'         => $worksheet->getCell('C'.$row)->getValue(),
                        'nama_project'          => $worksheet->getCell('D'.$row)->getValue() ?? 'Untitled',
                        'status'                => $status,
                        'type'                  => $worksheet->getCell('F'.$row)->getValue(),
                        'basket'                => $worksheet->getCell('G'.$row)->getValue(),
                        'is_deleted'            => 0,
                        'created_at'            => Carbon::now(),
                        'updated_at'            => Carbon::now()
                    ], 
                    ['nodin'], 
                    ['tanggal_nodin', 'nomor_pr_jasa', 'nama_project', 'status', 'basket', 'updated_at']
                );

                // get id
                $pengadaan = DB::table('tbl_pengadaan')
                    ->where('nodin', $worksheet->getCell('A'.$row)->getValue())
                    ->first();
                
                array_push($pengadaan_data, $pengadaan);
                
                // remove skki data
                DB::table('tbl_pengadaan_skki')
                    ->where('pengadaan_id', $pengadaan->id)
                    ->update([
                        'is_deleted' => 1,
                        'deleted_at' => Carbon::now()
                    ]);

                // rewrite skki data
                $skkis = explode(';', $worksheet->getCell('H'.$row)->getValue());
                
                foreach ($skkis as $value) {
                    // clean nomor skki
                    $nomor_prk_skki = trim($value);
                    if(!$nomor_prk_skki) {
                        continue;
                    }
                    // cek apakah prk ada
                    $skki = DB::table('tbl_skki')
                        ->where('nomor_prk_skki', $nomor_prk_skki)
                        ->first();

                    if($skki) {
                        DB::table('tbl_pengadaan_skki')
                            ->insert([
                                'pengadaan_id' => $pengadaan->id,
                                'skki_id' => $skki->id,
                            ]);
                    }
                }

                $row++;
            }
            

            $this->destroyTempUpload($upload['file_path']);
            return response()->json($pengadaan_data);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function show($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $pengadaan->wbs_jasa = DB::table('tbl_pengadaan_jasa')
            ->select(DB::raw('sum(harga) as total'))
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->first();

        $pengadaan->wbs_material = DB::table('tbl_pengadaan_material')
            ->select(DB::raw('sum(harga*jumlah) as total'))
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->first();
        
        return response()->json([
            'success' => true,
            'data' => $pengadaan
        ], 200);
    }

    public function update($pengadaan_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        // cek apa update status
        if($request->status) {
            // jika status == 1, cek apa ada kontrak
            if($request->status == 1) {
                $kontrak = DB::table('tbl_kontrak')
                    ->where('pengadaan_id', $pengadaan->id)
                    ->where('is_deleted', 0)
                    ->first();

                // jika ada, lempar pesan error
                if($kontrak) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak dapat diubah. Terdapat kontrak menggunakan pengadaan ini.'
                    ], 400);
                }
            }
        }

        $update = DB::table('tbl_pengadaan')
            ->where('id', $pengadaan->id)
            ->update([
                'nodin' => $request->nodin,
                'tanggal_nodin' => $request->tanggal_nodin,
                'nama_project' => $request->nama_project,
                'nomor_pr_jasa' => $request->nomor_pr_jasa,
                'status' => $request->status,
                'type' => $request->type,
            ]);

        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan->id);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
                'data' => $pengadaan
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil disimpan',
            'data' => $pengadaan
        ], 200);
    }

    public function destroy($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        // cek apa ada kontrak, kalo ada, kasih pesan error
        $kontrak = DB::table('tbl_kontrak')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->first();

        if($kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat dihapus. Terdapat kontrak menggunakan pengadaan ini.'
            ], 400);
        }

        $update = DB::table('tbl_pengadaan')
            ->where('id', $pengadaan->id)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);

        // hapus pengadaan skki
        $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);

        // hapus material
        $pengadaan_materials = DB::table('tbl_pengadaan_material')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);
            
        // hapus jasa
        $pengadaan_jasas = DB::table('tbl_pengadaan_jasa')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);

        // hapus catatan
        $pengadaan_catatan = DB::table('tbl_pengadaan_catatan')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->update([
                'is_deleted' => 1,
                'deleted_at' => Carbon::now()
            ]);
        
        return response()->json([
            'success' => true,
        ], 200);
    }

    /**
     * ========= CATATAN ==============
     */

    public function indexCatatan($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $catatans = DB::table('tbl_pengadaan_catatan')
            ->where('pengadaan_id', $pengadaan->id)
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

    public function storeCatatan($pengadaan_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $catatan_id = DB::table('tbl_pengadaan_catatan')->insertGetId([
            'catatan' => $request->catatan,
            'pengadaan_id' => $pengadaan->id,
            'user_id' => auth()->id(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        $catatan = DB::table('tbl_pengadaan_catatan')->find($catatan_id);
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

    public function indexJasa($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $jasas = DB::table('tbl_pengadaan_jasa')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jasas
        ], 200);
    }

    public function wbsJasa($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        // hitung total wbs jasa
        $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->get();
        
        $total_wbs_jasa = 0;
        foreach ($pengadaan_skkis as $pengadaan_skki) {
            $skki_prks = DB::table('tbl_skki_prk')
                ->where('skki_id', $pengadaan_skki->skki_id)
                ->where('is_deleted', 0)
                ->get();
            
            foreach ($skki_prks as $skki_prk) {
                $jasa = DB::table('tbl_prk_jasa')
                    ->select(DB::raw('sum(harga) as total'))
                    ->where('prk_id', $skki_prk->prk_id)
                    ->where('is_deleted', 0)
                    ->groupBy('prk_id')
                    ->first();
                if($jasa) {
                    $total_wbs_jasa += $jasa->total;
                }
            }
        }

        // hitung used wbs jasa
        $pengadaan_jasas = DB::table('tbl_pengadaan_jasa')
            ->select(DB::raw('sum(harga) as total'))
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total_wbs_jasa,
                'used'  => $pengadaan_jasas->total ?? 0,
                'rest'  => $total_wbs_jasa - ($pengadaan_jasas->total ?? 0)
            ]
        ], 200);
    }

    public function storeJasa($pengadaan_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $jasas = $request->all();

        $result = [];
        foreach ($jasas as $jasa) {
            if($jasa['nama_jasa'] && $jasa['harga']) {
                $jasa_id = DB::table('tbl_pengadaan_jasa')->insertGetId([
                    'nama_jasa' => $jasa['nama_jasa'],
                    'harga' => (int)$jasa['harga'],
                    'pengadaan_id' => $pengadaan->id
                ]);

                $jasa = DB::table('tbl_pengadaan_jasa')->find($jasa_id);
                array_push($result, $jasa);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    public function updateJasa($pengadaan_id, $jasa_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $jasa = DB::table('tbl_pengadaan_jasa')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('id', $jasa_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$jasa) {
            return response()->json([
                'success' => false,
                'message' => 'Jasa tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pengadaan_jasa')
            ->where('id', $jasa->id)
            ->update([
                'nama_jasa' => $request->nama_jasa,
                'harga' => $request->harga,
            ]);

        $jasa = DB::table('tbl_pengadaan_jasa')->find($jasa->id);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil disimpan',
            'data' => $jasa
        ], 200);
    }

    public function destroyJasa($pengadaan_id, $jasa_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $jasa = DB::table('tbl_pengadaan_jasa')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('id', $jasa_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$jasa) {
            return response()->json([
                'success' => false,
                'message' => 'Jasa tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pengadaan_jasa')
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
    public function indexMaterial($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $materials = DB::table('tbl_pengadaan_material')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->get();
        
        foreach($materials as $key => $material) {
            $materials[$key]->prk = DB::table('tbl_prk')->find($material->prk_id);
        }

        return response()->json([
            'success' => true,
            'data' => $materials
        ], 200);
    }

    public function wbsMaterial($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        // hitung total wbs jasa
        $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->get();
        
        $total_wbs_material = 0;
        foreach ($pengadaan_skkis as $pengadaan_skki) {
            $skki_prks = DB::table('tbl_skki_prk')
                ->where('skki_id', $pengadaan_skki->skki_id)
                ->where('is_deleted', 0)
                ->get();
            
            foreach ($skki_prks as $skki_prk) {
                $material = DB::table('tbl_prk_material')
                    ->select(DB::raw('sum(harga*jumlah) as total'))
                    ->where('prk_id', $skki_prk->prk_id)
                    ->where('is_deleted', 0)
                    ->groupBy('prk_id')
                    ->first();
                
                if($material) {
                    $total_wbs_material += $material->total;
                }
            }
        }

        // hitung used wbs jasa
        $pengadaan_materials = DB::table('tbl_pengadaan_material')
            ->select(DB::raw('sum(harga*jumlah) as total'))
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total_wbs_material,
                'used'  => $pengadaan_materials->total ?? 0,
                'rest'  => $total_wbs_material - ($pengadaan_materials->total ?? 0)
            ]
        ], 200);
    }

    public function stokMaterial($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->get();

        $result = [];
        foreach ($pengadaan_skkis as $pengadaan_skki) {
            $skki_prks = DB::table('tbl_skki_prk')
                ->where('skki_id', $pengadaan_skki->skki_id)
                ->where('is_deleted', 0)
                ->get();
            
            foreach ($skki_prks as $skki_prk) {
                $prk = DB::table('tbl_prk')->find($skki_prk->prk_id);

                $materials = DB::table('tbl_prk_material')
                    ->where('prk_id', $skki_prk->prk_id)
                    ->where('is_deleted', 0)
                    ->get();

                foreach ($materials as $key => $material) {
                    $materials[$key]->prk = $prk;
                    array_push($result, $material);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    public function storeMaterial($pengadaan_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $materials = $request->all();

        $result = [];
        foreach ($materials as $material) {
            if($material['id'] && $material['jumlah']) {
                $material_prk = DB::table('tbl_prk_material')->find($material['id']);
                if($material_prk) {
                    $material_id = DB::table('tbl_pengadaan_material')->insertGetId([
                        'kode_normalisasi'  => $material_prk->kode_normalisasi,
                        'nama_material'     => $material_prk->nama_material,
                        'satuan'            => $material_prk->satuan,
                        'harga'             => $material_prk->harga,
                        'jumlah'            => $material['jumlah'],
                        'pengadaan_id'      => $pengadaan->id,
                        'prk_id'            => $material_prk->prk_id
                    ]);
    
                    $material = DB::table('tbl_pengadaan_material')->find($material_id);
                    array_push($result, $material);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    public function updateMaterial($pengadaan_id, $material_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'PRK tidak ditemukan'
            ], 404);
        }

        $material = DB::table('tbl_pengadaan_material')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('id', $material_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pengadaan_material')
            ->where('id', $material->id)
            ->update([
                'jumlah' => (int)$request->jumlah,
            ]);

        $material = DB::table('tbl_pengadaan_material')->find($material->id);

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

    public function destroyMaterial($pengadaan_id, $material_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $material = DB::table('tbl_pengadaan_material')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('id', $material_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pengadaan_material')
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

    public function indexLampiran($pengadaan_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success'   => false,
                'message'   => 'Pengadaan tidak ditemukan'
            ], 200);
        }

        $page = $request->page;
        if($page) {
            $page = preg_replace('/[^0-9]/i', '', $page);
        } else {
            $page = 1;
        }
        $offset = env('PER_PAGE') * ($page - 1);

        $lampirans = DB::table('tbl_pengadaan_lampiran');
        if($request->search) {
            $lampirans = $lampirans->where('nama', 'like', '%'.$request->search.'%');
        }
        $lampirans = $lampirans->where('pengadaan_id', $pengadaan->id)
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

    public function storeLampiran($pengadaan_id, Request $request) {
        try {
            $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

            if(!$pengadaan) {
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
                    'pengadaan_id'=> $pengadaan->id
                ]);
            }

            DB::table('tbl_pengadaan_lampiran')->insert($lampirans);

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

    public function destroyLampiran($pengadaan_id, $lampiran_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success'   => false,
                'message'   => 'SKKI tidak ditemukan'
            ], 200);
        }

        $lampiran = DB::table('tbl_pengadaan_lampiran')->find($lampiran_id);

        if(!$lampiran) {
            return response()->json([
                'success'   => false,
                'message'   => 'Lampiran tidak ditemukan'
            ], 200);
        }

        $lampirans = DB::table('tbl_pengadaan_lampiran')
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

    // ============ SKKI ===============
    public function indexSkki($pengadaan_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('is_deleted', 0)
            ->get();

        foreach ($pengadaan_skkis as $key => $pengadaan_skki) {
            $pengadaan_skkis[$key]->skki = DB::table('tbl_skki')->find($pengadaan_skki->skki_id);

            $skki_prks = DB::table('tbl_skki_prk')
                ->where('skki_id', $pengadaan_skki->skki_id)
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

                $rab_jasa_total += $rab_jasa->total;
                $rab_material_total += $rab_material->total;
            }

            $pengadaan_skkis[$key]->skki->rab_jasa['total'] = $rab_jasa_total;
            $pengadaan_skkis[$key]->skki->rab_material['total'] = $rab_material_total;
        }

        return response()->json([
            'success' => true,
            'data' => $pengadaan_skkis
        ], 200);
    }

    public function storeSkki($pengadaan_id, Request $request) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $pengadaan_skki_id = DB::table('tbl_pengadaan_skki')
            ->insertGetId([
                'pengadaan_id' => $pengadaan->id,
                'skki_id' => $request->skki
            ]);

        $pengadaan_skki = DB::table('tbl_pengadaan_skki')->find($pengadaan_skki_id);

        return response()->json([
            'success' => true,
            'data' => $pengadaan_skki
        ], 200);
    }

    public function destroySkki($pengadaan_id, $pengadaan_skki_id) {
        $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

        if(!$pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan tidak ditemukan'
            ], 404);
        }

        $pengadaan_skki = DB::table('tbl_pengadaan_skki')
            ->where('pengadaan_id', $pengadaan->id)
            ->where('id', $pengadaan_skki_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$pengadaan_skki) {
            return response()->json([
                'success' => false,
                'message' => 'Pengadaan SKKI tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pengadaan_skki')
            ->where('id', $pengadaan_skki->id)
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

        // todo hapus jasa & material

        return response()->json([
            'success' => true,
            'message' => 'Berhasil dihapus',
        ], 200);
    }
}
