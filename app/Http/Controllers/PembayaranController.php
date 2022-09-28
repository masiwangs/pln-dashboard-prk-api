<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PembayaranController extends Controller
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
        $pembayarans = DB::table('tbl_kontrak')
            ->where('is_deleted', 0);
        
        if($request->basket && in_array($request->basket, [1, 2, 3])) {
            $pembayarans = $pembayarans->where('basket', $request->basket);
        }
        $pembayarans = $pembayarans->get();

        foreach ($pembayarans as $key => $pembayaran) {
            $tagihan_jasa = DB::table('tbl_kontrak_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('kontrak_id', $pembayaran->id)
                ->where('is_deleted', 0)
                ->groupBy('kontrak_id')
                ->first();
            $pembayarans[$key]->tagihan = $tagihan_jasa->total;

            $pembayarans[$key]->terbayar = DB::table('tbl_pembayaran')
                ->select(DB::raw('sum(nominal) as total'))
                ->where('kontrak_id', $pembayaran->id)
                ->where('is_deleted', 0)
                ->groupBy('kontrak_id')
                ->first() ?? 0;
        }
        
        return response()->json([
            'success' => true,
            'data' => $pembayarans
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

    public function show($kontrak_id) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayarn tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $kontrak
        ], 200);
    }

    public function stats($kontrak_id) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayarn tidak ditemukan'
            ], 404);
        }

        $tagihan_jasa = DB::table('tbl_kontrak_jasa')
            ->select(DB::raw('sum(harga) as total'))
            ->where('kontrak_id', $kontrak->id)
            ->where('is_deleted', 0)
            ->groupBy('kontrak_id')
            ->first();
        
        $tagihan = $tagihan_jasa->total;
        $terbayar = DB::table('tbl_pembayaran')
            ->select(DB::raw('sum(nominal) as total'))
            ->where('kontrak_id', $kontrak->id)
            ->where('is_deleted', 0)
            ->groupBy('kontrak_id')
            ->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => $tagihan,
                'used'  => $terbayar->total ?? 0,
                'rest'  => $tagihan - ($terbayar->total ?? 0)
            ]
        ], 200);
    }

    public function update($kontrak_id, Request $request) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_kontrak')
            ->where('id', $kontrak->id)
            ->update([
                'se'            => $request->se,
                'vip'           => $request->vip,
                'status'        => $request->status,
                'updated_at'    => Carbon::now()
            ]);

        if(!$update) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
            ], 400);
        }

        $kontrak = DB::table('tbl_kontrak')->find($kontrak->id);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil disimpan',
            'data' => $kontrak
        ], 200);
    }

    // public function destroy($pengadaan_id) {
    //     $pengadaan = DB::table('tbl_pengadaan')->find($pengadaan_id);

    //     if(!$pengadaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pengadaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $update = DB::table('tbl_pengadaan')
    //         ->where('id', $pengadaan->id)
    //         ->update([
    //             'is_deleted' => 1,
    //             'deleted_at' => Carbon::now()
    //         ]);

    //     // hapus pengadaan skki
    //     $pengadaan_skkis = DB::table('tbl_pengadaan_skki')
    //         ->where('pengadaan_id', $pengadaan->id)
    //         ->where('is_deleted', 0)
    //         ->update([
    //             'is_deleted' => 1,
    //             'deleted_at' => Carbon::now()
    //         ]);

    //     // hapus material
    //     $pengadaan_materials = DB::table('tbl_pengadaan_material')
    //         ->where('pengadaan_id', $pengadaan->id)
    //         ->where('is_deleted', 0)
    //         ->update([
    //             'is_deleted' => 1,
    //             'deleted_at' => Carbon::now()
    //         ]);
            
    //     // hapus jasa
    //     $pengadaan_jasas = DB::table('tbl_pengadaan_jasa')
    //         ->where('pengadaan_id', $pengadaan->id)
    //         ->where('is_deleted', 0)
    //         ->update([
    //             'is_deleted' => 1,
    //             'deleted_at' => Carbon::now()
    //         ]);

    //     // hapus catatan
    //     $pengadaan_catatan = DB::table('tbl_pengadaan_catatan')
    //         ->where('pengadaan_id', $pengadaan->id)
    //         ->where('is_deleted', 0)
    //         ->update([
    //             'is_deleted' => 1,
    //             'deleted_at' => Carbon::now()
    //         ]);
        
    //     return response()->json([
    //         'success' => true,
    //     ], 200);
    // }

    /**
     * ========= CATATAN ==============
     */

    public function indexCatatan($kontrak_id) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }

        $catatans = DB::table('tbl_pembayaran_catatan')
            ->where('kontrak_id', $kontrak->id)
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

    public function storeCatatan($kontrak_id, Request $request) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }

        $catatan_id = DB::table('tbl_pembayaran_catatan')->insertGetId([
            'catatan' => $request->catatan,
            'kontrak_id' => $kontrak->id,
            'user_id' => auth()->id(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        $catatan = DB::table('tbl_pembayaran_catatan')->find($catatan_id);
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

    // public function indexJasa($pelaksanaan_id) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $jasas = DB::table('tbl_pelaksanaan_jasa')
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('is_deleted', 0)
    //         ->get();

    //     foreach ($jasas as $key => $jasa) {
    //         $jasas[$key]->tanggal = date('Y-m-d', strtotime($jasa->tanggal));
    //         $jasas[$key]->tanggal_formatted = date('d M Y', strtotime($jasa->tanggal));
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => $jasas
    //     ], 200);
    // }

    // public function wbsJasa($pelaksanaan_id) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     // hitung used wbs jasa
    //     $kontrak_jasas = DB::table('tbl_kontrak_jasa')
    //         ->select(DB::raw('sum(harga) as total'))
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('is_deleted', 0)
    //         ->first();

    //     // hitung used wbs jasa
    //     $pelaksanaan_jasas = DB::table('tbl_pelaksanaan_jasa')
    //         ->select(DB::raw('sum(harga) as total'))
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('is_deleted', 0)
    //         ->first();

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'total' => $kontrak_jasas->total,
    //             'used'  => $pelaksanaan_jasas->total,
    //             'rest'  => $kontrak_jasas->total - $pelaksanaan_jasas->total,
    //         ]
    //     ], 200);
    // }

    // public function storeJasa($pelaksanaan_id, Request $request) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $jasas = $request->all();

    //     $result = [];
    //     foreach ($jasas as $jasa) {
    //         if($jasa['tanggal'] && $jasa['nama_jasa'] && $jasa['harga']) {
    //             $jasa_id = DB::table('tbl_pelaksanaan_jasa')->insertGetId([
    //                 'tanggal' => $jasa['tanggal'],
    //                 'nama_jasa' => $jasa['nama_jasa'],
    //                 'harga' => (int)$jasa['harga'],
    //                 'kontrak_id' => $pelaksanaan->id
    //             ]);

    //             $jasa = DB::table('tbl_pelaksanaan_jasa')->find($jasa_id);
    //             array_push($result, $jasa);
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => $result
    //     ], 200);
    // }

    // public function updateJasa($pelaksanaan_id, $jasa_id, Request $request) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $jasa = DB::table('tbl_pelaksanaan_jasa')
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('id', $jasa_id)
    //         ->where('is_deleted', 0)
    //         ->first();

    //     if(!$jasa) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Jasa tidak ditemukan'
    //         ], 404);
    //     }

    //     $update = DB::table('tbl_pelaksanaan_jasa')
    //         ->where('id', $jasa->id)
    //         ->update([
    //             'tanggal'   => $request->tanggal,
    //             'nama_jasa' => $request->nama_jasa,
    //             'harga'     => $request->harga,
    //         ]);

    //     $jasa = DB::table('tbl_pelaksanaan_jasa')->find($jasa->id);

    //     if(!$update) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Terjadi kesalahan'
    //         ], 400);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Berhasil disimpan',
    //         'data' => $jasa
    //     ], 200);
    // }

    // public function destroyJasa($pelaksanaan_id, $jasa_id) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $jasa = DB::table('tbl_pelaksanaan_jasa')
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('id', $jasa_id)
    //         ->where('is_deleted', 0)
    //         ->first();

    //     if(!$jasa) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Jasa tidak ditemukan'
    //         ], 404);
    //     }

    //     $update = DB::table('tbl_pelaksanaan_jasa')
    //         ->where('id', $jasa->id)
    //         ->update([
    //             'is_deleted' => 1,
    //             'deleted_at' => Carbon::now()
    //         ]);

    //     if(!$update) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Terjadi kesalahan',
    //         ], 400);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Berhasil dihapus',
    //     ], 200);
    // }

    // /**
    //  * ========= MATERIAL ============
    //  */
    // public function indexMaterial($pelaksanaan_id) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $materials = DB::table('tbl_pelaksanaan_material')
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('is_deleted', 0)
    //         ->get();

    //     foreach ($materials as $key => $material) {
    //         $materials[$key]->tanggal = date('Y-m-d', strtotime($material->tanggal));
    //         $materials[$key]->tanggal_formatted = date('d M Y', strtotime($material->tanggal));
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => $materials
    //     ], 200);
    // }

    // public function wbsMaterial($pelaksanaan_id) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     // hitung used wbs jasa
    //     $kontrak_materials = DB::table('tbl_kontrak_material')
    //         ->select(DB::raw('sum(harga*jumlah) as total'))
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('is_deleted', 0)
    //         ->first();

    //     // hitung used wbs jasa
    //     $pelaksanaan_materials = DB::table('tbl_pelaksanaan_material')
    //         ->select(DB::raw('sum(harga*jumlah*transaksi*-1) as total'))
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('is_deleted', 0)
    //         ->first();

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'total' => $kontrak_materials->total,
    //             'used'  => $pelaksanaan_materials->total,
    //             'rest'  => $kontrak_materials->total - $pelaksanaan_materials->total
    //         ]
    //     ], 200);
    // }

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

    // public function storeMaterial($pelaksanaan_id, Request $request) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $materials = $request->all();

    //     $result = [];
    //     foreach ($materials as $material) {
    //         if($material['id'] && $material['jumlah']) {
    //             $material_kontrak = DB::table('tbl_kontrak_material')->find($material['id']);

    //             if($material_kontrak) {
    //                 $material_id = DB::table('tbl_pelaksanaan_material')->insertGetId([
    //                     'tanggal'           => $material['tanggal'],
    //                     'tug'               => $material['tug'],
    //                     'kode_normalisasi'  => $material_kontrak->kode_normalisasi,
    //                     'nama_material'     => $material_kontrak->nama_material,
    //                     'satuan'            => $material_kontrak->satuan,
    //                     'harga'             => $material_kontrak->harga,
    //                     'jumlah'            => $material['jumlah'],
    //                     'transaksi'         => $material['transaksi'],
    //                     'kontrak_id'        => $pelaksanaan->id,
    //                     'prk_id'            => $material_kontrak->prk_id
    //                 ]);
    
    //                 $material = DB::table('tbl_pelaksanaan_material')->find($material_id);
    //                 array_push($result, $material);
    //             }
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => $result
    //     ], 200);
    // }

    // public function updateMaterial($pelaksanaan_id, $material_id, Request $request) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $material = DB::table('tbl_pelaksanaan_material')
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('id', $material_id)
    //         ->where('is_deleted', 0)
    //         ->first();

    //     if(!$material) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Material tidak ditemukan'
    //         ], 404);
    //     }

    //     $update = DB::table('tbl_pelaksanaan_material')
    //         ->where('id', $material->id)
    //         ->update([
    //             'jumlah' => (int)$request->jumlah,
    //         ]);

    //     $material = DB::table('tbl_pelaksanaan_material')->find($material->id);

    //     if(!$update) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Terjadi kesalahan'
    //         ], 400);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Berhasil disimpan',
    //         'data' => $material
    //     ], 200);
    // }

    // public function destroyMaterial($pelaksanaan_id, $material_id) {
    //     $pelaksanaan = DB::table('tbl_kontrak')->find($pelaksanaan_id);

    //     if(!$pelaksanaan) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Pelaksanaan tidak ditemukan'
    //         ], 404);
    //     }

    //     $material = DB::table('tbl_pelaksanaan_material')
    //         ->where('kontrak_id', $pelaksanaan->id)
    //         ->where('id', $material_id)
    //         ->where('is_deleted', 0)
    //         ->first();

    //     if(!$material) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Material tidak ditemukan'
    //         ], 404);
    //     }

    //     $update = DB::table('tbl_pelaksanaan_material')
    //         ->where('id', $material->id)
    //         ->update([
    //             'is_deleted' => 1,
    //             'deleted_at' => Carbon::now()
    //         ]);

    //     if(!$update) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Terjadi kesalahan',
    //         ], 400);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Berhasil dihapus',
    //     ], 200);
    // }

    /**
     * ========= LAMPIRAN ============
     */

    public function indexLampiran($pembayaran_id, Request $request) {
        $pembayaran = DB::table('tbl_kontrak')->find($pembayaran_id);

        if(!$pembayaran) {
            return response()->json([
                'success'   => false,
                'message'   => 'Pembayaran tidak ditemukan'
            ], 200);
        }

        $page = $request->page;
        if($page) {
            $page = preg_replace('/[^0-9]/i', '', $page);
        } else {
            $page = 1;
        }
        $offset = env('PER_PAGE') * ($page - 1);

        $lampirans = DB::table('tbl_pembayaran_lampiran');
        if($request->search) {
            $lampirans = $lampirans->where('nama', 'like', '%'.$request->search.'%');
        }
        $lampirans = $lampirans->where('kontrak_id', $pembayaran->id)
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

    public function storeLampiran($pembayaran_id, Request $request) {
        try {
            $pembayaran = DB::table('tbl_kontrak')->find($pembayaran_id);

            if(!$pembayaran) {
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
                    'kontrak_id'=> $pembayaran->id
                ]);
            }

            DB::table('tbl_pembayaran_lampiran')->insert($lampirans);

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

    public function destroyLampiran($pembayaran_id, $lampiran_id, Request $request) {
        $pembayaran = DB::table('tbl_kontrak')->find($pembayaran_id);

        if(!$pembayaran) {
            return response()->json([
                'success'   => false,
                'message'   => 'Pembayaran tidak ditemukan'
            ], 200);
        }

        $lampiran = DB::table('tbl_pembayaran_lampiran')->find($lampiran_id);

        if(!$lampiran) {
            return response()->json([
                'success'   => false,
                'message'   => 'Lampiran tidak ditemukan'
            ], 200);
        }

        $lampirans = DB::table('tbl_pembayaran_lampiran')
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

    /**
     * ============ PEMBAYARAN ===============
     */
    public function indexPembayaran($kontrak_id) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }

        $pembayarans = DB::table('tbl_pembayaran')
            ->where('kontrak_id', $kontrak->id)
            ->where('is_deleted', 0)
            ->get();

        foreach ($pembayarans as $key => $value) {
            $pembayarans[$key]->tanggal             = date('Y-m-d', strtotime($value->tanggal));
            $pembayarans[$key]->tanggal_formatted   = date('d M Y', strtotime($value->tanggal));
        }

        return response()->json([
            'success' => true,
            'data' => $pembayarans
        ], 200);
    }

    public function storePembayaran($kontrak_id, Request $request) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }

        $id = DB::table('tbl_pembayaran')
            ->insertGetId([
                'tanggal'       => $request->tanggal,
                'nominal'       => $request->nominal,
                'keterangan'    => $request->keterangan,
                'kontrak_id'    => $kontrak->id,
            ]);

        $pembayaran = DB::table('tbl_pembayaran')->find($id);

        return response()->json([
            'success' => true,
            'data' => $pembayaran
        ], 200);
    }

    public function updatePembayaran($kontrak_id, $pembayaran_id, Request $request) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }
        
        $pembayaran = DB::table('tbl_pembayaran')->find($pembayaran_id);
        
        if(!$pembayaran) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pembayaran')
            ->where('id', $pembayaran_id)
            ->update([
                'tanggal'       => $request->tanggal,
                'nominal'       => $request->nominal,
                'keterangan'    => $request->keterangan,
                'updated_at'    => Carbon::now()
            ]);

        if(!$update) {
            return response()->json([
                'success' => true,
                'message' => 'Terjadi kesalahan'
            ], 500);
        }

        $pembayaran = DB::table('tbl_pembayaran')->find($pembayaran_id);

        return response()->json([
            'success' => true,
            'data' => $pembayaran
        ], 200);
    }

    public function destroyPembayaran($kontrak_id, $pembayaran_id) {
        $kontrak = DB::table('tbl_kontrak')->find($kontrak_id);

        if(!$kontrak) {
            return response()->json([
                'success' => false,
                'message' => 'Kontrak tidak ditemukan'
            ], 404);
        }

        $pembayaran = DB::table('tbl_pembayaran')
            ->where('kontrak_id', $kontrak->id)
            ->where('id', $pembayaran_id)
            ->where('is_deleted', 0)
            ->first();

        if(!$pembayaran) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }

        $update = DB::table('tbl_pembayaran')
            ->where('id', $pembayaran->id)
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
