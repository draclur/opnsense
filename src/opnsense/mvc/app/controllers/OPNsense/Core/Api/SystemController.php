<?php

/**
 *    Copyright (C) 2019-2022 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Core\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * Class SystemController
 * @package OPNsense\Core
 */
class SystemController extends ApiControllerBase
{
    public function haltAction()
    {
        $backend = new Backend();
        $backend->configdRun('system halt', true);
        return [
            'status' => 'ok'
        ];
    }

    public function rebootAction()
    {
        $backend = new Backend();
        $backend->configdRun('system reboot', true);
        return [
            'status' => 'ok'
        ];
    }

    public function statusAction()
    {
        $backend = new Backend();
        $status = trim($backend->configdRun('system status'));

        /* TODO: iterate ACLs and check access rights */
        if ($status) {
            return $status;
        }

        return array("message" => "Unable to run configd action");
    }

    public function dismissStatusAction()
    {
        if ($this->request->isPost() && $this->request->hasPost("subject")) {
            $subsystem = $this->request->getPost("subject");
            if ($subsystem) {
                $backend = new Backend();
                $status = $backend->configdRun(sprintf('system dismiss status %s', $subsystem));
                if ($status == "OK") {
                    return [
                        'status' => 'ok'
                    ];
                }
            }
        }
    }
}
