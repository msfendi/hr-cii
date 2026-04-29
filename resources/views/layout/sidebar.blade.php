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
   @if(in_array($role,['Admin','HRD']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#management">
      <i class="fas fa-users-cog"></i>
      <span>Management</span>
      </a>
      <div id="management" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            @if($role=='Admin')
            <a class="collapse-item" href="{{ route('user.index') }}">User</a>
            <a class="collapse-item" href="{{ route('role.index') }}">Role</a>
            @endif
            <a class="collapse-item" href="{{ route('pelamar.index') }}">Pelamar</a>
            <a class="collapse-item" href="{{ route('biodata.index') }}">Biodata</a>
            <a class="collapse-item" href="{{ route('dept.index') }}">Departement</a>
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
         </div>
      </div>
   </li>
   @endif
   {{-- ================= EXPAT ================= --}}
   @if(in_array($role,['Admin','HRD']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#expat">
      <i class="fas fa-globe"></i>
      <span>Expat</span>
      </a>
      <div id="expat" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('expat.master.index') }}">Master</a>
            <a class="collapse-item" href="{{ route('expat.onleave.index') }}">On Leave</a>
            <a class="collapse-item" href="{{ route('expat.cost.index') }}">Cost</a>
         </div>
      </div>
   </li>
   @endif
   {{-- ================= ATTENDANCE ================= --}}
   @if(in_array($role,['Admin','HRD']))
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
   @if(in_array($role,['Admin','HRD']))
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
         </div>
      </div>
   </li>
   @endif
   {{-- ================= INSENTIF ================= --}}
   @if(in_array($role,['Admin','Payroll_STAFF', 'Payroll_SEWING', 'Payroll_NONSEWING']))
   <li class="nav-item">
      <a class="nav-link collapsed" data-toggle="collapse" data-target="#insentif">
      <i class="fas fa-hand-holding-usd"></i>
      <span>Insentif</span>
      </a>
      <div id="insentif" class="collapse" data-parent="#accordionSidebar">
         <div class="collapse-inner bg-white rounded">
            <a class="collapse-item" href="{{ route('insentif.threshold.index') }}">Insentif Threshold</a>
            <a class="collapse-item" href="{{ route('line-insentif-master.index') }}">Line Insentif</a>
            <a class="collapse-item" href="{{ route('pad-insentif-master.index') }}">Pad Print Insentif</a>
            <a class="collapse-item" href="{{ route('cutting-insentif-master.index') }}">Cutting Insentif</a>
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
            <a class="collapse-item" href="{{ route('devices.index') }}">Whatsapp Device</a>
            <a class="collapse-item" href="{{ route('recruitment.index') }}">Recruitment</a>
         </div>
      </div>
   </li>
   @endif
   <hr class="sidebar-divider d-none d-md-block">
</ul>