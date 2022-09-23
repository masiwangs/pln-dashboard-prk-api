<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PelaksanaanController extends Controller
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
        $kontraks = DB::table('tbl_kontrak')
            ->where('is_deleted', 0);
        
        if($request->basket && in_array($request->basket, [1, 2, 3])) {
            $kontraks = $kontraks->where('basket', $request->basket);
        }
        $kontraks = $kontraks->get();

        foreach ($kontraks as $key => $kontrak) {
            $kontraks[$key]->tanggal_kontrak = date('d M Y', strtotime($kontrak->tanggal_kontrak));
            $kontraks[$key]->tanggal_awal = date('d M Y', strtotime($kontrak->tanggal_awal));
            $kontraks[$key]->tanggal_akhir = date('d M Y', strtotime($kontrak->tanggal_akhir));
            $kontraks[$key]->wbs_jasa = DB::table('tbl_kontrak_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('kontrak_id', $kontrak->id)
                ->where('is_deleted', 0)
                ->groupBy('kontrak_id')
                ->first();

            $kontraks[$key]->wbs_material = DB::table('tbl_kontrak_material')
                ->select(DB::raw('sum(harga * jumlah) as total'))
                ->where('kontrak_id', $kontrak->id)
                ->where('is_deleted', 0)
                ->groupBy('kontrak_id')
                ->first();
        }
        
        return response()->json([
            'success' => true,
            'data' => $kontraks
        ], 200);
    }

    // public function store(Request $request) {
    //     $id = DB::table('tbl_kontrak')->insertGetId([
    //         'nomor_kontrak'     => $request->nomor_kontrak,
    //         'tanggal_kontrak'   => $request->tanggal_kontrak,
    //         'tanggal_awal'      => $request->tanggal_awal,
    //         'tanggal_akhir'     => $request->tanggal_akhir,
    //         'pelaksana'         => $request->pelaksana,
    //         'direksi'           => $request->direksi,
    //         'basket'            => $request->basket,
    //         'pengadaan_id'      => $request->pengadaan_id,
    //         'is_deleted'        => 0,
    //         'created_at'        => Carbon::now(),
    //         'updated_at'        => Carbon::now()
    //     ]);

    //     DB::table('tbl_pengadaan')
    //         ->where('id', $request->pengadaan_id)
    //         ->update([
    //             'status' => 2,
    //             'updated_at' => Carbon::now()
    //         ]);

    //     $kontrak = DB::table('tbl_kontrak')->find($id);

    //     // copy pengadaan jasa
    //     $pengadaan_jasas = DB::table('tbl_pengadaan_jasa')
    //         ->where('pengadaan_id', $kontrak->pengadaan_id)
    //         ->get();
    //     $kontrak_jasas = [];
    //     foreach ($pengadaan_jasas as $pengadaan_jasa) {
    //         if($pengadaan_jasa->is_deleted == 0) {
    //             array_push($kontrak_jasas, [
    //                 'nama_jasa'     => $pengadaan_jasa->nama_jasa,
    //                 'harga'         => $pengadaan_jasa->harga,
    //                 'kontrak_id'    => $kontrak->id,
    //                 'is_deleted'    => 0,
    //                 'created_at'    => Carbon::now(),
    //                 'updated_at'    => Carbon::now()
    //             ]);
    //         }
    //     }
    //     // copy pengadaan material
    //     $pengadaan_materials = DB::table('tbl_pengadaan_material')
    //         ->where('pengadaan_id', $kontrak->pengadaan_id)
    //         ->get();

    //     $kontrak_materials = [];
    //     foreach ($pengadaan_materials as $pengadaan_material) {
    //         if($pengadaan_material->is_deleted == 0) {
    //             array_push($kontrak_materials, [
    //                 'kode_normalisasi'  => $pengadaan_material->kode_normalisasi,
    //                 'nama_material'     => $pengadaan_material->nama_material,
    //                 'satuan'            => $pengadaan_material->satuan,
    //                 'harga'             => $pengadaan_material->harga,
    //                 'jumlah'            => $pengadaan_material->jumlah,
    //                 'prk_id'            => $pengadaan_material->prk_id,
    //                 'kontrak_id'        => $kontrak->id,
    //                 'is_deleted'        => 0,
    //                 'created_at'        => Carbon::now(),
    //                 'updated_at'        => Carbon::now()
    //             ]);
    //         }
    //     }

    //     DB::table('tbl_kontrak_jasa')->insert($kontrak_jasas);
    //     DB::table('tbl_kontrak_material')->insert($kontrak_materials);

    //     return response()->json([
    //         'success' => true,
    //         'data' => $kontrak
    //     ], 200);
    // }

    public function show($pelaksanaan_id) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $pengadaan = DB::table('tbl_pengadaan')->find($pelaksanaan->pengadaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $pelaksanaan->nomor_pr_material = $pengadaan->nomor_pr_material;
        
        return response()->json([
            'success' => true,
            'data' => $pelaksanaan
        ], 200);
    }

    public function update($pelaksanaan_id, Request $request) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $update_kontrak = DB::table('tbl_kontrak')
            ->where('id', $pelaksanaan->id)
            ->update([
                'spk'               => $request->spk,
                'progress'          => $request->progress,
                'updated_at'        => Carbon::now()
            ]);

        $update_pengadaan = DB::table('tbl_pengadaan')
            ->where('id', $pelaksanaan->pengadaan_id)
            ->update([
                'nomor_pr_material' => $request->nomor_pr_material,
            ]);

        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan->id);

        if(!$update_kontrak && $update_pengadaan) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil disimpan',
            'data' => $pelaksanaan
        ], 200);
    }

    // // public function destroy($pengadaan_id) {
    // //     $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

    // //     if(!$pengadaan) {
    // //         return response()->json([
    // //             'success' => false,
    // //             'message' => 'Pengadaan tidak ditemukan'
    // //         ], 404);
    // //     }

    // //     $update = DB::table('tbl_pengadaan')
    // //         ->where('id', $pengadaan->id)
    // //         ->update([
    // //             'is_deleted' => 1,
    // //             'deleted_at' => Carbon::now()
    // //         ]);

    // //     // hapus pengadaan skki
    // //     $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
    // //         ->where('pengadaan_id', $pengadaan->id)
    // //         ->where('is_deleted', 0)
    // //         ->update([
    // //             'is_deleted' => 1,
    // //             'deleted_at' => Carbon::now()
    // //         ]);

    // //     // hapus material
    // //     $pengadaan_materials = DB::table('tbl_pengadaan_material')
    // //         ->where('pengadaan_id', $pengadaan->id)
    // //         ->where('is_deleted', 0)
    // //         ->update([
    // //             'is_deleted' => 1,
    // //             'deleted_at' => Carbon::now()
    // //         ]);
            
    // //     // hapus jasa
    // //     $pengadaan_jasas = DB::table('tbl_pengadaan_jasa')
    // //         ->where('pengadaan_id', $pengadaan->id)
    // //         ->where('is_deleted', 0)
    // //         ->update([
    // //             'is_deleted' => 1,
    // //             'deleted_at' => Carbon::now()
    // //         ]);

    // //     // hapus catatan
    // //     $pengadaan_catatan = DB::table('tbl_pengadaan_catatan')
    // //         ->where('pengadaan_id', $pengadaan->id)
    // //         ->where('is_deleted', 0)
    // //         ->update([
    // //             'is_deleted' => 1,
    // //             'deleted_at' => Carbon::now()
    // //         ]);
        
    // //     return response()->json([
    // //         'success' => true,
    // //     ], 200);
    // // }

    /**
     * ========= CATATAN ==============
     */

    public function indexCatatan($pelaksanaan_id) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $catatans = DB::table('tbl_pelaksanaan_catatan')
            ->where('kontrak_id', $pelaksanaan->id)
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

    public function storeCatatan($pelaksanaan_id, Request $request) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $catatan_id = DB::table('tbl_pelaksanaan_catatan')->insertGetId([
            'catatan' => $request->catatan,
            'kontrak_id' => $pelaksanaan->id,
            'user_id' => auth()->id(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        $catatan = DB::table('tbl_pelaksanaan_catatan')->find($catatan_id);
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

    public function indexJasa($pelaksanaan_id) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $jasas = DB::table('tbl_pelaksanaan_jasa')
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('is_deleted', 0)
            ->get();

        foreach ($jasas as $key => $jasa) {
            $jasas[$key]->tanggal = date('Y-m-d', strtotime($jasa->tanggal));
            $jasas[$key]->tanggal_formatted = date('d M Y', strtotime($jasa->tanggal));
        }

        return response()->json([
            'success' => true,
            'data' => $jasas
        ], 200);
    }

    public function wbsJasa($pelaksanaan_id) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        // hitung used wbs jasa
        $kontrak_jasas = DB::table('tbl_kontrak_jasa')
            ->select(DB::raw('sum(harga) as total'))
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('is_deleted', 0)
            ->first();

        // hitung used wbs jasa
        $pelaksanaan_jasas = DB::table('tbl_pelaksanaan_jasa')
            ->select(DB::raw('sum(harga) as total'))
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('is_deleted', 0)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $kontrak_jasas->total,
                'used'  => $pelaksanaan_jasas->total,
                'rest'  => $kontrak_jasas->total - $pelaksanaan_jasas->total,
            ]
        ], 200);
    }

    public function storeJasa($pelaksanaan_id, Request $request) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $jasas = $request->all();

        $result = [];
        foreach ($jasas as $jasa) {
            if($jasa['tanggal'] && $jasa['nama_jasa'] && $jasa['harga']) {
                $jasa_id = DB::table('tbl_pelaksanaan_jasa')->insertGetId([
                    'tanggal' => $jasa['tanggal'],
                    'nama_jasa' => $jasa['nama_jasa'],
                    'harga' => (int)$jasa['harga'],
                    'kontrak_id' => $pelaksanaan->id
                ]);

                $jasa = DB::table('tbl_pelaksanaan_jasa')->find($jasa_id);
                array_push($result, $jasa);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    public function updateJasa($pelaksanaan_id, $jasa_id, Request $request) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $jasa = DB::table('tbl_pelaksanaan_jasa')
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('id', $jasa_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$jasa) {
            return response()->json([
                'success' => false,
                'message' => 'Jasa tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pelaksanaan_jasa')
            ->where('id', $jasa->id)
            ->update([
                'tanggal'   => $request->tanggal,
                'nama_jasa' => $request->nama_jasa,
                'harga'     => $request->harga,
            ]);

        $jasa = DB::table('tbl_pelaksanaan_jasa')->find($jasa->id);

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

    public function destroyJasa($pelaksanaan_id, $jasa_id) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $jasa = DB::table('tbl_pelaksanaan_jasa')
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('id', $jasa_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$jasa) {
            return response()->json([
                'success' => false,
                'message' => 'Jasa tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pelaksanaan_jasa')
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
    public function indexMaterial($pelaksanaan_id) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $materials = DB::table('tbl_pelaksanaan_material')
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('is_deleted', 0)
            ->get();

        foreach ($materials as $key => $material) {
            $materials[$key]->tanggal = date('Y-m-d', strtotime($material->tanggal));
            $materials[$key]->tanggal_formatted = date('d M Y', strtotime($material->tanggal));
        }

        return response()->json([
            'success' => true,
            'data' => $materials
        ], 200);
    }

    public function wbsMaterial($pelaksanaan_id) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        // hitung used wbs jasa
        $kontrak_materials = DB::table('tbl_kontrak_material')
            ->select(DB::raw('sum(harga*jumlah) as total'))
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('is_deleted', 0)
            ->first();

        // hitung used wbs jasa
        $pelaksanaan_materials = DB::table('tbl_pelaksanaan_material')
            ->select(DB::raw('sum(harga*jumlah*transaksi*-1) as total'))
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('is_deleted', 0)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $kontrak_materials->total,
                'used'  => $pelaksanaan_materials->total,
                'rest'  => $kontrak_materials->total - $pelaksanaan_materials->total
            ]
        ], 200);
    }

    // // public function stokMaterial($pengadaan_id) {
    // //     $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

    // //     if(!$pengadaan) {
    // //         return response()->json([
    // //             'success' => false,
    // //             'message' => 'Pengadaan tidak ditemukan'
    // //         ], 404);
    // //     }

    // //     $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
    // //         ->where('pengadaan_id', $pengadaan->id)
    // //         ->where('is_deleted', 0)
    // //         ->get();

    // //     $result = [];
    // //     foreach ($pengadaan_skkis as $pengadaan_skki) {
    // //         $skki_prks = DB::table('tbl_skki_prk')
    // //             ->where('skki_id', $pengadaan_skki->skki_id)
    // //             ->where('is_deleted', 0)
    // //             ->get();
            
    // //         foreach ($skki_prks as $skki_prk) {
    // //             $prk = DB::table('tbl_prk')->find($skki_prk->prk_id);

    // //             $materials = DB::table('tbl_prk_material')
    // //                 ->where('prk_id', $skki_prk->prk_id)
    // //                 ->where('is_deleted', 0)
    // //                 ->get();

    // //             foreach ($materials as $key => $material) {
    // //                 $materials[$key]->prk = $prk;
    // //                 array_push($result, $material);
    // //             }
    // //         }
    // //     }

    // //     return response()->json([
    // //         'success' => true,
    // //         'data' => $result
    // //     ], 200);
    // // }

    public function storeMaterial($pelaksanaan_id, Request $request) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $materials = $request->all();

        $result = [];
        foreach ($materials as $material) {
            if($material['id'] && $material['jumlah']) {
                $material_kontrak = DB::table('tbl_kontrak_material')->find($material['id']);

                if($material_kontrak) {
                    $material_id = DB::table('tbl_pelaksanaan_material')->insertGetId([
                        'tanggal'           => $material['tanggal'],
                        'tug'               => $material['tug'],
                        'kode_normalisasi'  => $material_kontrak->kode_normalisasi,
                        'nama_material'     => $material_kontrak->nama_material,
                        'satuan'            => $material_kontrak->satuan,
                        'harga'             => $material_kontrak->harga,
                        'jumlah'            => $material['jumlah'],
                        'transaksi'         => $material['transaksi'],
                        'kontrak_id'        => $pelaksanaan->id,
                        'prk_id'            => $material_kontrak->prk_id
                    ]);
    
                    $material = DB::table('tbl_pelaksanaan_material')->find($material_id);
                    array_push($result, $material);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    public function updateMaterial($pelaksanaan_id, $material_id, Request $request) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $material = DB::table('tbl_pelaksanaan_material')
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('id', $material_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pelaksanaan_material')
            ->where('id', $material->id)
            ->update([
                'jumlah' => (int)$request->jumlah,
            ]);

        $material = DB::table('tbl_pelaksanaan_material')->find($material->id);

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

    public function destroyMaterial($pelaksanaan_id, $material_id) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pelaksanaan tidak ditemukan'
            ], 404);
        }

        $material = DB::table('tbl_pelaksanaan_material')
            ->where('kontrak_id', $pelaksanaan->id)
            ->where('id', $material_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pelaksanaan_material')
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

    public function indexLampiran($pelaksanaan_id, Request $request) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success'   => false,
                'message'   => 'Pelaksanaan tidak ditemukan'
            ], 200);
        }

        $page = $request->page;
        if($page) {
            $page = preg_replace('/[^0-9]/i', '', $page);
        } else {
            $page = 1;
        }
        $offset = env('PER_PAGE') * ($page - 1);

        $lampirans = DB::table('tbl_pelaksanaan_lampiran');
        if($request->search) {
            $lampirans = $lampirans->where('nama', 'like', '%'.$request->search.'%');
        }
        $lampirans = $lampirans->where('kontrak_id', $pelaksanaan->id)
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

    public function storeLampiran($pelaksanaan_id, Request $request) {
        try {
            $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

            if(!$pelaksanaan) {
                return response()->json([
                    'success'   => false,
                    'message'   => 'Pelaksanaan tidak ditemukan'
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
                    'kontrak_id'=> $pelaksanaan->id
                ]);
            }

            DB::table('tbl_pelaksanaan_lampiran')->insert($lampirans);

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

    public function destroyLampiran($pelaksanaan_id, $lampiran_id, Request $request) {
        $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

        if(!$pelaksanaan) {
            return response()->json([
                'success'   => false,
                'message'   => 'Pelaksanaan tidak ditemukan'
            ], 200);
        }

        $lampiran = DB::table('tbl_pelaksanaan_lampiran')->find($lampiran_id);

        if(!$lampiran) {
            return response()->json([
                'success'   => false,
                'message'   => 'Lampiran tidak ditemukan'
            ], 200);
        }

        $lampirans = DB::table('tbl_pelaksanaan_lampiran')
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

    // // // ============ SKKI ===============
    // // public function indexSkki($pengadaan_id) {
    // //     $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

    // //     if(!$pengadaan) {
    // //         return response()->json([
    // //             'success' => false,
    // //             'message' => 'Pengadaan tidak ditemukan'
    // //         ], 404);
    // //     }

    // //     $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
    // //         ->where('pengadaan_id', $pengadaan->id)
    // //         ->where('is_deleted', 0)
    // //         ->get();

    // //     foreach ($pengadaan_skkis as $key => $pengadaan_skki) {
    // //         $pengadaan_skkis[$key]->skki = DB::table('tbl_skki')->find($pengadaan_skki->skki_id);

    // //         $skki_prks = DB::table('tbl_skki_prk')
    // //             ->where('skki_id', $pengadaan_skki->skki_id)
    // //             ->where('is_deleted', 0)
    // //             ->get();

    // //         $rab_jasa_total = 0;
    // //         $rab_material_total = 0;
    // //         foreach ($skki_prks as $skki_prk) {
    // //             $rab_jasa = DB::table('tbl_prk_jasa')
    // //                 ->select(DB::raw('sum(harga) as total'))
    // //                 ->where('prk_id', $skki_prk->prk_id)
    // //                 ->where('is_deleted', 0)
    // //                 ->groupBy('prk_id')
    // //                 ->first();
                    
    // //             $rab_material = DB::table('tbl_prk_material')
    // //                 ->select(DB::raw('sum(harga * jumlah) as total'))
    // //                 ->where('prk_id', $skki_prk->prk_id)
    // //                 ->where('is_deleted', 0)
    // //                 ->groupBy('prk_id')
    // //                 ->first();

    // //             $rab_jasa_total += $rab_jasa->total;
    // //             $rab_material_total += $rab_material->total;
    // //         }

    // //         $pengadaan_skkis[$key]->skki->rab_jasa['total'] = $rab_jasa_total;
    // //         $pengadaan_skkis[$key]->skki->rab_material['total'] = $rab_material_total;
    // //     }

    // //     return response()->json([
    // //         'success' => true,
    // //         'data' => $pengadaan_skkis
    // //     ], 200);
    // // }

    // // public function storeSkki($pengadaan_id, Request $request) {
    // //     $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

    // //     if(!$pengadaan) {
    // //         return response()->json([
    // //             'success' => false,
    // //             'message' => 'Pengadaan tidak ditemukan'
    // //         ], 404);
    // //     }

    // //     $pengadaan_skki_id = DB::table('tbl_pengadaan_skki')
    // //         ->insertGetId([
    // //             'pengadaan_id' => $pengadaan->id,
    // //             'skki_id' => $request->skki
    // //         ]);

    // //     $pengadaan_skki = DB::table('tbl_pengadaan_skki')->find($pengadaan_skki_id);

    // //     return response()->json([
    // //         'success' => true,
    // //         'data' => $pengadaan_skki
    // //     ], 200);
    // // }

    // // public function destroySkki($pengadaan_id, $pengadaan_skki_id) {
    // //     $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

    // //     if(!$pengadaan) {
    // //         return response()->json([
    // //             'success' => false,
    // //             'message' => 'Pengadaan tidak ditemukan'
    // //         ], 404);
    // //     }

    // //     $pengadaan_skki = DB::table('tbl_pengadaan_skki')
    // //         ->where('pengadaan_id', $pengadaan->id)
    // //         ->where('id', $pengadaan_skki_id)
    // //         ->where('is_deleted', 0)
    // //         ->first();

    // //     if(!$pengadaan_skki) {
    // //         return response()->json([
    // //             'success' => false,
    // //             'message' => 'Pengadaan SKKI tidak ditemukan'
    // //         ], 404);
    // //     }

    // //     $update = DB::table('tbl_pengadaan_skki')
    // //         ->where('id', $pengadaan_skki->id)
    // //         ->update([
    // //             'is_deleted' => 1,
    // //             'deleted_at' => Carbon::now()
    // //         ]);

    // //     if(!$update) {
    // //         return response()->json([
    // //             'success' => false,
    // //             'message' => 'Terjadi kesalahan',
    // //         ], 400);
    // //     }

    // //     // todo hapus jasa & material

    // //     return response()->json([
    // //         'success' => true,
    // //         'message' => 'Berhasil dihapus',
    // //     ], 200);
    // // }
}
