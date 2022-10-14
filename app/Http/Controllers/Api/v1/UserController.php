<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;
use App\User;
use JWTAuth;
use Illuminate\Support\Str;
use Validator;
use DB;
use DateTime;
use Auth;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->all();

        // Build the validation constraint set.
        $rules = [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->messages();

            $content = array(
                'success' => false,
                'data'    => null,
                'message' => $messages,
                'href' =>  $request->path()
            );

            return response()->json($content)->setStatusCode(400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'api_key' => sha1(uniqid(mt_rand(), true)),
        ]);

        return new UserResource($user);
    }
    public function login(Request $request)
    {

        $validatedData = $request->validate([
            'email' => 'required|email|max:255|exists:users',
            'password' => 'required|string|min:6',
        ]);
        // grab credentials from the request
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                $content = [
                    'success' => false,
                    'error' => 'Invalid e-mail address or password.',
                ];

                return response()->json($content)->setStatusCode(401);
            }
        } catch (JWTException $e) {
            $content = [
                'success' => false,
                'error' => 'JWT token could not create token.',
            ];

            // something went wrong
            return response()->json($content)->setStatusCode(500);
        }

        $content = [
            'success' => true,
            'token' => $token,
        ];

        return response()->json($content)->withCookie(cookie('jwt_auth', $token, config('jwt.refresh_ttl', 20160), env('JWT_PATH', '/')))->withCookie(cookie('jwt_auth', $token, -1, '/event'))->setStatusCode(200);
    }
    public function logout(Request $request)
    {
       $token = $request->cookie('jwt_auth');

        // Check JWT auth header in cookie
        if ($token) {
            $cookie = \Cookie::forget('jwt_auth');
            $content = [
                'success' => true,
                'message' => 'User logged out Successfully.',
            ];

            return response()->json($content)->withCookie($cookie)->setStatusCode(200);
        } else {
            $content = [
                'success' => false,
                'error' => 'Cookie not found.',
            ];

            return response()->json($content)->setStatusCode(500);
        }
    }

    public function resetPassword(Request $request)
    {
        return $this->updatePassword($request);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->all();
        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|string|min:6',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->messages();
            $content = array(
                'success' => false,
                'data'    => null,
                'message' => $messages,
                'href' =>  $request->path()
            );
            return response()->json($content)->setStatusCode(400);
        }
        if (!(Hash::check($request->get('current_password'), Auth::user()->password))) {
            $content = [
                'success' => false,
                'data' => null,
                'message' => 'Your current password does not matches with the password you provided. Please try again.',
                'href' =>  $request->path()
            ];
            return response()->json($content)->setStatusCode(400);
        }

        if(strcmp($request->get('current_password'), $request->get('new_password')) == 0){
            $content = [
                'success' => false,
                'data' => null,
                'message' => 'New Password cannot be same as your current password. Please choose a different password.',
                'href' =>  $request->path()
            ];
            return response()->json($content)->setStatusCode(400);
        }

        User::where('id', '=', Auth::user()->id)->update([
            'password' => Hash::make($data['new_password']),
        ]);

        $user = User::where('id', Auth::user()->id)->first();
        $payloadable = [
            'api_key' => $user->api_key,
        ];

        try {
            // verify the credentials and create a token for the user
            $credentials = ['email' => $user->email, 'password' => $data['new_password']];
            if (!$token = JWTAuth::attempt($credentials)) {

                $content = [
                    'success'      => false,
                    'message'      => 'Invalid e-mail address or password.',
                    'href' =>  $request->path()
                ];

                return response()->json($content)->setStatusCode(401);
            }
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        $content = [
            'success' => true,
            'token' => $token,
            'message'   => 'Password changed successfully.',
        ];

        return response()->json($content)->withCookie(cookie('jwt_auth', $token, config('jwt.refresh_ttl', 20160), env('JWT_PATH', '/')))->withCookie(cookie('jwt_auth', $token, -1, '/event'))->setStatusCode(200);
    }
}
