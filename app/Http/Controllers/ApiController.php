<?php

namespace App\Http\Controllers;

use App\Model\Member;
use App\Model\Menu;
use App\Model\MenuCategory;
use App\Model\Order;
use App\Model\OrderGood;
use App\Model\Shop;
use App\Model\ShopCart;
use App\Model\ShopUser;
use App\Model\UserSite;
use App\SignatureHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class ApiController extends Controller
{

//    public function __construct()
//    {
//        $this->middleware('auth', [
//            'except' => ['cart', 'address']
//        ]);
//    }

    //获得商家列表接口
    public function shops(Request $request)
    {
        if(!Redis::get('shops')){
        $where = [];
        if ($request->keyword) {
            $where[] = ['shop_name', 'like', "%$request->keyword%"];
        }
        $shops = Shop::where($where)->get()->makeHidden(['created_at', 'updated_at', 'shop_category_id']);
        foreach ($shops as &$shop) {
            $shop['distance'] = mt_rand(1, 999);
            $shop['estimate_time'] = mt_rand(20, 100);
        }

            Redis::set('shops',json_encode($shops));
        }

        return Redis::get('shops');
    }

    //获得指定商家接口
    public function getshop(Request $request)
    {
        if(!Redis::get('shop')){
        $shop = Shop::where('id', $request->id)->first();
        $shop['service_code'] = 4.5;// 服务总评分
        $shop['foods_code'] = 4.4;// 食物总评分
        $shop['high_or_low'] = true;// 低于还是高于周边商家
        $shop['h_l_percent'] = 30;// 低于还是高于周边商家的百分比
        $shop['distance'] = mt_rand(1, 999);
        $shop['estimate_time'] = mt_rand(20, 100);
        unset($shop['shop_category_id']);
        unset($shop['created_at']);
        unset($shop['updated_at']);

        $menucategorys = MenuCategory::where('shop_id', $request->id)
            ->get(['id', 'description', 'is_selected', 'name', 'type_accumulation']);

        //  dd($menucategorys);

        foreach ($menucategorys as &$menucategory) {
            $menus = Menu::where(['shop_id' => $request->id, 'category_id' => $menucategory->id])->get();
            unset($menucategory['id']);
            foreach ($menus as &$menu) {
                $menu['goods_id'] = $menu->id;
                unset($menu['id']);
                unset($menu['shop_id']);
                unset($menu['category_id']);
                unset($menu['created_at']);
                unset($menu['updated_at']);
            }
            $menucategory['goods_list'] = $menus;
        }
        $shop['evaluate'] = [//评价
            [
                "user_id" => 12344,
                "username" => "w******k",
                "user_img" => "http://www.homework.com/images/slider-pic4.jpeg",
                "time" => "2017-2-22",
                "evaluate_code" => 1,
                "send_time" => 30,
                "evaluate_details" => "不怎么好吃"
            ]
        ];
        $shop['commodity'] = $menucategorys;//店铺商品

         Redis::set('shop',json_encode($shop));
        }
        return Redis::get('shop');
    }

    //获取短信验证码接口
    public function sms(Request $request)
    {
        $params = array();

        // *** 需用户填写部分 ***

        // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
        $accessKeyId = "LTAIbg2CqC4uvjtx";
        $accessKeySecret = "oVC8Do00RtZVs7PhwNHXKGRkROEviD";

        // fixme 必填: 短信接收号码
        $params["PhoneNumbers"] = $request->tel;

        // fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $params["SignName"] = "袁静鹏";

        // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $params["TemplateCode"] = "SMS_140595019";

        // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
        $code = random_int(1000, 9999);
        Redis::setex('code_' . $request->tel, 600, $code);
        $params['TemplateParam'] = Array(
            "code" => $code,
            //"product" => "阿里通信"
        );

        // fixme 可选: 设置发送短信流水号
        $params['OutId'] = "12345";

        // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
        $params['SmsUpExtendCode'] = "1234567";


        // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
        if (!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
            $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }

        // 初始化SignatureHelper实例用于设置参数，签名以及发送请求
        $helper = new SignatureHelper();

        // 此处可能会抛出异常，注意catch
        try {
            $content = $helper->request(
                $accessKeyId,
                $accessKeySecret,
                "dysmsapi.aliyuncs.com",
                array_merge($params, array(
                    "RegionId" => "cn-hangzhou",
                    "Action" => "SendSms",
                    "Version" => "2017-05-25",
                ))
            // fixme 选填: 启用https
            // ,true
            );
        } catch (\Exception $e) {
            return ['status' => 0, 'message' => '验证码发送失败'];
        }

        return ['status' => 1, 'message' => '验证码发送成功'];
    }

    //注册接口
    public function regist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:members',
            'tel' => 'required|unique:members|regex:/^1\d{10}$/',
            'sms' => 'required',
            'password' => 'required|between:6,16',
        ], [
            'username.required' => '用户名不能为空',
            'username.unique' => '用户名已存在',
            'tel.required' => '电话不能为空',
            'tel.unique' => '该号码已被注册',
            'tel.regex' => '请输入正确的号码',
            'sms.unique' => '验证码不能为空',
            'password.required' => '密码不能为空',
            'password.between' => '密码必须为6-16位',
        ]);
        if ($validator->fails()) {
            return ['status' => "false", 'message' => $validator->errors()->first()];
        }
        if (Redis::get('code_' . $request->tel) != $request->sms) {
            return ['status' => "false", 'message' => "验证码错误"];
        }
        $data = $request->all();
        $data['password'] = bcrypt($request->password);
        Member::create($data);
        return ['status' => "true", 'message' => '注册成功'];
    }

    //登录接口
    public function loginCheck(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'password' => 'required',
        ], [
            'name.required' => '用户名不能为空',
            'password.required' => '密码不能为空',
        ]);
        if ($validator->fails()) {
            return ['status' => "false", 'message' => $validator->errors()->first()];
        }

        if (Auth::attempt(['username' => $request->name, 'password' => $request->password])) {
            $success = [
                'status' => "true",
                'message' => '登录成功',
                'user_id' => Auth::user()->id,
                'username' => Auth::user()->username,
            ];
        } else {
            $success = [
                'status' => "false",
                'message' => '登录失败',
                'user_id' => '',
                'username' => '',
            ];
        }
        return $success;
    }

    //地址列表接口
    public function addressList()
    {
        $address = UserSite::where('user_id', Auth::user()->id)->get(['id', 'province as provence', 'city', 'county as area', 'address as detail_address', 'name', 'tel']);
        return $address;
    }

    //指定地址接口
    public function address(Request $request)
    {
        $address = UserSite::where(['user_id' => Auth::user()->id, 'id' => $request->id])->get(['id', 'province as provence', 'city', 'county as area', 'address as detail_address', 'name', 'tel'])->first();
        return $address;
    }

    //保存新增地址接口
    public function addAddress(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'tel' => 'required',
            'provence' => 'required',
            'city' => 'required',
            'area' => 'required',
            'detail_address' => 'required',
        ], [
            'name.required' => '名字不能为空',
            'tel.required' => '电话不能为空',
            'provence.required' => '省不能为空',
            'city.required' => '市不能为空',
            'area.required' => '县不能为空',
            'detail_address.required' => '详细地址不能为空',
        ]);
        if ($validator->fails()) {
            return ['status' => "false", 'message' => $validator->errors()->first()];
        }

        $data = [
            'name' => $request->name,
            'tel' => $request->tel,
            'province' => $request->provence,
            'city' => $request->city,
            'county' => $request->area,
            'address' => $request->detail_address,
            'user_id' => Auth::user()->id,
            'is_default' => 0,
        ];
        UserSite::create($data);
        return ['status' => "true", 'message' => '添加成功'];
    }


    //保存修改地址接口
    public function editAddress(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'tel' => 'required',
            'provence' => 'required',
            'city' => 'required',
            'area' => 'required',
            'detail_address' => 'required',
        ], [
            'name.required' => '名字不能为空',
            'tel.required' => '电话不能为空',
            'provence.required' => '省不能为空',
            'city.required' => '市不能为空',
            'area.required' => '县不能为空',
            'detail_address.required' => '详细地址不能为空',
        ]);
        if ($validator->fails()) {
            return ['status' => "false", 'message' => $validator->errors()->first()];
        }

        $data = [
            'name' => $request->name,
            'tel' => $request->tel,
            'province' => $request->provence,
            'city' => $request->city,
            'county' => $request->area,
            'address' => $request->detail_address,
        ];
        $address = UserSite::find($request->id);
        $address->update($data);
        return ['status' => "true", 'message' => '修改成功'];
    }


    //保存购物车接口
    public function addCart(Request $request)
    {
        $goodsList = $request->goodsList;
        $goodsCount = $request->goodsCount;
        $user_id = Auth::user()->id;
        $data = [];
        foreach ($goodsList as $key => $list) {
            $data[] = ['user_id' => $user_id, 'goods_id' => $list, 'amount' => $goodsCount[$key]];
        }
        ShopCart::where('user_id', $user_id)->delete();
        DB::table('shop_carts')->insert($data);

        return ["status" => "true", "message" => "添加成功"];
    }

    //获取购物车数据接口
    public function cart()
    {
        $carts = ShopCart::where('user_id', Auth::user()->id)->get();
        $totalCost = 0;
        foreach ($carts as $cart) {
            $good = Menu::where('id', $cart->goods_id)->get(['id as goods_id', 'goods_name', 'goods_img', 'goods_price'])->first();
            $good['amount'] = $cart->amount;
            $totalCost += $good->goods_price * $cart->amount;
            $goods[] = $good;
        }
        return ['goods_list' => $goods, 'totalCost' => $totalCost];
    }


    //添加订单接口
    public function addOrder(Request $request)
    {

        $carts = ShopCart::where('user_id', Auth::user()->id)->get();
        //订单价格
        $data = UserSite::where('id', $request->address_id)
            ->where('user_id',Auth::id())
            ->get(['user_id', 'province', 'city', 'county', 'address', 'tel', 'name'])->first();
        $data['sn'] = date('YmdHis').random_int(10000,99999);
        $total = 0;
        foreach ($carts as $cart) {
            $good = Menu::where('id', $cart->goods_id)->get(['id as goods_id', 'goods_name', 'goods_img', 'goods_price', 'shop_id'])->first();
            $good['amount'] = $cart->amount;
            $total += $good->goods_price * $cart->amount;
            //商家id
            $shop_id = $good->shop_id;
            //商品id
            $goods[] = $good;
        }
        $data['shop_id'] = $shop_id;
        $data['total'] = $total;
        $data['status'] = 0;

        $data['out_trade_no'] = str_random(16);
        $_SERVER['email']=ShopUser::where('shop_id',$shop_id)->first()->email;
        DB::beginTransaction();
        try {
            $create = Order::create($data->toArray());
            $order_id = $create->id;
            foreach ($goods as $good) {
                $good['order_id'] = $order_id;
                unset($good['shop_id']);
                OrderGood::create($good->toArray());
            }
            DB::commit();
            ShopCart::where('user_id', Auth::user()->id)->delete();

            $params = array();

            // *** 需用户填写部分 ***

            // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
            $accessKeyId = "LTAIbg2CqC4uvjtx";
            $accessKeySecret = "oVC8Do00RtZVs7PhwNHXKGRkROEviD";

            // fixme 必填: 短信接收号码
            $params["PhoneNumbers"] = $data['tel'];

            // fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
            $params["SignName"] = "袁静鹏";

            // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
            $params["TemplateCode"] = "SMS_141140001";

            // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
            $params['TemplateParam'] = Array(
                "name" => "饿了吧",
                //"product" => "阿里通信"
            );

            // fixme 可选: 设置发送短信流水号
            $params['OutId'] = "12345";

            // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
            $params['SmsUpExtendCode'] = "1234567";


            // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
            if (!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
                $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
            }

            // 初始化SignatureHelper实例用于设置参数，签名以及发送请求
            $helper = new SignatureHelper();

            // 此处可能会抛出异常，注意catch
                $content = $helper->request(
                    $accessKeyId,
                    $accessKeySecret,
                    "dysmsapi.aliyuncs.com",
                    array_merge($params, array(
                        "RegionId" => "cn-hangzhou",
                        "Action" => "SendSms",
                        "Version" => "2017-05-25",
                    ))
                // fixme 选填: 启用https
                // ,true
                );

            $r =\Illuminate\Support\Facades\Mail::send('email', ['user'=>'zhangsan'], function ($message) {
                $message->from('18202840880@163.com', '饿了吧通知');
                $message->to([$_SERVER['email']])->subject('有新订单产生');
            });

            return ['status' => "true", 'message' => '添加成功', 'order_id' => $order_id];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['status' => "false", 'message' => '添加失败'];
        }

    }


    // 获得指定订单接口
    public function order(Request $request)
    {
        $order=Order::find($request->id);
        $shop=Shop::find($order->shop_id);
            $data['id']=$order->id;
            $data['order_code']=$order->sn;
            $data['order_birth_time']=(string)$order->created_at;
            $data['order_status']=$order->status;
            $data['shop_id']=$order->shop_id;
            $data['shop_name']=$shop->shop_name;
            $data['shop_img']=$shop->shop_img;
            $ordergoods=OrderGood::where('order_id',$request->id)->get();
            $order_price=0;
            $goods=[];
            foreach ($ordergoods as $ordergood){
                $good=Menu::find($ordergood->goods_id);
                $goods[]=[
                    'goods_id'=>$good->id,
                    'goods_name'=>$good->goods_name,
                    'goods_img'=>$good->goods_img,
                    'goods_price'=>$good->goods_price,
                    'amount'=>$ordergood->amount,
                ];
                $order_price+=$good->goods_price*$ordergood->amount;
            }
            $data['goods_list']=$goods;
            $data['order_price']=$order_price;
            $data['order_address']=$order->address.$order->address;
            return $data;
    }

    // 获得订单列表接口
    public function orderList()
    {
        $orders=Order::where('user_id',Auth::user()->id)->get();
        $orderList=[];
        foreach ($orders as $order){
            $shop=Shop::find($order->shop_id);
            $data['id']=$order->id;
            $data['order_code']=$order->sn;
            $data['order_birth_time']=(string)$order->created_at;
            $status=['-1'=>'已取消','0'=>'待支付','1'=>'待发货','2'=>'待确认','3'=>'完成'];
            $data['order_status']=$status[$order->status];
            $data['shop_id']=$order->shop_id;
            $data['shop_name']=$shop->shop_name;
            $data['shop_img']=$shop->shop_img;
            $ordergoods=OrderGood::where('order_id',$order->id)->get();
            $order_price=0;
            $goods=[];
            foreach ($ordergoods as $ordergood){
                $good=Menu::find($ordergood->goods_id);
               $goods[]=[
                    'goods_id'=>$good->id,
                    'goods_name'=>$good->goods_name,
                    'goods_img'=>$good->goods_img,
                    'goods_price'=>$good->goods_price,
                    'amount'=>$ordergood->amount,
                ];
                $order_price+=$good->goods_price*$ordergood->amount;
            }
            $data['goods_list']=$goods;
            $data['order_price']=$order_price;
            $data['order_address']=$order->address.$order->address;
            $orderList[]=$data;
        }
        return $orderList;
    }


    // 修改密码接口
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'oldPassword'=>'required',
            'newPassword'=>'required',
        ], [
            'oldPassword.required'=>'旧密码不能为空',
            'newPassword.required'=>'新密码不能为空',
        ]);
        if ($validator->fails()) {
            return ['status' => "false", 'message' => $validator->errors()->first()];
        }

        $user=Auth::user();
        if (Hash::check($request->oldPassword, $user->password)) {
            $user->update(['password'=>bcrypt($request->newPassword)]);
            return ['status' => 'true', 'message' => '修改成功'];
        }else{
            return ['status' => 'false', 'message' => '修改失败'];
        }
    }

    // 忘记密码接口
    public function forgetPassword(Request $request)
    {
        $tel=$request->tel;
        $sms=$request->sms;
        $password=$request->password;
        $validator = Validator::make($request->all(), [
            'tel'=>'required',
            'sms'=>'required',
            'password'=>'required',
        ], [
            'tel.required'=>'电话不能为空',
            'sms.required'=>'验证码不能为空',
            'password.required'=>'新密码不能为空',
        ]);
        if ($validator->fails()) {
            return ['status' => "false", 'message' => $validator->errors()->first()];
        }
        $count=Member::where('tel',$tel)->count();
        if($count==0){
            return ['status' => "false", 'message' => "手机号错误"];
        }
        if (Redis::get('code_' . $request->tel) != $sms) {
            return ['status' => "false", 'message' => "验证码错误"];
        }
        $member=Member::where('tel',$tel)->first();
        $member->update(['password'=>bcrypt($password)]);
        return ['status' => "true", 'message' => "重置成功"];
    }

}
