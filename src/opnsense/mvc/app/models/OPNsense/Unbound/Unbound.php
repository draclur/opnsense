<?php

/*
 * Copyright (C) 2021 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Unbound;

use Phalcon\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;

class Unbound extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $config = Config::getInstance()->object();

        // Check if both Unbound is enabled and the assigned port does not clash with the dnsmasq configuration
        $enabled = $this->general->enabled;
        $port = $this->general->port;
        foreach ([$enabled, $port] as $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                $dnsmasq_port = !empty((string)$config->dnsmasq->port) ? (string)$config->dnsmasq->port : '53';
                if (!empty((string)$enabled) && !empty((string)$config->dnsmasq->enable) && (string)$port == $dnsmasq_port) {
                    $messages->appendMessage(
                        new Message(gettext('Dnsmasq is still active on the same port. Disable it before enabling Unbound.'),
                        'general.'.$node->getInternalXMLTagName())
                    );
                }
            }
        }

        return $messages;
    }
}
