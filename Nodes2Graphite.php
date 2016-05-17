#!/usr/bin/php
<?php

/**
 * Class Nodes2Grafana
 */
class Nodes2Graphite
{
    /**
     * @var string Grafana host IP
     */
    private $graphiteHost;

    /**
     * @var integer Grafana host IP
     */
    private $graphitePort;

    /**
     * @var resource Graphite socket
     */
    private $graphiteSocket;

    /**
     * Nodes2Graphite constructor.
     * @param string $graphiteHost
     * @param integer $graphitePort
     */
    public function __construct($graphiteHost, $graphitePort)
    {
        $this->graphiteHost = $graphiteHost;
        $this->graphitePort = $graphitePort;
    }

    /**
     * Prepeare data for graphite
     * @param string $nodefile
     * @param string $namespace
     */
    public function prepearData($nodefile, $namespace)
    {
        $nodeJson = file_get_contents($nodefile);
        $object = json_decode($nodeJson);
        $nodes = $object->nodes;
        $online = 0;
        $clients = 0;
        foreach ($nodes as $node) {
            $node_id = $node->nodeinfo->node_id;
            $hostname = $this->sanitize($node->nodeinfo->hostname);
            if ($node->flags->online === true) {
                $nodeNamespace = $namespace . '.nodes.' . $node_id . '.' . $hostname . '.';
                $online++;
                $clients += $node->statistics->clients;
                $this->sendToGraphite($nodeNamespace . 'flags.online', 1);
                $this->sendToGraphite($nodeNamespace . 'hostname.' . $hostname, true);
                $this->sendToGraphite($nodeNamespace . 'model.' . $this->sanitize($node->nodeinfo->hardware->model), true);
                $this->sendToGraphite($nodeNamespace . 'statistics.traffic.tx', $node->statistics->traffic->tx->bytes);
                $this->sendToGraphite($nodeNamespace . 'statistics.traffic.rx', $node->statistics->traffic->rx->bytes);
                $this->sendToGraphite($nodeNamespace . 'statistics.traffic.mgmt_tx', $node->statistics->traffic->mgmt_tx->bytes);
                $this->sendToGraphite($nodeNamespace . 'statistics.traffic.mgmt_rx', $node->statistics->traffic->mgmt_rx->bytes);
                $this->sendToGraphite($nodeNamespace . 'statistics.traffic.forward', $node->statistics->traffic->forward->bytes);
                $this->sendToGraphite($nodeNamespace . 'statistics.loadavg', $node->statistics->loadavg);
                $this->sendToGraphite($nodeNamespace . 'statistics.memory_usage', $node->statistics->memory_usage);
                $this->sendToGraphite($nodeNamespace . 'statistics.uptime', $node->statistics->uptime);
                $this->sendToGraphite($nodeNamespace . 'statistics.clients', $node->statistics->clients);
            }
        }
        $this->sendToGraphite($namespace . '.mesh.online', $online);
        $this->sendToGraphite($namespace . '.mesh.clients', $clients);
    }

    /**
     * @param string $string UTF-8 input
     * @return string Allowed chars
     */
    private function sanitize($string)
    {
        return preg_replace('/[^a-z0-9\-\_]/i', '_', $string);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    private function sendToGraphite($key, $value)
    {
        $this->openSocket();
        echo "Sending to graphite.... $key => $value\n";
        try {
            fwrite($this->graphiteSocket, $key . ' ' . $value . ' ' . time() . PHP_EOL);
        } catch (Exception $e) {
            echo "\nNetwork error: " . $e->getMessage();
        }
    }

    private function openSocket()
    {
        if ($this->graphiteSocket === null) {
            $this->graphiteSocket = fsockopen('tcp://' . $this->graphiteHost, $this->graphitePort, $errno, $errstr);
            if (!$this->graphiteSocket) {
                echo "$errstr ($errno)\n";
            }
        }
    }

    public function closeSocket()
    {
        if ($this->graphiteSocket !== null) {
            fclose($this->graphiteSocket);
        }
    }
}

$nodes2Graphite = new Nodes2Graphite('213.166.225.42', 2003);
$nodes2Graphite->prepearData('/var/www/data/nodes.json', 'ffrgb');
$nodes2Graphite->closeSocket();
