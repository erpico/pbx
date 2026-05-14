<?php
  
use App\Middleware\OnlyAdmin;
use App\Middleware\SecureRouteMiddleware;
use App\Middleware\SetRoles;
use Slim\Http\Request;
use Slim\Http\Response;
  
  // Legacy routes

$app->get('/legacy/phones', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $phones = new \Erpico\Phones($app->getContainer());
    return $response->withJson([
        "data" => $phones->getPhones($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $phones->getPhones($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/dashboard', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $phones = new \Erpico\Phones($app->getContainer());

    return $response->withJson([
        "data" => $phones->getPhones($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $phones->getPhones($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');



$app->get('/legacy/cdr_report', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $cdr_report = new \Erpico\Cdr_report($app->getContainer());

    $stat = $cdr_report->getCdr_report($filter, $pos, $count, 1);

    return $response->withJson([
        "data" => $cdr_report->getCdr_report($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $stat['total'],
        "stat" => $stat
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/cdr_report_total', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $cdr_report = new \Erpico\Cdr_report($app->getContainer());

    $stat = $cdr_report->getCdr_report_total($filter, $pos, $count, 1);

    return $response->withJson([
        "data" => $cdr_report->getCdr_report_total($filter, $pos, $count, 0),
        "total_count" => $stat['total'],
        "stat" => $stat
    ]);

})->add('\App\Middleware\OnlyAuthUser');



$app->get('/legacy/call_recording', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $call_recording = new \Erpico\Call_recording($app->getContainer());

    $stat = $call_recording->getCall_recording_3($filter, $pos, $count, 1);

    return $response->withJson([
        "data" => $call_recording->getCall_recording_3($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $stat['total'],
        "stat" => $stat
    ]);

})->add('\App\Middleware\OnlyAuthUser');



$app->get('/legacy/contact_cdr_report', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $contact_cdr_report = new \Erpico\Contact_cdr_report($app->getContainer());

    $stat = $contact_cdr_report->getPlainContact_cdr_report($filter, $pos, $count, 1);

    return $response->withJson([
        "data" => $contact_cdr_report->getPlainContact_cdr_report($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $stat['total'],
        "stat" => $stat
    ]);

})->add('\App\Middleware\OnlyAuthUser');



$app->get('/legacy/record_contact_center', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $record_contact_center = new \Erpico\Record_contact_center($app->getContainer());

    $stat = $record_contact_center->getRecord_contact_center($filter, $pos, $count, 1);

    return $response->withJson([
        "data" => $record_contact_center->getRecord_contact_center($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $stat['total'],
        "stat" => $stat
    ]);

})->add('\App\Middleware\OnlyAuthUser');



$app->get('/legacy/grouped_agents_reports', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getAgent_reports($filter)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_queues_table_chart1', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getQueues_table_chart1($filter)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_queues_table_chart2', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getQueues_table_chart2($filter)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_queues_table_chart3', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getQueues_table_chart3($filter)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_queues_table_chart4', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getQueues_table_chart4($filter)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_queues_table_chart5', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getQueues_table_chart5($filter)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_queues_table_total', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getQueues_table_total($filter)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_queues_name', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getQueues_name($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getQueues_name($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/grouped_queues_name/short', function (Request $request, Response $response, array $args) use ($app) {
  $filter = $request->getParam('filter', []);

  $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
  return $response->withJson($grouped_reports->getQueues_name($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_reports_total', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getGrouped_reports_total($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getGrouped_reports_total($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/grouped_reports_total_chart1', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getGrouped_reports_total_chart1($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getGrouped_reports_total_chart1($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_reports_total_chart2', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getGrouped_reports_total_chart2($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getGrouped_reports_total_chart2($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_reports_total_chart3', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getGrouped_reports_total_chart3($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getGrouped_reports_total_chart3($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_reports_total_chart4', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getGrouped_reports_total_chart4($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getGrouped_reports_total_chart4($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_reports_total_chart5', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getGrouped_reports_total_chart5($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getGrouped_reports_total_chart5($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_reports_total_chart6', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getGrouped_reports_total_chart6($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getGrouped_reports_total_chart6($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/grouped_reports_total_chart7', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $grouped_reports = new \Erpico\Grouped_reports($app->getContainer());
    return $response->withJson([
        "data" => $grouped_reports->getGrouped_reports_total_chart7($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $grouped_reports->getGrouped_reports_total_chart7($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->map(['GET', 'OPTIONS'], '/legacy/lost_calls_list', function (Request $request, Response $response, array $args) use ($app) {
    if ($request->getMethod() === 'OPTIONS') {
      return $response->withJson([]);
    }
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $lost_calls = new \Erpico\Lost_calls($app->getContainer());
    return $response->withJson([
        "data" => $lost_calls->getLost_calls_list($filter, $pos, $count, 0),
        "total_count" => $lost_calls->getLost_calls_list($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->map(['GET', 'OPTIONS'], '/legacy/lost_calls_total', function (Request $request, Response $response, array $args) use ($app) {
    if ($request->getMethod() === 'OPTIONS') {
      return $response->withJson([]);
    }
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $lost_calls = new \Erpico\Lost_calls($app->getContainer());
    return $response->withJson([
    "data" => $lost_calls->getLost_calls_total($filter/*, $pos, $count, 0*/),
        // "total_count" => $lost_calls->getLost_calls_total($filter, $pos, $count, 1)
    ]);

})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/traffic_for_period', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $analysis_outgoing_calls = new \Erpico\Analysis_outgoing_calls($app->getContainer());
    return $response->withJson([
        "data" => $analysis_outgoing_calls->getTraffic_for_period($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $analysis_outgoing_calls->getTraffic_for_period($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/popular_city_for_period', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $analysis_outgoing_calls = new \Erpico\Analysis_outgoing_calls($app->getContainer());
    return $response->withJson([
        "data" => $analysis_outgoing_calls->getPopular_city_for_period($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $analysis_outgoing_calls->getPopular_city_for_period($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/popular_longdistance_over_period', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $analysis_outgoing_calls = new \Erpico\Analysis_outgoing_calls($app->getContainer());
    return $response->withJson([
        "data" => $analysis_outgoing_calls->getPopular_longdistance_over_period($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $analysis_outgoing_calls->getPopular_longdistance_over_period($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/popular_cell_for_period', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $analysis_outgoing_calls = new \Erpico\Analysis_outgoing_calls($app->getContainer());
    return $response->withJson([
        "data" => $analysis_outgoing_calls->getPopular_cell_for_period($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $analysis_outgoing_calls->getPopular_cell_for_period($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/most_calling_employees_for_period', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $analysis_outgoing_calls = new \Erpico\Analysis_outgoing_calls($app->getContainer());
    return $response->withJson([
        "data" => $analysis_outgoing_calls->getMost_calling_employees_for_period($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $analysis_outgoing_calls->getMost_calling_employees_for_period($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/operators_work_report_list', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $operators_work_report = new \Erpico\Operators_work_report($app->getContainer());
    /*return $response->withJson([
        "data" => $operators_work_report->getOperators_work_report_list($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $operators_work_report->getOperators_work_report_list($filter, $pos, $count, 1)
    ]);*/
    return $response->withJson($operators_work_report->getOperators_work_report_list($filter, 0, 100, 0));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/operators_work_report', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $operators_work_report = new \Erpico\Operators_work_report($app->getContainer());
    /*return $response->withJson([
        "data" => $operators_work_report->getOperators_work_report($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $operators_work_report->getOperators_work_report($filter, $pos, $count, 1)
    ]);*/
    return $response->withJson($operators_work_report->getOperators_work_report($filter, 0, 100, 0));    
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));


$app->get('/legacy/interval_reports_day', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $interval_reports = new \Erpico\Interval_reports($app->getContainer());
    return $response->withJson([
        "data" => $interval_reports->getInterval_reports_day($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $interval_reports->getInterval_reports_day($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/interval_reports_day_chart1', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $interval_reports = new \Erpico\Interval_reports($app->getContainer());
    return $response->withJson([
        "data" => $interval_reports->getInterval_reports_day_chart1($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $interval_reports->getInterval_reports_day_chart1($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/interval_reports_day_chart2', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $interval_reports = new \Erpico\Interval_reports($app->getContainer());
    return $response->withJson([
        "data" => $interval_reports->getInterval_reports_day_chart2($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $interval_reports->getInterval_reports_day_chart2($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/interval_reports_day_chart3', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $interval_reports = new \Erpico\Interval_reports($app->getContainer());
    return $response->withJson([
        "data" => $interval_reports->getInterval_reports_day_chart3($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $interval_reports->getInterval_reports_day_chart3($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/interval_reports_day_chart4', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $interval_reports = new \Erpico\Interval_reports($app->getContainer());
    return $response->withJson([
        "data" => $interval_reports->getInterval_reports_day_chart4($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $interval_reports->getInterval_reports_day_chart4($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/interval_reports_day_chart5', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $interval_reports = new \Erpico\Interval_reports($app->getContainer());
    return $response->withJson([
        "data" => $interval_reports->getInterval_reports_day_chart5($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $interval_reports->getInterval_reports_day_chart5($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/daily_report', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $daily_report = new \Erpico\Daily_report($app->getContainer());
    return $response->withJson([
        "data" => $daily_report->getDaily_report($filter, $pos, $count, 0),
        "total_count" => $daily_report->getDaily_report($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/month_traffic_period', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $month_traffic = new \Erpico\Month_traffic($app->getContainer());
    return $response->withJson($month_traffic->getMonth_traffic_period($filter, $pos, $count, 0)
        // "pos" => $pos,
        // "total_count" => $month_traffic->getMonth_traffic_period($filter, $pos, $count, 1)
    );
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/month_traffic', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $month_traffic = new \Erpico\Month_traffic($app->getContainer());
    return $response->withJson([
        "data" => $month_traffic->getMonth_traffic($filter, $pos, $count, 0)
        // "pos" => $pos,
        // "total_count" => $month_traffic->getMonth_traffic($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/month_traffic_chart', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $month_traffic = new \Erpico\Month_traffic($app->getContainer());
    return $response->withJson([
        "data" => $month_traffic->getMonth_traffic_chart($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $month_traffic->getMonth_traffic_chart($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/hourly_load_chart1', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $hourly_load = new \Erpico\Hourly_load($app->getContainer());
    return $response->withJson($hourly_load->getHourly_load_chart1($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/hourly_load_chart2', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $hourly_load = new \Erpico\Hourly_load($app->getContainer());
    return $response->withJson($hourly_load->getHourly_load_chart2($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/hourly_load', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $hourly_load = new \Erpico\Hourly_load($app->getContainer());
    return $response->withJson([
        "data" => $hourly_load->getHourly_load($filter, $pos, $count, 0),
        // "pos" => $pos,
        // "total_count" => $hourly_load->getHourly_load($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/compare_calls_chart', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $compare_calls = new \Erpico\Compare_calls($app->getContainer());
    return $response->withJson($compare_calls->getCompare_calls_chart($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/compare_calls', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $compare_calls = new \Erpico\Compare_calls($app->getContainer());
    return $response->withJson([
        "data" => $compare_calls->getCompare_calls($filter, $pos, $count, 0),
        "total_count" => $compare_calls->getCompare_calls($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_incoming_external_total', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $incoming_external = new \Erpico\Ext_incoming_external($app->getContainer());
    return $response->withJson([
        "data" => $incoming_external->getExt_incoming_external_total($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $incoming_external->getExt_incoming_external_total($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_incoming_external_personal', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $incoming_external = new \Erpico\Ext_incoming_external($app->getContainer());
    return $response->withJson([
        "data" => $incoming_external->getExt_incoming_external_personal($filter, $pos, $count, 0),
        "pos" => $pos
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_incoming_internal', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $incoming_internal = new \Erpico\Ext_incoming_internal($app->getContainer());
    return $response->withJson([
        "data" => $incoming_internal->getExt_incoming_internal($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $incoming_internal->getExt_incoming_internal($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_outgoing', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $outgoing = new \Erpico\Ext_outgoing($app->getContainer());
    return $response->withJson([
        "data" => $outgoing->getExt_outgoing($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $outgoing->getExt_outgoing($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_dashboard_agentslist[/[{id}]]', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    if (isset($args['id'])) {
        $filter['user_id'] = intval($args['id']);
    }

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    return $response->withJson([
        "data" => $dashboard->getExt_dashboard_agentslist($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $dashboard->getExt_dashboard_agentslist($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/ext_dashboard_agentslist/{id}/short', function (Request $request, Response $response, array $args) use ($app) {
    if (isset($args['id'])) {
        $filter['user_id'] = intval($args['id']);
    }

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    return $response->withJson($dashboard->getExt_dashboard_agentslist($filter, $pos, $count, 0));

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/ext_dashboard_getgages', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    return $response->withJson($dashboard->getExt_dashboard_getgages($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_dashboard_agents_stat', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    return $response->withJson([
        "data" => $dashboard->getExt_dashboard_agentsstat($filter, $pos, $count, 0),
        "pos" => $pos
        // "total_count" => $dashboard->getExt_dashboard_agentsstat($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_dashboard_agents_stat_chart', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    return $response->withJson($dashboard->getExt_dashboard_agentsstat($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_dashboard_treetable', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    return $response->withJson($dashboard->getExt_dashboard_treetable($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_dashboard_callschart', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    return $response->withJson($dashboard->getExt_dashboard_callschart($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_dashboard_hourschart', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    return $response->withJson($dashboard->getExt_dashboard_hourschart($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext_dashboard_worktimechart', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $dashboard = new \Erpico\Ext_dashboard($app->getContainer());
    
    return $response->withJson($dashboard->getExt_dashboard_worktimechart($filter, $pos, $count, 0));

})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext/checklist/list[/{params:.*}]', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $params = explode('/', $args['params']);

    if (isset($params)) {
        $filter['checklist_id'] = intval($params[0]);
    }

    if (isset($params)) {
        $filter['user_id'] = intval($params[1]);
    }

    if (isset($params)) {
        $filter['date'] = strtotime($params[2]);
    }

    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    return $response->withJson([
        "data" => $checklist->getExt_checklist_list($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $checklist->getExt_checklist_list($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/ext/checklist/tree', function (Request $request, Response $response, array $args) use ($app) {
    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    $parent = $request->getParam('parent', 0);
    $start = $request->getParam('start', 0);
    $finish = $request->getParam('finish', 0);
    $open = $request->getParam('open', 0);

    if (!strlen($start) || !strlen($finish)) {
        return $response->withJson([ "error" => 1, "message" => "Bad query"]);
    }

    $res = $checklist->fetchTree($start, $finish, $parent, $open);
    return $response->withJson($res);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->post('/legacy/ext/checklists/change[/[{id}]]', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);

    if (isset($args['id'])) {
        $filter['date'] = strtotime($args['id']);
    }

    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    return $response->withJson($checklist->checklist_change($filter));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext/checklists/groups[/[{gid}]]', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    if (isset($args['gid'])) {
        $filter['parent_id'] = intval($args['gid']);
    }

    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    return $response->withJson($checklist->getExt_checklist_listgroups($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->post('/legacy/ext/checklist/addgroup', function (Request $request, Response $response, array $args) use ($app) {
    $params = $request->getParams();

    // Here we should check parameters

    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    return $response->withJson($checklist->addgroup($params));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->post('/legacy/ext/checklist/deletegroup', function (Request $request, Response $response, array $args) use ($app) {
    $params = $request->getParams();

    // Here we should check parameters

    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    return $response->withJson($checklist->deletegroup($params));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->post('/legacy/ext/checklist/questions/save[/[{id}]]', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);

    if (isset($args['id'])) {
        $filter['save'] = intval($args['id']);
    }

    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    return $response->withJson($checklist->checklist_save($filter));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->post('/legacy/ext/checklists/fill[/[{id}]]', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    if (isset($args['id'])) {
        $filter['date'] = strtotime($args['id']);
    }

    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    return $response->withJson($checklist->getExt_checklist_fill($filter, $pos, $count, 0));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->post('/legacy/ext/checklist/deleteanswer', function (Request $request, Response $response, array $args) use ($app) {

    $checklist = new \Erpico\Ext_checklist($app->getContainer());
    return $response->withJson($checklist->checklist_delete_answer());
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','erpico.admin']));

$app->get('/legacy/ext/scripts/list', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);
    $scripts = new \Erpico\Ext_scripts($app->getContainer());
    
    return $response->withJson($scripts->getExt_scripts_list($filter, $pos, $count, 0));
})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/ext/scripts/liststages[/{params:.*}]', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $params = explode('/', $args['params']);

    if (isset($params)) {
        $filter['list_stages'] = intval($params[0]);
    }

    $filter['parent_id'] = 0;

    $scripts = new \Erpico\Ext_scripts($app->getContainer());
    return $response->withJson([
        "data" => $scripts->getExt_scripts_list_stages($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $scripts->getExt_scripts_list_stages($filter, $pos, $count, 1)
    ]);
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.reports','phc.admin','erpico.admin']));

$app->get('/legacy/ext_scripts_stages', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);
    $pos = intval($request->getParam('start', 0));
    $count = $request->getParam('count', 20);

    $scripts = new \Erpico\Ext_scripts($app->getContainer());
    return $response->withJson([
        "data" => $scripts->getScriptAllStages($filter, $pos, $count, 0),
        "pos" => $pos,
        "total_count" => $scripts->getScriptAllStages($filter, $pos, $count, 1)
    ]);

})->add('\App\Middleware\OnlyAuthUser');

$app->post('/legacy/ext/scripts/save/stageelements', function (Request $request, Response $response, array $args) use ($app) {
    $params = $request->getParams();
    // Here we should check parameters
    $scripts = new \Erpico\Ext_scripts($app->getContainer());
    
    return $response->withJson($scripts->scripts_save_stage_elements($params));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->get('/legacy/ext/scripts/list/stageelements[/[{id}]]', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam('filter', []);

    if (isset($args['id'])) {
        $filter['list_stage_elements'] = intval($args['id']);
    }
    $scripts = new \Erpico\Ext_scripts($app->getContainer());
    
    return $response->withJson($scripts->scripts_list_stage_elements($filter));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->post('/legacy/ext/scripts/save/stage', function (Request $request, Response $response, array $args) use ($app) {
    $params = $request->getParams();

    // Here we should check parameters
    $scripts = new \Erpico\Ext_scripts($app->getContainer());
    
    return $response->withJson($scripts->scripts_save_stage($params));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->post('/legacy/ext/scripts/save', function (Request $request, Response $response, array $args) use ($app) {
    $params = $request->getParams();

    // Here we should check parameters
    $scripts = new \Erpico\Ext_scripts($app->getContainer());
    
    return $response->withJson($scripts->scripts_save($params));
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.admin','erpico.admin']));

$app->get('/legacy/nps/options/{option_key}', function (Request $request, Response $response, array $args) use ($app) {
    $option_key = $args["option_key"];
    $filter = $request->getParam("filter", "");

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->getFilter($option_key, $filter));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/legacy/nps/count', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam("filter", "");
    $byCities = $request->getParam("byCities", 0);

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->getCount($filter, $byCities));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/legacy/nps/promoters', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam("filter", "");

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->getPromoters($filter));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/legacy/nps/detractors', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam("filter", "");

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->getDetractors($filter));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/legacy/nps/report', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam("filter", "");

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->getReport($filter));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/legacy/nps/clients', function (Request $request, Response $response, array $args) use ($app) {
    $filter = $request->getParam("filter", "");

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->getClients($filter));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/legacy/nps/clients/import', function (Request $request, Response $response, array $args) use ($app) {
    $data = $request->getParam("data", null);

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->importClients($data));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/legacy/nps/clients/remove', function (Request $request, Response $response, array $args) use ($app) {
    $id = $request->getParam("id", null);

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->removeClient(intval($id)));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/legacy/nps/clients/setstate', function (Request $request, Response $response, array $args) use ($app) {
    $npsId = $request->getParam("npsId", null);
    $state = $request->getParam("state", null);

    $nps = new \Erpico\Nps($app->getContainer());
    return $response->withJson($nps->setClientState(intval($npsId),intval($state)));
  
})->add(new SecureRouteMiddleware($app->getContainer()->get('roleProvider')))->add(new SetRoles(['phc.nps','erpico.admin']));

$app->get('/controllers/groups.php', function (Request $request, Response $response, array $args) use($app) {
  $container = $app->getContainer();
  $db = $container['db'];

  $sql = "SELECT id, name FROM contact_groups";

  $res = $db->query($sql);
  $result = array();
  while ($row = $res->fetch(\PDO::FETCH_NUM)) {
	$group = [ "id" => intval($row[0]),
						 "name" => $row[1],
						 "queue" => $row[2],
						 "queues" => [],
						 "phones" => [],
						 "users" => [] ];
	$sql = "SELECT queue_id FROM contact_groups_queues WHERE contact_groups_id = {$row[0]}";
	$res2 = $db->query($sql);
	while ($row2 = $res2->fetch(\PDO::FETCH_ASSOC) ) {
		$group['queues'][] = intval($row2['queue_id']);
	}
	$sql = "SELECT phone, queue_id, acl_user_id FROM contact_groups_items WHERE contact_groups_id = {$row[0]}";
	$res2 = $db->query($sql);
	while ($row2 = $res2->fetch(\PDO::FETCH_ASSOC) ) {
		if (!is_null($row2['phone']))
			$group['phones'][] = $row2['phone'];
		if (!is_null($row2['acl_user_id']))
		$group['users'][] = intval($row2['acl_user_id']);
		if (!is_null($row2['queue_id'])) {
			// Fetch all users in queue
			$sql = "SELECT DISTINCT A.acl_user_id FROM queue_agent AS A LEFT JOIN cfg_user_setting AS B ON (B.acl_user_id = A.acl_user_id AND B.handle = 'cti.ext') WHERE queue_id = {$row2['queue_id']}";
			$res3 = $db->query($sql);
			while ($row3 = $res3->fetch(\PDO::FETCH_NUM)) {
				$group['users'][] = intval($row3[0]);
			}
		}
	}
	$result[] = $group;
  }
  return $response->withHeader('Content-Type', 'application/json')->withStatus(200)->withJson($result, null, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
});

$app->get('/legacy/scripts/getstageid', function (Request $request, Response $response, array $args) use ($app) {
  $id = $request->getParam("id", 0);
  // getFirstStageId
  $script = new \Erpico\Script($app->getContainer());
  return $response->withJson(["result"=>$script->getFirstStageId(intval($id))]);

})->add('\App\Middleware\OnlyAuthUser');

$app->get('/legacy/scripts', function (Request $request, Response $response, array $args) use ($app) {
  $id = $request->getParam("id", 0);
  $sid = $request->getParam("sid", 0);
  $nps_id = $request->getParam("nps_id", 0);
  $script = new \Erpico\Script($app->getContainer());
  return $response->withJson($script->getScriptInfo($id, $sid, $nps_id));

})->add('\App\Middleware\OnlyAuthUser');

$app->post('/legacy/scripts/set_answer', function (Request $request, Response $response, array $args) use ($app) {
  $data = $request->getParam("data", "");
  $script = new \Erpico\Script($app->getContainer());
  return $response->withJson($script->setAnswer($data));

})->add('\App\Middleware\OnlyAuthUser');
