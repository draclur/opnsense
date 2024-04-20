<?php

/*
 * Copyright (C) 2024 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Core\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\ACL;
use OPNsense\Core\Config;

class DashboardController extends ApiControllerBase
{
    private function getTranslations()
    {
        return [
            'cpu' => [
                'title' => gettext('CPU'),
                'total' => gettext('Total'),
                'interrupt' => gettext('Interrupt'),
                'user' => gettext('User'),
                'system' => gettext('System'),
            ],
            'interfaces' => [
                'title' => gettext('Interfaces'),
            ],
            'systeminformation' => [
                'title' => gettext('System Information'),
                'name' => gettext('Name'),
                'versions' => gettext('Versions'),
                'updates' => gettext('Updates'),
                'datetime' => gettext('Current date/time'),
                'uptime' => gettext('Uptime'),
                'config' => gettext('Last configuration change')
            ],
            'interfacestatistics' => [
                'title' => gettext('Interface Statistics'),
                'bytesin' => gettext('Bytes In'),
                'bytesout' => gettext('Bytes Out'),
                'packetsin' => gettext('Packets In'),
                'packetsout' => gettext('Packets Out'),
                'errorsin' => gettext('Errors In'),
                'errorsout' => gettext('Errors Out'),
                'collisions' => gettext('Collisions'),
            ],
            'traffic' => [
                'title' => gettext('Traffic Graph'),
                'trafficin' => gettext('Traffic In'),
                'trafficout' => gettext('Traffic Out'),
            ],
            'memory' => [
                'title' => gettext('Memory usage'),
                'used' => gettext('Used'),
                'free' => gettext('Free'),
                'arc' => gettext('ARC'),
            ]
        ];
    }

    private function canAccessEndpoints($fname)
    {
        if (!file_exists($fname)) {
            return false;
        }

        $handle = fopen($fname, "r");

        if ($handle) {
            $lines = [];
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, "// endpoint:") === 0) {
                    $endpoint = explode(':', trim($line))[1] ?? null;
                    if (!empty($endpoint)) {
                        $endpoint = strstr($endpoint, ' ', true) ?: $endpoint;
                        $lines[] = $endpoint;
                    }
                    continue;
                }
                break;
            }

            fclose($handle);

            $acl = new ACL();
            foreach ($lines as $line) {
                if (!$acl->isPageAccessible($this->getUserName(), $line)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getDashboardAction()
    {
        $this->sessionClose();
        $result = [];
        $dashboard = null;

        $config = Config::getInstance()->object();
        foreach ($config->system->user as $node) {
            if ($this->getUserName() === (string)$node->name) {
                $dashboard = (string)$node->dashboard;
            }
        }

        $widgetModules = array_filter(
            glob('/usr/local/opnsense/www/js/widgets/*.js'),
            function ($element) {
                $base = basename($element);
                if (str_contains($base, '.js') && !str_contains($base, 'Base')) {
                    return $this->canAccessEndpoints($element);
                }

                return false;
            }
        );

        $widgetModules = array_map(function ($element) {
            return basename($element);
        }, $widgetModules);

        $result['modules'] = [];
        foreach ($widgetModules as $module) {
            $id = strtolower(basename($module, '.js'));
            $result['modules'][] = [
                'id' => $id,
                'module' => basename($module),
                'translations' => $this->getTranslations()[$id] ?? []
            ];
        }

        $result['dashboard'] = !empty($dashboard) ? base64_decode($dashboard) : null;

        return json_encode($result);
    }

    public function saveWidgetsAction()
    {

        $result = ['result' => 'failed'];

        if ($this->request->isPost() && !empty($this->request->getRawBody())) {
            $dashboard = $this->request->getRawBody();
            if (strlen($dashboard) > (1024 * 1024)) {
                // prevent saving large blobs of data
                return json_encode($result);
            }

            $encoded = base64_encode($dashboard);
            $config = Config::getInstance()->object();
            $name = $this->getUserName();
            foreach ($config->system->user as $node) {
                if ($name === (string)$node->name) {
                    $node->dashboard = $encoded;
                    Config::getInstance()->save();
                    $result = ['result' => 'saved'];
                    break;
                }
            }
        }

        return json_encode($result);
    }

    public function restoreDefaultsAction()
    {
        $result = ['result' => 'failed'];

        $config = Config::getInstance()->object();
        $name = $this->getUserName();

        foreach ($config->system->user as $node) {
            if ($name === (string)$node->name) {
                $node->dashboard = null;
                Config::getInstance()->save();
                $result = ['result' => 'saved'];
                break;
            }
        }

        return json_encode($result);
    }
}
