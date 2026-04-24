<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;

class LoginController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $username = Auth::user()->name;

            Alert::success('Login Successfully!', 'Welcome To Chutex HRIS Sistem');
            return redirect()->intended('/home');
        }
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout()
    {
        $username = Auth::user()->name;
        Auth::logout();
        Alert::success('Logout Successfully!', 'See You Next Time');
        return redirect('/login');
    }

    public function qrauth(Request $request)
    {
        $npk = $request->qrcode;

        if (!preg_match('/^C-\d{5}$/', $npk)) {
            return back()->with('error', 'Format NPK salah');
        }

        // cari user
        $user = User::where('npk', $npk)->first();

        if (!$user) {
            return back()->with('error', 'User tidak ditemukan');
        }

        // login user
        Auth::login($user);

        $request->session()->regenerate();

        Alert::success(
            'Login Successfully!',
            'Welcome To Chutex HRIS'
        );

        return redirect()->intended('/home');
    }
}
