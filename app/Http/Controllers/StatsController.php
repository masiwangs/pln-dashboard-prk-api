<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
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

    public function dashboardData(Request $request) {
        $basket = $request->basket;
        if($basket) {
            $basket = preg_replace('/[^0-9]/i', '', $basket);
            if(!in_array($basket, [1, 2, 3])) {
                $basket = 1;
            }
        } else {
            $basket = 0;
        }


        // prk
        $prks = DB::table('tbl_prk');
        if($basket > 0) {
            $prks = $prks->where('basket', $basket);
        }
        $prks = $prks->where('is_deleted', 0)
            ->get();
        $prk_jasa_total = 0;
        $prk_material_total = 0;
        foreach ($prks as $value) {
            $prk_jasa = DB::table('tbl_prk_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('prk_id', $value->id)
                ->where('is_deleted', 0)
                ->first();
            $prk_material = DB::table('tbl_prk_material')
                ->select(DB::raw('sum(harga*jumlah) as total'))
                ->where('prk_id', $value->id)
                ->where('is_deleted', 0)
                ->first();

            if($prk_jasa->total) {
                $prk_jasa_total += $prk_jasa->total;
                $prk_material_total += $prk_material->total;
            }
        }

        // skki
        $skkis = DB::table('tbl_skki');
        if($basket > 0) {
            $skkis       = $skkis->where('basket', $basket);
        }
        $skkis = $skkis->where('is_deleted', 0)
            ->get();
        $skki_jasa_total = 0;
        $skki_material_total = 0;
        foreach ($skkis as $skki) {
            $skki_prks = DB::table('tbl_skki_prk')
                ->where('skki_id', $skki->id)
                ->where('is_deleted', 0)
                ->get();

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
                    $skki_jasa_total += $rab_jasa->total;
                }

                if($rab_material) {
                    $skki_material_total += $rab_material->total;
                }
            }
        }

        // pengadaan
        $pengadaans = DB::table('tbl_pengadaan');
        if($basket > 0) {
            $pengadaans = $pengadaans->where('basket', $basket);
        }
        $pengadaans = $pengadaans->where('is_deleted', 0)
            ->get();
        $pengadaan_jasa_total = 0;
        $pengadaan_material_total = 0;
        foreach ($pengadaans as $value) {
            $pengadaan_jasa = DB::table('tbl_pengadaan_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('is_deleted', 0)
                ->first();
            $pengadaan_material = DB::table('tbl_pengadaan_material')
                ->select(DB::raw('sum(harga*jumlah) as total'))
                ->where('is_deleted', 0)
                ->first();
            if($pengadaan_jasa->total) {
                $pengadaan_jasa_total += $pengadaan_jasa->total;
            }
            if($pengadaan_material->total) {
                $pengadaan_material_total += $pengadaan_material->total;
            }
        }

        // kontrak pelaksanaan & pembayaran
        $kontraks = DB::table('tbl_kontrak');
        if($basket > 0) {
            $kontraks = $kontraks->where('basket', $basket);
        }
        $kontraks = $kontraks->where('is_deleted', 0)
            ->get();
        $kontrak_jasa_total = 0;
        $kontrak_material_total = 0;
        $pelaksanaan_jasa_total = 0;
        $pelaksanaan_material_total = 0;
        $pembayaran_total = 0;
        foreach ($kontraks as $value) {
            // kontrak
            $kontrak_jasa = DB::table('tbl_kontrak_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('is_deleted', 0)
                ->first();
            $kontrak_material = DB::table('tbl_kontrak_material')
                ->select(DB::raw('sum(harga*jumlah) as total'))
                ->where('is_deleted', 0)
                ->first();
            if($kontrak_jasa->total) {
                $kontrak_jasa_total += $kontrak_jasa->total;
            }
            if($kontrak_material->total) {
                $kontrak_material_total += $kontrak_material->total;
            }

            // pelaksanaan
            $pelaksanaan_jasa = DB::table('tbl_pelaksanaan_jasa')
                ->select(DB::raw('sum(harga) as total'))
                ->where('kontrak_id', $value->id)
                ->where('is_deleted', 0)->first();
            $pelaksanaan_material = DB::table('tbl_pelaksanaan_material')
                ->select(DB::raw('sum(harga*jumlah*transaksi*-1) as total'))
                ->where('kontrak_id', $value->id)
                ->where('is_deleted', 0)->first();
            if($pelaksanaan_jasa->total) {
                $pelaksanaan_jasa_total += $pelaksanaan_jasa->total;
            }
            if($pelaksanaan_material->total) {
                $pelaksanaan_material_total += $pelaksanaan_material->total;
            }

            // pembayaran
            $pembayaran = DB::table('tbl_pembayaran')
                ->select(DB::raw('sum(nominal) as total'))
                ->where('kontrak_id', $value->id)
                ->where('is_deleted', 0)
                ->first();
            if($pembayaran->total) {
                $pembayaran_total += $pembayaran->total;
            }

        }

        return response()->json([
            'success' => true,
            'data' => [
                'prk' => [
                    'jasa'      => $prk_jasa_total,
                    'material'  => $prk_material_total,
                    'total'     => $prk_jasa_total + $prk_material_total
                ],
                'skki' => [
                    'jasa'      => $skki_jasa_total,
                    'material'  => $skki_material_total,
                    'total'     => $skki_jasa_total + $skki_material_total
                ],
                'pengadaan' => [
                    'jasa'      => $pengadaan_jasa->total ?? 0,
                    'material'  => $pengadaan_material->total ?? 0,
                    'total'     => ($pengadaan_jasa->total ?? 0) + ($pengadaan_material->total ?? 0)
                ],
                'kontrak' => [
                    'jasa'      => $kontrak_jasa->total ?? 0,
                    'material'  => $kontrak_material->total ?? 0,
                    'total'     => ($kontrak_jasa->total ?? 0) + ($kontrak_material->total ?? 0)
                ],
                'pelaksanaan' => [
                    'jasa'      => $pelaksanaan_jasa_total,
                    'material'  => $pelaksanaan_material_total,
                    'total'     => ($pelaksanaan_jasa_total) + ($pelaksanaan_material_total)
                ],
                'pembayaran' => [
                    'total'     => $pembayaran_total
                ]
            ]
        ]);
    }
}
