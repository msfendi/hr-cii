@php
$role = $roleusers[0]->rolename;
@endphp
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
   {{-- BRAND --}}
   <a class="sidebar-brand d-flex align-items-center justify-content-center">
      <div class="sidebar-brand-icon">
         <img src="{{ asset('img/chutex.svg') }}" style="width:40px;">
      </div>
      <div class="sidebar-brand-text mx-3">Chutex <sup>Sys</sup></div>
   </a>
   <hr class="sidebar-divider my-0">
   {{-- DASHBOARD --}}
   <li class="nav-item active">
      <a class="nav-link" href="{{ route('home') }}">
      <i class="fas fa-tachometer-alt"></i>
      <span>Dashboard</span>
      </a>
   </li>
   {{-- ================= MANAGEMENT ================= --}}
   @if(in_array($role,['Admin','HRD', 'Payroll_STAFF','Payroll_SEWING','Payroll_NONSEWING']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#management">
      <i class="fas fa-users-cog"></i>
      <span>Management</span>
      </a>
      <div id="management" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            @if(in_array($role,['Admin','HRD','Payroll_STAFF','Payroll_SEWING','Payroll_NONSEWING']))
            <a class="collapse-item" href="{{ route('devices.index') }}">Whatsapp Device</a>
            <a class="collapse-item" href="{{ route('recruitment.index') }}">Recruitment</a>
            <a class="collapse-item" href="{{ route('recruitment-position.index') }}">Recruitment Position</a>
            <a class="collapse-item" href="{{ route('pelamar.index') }}">Pelamar</a>
            <hr>
            <a class="collapse-item" href="{{ route('parent-dept.index') }}">Parent Departement</a>
            <a class="collapse-item" href="{{ route('dept.index') }}">Departement</a>
            <a class="collapse-item" href="{{ route('biodata.index') }}">Biodata</a>
            <a class="collapse-item" href="{{ route('biodata_keluar.index') }}">Biodata Keluar</a>
            <a class="collapse-item" href="{{ route('employee_exit_history.index') }}">Riwayat Karyawan Keluar</a>
            <a class="collapse-item" href="{{ route('employees-contract.index') }}">Kontrak Karyawan</a>
            <hr>
            <a class="collapse-item" href="{{ route('ijin-meninggalkan-pekerjaan.index') }}">Meninggalkan Pekerjaan</a>
            @endif
         </div>
      </div>
   </li>
   @endif
   {{-- ================= APPROVAL ================= --}}
   @if(in_array($role,['Admin','Management']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#approval">
      <i class="fas fa-check"></i>
      <span>Approval</span>
      </a>
      <div id="approval" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('insentif-approve.index') }}">Insentif Approval</a>
            <a class="collapse-item" href="{{ route('payroll-approve.index') }}">Payroll Approval</a>
            <a class="collapse-item" href="{{ route('thr-approve.index') }}">THR Approval</a>
            <a class="collapse-item" href="{{ route('compensation-approve.index') }}">Compensation Approval</a>
         </div>
      </div>
   </li>
   @endif
   {{-- ================= EXPAT ================= --}}
   @if(in_array($role,['Admin','GA']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#expat">
      <i class="fas fa-globe"></i>
      <span>Expat Master</span>
      </a>
      <div id="expat" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('chu-family.index') }}">Chu Family</a>
            <hr>
            <a class="collapse-item" href="{{ route('expat.master.index') }}">Master</a>
            <a class="collapse-item" href="{{ route('expat.onleave.index') }}">On Leave</a>
            <a class="collapse-item" href="{{ route('expat.cost.index') }}">Cost</a>
            <a class="collapse-item" href="{{ route('epo.index') }}">EPO</a>
         </div>
      </div>
   </li>
   @endif
   {{-- ================= GUEST MASTER ================= --}}
   @if(in_array($role,['Admin','GA']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#guest-master">
      <i class="fas fa-globe"></i>
      <span>Guest Master</span>
      </a>
      <div id="guest-master" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('guest-master.index') }}">Guest Master</a>
            <a class="collapse-item" href="{{ route('foreign-guest.index') }}">Foreign Guest</a>
         </div>
      </div>
   </li>
   @endif
   {{-- ================= ATTENDANCE ================= --}}
   @if(in_array($role,['Admin','HRD', 'Payroll_STAFF','Payroll_SEWING','Payroll_NONSEWING']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#attendance">
      <i class="fas fa-clock"></i>
      <span>Attendance</span>
      </a>
      <div id="attendance" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('attendance.index') }}">Attendance</a>
            <a class="collapse-item" href="{{ route('attendance.checkMasterData') }}">Check Master</a>
            <a class="collapse-item" href="{{ route('attendance-finger.index') }}">Sync Finger</a>
            <a class="collapse-item" href="{{ route('overtime.index') }}">Overtime</a>
            <a class="collapse-item" href="{{ route('overtime.calendar') }}">Calendar</a>
            <a class="collapse-item" href="{{ route('shift.index') }}">Shift</a>
            <a class="collapse-item" href="{{ route('employee-shift.index') }}">Employee Shift</a>
            <a class="collapse-item" href="{{ route('late-compensation.index') }}">Late Compensation</a>
         </div>
      </div>
   </li>
   @endif
   {{-- ================= LEAVE & HOLIDAY ================= --}}
   @if(in_array($role,['Admin','HRD', 'Payroll_STAFF','Payroll_SEWING','Payroll_NONSEWING']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#leave">
      <i class="fas fa-plane"></i>
      <span>Leave & Holiday</span>
      </a>
      <div id="leave" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('leave-balances.index') }}">Leave Balance</a>
            <a class="collapse-item" href="{{ route('leave-recap.index') }}">Leave Recap</a>
            <a class="collapse-item" href="{{ route('approval.index') }}">Approval</a>
            <a class="collapse-item" href="{{ route('holidays.index') }}">Holiday</a>
         </div>
      </div>
   </li>
   @endif
   {{-- ================= POLIKLINIK ================= --}}
   @if(in_array($role,['Admin','Dokter']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#clinic">
      <i class="fas fa-medkit"></i>
      <span>Poliklinik</span>
      </a>
      <div id="clinic" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('kunjungan.index') }}">Kunjungan</a>
            <a class="collapse-item" href="{{ route('dokter.antrian') }}">Antrian</a>
            <a class="collapse-item" href="{{ route('report.rekap') }}">Rekap</a>
            <a class="collapse-item" href="{{ route('health-test.index') }}">Test Kesehatan</a>
         </div>
      </div>
   </li>
   @endif
   {{-- ================= PAYROLL ================= --}}
   @if(in_array($role,['Admin','Payroll_STAFF', 'Payroll_SEWING', 'Payroll_NONSEWING']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#payroll">
      <i class="fas fa-calculator"></i>
      <span>Payroll</span>
      </a>
      <div id="payroll" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('payroll-setting.index') }}">Setting</a>
            <a class="collapse-item" href="{{ route('payroll-master.index') }}">Master</a>
            <a class="collapse-item" href="{{ route('payroll-adjusments.index') }}">Payroll Adjusments</a>
            <a class="collapse-item" href="{{ route('payroll-components.index') }}">Components</a>
            <a class="collapse-item" href="{{ route('payroll-periods.index') }}">Period</a>
            <a class="collapse-item" href="{{ route('payroll-process.index') }}">Process</a>
            <a class="collapse-item" href="{{ route('employee-payroll.index') }}">Employee Payroll</a>
            <hr>
            <a class="collapse-item" href="{{ route('thr-periods.index') }}">THR Period</a>
            <a class="collapse-item" href="{{ route('thr-process.index') }}">THR Process</a>
            <hr>
            <a class="collapse-item" href="{{ route('bpjs-exceptions.index') }}">BPJS Exceptions</a>
            <hr>
            @if(in_array($role,['Admin']))
            <a class="collapse-item" href="{{ route('compensation.index') }}">Compensation</a>
            @endif
         </div>
      </div>
   </li>
   @endif
   {{-- ================= INSENTIF ================= --}}
   @if(in_array($role,['Admin','Payroll_STAFF', 'Payroll_SEWING', 'Payroll_NONSEWING', 'IE']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#insentif">
      <i class="fas fa-hand-holding-usd"></i>
      <span>Insentif</span>
      </a>
      <div id="insentif" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            @if(in_array($role,['Admin','Payroll_STAFF', 'Payroll_SEWING', 'Payroll_NONSEWING']))
            <a class="collapse-item" href="{{ route('insentif.threshold.index') }}">Insentif Threshold</a>
            <!-- <a class="collapse-item" href="{{ route('dept-insentif-role.index') }}">Insentif Mapping</a> -->
            <a class="collapse-item" href="{{ route('insentif-role-formulas.index') }}">Insentif Formula</a>
            <a class="collapse-item" href="{{ route('sewing-violations.index') }}">Sewing Violations</a>
            <hr>
            @endif
            @if(in_array($role,['Admin','Payroll_STAFF', 'Payroll_SEWING', 'Payroll_NONSEWING', 'IE']))
            <a class="collapse-item" href="{{ route('line-insentif-master.index') }}">Line Insentif</a>
            <a class="collapse-item" href="{{ route('pad-insentif-master.index') }}">Pad Print Insentif</a>
            <a class="collapse-item" href="{{ route('cutting-insentif-master.index') }}">Cutting Insentif</a>
            <a class="collapse-item" href="{{ route('heat-insentif-master.index') }}">Heat Seal Insentif</a>
            <a class="collapse-item" href="{{ route('employee6s.index') }}">6S Insentif</a>
            @endif
         </div>
      </div>
   </li>
   @endif
   {{-- ================= EVALUATION ================= --}}
   @if(in_array($role,['Admin','HRD']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#evaluation">
      <i class="fas fa-clipboard-check"></i>
      <span>Evaluation</span>
      </a>
      <div id="evaluation" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
                <a class="collapse-item" href="{{ route('evaluation-jobscope.index') }}">Evaluation Jobscope</a>
                <a class="collapse-item" href="{{ route('evaluation-questionnaire.index') }}">Evaluation
                    Questionnaire</a>
                <a class="collapse-item" href="{{ route('evaluation-employee.index') }}">Employee Evaluation</a>
         </div>
      </div>
   </li>
   @endif
   {{-- ================= SYSTEM ================= --}}
   @if(in_array($role,['Admin','HRD']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#system">
      <i class="fas fa-cog"></i>
      <span>System</span>
      </a>
      <div id="system" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('user.index') }}">User</a>
            <a class="collapse-item" href="{{ route('role.index') }}">Role</a>
            <a class="collapse-item" href="{{ route('activity-logs.index') }}">Activity Logs</a>
         </div>
      </div>
   </li>
   @endif
   <hr class="sidebar-divider d-none d-md-block">
</ul>



    <div id="notif-container"
        style="position:fixed;top:20px;right:20px;z-index:9999;">
    </div>

    <!-- Pusher -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    <!-- Laravel Echo -->
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo/dist/echo.iife.js"></script>

    <script>
        window.Pusher = Pusher;

        const EchoClass = window.Echo.default ?? window.Echo;
        const wsHost = window.location.hostname;

        window.Echo = new EchoClass({
            broadcaster: 'pusher',

            key: window.reverbKey,

            wsHost: wsHost,
            wsPort: 8800,
            wssPort: 443,

            cluster: 'mt1',

            forceTLS: window.location.protocol === 'https:',
            disableStats: true,
            enabledTransports: ['ws','wss'],
        });
    </script>
    <script>
window.Echo.channel('notification-channel')
.listen('NotificationEvent', (e) => {

    /*
    =====================================
    TYPE CONFIG
    =====================================
    */

    const types = {

        success:{
            border:'#28a745',
            bg:'#e9f9ee',
            color:'#28a745',
            icon:'✔'
        },

        danger:{
            border:'#dc3545',
            bg:'#fdeaea',
            color:'#dc3545',
            icon:'✖'
        },

        warning:{
            border:'#ffc107',
            bg:'#fff8e1',
            color:'#ffc107',
            icon:'⚠'
        }

    };

    const type = types[e.type] ?? types.success;

    /*
    =====================================
    BUILD NOTIFICATION
    =====================================
    */

    const notif = document.createElement('div');

    notif.innerHTML = `
    <div style="
        display:flex;
        align-items:center;
        gap:14px;
        background:rgba(255,255,255,.95);
        backdrop-filter:blur(10px);
        border-left:5px solid ${type.border};
        padding:16px;
        margin-bottom:12px;
        border-radius:12px;
        box-shadow:0 8px 18px rgba(0,0,0,.12);
        font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
        animation:slideIn .35s ease;
    ">

        <!-- ICON -->
        <div style="
            width:42px;
            height:42px;
            border-radius:50%;
            background:${type.bg};
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:18px;
            color:${type.color};
            font-weight:bold;
            flex-shrink:0;
        ">
            ${type.icon}
        </div>

        <!-- CONTENT -->
        <div style="flex:1">

            <div style="
                font-size:15px;
                font-weight:600;
                color:#222;
                margin-bottom:3px;
            ">
                ${e.title}
            </div>

            <div style="
                font-size:14px;
                color:#555;
                line-height:1.5;
            ">
                ${e.message}
            </div>

        </div>

    </div>
    `;

    document.getElementById('notif-container')
        .prepend(notif);

    /*
    =====================================
    AUTO REMOVE
    =====================================
    */

    setTimeout(() => {
        notif.style.opacity = 0;
        notif.style.transform = 'translateY(-10px)';
        setTimeout(()=>notif.remove(),300);
    },4000);

});
</script>