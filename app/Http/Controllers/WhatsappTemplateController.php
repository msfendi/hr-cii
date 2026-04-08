<?php

namespace App\Http\Controllers;

use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;

class WhatsappTemplateController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $templates = WhatsappTemplate::latest()->get();

        return view('whatsapp.templates.index', compact('templates'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE FORM
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('whatsapp.templates.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'message' => 'required',
        ]);

        WhatsappTemplate::create([
            'name' => $request->name,
            'message' => $request->message,
        ]);

        return redirect()
            ->route('templates.index')
            ->with('success', 'Template berhasil dibuat');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT FORM
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $template = WhatsappTemplate::findOrFail($id);

        return view('whatsapp.templates.edit', compact('template'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $template = WhatsappTemplate::findOrFail($id);

        $request->validate([
            'name' => 'required',
            'message' => 'required',
        ]);

        $template->update([
            'name' => $request->name,
            'message' => $request->message,
        ]);

        return redirect()
            ->route('templates.index')
            ->with('success', 'Template berhasil diupdate');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        WhatsappTemplate::findOrFail($id)->delete();

        return redirect()
            ->route('templates.index')
            ->with('success', 'Template berhasil dihapus');
    }
}
