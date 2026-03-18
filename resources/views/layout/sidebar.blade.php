<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center">
        <div class="sidebar-brand-icon">
            <img src="{{ asset('img/chutex.svg') }}" style="width: 40px;">
        </div>
        <div class="sidebar-brand-text mx-3">Chutex <sup>Sys</sup></div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item active">
        <a class="nav-link" href="{{ route('home') }}">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span></a>
    </li>

    @if($roleusers[0]->rolename == 'Admin')
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseUser"
            aria-expanded="true" aria-controls="collapseUser">
            <i class="fas fa-fw fa-users"></i>
            <span>User</span>
        </a>
        <div id="collapseUser" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('user.index') }}">Daftar User</a>
                <a class="collapse-item" href="{{ route('role.index') }}">Daftar Role</a>
            </div>
        </div>
    </li>
    @endif

    @if($roleusers[0]->rolename == 'Admin' || $roleusers[0]->rolename == 'HRD')
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePKWT"
            aria-expanded="true" aria-controls="collapsePKWT">
            <i class="fas fa-fw fa-users"></i>
            <span>Karyawan</span>
        </a>
        <div id="collapsePKWT" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('pelamar.index') }}">Pelamar List</a>
                <a class="collapse-item" href="{{ route('biodata.index') }}">Biodata List</a>
                <a class="collapse-item" href="{{ route('dept.index') }}">Departement List</a>
                <a class="collapse-item" href="{{ route('approval.index') }}">Master Approval</a>
            </div>
        </div>
    </li>

    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseAudit"
            aria-expanded="true" aria-controls="collapseAudit">
            <i class="fas fa-fw fa-users"></i>
            <span>Audit</span>
        </a>
        <div id="collapseAudit" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('attendance.index') }}">Attendance List</a>
                <a class="collapse-item" href="{{ route('attendance.checkMasterData') }}">Check Master Data</a>
            </div>
        </div>
    </li>

    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseSyncronize"
            aria-expanded="true" aria-controls="collapseSyncronize">
            <i class="fas fa-fw fa-users"></i>
            <span>Syncronize Finger</span>
        </a>
        <div id="collapseSyncronize" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('attendance-finger.index') }}">Syncronize Finger</a>
            </div>
        </div>
    </li>

    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseOvertime"
            aria-expanded="true" aria-controls="collapseOvertime">
            <i class="fas fa-fw fa-users"></i>
            <span>Overtime</span>
        </a>
        <div id="collapseOvertime" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('overtime.index') }}">Overtime List</a>
                <a class="collapse-item" href="{{ route('overtime.calendar') }}">Overtime Calendar</a>
            </div>
        </div>
    </li>
    @endif

    @if($roleusers[0]->rolename == 'Admin' || $roleusers[0]->rolename == 'Dokter')
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePoliklinik"
            aria-expanded="true" aria-controls="collapsePoliklinik">
            <i class="fas fa-fw fa-medkit"></i>
            <span>Poliklinik</span>
        </a>
        <div id="collapsePoliklinik" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('kunjungan.index') }}">Daftar Kunjungan</a>
                <a class="collapse-item" href="{{ route('dokter.antrian') }}">Antrian Dokter</a>
                <a class="collapse-item" href="{{ route('report.rekap') }}">Rekap Kunjungan</a>
            </div>
        </div>
    </li>
    @endif

    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseInsentif"
            aria-expanded="true" aria-controls="collapseInsentif">
            <i class="fas fa-fw fa-calculator"></i>
            <span>Insentif</span>
        </a>
        <div id="collapseInsentif" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('line-insentif-master.index') }}">Line Insentif Master</a>
                <a class="collapse-item" href="{{ route('pad-insentif-master.index') }}">Pad Print Insentif Master</a>
                <a class="collapse-item" href="{{ route('cutting-insentif-master.index') }}">Cutting Insentif Master</a>
            </div>
        </div>
    </li>
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePayroll"
            aria-expanded="true" aria-controls="collapsePayroll">
            <i class="fas fa-fw fa-calculator"></i>
            <span>Payroll</span>
        </a>
        <div id="collapsePayroll" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('payroll-master.index') }}">Payroll Master</a>
                <a class="collapse-item" href="{{ route('payroll-components.index') }}">Payroll Components</a>
                <a class="collapse-item" href="{{ route('payroll-periods.index') }}">Payroll Period</a>
                <a class="collapse-item" href="{{ route('payroll-process.index') }}">Payroll Process</a>
                <a class="collapse-item" href="{{ route('payroll-approve.index') }}">Payroll Approve</a>
                <a class="collapse-item" href="{{ route('employee-payroll.index') }}">Employee Payroll</a>
            </div>
        </div>
    </li>
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseHoliday"
            aria-expanded="true" aria-controls="collapseHoliday">
            <i class="fas fa-fw fa-plane"></i>
            <span>Holiday</span>
        </a>
        <div id="collapseHoliday" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('holidays.index') }}">Holiday List</a>
            </div>
        </div>
    </li>
    
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseEvaluation"
            aria-expanded="true" aria-controls="collapseEvaluation">
            <i class="fas fa-fw fa-history"></i>
            <span>Evaluation</span>
        </a>
        <div id="collapseEvaluation" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('evaluation-jobscope.index') }}">Evaluation Jobscope</a>
                <a class="collapse-item" href="{{ route('evaluation-questionnaire.index') }}">Evaluation Questionnaire</a>
                <a class="collapse-item" href="{{ route('evaluation-employee.index') }}">Employee Evaluation</a>
            </div>
        </div>
    </li>


    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <!-- <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div> -->

</ul>
<!-- End of Sidebar -->