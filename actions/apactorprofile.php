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

class apActorProfileAction extends ManagedAction
{    
    protected $needLogin = false;
    protected $canPost   = true;

    protected function handle()
    {
        $nickname = $this->trimmed('nickname');
        try {
          $user = User::getByNickname($nickname);
          $profile = $user->getProfile();
        } catch (Exception $e) {
          throw new \Exception('Invalid username');
        }

        header('Content-Type: application/activity+json');

        $res = Activitypub_profile::profileToObject($profile);

        echo json_encode($res, JSON_UNESCAPED_SLASHES | (isset($_GET["pretty"]) ? JSON_PRETTY_PRINT : null));
    }
}
