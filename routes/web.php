<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
use Illuminate\Support\Facades\Hash;

app('translator')->setLocale('id');

$router->get('/', function () use ($router) {
    return app('translator')->getLocale();
});
$router->group(['prefix' => 'api/auth'], function() use ($router) {
    // ============= AUTH ================
    $router->post(  '/login',                                               'AuthController@login'                  );
    // $router->post(  '/refresh',                                             'AuthController@refresh'                );
    $router->group(['middleware' => 'auth:api'], function() use($router) {
        $router->get( '/user',                                             'AuthController@user'                    );
        $router->post('/user/update/nama',                                 'AuthController@updateNama'              );
        $router->post('/user/update/password',                             'AuthController@updatePassword'          );
        $router->post('/logout',                                           'AuthController@logout'                  );
    });
});

$router->group(['middleware' => 'auth', 'prefix' => 'api/v1'], function() use ($router) {
    // ============= DASHBOARD ================
    $router->get('/stats', [
        'middleware' => [],
        'uses' => 'StatsController@dashboardData'
    ]);
    // ============= PRK ================
    $router->get('/prk', [
        'middleware' => [],
        'uses' => 'PrkController@index'
    ]);
    $router->post('/prk', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@store'
    ]);
    $router->post('/prk/import', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@import'
    ]);
    $router->get('/prk/{prk_id}', [
        'middleware' => [],
        'uses' => 'PrkController@show'
    ]);
    $router->post('/prk/{prk_id}', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@update'
    ]);
    $router->delete('/prk/{prk_id}', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@destroy'
    ]);
    $router->get('/prk/{prk_id}/catatan', [
        'middleware' => [],
        'uses' => 'PrkController@indexCatatan'
    ]);
    $router->post('/prk/{prk_id}/catatan', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@storeCatatan'
    ]);
    $router->get('/prk/{prk_id}/jasa', [
        'middleware' => [],
        'uses' => 'PrkController@indexJasa'
    ]);
    $router->post('/prk/{prk_id}/jasa', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@storeJasa'
    ]);
    $router->post('/prk/{prk_id}/jasa/{jasa_id}', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@updateJasa'
    ]);
    $router->delete('/prk/{prk_id}/jasa/{jasa_id}', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@destroyJasa'
    ]);
    $router->get('/prk/{prk_id}/lampiran', [
        'middleware' => [],
        'uses' => 'PrkController@indexLampiran'
    ]);
    $router->post('/prk/{prk_id}/lampiran', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@storeLampiran'
    ]);
    $router->delete('/prk/{prk_id}/lampiran/{lampiran_id}', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@destroyLampiran'
    ]);
    $router->get('/prk/{prk_id}/material', [
        'middleware' => [],
        'uses' => 'PrkController@indexMaterial'
    ]);
    $router->post('/prk/{prk_id}/material', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@storeMaterial'
    ]);
    $router->post('/prk/{prk_id}/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@updateMaterial'
    ]);
    $router->delete('/prk/{prk_id}/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-prk'
        ],
        'uses' => 'PrkController@destroyMaterial'
    ]);
    
    // ============= SKKI ================
    $router->get('/skki', [
        'middleware' => [],
        'uses' => 'SkkiController@index'
    ]);
    $router->post('/skki', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@store'
    ]);
    $router->post('/skki/import', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@import'
    ]);
    $router->get('/skki/{skki_id}', [
        'middleware' => [],
        'uses' => 'SkkiController@show'
    ]);
    $router->post('/skki/{skki_id}', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@update'
    ]);
    $router->delete('/skki/{skki_id}', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@destroy'
    ]);
    $router->get('/skki/{skki_id}/catatan', [
        'middleware' => [],
        'uses' => 'SkkiController@indexCatatan'
    ]);
    $router->post('/skki/{skki_id}/catatan', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@storeCatatan'
    ]);
    $router->get('/skki/{skki_id}/jasa', [
        'middleware' => [],
        'uses' => 'SkkiController@indexJasa'
    ]);
    $router->get('/skki/{skki_id}/lampiran', [
        'middleware' => [],
        'uses' => 'SkkiController@indexLampiran'
    ]);
    $router->post('/skki/{skki_id}/lampiran', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@storeLampiran'
    ]);
    $router->delete('/skki/{skki_id}/lampiran/{lampiran_id}', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@destroyLampiran'
    ]);
    $router->get('/skki/{skki_id}/material', [
        'middleware' => [],
        'uses' => 'SkkiController@indexMaterial'
    ]);
    $router->get('/skki/{skki_id}/prk', [
        'middleware' => [],
        'uses' => 'SkkiController@indexPrk'
    ]);
    $router->post('/skki/{skki_id}/prk', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@storePrk'
    ]);
    $router->delete('/skki/{skki_id}/prk/{skki_prk_id}', [
        'middleware' => [
            'permission:superadmin,edit-skki'
        ],
        'uses' => 'SkkiController@destroyPrk'
    ]);
    
    // ============= PENGADAAN ================
    $router->get('/pengadaan', [
        'middleware' => [],
        'uses' => 'PengadaanController@index'
    ]);
    $router->post('/pengadaan', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@store'
    ]);
    $router->post('/pengadaan/import', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@import'
    ]);
    $router->get('/pengadaan/{pengadaan_id}', [
        'middleware' => [],
        'uses' => 'PengadaanController@show'
    ]);
    $router->post('/pengadaan/{pengadaan_id}', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@update'
    ]);
    $router->delete('/pengadaan/{pengadaan_id}', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@destroy'
    ]);
    $router->get('/pengadaan/{pengadaan_id}/catatan', [
        'middleware' => [],
        'uses' => 'PengadaanController@indexCatatan'
    ]);
    $router->post('/pengadaan/{pengadaan_id}/catatan', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@storeCatatan'
    ]);
    $router->get('/pengadaan/{pengadaan_id}/jasa', [
        'middleware' => [],
        'uses' => 'PengadaanController@indexJasa'
    ]);
    $router->post('/pengadaan/{pengadaan_id}/jasa', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@storeJasa'
    ]);
    $router->post('/pengadaan/{pengadaan_id}/jasa/{jasa_id}', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@updateJasa'
    ]);
    $router->delete('/pengadaan/{pengadaan_id}/jasa/{jasa_id}', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@destroyJasa'
    ]);
    $router->get('/pengadaan/{pengadaan_id}/lampiran', [
        'middleware' => [],
        'uses' => 'PengadaanController@indexLampiran'
    ]);
    $router->post('/pengadaan/{pengadaan_id}/lampiran', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@storeLampiran'
    ]);
    $router->delete('/pengadaan/{pengadaan_id}/lampiran/{lampiran_id}', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@destroyLampiran'
    ]);
    $router->get('/pengadaan/{pengadaan_id}/material', [
        'middleware' => [],
        'uses' => 'PengadaanController@indexMaterial'
    ]);
    $router->post('/pengadaan/{pengadaan_id}/material', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@storeMaterial'
    ]);
    $router->post('/pengadaan/{pengadaan_id}/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@updateMaterial'
    ]);
    $router->delete('/pengadaan/{pengadaan_id}/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@destroyMaterial'
    ]);
    $router->get('/pengadaan/{pengadaan_id}/skki', [
        'middleware' => [],
        'uses' => 'PengadaanController@indexSkki'
    ]);
    $router->post('/pengadaan/{pengadaan_id}/skki', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@storeSkki'
    ]);
    $router->delete('/pengadaan/{pengadaan_id}/skki/{pengadaan_skki_id}', [
        'middleware' => [
            'permission:superadmin,edit-pengadaan'
        ],
        'uses' => 'PengadaanController@destroySkki'
    ]);
    $router->get('/pengadaan/{pengadaan_id}/stok-material', [
        'middleware' => [],
        'uses' => 'PengadaanController@stokMaterial'
    ]);
    $router->get('/pengadaan/{pengadaan_id}/wbs-jasa', [
        'middleware' => [],
        'uses' => 'PengadaanController@wbsJasa'
    ]);
    $router->get('/pengadaan/{pengadaan_id}/wbs-material', [
        'middleware' => [],
        'uses' => 'PengadaanController@wbsMaterial'
    ]);
    
    // ============= KONTRAK ================
    $router->get('/kontrak', [
        'middleware' => [],
        'uses' => 'KontrakController@index'
    ]);
    $router->post('/kontrak', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@store'
    ]);
    $router->get('/kontrak/{kontrak_id}', [
        'middleware' => [],
        'uses' => 'KontrakController@show'
    ]);
    $router->post('/kontrak/{kontrak_id}', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@update'
    ]);
    $router->post('/kontrak/{kontrak_id}/amandemen', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@updateAmandemen'
    ]);
    $router->get('/kontrak/{kontrak_id}/catatan', [
        'middleware' => [],
        'uses' => 'KontrakController@indexCatatan'
    ]);
    $router->post('/kontrak/{kontrak_id}/catatan', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@storeCatatan'
    ]);
    $router->get('/kontrak/{kontrak_id}/jasa', [
        'middleware' => [],
        'uses' => 'KontrakController@indexJasa'
    ]);
    $router->post('/kontrak/{kontrak_id}/jasa', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@storeJasa'
    ]);
    $router->post('/kontrak/{kontrak_id}/jasa/{jasa_id}', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@updateJasa'
    ]);
    $router->delete('/kontrak/{kontrak_id}/jasa/{jasa_id}', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@destroyJasa'
    ]);
    $router->get('/kontrak/{kontrak_id}/lampiran', [
        'middleware' => [],
        'uses' => 'KontrakController@indexLampiran'
    ]);
    $router->post('/kontrak/{kontrak_id}/lampiran', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@storeLampiran'
    ]);
    $router->delete('/kontrak/{kontrak_id}/lampiran/{lampiran_id}', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@destroyLampiran'
    ]);
    $router->get('/kontrak/{kontrak_id}/material', [
        'middleware' => [],
        'uses' => 'KontrakController@indexMaterial'
    ]);
    $router->post('/kontrak/{kontrak_id}/material', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@storeMaterial'
    ]);
    $router->post('/kontrak/{kontrak_id}/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@updateMaterial'
    ]);
    $router->delete('/kontrak/{kontrak_id}/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-kontrak'
        ],
        'uses' => 'KontrakController@destroyMaterial'
    ]);
    $router->get('/kontrak/{kontrak_id}/wbs-jasa', [
        'middleware' => [],
        'uses' => 'KontrakController@wbsJasa'
    ]);
    $router->get('/kontrak/{kontrak_id}/wbs-material', [
        'middleware' => [],
        'uses' => 'KontrakController@wbsMaterial'
    ]);
    
    // ============= PELAKSANAAN ================
    $router->get('/pelaksanaan', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@index']);
    $router->get('/pelaksanaan/{pelaksanaan_id}', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@show']);
    $router->post('/pelaksanaan/{pelaksanaan_id}', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@update']);
    $router->get('/pelaksanaan/{pelaksanaan_id}/catatan', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@indexCatatan']);
    $router->post('/pelaksanaan/{pelaksanaan_id}/catatan', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@storeCatatan']);
    $router->get('/pelaksanaan/{pelaksanaan_id}/jasa', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@indexJasa']);
    $router->post('/pelaksanaan/{pelaksanaan_id}/jasa', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@storeJasa']);
    $router->post('/pelaksanaan/{pelaksanaan_id}/jasa/{jasa_id}', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@updateJasa']);
    $router->delete('/pelaksanaan/{pelaksanaan_id}/jasa/{jasa_id}', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@destroyJasa'
    ]);
    $router->get('/pelaksanaan/{pelaksanaan_id}/lampiran', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@indexLampiran']);
    $router->post('/pelaksanaan/{pelaksanaan_id}/lampiran', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@storeLampiran']);
    $router->delete('/pelaksanaan/{pelaksanaan_id}/lampiran/{lampiran_id}', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@destroyLampiran'
    ]);
    $router->get('/pelaksanaan/{pelaksanaan_id}/material', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@indexMaterial']);
    $router->post('/pelaksanaan/{pelaksanaan_id}/material', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@storeMaterial']);
    $router->post('/pelaksanaan/{pelaksanaan_id}/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@updateMaterial']);
    $router->delete('/pelaksanaan/{pelaksanaan_id}/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-pelaksanaan'
        ],
        'uses' => 'PelaksanaanController@destroyMaterial'
    ]);
    $router->get('/pelaksanaan/{pelaksanaan_id}/wbs-jasa', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@wbsJasa']);
    $router->get('/pelaksanaan/{pelaksanaan_id}/wbs-material', [
        'middleware' => [],
        'uses' => 'PelaksanaanController@wbsMaterial']);
    
    // ============ PEMBAYARAN ================
    $router->get('/pembayaran', [
        'middleware' => [],
        'uses' => 'PembayaranController@index'
    ]);
    $router->get('/pembayaran/{kontrak_id}', [
        'middleware' => [],
        'uses' => 'PembayaranController@show'
    ]);
    $router->post('/pembayaran/{kontrak_id}', [
        'middleware' => [
            'permission:superadmin,edit-pembayaran'
        ], 
        'uses' => 'PembayaranController@update'
    ]);
    $router->get('/pembayaran/{kontrak_id}/catatan', 
        'PembayaranController@indexCatatan');
    $router->post('/pembayaran/{kontrak_id}/catatan', 
        'PembayaranController@storeCatatan');
    $router->get('/pembayaran/{pembayaran_id}/lampiran', 
        'PembayaranController@indexLampiran');
    $router->post('/pembayaran/{pembayaran_id}/lampiran', [
        'middleware' => [
            'permission:superadmin,edit-pembayaran'
        ], 
        'uses' => 'PembayaranController@storeLampiran'
    ]);
    $router->delete('/pembayaran/{pembayaran_id}/lampiran/{lampiran_id}', [
        'middleware' => [
            'permission:superadmin,edit-pembayaran'
        ], 
        'uses' => 'PembayaranController@destroyLampiran'
    ]);
    $router->get('/pembayaran/{kontrak_id}/pembayaran', [
        'middleware' => [],
        'uses' => 'PembayaranController@indexPembayaran'
    ]);
    $router->post('/pembayaran/{kontrak_id}/pembayaran', [
        'middleware' => [
            'permission:superadmin,edit-pembayaran'
        ], 
        'uses' => 'PembayaranController@storePembayaran'
    ]);
    $router->post('/pembayaran/{kontrak_id}/pembayaran/{pembayaran_id}', [
        'middleware' => [
            'permission:superadmin,edit-pembayaran'
        ], 
        'uses' => 'PembayaranController@updatePembayaran'
    ]);
    $router->delete('/pembayaran/{kontrak_id}/pembayaran/{pembayaran_id}', [
        'middleware' => [
            'permission:superadmin,edit-pembayaran'
        ], 
        'uses' => 'PembayaranController@destroyPembayaran'
    ]);
    $router->get('/pembayaran/{kontrak_id}/stats', [
        'middleware' => [],
        'uses' => 'PembayaranController@stats'
    ]);

    // ============= MATERIAL ================
    $router->get('/material', [
        'middleware' => [],
        'uses' => 'MaterialController@index'
    ]);
    $router->post('/material', [
        'middleware' => [
            'permission:superadmin,edit-material'
        ], 
        'uses' => 'MaterialController@store'
    ]);
    $router->post('/material/import', [
        'middleware' => [
            'permission:superadmin,edit-material'
        ], 
        'uses' => 'MaterialController@import'
    ]);
    $router->post('/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-material'
        ], 
        'uses' => 'MaterialController@update'
    ]);
    $router->delete('/material/{material_id}', [
        'middleware' => [
            'permission:superadmin,edit-material'
        ], 
        'uses' => 'MaterialController@destroy'
    ]);

    // ============= ADMIN ================
    $router->get('/admin', [
        'middleware' => [
            'permission:superadmin'
        ], 
        'uses' => 'AdminController@index'
    ]);
    $router->post('/admin', [
        'middleware' => [
            'permission:superadmin'
        ], 
        'uses' => 'AdminController@store'
    ]);
    $router->post('/admin/{admin_id}', [
        'middleware' => [
            'permission:superadmin'
        ], 
        'uses' => 'AdminController@update'
    ]);
    $router->post('/admin/{admin_id}/password', [
        'middleware' => [
            'permission:superadmin'
        ], 
        'uses' => 'AdminController@updatePassword'
    ]);
    $router->delete('/admin/{admin_id}', [
        'middleware' => [
            'permission:superadmin'
        ], 
        'uses' => 'AdminController@destroy'
    ]);
    
    // ============= PERMISSIONS ================
    $router->get('/permission', [
        'middleware' => [
            'permission:superadmin'
        ], 
        'uses' => 'PermissionController@index'
    ]);
});

// $router->options('/{any:.*}', function (Request $req) {
//     return;
// });