<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;





Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('/loginconnect','App\Http\Controllers\API\LoginController@loginconnect'); 
Route::post('/registerconnect','App\Http\Controllers\API\LoginController@registerconnect');

Route::group(['middleware' => ['auth:api']], function() {
    Route::post('/logoutconnect','App\Http\Controllers\API\LoginController@logoutconnect');
    Route::post('/nearbyconnects','App\Http\Controllers\API\LoginController@nearbyconnects'); 
    Route::post('/latlongupdate','App\Http\Controllers\API\LoginController@latlongupdate');


    Route::post('/friend-request/{receiverId}','App\Http\Controllers\API\FriendshipController@sendRequest');
    Route::post('/accept-request/{id}','App\Http\Controllers\API\FriendshipController@acceptRequest');
    Route::post('/reject-request/{id}','App\Http\Controllers\API\FriendshipController@rejectRequest');
    Route::get('/sent-requests','App\Http\Controllers\API\FriendshipController@sentRequests');
    Route::get('/received-requests','App\Http\Controllers\API\FriendshipController@receivedRequests');
    Route::get('/friends-list','App\Http\Controllers\API\FriendshipController@friendsList');
    Route::delete('/unfriend/{friendId}','App\Http\Controllers\API\FriendshipController@unfriend');

    Route::get('/myprofile','App\Http\Controllers\API\FriendshipController@myprofile');
    Route::post('/send-message','App\Http\Controllers\API\FriendshipController@sendMessage'); 
    Route::get('/getMessages','App\Http\Controllers\API\FriendshipController@getMessages');  
    Route::get('/getChatUsers','App\Http\Controllers\API\FriendshipController@getChatUsers');
});
