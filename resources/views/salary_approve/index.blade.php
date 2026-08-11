<!DOCTYPE html>
<html lang="en">

@include('layout.header')

<head>
    <style>
        .s-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .2px;
            white-space: nowrap;
        }

        .s-test { background: #e0f2fe; color: #0369a1; }
        .s-onboard { background: #d1fae5; color: #065f46; }
        .s-reject { background: #fee2e2; color: #991b1b; }

        .act-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 5px;
            border: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
        }

        .act-wa {
            background: #dcfce7;
            color: #15803d;
        }

        .act-wa:hover {
            background: #16a34a;
            color: #fff;
        }

        .act-reject {
            background: #fee2e2;
            color: #991b1b;
        }

        .act-reject:hover {
            background: #dc2626;
            color: #fff;
        }

        /* Step tracker column */
        .step-track {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .step-row {
            font-size: 11.5px;
        }

        .step-row-label {
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 2px;
        }

        .step-row-label.done { color: #166534; }
        .step-row-label.active { color: #92400e; }
        .step-row-label.upcoming { color: #9ca3af; }

        .approver-chip {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10.5px;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 10px;
            margin: 1px 3px 1px 0;
        }

        .approver-chip.approve {
            background: #dcfce7;
            color: #166534;
        }

        .approver-chip.waiting {
            background: #fef3c7;
            color: #92400e;
        }

        /* Approve modal info card */
        .approve-info-card {
            background: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }

        .approve-info-card .fl {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #9ca3af;
        }

        .approve-info-card .fv {
            font-size: 14.5px;
            font-weight: 700;
            color: #2d3748;
        }
    </style>
</head>

<body id="page-top">

    @include('sweetalert::alert')

    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                                <i class="fas fa-money-check-alt mr-2 text-primary"></i>Approval Pengajuan Gaji
                            </h1>
                            <p class="mb-0 text-muted small">
                                Persetujuan expected salary karyawan baru (staff). Hanya menampilkan pengajuan
                                yang Anda ditugaskan sebagai approver.
                            </p>
                        </div>
                    </div>

                    @foreach (['success', 'error', 'warning', 'info'] as $type)
                        @if ($msg = Session::get($type))
                            <div class="alert alert-{{ $type }} alert-dismissible fade show shadow-sm" role="alert">
                                <strong>{{ $msg }}</strong>
                                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            </div>
                        @endif
                    @endforeach

                    <div class="card shadow mb-4">
                        <div class="card-header py-3" style="background: linear-gradient(135deg,#4338ca 0%,#6366f1 100%);">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-table mr-2"></i>Daftar Pengajuan</h6>
                        </div>
                        <div class="card-body">
                            @if($data->isEmpty())
                                <p class="text-muted mb-0" style="font-size:14px;">
                                    <i class="fas fa-info-circle mr-1"></i>Belum ada pengajuan gaji yang menugaskan Anda sebagai approver.
                                </p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="dataTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Nama Pelamar</th>
                                                <th>Jabatan / Dept</th>
                                                <th>Expected Salary</th>
                                                <th>Approved Salary</th>
                                                <th style="min-width:220px">Progress Approval</th>
                                                <th>Status</th>
                                                <th>Diajukan</th>
                                                <th width="140" class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($data as $row)
                                                <tr>
                                                    <td>{{ $row->nama_pelamar }}</td>
                                                    <td>{{ $row->jabatan }} <br><small class="text-muted">{{ $row->department }}</small></td>
                                                    <td>Rp {{ number_format($row->expected_salary, 0, ',', '.') }}</td>
                                                    <td>{{ $row->approved_salary ? 'Rp ' . number_format($row->approved_salary, 0, ',', '.') : '-' }}</td>

                                                    {{-- Progress Approval — tanda jelas mana yang sudah & belum approve --}}
                                                    <td>
                                                        <div class="step-track">
                                                            @foreach($row->steps as $i => $step)
                                                                @php
                                                                    $rowState = $step['done'] ? 'done' : ($row->current_step === $i ? 'active' : 'upcoming');
                                                                @endphp
                                                                <div class="step-row">
                                                                    <div class="step-row-label {{ $rowState }}">
                                                                        @if($rowState === 'done')
                                                                            <i class="fas fa-check-circle"></i>
                                                                        @elseif($rowState === 'active')
                                                                            <i class="fas fa-hourglass-half"></i>
                                                                        @else
                                                                            <i class="fas fa-circle" style="font-size:7px;"></i>
                                                                        @endif
                                                                        {{ $step['label'] }}
                                                                    </div>
                                                                    <div>
                                                                        @foreach($step['approvers'] as $ap)
                                                                            <span class="approver-chip {{ $ap['status'] === 'approve' ? 'approve' : 'waiting' }}">
                                                                                <i class="fas {{ $ap['status'] === 'approve' ? 'fa-check' : 'fa-clock' }}"></i>
                                                                                {{ $ap['name'] }}
                                                                            </span>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <span class="s-pill {{ $row->status === 'finish' ? 's-onboard' : ($row->status === 'rejected' ? 's-reject' : 's-test') }}">
                                                            {{ strtoupper($row->status) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $row->created_at->format('d M Y') }}</td>
                                                    <td class="text-center">
                                                        @if($row->can_approve)
                                                            <button type="button" class="act-btn act-wa btn-approve-salary"
                                                                data-row="{{ json_encode($row) }}"
                                                                data-toggle="modal" data-target="#approveSalaryModal">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                            <form action="{{ route('salary-approve.reject', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tolak pengajuan gaji {{ $row->nama_pelamar }}?')">
                                                                @csrf
                                                                <button type="submit" class="act-btn act-reject mt-1">
                                                                    <i class="fas fa-times"></i> Tolak
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="text-muted" style="font-size:12px;">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

            {{-- Modal approve — info lengkap + muncul input approved_salary kalau step terakhir (GM) --}}
            <div class="modal fade" id="approveSalaryModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <form id="formApproveSalary" method="POST">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title font-weight-bold">Approve Pengajuan Gaji</h5>
                                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="approve-info-card">
                                    <div class="mb-2">
                                        <div class="fl">Pelamar</div>
                                        <div class="fv" id="approve_salary_nama">-</div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="fl">Jabatan / Dept</div>
                                            <div class="fv" style="font-size:13px;" id="approve_salary_jabatan">-</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="fl">Expected Salary</div>
                                            <div class="fv" style="font-size:13px;" id="approve_salary_expected">-</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="fl mb-1">Progress Approval</div>
                                <div id="approve_salary_steps" class="mb-3"></div>

                                <div id="approve_salary_field"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-success btn-sm font-weight-bold">Approve</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @include('layout.footer')
        </div>
    </div>

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

    <script>
        $(document).ready(function () {
            if ($('#dataTable tbody tr').length) {
                $('#dataTable').DataTable({ pageLength: 10, order: [] });
            }

            $(document).on('click', '.btn-approve-salary', function () {
                const row = $(this).data('row');
                const isLast = row.current_step === (row.steps.length - 1);

                $('#approve_salary_nama').text(row.nama_pelamar);
                $('#approve_salary_jabatan').text((row.jabatan || '-') + ' / ' + (row.department || '-'));
                $('#approve_salary_expected').text('Rp ' + Number(row.expected_salary).toLocaleString('id-ID'));
                $('#formApproveSalary').attr('action', '/salary-approve/' + row.id + '/approve');

                let stepsHtml = '';
                (row.steps || []).forEach(step => {
                    stepsHtml += `<div class="mb-2" style="font-size:13px;">
                        <strong>${step.label}${step.done ? ' <span class="text-success">✔ Selesai</span>' : ''}</strong><br>`;
                    (step.approvers || []).forEach(a => {
                        const icon = a.status === 'approve'
                            ? '<i class="fas fa-check-circle text-success"></i>'
                            : '<i class="fas fa-hourglass-half text-warning"></i>';
                        stepsHtml += `<span style="margin-right:12px;">${icon} ${a.name}</span>`;
                    });
                    stepsHtml += `</div>`;
                });
                $('#approve_salary_steps').html(stepsHtml);

                if (isLast) {
                    const expectedVal = row.expected_salary ? Number(row.expected_salary) : '';
                    const expectedFormatted = expectedVal ? expectedVal.toLocaleString('id-ID') : '';

                    $('#approve_salary_field').html(
                        `<div class="form-group mb-0">
                             <label class="font-weight-bold text-dark mb-1" style="font-size: 14px;">APPROVED SALARY</label>
                             <div class="input-group">
                                 <div class="input-group-prepend">
                                     <span class="input-group-text bg-primary text-white font-weight-bold">Rp</span>
                                 </div>
                                 <input type="text" id="approved_salary_display" class="form-control font-weight-bold" value="${expectedFormatted}" placeholder="0" required>
                                 <input type="hidden" name="approved_salary" id="approved_salary" value="${expectedVal}">
                             </div>
                             <small class="form-text text-muted mt-1" style="font-size: 12px;">Otomatis terisi nominal Expected Salary. Bisa diubah jika nominal yang disetujui berbeda.</small>
                           </div>`
                    );
                } else {
                    $('#approve_salary_field').html(
                        `<p class="mb-0 text-muted" style="font-size:13px;">Approve tahap Management Dept untuk melanjutkan ke tahap General Manager.</p>`
                    );
                }
            });

            $(document).on('input', '#approved_salary_display', function () {
                let val = $(this).val().replace(/[^0-9]/g, '');
                $('#approved_salary').val(val);
                if (val) {
                    $(this).val(parseInt(val, 10).toLocaleString('id-ID'));
                } else {
                    $(this).val('');
                }
            });
        });
    </script>

    <script src="{{ asset('js/demo/datatables-demo.js') }}"></script>

</body>
</html>