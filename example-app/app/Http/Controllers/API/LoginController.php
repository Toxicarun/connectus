<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Validator;
use Illuminate\Support\Facades\Auth;
use DB;

class LoginController extends Controller
{

    public function registerconnect(Request $request)
  {

     $user_name=$request->user_name;
    $email = $request->email;
    $phone = $request->phone;


    $validator = Validator::make($request->all(), [
        'name' => 'required',
        'user_name' => 'required|unique:users,user_name',
        'email' => 'required|unique:users,email',
        'phone' => 'required',
        'password' => 'required',
        'c_password' => 'required|same:password',
        ]);

     if ($validator->fails()) {
      return response()->json(['error'=>$validator->errors(), 'status' => False]);            
    }

    $input = $request->all();

    $input['password'] = bcrypt($input['password']);
    $input['status'] = config('constant.ACTIVE');

    $user = User::create($input);



    $success['token'] =  $user->createToken('apiToken')->plainTextToken;

    $result_arr = array('status'=>True,'success' => $success,'user' => $user,'message'=>'User register');

    return json_encode($result_arr); 




  }


    public function loginconnect(Request $request){
        
       
        $validator = Validator::make($request->all(), [
            'login' => 'required',
            'password' => 'required',
            ],
            [
                'login.required' => 'Username or email is required.',
            ]);
            if ($validator->fails()) {
              return response()->json(['error'=>$validator->errors(), 'status' => False]);            
            }

        // $user = User::where('email',$request->email)->first();

        $user = User::where('email', $request->login)
           ->orWhere('user_name', $request->login)
           ->first();

        if(!$user || !Hash::check($request->password,$user->password)){
            return response()->json([
                'message' => 'Invalid Credentials'
            ],401);
        }
       
        // $token = $user->createToken($user->name.'-AuthToken')->plainTextToken;
        $success['token'] = $user->createToken('apiToken')->plainTextToken;
        


        $result_arr = array('status'=>True,'success' => $success,'user' => $user,'message'=>'Logged in');

        return json_encode($result_arr); 

    }


    public function nearbyconnects(Request $request){

        $validator = Validator::make($request->all(), [
            'radius' => 'required|numeric',
            ],
            [
                'radius.required' => 'Radius is required.',
                'radius.numeric' => 'Radius must be a number.',
            ]);
            if ($validator->fails()) {
              return response()->json(['error'=>$validator->errors(), 'status' => False]);            
            }

        $user = Auth::user(); // Get logged-in user

        $latitude = $user->latitude;
        $longitude = $user->longitude;
        $radius = $request->radius;

        $data = DB::table('users')
        ->select(
            'id', 
            'user_name', 
            'latitude', 
            'longitude',
            DB::raw("ROUND(
                6371 * 2 * ASIN(SQRT(
                    POWER(SIN(RADIANS(latitude - {$latitude}) / 2), 2) +
                    COS(RADIANS({$latitude})) * COS(RADIANS(latitude)) *
                    POWER(SIN(RADIANS(longitude - {$longitude}) / 2), 2)
                )), 0
            ) AS distance_km"),
            DB::raw("ROUND(
                6371000 * 2 * ASIN(SQRT(
                    POWER(SIN(RADIANS(latitude - {$latitude}) / 2), 2) +
                    COS(RADIANS({$latitude})) * COS(RADIANS(latitude)) *
                    POWER(SIN(RADIANS(longitude - {$longitude}) / 2), 2)
                )), 2
            ) AS distance_m")
        )
        ->whereNotNull('latitude')  // Ignore NULL latitudes
        ->whereNotNull('longitude') // Ignore NULL longitudes
        ->where('id', '!=', Auth::id()) // Exclude logged-in user
        ->having("distance_km", "<=", $radius)  // Only users within the given radius
        ->orderBy("distance_km")  // Order by closest first
        ->get();

        // Loop through data and check friendship status
foreach ($data as $user) {
    $friendship = DB::table('friendships')
        ->where(function ($query) use ($user) {
            $query->where('sender_id', Auth::id())->where('receiver_id', $user->id)
                  ->orWhere('sender_id', $user->id)->where('receiver_id', Auth::id());
        })
        ->first();

    // Determine friendship status
    if ($friendship) {
        if ($friendship->status == 'accepted') {
            $user->friendship_status = 'friends';
        } elseif ($friendship->status == 'pending' && $friendship->sender_id == Auth::id()) {
            $user->friendship_status = 'request_sent';
        } elseif ($friendship->status == 'pending' && $friendship->receiver_id == Auth::id()) {
            $user->friendship_status = 'request_received';
        } elseif ($friendship->status == 'rejected') {
            $user->friendship_status = 'not_friends'; // Can resend request
        }
    } else {
        $user->friendship_status = 'not_friends';
    }
}

        return response()->json([
          "data"=> $data
        ]);


    }

    public function latlongupdate(Request $request){
        
        $validator = Validator::make($request->all(), [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ], [
            'latitude.required'  => 'Latitude is required.',
            'latitude.numeric'   => 'Latitude must be a number.',
            'latitude.between'   => 'Latitude must be between -90 and 90.',
        
            'longitude.required' => 'Longitude is required.',
            'longitude.numeric'  => 'Longitude must be a number.',
            'longitude.between'  => 'Longitude must be between -180 and 180.',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 'status' => false]);
        }
        
        //  Round latitude and longitude to 9 decimal places before using them
        $latitude = round($request->latitude, 9);
        $longitude = round($request->longitude, 9);

        $user_id = AUTH::user()->id;
        $update_points = User::where('id',$user_id)
                          ->update([
                            'latitude' => $latitude,
                            'longitude'=> $longitude
                                   ]);

        if ($update_points) {
            return response()->json([
                'status' => true,
                'message' => 'Location updated successfully.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update location.',
            ]);
        }


       
    }


    public function logoutconnect(){
        auth()->user()->tokens()->delete();
    
        return response()->json([
          "message"=>"logged out"
        ]);
    }

    public function loginuser(Request $request){
         return "ok";
    }
}
