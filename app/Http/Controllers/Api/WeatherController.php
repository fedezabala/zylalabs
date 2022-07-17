<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Weather;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{

    private function _getWeatherFromApi($city=null) {
        if(isset($city)) {
            $response = Http::get('http://api.weatherstack.com/current',[
                'access_key'=>'2dfcf1dece06c15a7ad9be8ed9fc81b2',
                'query' => $city
            ]);

            if(!is_null($response->json('success'))&&$response->json('success')==false) {
                $return = [
                    "status" => 0,
                    "msg" => "API Error",
                    "data" => $response->json('error'),
                    "code" => 500
                ];                
            } else {
                $return = [ 
                    "status" => 1,
                    "msg" => "New/updated record",
                    "data" => $response->json(),
                    "code" => 200
                ];
            }
        } else {
            $return = [
                "status" => 0,
                "msg" => "City can't be empty",
                "data" => "",
                "code" => 404
            ];
        }

        return $return;
    }

    private function _saveOrUpdate(Request $request, $cityId = null) {
        //Query API
        $apiQuery = $this->_getWeatherFromApi($request->input('query'));
        
        if($apiQuery['status']!==0) {
            //Save or update
            if(is_null($cityId)) {
                $weather = new Weather();
                $weather->city = $request->input('query');
                $weather->lastQuery = json_encode($apiQuery['data']);
                $weather->save();
            } else {
                $weather = Weather::find($cityId);
                $weather->update([
                    'lastQuery' => json_encode($apiQuery['data'])
                ]);
            }
        }

        return $apiQuery;
    }

    public function current(Request $request) {

        //db check
        $city = Weather::where("city","=", $request->input('query'))->first();
        if(isset($city->id)) {
            //hour check
            if($city->updated_at->diffInHours(now())>1) {
                //new query and update record
                $apiResponse = $this->_saveOrUpdate($request, $city->id); 
            } else {
                //return saved data
                $apiResponse = [ 
                    "status" => 1,
                    "msg" => "Saved record",
                    "data" => json_decode($city->lastQuery),
                    "code" => 200
                ];
            }
        } else {
            //new query and new record
            $apiResponse = $this->_saveOrUpdate($request); 
        }

        return response()->json($apiResponse,$apiResponse['code']);
    }
}
