#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2015 Deciso B.V.
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
 */

// use legacy code to generate certs and ca's
// eventually we need to replace this.
require_once("config.inc");
require_once("legacy_bindings.inc");

use OPNsense\Core\Config;
use OPNsense\Trust\Trust;

// traverse captive portal zones
$configObj = Config::getInstance()->object();
$mdlTrust = new Trust();
if (isset($configObj->OPNsense->captiveportal->zones)) {
    foreach ($configObj->OPNsense->captiveportal->zones->children() as $zone) {
        $cert_uuid = (string)$zone->certificate;
        $zone_id = (string)$zone->zoneid;
        // if the zone has a certificate attached, search for its contents
        if ($cert_uuid != "" && ($cert = $mdlTrust->certs->cert->{$cert_uuid})) {

            // generate cert pem file
            $pem_content = trim(str_replace("\n\n", "\n", str_replace(
                "\r",
                "",
                base64_decode((string)$cert->crt)
            )));

            $pem_content .= "\n";
            $pem_content .= trim(str_replace(
                "\n\n",
                "\n",
                str_replace("\r", "", base64_decode((string)$cert->prv))
            ));
            $pem_content .= "\n";
            $output_pem_filename = "/var/etc/cert-cp-zone" . $zone_id . ".pem";
            file_put_contents($output_pem_filename, $pem_content);
            chmod($output_pem_filename, 0600);
            echo "certificate generated " . $output_pem_filename . "\n";
            // generate ca pem file
            if (!empty((string) $cert->cauuid)) {
                $output_pem_filename = "/var/etc/ca-cp-zone" . $zone_id . ".pem";
                $ca = $mdlTrust->ca_chain($cert);
                file_put_contents($output_pem_filename, $ca);
                chmod($output_pem_filename, 0600);
                echo "certificate generated " . $output_pem_filename . "\n";
            }
        }
    }
}
