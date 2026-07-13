<!-- resources/views/food_menu/edit.blade.php -->
<!DOCTYPE html>
<html lang="en">
  @include('layout.header')
  <body id="page-top">
  @include('sweetalert::alert')
  <div id="wrapper">
  @include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        @include('layout.navbar')
        <div class="container-fluid">

          <link rel="preconnect" href="https://fonts.googleapis.com">
          <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

          <style>
            .fm-kiosk{ --fm-red:#E8442C; --fm-red-dark:#C4331F; --fm-yellow:#FFC933; --fm-green:#2FA66B;
              --fm-dark:#2B2118; --fm-cream:#FFF8EE; --fm-white:#FFFFFF; --fm-gray:#8C8072; --fm-border:#F0E4D4;
              --fm-shadow:0 10px 30px rgba(43,33,24,.08); font-family:'Inter',sans-serif; color:var(--fm-dark); }
            .fm-kiosk h1,.fm-kiosk h2,.fm-kiosk h3,.fm-kiosk .fm-display{ font-family:'Baloo 2',sans-serif; }

            .fm-head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
            .fm-head h1{ font-size:1.9rem; font-weight:800; margin-bottom:2px; }
            .fm-head p{ color:var(--fm-gray); margin:0; font-size:.92rem; }
            .fm-back{ background:var(--fm-white); border:1.5px solid var(--fm-border); color:var(--fm-dark); font-weight:600;
              padding:9px 18px; border-radius:12px; font-size:.85rem; }
            .fm-back:hover{ background:var(--fm-cream); color:var(--fm-dark); }

            .fm-layout{ display:flex; gap:28px; align-items:flex-start; }
            .fm-preview-col{ flex:0 0 320px; position:sticky; top:20px; }
            .fm-form-col{ flex:1; min-width:0; }

            .fm-kiosk-screen{ background:var(--fm-dark); border-radius:28px; padding:14px 14px 20px; box-shadow:var(--fm-shadow); }
            .fm-kiosk-screen::before{ content:""; display:block; width:46px; height:5px; background:#4A3B2C; border-radius:6px; margin:0 auto 12px; }
            .fm-kiosk-photo{ background:var(--fm-cream); border-radius:18px; height:190px; position:relative; overflow:hidden;
              display:flex; align-items:center; justify-content:center; border:2px dashed #E7D9C3; }
            .fm-kiosk-photo img{ width:100%; height:100%; object-fit:cover; }
            .fm-photo-placeholder{ color:#C9B79C; text-align:center; font-size:.8rem; }
            .fm-photo-placeholder i{ display:block; font-size:2rem; margin-bottom:6px; }
            .fm-kiosk-info{ background:var(--fm-white); border-radius:18px; margin-top:10px; padding:16px; }
            .fm-kiosk-canteen{ display:inline-block; background:var(--fm-yellow); color:var(--fm-dark); font-weight:700;
              font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; padding:3px 10px; border-radius:20px; }
            .fm-kiosk-info h3{ font-size:1.25rem; font-weight:700; margin:10px 0 4px; line-height:1.2; }
            .fm-kiosk-desc{ font-size:.82rem; color:var(--fm-gray); margin-bottom:12px; min-height:20px; }
            .fm-kiosk-footer{ display:flex; align-items:center; justify-content:space-between; }
            .fm-kiosk-price{ font-family:'Baloo 2',sans-serif; font-weight:800; font-size:1.3rem; color:var(--fm-red); }
            .fm-kiosk-quota{ font-size:.72rem; background:var(--fm-cream); color:var(--fm-gray); padding:3px 9px; border-radius:20px; font-weight:600; }
            .fm-kiosk-chip{ margin-top:10px; display:inline-flex; align-items:center; gap:5px; font-size:.72rem; font-weight:700;
              padding:4px 11px; border-radius:20px; background:#E7F7EE; color:var(--fm-green); }
            .fm-kiosk-chip.inactive{ background:#F3EDE4; color:var(--fm-gray); }
            .fm-kiosk-chip i{ font-size:.55rem; }
            .fm-preview-hint{ font-size:.75rem; color:var(--fm-gray); text-align:center; margin-top:10px; }

            .fm-section{ background:var(--fm-white); border:1px solid var(--fm-border); border-radius:18px; padding:22px 24px; margin-bottom:20px; box-shadow:var(--fm-shadow); }
            .fm-section-title{ display:flex; align-items:center; gap:10px; font-weight:700; font-size:1.02rem; margin-bottom:18px; }
            .fm-section-title .fm-icon{ width:34px; height:34px; border-radius:10px; background:var(--fm-red); color:#fff;
              display:flex; align-items:center; justify-content:center; font-size:.9rem; flex:none; }
            .fm-section-title .fm-icon.yellow{ background:var(--fm-yellow); color:var(--fm-dark); }
            .fm-section-title .fm-icon.green{ background:var(--fm-green); }

            .fm-label{ font-weight:600; font-size:.84rem; margin-bottom:6px; display:block; }
            .fm-label .fm-req{ color:var(--fm-red); }
            .fm-label .fm-opt{ color:var(--fm-gray); font-weight:500; font-size:.75rem; }
            .fm-kiosk .form-control{ border:1.5px solid var(--fm-border); border-radius:12px; padding:10px 14px; font-size:.9rem; height:auto; }
            .fm-kiosk .form-control:focus{ border-color:var(--fm-red); box-shadow:0 0 0 3px rgba(232,68,44,.12); }
            .fm-kiosk textarea.form-control{ border-radius:14px; }

            .fm-price-input{ position:relative; }
            .fm-price-input .fm-prefix{ position:absolute; left:14px; top:50%; transform:translateY(-50%); font-weight:700; color:var(--fm-gray); }
            .fm-price-input input{ padding-left:38px !important; }

            .fm-photo-drop{ border:2px dashed var(--fm-border); border-radius:14px; padding:16px; text-align:center; cursor:pointer; transition:.15s; }
            .fm-photo-drop:hover{ border-color:var(--fm-red); background:#FFF4F1; }
            .fm-photo-drop i{ font-size:1.4rem; color:var(--fm-red); margin-bottom:6px; display:block; }
            .fm-photo-drop span{ font-size:.82rem; color:var(--fm-gray); }
            .fm-photo-drop input[type=file]{ display:none; }
            .fm-current-photo{ display:flex; align-items:center; gap:10px; margin-top:10px; font-size:.78rem; color:var(--fm-gray); }
            .fm-current-photo img{ width:44px; height:44px; object-fit:cover; border-radius:8px; border:1px solid var(--fm-border); }

            .fm-toggle-row{ display:flex; align-items:center; justify-content:space-between; background:var(--fm-cream);
              border-radius:14px; padding:14px 18px; }
            .fm-toggle-row .fm-toggle-label{ font-weight:700; font-size:.9rem; }
            .fm-toggle-row .fm-toggle-sub{ font-size:.76rem; color:var(--fm-gray); margin-top:1px; }
            .fm-switch{ position:relative; width:48px; height:27px; flex:none; }
            .fm-switch input{ opacity:0; width:0; height:0; }
            .fm-switch-slider{ position:absolute; inset:0; background:#D9CDBA; border-radius:20px; cursor:pointer; transition:.2s; }
            .fm-switch-slider::before{ content:""; position:absolute; width:21px; height:21px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s; }
            .fm-switch input:checked + .fm-switch-slider{ background:var(--fm-green); }
            .fm-switch input:checked + .fm-switch-slider::before{ transform:translateX(21px); }

            .fm-date-chips{ display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
            .fm-date-chip{ background:var(--fm-red); color:#fff; font-size:.78rem; font-weight:600; padding:5px 10px 5px 12px;
              border-radius:20px; display:inline-flex; align-items:center; gap:8px; }
            .fm-date-chip i{ cursor:pointer; opacity:.8; font-size:.72rem; }
            .fm-date-chip i:hover{ opacity:1; }
            .fm-chip-empty{ font-size:.8rem; color:var(--fm-gray); font-style:italic; }

            .fm-actions{ display:flex; gap:12px; margin-top:6px; }
            .fm-btn-primary{ background:var(--fm-red); border:none; color:#fff; font-weight:700; padding:12px 30px; border-radius:14px;
              font-size:.92rem; box-shadow:0 8px 18px rgba(232,68,44,.28); transition:.15s; }
            .fm-btn-primary:hover{ background:var(--fm-red-dark); color:#fff; transform:translateY(-1px); }
            .fm-btn-secondary{ background:var(--fm-white); border:1.5px solid var(--fm-border); color:var(--fm-dark); font-weight:600;
              padding:12px 26px; border-radius:14px; font-size:.92rem; }
            .fm-btn-secondary:hover{ background:var(--fm-cream); color:var(--fm-dark); }

            @media (max-width:991px){ .fm-layout{ flex-direction:column; } .fm-preview-col{ position:static; flex:none; width:100%; max-width:340px; margin:0 auto 20px; } }
          </style>

          <div class="fm-kiosk">
            <div class="fm-head">
              <div>
                <h1>Edit Menu Makanan</h1>
                <p>Perbarui detail menu — preview di samping mengikuti perubahan secara langsung.</p>
              </div>
              <a href="{{ route('food-menus.index') }}" class="fm-back"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>

            @if ($errors->any())
              <div class="alert alert-danger">
                <ul class="mb-0">
                  @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
              </div>
            @endif

            <form action="{{ route('food-menus.update', $menu->id) }}" method="POST" enctype="multipart/form-data" id="menuForm">
              @csrf
              @method('PUT')

              <div class="fm-layout">
                <!-- Live kiosk preview -->
                <div class="fm-preview-col">
                  <div class="fm-kiosk-screen">
                    <div class="fm-kiosk-photo" id="previewPhotoWrap">
                      <img id="previewPhoto" src="{{ $menu->photo ? asset('storage/'.$menu->photo) : '' }}"
                           style="{{ $menu->photo ? '' : 'display:none;' }}" alt="">
                      <div id="previewPhotoPlaceholder" class="fm-photo-placeholder" style="{{ $menu->photo ? 'display:none;' : '' }}">
                        <i class="fas fa-utensils"></i>
                        <span>Foto Menu</span>
                      </div>
                    </div>
                    <div class="fm-kiosk-info">
                      <span class="fm-kiosk-canteen" id="previewCanteen">{{ $menu->canteen->name ?? 'Pilih Kantin' }}</span>
                      <h3 id="previewName">{{ $menu->name }}</h3>
                      <p id="previewDesc" class="fm-kiosk-desc">{{ $menu->description ?: 'Deskripsi menu akan tampil di sini' }}</p>
                      <div class="fm-kiosk-footer">
                        <span class="fm-kiosk-price" id="previewPrice">Rp {{ number_format($menu->price,0,',','.') }}</span>
                        <span class="fm-kiosk-quota" id="previewQuota">{{ $menu->quota ? 'Kuota '.$menu->quota.'/hari' : 'Unlimited' }}</span>
                      </div>
                      <span class="fm-kiosk-chip {{ $menu->is_active ? '' : 'inactive' }}" id="previewStatus">
                        <i class="fas fa-circle"></i> {{ $menu->is_active ? 'Aktif' : 'Nonaktif' }}
                      </span>
                    </div>
                  </div>
                  <p class="fm-preview-hint"><i class="fas fa-eye"></i> Simulasi tampilan kartu menu di kiosk</p>
                </div>

                <!-- Form fields -->
                <div class="fm-form-col">

                  <div class="fm-section">
                    <div class="fm-section-title"><span class="fm-icon"><i class="fas fa-store"></i></span> Informasi Dasar</div>
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label class="fm-label">Kantin <span class="fm-req">*</span></label>
                        <select name="canteen_id" id="inputCanteen" class="form-control" required>
                          <option value="">-- Pilih Kantin --</option>
                          @foreach($canteens as $c)
                            <option value="{{ $c->id }}" {{ $menu->canteen_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                          @endforeach
                        </select>
                      </div>
                      <div class="form-group col-md-6">
                        <label class="fm-label">Nama Menu <span class="fm-req">*</span></label>
                        <input type="text" name="name" id="inputName" class="form-control" value="{{ old('name', $menu->name) }}" required>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="fm-label">Deskripsi <span class="fm-opt">(opsional)</span></label>
                      <textarea name="description" id="inputDesc" class="form-control" rows="2">{{ old('description', $menu->description) }}</textarea>
                    </div>
                    <div class="form-group mb-0">
                      <label class="fm-label">Foto Menu <span class="fm-opt">(opsional, upload baru untuk mengganti)</span></label>
                      <label class="fm-photo-drop" id="photoDrop" for="inputPhoto">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span id="photoDropText">Klik untuk unggah foto baru (JPG/PNG, maks 2MB)</span>
                        <input type="file" name="photo" id="inputPhoto" accept="image/*">
                      </label>
                      @if($menu->photo)
                        <div class="fm-current-photo">
                          <img src="{{ asset('storage/'.$menu->photo) }}" alt="">
                          <span>Foto saat ini digunakan</span>
                        </div>
                      @endif
                    </div>
                  </div>

                  <div class="fm-section">
                    <div class="fm-section-title"><span class="fm-icon yellow"><i class="fas fa-tags"></i></span> Harga &amp; Kuota</div>
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label class="fm-label">Harga <span class="fm-opt">(opsional)</span></label>
                        <div class="fm-price-input">
                          <span class="fm-prefix">Rp</span>
                          <input type="number" step="0.01" min="0" name="price" id="inputPrice" class="form-control" value="{{ old('price', $menu->price) }}">
                        </div>
                      </div>
                      <div class="form-group col-md-6">
                        <label class="fm-label">Kuota per Hari <span class="fm-opt">(kosongkan = unlimited)</span></label>
                        <input type="number" min="1" name="quota" id="inputQuota" class="form-control" value="{{ old('quota', $menu->quota) }}">
                      </div>
                    </div>
                  </div>

                  <div class="fm-section">
                    <div class="fm-section-title"><span class="fm-icon green"><i class="fas fa-calendar-check"></i></span> Ketersediaan</div>
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label class="fm-label">Tersedia mulai tanggal <span class="fm-opt">(opsional)</span></label>
                        <input type="date" name="available_start" class="form-control"
                               value="{{ old('available_start', $menu->available_start ? \Carbon\Carbon::parse($menu->available_start)->toDateString() : '') }}">
                      </div>
                      <div class="form-group col-md-6">
                        <label class="fm-label">Sampai tanggal <span class="fm-opt">(opsional)</span></label>
                        <input type="date" name="available_end" class="form-control"
                               value="{{ old('available_end', $menu->available_end ? \Carbon\Carbon::parse($menu->available_end)->toDateString() : '') }}">
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="fm-label">Tanggal Tersedia Khusus <span class="fm-opt">(kosongkan = setiap hari dalam rentang di atas)</span></label>
                      <input type="text" id="availableDatesPicker" class="form-control" placeholder="Pilih satu atau beberapa tanggal">
                      <div class="fm-date-chips" id="dateChips"></div>
                      <div id="availableDatesHidden"></div>
                    </div>

                    <div class="fm-toggle-row">
                      <div>
                        <div class="fm-toggle-label">Status Menu</div>
                        <div class="fm-toggle-sub" id="toggleSub">{{ $menu->is_active ? 'Menu tampil di kiosk' : 'Menu disembunyikan dari kiosk' }}</div>
                      </div>
                      <label class="fm-switch">
                        <input type="checkbox" name="is_active" id="inputActive" {{ $menu->is_active ? 'checked' : '' }}>
                        <span class="fm-switch-slider"></span>
                      </label>
                    </div>
                  </div>

                  <div class="fm-actions">
                    <button type="submit" class="fm-btn-primary"><i class="fas fa-save"></i> Update Menu</button>
                    <a href="{{ route('food-menus.index') }}" class="fm-btn-secondary">Batal</a>
                  </div>

                </div>
              </div>
            </form>
          </div>

        </div>
      </div>
  @include('layout.footer')
    </div>
  </body>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    window.fmDefaultDates = @json(old('available_dates', $menu->available_dates ?? []));

    document.addEventListener('DOMContentLoaded', function () {
      const canteenSel   = document.getElementById('inputCanteen');
      const nameInput    = document.getElementById('inputName');
      const descInput    = document.getElementById('inputDesc');
      const priceInput   = document.getElementById('inputPrice');
      const quotaInput   = document.getElementById('inputQuota');
      const activeInput  = document.getElementById('inputActive');
      const photoInput   = document.getElementById('inputPhoto');

      const previewCanteen = document.getElementById('previewCanteen');
      const previewName    = document.getElementById('previewName');
      const previewDesc    = document.getElementById('previewDesc');
      const previewPrice   = document.getElementById('previewPrice');
      const previewQuota   = document.getElementById('previewQuota');
      const previewStatus  = document.getElementById('previewStatus');
      const previewPhoto   = document.getElementById('previewPhoto');
      const previewPhotoPlaceholder = document.getElementById('previewPhotoPlaceholder');
      const toggleSub      = document.getElementById('toggleSub');

      function formatRupiah(value) {
        const n = parseFloat(value || 0);
        return 'Rp ' + n.toLocaleString('id-ID', { maximumFractionDigits: 0 });
      }

      canteenSel.addEventListener('change', function () {
        const text = this.options[this.selectedIndex]?.text || 'Pilih Kantin';
        previewCanteen.textContent = this.value ? text : 'Pilih Kantin';
      });
      nameInput.addEventListener('input', function () {
        previewName.textContent = this.value || 'Nama Menu';
      });
      descInput.addEventListener('input', function () {
        previewDesc.textContent = this.value || 'Deskripsi menu akan tampil di sini';
      });
      priceInput.addEventListener('input', function () {
        previewPrice.textContent = formatRupiah(this.value);
      });
      quotaInput.addEventListener('input', function () {
        previewQuota.textContent = this.value ? ('Kuota ' + this.value + '/hari') : 'Unlimited';
      });
      activeInput.addEventListener('change', function () {
        if (this.checked) {
          previewStatus.className = 'fm-kiosk-chip';
          previewStatus.innerHTML = '<i class="fas fa-circle"></i> Aktif';
          toggleSub.textContent = 'Menu tampil di kiosk';
        } else {
          previewStatus.className = 'fm-kiosk-chip inactive';
          previewStatus.innerHTML = '<i class="fas fa-circle"></i> Nonaktif';
          toggleSub.textContent = 'Menu disembunyikan dari kiosk';
        }
      });
      photoInput.addEventListener('change', function () {
        const file = this.files[0];
        document.getElementById('photoDropText').textContent = file ? file.name : 'Klik untuk unggah foto baru (JPG/PNG, maks 2MB)';
        if (file) {
          const reader = new FileReader();
          reader.onload = e => {
            previewPhoto.src = e.target.result;
            previewPhoto.style.display = 'block';
            previewPhotoPlaceholder.style.display = 'none';
          };
          reader.readAsDataURL(file);
        }
      });

      // ---- Multi date picker ----
      const hiddenContainer = document.getElementById('availableDatesHidden');
      const chipWrap = document.getElementById('dateChips');

      function syncHiddenInputs(selectedDates) {
        hiddenContainer.innerHTML = '';
        selectedDates.forEach(d => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'available_dates[]';
          input.value = flatpickr.formatDate(d, 'Y-m-d');
          hiddenContainer.appendChild(input);
        });
        renderChips(selectedDates);
      }

      function renderChips(selectedDates) {
        chipWrap.innerHTML = '';
        if (!selectedDates.length) {
          chipWrap.innerHTML = '<span class="fm-chip-empty">Belum ada tanggal khusus dipilih</span>';
          return;
        }
        selectedDates.forEach((d, idx) => {
          const chip = document.createElement('span');
          chip.className = 'fm-date-chip';
          chip.innerHTML = flatpickr.formatDate(d, 'd M Y') + ' <i class="fas fa-times" data-idx="' + idx + '"></i>';
          chipWrap.appendChild(chip);
        });
      }

      const fp = flatpickr('#availableDatesPicker', {
        mode: 'multiple',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd M Y',
        defaultDate: window.fmDefaultDates || [],
        onChange: function (selectedDates) { syncHiddenInputs(selectedDates); },
        onReady: function (selectedDates) { syncHiddenInputs(selectedDates); }
      });

      chipWrap.addEventListener('click', function (e) {
        if (e.target.matches('i[data-idx]')) {
          const idx = parseInt(e.target.getAttribute('data-idx'), 10);
          const dates = fp.selectedDates.slice();
          dates.splice(idx, 1);
          fp.setDate(dates);
          syncHiddenInputs(dates);
        }
      });
    });
  </script>
</html>