@php
    $id = $menu ? (is_array($menu) ? ($menu['id'] ?? null) : ($menu->id ?? null)) : null;
    $key = $menu ? (is_array($menu) ? ($menu['key'] ?? '') : ($menu->key ?? '')) : '';
    $label = $menu ? (is_array($menu) ? ($menu['label'] ?? '') : ($menu->label ?? '')) : '';
    $desc = $menu ? (is_array($menu) ? ($menu['description'] ?? '') : ($menu->description ?? '')) : '';
    $routeName = $menu ? (is_array($menu) ? ($menu['route_name'] ?? '') : ($menu->route_name ?? '')) : '';
    $icon = $menu ? (is_array($menu) ? ($menu['icon'] ?? 'fas fa-circle') : ($menu->icon ?? 'fas fa-circle')) : 'fas fa-circle';
    $color = $menu ? (is_array($menu) ? ($menu['color'] ?? '#4e73df') : ($menu->color ?? '#4e73df')) : '#4e73df';
    $enabled = $menu ? (is_array($menu) ? ($menu['is_enabled'] ?? true) : ($menu->is_enabled ?? true)) : true;
    $urut = $menu ? (is_array($menu) ? ($menu['sort_order'] ?? 0) : ($menu->sort_order ?? 0)) : 99;
@endphp

@if(! $id)
    <div class="form-group">
        <label class="font-weight-bold">Key <span class="text-danger">*</span></label>
        <input type="text" name="key" class="form-control" maxlength="60" placeholder="e.g. payslip" required>
        <small class="text-muted">Unique menu identifier (letters, numbers, hyphens).</small>
    </div>
@else
    <input type="hidden" name="key" value="{{ $key }}">
    <div class="form-group">
        <label class="font-weight-bold">Key</label>
        <input type="text" class="form-control" value="{{ $key }}" disabled>
    </div>
@endif

<div class="form-group">
    <label class="font-weight-bold">Label <span class="text-danger">*</span></label>
    <input type="text" name="label" class="form-control" maxlength="120" value="{{ $label }}" required>
</div>

<div class="form-group">
    <label class="font-weight-bold">Deskripsi</label>
    <input type="text" name="description" class="form-control" maxlength="255" value="{{ $desc }}">
</div>

<div class="form-group">
    <label class="font-weight-bold">Target Route <span class="text-danger">*</span></label>
    <select name="route_name" class="form-control" required>
        <option value="">-- select route --</option>
        @foreach($routeTersedia as $name => $lbl)
            <option value="{{ $name }}" {{ $routeName === $name ? 'selected' : '' }}>{{ $name }} — {{ $lbl }}</option>
        @endforeach
    </select>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label class="font-weight-bold">Ikon (FontAwesome)</label>
        <input type="text" name="icon" class="form-control" maxlength="60" value="{{ $icon }}">
    </div>
    <div class="form-group col-md-4">
        <label class="font-weight-bold">Color</label>
        <input type="color" name="color" class="form-control" value="{{ $color }}">
    </div>
    <div class="form-group col-md-4">
        <label class="font-weight-bold">Urutan</label>
        <input type="number" name="sort_order" class="form-control" min="0" max="999" value="{{ $urut }}">
    </div>
</div>

<div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="enabled{{ $id ?? 'baru' }}" {{ $enabled ? 'checked' : '' }}>
    <label class="form-check-label" for="enabled{{ $id ?? 'baru' }}">Show this menu in the Employee Portal</label>
</div>
