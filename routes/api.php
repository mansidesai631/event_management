<?php

use App\Event;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::group(['namespace' => 'Api\v1'], function () {
        // Users
        Route::post('/register', 'UserController@register')->name('users.register');
        Route::post('/login', 'UserController@login')->name('users.login');

        Route::group(['middleware' => 'jwtauthcheck'], function ($api) {
            Route::get('/logout', 'UserController@logout')->name('users.logout');
            Route::put('/update-password', 'UserController@updatePassword')->name('users.updatePassword');
            Route::put('/reset-password', 'UserController@resetPassword')->name('users.resetPassword');

            Route::post('/events', 'EventController@store')->name('events.store');
            Route::put('/events/{event}/update', 'EventController@update')->name('events.update');
            Route::get('/events/{event}', 'EventController@show')->name('events.show');
            Route::get('/events', 'EventController@index')->name('events.index');
            Route::post('/events/{event}/invite', 'EventController@invite')->name('events.invite');
        });
    });
});

