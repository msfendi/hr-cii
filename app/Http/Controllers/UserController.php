<?php

namespace App\Http\Controllers;

use App\Models\ModelHasRoles;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;

class UserController extends Controller
{
    public function index()
    {
        $users   = User::all();
        return view('user.index', compact('users'));
    }

    public function detail($id)
    {
        $users = User::find($id);
        return view('auth.detail', ['users' => $users]);
    }

    public function delete($id)
    {
        $users = User::find($id);
        $users->delete();
        Alert::success('Delete Successfully!', 'User ' . $users->name . ' successfully deleted!');
        return redirect()->intended('user/index');
    }

    public function assign($id)
    {
        $modelhasroles = User::select('users.name', 'users.email', 'users.id', 'model_has_roles.*')
            ->leftJoin('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->where('users.id', '=', $id)
            ->get();
        $roles = Role::all();
        return view('auth.assign', compact('modelhasroles', 'roles'));
    }

    public function assignrole(Request $request)
    {
        $modelhasroles = ModelHasRoles::where('model_id', '=', $request->id)
            ->where('model_type', '=', 'App\Models\User')
            ->delete();
        ModelHasRoles::updateOrCreate(
            [
                'role_id' => $request->role_id,
                'model_type' => 'App\Models\User',
                'model_id' => $request->id
            ]
        );

        Alert::success('Assign Successfully!', 'User successfully assigned!');
        return redirect()->intended('user/index');
    }

    public function update(Request $request)
    {
        $user = User::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|',
            'dept' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user->fill([
            'name' => $request->name,
            'dept' => $request->dept,
            'email' => $request->email,
        ]);

        $user->save();

        Alert::success('Update Successfully!', 'User ' . $request->name . ' successfully updated!');
        return redirect()->intended('user/index');
    }
}
