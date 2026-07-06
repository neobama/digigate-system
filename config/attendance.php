<?php

$officeLatitude = env('ATTENDANCE_OFFICE_LATITUDE');
$officeLongitude = env('ATTENDANCE_OFFICE_LONGITUDE');

return [
    // Empty .env values must not become 0 — (float) env('KEY', default) ignores default when KEY=""
    'office_latitude' => (float) (filled($officeLatitude) ? $officeLatitude : -6.199934),
    'office_longitude' => (float) (filled($officeLongitude) ? $officeLongitude : 106.856236),
    'radius_meters' => (int) (filled(env('ATTENDANCE_RADIUS_METERS')) ? env('ATTENDANCE_RADIUS_METERS') : 50),
    'min_radius_meters' => 10,
    'max_radius_meters' => 50,
];
