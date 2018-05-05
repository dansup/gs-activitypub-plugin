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
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @copyright 2015 Free Software Foundaction, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://gnu.io/social
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class Activitypub_attachment extends Managed_DataObject
{

  public static function attachmentToObject($attachment)
  {
      $res = [
        '@context'          => [
          "https://www.w3.org/ns/activitystreams",
          [
            "@language" => "en"
          ]
        ],
        'id'        => $attachment->getID (),
        'mimetype'  => $attachment->mimetype,
        'url'       => $attachment->getUrl (),
        'size'      => intval($attachment->size), // $attachment->getSize ()
        'title'     => $attachment->getTitle (),
        'meta'      => null
      ];
      
      // Image
      if (substr($res["mimetype"], 0, 5) == "image")
      {
        $res["meta"]= [
          'width'  => $attachment->width,
          'height' => $attachment->height
        ];
      }

    return $res;
  }
}
