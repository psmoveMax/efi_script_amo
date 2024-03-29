<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\webhookServices;
class EfIController extends Controller
{
    private $webhookServices;
    public function __construct()
    {
        $this->webhookServices = new webhookServices();
    }
    public function entity_create(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->webhookServices->entity_create($request);
    }

    public function entity_edit(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->webhookServices->entity_edit($request);
    }


}

