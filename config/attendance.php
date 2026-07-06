<?php

return [
    'office_latitude' => (float) env('ATTENDANCE_OFFICE_LATITUDE', -6.19990280418286),
    'office_longitude' => (float) env('ATTENDANCE_OFFICE_LONGITUDE', 106.8561197453926),
    'radius_meters' => (int) env('ATTENDANCE_RADIUS_METERS', 50),
    'min_radius_meters' => 10,
    'max_radius_meters' => 50,
];
