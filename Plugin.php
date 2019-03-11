<?php
/*
 * This file is a part of Mibew Bulk Logs Operations Plugin.
 *
 * Copyright 2018 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Mibew\Mibew\Plugin\BulkLogsOperations;

use Mibew\EventDispatcher\EventDispatcher;
use Mibew\EventDispatcher\Events;

class Plugin extends \Mibew\Plugin\AbstractPlugin implements \Mibew\Plugin\PluginInterface
{
    protected $initialized = true;

    /**
     * The main entry point of a plugin.
     */
    public function run()
    {
        $dispatcher = EventDispatcher::getInstance();
        $dispatcher->attachListener(Events::PAGE_ADD_JS, $this, 'addJs');
    }

    public function addJs(&$args)
    {
        if (array_key_exists(SESSION_PREFIX . 'operator', $_SESSION)
            && is_capable(CAN_ADMINISTRATE, operator_by_id($_SESSION[SESSION_PREFIX . 'operator']['operatorid']))) {
            if (!strcmp('/operator/history', $args['request']->getPathInfo())) {
                $args['js'][] = str_replace(DIRECTORY_SEPARATOR, '/', $this->getFilesPath()) . '/js/alter_form.js';
            }
        }
    }

    /**
     * Specify version of the plugin.
     *
     * @return string Plugin's version.
     */
    public static function getVersion()
    {
        return '0.1.0';
    }
}
