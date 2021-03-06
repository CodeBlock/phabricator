<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

abstract class PhabricatorFactEngine {

  final public static function loadAllEngines() {
    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();

    $objects = array();
    foreach ($classes as $class) {
      $objects[] = newv($class['name'], array());
    }

    return $objects;
  }

  public function shouldComputeRawFactsForObject(PhabricatorLiskDAO $object) {
    return false;
  }

  public function computeRawFactsForObject(PhabricatorLiskDAO $object) {
    return array();
  }

  public function shouldComputeAggregateFacts() {
    return false;
  }

  public function computeAggregateFacts() {
    return array();
  }

}
