<?php

namespace App\Http\Controllers;

use App\Helper\ApiHelper;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Auth;
use Validator;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct()
    {
//        $this->middleware('auth:api', ['except' => ['login']]);
    }

    public function signup(Request $request)
    {
        //Validate Unique Email & Name
        $validation = Validator::make($request->all(), [
            'name'=>'required',
            'email'=>'required|email|unique:users',
        ]);
        if ($validation->fails()) {
            \Log::info($validation->errors());
            return ApiHelper::jsonResponse('All fields are required!', 'Not Found', 404, NULL);
        }
        //Password Validation
        $passwordValidator = Validator::make($request->all(), [
            'password'=>'required|min:8|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%]).*$/',
            'password_Confirmation'=>'required|min:8|same:password|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%]).*$/',
        ]);
        if ($passwordValidator->fails()) {
            Log::info($passwordValidator->errors());
            return ApiHelper::jsonResponse('Password Must Be 8-16 Characters & Contain Numbers, One Upper Case Character, One Lower Case Characters And One Special Character or The Passwords You Entered Do Not Match!! ', 'Not Found', 404, NULL);
        }
        //Insert Data
        try {
            $insertNewUser = new User();
            $insertNewUser->name = $request->name;
            $insertNewUser->email = $request->email;
            $insertNewUser->password = $request->password;
            $insertNewUser->save();
            return ApiHelper::jsonResponse('User Added Successfully!', 'OK', 200, NULL);
        } catch (\Exception $e) {
            \Log::info($e);
            return ApiHelper::jsonResponse('Something went wrong!', 'Not Found', 404, NULL);
        }

    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if ($token = $this->guard()->attempt($credentials)) {
            return $this->respondWithToken($token);
        }

        return response()->json(['error' => 'Unauthorized! Email or Password did not match'], 401);
    }

    public function logout()
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60
        ]);
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\Guard
     */
    public function guard()
    {
        return Auth::guard();
    }
}
