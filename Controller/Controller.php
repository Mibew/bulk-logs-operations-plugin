<?php
/*
 * This file is a part of Mibew Bulk Logs Operations Plugin.
 *
 * Copyright 2018 Fedor A. Fetisov <faf@mibew.org>.
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

namespace Mibew\Mibew\Plugin\BulkLogsOperations\Controller;

use Mibew\Database;
use Mibew\Settings;
use Mibew\Thread;
use Mibew\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Mibew\Handlebars\Helpers;
use Handlebars\Handlebars as HandlebarsEngine;

class Controller extends AbstractController
{

    /**
    * Returns exported chat threads selected with the search query
    *
    * @param  Request  $request
    * @return Response Exported chat threads as downloadable plain text file
    */
    public function exportAction(Request $request)
    {
        // Get selected chat threads
        $threads = $this->getThreads($request);
        // Generate plain text with chat threads
        $content = '';
        if (count($threads)) {
            foreach ($threads as $raw) {

                // Get thread info
                $thread = Thread::load($raw['threadid']);
                $group = group_by_id($thread->groupId);

                // Add thread info to the text
                $content = ($content === '') ? '' : $content . "\n===================\n";
                $content .= format_date($thread->created, 'full') . "\n";
                $content .= getlocal('Name') . ': ' . $thread->userName . "\n";
                $content .= getlocal('Visitor\'s address') . ': ' . get_user_addr($thread->remote) . "\n";
                $content .= getlocal('Browser') . ': ' . get_user_agent_version($thread->userAgent) . "\n";
                if ($group && get_group_name($group)) {
                    $content .= getlocal('Group') . ': ' . get_group_name($group) . "\n";
                }
                $content .= getlocal('Operator') . ': ' . $thread->agentName . "\n--------------\n";

                // Get messages for the thread
                $last_id = -1;
                $messages = array_map(
                    'sanitize_message',
                    $thread->getMessages(false, $last_id)
                );
                // Add messages to the text
                foreach ($messages as $message) {
                    // Skip service messages for the operator
                    if ($message['kind'] == Thread::KIND_FOR_AGENT) {
                        continue;
                    }
                    $content .= '[' . format_date($message['created'], 'time') .']' . ' ';
                    $tail = '';
                    switch ($message['kind']) {
                        case Thread::KIND_USER:
                            $content .= $thread->userName . ' > ';
                            break;
                        case Thread::KIND_AGENT:
                            $content .= $thread->agentName . ' > ';
                            break;
                        default:
                            $content .= '// ';
                            $tail = ' //';
                    }
                    $content .= $message['message'] . $tail . "\n";
                }
            }
        }
        // Compose response
        $response = new Response($content, 200);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="log-' . time() . '.txt"');
        return $response;
    }

    /**
    * Delete chat threads selected with the search query and redirects to the threads history page
    *
    * @param  Request  $request
    * @return Response Redirection to the threads history page with the given search query
    */
    public function deleteAction(Request $request)
    {
        // Get selected chat threads
        $threads = $this->getThreads($request);
        if (count($threads)) {
            // Delete selected threads
            $db = Database::getInstance();
            foreach ($threads as $thread) {
                $db->query("DELETE FROM {message} WHERE threadid = ?", array($thread['threadid']));
                $db->query("DELETE FROM {thread} WHERE threadid = ?", array($thread['threadid']));
            }
        }
        // Compose and make redirection
        $qs = $request->getQueryString();
        $qs = $qs ? '?' . $qs : '';
        return $this->redirect($this->generateUrl('history') . $qs);
    }

    /**
    * Get all chat threads selected with the search query from the request
    *
    * @param  Request  $request
    * @return array Selected threads (only IDs)
    */
    protected function getThreads(Request $request)
    {

        // Initial code for the threads selection was taken from
        // @see \Mibew\Controller\HistoryController
        $threads = array();

        $operator = $this->getOperator();
        $query = $request->query->get('q', false);

        $search_type = $request->query->get('type');
        if (!in_array($search_type, array('all', 'message', 'operator', 'visitor'))) {
            $search_type = 'all';
        }

        $search_in_system_messages = ($request->query->get('insystemmessages') == 'on') || !$query;

        if ($query !== false && is_capable(CAN_ADMINISTRATE, $operator)) {
            // Escape MySQL LIKE wildcards in the query
            $escaped_query = str_replace(array('%', '_'), array('\\%', '\\_'), $query);
            // Replace commonly used "?" and "*" wildcards with MySQL ones.
            $escaped_query = str_replace(array('*', '?'), array('%', '_'), $escaped_query);

            $db = Database::getInstance();
            $groups = $db->query(
                ("SELECT {opgroup}.groupid AS groupid, vclocalname " .
                    "FROM {opgroup} " .
                    "ORDER BY vclocalname"),
                null,
                array('return_rows' => Database::RETURN_ALL_ROWS)
            );

            $group_name = array();
            foreach ($groups as $group) {
                $group_name[$group['groupid']] = $group['vclocalname'];
            }

            $values = array(
                ':query' => "%{$escaped_query}%",
                ':invitation_accepted' => Thread::INVITATION_ACCEPTED,
                ':invitation_not_invited' => Thread::INVITATION_NOT_INVITED,
            );

            $search_conditions = array();
            if ($search_type == 'message' || $search_type == 'all') {
                $search_conditions[] = "({message}.tmessage LIKE :query"
                    . ($search_in_system_messages
                        ? ''
                        : " AND ({message}.ikind = :kind_user OR {message}.ikind = :kind_agent)")
                    . ")";
                if (!$search_in_system_messages) {
                    $values[':kind_user'] = Thread::KIND_USER;
                    $values[':kind_agent'] = Thread::KIND_AGENT;
                }
            }
            if ($search_type == 'operator' || $search_type == 'all') {
                $search_conditions[] = "({thread}.agentname LIKE :query)";
            }
            if ($search_type == 'visitor' || $search_type == 'all') {
                $search_conditions[] = "({thread}.username LIKE :query)";
                $search_conditions[] = "({thread}.remote LIKE :query)";
            }

            // Get ids of threads to delete
            $threads = $db->query(
                ("SELECT DISTINCT {thread}.threadid "
                . "FROM {thread}, {message} "
                . "WHERE {message}.threadid = {thread}.threadid "
                    . "AND ({thread}.invitationstate = :invitation_accepted "
                        . "OR {thread}.invitationstate = :invitation_not_invited) "
                    . "AND (" . implode(' OR ', $search_conditions) . ")"),
                $values,
                array('return_rows' => Database::RETURN_ALL_ROWS)
            );
        }

        return $threads;
    }
}
