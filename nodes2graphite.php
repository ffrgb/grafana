#!/usr/bin/php
<?php

$graphite_host='213.166.225.42';
$nodesfile='/var/www/data/nodes.json';
$namespace='ffrgb.nodes';

function makeMac($a_nodeid) {
  for ($i=0;$i<sizeOf($a_nodeid);$i++) {
    $outstr.=$a_nodeid[$i];
    if ($i % 2 == 0) {
      $outstr.=':';
    }
  }
  return($outstr);
}

function sendToGraphite($key, $value) {
	echo "Sending to graphite.... $key => $value\n";
  try {
  	$socketHandler = fsockopen('tcp://213.166.225.42', 2003, $errno, $errstr);
    if (!empty($errno)) echo $errno;
    if (!empty($errstr)) echo $errstr;
  	fwrite($socketHandler, $key . ' ' . $value . ' ' . time() . PHP_EOL);
  } catch (Exception $e) {
    echo "\nNetwork error: ".$e->getMessage();
  }

}

$nodeJson=file_get_contents($nodesfile);
$object=json_decode($nodeJson);
$nodes=$object->nodes;
//asort($nodes);
$online=0;
$clients=0;
for ($i=0;$i<sizeOf($nodes);$i++) {
  $node=$nodes[$i];
  $node_id=$node->nodeinfo->node_id;
  $hostname=$node->nodeinfo->hostname;
  $hostname=str_replace(array('.', ' '), '_', $node->nodeinfo->hostname);
  switch ($node->flags->online) {
    case false:
      sendToGraphite("$namespace.$node_id.flags.online",0);
      break;
    case true:
      $online++;
      $clients+=$node->statistics->clients;
      sendToGraphite("$namespace.$node_id.$hostname.flags.online",1);
      sendToGraphite("$namespace.$node_id.$hostname.hostname.".$hostname,true);
      sendToGraphite("$namespace.$node_id.$hostname.model.".$node->nodeinfo->hardware->model,true);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.traffic.tx",$node->statistics->traffic->tx->bytes);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.traffic.rx",$node->statistics->traffic->rx->bytes);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.traffic.mgmt_tx",$node->statistics->traffic->mgmt_tx->bytes);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.traffic.mgmt_rx",$node->statistics->traffic->mgmt_rx->bytes);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.traffic.forward",$node->statistics->traffic->forward->bytes);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.loadavg",$node->statistics->loadavg);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.memory_usage",$node->statistics->memory_usage);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.uptime",$node->statistics->uptime);
      sendToGraphite("$namespace.$node_id.$hostname.statistics.clients",$node->statistics->clients);
      break;
  }
  unset($node);
}

sendToGraphite("ffrgb.mesh.online",$online);
sendToGraphite("ffrgb.mesh.clients",$clients);

?>
