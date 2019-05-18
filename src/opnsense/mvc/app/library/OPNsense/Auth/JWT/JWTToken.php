<?php
/*
 * Copyright (C) 2019 Fabian Franz
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

namespace OPNsense\Auth\JWT;


abstract class JWTToken
{
    protected $signature_value;
    protected $type_hash;
    protected $claims;
    protected $verify_string;
    public function b64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    public function b64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    public function parseToken($token) {
        if (substr_count($token, '.') != 2){
            throw new \Exception("Invalid JWT given");
        }


        $split = explode('.', $token);
        $this->type_hash = json_decode($this->b64UrlDecode($split[0]), true);
        $this->claims = json_decode($this->b64UrlDecode($split[1]), true);
        if (empty($split[2])) {
            $this->signature_value = null;
        } else {
            $this->signature_value = $this->b64UrlDecode($split[2]);
        }

        $this->verify_string = $split[0] . '.' . $split[1];

        return $this->verify();
    }

    public function verify_claims() : bool {
        return true;
    }


    public abstract function verify(): bool;
    public abstract function sign($claims): string;
}