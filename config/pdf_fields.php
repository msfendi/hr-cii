<?php

// Kalau nanti ada jenis dokumen lain (mis. Bill of Lading), tinggal
// tambah key baru di array ini, tidak perlu ubah kode parser.

return [

    'booking_confirmation' => [

        'general' => [
            'booking_date' => 'Booking Date',
        ],

        'party' => [
            'booked_by'   => 'Booked By',
            'shipper'     => 'Shipper',
            'consignee'   => 'Consignee',
            'os_agent'    => 'O/S Agent',
            'deliver_to'  => 'Deliver To',
        ],

        'routing' => [
            'vessel_voy'          => 'Vessel/Voy',
            'vessel_flag'         => 'Vessel Flag',
            'place_of_acceptance' => 'Place of Acceptance',
            'load_port'           => 'Load Port',
            'cut'                 => 'CUT',
            'etd'                 => 'ETD',
            'discharge_port'      => 'Discharge Port',
            'final_dest'          => 'Final Dest',
            'eta'                 => 'ETA',
            'place_of_delivery'   => 'Place of Delivery',
            'ss_line'             => 'SS Line',
        ],

        'compliance' => [
            'marine_ins' => 'Marine Ins',
            'obl_req'    => 'OBL Req',
            'haz'        => 'Haz',
            'ams'        => 'AMS',
            'aci'        => 'ACI',
        ],
    ],

];
