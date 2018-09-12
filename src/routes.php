<?php

use Slim\Http\Request;
use Slim\Http\Response;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Swagger docs
$app->get('/', function (Request $request, Response $response, array $args) {
    $swaggerDocs = json_decode(file_get_contents(__DIR__.'/swagger.json'), TRUE);
    return $response->withJson($swaggerDocs);
});

// GET antminer details
$app->get('/antminer', function (Request $request, Response $response, array $args) {

    $r = $request->getQueryParams();

    $this->logger->info(json_encode(array('timestamp' => date('c'), 'event' => 'FETCH_ANTMINER_DETAILS', 'ip' => $r['ip'], 'pw' => $r['pw'], 'type' => $r['type'])));

    $antminer = new Antminer($r['ip'],$r['pw'], strtoupper($r['type']));

    $details = array(
      'timestamp'   => date('c'),
      'ip'          => $antminer->getIp(),
      'type'        => $antminer->getType(),
      'state'       => $antminer->getState(),
      'network'     => $antminer->getNetwork(),
      'config'      => $antminer->getConfig(),
      'summary'     => $antminer->getSummary(),
      'pools'       => $antminer->getPools(),
      'stats'       => $antminer->getStats()
    );

    return $response->withJson($details);
});

// GET antminer state only
$app->get('/antminer/state', function (Request $request, Response $response, array $args) {

    $r = $request->getQueryParams();

    $this->logger->info(json_encode(array('timestamp' => date('c'), 'event' => 'FETCH_ANTMINER_STATE', 'ip' => $r['ip'], 'pw' => $r['pw'], 'type' => $r['type'])));

    $antminer = new Antminer($r['ip'],$r['pw'], strtoupper($r['type']));

    $state = array(
      'timestamp'   => date('c'),
      'ip'          => $antminer->getIp(),
      'type'        => $antminer->getType(),
      'state'       => $antminer->getState()
    );

    return $response->withJson($state);

});

// UPDATE antminer state
// {"state": "ONLINE"} => will attempt to change state from IDLE to ONLINE
// {"state": "IDLE"} => will attempt to change state from ONLINE to IDLE
// NOTE - If state currently is OFFLINE, the miner must be restarted by power cycling the miner, and can't be done via the API
$app->put('/antminer/state', function (Request $request, Response $response, array $args) {

    $r = $request->getQueryParams();
    $b = $request->getParsedBody();

    $antminer = new Antminer($r['ip'],$r['pw'], strtoupper($r['type']));

    // If current state matches do nothing
    if (strtoupper($b['state']) == $antminer->getState()) {
      $responseMsg = [];
      $responseMsg['status'] = "no_change_required";
      $responseMsg['message'] = "the current state of the miner is [".$antminer->getState()."]";
      return $response->withJson($responseMsg);
    }

    // If current state is offline, respond that change is not possible
    if ($antminer->getState() == "OFFLINE") {
      $responseMsg = [];
      $responseMsg['status'] = "no_change_possible";
      $responseMsg['message'] = "the miner is currently offline or inaccessible and most likely requires a manual restart";
      return $response->withJson($responseMsg);
    }

    // Change state as requested
    if (strtoupper($b['state']) == "OFFLINE") {

      $antminer->poweroff();

      $responseMsg = [];
      $responseMsg['status'] = "ok";
      $responseMsg['message'] = "the miner has been powered off";

      return $response->withJson($responseMsg);

    } elseif (strtoupper($b['state']) == "IDLE") {

      $antminer->shutdown();

      $responseMsg = [];
      $responseMsg['status'] = "ok";
      $responseMsg['message'] = "the miner has been set to be [IDLE] and is now being rebooted so that new state can take affect";

      return $response->withJson($responseMsg);

    } elseif (strtoupper($b['state']) == "ONLINE") {

      $antminer->startup();

      $responseMsg = [];
      $responseMsg['status'] = "ok";
      $responseMsg['message'] = "the miner has been set to be [ONLINE] and is now being rebooted so that new state can take affect";

      return $response->withJson($responseMsg);

    } else {
      $responseMsg = [];
      $responseMsg['status'] = "no_change";
      $responseMsg['message'] = "state change not valid [".$b['state']."]";
    }


});
