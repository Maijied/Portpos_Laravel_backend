<?php

namespace App\Helper;

class ApiHelper {

    public function jsonResponse($message, $status, $code, $data){
        $response = [
            'message'  => $message,
            'status'   => $status,
            'code'     => $code,
            'data'     => $data
        ];
        return response()->json($response);
    }
}
