@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-layer-group mr-1" style="color:#4e73df;"></i> Pengelola Portal HRIS v2
        </h1>
        <div>
            <span class="badge badge-info">{{ $modeLabel }}</span>
            <a href="{{ route('admin.whats-new.index') }}" class="btn btn-sm btn-outline-secondary ml-1">
                <i class="fas fa-bullhorn mr-1"></i>What's New</a>
            <a href="{{ url('/v2') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-external-link-alt mr-1"></i>View Portal</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        {{-- Setelan portal --}}
        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Portal Settings</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('v2.admin.settings.update') }}">
                        @csrf
                        <div class="form-group">
                            <label class="font-weight-bold">Face Verification Mode</label>
                            <select name="hris_v2_verification_mode" class="form-control">
                                <option value="liveness" {{ $mode === 'liveness' ? 'selected' : '' }}>Liveness (live face — default, fail-safe)</option>
                                <option value="random_challenge" {{ $mode === 'random_challenge' ? 'selected' : '' }}>
                                    Random Challenge (motion challenge)
                                </option>
                                <option value="none" {{ $mode === 'none' ? 'selected' : '' }}>
                                    Without Motion Verification (face scan only, no liveness &amp; challenge)
                                </option>
                            </select>
                            <small class="text-muted">The same mode can also be changed in <a href="{{ route('face.attendance.settings.index') }}">/face-attendance/settings</a> (the "Employee Portal Verification Mode (HRIS v2)" card).</small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Login Session Length (minutes)</label>
                            <input type="number" name="session_minutes" class="form-control"
                                   min="0" max="10080" value="{{ $sessionMinutes }}">
                            <small class="text-muted">0 = tanpa batas. Default 480 (8 jam).</small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Judul Portal</label>
                            <input type="text" name="portal_title" class="form-control" maxlength="150"
                                   value="{{ $portalTitle }}">
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Employee Login Page Notes</label>
                            <input type="text" name="employee_login_note" class="form-control" maxlength="255"
                                   value="{{ $loginNote }}">
                        </div>

                        <button class="btn btn-primary btn-block">
                            <i class="fas fa-save mr-1"></i>Save Settings</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Menu portal --}}
        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Portal Menu</h6>
                    <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#modalTambahMenu">
                        <i class="fas fa-plus mr-1"></i> Add Menu
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Order</th><th>Menu</th><th>Route</th><th>Status</th><th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($menus as $m)
                                    @php
                                        $id = is_array($m) ? ($m['id'] ?? null) : ($m->id ?? null);
                                        $label = is_array($m) ? ($m['label'] ?? '') : ($m->label ?? '');
                                        $key = is_array($m) ? ($m['key'] ?? '') : ($m->key ?? '');
                                        $desc = is_array($m) ? ($m['description'] ?? '') : ($m->description ?? '');
                                        $routeName = is_array($m) ? ($m['route_name'] ?? '') : ($m->route_name ?? '');
                                        $icon = is_array($m) ? ($m['icon'] ?? '') : ($m->icon ?? '');
                                        $color = is_array($m) ? ($m['color'] ?? '') : ($m->color ?? '');
                                        $enabled = is_array($m) ? ($m['is_enabled'] ?? false) : ($m->is_enabled ?? false);
                                        $urut = is_array($m) ? ($m['sort_order'] ?? 0) : ($m->sort_order ?? 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $urut }}</td>
                                        <td>
                                            <i class="{{ $icon }} mr-1" style="color:{{ $color }};"></i>
                                            <strong>{{ $label }}</strong>
                                            <div class="small text-muted">{{ $desc }}</div>
                                        </td>
                                        <td><code>{{ $routeName }}</code></td>
                                        <td>
                                            @if($enabled)
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($id)
                                                <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalEditMenu{{ $id }}">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" action="{{ route('v2.admin.menu.destroy', $id) }}" class="d-inline"
                                                      onsubmit="return confirm('Delete the menu {{ $label }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            @else
                                                <span class="small text-muted">built-in menu (not migrated yet)</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Allowed Target Routes</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small">
                        @foreach($routeTersedia as $name => $label)
                            <li><code>{{ $name }}</code> — {{ $label }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal edit menu --}}
@foreach($menus as $m)
    @php
        $id = is_array($m) ? ($m['id'] ?? null) : ($m->id ?? null);
    @endphp
    @if($id)
        <div class="modal fade" id="modalEditMenu{{ $id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('v2.admin.menu.update', $id) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Menu</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        @include('hris_v2._form_menu', ['menu' => $m, 'routeTersedia' => $routeTersedia])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach

{{-- Add menu modal --}}
<div class="modal fade" id="modalTambahMenu" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('v2.admin.menu.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Add Portal Menu</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                @include('hris_v2._form_menu', ['menu' => null, 'routeTersedia' => $routeTersedia])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-success"><i class="fas fa-plus mr-1"></i>Add</button>
            </div>
        </form>
    </div>
</div>
@endsection
