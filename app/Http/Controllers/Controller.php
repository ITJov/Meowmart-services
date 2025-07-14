<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function sendResponse($result, $message)
{
    return response()->json([
        'success' => true,
        'data' => $result,
        'message' => $message,
    ], 200);
}

protected function sendError($error, $errorMessages = [], $code = 404)
{
    return response()->json([
        'success' => false,
        'message' => $error,
        'data' => $errorMessages
    ], $code);
}

}
