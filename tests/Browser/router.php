<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$directory = getenv('PROJECTLINK_ANALYTICS_FIXTURES') ?: sys_get_temp_dir().'/projectlink-analytics';
$version = $_COOKIE['analytics_version'] ?? 'before';
$role = $_COOKIE['analytics_role'] ?? 'student_org';
if (preg_match('~^/fixtures/(before|after)/(empty-)?(celp_secretary|coordinator|celp_head|csa_director|student_org|college_department|participant)$~', $path, $match)) {
    $version = $match[1];
    $role = $match[3];
    setcookie('analytics_version', $version, ['path' => '/', 'samesite' => 'Lax']);
    setcookie('analytics_role', $role, ['path' => '/', 'samesite' => 'Lax']);
    header('Content-Type: text/html; charset=UTF-8');
    readfile($directory.'/'.$version.'-'.($match[2] ?? '').$role.'.html');

    return;
}
if ($path === '/session/activity') {
    header('Content-Type: application/json');
    echo '{"ok":true}';

    return;
}
if (! in_array($version, ['before', 'after']) || ! in_array($role, ['celp_secretary', 'coordinator', 'celp_head', 'csa_director', 'student_org', 'college_department', 'participant'])) {
    http_response_code(400);

    return;
}
$suffix = match ($path) {
    '/student-org/proposals' => 'proposals','/projects/all' => 'all','/projects/review','/projects/second-review','/projects/third-review','/projects/fourth-review' => 'queue',default => null
};
if ($suffix && is_file($directory.'/'.$version.'-'.$role.'-'.$suffix.'.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($directory.'/'.$version.'-'.$role.'-'.$suffix.'.html');

    return;
}
$public = realpath(dirname(__DIR__, 2).'/public');
$asset = realpath($public.'/'.rawurldecode($path));
if ($asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset) && ! str_starts_with($path, '/storage/')) {
    return false;
}
http_response_code(404);
echo 'Local analytics fixture route unavailable.';
