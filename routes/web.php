<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Route::get('/', function () {
//    return view('welcome');
//});

Route::prefix('api')->group(function () {
    //获得商家列表接口
    Route::get('shops','ApiController@shops');
    //获得指定商家接口
    Route::get('getshop','ApiController@getshop');
    //注册接口
    Route::post('regist','ApiController@regist');
    //登录接口
    Route::post('loginCheck','ApiController@loginCheck');
    //获取短信验证码接口
    Route::get('sms','ApiController@sms');
    //地址列表接口
    Route::get('addressList','ApiController@addressList');
    // 指定地址接口
    Route::get('address','ApiController@address');
    // 保存新增地址接口
    Route::post('addAddress','ApiController@addAddress');
    // 保存修改地址接口
    Route::post('editAddress','ApiController@editAddress');
    // 保存购物车接口
    Route::post('addCart','ApiController@addCart');
    // 获取购物车数据接口
    Route::get('cart','ApiController@cart');
    //添加订单接口
    Route::post('addOrder','ApiController@addOrder');
    // 获得订单列表接口
    Route::get('orderList','ApiController@orderList');
    // 获得指定订单接口
    Route::get('order','ApiController@order');
    // 修改密码接口
    Route::post('changePassword','ApiController@changePassword');
    // 忘记密码接口
    Route::post('forgetPassword','ApiController@forgetPassword');
});
