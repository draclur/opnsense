<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\Base;


class Validation
{
    private $validators = [];
    private $messages = [];
    public function __construct($validators = [])
    {
        $this->validators = $validators;
    }

    /**
     *  Appends a message to the messages list
     *  @$message MessageInterface $message
     */
    public function appendMessage($message)
    {
        $this->messages[] = $message;
        $this->data = [];
    }

    /**
     * Adds a validator to a field
     *
     * @param string|array       $field
     * @param BaseValidator|ValidatorInterface $validator
     *
     * @return Validation
     */
    public function add($key, $validator)
    {
        if (empty($this->validators[$key])){
            $this->validators[$key] = [];
        }
        $this->validators[$key][] = $validator;
        return $this;
    }

    /**
     * Validate a set of data according to a set of rules
     *
     * @param array data
     */
    public function validate($data)
    {
        $this->data = $data;
        // XXX: version check
        $validation = new \Phalcon\Validation();
        $validation->bind($this, $data);

        foreach ($data as $key => $value) {
            if (!empty($this->validators[$key])) {
                foreach ($this->validators[$key] as $validator) {
                    if (is_a($validator, "OPNsense\Base\BaseValidator")) {
                        $validator->validate($this, $key);
                    } else {
                        $validator->validate($validation, $key);
                    }
                }
            }
        }
        $phalconMsgs = $validation->getMessages();
        if  (!empty($phalconMsgs)) {
            foreach ($phalconMsgs as $phalconMsg) {
                $this->messages[] = $phalconMsg;
            }
        }
        return $this->messages;
    }

    public function getValue($attribute)
    {
        return isset($this->data[$attribute]) ? $this->data[$attribute] : null;
    }
}
