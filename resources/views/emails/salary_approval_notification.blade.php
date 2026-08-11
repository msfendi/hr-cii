<!DOCTYPE html>
<html lang='id'>

<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='color-scheme' content='light dark'>
    <meta name='supported-color-schemes' content='light dark'>

    <title>Salary Approval Notification</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #eef2f7;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
        }

        table {
            border-collapse: collapse;
        }

        a {
            text-decoration: none;
        }

        /* ==============================
   COMPACT SPACING
   ============================== */

        .wrapper {
            width: 100%;
            padding: 20px 12px;
            box-sizing: border-box;
        }

        .main-card {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #dfe5ef;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }


        /* ==============================
   HEADER
   ============================== */

        .header {
            padding: 20px 26px;
            background: #f8faff;
            border-bottom: 1px solid #e6eaf1;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: #4f46e5;
            border-radius: 10px;
            text-align: center;
            vertical-align: middle;
            font-size: 18px;
        }

        .badge {
            display: inline-block;
            margin-left: 8px;
            padding: 5px 10px;
            background: #e8eaff;
            color: #3730a3;
            border-radius: 20px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .title {
            margin: 12px 0 0 0;
            color: #172033;
            font-size: 21px;
            line-height: 1.3;
            font-weight: 800;
        }


        /* ==============================
   BODY
   ============================== */

        .body {
            padding: 22px 26px 24px 26px;
        }

        .greeting {
            margin: 0 0 5px 0;
            color: #172033;
            font-size: 15px;
            font-weight: 700;
        }

        .description {
            margin: 0 0 18px 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.55;
        }


        /* ==============================
   DETAIL CARD
   ============================== */

        .info-card {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .info-title {
            padding: 10px 14px;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .info-label {
            width: 38%;
            padding: 9px 12px;
            color: #64748b;
            font-size: 11px;
            vertical-align: middle;
        }

        .info-value {
            width: 62%;
            padding: 9px 12px;
            color: #172033;
            font-size: 12px;
            font-weight: 700;
            vertical-align: middle;
            word-break: break-word;
        }

        .salary {
            color: #047857;
            font-size: 13px;
        }


        /* ==============================
   APPROVAL CTA
   ============================== */

        .approval-box {
            text-align: center;
            padding: 18px 16px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 12px;
        }

        .approval-title {
            margin: 0 0 4px 0;
            color: #1e1b4b;
            font-size: 15px;
            font-weight: 800;
        }

        .approval-text {
            margin: 0 0 13px 0;
            color: #64748b;
            font-size: 11px;
            line-height: 1.5;
        }


        /* ==============================
   MAIN BUTTON
   ============================== */

        .approval-button {
            display: inline-block;
            min-width: 230px;
            padding: 12px 22px;
            background-color: #4338ca !important;
            background: #4338ca !important;
            color: #ffffff !important;
            border: 2px solid #3730a3;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 800;
            text-align: center;
            box-shadow: 0 4px 12px rgba(67, 56, 202, 0.22);
        }


        /* ==============================
   MANUAL URL
   ============================== */

        .manual-link {
            margin: 10px 0 0 0;
            color: #64748b;
            font-size: 10px;
            line-height: 1.45;
            word-break: break-all;
        }


        /* ==============================
   SECURITY NOTE
   ============================== */

        .security-note {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #d9def0;
            color: #64748b;
            font-size: 9px;
            line-height: 1.45;
        }


        /* ==============================
   FOOTER
   ============================== */

        .footer {
            padding: 13px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .footer-main {
            margin: 0;
            color: #64748b;
            font-size: 10px;
            line-height: 1.5;
        }

        .footer-small {
            color: #94a3b8;
            font-size: 9px;
        }


        /* ==============================
   MOBILE
   ============================== */

        @media only screen and (max-width: 480px) {

            .wrapper {
                padding: 10px 7px !important;
            }

            .header {
                padding: 18px 18px !important;
            }

            .body {
                padding: 18px 18px 20px 18px !important;
            }

            .title {
                font-size: 19px !important;
            }

            .info-label {
                width: 40% !important;
                padding: 8px 9px !important;
                font-size: 10px !important;
            }

            .info-value {
                width: 60% !important;
                padding: 8px 9px !important;
                font-size: 11px !important;
            }

            .approval-box {
                padding: 16px 12px !important;
            }

            .approval-button {
                display: block !important;
                width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
                padding: 12px 14px !important;
            }

            .footer {
                padding: 12px 16px !important;
            }
        }
    </style>
</head>

<body>

    <table width='100%' cellpadding='0' cellspacing='0' border='0'>
        <tr>
            <td align='center'>

                <div class='wrapper'>

                    <!-- MAIN CARD -->
                    <table class='main-card' cellpadding='0' cellspacing='0' border='0'>
                        <!-- HEADER -->
                        <tr>
                            <td class='header'>
                                <h1 class='title'>
                                    Salary Approval Request Notification
                                </h1>
                            </td>
                        </tr>

                        <!-- BODY -->
                        <tr>
                            <td class='body'>

                                <!-- GREETING -->
                                <p class='greeting'>
                                    Hi, {{ $approverName }},
                                </p>

                                <p class='description'>
                                    There is a new salary request for a
                                    <strong style='color:#172033;'>Staff</strong>
                                    employee that requires your approval at the
                                    <span class='step'>
                                        {{ $stepName }}
                                    </span> stage.
                                </p>


                                <!-- DETAIL CARD -->
                                <table class='info-card' width='100%' cellpadding='0' cellspacing='0' border='0'>

                                    <tr>
                                        <td colspan='2' class='info-title'>
                                            📋 &nbsp; REQUEST DETAILS
                                        </td>
                                    </tr>

                                    <tr class='info-row'>
                                        <td class='info-label'>
                                            👤 &nbsp; Applicant Name
                                        </td>
                                        <td class='info-value'>
                                            {{ $namaPelamar }}
                                        </td>
                                    </tr>

                                    <tr class='info-row'>
                                        <td class='info-label'>
                                            💼 &nbsp; Position / Title
                                        </td>
                                        <td class='info-value'>
                                            {{ $jabatan }}
                                        </td>
                                    </tr>

                                    <tr class='info-row'>
                                        <td class='info-label'>
                                            🏢 &nbsp; Department
                                        </td>
                                        <td class='info-value'>
                                            {{ $department }}
                                        </td>
                                    </tr>

                                    <tr class='info-row'>
                                        <td class='info-label'>
                                            💰 &nbsp; Expected Salary
                                        </td>
                                        <td class='info-value salary'>
                                            {{ $expectedSalaryFmt }}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class='info-label'>
                                            📌 &nbsp; Approval Stage
                                        </td>
                                        <td class='info-value'>
                                            <span class='step'>
                                                {{ $stepName }}
                                            </span>
                                        </td>
                                    </tr>

                                </table>

                                <div class='approval-box'>

                                    <br>
                                    <p class='approval-title'>
                                        Request Awaiting Your Approval
                                    </p>

                                    <p class='approval-text'>
                                        Please click the button below to view
                                        the request details and take action.
                                    </p>

                                    <!-- MAIN BUTTON -->
                                    <a href='{{ $approvalUrl }}' class='approval-button' style='background:#4338ca !important;
                                          background-color:#4338ca !important;
                                          color:#ffffff !important;
                                          border:2px solid #3730a3;
                                          border-radius:10px;
                                          display:inline-block;
                                          padding:15px 28px;
                                          min-width:260px;
                                          text-align:center;
                                          font-size:15px;
                                          font-weight:800;
                                          text-decoration:none;'>
                                        <span style='color:#ffffff !important;'>
                                            🔗 &nbsp; OPEN APPROVAL PAGE
                                        </span>
                                    </a>
                                </div>

                            </td>
                        </tr>
                    </table>

                </div>

            </td>
        </tr>
    </table>

</body>

</html>