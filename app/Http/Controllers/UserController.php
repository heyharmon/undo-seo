<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return response()->json($users);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }
}
