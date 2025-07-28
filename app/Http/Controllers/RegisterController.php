<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;

class RegisterController extends Controller
{
    public function index()
    {
        return view('auth.register');
    }

    public function create()
    {
        return view('auth.registration');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if (Auth::attempt(['email' => $user->email, 'password' => $request->password])) {
            $request->session()->regenerate();

            Alert::success('Create Successfully!', 'User ' . $request->name . ' successfully created!');
            return redirect()->intended('home');
        }
    }

    public function storeAuth(Request $request)
    {
        $this->validate($request, [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'dept' => $request->dept,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Alert::success('Create Successfully!', 'User ' . $request->name . ' successfully created!');
        return redirect()->intended('user/index');
    }
}
