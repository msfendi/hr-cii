<!DOCTYPE html>
<html lang="en">
@include('layout.header')


<body id="page-top">
    <!-- Page Wrapper -->
    @include('sweetalert::alert')

    <div id="wrapper">
        @include('layout.sidebar')
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                @include('layout.navbar')
                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-project-diagram text-primary"></i> Master Approval
                        </h1>
                        <div>
                            <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm mr-2"
                                id="btnImportGroup" data-toggle="modal" data-target="#modalImport">
                                <i class="fas fa-file-excel fa-sm text-white-50"></i> Import Excel
                            </button>
                            <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"
                                id="btnTambahGroup" data-toggle="modal" data-target="#modalGroup">
                                <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Group
                            </button>
                        </div>
                    </div>

                    <!-- DataTable Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Approval Groups</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">

                                <table class="table table-bordered table-sm" id="approvalTable" width="100%"
                                    cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="40">No</th>
                                            <th>Nama Group</th>
                                            <th>Departemen</th>
                                            <th width="100">Jumlah Level</th>
                                            <th width="130">Aksi</th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->

            @include('layout.footer')

        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->


    <!-- ===================== ADD / EDIT GROUP MODAL ===================== -->
    <div class="modal fade" id="modalGroup" tabindex="-1" role="dialog" aria-labelledby="modalGroupLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalGroupLabel">Tambah Approval Group</h5>
                    <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formGroup">
                    @csrf
                    <input type="hidden" id="group_id" name="group_id">
                    <div class="modal-body">

                        <div class="form-group">
                            <label for="group_name">Nama Group <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="group_name" name="name"
                                placeholder="Contoh: Sewing A, Staff, dll" required>
                        </div>


                        <div class="form-group">
                            <label>Departemen Terkait <span class="text-danger">*</span></label>
                            <select class="select2" id="group_dept" name="dept[]" multiple="multiple" required
                                style="width: 100%;">
                                @foreach($depts as $d)
                                    <option value="{{ $d->ID_DEPT }}">{{ $d->DEPARTEMENT }}</option>
                                @endforeach
                            </select>
                        </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" id="btnSimpanGroup">
                                <i class="fas fa-save mr-1"></i> Simpan
                            </button>
                        </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================== END MODAL ===================== -->

    <!-- ===================== DETAIL / RULES MODAL ===================== -->
    <div class="modal fade" id="modalRules" tabindex="-1" role="dialog" aria-labelledby="modalRulesLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalRulesLabel">
                        <i class="fas fa-sitemap mr-1"></i> Approval Rules — <span id="rulesGroupName"></span>
                    </h5>
                    <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    {{-- Departments in this group --}}
                    <div class="mb-3">
                        <strong class="text-gray-600 small text-uppercase">Departemen:</strong>
                        <div id="rulesDeptBadges" class="mt-1"></div>
                    </div>
                    <hr>

                    {{-- Rules table --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold text-gray-700 mb-0">Daftar Approver</h6>
                        <button type="button" class="btn btn-sm btn-success" id="btnTambahRule">
                            <i class="fas fa-plus fa-sm"></i> Tambah Approver
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="rulesTable" width="100%">
                            <thead class="thead-light">
                                <tr>
                                    <th width="60">Level</th>
                                    <th>Nama Jabatan</th>
                                    <th>NPK Approver</th>
                                    <th>Nama Approver</th>
                                    <th width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="rulesTableBody"></tbody>
                        </table>
                    </div>

                    {{-- Inline Add/Edit form for a rule --}}
                    <div id="ruleFormContainer" style="display:none;" class="border rounded p-3 mt-3 bg-light">
                        <h6 class="font-weight-bold text-gray-700 mb-3" id="ruleFormTitle">Tambah Approver</h6>
                        <form id="formRule">
                            @csrf
                            <input type="hidden" id="rule_id">
                                <input type="hidden" id="rule_group_id">
                                <div class="form-row">
                                    <div class="form-group col-md-2">
                                        <label>Level <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="rule_level" min="1" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Nama Jabatan <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="rule_name"
                                            placeholder="Contoh: SPV, Chief" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Cari Karyawan (NPK/Nama) <span class="text-danger">*</span>
                                            </label>
                                                <input type="text" class="form-control" id="rule_search"
                                                    placeholder="Ketik NPK atau nama..." autocomplete="off">
                                                <div id="searchResults" class="list-group position-absolute w-100"
                                                    style="z-index:1050; max-height:200px; overflow-y:auto; display:none;">
                                                </div>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>NPK Approver</label>
                                        <input type="text" class="form-control" id="rule_approval_id" readonly
                                            placeholder="Otomatis terisi">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end" style="gap:.5rem;">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        id="btnCancelRule">Batal</button>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save mr-1"></i> Simpan Rule
                                    </button>
                                </div>
                                </form>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <!-- ===========    ========== END RULES MODAL ===================== -->

    <!-- ===    ================== DELETE CONFIRM MODAL ===================== -->
    <div class="modal fade" id="modalDeleteGroup" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Hapus Approval Group</h5>
                    <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah anda yakin ingin menghapus group <strong id="deleteGroupName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmDeleteGroup">
                        <i class="fas fa-trash mr-1"></i> Hapus

                    </button>
                </div>
            </div>
        </div>

    </div>
    <!-- ===========    ========== END DELETE MODAL ===================== -->

    <!-- ===    ================== IMPORT EXCEL MODAL ===================== -->
    <div class="modal fade" id="modalImport" tabindex="-1" role="dialog" aria-labelledby="modalImportLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalImportLabel"><i class="fas fa-file-excel mr-1"></i> Import Approval
                        dari Excel</h5>
                    <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Satu baris Excel mewakili satu approver di satu level. Baris dengan
                        <strong>Nama Group</strong> yang sama akan otomatis digabung menjadi satu Approval Group dengan beberapa level approver.
                    </p>



                    <table class="table table-bordered table-sm small mb-3">

                        <thead class="thead-light">


                            <tr>


                                <th>Kolom</th>
                                <th>Keterangan</th>
                            </tr>
                            </thead>

                            <tbody>
                                <tr>
                                    <td>Nama Group</td>
                                    <td>Nama approval group, contoh: <em>Staff Produksi</em></td>
                                </tr>
                                <tr>
                                    <td>Kode Departemen</td>
                                    <td>ID Departemen, boleh lebih dari satu dipisah koma, contoh: <em>10,11</em></td>
                                </tr>
                                <tr>
                                    <td>Level Approval</td>
                                    <td>Urutan approval, contoh: <em>1</em>, <em>2</em>, <em>3</em></td>
                                </tr>
                                <tr>
                                    <td>Nama Jabatan</td>
                                    <td>Label posisi approver, contoh: <em>SPV</em></td>
                                </tr>
                                <tr>
                                    <td>NPK Approver</td>
                                    <td>NPK karyawan approver di level tersebut</td>
                                </tr>
                                <tr>
                                    <td>Nama Approver</td>
                                    <td>Hanya referensi, tidak wajib akurat</td>
                                </tr>
                            </tbody>
                    </table>

                    <a href="{{ route('approval.download-template') }}" class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="fas fa-download mr-1"></i> Download Template Excel
                    </a>

                    <form id="formImport">
                        @csrf

                        <div class="form-group mb-0">
                            <label>File Excel (.xlsx) <span class="text-danger">*</span></label>
                            <input type="file" class="form-control-file" id="import_file" name="file"
                                accept=".xlsx,.xls" required>
                        </div>
                    </form>

                    <div id="importErrors" class="alert alert-danger small mt-3" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="btnSubmitImport">
                        <i class="fas fa-upload mr-1"></i> Import
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- ===================== END IMPORT MODAL ===================== -->

</body>

@include('layout.footerscript')
<!-- DataTables -->
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Select2 Bootstrap4 Theme -->
<script src="{{ asset('vendor/jquery/select2.min.js') }}"></script>
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css"
    rel="stylesheet" />

<script>
    $(document).ready(function () {

        // ── Global Variables ────────────────────────────────────
        var TOKEN = '{{ csrf_token() }}';
        var currentGroupData = null;  // Stores data of the currently viewed group in Rules modal
        var deleteGroupId = null;     // Stores ID of the group to be deleted
        var searchTimer;              // Timer for employee search debounce

        function doAjax(url, data, onDone) {
            $.post(url, data)
                .done(function (response) {
                    if (response.status === 'success') {
                        // Show success notification
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        // Run callback if provided
                        if (onDone) {
                            onDone(response);
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message
                        });
                    }
                })
                .fail(function (xhr) {
                    // Handle network or server errors
                    var errorMsg = 'Terjadi kesalahan.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: errorMsg
                    });
                });
        }

        function refreshRules() {
            table.ajax.reload(function () {
                // Find the updated group data from the reloaded table
                var allRows = table.rows().data().toArray();
                var updated = allRows.find(function (row) {
                    return row.id == currentGroupData.id;
                });

                if (updated) {
                    currentGroupData = updated;
                    renderRulesTable(updated.rules);
                }
            }, false);
        }

        function badgeList(items, badgeClass) {
            if (!items || items.length === 0) {
                return '<span class="text-muted">-</span>';
            }

            var badges = '';
            for (var i = 0; i < items.length; i++) {
                badges += '<span class="badge badge-' + badgeClass + ' mr-1 mb-1">' + items[i] + '</span>';
            }
            return badges;
        }

        $('#group_dept').select2({
            dropdownParent: $('#modalGroup'),
            theme: 'bootstrap4',
            placeholder: "Pilih Departemen...",
            allowClear: true
        });

        // =====  ====================================================
        // DATATABLE    SETUP
        // =====   ====================================================
        var table = $('#approvalTable').DataTable({
            processing: true,
            ajax: {
                url: '{{ route("approval.get-data") }}',
                dataSrc: 'data'
            },
            columns: [
                // C  olumn 1: Row number
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                // Colum n 2: Group name
                { data: 'name' },
                // Column 3: Department badges
                {
                    data: 'dept_names',
                    render: function (value) {
                        return badgeList(value, 'info');
                    }
                },
                // Column 4: Number of approval levels
                {
                    data: 'rules_count',
                    className: 'text-center',
                    render: function (value) {
                        return '<span class="badge badge-primary">' + value + ' level</span>';
                    }
                },
                // Column 5: Action buttons (Detail, Edit, Delete)
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (row) {
                        return ''
                            + '<button class="btn btn-info btn-circle btn-sm btn-detail mr-1"'
                            + ' data-id="' + row.id + '" title="Detail">'
                            + '<i class="fas fa-sitemap"></i></button>'

                            + '<button class="btn btn-warning btn-circle btn-sm btn-edit mr-1"'
                            + ' data-id="' + row.id + '"'
                            + ' data-name="' + row.name + '"'
                            + " data-dept='" + JSON.stringify(row.dept) + "'"
                            + ' title="Edit">'
                            + '<i class="fas fa-edit"></i></button>'

                            + '<button class="btn btn-danger btn-circle btn-sm btn-delete"'
                            + ' data-id="' + row.id + '"'
                            + ' data-name="' + row.name + '"'
                            + ' title="Hapus">'
                            + '<i class="fas fa-trash"></i></button>';
                    }
                }
            ]
        });

        // =========================================================
        // GROUP: ADD (reset modal form for new entry)
        // =========================================================
        $('#btnTambahGroup').on('click', function () {
            $('#modalGroupLabel').text('Tambah Approval Group');
            $('#formGroup')[0].reset();
            $('#group_id').val('');
            $('#group_dept').val(null).trigger('change');
        });

        // =========================================================
        // GROUP: EDIT (populate modal form with existing data)
        // =========================================================
        $('#approvalTable').on('click', '.btn-edit', function () {
            var deptIds = $(this).data('dept');

            $('#modalGroupLabel').text('Edit Approval Group');
            $('#group_id').val($(this).data('id'));
            $('#group_name').val($(this).data('name'));

            // Convert dept IDs to strings so Select2 can match them
            $('#group_dept').val(deptIds.map(String)).trigger('change');
            $('#modalGroup').modal('show');
        });

        // =========================================================
        // GROUP: SAVE (handles both Add and Edit)
        // =========================================================
        $('#formGroup').on('submit', function (e) {
            e.preventDefault();

            var id = $('#group_id').val();

            // If id exists, it's an update; otherwise it's a new entry
            var url = id
                ? '{{ url("/approval/update") }}/' + id
                : '{{ route("approval.store") }}';

            doAjax(url, $(this).serialize(), function () {
                $('#modalGroup').modal('hide');
                table.ajax.reload(null, false);
            });
        });

        // =========================================================
        // GROUP: DELETE (show confirmation modal)
        // =========================================================
        $('#approvalTable').on('click', '.btn-delete', function () {
            deleteGroupId = $(this).data('id');
            $('#deleteGroupName').text($(this).data('name'));
            $('#modalDeleteGroup').modal('show');
        });

        // GROUP: CONFIRM DELETE
        $('#btnConfirmDeleteGroup').on('click', function () {
            if (!deleteGroupId) return;

            var id = deleteGroupId;
            deleteGroupId = null;
            $('#modalDeleteGroup').modal('hide');

            doAjax('{{ url("/approval/destroy") }}/' + id, { _token: TOKEN }, function () {
                table.ajax.reload(null, false);
            });
        });

        // =========================================================
        // RULES: SHOW DETAIL MODAL
        // =========================================================
        $('#approvalTable').on('click', '.btn-detail', function () {
            var groupId = $(this).data('id');

            // Find the group data from the DataTable rows
            var allRows = table.rows().data().toArray();
            var rowData = allRows.find(function (row) {
                return row.id == groupId;
            });
            if (!rowData) return;

            // Store group data for later use (e.g. refreshRules)
            currentGroupData = rowData;

            // Populate modal header and department badges
            $('#rulesGroupName').text(rowData.name);
            $('#rule_group_id').val(rowData.id);
            $('#rulesDeptBadges').html(badgeList(rowData.dept_names, 'info'));

                   // R ender rules table and hide the inline form
               renderRulesTable(rowData.rules);
            $('#ruleFormContainer').hide();

            $('#modalRules').modal('show');
        });

        /**
  * Rende    r the rules/approver table inside the Rules modal.
     * - rul es: Array of rule objects from the server
     */
        function renderRulesTable(rules) {
            var tbody = $('#rulesTableBody');
              tbody.empty();

            // Show empty message if no rules
            if (!rules || rules.length === 0) {
                tbody.html('<tr><td colspan="5" class="text-center text-muted">Belum ada approver</td></tr>');
                return;
            }

            // Sort rules by level (ascending)
            rules.sort(function (a, b) {
                return a.level - b.level;
            });

            // Build each row
            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                var row = '<tr>'
                    + '<td class="text-center"><span class="badge badge-primary">' + rule.level + '</span></td>'
                    + '<td>' + rule.name + '</td>'
                    + '<td><code>' + rule.approval_id + '</code></td>'
                    + '<td>' + rule.approval_name + '</td>'
                    + '<td>'
                    + '  <button class="btn btn-warning btn-circle btn-sm btn-edit-rule mr-1"'
                    + '    data-id="' + rule.id + '"'
                    + '    data-name="' + rule.name + '"'
                    + '    data-approvalid="' + rule.approval_id + '"'
                    + '    data-level="' + rule.level + '"'
                    + '    title="Edit"><i class="fas fa-edit"></i></button>'
                    + '  <button class="btn btn-danger btn-circle btn-sm btn-delete-rule"'
                    + '    data-id="' + rule.id + '"'
                    + '    data-name="' + rule.name + '"'
                    + '    title="Hapus"><i class="fas fa-trash"></i></button>'
                    + '</td>'
                    + '</tr>';
                tbody.append(row);
            }
        }

        // =========================================================
        // RULE: ADD (reset inline form for new approver)
        // =========================================================
        $('#btnTambahRule').on('click', function () {
            $('#ruleFormTitle').text('Tambah Approver');
            $('#formRule')[0].reset();
            $('#rule_id').val('');
            $('#rule_approval_id').val('');
            $('#ruleFormContainer').slideDown(200);
        });

        // RULE: CANCEL (hide inline form)
        $('#btnCancelRule').on('click', function () {
            $('#ruleFormContainer').slideUp(200);
        });

        // =====   ====================================================
        // RULE: EDIT (populate inline form with existing rule data)
        // =====    ====================================================
        $(document).on('click', '.btn-edit-rule', function () {
            var button = $(this);

            $('#ruleFormTitle').text('Edit Approver');
            $('#rule_id').val(button.data('id'));
            $('#rule_name').val(button.data('name'));
            $('#rule_approval_id').val(button.data('approvalid'));
            $('#rule_search').val(button.data('approvalid'));
            $('#rule_level').val(button.data('level'));
            $('#ruleFormContainer').slideDown(200);
        });

        // =    ========================================================
        // RULE: SAVE (handles both Add and Edit)
        // =========================================================
        $('#formRule').on('submit', function (e) {
            e.preventDefault();

            var payload = {
                _token: TOKEN,
                rules_id: $('#rule_group_id').val(),
                name: $('#rule_name').val(),
                approval_id: $('#rule_approval_id').val(),
                level: $('#rule_level').val()
            };

            // Validate: approver must be selected
            if (!payload.approval_id) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Perhatian',
                    text: 'Pilih karyawan sebagai approver terlebih dahulu.'
                });
                return;
            }

            // Deter  mine if this is an update or a new rule
            var ruleId = $('#rule_id').val();
            var url = ruleId
                ? '{{ url("/approval/rule/update") }}/' + ruleId
                : '{{ route("approval.rule.store") }}';

            doAjax(url, payload, function () {
                $('#ruleFormContainer').slideUp(200);
                refreshRules();
            });
        });

        // =========================================================
        // RULE: DELETE (with SweetAlert confirmation)
        // =========================================================
        $(document).on('click', '.btn-delete-rule', function () {
            var ruleId = $(this).data('id');
            var ruleName = $(this).data('name');

            Swal.fire({
                title: 'Hapus Approver?',
                text: 'Hapus "' + ruleName + '" dari group ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then(function (result) {
                if (result.isConfirmed) {
                    doAjax(
                        '{{ url("/approval/rule/destroy") }}/' + ruleId,
                        { _token: TOKEN },
                        refreshRules
                    );
                }
            });
        });

        // =========================================================
        // EMPLOYEE SEARCH (autocomplete for selecting approver)
        // ========= ================================================
        $('#rule_search').on('input', function () {
            var keyword = $(this).val();

            // Clear any existing timer to avoid too many API calls
            clearTimeout(searchTimer);

            // Only search if at least 2 characters are typed
            if (keyword.length < 2) {
                $('#searchResults').hide();
                return;
            }

            // Wait 300ms after user stops typing before searching (debounce)
            searchTimer = setTimeout(function () {
                $.get('{{ route("approval.search-employee") }}', { q: keyword }, function (employees) {
                    var container = $('#searchResults');
                    container.empty();

                    if (employees.length === 0) {
                        container.append('<div class="list-group-item text-muted small">Tidak ditemukan</div>');
                    } else {
                        // Build a clickable list of search results
                        for (var i = 0; i < employees.length; i++) {
                            var emp = employees[i];
                            var item = ''
                                + '<a href="#" class="list-group-item list-group-item-action search-result-item py-1 px-3"'
                                + ' data-npk="' + emp.NPK + '"'
                                + ' data-name="' + emp.NAMA_KARYAWAN + '">'
                                + '<strong>' + emp.NPK + '</strong> — ' + emp.NAMA_KARYAWAN
                                + '</a>';
                            container.append(item);
                        }
                    }

                    container.show();
                });
            }, 300);
        });

        // W    hen an employee is selected from search results
        $(document).on('click', '.search-result-item', function (e) {
            e.preventDefault();

            var npk = $(this).data('npk');
            var name = $(this).data('name');

            $('#rule_approval_id').val(npk);
            $('#rule_search').val(npk + ' — ' + name);
            $('#searchResults').hide();
        });

        // H   ide search results when clicking anywhere else on the page
        $(document).on('click', function (e) {
            var clickedInsideSearch = $(e.target).closest('#rule_search, #searchResults').length > 0;
            if (!clickedInsideSearch) {
                $('#searchResults').hide();
            }
        });

        // =====  ====================================================
        // I   MPORT: UPLOAD EXCEL (ApprovalDept + ApprovalRule)
        // =========================================================
        $('#modalImport').on('hidden.bs.modal', function () {
            $('#formImport')[0].reset();
            $('#importErrors').hide().empty();
        });

        $('#btnSubmitImport').on('click', function () {
            var fileInput = document.getElementById('import_file');
            if (!fileInput.files.length) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Pilih file Excel terlebih dahulu.' });
            return;
        }

        var formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('_token', TOKEN);

        $('#importErrors').hide().empty();
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mengimpor...');

        $.ajax({
            url: '{{ route("approval.import") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 2000, showConfirmButton: false });
                $('#modalImport').modal('hide');
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                var res = xhr.responseJSON;
                if (res && res.errors) {
                    var html = '<strong>' + res.message + '</strong><ul class="mb-0 mt-1">';
                    res.errors.forEach(function (e) { html += '<li>' + e + '</li>'; });
                    html += '</ul>';
                    $('#importErrors').html(html).show();
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: (res && res.message) ? res.message : 'Terjadi kesalahan.' });
                }
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Import');
            }
        });
    });

    });
</script>

</html>