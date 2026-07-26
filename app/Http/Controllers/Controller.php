<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Permite usar $this->authorize() en los controladores, para que la
    // autorizacion se resuelva en el servidor y no dependa de ocultar enlaces.
    use AuthorizesRequests;
}
