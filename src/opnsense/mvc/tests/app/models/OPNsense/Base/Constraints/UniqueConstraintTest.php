<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Base\Constraints;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\ContainerField;
use OPNsense\Base\FieldTypes\TextField;

class UniqueTestContainer extends ArrayField
{
    private $uniqueConstraints = [];
    private $valuesRequired = true;

    /**
     * @param $nodes a single node or an array of nodes
     * a single node represent a single unique value across
     * multiple array elements, while multiple nodes represent
     * the usage of 'addFields'
     */
    public function addNode($nodes) 
    {
        // UniqueConstraint requires a depth of 2, so add a container node
        $container = new ContainerField();
        $constraint = new UniqueConstraint();
        $this->addChildNode('UniqueTest', $container);
        $addFields = [];
        $fields_added = false;
        foreach ($nodes as $name => $value) {
            $node = new TextField(null, $name);
            $node->setRequired($this->valuesRequired ? "Y" : "N");
            if ($name === array_key_first($nodes)) {
                $constraint->setOption('node', $node);
                $constraint->setOption('name', $name);
                $constraint->setOption('ValidationMessage', 'Validation Failed');
            } else {
                $addFields[] = $name;
                $fields_added = true;
            }
            $node->setValue($value);
            $container->addChildNode($name, $node);
        }

        if ($fields_added) {
            $constraint->setOption('addFields', $addFields);
        }

        $this->uniqueConstraints[] = $constraint;
    }

    public function setRequired($required)
    {
        $this->valuesRequired = $required;
    }

    public function validate()
    {
        $validator = new \OPNsense\Base\Validation();
        $constraint = $this->uniqueConstraints[0];
        $ret = $constraint->validate($validator, '');
        $msgs = $validator->getMessages();

        return [$ret, $msgs];
    }
}

class UniqueConstraintTest extends \PHPUnit\Framework\Testcase
{
    public function testEqualValues()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test' => 'value1']);
        $container->addNode(['unique_test' => 'value1']);

        list($ret, $msgs) = $container->validate();

        $this->assertEquals(1, $msgs->count());
        $this->assertEquals(false, $ret);
    }

    public function testNonEqualValues()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test' => 'value1']);
        $container->addNode(['unique_test' => 'value2']);

        list($ret, $msgs) = $container->validate();

        $this->assertEquals(0, $msgs->count());
        $this->assertEquals(true, $ret);
    }

    public function testMultipleEqualValues()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test' => 'value1', 'unique_test2' => 'value2']);
        $container->addNode(['unique_test' => 'value1', 'unique_test2' => 'value2']);

        list($ret, $msgs) = $container->validate();

        $this->assertEquals(1, $msgs->count());
        $this->assertEquals(false, $ret);
    }

    public function testMultipleNonEqualValues()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test' => 'value1', 'unique_test2' => 'value1']);
        $container->addNode(['unique_test' => 'value1', 'unique_test2' => 'value2']);

        list($ret, $msgs) = $container->validate();

        $this->assertEquals(0, $msgs->count());
        $this->assertEquals(true, $ret);
    }

    public function testEmptyValuesNonRequired()
    {
        $container = new UniqueTestContainer();
        $container->setRequired(false);
        $container->addNode(['unique_test' => '']);
        $container->addNode(['unique_test' => '']);

        list($ret, $msgs) = $container->validate();

        $this->assertEquals(0, $msgs->count());
        $this->assertEquals(true, $ret);
    }

    public function testMultipleEmptyValuesNonRequired()
    {
        $container = new UniqueTestContainer();
        $container->setRequired(false);
        $container->addNode(['unique_test' => '', 'unique_test2' => '']);
        $container->addNode(['unique_test' => '', 'unique_test2' => '']);

        list($ret, $msgs) = $container->validate();

        $this->assertEquals(0, $msgs->count());
        $this->assertEquals(true, $ret);
    }

    public function testMultipleSetAndEmptyValuesNonRequired()
    {
        $container = new UniqueTestContainer();
        $container->setRequired(false);
        $container->addNode(['unique_test1' => 'value1', 'unique_test2' => '']);
        $container->addNode(['unique_test1' => 'value1', 'unique_test2' => '']);

        list($ret, $msgs) = $container->validate();

        $this->assertEquals(1, $msgs->count());
        $this->assertEquals(false, $ret);
    }

    public function testFirstValueEmptyPassAll()
    {
        $container = new UniqueTestContainer();
        $container->setRequired(false);
        $container->addNode(['unique_test1' => '', 'unique_test2' => 'value1']);
        $container->addNode(['unique_test1' => '', 'unique_test2' => 'value1']);

        list($ret, $msgs) = $container->validate();

        $this->assertEquals(0, $msgs->count());
        $this->assertEquals(true, $ret);
    }
}
