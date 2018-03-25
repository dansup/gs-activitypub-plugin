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

class ActivityPubActorAction extends ManagedAction
{
    protected $needLogin = false;
    protected $canPost   = true;

    protected function handle()
    {
        $user = User::getByID($this->trimmed('id'));
        $profile = $user->getProfile();

        $res = [
          '@context' => ["https://www.w3.org/ns/activitystreams"],
          'id' => $profile->profileurl,
          'type' => 'Person',
          'following' => null,
          'followers' => null,
          'inbox'     => null,
          'outbox'    => null,
          'liked'     => null,
          'featured'  => null,
          'preferredUsername' => $user->nickname,
          'name'      => $user->nickname,
          'summary'   => $profile->bio,
          'url'   => $profile->profileurl
        ];

        header('Content-Type: application/json');
        
        echo json_encode($res, JSON_PRETTY_PRINT);
    }
}
