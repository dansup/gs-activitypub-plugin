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
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2015 Free Software Foundaction, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://gnu.io/social
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class Activitypub_notice extends Managed_DataObject
{

  public static function noticeToObject($notice)
  {
    $attachments = [];
    foreach ($notice->attachments () as $attachment)
    {
      $attachments[] = Activitypub_attachment::attachmentToObject ($attachment);
    }

    $tags = [];
    foreach ($notice->getTags () as $tag)
    {
      $tags[] = Activitypub_tag::tagNameToObject ($tag);
    }
    
    // todo: fix timestamp formats
    $item = [
      'id'           => $notice->getUrl(),
      'type'         => 'Notice',                 // TODO: handle other types
      'actor'        => $notice->getProfile()->getUrl(),
      'published'    => $notice->getCreated (),
      'to'           => [
                                                  // TODO: handle proper scope
                          'https://www.w3.org/ns/activitystreams#Public'
                        ],
      'cc'           => [
                                                  // TODO: add cc's
                          "{$notice->getProfile()->getUrl()}/subscribers",
                        ],
      'content'      => $notice->getContent(),
      'rendered'     => $notice->getRendered(),
      'url'          => $notice->getUrl(),
      'reply_to'     => empty($notice->reply_to) ? null : Notice::getById($notice->reply_to)->getUrl(),
      'is_local'     => $notice->isLocal(),
      'conversation' => intval($notice->conversation),
      'attachment'   => $attachments,
      'tag'          => $tags
    ];
    
    return $item;
  }
}
