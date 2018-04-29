<?php
/**
 * GNU social - a federating social network
 *
 * Todo: Description
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @copyright 2015 Free Software Foundaction, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://gnu.io/social
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ActorOutboxAction extends ManagedAction
{
    protected $needLogin = false;
    protected $canPost   = true;

    protected function handle()
    {
      $nickname = $this->trimmed('nickname');
      $res = $this->generateOutbox($nickname);
      header('Content-Type: application/json');
      echo $this->output($res);
    }

    protected function output($res)
    {
      if(!empty($_GET['pretty']) && $_GET['pretty'] == true) {
        $res = json_encode($res, JSON_PRETTY_PRINT);
      } else {
        $res = json_encode($res);
      }
      return $res;
    }

    protected function generateOutbox($nickname)
    {
      try {
        $user = User::getByNickname($nickname);
        $profile = $user->getProfile();
        $url = $profile->profileurl;
      } catch (Exception $e) {
        throw new \Exception('Invalid username');
      }

      $notices = [];
      $notice = $profile->getNotices();
      while ($notice->fetch()) {
        $note = $notice;

        // TODO: Handle other types
        if($note->object_type == 'http://activitystrea.ms/schema/1.0/note') {
          $notices[] = $this->noticeToObject($note);
        }
      }

      $res = [
        '@context' => [
          "https://www.w3.org/ns/activitystreams",
          [
            "@language" => "en"
          ]
        ],
        'id' => "{$url}/outbox.json",
        'type'  => 'OrderedCollection',
        'totalItems' => $profile->noticeCount(),
        'orderedItems' => $notices
      ];
      return $res;
    }

    protected function noticeToObject($notice)
    {
      // todo: fix timestamp formats
      $item = [
        'id'  => $notice->getUrl(),
        // TODO: handle other types
        'type' => 'Create',
        'actor' => $notice->getProfile()->getUrl(),
        'published' => $notice->created,
        'to' => [
          'https://www.w3.org/ns/activitystreams#Public'
        ],
        'cc' => [
          "{$notice->getProfile()->getUrl()}/subscribers",
        ],
        'object' => [
          'id' => $notice->getUrl(),

          // TODO: handle other types
          'type' => 'Note',

          // XXX: CW Title
          'summary' => null,
          'content' => $notice->getRendered(),
          'inReplyTo' => empty($notice->reply_to) ? null : Notice::getById($notice->reply_to)->getUrl(),

          // TODO: fix date format
          'published' => $notice->created,
          'url' => $notice->getUrl(),
          'attributedTo' => $notice->getProfile()->getUrl(),
          'to' => [
            // TODO: handle proper scope
            'https://www.w3.org/ns/activitystreams#Public'
          ],
          'cc' => [
            // TODO: add cc's
            "{$notice->getProfile()->getUrl()}/subscribers",
          ],
          'sensitive' => null,
          'atomUri' => $notice->getUrl(),
          'inReplyToAtomUri' => null,
          'conversation' => $notice->getUri(),
          'attachment' => [],
          'tag' => []
        ]
      ];
      return $item;
    }

}
